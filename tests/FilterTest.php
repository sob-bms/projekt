<?php
declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';

final class FilterTest extends TestCase
{
    private function findNavne(array $get): array
    {
        $filter = projekt_filter_fra_get($get);
        $hvor = byg_projekt_where($filter);
        $stmt = $this->pdo->prepare("SELECT navn FROM projekter p WHERE {$hvor['sql']} ORDER BY navn");
        $stmt->execute($hvor['params']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function test_filtrerer_paa_byggestart_interval(): void
    {
        $this->opretProjekt(['navn' => 'For tidligt', 'byggestart_maaned' => '2025-01']);
        $this->opretProjekt(['navn' => 'I intervallet', 'byggestart_maaned' => '2026-06']);
        $this->opretProjekt(['navn' => 'For sent', 'byggestart_maaned' => '2028-01']);
        $this->opretProjekt(['navn' => 'Ukendt dato', 'byggestart_maaned' => null]);

        $navne = $this->findNavne(['byggestart_fra' => '2026-01', 'byggestart_til' => '2026-12']);

        $this->assertSame(['I intervallet'], $navne);
    }

    public function test_filtrerer_paa_bekraeftet_byggestart(): void
    {
        $this->opretProjekt(['navn' => 'Bekræftet', 'byggestart_maaned' => '2026-05', 'byggestart_bekraeftet' => 1]);
        $this->opretProjekt(['navn' => 'Ikke bekræftet', 'byggestart_maaned' => '2026-05', 'byggestart_bekraeftet' => 0]);
        $this->opretProjekt(['navn' => 'Ukendt', 'byggestart_maaned' => null, 'byggestart_bekraeftet' => 0]);

        $this->assertSame(['Bekræftet'], $this->findNavne(['byggestart_status' => 'bekraeftet']));
        $this->assertSame(['Ikke bekræftet'], $this->findNavne(['byggestart_status' => 'ikke_bekraeftet']));
        $this->assertSame(['Ukendt'], $this->findNavne(['byggestart_status' => 'ukendt']));
    }

    public function test_filtrerer_paa_tildelt_og_ikke_tildelt_ansvarlig(): void
    {
        $bruger = $this->opretBruger('SOB');
        $tildeltId = $this->opretProjekt(['navn' => 'Tildelt']);
        $ikkeTildeltId = $this->opretProjekt(['navn' => 'Ikke tildelt']);
        $this->pdo->prepare('INSERT INTO projekt_ansvarlige (projekt_id, bruger_id, primaer) VALUES (?, ?, 1)')
            ->execute([$tildeltId, $bruger]);

        $this->assertSame(['Tildelt'], $this->findNavne(['ansvarlig_tildeling' => 'tildelt']));
        $this->assertSame(['Ikke tildelt'], $this->findNavne(['ansvarlig_tildeling' => 'ikke_tildelt']));
    }

    public function test_filtrerer_paa_specifik_ansvarlig_kun_primaer(): void
    {
        $primaer = $this->opretBruger('AAA');
        $medansvarlig = $this->opretBruger('BBB');
        $id = $this->opretProjekt(['navn' => 'Med to ansvarlige']);
        $this->pdo->prepare('INSERT INTO projekt_ansvarlige (projekt_id, bruger_id, primaer) VALUES (?, ?, 1), (?, ?, 0)')
            ->execute([$id, $primaer, $id, $medansvarlig]);

        // Som medansvarlig (ikke primær) matcher uden "kun_primaer", men ikke med.
        $this->assertSame(['Med to ansvarlige'], $this->findNavne(['ansvarlig' => [$medansvarlig]]));
        $this->assertSame([], $this->findNavne(['ansvarlig' => [$medansvarlig], 'kun_primaer' => '1']));
        $this->assertSame(['Med to ansvarlige'], $this->findNavne(['ansvarlig' => [$primaer], 'kun_primaer' => '1']));
    }

    public function test_filtrerer_paa_aabenlukket(): void
    {
        $this->opretProjekt(['navn' => 'Åben sag', 'aabenlukket' => 'Åben']);
        $this->opretProjekt(['navn' => 'Lukket sag', 'aabenlukket' => 'Lukket']);
        $this->opretProjekt(['navn' => 'Annulleret sag', 'aabenlukket' => 'Annulleret']);

        $this->assertSame(['Lukket sag'], $this->findNavne(['aabenlukket' => 'Lukket']));
    }

    public function test_kombinerer_flere_filtre(): void
    {
        $this->opretProjekt(['navn' => 'Match', 'aabenlukket' => 'Åben', 'salgsresultat' => 'Vundet']);
        $this->opretProjekt(['navn' => 'Forkert status', 'aabenlukket' => 'Lukket', 'salgsresultat' => 'Vundet']);
        $this->opretProjekt(['navn' => 'Forkert salg', 'aabenlukket' => 'Åben', 'salgsresultat' => 'Tabt']);

        $this->assertSame(['Match'], $this->findNavne(['aabenlukket' => 'Åben', 'salgsresultat' => 'Vundet']));
    }
}
