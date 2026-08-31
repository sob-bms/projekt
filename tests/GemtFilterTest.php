<?php
declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';

final class GemtFilterTest extends TestCase
{
    public function test_gemmer_og_henter_filter_for_bruger(): void
    {
        $brugerId = $this->opretBruger('SOB');

        $this->assertNull(hent_gemt_filter($this->pdo, $brugerId, 'projekter'));

        $filter = projekt_filter_fra_get(['soeg' => 'Kolding', 'aabenlukket' => 'Åben', 'ansvarlig' => ['1']]);
        gem_filter_for_bruger($this->pdo, $brugerId, 'projekter', $filter);

        $this->assertSame($filter, hent_gemt_filter($this->pdo, $brugerId, 'projekter'));
        // Dashboardet har sit eget gemte filter, uafhængigt af projektlisten.
        $this->assertNull(hent_gemt_filter($this->pdo, $brugerId, 'dashboard'));
    }

    public function test_gem_overskriver_tidligere_gemt_filter(): void
    {
        $brugerId = $this->opretBruger('SOB');

        gem_filter_for_bruger($this->pdo, $brugerId, 'projekter', projekt_filter_fra_get(['soeg' => 'Først']));
        gem_filter_for_bruger($this->pdo, $brugerId, 'projekter', projekt_filter_fra_get(['soeg' => 'Sidst']));

        $this->assertSame('Sidst', hent_gemt_filter($this->pdo, $brugerId, 'projekter')['soeg']);
    }

    public function test_sletter_gemt_filter(): void
    {
        $brugerId = $this->opretBruger('SOB');
        gem_filter_for_bruger($this->pdo, $brugerId, 'projekter', projekt_filter_fra_get(['soeg' => 'Kolding']));

        slet_gemt_filter($this->pdo, $brugerId, 'projekter');

        $this->assertNull(hent_gemt_filter($this->pdo, $brugerId, 'projekter'));
    }

    public function test_filter_ikke_tomme_fjerner_tomme_felter(): void
    {
        $filter = projekt_filter_fra_get(['soeg' => 'Kolding', 'ansvarlig' => ['3']]);

        $this->assertSame(['soeg' => 'Kolding', 'ansvarlig' => [3]], projekt_filter_ikke_tomme($filter));
    }

    public function test_filter_til_skjulte_felter_udfolder_arrays_og_boolske_vaerdier(): void
    {
        $filter = projekt_filter_fra_get(['soeg' => 'Kolding', 'ansvarlig' => ['3', '5'], 'kun_primaer' => '1']);

        $felter = filter_til_skjulte_felter(projekt_filter_ikke_tomme($filter));

        $this->assertSame([
            ['soeg', 'Kolding'],
            ['ansvarlig[]', '3'],
            ['ansvarlig[]', '5'],
            ['kun_primaer', '1'],
        ], $felter);
    }
}
