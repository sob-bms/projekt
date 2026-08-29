<?php
declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../inc/import/XlsxReader.php';
require_once __DIR__ . '/../inc/import/Importer.php';

final class ImportTest extends TestCase
{
    private string $fixtureSti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureSti = sys_get_temp_dir() . '/bms_test_' . uniqid() . '.xlsx';
        XlsxFixtureBuilder::byg($this->fixtureSti);
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixtureSti)) {
            unlink($this->fixtureSti);
        }
    }

    private function nyImporter(): Importer
    {
        return new Importer($this->pdo, new XlsxReader($this->fixtureSti));
    }

    public function test_xlsx_reader_laeser_excel_dato_korrekt(): void
    {
        $reader = new XlsxReader($this->fixtureSti);
        $raekker = $reader->laesArk('Projekter');

        $this->assertInstanceOf(DateTimeImmutable::class, $raekker[4]['A']);
        $this->assertSame('2026-01-15', $raekker[4]['A']->format('Y-m-d'));
    }

    public function test_import_normaliserer_kort_maanedsformat(): void
    {
        $resultat = $this->nyImporter()->koer(false);

        $this->assertSame([], $resultat['fejl']);
        $stmt = $this->pdo->prepare('SELECT byggestart_maaned FROM projekter WHERE navn = ?');
        $stmt->execute(['Testprojekt Et']);
        $this->assertSame('2027-09', $stmt->fetchColumn());
    }

    public function test_import_advarer_ved_ukendt_dato_uden_at_fejle_hele_importen(): void
    {
        $resultat = $this->nyImporter()->koer(false);

        $this->assertSame([], $resultat['fejl']);
        $advarslerTekst = implode(' | ', $resultat['advarsler']);
        $this->assertStringContainsString('Byggestart', $advarslerTekst);

        $stmt = $this->pdo->prepare('SELECT byggestart_maaned FROM projekter WHERE navn = ?');
        $stmt->execute(['Testprojekt To']);
        $this->assertNull($stmt->fetchColumn());
    }

    public function test_import_advarer_ved_urene_bms_ansvarlig_og_opretter_ikke_bruger(): void
    {
        $resultat = $this->nyImporter()->koer(false);

        $advarslerTekst = implode(' | ', $resultat['advarsler']);
        $this->assertStringContainsString('BMS ansvarlig', $advarslerTekst);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM projekt_ansvarlige pa
             JOIN projekter p ON p.id = pa.projekt_id WHERE p.navn = ?'
        );
        $stmt->execute(['Testprojekt To']);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function test_import_springer_raekke_med_tomt_navn_over_men_taeller_ikke_helt_tomme_raekker(): void
    {
        $resultat = $this->nyImporter()->koer(false);

        $this->assertSame(1, $resultat['oversprungne']);
    }

    public function test_import_advarer_om_ureferet_hjaelpeark(): void
    {
        $resultat = $this->nyImporter()->koer(false);

        $advarslerTekst = implode(' | ', $resultat['advarsler']);
        $this->assertStringContainsString('"2"', $advarslerTekst);
        $this->assertStringContainsString('ikke refereret', $advarslerTekst);

        // Hjælpeark "1" ER refereret (fra række 4) og skal derfor IKKE
        // fremgå som en "ikke refereret"-advarsel.
        $this->assertStringNotContainsString('"1" er ikke refereret', $advarslerTekst);
    }

    public function test_dry_run_skriver_intet_til_databasen(): void
    {
        $foer = (int)$this->pdo->query('SELECT COUNT(*) FROM projekter')->fetchColumn();

        $resultat = $this->nyImporter()->koer(true);
        $this->assertGreaterThan(0, $resultat['nye']);

        $efter = (int)$this->pdo->query('SELECT COUNT(*) FROM projekter')->fetchColumn();
        $this->assertSame($foer, $efter, 'dry-run må ikke skrive noget til databasen');
    }

    public function test_genimport_af_samme_fil_er_idempotent(): void
    {
        $foersteResultat = $this->nyImporter()->koer(false);
        $antalEfterFoerste = (int)$this->pdo->query('SELECT COUNT(*) FROM projekter')->fetchColumn();

        $andetResultat = $this->nyImporter()->koer(false);
        $antalEfterAndet = (int)$this->pdo->query('SELECT COUNT(*) FROM projekter')->fetchColumn();

        $this->assertGreaterThan(0, $foersteResultat['nye']);
        $this->assertSame(0, $andetResultat['nye'], 'anden importkørsel må ikke oprette nye projekter');
        $this->assertSame($foersteResultat['nye'], $andetResultat['opdaterede']);
        $this->assertSame($antalEfterFoerste, $antalEfterAndet, 'antal projekter må ikke ændre sig ved genimport');
    }
}
