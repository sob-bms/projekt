<?php
declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';

final class ProjektGemTest extends TestCase
{
    public function test_opretter_nyt_projekt_med_automatisk_dato_og_bruger(): void
    {
        $brugerId = $this->opretBruger('SOB');

        $foer = new DateTimeImmutable('-1 minute');
        $id = gem_projekt_fra_formular($this->pdo, [
            'navn' => 'Nyt testprojekt',
            'aabenlukket' => 'Åben',
            'salgsresultat' => 'Ikke afgjort',
        ], $brugerId);

        $stmt = $this->pdo->prepare('SELECT * FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $projekt = $stmt->fetch();

        $this->assertSame('Nyt testprojekt', $projekt['navn']);
        $this->assertSame($brugerId, (int)$projekt['oprettet_af']);
        $this->assertSame($brugerId, (int)$projekt['aendret_af']);
        $this->assertGreaterThanOrEqual($foer, new DateTimeImmutable($projekt['oprettet']));
    }

    public function test_kan_ikke_manipulere_auditfelter_via_formular(): void
    {
        $brugerId = $this->opretBruger('SOB');
        $andenBrugerId = $this->opretBruger('KKN');

        // gem_projekt_fra_formular læser aldrig oprettet_af/aendret_af fra
        // $post - de sættes altid ud fra den kaldende (indloggede) bruger.
        $id = gem_projekt_fra_formular($this->pdo, [
            'navn' => 'Forsøg på at forfalske audit',
            'oprettet_af' => $andenBrugerId,
            'aendret_af' => $andenBrugerId,
        ], $brugerId);

        $stmt = $this->pdo->prepare('SELECT oprettet_af, aendret_af FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $projekt = $stmt->fetch();

        $this->assertSame($brugerId, (int)$projekt['oprettet_af']);
        $this->assertNotSame($andenBrugerId, (int)$projekt['oprettet_af']);
    }

    public function test_redigerer_eksisterende_projekt_og_opdaterer_aendret_af(): void
    {
        $opretter = $this->opretBruger('SOB');
        $redaktoer = $this->opretBruger('KKN');
        $id = $this->opretProjekt(['navn' => 'Original', 'oprettet_af' => $opretter, 'aendret_af' => $opretter]);

        gem_projekt_fra_formular($this->pdo, ['id' => $id, 'navn' => 'Redigeret navn'], $redaktoer);

        $stmt = $this->pdo->prepare('SELECT navn, oprettet_af, aendret_af FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $projekt = $stmt->fetch();

        $this->assertSame('Redigeret navn', $projekt['navn']);
        $this->assertSame($opretter, (int)$projekt['oprettet_af'], 'oprettet_af må ikke ændres ved redigering');
        $this->assertSame($redaktoer, (int)$projekt['aendret_af']);
    }

    public function test_afviser_negativ_projektsum(): void
    {
        $brugerId = $this->opretBruger();
        $this->expectException(ProjektValideringsFejl::class);
        gem_projekt_fra_formular($this->pdo, ['navn' => 'X', 'projektsum' => '-100'], $brugerId);
    }

    public function test_tillader_tom_projektsum(): void
    {
        $brugerId = $this->opretBruger();
        $id = gem_projekt_fra_formular($this->pdo, ['navn' => 'Uden sum', 'projektsum' => ''], $brugerId);
        $stmt = $this->pdo->prepare('SELECT projektsum FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertNull($stmt->fetchColumn());
    }

    public function test_kraever_navn(): void
    {
        $brugerId = $this->opretBruger();
        $this->expectException(ProjektValideringsFejl::class);
        gem_projekt_fra_formular($this->pdo, ['navn' => '  '], $brugerId);
    }

    public function test_tabt_aarsag_gemmes_kun_naar_resultat_er_tabt(): void
    {
        $brugerId = $this->opretBruger();

        $id = gem_projekt_fra_formular($this->pdo, [
            'navn' => 'Tabt projekt',
            'salgsresultat' => 'Tabt',
            'tabt_aarsag' => 'Pris',
        ], $brugerId);
        $stmt = $this->pdo->prepare('SELECT tabt_aarsag FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame('Pris', $stmt->fetchColumn());
    }

    public function test_tabt_aarsag_tvinges_tom_naar_resultat_ikke_er_tabt(): void
    {
        $brugerId = $this->opretBruger();

        // Selvom klienten (fejlagtigt eller ondsindet) sender en tabt_aarsag,
        // skal den ignoreres når salgsresultat ikke er "Tabt".
        $id = gem_projekt_fra_formular($this->pdo, [
            'navn' => 'Vundet projekt',
            'salgsresultat' => 'Vundet',
            'tabt_aarsag' => 'Pris',
            'tabt_aarsag_note' => 'Bør ikke gemmes',
        ], $brugerId);

        $stmt = $this->pdo->prepare('SELECT tabt_aarsag, tabt_aarsag_note FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $projekt = $stmt->fetch();
        $this->assertNull($projekt['tabt_aarsag']);
        $this->assertNull($projekt['tabt_aarsag_note']);
    }

    public function test_tabt_aarsag_afviser_ugyldig_vaerdi(): void
    {
        $brugerId = $this->opretBruger();
        $id = gem_projekt_fra_formular($this->pdo, [
            'navn' => 'X',
            'salgsresultat' => 'Tabt',
            'tabt_aarsag' => 'Ikke-en-gyldig-vaerdi',
        ], $brugerId);

        $stmt = $this->pdo->prepare('SELECT tabt_aarsag FROM projekter WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertNull($stmt->fetchColumn(), 'ugyldig tabt_aarsag skal ignoreres, ikke gemmes råt');
    }

    public function test_hoejst_en_primaer_ansvarlig(): void
    {
        $brugerId = $this->opretBruger('SOB');
        $b1 = $this->opretBruger('AAA');
        $b2 = $this->opretBruger('BBB');

        $id = gem_projekt_fra_formular($this->pdo, [
            'navn' => 'Flere ansvarlige',
            'ansvarlig_id' => [$b1, $b2],
            'primaer_ansvarlig_id' => (string)$b1,
        ], $brugerId);

        $stmt = $this->pdo->prepare('SELECT bruger_id, primaer FROM projekt_ansvarlige WHERE projekt_id = ? ORDER BY bruger_id');
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();

        $antalPrimaere = array_sum(array_column($rows, 'primaer'));
        $this->assertSame(1, $antalPrimaere);
        $this->assertCount(2, $rows);
    }
}
