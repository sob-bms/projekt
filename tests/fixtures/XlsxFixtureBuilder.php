<?php
declare(strict_types=1);

/**
 * Bygger en minimal, gyldig .xlsx-testfil i hånden (uden Composer-pakker),
 * så importtests kan køre uden at røre den rigtige, fortrolige
 * kunderegnearksfil (som ifølge opgaven aldrig må committes til git).
 *
 * Fixture-arket "Projekter" efterligner den virkelige projektoversigts
 * kolonneopbygning (header i række 3, data fra række 4) med et lille sæt
 * bevidst udvalgte "svære" tilfælde: en rigtig Excel-dato, et kort
 * "YYYY-M"-format, et ukendt "?", tomme rækker, en urene BMS-ansvarlig og en
 * reference til hjælpearket "1" (mens hjælpeark "2" bevidst IKKE refereres).
 */
class XlsxFixtureBuilder
{
    public static function byg(string $sti): void
    {
        $delteStrenge = [];
        $strengIndeks = function (string $s) use (&$delteStrenge): int {
            $i = array_search($s, $delteStrenge, true);
            if ($i !== false) {
                return $i;
            }
            $delteStrenge[] = $s;
            return count($delteStrenge) - 1;
        };

        $serial = fn (string $ymd) => (int)(new DateTimeImmutable($ymd))->diff(new DateTimeImmutable('1899-12-30'))->days;

        // r=4: gyldig dato, kort byggestart-format "2027-9".
        $raekke4 = [
            'A' => ['v' => $serial('2026-01-15'), 's' => 1],
            'B' => ['t' => 's', 'v' => $strengIndeks('SOB')],
            'D' => ['t' => 's', 'v' => $strengIndeks('Testprojekt Et')],
            'J' => ['t' => 's', 'v' => $strengIndeks('2027-9')],
            'M' => ['t' => 's', 'v' => $strengIndeks('1')],
            'S' => ['t' => 's', 'v' => $strengIndeks('Åben')],
            'T' => ['t' => 's', 'v' => $strengIndeks('Vundet')],
            'O' => ['t' => 's', 'v' => $strengIndeks('AAA/BBB')],
        ];
        // r=5: ukendt byggestart "?", uren BMS-ansvarlig (fritekst med mellemrum).
        $raekke5 = [
            'A' => ['v' => $serial('2026-02-01'), 's' => 1],
            'B' => ['t' => 's', 'v' => $strengIndeks('SOB')],
            'D' => ['t' => 's', 'v' => $strengIndeks('Testprojekt To')],
            'J' => ['t' => 's', 'v' => $strengIndeks('?')],
            'S' => ['t' => 's', 'v' => $strengIndeks('Åben')],
            'O' => ['t' => 's', 'v' => $strengIndeks('KHL kigger på siten')],
        ];
        // r=6: helt tom række (skal springes over, tæller ikke som "oversprunget").
        $raekke6 = [];
        // r=7: tomt navn (skal tælle som oversprunget).
        $raekke7 = [
            'B' => ['t' => 's', 'v' => $strengIndeks('KKN')],
        ];
        // r=8: endnu et gyldigt projekt.
        $raekke8 = [
            'A' => ['v' => $serial('2026-03-03'), 's' => 1],
            'B' => ['t' => 's', 'v' => $strengIndeks('KKN')],
            'D' => ['t' => 's', 'v' => $strengIndeks('Testprojekt Tre')],
            'S' => ['t' => 's', 'v' => $strengIndeks('Lukket')],
            'T' => ['t' => 's', 'v' => $strengIndeks('Tabt')],
            'U' => ['t' => 's', 'v' => $strengIndeks('Pris')],
        ];

        $lavRow = function (int $r, array $celler): string {
            $xml = "<row r=\"$r\">";
            foreach ($celler as $kol => $c) {
                $ref = "$kol$r";
                $sAttr = isset($c['s']) ? ' s="' . $c['s'] . '"' : '';
                $tAttr = isset($c['t']) ? ' t="' . $c['t'] . '"' : '';
                $xml .= "<c r=\"$ref\"$sAttr$tAttr><v>{$c['v']}</v></c>";
            }
            return $xml . '</row>';
        };

        $projekterSheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $lavRow(4, $raekke4) . $lavRow(5, $raekke5) . $lavRow(6, $raekke6)
            . $lavRow(7, $raekke7) . $lavRow(8, $raekke8)
            . '</sheetData></worksheet>';

        // Hjælpeark "1" (refereres fra række 4, kolonne M).
        $i1 = $strengIndeks('Baggrundstekst for projekt et.');
        $hjaelpeark1Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>' . $i1 . '</v></c></row>'
            . '</sheetData></worksheet>';

        // Hjælpeark "2" (bevidst IKKE refereret fra nogen projektrække).
        $i2 = $strengIndeks('Uref. hjælpetekst.');
        $hjaelpeark2Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>' . $i2 . '</v></c></row>'
            . '</sheetData></worksheet>';

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($delteStrenge) . '" uniqueCount="' . count($delteStrenge) . '">'
            . implode('', array_map(fn ($s) => '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1) . '</t></si>', $delteStrenge))
            . '</sst>';

        // cellXfs-indeks 0 = standard (ikke dato), indeks 1 = dato (numFmtId 14, indbygget).
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14"/></cellXfs>'
            . '</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Projekter" sheetId="1" r:id="rId1"/>'
            . '<sheet name="1" sheetId="2" r:id="rId2"/>'
            . '<sheet name="2" sheetId="3" r:id="rId3"/>'
            . '</sheets></workbook>';

        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            . '</Relationships>';

        if (is_file($sti)) {
            unlink($sti);
        }
        $zip = new ZipArchive();
        $zip->open($sti, ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $projekterSheetXml);
        $zip->addFromString('xl/worksheets/sheet2.xml', $hjaelpeark1Xml);
        $zip->addFromString('xl/worksheets/sheet3.xml', $hjaelpeark2Xml);
        $zip->close();
    }
}
