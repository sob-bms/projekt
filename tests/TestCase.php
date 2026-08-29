<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->nulstilTabeller();
    }

    /**
     * Tømmer alle applikationstabeller mellem hver test, så tests er
     * uafhængige af hinandens data. skema_migrationer bevares.
     */
    private function nulstilTabeller(): void
    {
        $tabeller = [
            'projekt_historik', 'projekt_noter', 'kontaktpersoner',
            'projekt_virksomheder', 'projekt_ansvarlige',
            'projekter', 'virksomheder', 'brugere',
        ];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tabeller as $t) {
            $this->pdo->exec("TRUNCATE TABLE $t");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function opretBruger(string $initialer = 'SOB', string $rolle = ROLLE_REDAKTOER): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO brugere (initialer, navn, rolle, aktiv) VALUES (?, ?, ?, 1)');
        $stmt->execute([$initialer, "Test $initialer", $rolle]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Opretter et minimalt projekt direkte i databasen (uden om
     * gem_projekt_fra_formular) - praktisk som testfixture.
     */
    protected function opretProjekt(array $overskriv = []): int
    {
        $data = array_merge([
            'navn' => 'Testprojekt',
            'aabenlukket' => 'Åben',
            'salgsresultat' => 'Ikke afgjort',
            'projektsum' => null,
            'byggestart_maaned' => null,
            'byggestart_bekraeftet' => 0,
        ], $overskriv);

        $kolonner = array_keys($data);
        $pladsholdere = implode(', ', array_fill(0, count($kolonner), '?'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO projekter (' . implode(', ', $kolonner) . ") VALUES ($pladsholdere)"
        );
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }
}
