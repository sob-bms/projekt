<?php
declare(strict_types=1);

// Selve gemme-logikken for et projekt, udskilt fra projekt-gem.php så den
// kan kaldes direkte (og dermed testes) uden en rigtig HTTP-request.

class ProjektValideringsFejl extends RuntimeException
{
}

/**
 * Validerer og gemmer et projekt ud fra formular-lignende data (samme
 * feltnavne som $_POST i projekt-form.php). Håndterer oprettelse eller
 * opdatering, audit-felter (tilføjet/ændret af/dato), historik-logning samt
 * synkronisering af ansvarlige, virksomheder og kontaktpersoner.
 *
 * @param array $post Formular-data (som $_POST)
 * @param int $brugerId Den aktuelt indloggede brugers id (bruges til audit-felter)
 * @return int Projektets id
 * @throws ProjektValideringsFejl ved ugyldige data
 */
function gem_projekt_fra_formular(PDO $pdo, array $post, int $brugerId): int
{
    $id = isset($post['id']) && $post['id'] !== '' ? (int)$post['id'] : null;
    $navn = trim($post['navn'] ?? '');
    if ($navn === '') {
        throw new ProjektValideringsFejl('Projekt-/sagsnavn er påkrævet.');
    }

    $projektsumRaa = trim((string)($post['projektsum'] ?? ''));
    $projektsum = null;
    if ($projektsumRaa !== '') {
        if (!is_numeric($projektsumRaa)) {
            throw new ProjektValideringsFejl('Projektsum skal være et tal.');
        }
        $projektsum = (float)$projektsumRaa;
        if ($projektsum < 0) {
            throw new ProjektValideringsFejl('Projektsum må ikke være negativ.');
        }
    }

    try {
        $byggestartMaaned = normaliser_maaned($post['byggestart_maaned'] ?? '');
        $byggeslutMaaned = normaliser_maaned($post['byggeslut_maaned'] ?? '');
    } catch (InvalidArgumentException $e) {
        throw new ProjektValideringsFejl('Ugyldig byggestart/byggeslut: ' . $e->getMessage());
    }

    $aabenlukket = in_array($post['aabenlukket'] ?? '', AABENLUKKET_LISTE, true) ? $post['aabenlukket'] : 'Åben';
    $salgsresultat = in_array($post['salgsresultat'] ?? '', SALGSRESULTAT_LISTE, true) ? $post['salgsresultat'] : 'Ikke afgjort';

    // Tabt årsag er kun relevant (og skal kun kunne sættes) når resultatet er Tabt.
    $tabtAarsag = null;
    $tabtAarsagNote = null;
    if ($salgsresultat === 'Tabt') {
        $tabtAarsag = in_array($post['tabt_aarsag'] ?? '', TABT_AARSAG_LISTE, true) ? $post['tabt_aarsag'] : null;
        if ($tabtAarsag === 'Andet') {
            $tabtAarsagNote = trim($post['tabt_aarsag_note'] ?? '') ?: null;
        }
    }

    $antalPlan = trim((string)($post['antal_plan'] ?? '')) !== '' ? max(0, (int)$post['antal_plan']) : null;
    $antalBoliger = trim((string)($post['antal_boliger'] ?? '')) !== '' ? max(0, (int)$post['antal_boliger']) : null;
    $kaelder = in_array($post['kaelder'] ?? '', ['Ja', 'Nej'], true) ? $post['kaelder'] : null;

    $data = [
        'navn' => $navn,
        'lead' => trim($post['lead'] ?? '') ?: null,
        'adresse' => trim($post['adresse'] ?? '') ?: null,
        'postnummer' => trim($post['postnummer'] ?? '') ?: null,
        'by_navn' => trim($post['by_navn'] ?? '') ?: null,
        'stadie' => trim($post['stadie'] ?? '') ?: null,
        'enterpriseform' => trim($post['enterpriseform'] ?? '') ?: null,
        'byggestart_maaned' => $byggestartMaaned,
        'byggestart_bekraeftet' => !empty($post['byggestart_bekraeftet']) ? 1 : 0,
        'byggeslut_maaned' => $byggeslutMaaned,
        'aabenlukket' => $aabenlukket,
        'salgsresultat' => $salgsresultat,
        'tabt_aarsag' => $tabtAarsag,
        'tabt_aarsag_note' => $tabtAarsagNote,
        'projektsum' => $projektsum,
        'noter' => trim($post['noter'] ?? '') ?: null,
        'antal_plan' => $antalPlan,
        'kaelder' => $kaelder,
        'antal_boliger' => $antalBoliger,
        'ekstern_link' => trim($post['ekstern_link'] ?? '') ?: null,
    ];

    // Historik-relevante felter: log før/efter for de vigtigste statusfelter.
    $historikFelter = ['aabenlukket', 'salgsresultat', 'projektsum', 'stadie', 'byggestart_maaned', 'tabt_aarsag'];

    $pdo->beginTransaction();
    try {
        if ($id) {
            $foer = $pdo->prepare('SELECT * FROM projekter WHERE id = ?');
            $foer->execute([$id]);
            $foerRaekke = $foer->fetch();
            if (!$foerRaekke) {
                throw new ProjektValideringsFejl('Projekt ikke fundet.');
            }

            $stmt = $pdo->prepare(
                'UPDATE projekter SET navn=?, lead=?, adresse=?, postnummer=?, by_navn=?, stadie=?, enterpriseform=?,
                 byggestart_maaned=?, byggestart_bekraeftet=?, byggeslut_maaned=?, aabenlukket=?, salgsresultat=?,
                 tabt_aarsag=?, tabt_aarsag_note=?, projektsum=?, noter=?, antal_plan=?, kaelder=?, antal_boliger=?,
                 ekstern_link=?, aendret_af=? WHERE id=?'
            );
            $stmt->execute([...array_values($data), $brugerId, $id]);

            foreach ($historikFelter as $felt) {
                // projektsum kommer fra databasen som DECIMAL-streng (fx
                // "12500000.00"), mens den nye værdi er en PHP float.
                // Normalisér begge til samme format, så uændrede beløb ikke
                // fejlagtigt logges som en ændring.
                if ($felt === 'projektsum') {
                    $gammelVaerdi = $foerRaekke[$felt] !== null ? number_format((float)$foerRaekke[$felt], 2, '.', '') : null;
                    $nyVaerdi = $data[$felt] !== null ? number_format((float)$data[$felt], 2, '.', '') : null;
                } else {
                    $gammelVaerdi = $foerRaekke[$felt] !== null ? (string)$foerRaekke[$felt] : null;
                    $nyVaerdi = $data[$felt] !== null ? (string)$data[$felt] : null;
                }
                log_felt_aendring($pdo, $id, $brugerId, $felt, $gammelVaerdi, $nyVaerdi);
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO projekter (navn, lead, adresse, postnummer, by_navn, stadie, enterpriseform,
                 byggestart_maaned, byggestart_bekraeftet, byggeslut_maaned, aabenlukket, salgsresultat,
                 tabt_aarsag, tabt_aarsag_note, projektsum, noter, antal_plan, kaelder, antal_boliger,
                 ekstern_link, oprettet_af, aendret_af)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([...array_values($data), $brugerId, $brugerId]);
            $id = (int)$pdo->lastInsertId();
        }

        // BMS-ansvarlige.
        $ansvarligIds = array_map('intval', $post['ansvarlig_id'] ?? []);
        $primaerAnsvarligId = isset($post['primaer_ansvarlig_id']) && $post['primaer_ansvarlig_id'] !== ''
            ? (int)$post['primaer_ansvarlig_id'] : null;
        synkroniser_ansvarlige($pdo, $id, $ansvarligIds, $primaerAnsvarligId);

        // Virksomhedstilknytninger.
        $virksomhedIds = $post['virksomhed_id'] ?? [];
        $virksomhedRoller = $post['virksomhed_rolle'] ?? [];
        $virksomhedFag = $post['virksomhed_fag'] ?? [];
        $virksomhedSum = $post['virksomhed_sum'] ?? [];
        $tilknytninger = [];
        foreach ($virksomhedIds as $i => $vId) {
            $tilknytninger[] = [
                'virksomhed_id' => (int)$vId,
                'rolle' => $virksomhedRoller[$i] ?? '',
                'fagomraade' => $virksomhedFag[$i] ?? '',
                'aftalt_sum' => $virksomhedSum[$i] ?? '',
            ];
        }
        synkroniser_virksomhedstilknytninger($pdo, $id, $tilknytninger);

        // Kontaktpersoner.
        $kontaktNavne = $post['kontakt_navn'] ?? [];
        $kontaktStillinger = $post['kontakt_stilling'] ?? [];
        $kontaktTelefoner = $post['kontakt_telefon'] ?? [];
        $kontaktEmails = $post['kontakt_email'] ?? [];
        $primaerKontaktIndex = $post['primaer_kontakt_index'] ?? null;
        $kontakter = [];
        foreach ($kontaktNavne as $i => $navnVaerdi) {
            $kontakter[] = [
                'navn' => $navnVaerdi,
                'stilling' => $kontaktStillinger[$i] ?? '',
                'telefon' => $kontaktTelefoner[$i] ?? '',
                'email' => $kontaktEmails[$i] ?? '',
                'primaer' => $primaerKontaktIndex !== null && (string)$i === (string)$primaerKontaktIndex,
            ];
        }
        synkroniser_kontaktpersoner($pdo, $id, $kontakter);

        $pdo->commit();
    } catch (ProjektValideringsFejl $e) {
        $pdo->rollBack();
        throw $e;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw new RuntimeException('Kunne ikke gemme projektet: ' . $e->getMessage(), 0, $e);
    }

    return $id;
}
