<?php
declare(strict_types=1);

/**
 * Minimal, afhængighedsfri .xlsx-læser (en .xlsx-fil er en zip med XML).
 * Bruger kun indbyggede PHP-udvidelser (ZipArchive + SimpleXML), så
 * projektet ikke behøver Composer/PhpSpreadsheet for at kunne importere.
 *
 * Understøtter det, importeren har brug for: liste af arknavne, læsning af
 * et ark som rækker af celler (indekseret ved kolonnebogstav), delte
 * strenge, og genkendelse af dato-formatterede celler (så Excel-datoer
 * konverteres korrekt til rigtige datoer).
 */
class XlsxReader
{
    private ZipArchive $zip;
    /** @var array<string,string> arknavn => intern sti (fx xl/worksheets/sheet1.xml) */
    private array $arkStier = [];
    /** @var list<string> arknavne i den rækkefølge, de står i projektmappen */
    private array $arkRaekkefoelge = [];
    /** @var list<string> delte strenge, indekseret 0..n */
    private array $delteStrenge = [];
    /** @var array<int,bool> cellXfs-indeks => er dato-format */
    private array $datoFormatPrStil = [];

    public function __construct(string $sti)
    {
        if (!is_file($sti)) {
            throw new RuntimeException("Filen findes ikke: $sti");
        }
        $this->zip = new ZipArchive();
        if ($this->zip->open($sti) !== true) {
            throw new RuntimeException("Kunne ikke åbne som .xlsx (zip): $sti");
        }
        $this->indlaesArkListe();
        $this->indlaesDelteStrenge();
        $this->indlaesStilarter();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /** @return list<string> */
    public function arknavne(): array
    {
        return $this->arkRaekkefoelge;
    }

    public function harArk(string $arknavn): bool
    {
        return isset($this->arkStier[$arknavn]);
    }

    /**
     * Læser et helt ark. Returnerer rækker indekseret fra 1 (som i Excel),
     * hver række er et associativt array [kolonnebogstav => værdi].
     * Tomme celler er ikke med i rækkens array (dvs. brug ?? null).
     *
     * @return array<int,array<string,mixed>>
     */
    public function laesArk(string $arknavn): array
    {
        if (!isset($this->arkStier[$arknavn])) {
            throw new RuntimeException("Ukendt ark: $arknavn");
        }
        $xmlIndhold = $this->zip->getFromName($this->arkStier[$arknavn]);
        if ($xmlIndhold === false) {
            throw new RuntimeException("Kunne ikke læse ark: $arknavn");
        }
        $sx = new SimpleXMLElement($xmlIndhold);
        $raekker = [];

        foreach ($sx->sheetData->row as $rowEl) {
            $rowNr = (int)$rowEl['r'];
            $raekke = [];
            foreach ($rowEl->c as $cellEl) {
                $ref = (string)$cellEl['r'];
                $kolonneBogstav = preg_replace('/\d+/', '', $ref);
                $raekke[$kolonneBogstav] = $this->laesCelle($cellEl);
            }
            $raekker[$rowNr] = $raekke;
        }
        return $raekker;
    }

    /**
     * Konverterer et kolonnebogstav (A, B, ..., Z, AA, ...) til et
     * 0-baseret indeks.
     */
    public static function kolonneTilIndeks(string $bogstav): int
    {
        $indeks = 0;
        foreach (str_split(strtoupper($bogstav)) as $tegn) {
            $indeks = $indeks * 26 + (ord($tegn) - ord('A') + 1);
        }
        return $indeks - 1;
    }

    private function laesCelle(SimpleXMLElement $cellEl): mixed
    {
        $type = (string)$cellEl['t'];
        $stilId = $cellEl['s'] !== null ? (int)$cellEl['s'] : null;

        if ($type === 's') {
            $indeks = (int)$cellEl->v;
            return $this->delteStrenge[$indeks] ?? null;
        }
        if ($type === 'inlineStr') {
            return (string)($cellEl->is->t ?? '');
        }
        if ($type === 'str') {
            return (string)$cellEl->v;
        }
        if ($type === 'b') {
            return ((string)$cellEl->v) === '1';
        }

        // Numerisk (evt. dato/tid gemt som Excel-serienummer).
        if (!isset($cellEl->v) || (string)$cellEl->v === '') {
            return null;
        }
        $vaerdi = (string)$cellEl->v;
        if (!is_numeric($vaerdi)) {
            return $vaerdi;
        }
        $tal = (float)$vaerdi;

        if ($stilId !== null && ($this->datoFormatPrStil[$stilId] ?? false)) {
            return self::excelSerielTilDato($tal);
        }
        // Returnér som int når det er et helt tal, ellers float.
        return $tal == (int)$tal ? (int)$tal : $tal;
    }

    /**
     * Konverterer et Excel-serienummer (dage siden 1899-12-30) til en
     * DateTimeImmutable. Understøtter tidsdelen (decimaler = tid på dagen).
     */
    public static function excelSerielTilDato(float $serienummer): DateTimeImmutable
    {
        $basis = new DateTimeImmutable('1899-12-30');
        $heleDage = (int)floor($serienummer);
        $sekunderPaaDagen = (int)round(($serienummer - $heleDage) * 86400);
        return $basis->modify("+$heleDage days")->modify("+$sekunderPaaDagen seconds");
    }

    private function indlaesArkListe(): void
    {
        $workbookXml = $this->zip->getFromName('xl/workbook.xml');
        $relsXml = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('Ugyldig .xlsx-fil: mangler workbook.xml eller rels.');
        }

        $rels = new SimpleXMLElement($relsXml);
        $stiPrRid = [];
        foreach ($rels->Relationship as $rel) {
            $stiPrRid[(string)$rel['Id']] = 'xl/' . ltrim((string)$rel['Target'], '/');
        }

        $wb = new SimpleXMLElement($workbookXml);
        $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        foreach ($wb->sheets->sheet as $sheetEl) {
            $navn = (string)$sheetEl['name'];
            $rid = (string)$sheetEl->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($stiPrRid[$rid])) {
                $this->arkStier[$navn] = $stiPrRid[$rid];
                $this->arkRaekkefoelge[] = $navn;
            }
        }
    }

    private function indlaesDelteStrenge(): void
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $sx = new SimpleXMLElement($xml);
        foreach ($sx->si as $si) {
            if (isset($si->t)) {
                $this->delteStrenge[] = (string)$si->t;
            } else {
                // Rich text (flere <r><t>-fragmenter) - sæt sammen.
                $tekst = '';
                foreach ($si->r as $r) {
                    $tekst .= (string)$r->t;
                }
                $this->delteStrenge[] = $tekst;
            }
        }
    }

    private function indlaesStilarter(): void
    {
        $xml = $this->zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return;
        }
        $sx = new SimpleXMLElement($xml);

        // Indbyggede dato/tid-formater i OOXML (delvist - de mest almindelige).
        $indbyggedeDatoFormater = array_flip([14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57]);

        $tilpassedeDatoFormater = [];
        if (isset($sx->numFmts)) {
            foreach ($sx->numFmts->numFmt as $numFmt) {
                $kode = (string)$numFmt['formatCode'];
                if (preg_match('/[ymdhs]/i', $kode) && !str_contains(strtolower($kode), 'general')) {
                    $tilpassedeDatoFormater[(int)$numFmt['numFmtId']] = true;
                }
            }
        }

        if (isset($sx->cellXfs)) {
            $indeks = 0;
            foreach ($sx->cellXfs->xf as $xf) {
                $numFmtId = (int)$xf['numFmtId'];
                $this->datoFormatPrStil[$indeks] = isset($indbyggedeDatoFormater[$numFmtId])
                    || isset($tilpassedeDatoFormater[$numFmtId]);
                $indeks++;
            }
        }
    }
}
