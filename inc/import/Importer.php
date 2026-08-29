<?php
declare(strict_types=1);

require_once __DIR__ . '/XlsxReader.php';

/**
 * Importerer projekter fra "Projektoversigt"-regnearket (ark "Projekter",
 * data fra række 4) ind i projekter-tabellen, med tilhørende virksomheder,
 * kontaktpersoner og BMS-ansvarlige.
 *
 * Kørsel sker altid i én databasetransaktion. Ved dry-run køres nøjagtig
 * samme logik, men transaktionen rulles tilbage til sidst i stedet for at
 * blive committet - så en dry-run-opsummering er 100% retvisende for, hvad
 * en rigtig import ville gøre.
 *
 * Idempotens: hver importeret projektrække får import_kilde + import_raekke
 * sat. En efterfølgende import af samme fil genkender allerede importerede
 * rækker på denne kombination og opdaterer i stedet for at oprette på ny.
 */
class Importer
{
    public const IMPORT_KILDE = 'excel-projektoversigt-region-oest';
    private const ARK_NAVN = 'Projekter';
    private const FOERSTE_DATARAEKKE = 4;
    private const HJAELPEARK = ['1', '2', '3', '4'];

    private PDO $pdo;
    private XlsxReader $reader;

    /** @var array<string,int> cache af bruger-id pr. initialer (opslag under kørslen) */
    private array $brugerCache = [];
    /** @var array<int,bool> hvilke hjælpeark (1-4) der faktisk er refereret fra en projektrække */
    private array $hjaelpearkBrugt = [];

    public function __construct(PDO $pdo, XlsxReader $reader)
    {
        $this->pdo = $pdo;
        $this->reader = $reader;
    }

    /**
     * @return array{nye:int,opdaterede:int,oversprungne:int,advarsler:list<string>,fejl:list<string>,raekker:list<array>}
     */
    public function koer(bool $dryRun): array
    {
        if (!$this->reader->harArk(self::ARK_NAVN)) {
            return [
                'nye' => 0, 'opdaterede' => 0, 'oversprungne' => 0,
                'advarsler' => [], 'raekker' => [],
                'fejl' => ['Regnearket indeholder ikke et ark ved navn "' . self::ARK_NAVN . '".'],
            ];
        }

        $raekkerXls = $this->reader->laesArk(self::ARK_NAVN);
        $sidsteRaekke = $raekkerXls ? max(array_keys($raekkerXls)) : 0;

        $resultat = ['nye' => 0, 'opdaterede' => 0, 'oversprungne' => 0, 'advarsler' => [], 'fejl' => [], 'raekker' => []];
        $this->hjaelpearkBrugt = [];

        $this->pdo->beginTransaction();
        try {
            for ($raekkeNr = self::FOERSTE_DATARAEKKE; $raekkeNr <= $sidsteRaekke; $raekkeNr++) {
                $raa = $raekkerXls[$raekkeNr] ?? [];
                if ($this->raekkeErTom($raa)) {
                    continue;
                }
                $this->importerRaekke($raekkeNr, $raa, $resultat);
            }

            foreach (self::HJAELPEARK as $arkNavn) {
                if ($this->reader->harArk($arkNavn) && empty($this->hjaelpearkBrugt[$arkNavn])) {
                    $resultat['advarsler'][] = "Hjælpeark \"$arkNavn\" er ikke refereret fra nogen projektrække "
                        . 'og er ikke importeret automatisk. Gennemgås og tilknyttes evt. manuelt.';
                }
            }

            if ($dryRun) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $resultat['fejl'][] = 'Import afbrudt: ' . $e->getMessage();
        }

        return $resultat;
    }

    private function raekkeErTom(array $raa): bool
    {
        foreach ($raa as $vaerdi) {
            if ($vaerdi !== null && $vaerdi !== '') {
                return false;
            }
        }
        return true;
    }

    private function importerRaekke(int $raekkeNr, array $raa, array &$resultat): void
    {
        $tekst = function (string $kol) use ($raa): string {
            $v = $raa[$kol] ?? null;
            if ($v instanceof DateTimeImmutable) {
                return $v->format('Y-m-d');
            }
            return trim((string)($v ?? ''));
        };
        $advar = function (string $besked) use (&$resultat, $raekkeNr): void {
            $resultat['advarsler'][] = "Række $raekkeNr: $besked";
        };

        $navn = $tekst('D');
        if ($navn === '') {
            $resultat['oversprungne']++;
            $advar('Springet over - "Projekt-/sagsnavn" (kolonne D) er tomt.');
            return;
        }

        $raaData = [];

        // A: Dato -> Tilføjet dato.
        $tilfoejetDato = null;
        $datoVaerdi = $raa['A'] ?? null;
        if ($datoVaerdi instanceof DateTimeImmutable) {
            $tilfoejetDato = $datoVaerdi->format('Y-m-d H:i:s');
        } elseif ($tekst('A') !== '') {
            $raaData['A'] = $tekst('A');
            $advar('Kunne ikke tolke "Dato" (kolonne A) som en dato: "' . $tekst('A') . '".');
        }

        // B: Init. -> Tilføjet af.
        $tilfoejetAfId = null;
        $initB = $tekst('B');
        if ($initB !== '') {
            if (preg_match('/^[A-ZÆØÅa-zæøå]{2,5}$/u', $initB)) {
                $tilfoejetAfId = $this->findEllerOpretBruger($initB);
            } else {
                $raaData['B'] = $initB;
                $advar('"Init." (kolonne B, tilføjet af) ser ikke ud som rene initialer: "' . $initB . '".');
            }
        }

        $lead = $tekst('C') ?: null;

        // E: Sum i mio -> projektsum i kr.
        $projektsum = null;
        $sumRaa = $raa['E'] ?? null;
        if ($sumRaa !== null && $sumRaa !== '') {
            if (is_numeric($sumRaa)) {
                $sum = (float)$sumRaa * 1_000_000;
                if ($sum < 0) {
                    $advar('"Sum i mio" (kolonne E) er negativ og er ikke importeret: "' . $sumRaa . '".');
                    $raaData['E'] = (string)$sumRaa;
                } else {
                    $projektsum = $sum;
                }
            } else {
                $raaData['E'] = (string)$sumRaa;
                $advar('"Sum i mio" (kolonne E) er ikke et tal: "' . $sumRaa . '".');
            }
        }

        // F: "Adresse - Postnr" -> adresse + postnummer.
        [$adresse, $postnummer] = $this->delAdressePostnummer($tekst('F'));
        $by = $tekst('G') ?: null;
        $stadie = $tekst('H') ?: null;
        $enterpriseform = $tekst('I') ?: null;

        // J/K: Byggestart / Byggeslut -> YYYY-MM.
        $byggestartMaaned = $this->normaliserMaanedSikkert($tekst('J'), 'Byggestart (kolonne J)', $raekkeNr, $resultat, $raaData, 'J');
        $byggeslutMaaned = $this->normaliserMaanedSikkert($tekst('K'), 'Byggeslut (kolonne K)', $raekkeNr, $resultat, $raaData, 'K');

        // M: Noter -> legacy-noter, evt. via reference til hjælpeark 1-4.
        $legacyNoter = $this->udledLegacyNoter($tekst('M'), $advar);

        // P/R: Antal plan / Sum af Boliger.
        $antalPlan = $this->tilHeltalEllerNull($tekst('P'), 'Antal plan (kolonne P)', $advar, $raaData, 'P');
        $antalBoliger = $this->tilHeltalEllerNull($tekst('R'), 'Sum af Boliger (kolonne R)', $advar, $raaData, 'R');

        // Q: Kælder (Ja/Nej).
        $kaelder = null;
        $kaelderRaa = $tekst('Q');
        if ($kaelderRaa !== '') {
            $normaliseret = mb_strtolower($kaelderRaa);
            if ($normaliseret === 'ja') {
                $kaelder = 'Ja';
            } elseif ($normaliseret === 'nej') {
                $kaelder = 'Nej';
            } else {
                $raaData['Q'] = $kaelderRaa;
                $advar('"Kælder" (kolonne Q) er hverken Ja eller Nej: "' . $kaelderRaa . '".');
            }
        }

        // S: Åben/lukket.
        $aabenlukket = 'Åben';
        $aabenlukketRaa = $tekst('S');
        if ($aabenlukketRaa !== '') {
            if (in_array($aabenlukketRaa, AABENLUKKET_LISTE, true)) {
                $aabenlukket = $aabenlukketRaa;
            } else {
                $raaData['S'] = $aabenlukketRaa;
                $advar('"Åben / lukket" (kolonne S) er ukendt værdi, sat til "Åben": "' . $aabenlukketRaa . '".');
            }
        }

        // T: Vundet/tabt -> salgsresultat.
        $salgsresultat = 'Ikke afgjort';
        $tRaa = $tekst('T');
        if ($tRaa !== '') {
            $normaliseret = preg_replace('/\s*\/\s*/', '/', $tRaa);
            if (in_array($normaliseret, SALGSRESULTAT_LISTE, true)) {
                $salgsresultat = $normaliseret;
            } else {
                $raaData['T'] = $tRaa;
                $advar('"Vundet / tabt" (kolonne T) er ukendt værdi, sat til "Ikke afgjort": "' . $tRaa . '".');
            }
        }

        // U: Årsag hvis tabt -> tabt_aarsag (systemets tre valgmuligheder har
        // forrang frem for hjælpearkets egen liste, fx "Kvalitet").
        $tabtAarsag = null;
        $tabtAarsagNote = null;
        $uRaa = $tekst('U');
        if ($salgsresultat === 'Tabt') {
            if ($uRaa === '') {
                $advar('Salgsresultat er "Tabt", men "Årsag hvis tabt" (kolonne U) er tom.');
            } elseif (in_array($uRaa, TABT_AARSAG_LISTE, true)) {
                $tabtAarsag = $uRaa;
            } else {
                $tabtAarsag = 'Andet';
                $tabtAarsagNote = mb_substr($uRaa, 0, 500);
                $advar('"Årsag hvis tabt" (kolonne U) er uden for de tilladte valg og er lagt i "Andet": "' . $uRaa . '".');
            }
        } elseif ($uRaa !== '') {
            $raaData['U'] = $uRaa;
        }

        $noter = $tekst('V') ?: null;
        $eksternLink = $tekst('W') ?: null;

        $data = [
            'navn' => $navn,
            'lead' => $lead,
            'adresse' => $adresse,
            'postnummer' => $postnummer,
            'by_navn' => $by,
            'stadie' => $stadie,
            'enterpriseform' => $enterpriseform,
            'byggestart_maaned' => $byggestartMaaned,
            'byggestart_bekraeftet' => 0,
            'byggeslut_maaned' => $byggeslutMaaned,
            'aabenlukket' => $aabenlukket,
            'salgsresultat' => $salgsresultat,
            'tabt_aarsag' => $tabtAarsag,
            'tabt_aarsag_note' => $tabtAarsagNote,
            'projektsum' => $projektsum,
            'noter' => $noter,
            'antal_plan' => $antalPlan,
            'kaelder' => $kaelder,
            'antal_boliger' => $antalBoliger,
            'ekstern_link' => $eksternLink,
            'legacy_noter' => $legacyNoter,
        ];

        $projektId = $this->gemProjekt($raekkeNr, $data, $tilfoejetDato, $tilfoejetAfId, $raaData, $resultat);

        // L: Entreprenør / Kunde -> virksomhedstilknytning.
        $this->importerVirksomhed($projektId, $tekst('L'), $advar);

        // N: Kontaktperson.
        $this->importerKontaktperson($projektId, $tekst('N'), $advar);

        // O: BMS ansvarlig -> primær + medansvarlige.
        $this->importerAnsvarlige($projektId, $tekst('O'), $advar);
    }

    private function gemProjekt(
        int $raekkeNr,
        array $data,
        ?string $tilfoejetDato,
        ?int $tilfoejetAfId,
        array $raaData,
        array &$resultat
    ): int {
        $data['import_kilde'] = self::IMPORT_KILDE;
        $data['import_raekke'] = $raekkeNr;
        $data['import_raa_data'] = $raaData ? json_encode($raaData, JSON_UNESCAPED_UNICODE) : null;

        $find = $this->pdo->prepare('SELECT id FROM projekter WHERE import_kilde = ? AND import_raekke = ?');
        $find->execute([self::IMPORT_KILDE, $raekkeNr]);
        $eksisterende = $find->fetch();

        $kolonner = array_keys($data);
        if ($eksisterende) {
            $id = (int)$eksisterende['id'];
            $sæt = implode(', ', array_map(fn ($k) => "$k = ?", $kolonner));
            $stmt = $this->pdo->prepare("UPDATE projekter SET $sæt WHERE id = ?");
            $stmt->execute([...array_values($data), $id]);
            $resultat['opdaterede']++;
            $resultat['raekker'][] = ['raekke' => $raekkeNr, 'handling' => 'opdateret', 'navn' => $data['navn']];
        } else {
            $indsaetKolonner = $kolonner;
            $indsaetVaerdier = array_values($data);
            if ($tilfoejetDato !== null) {
                $indsaetKolonner[] = 'oprettet';
                $indsaetVaerdier[] = $tilfoejetDato;
            }
            if ($tilfoejetAfId !== null) {
                $indsaetKolonner[] = 'oprettet_af';
                $indsaetVaerdier[] = $tilfoejetAfId;
                $indsaetKolonner[] = 'aendret_af';
                $indsaetVaerdier[] = $tilfoejetAfId;
            }
            $pladsholdere = implode(', ', array_fill(0, count($indsaetKolonner), '?'));
            $kolonneListe = implode(', ', $indsaetKolonner);
            $stmt = $this->pdo->prepare("INSERT INTO projekter ($kolonneListe) VALUES ($pladsholdere)");
            $stmt->execute($indsaetVaerdier);
            $id = (int)$this->pdo->lastInsertId();
            $resultat['nye']++;
            $resultat['raekker'][] = ['raekke' => $raekkeNr, 'handling' => 'ny', 'navn' => $data['navn']];
        }
        return $id;
    }

    private function normaliserMaanedSikkert(
        string $raa,
        string $feltLabel,
        int $raekkeNr,
        array &$resultat,
        array &$raaData,
        string $kolonne
    ): ?string {
        if ($raa === '') {
            return null;
        }
        try {
            return normaliser_maaned($raa);
        } catch (InvalidArgumentException $e) {
            $raaData[$kolonne] = $raa;
            $resultat['advarsler'][] = "Række $raekkeNr: $feltLabel kunne ikke tolkes som måned/år: \"$raa\".";
            return null;
        }
    }

    private function tilHeltalEllerNull(string $raa, string $feltLabel, callable $advar, array &$raaData, string $kolonne): ?int
    {
        if ($raa === '') {
            return null;
        }
        if (is_numeric($raa) && (float)$raa == (int)(float)$raa) {
            return max(0, (int)$raa);
        }
        $raaData[$kolonne] = $raa;
        $advar("$feltLabel er ikke et helt tal: \"$raa\".");
        return null;
    }

    /**
     * Splitter "Adresse - Postnr", fx "Ahorn Alle 2-44, 4100", i adresse og
     * postnummer. Kun et afsluttende 4-cifret tal genkendes sikkert som
     * postnummer - ellers bevares hele teksten som adresse.
     *
     * @return array{0:?string,1:?string}
     */
    private function delAdressePostnummer(string $raa): array
    {
        if ($raa === '') {
            return [null, null];
        }
        if (preg_match('/^(.*?)[,\s]+(\d{4})$/', $raa, $m)) {
            return [trim($m[1]) ?: null, $m[2]];
        }
        return [$raa, null];
    }

    /**
     * Kolonne M ("Noter") indeholder enten fritekst eller en talreference
     * (1-4) til et hjælpeark. Hjælpearkets fulde indhold hentes ind som
     * legacy-noter for projektet.
     */
    private function udledLegacyNoter(string $raa, callable $advar): ?string
    {
        if ($raa === '') {
            return null;
        }
        if (in_array($raa, self::HJAELPEARK, true) && $this->reader->harArk($raa)) {
            $this->hjaelpearkBrugt[$raa] = true;
            $tekstLinjer = [];
            foreach ($this->reader->laesArk($raa) as $celleRaekke) {
                foreach ($celleRaekke as $vaerdi) {
                    if ($vaerdi === null || $vaerdi === '') {
                        continue;
                    }
                    $tekstLinjer[] = $vaerdi instanceof DateTimeImmutable ? $vaerdi->format('Y-m-d') : (string)$vaerdi;
                }
            }
            return "[Fra hjælpeark \"$raa\"]\n" . implode("\n", $tekstLinjer);
        }
        return $raa;
    }

    /**
     * Finder eller opretter en bruger ud fra rene initialer. Bruges kun når
     * værdien allerede er valideret til at se ud som initialer.
     */
    private function findEllerOpretBruger(string $initialer): int
    {
        $noegle = mb_strtoupper($initialer);
        if (isset($this->brugerCache[$noegle])) {
            return $this->brugerCache[$noegle];
        }
        $eksisterende = hent_bruger_ved_initialer($this->pdo, $initialer);
        if ($eksisterende) {
            $this->brugerCache[$noegle] = (int)$eksisterende['id'];
            return $this->brugerCache[$noegle];
        }
        $stmt = $this->pdo->prepare('INSERT INTO brugere (initialer, navn, rolle, aktiv) VALUES (?, ?, ?, 1)');
        $stmt->execute([$initialer, $initialer, ROLLE_LAESER]);
        $id = (int)$this->pdo->lastInsertId();
        $this->brugerCache[$noegle] = $id;
        return $id;
    }

    private function importerVirksomhed(int $projektId, string $raa, callable $advar): void
    {
        if ($raa === '') {
            return;
        }
        $segmenter = preg_split('/\s*,\s*|\s+\+\s+/', $raa) ?: [$raa];
        $segmenter = array_values(array_filter(array_map('trim', $segmenter), fn ($s) => $s !== ''));

        $alleSikre = $segmenter && array_reduce($segmenter, fn ($ok, $s) => $ok && $this->virksomhedsnavnSerSikkertUd($s), true);

        $navneListe = $alleSikre ? $segmenter : [$raa];
        if (!$alleSikre) {
            $advar('"Entreprenør / Kunde" (kolonne L) kunne ikke opdeles sikkert i flere virksomheder og er importeret som ét navn - bør gennemgås manuelt: "' . $raa . '".');
        }

        foreach ($navneListe as $navn) {
            $navn = trim($navn, " \t\n\r\0\x0B.");
            if ($navn === '') {
                continue;
            }
            $virksomhed = find_eller_opret_virksomhed($this->pdo, $navn);
            $stmt = $this->pdo->prepare(
                'INSERT INTO projekt_virksomheder (projekt_id, virksomhed_id, rolle) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE rolle = VALUES(rolle)'
            );
            $stmt->execute([$projektId, $virksomhed['id'], 'Hovedentreprenør']);
        }
    }

    private function virksomhedsnavnSerSikkertUd(string $navn): bool
    {
        if (mb_strlen($navn) > 60) {
            return false;
        }
        $advarselsord = '/\b(der|som|har|er|på|til|fra|og med|hyret|udfører|udføres|bruger|kigger|hører|leverer|monteret|hejs)\b/iu';
        return preg_match($advarselsord, $navn) !== 1;
    }

    private function importerKontaktperson(int $projektId, string $raa, callable $advar): void
    {
        // Kontaktpersoner har (i modsætning til virksomheder/ansvarlige)
        // ingen naturlig nøgle at lave "ON DUPLICATE KEY" på. For at holde
        // gentagne importkørsler idempotente genskabes projektets
        // kontaktpersoner derfor fuldt ud fra kildekolonnen ved hver import
        // af rækken - samme mønster som projekt-gem.php bruger ved almindelig
        // redigering.
        $this->pdo->prepare('DELETE FROM kontaktpersoner WHERE projekt_id = ?')->execute([$projektId]);
        if ($raa === '') {
            return;
        }
        $segmenter = preg_split('/\s*\beller\b\s*|;\s*/iu', $raa) ?: [$raa];
        $primaerSat = false;

        foreach ($segmenter as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $email = null;
            if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $segment, $m)) {
                $email = $m[0];
            }
            $telefon = null;
            if (preg_match('/\b\d[\d\s]{5,12}\d\b/', $segment, $m)) {
                $telefon = trim($m[0]);
            }

            $navn = $segment;
            if ($email) {
                $navn = str_replace($email, '', $navn);
            }
            if ($telefon) {
                $navn = str_replace($telefon, '', $navn);
            }
            $navn = trim($navn, " \t\n\r\0\x0B,-");
            if ($navn === '') {
                if ($email || $telefon) {
                    $navn = $email ?? $telefon;
                } else {
                    continue;
                }
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO kontaktpersoner (projekt_id, navn, telefon, email, primaer) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$projektId, mb_substr($navn, 0, 150), $telefon, $email, $primaerSat ? 0 : 1]);
            $primaerSat = true;
        }
    }

    private function importerAnsvarlige(int $projektId, string $raa, callable $advar): void
    {
        if ($raa === '') {
            return;
        }
        $dele = array_values(array_filter(array_map('trim', explode('/', $raa)), fn ($s) => $s !== ''));

        $alleGyldige = $dele && array_reduce(
            $dele,
            fn ($ok, $d) => $ok && preg_match('/^[A-ZÆØÅa-zæøå]{2,5}$/u', $d) === 1,
            true
        );

        if (!$alleGyldige) {
            $advar('"BMS ansvarlig" (kolonne O) kunne ikke tolkes sikkert som initialer og er ikke tilknyttet automatisk: "' . $raa . '".');
            return;
        }

        $primaer = true;
        foreach ($dele as $initialer) {
            $brugerId = $this->findEllerOpretBruger($initialer);
            $stmt = $this->pdo->prepare(
                'INSERT INTO projekt_ansvarlige (projekt_id, bruger_id, primaer) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE primaer = VALUES(primaer)'
            );
            $stmt->execute([$projektId, $brugerId, $primaer ? 1 : 0]);
            $primaer = false;
        }
    }
}
