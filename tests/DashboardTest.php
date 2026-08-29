<?php
declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';

final class DashboardTest extends TestCase
{
    public function test_projektsum_tælles_ikke_dobbelt_ved_flere_ansvarlige(): void
    {
        $primaer = $this->opretBruger('AAA');
        $medansvarlig = $this->opretBruger('BBB');

        $id = $this->opretProjekt(['navn' => 'Stort projekt', 'projektsum' => 10_000_000]);
        $this->pdo->prepare('INSERT INTO projekt_ansvarlige (projekt_id, bruger_id, primaer) VALUES (?, ?, 1), (?, ?, 0)')
            ->execute([$id, $primaer, $id, $medansvarlig]);

        $filter = projekt_filter_fra_get([]);
        $dash = hent_dashboard_data($this->pdo, $filter);

        // Den samlede sum for hele dashboardet må kun tælle projektet én
        // gang, uanset at det har to ansvarlige.
        $this->assertSame(10_000_000.0, $dash['total_sum']);
        $this->assertSame(1, $dash['total_antal']);

        // Og i grupperingen pr. ansvarlig må summen kun ligge hos den
        // PRIMÆRE ansvarlige - ikke gentaget hos medansvarlige.
        $navnePrSum = array_column($dash['ansvarlig_data'], 'sum', 'navn');
        $this->assertSame(10_000_000.0, (float)$navnePrSum['Test AAA']);
        $this->assertArrayNotHasKey('Test BBB', $navnePrSum);

        $summerITalt = array_sum(array_map('floatval', array_column($dash['ansvarlig_data'], 'sum')));
        $this->assertSame(10_000_000.0, $summerITalt, 'summen af alle grupper skal matche totalen, ikke det dobbelte');
    }

    public function test_kpi_tal_respekterer_filter(): void
    {
        $this->opretProjekt(['navn' => 'Åben', 'aabenlukket' => 'Åben', 'projektsum' => 1_000_000]);
        $this->opretProjekt(['navn' => 'Lukket', 'aabenlukket' => 'Lukket', 'projektsum' => 2_000_000]);

        $filter = projekt_filter_fra_get(['aabenlukket' => 'Lukket']);
        $dash = hent_dashboard_data($this->pdo, $filter);

        $this->assertSame(1, $dash['total_antal']);
        $this->assertSame(2_000_000.0, $dash['total_sum']);
    }

    public function test_tildelt_og_ikke_tildelt_kpi(): void
    {
        $bruger = $this->opretBruger('SOB');
        $tildeltId = $this->opretProjekt(['navn' => 'Tildelt']);
        $this->opretProjekt(['navn' => 'Ikke tildelt']);
        $this->pdo->prepare('INSERT INTO projekt_ansvarlige (projekt_id, bruger_id, primaer) VALUES (?, ?, 1)')
            ->execute([$tildeltId, $bruger]);

        $dash = hent_dashboard_data($this->pdo, projekt_filter_fra_get([]));

        $this->assertSame(1, $dash['antal_tildelt']);
        $this->assertSame(1, $dash['antal_ikke_tildelt']);
    }
}
