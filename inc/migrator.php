<?php
declare(strict_types=1);

// Genbrugelig migrationslogik - kaldes af bin/migrer.php (kommandolinje) og
// af testopsætningen (tests/bootstrap.php), så begge kører nøjagtig samme
// migrationsforløb mod deres respektive database.

/**
 * Kører alle endnu ikke-anvendte migrationer fra db/migrations/.
 * Sikker at kalde gentagne gange (og mod både en tom og en eksisterende
 * database) - allerede kørte filer springes over.
 *
 * @return int Antal migrationer der blev kørt i dette kald.
 */
function koer_migrationer(PDO $pdo, ?string $migrationsMappe = null): int
{
    $migrationsMappe ??= __DIR__ . '/../db/migrations';

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS skema_migrationer (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            fil VARCHAR(255) NOT NULL,
            koert TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_skema_migrationer_fil (fil)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $koerte = $pdo->query('SELECT fil FROM skema_migrationer')->fetchAll(PDO::FETCH_COLUMN);

    $filer = glob($migrationsMappe . '/*.sql') ?: [];
    sort($filer, SORT_STRING);

    $antalKoert = 0;
    foreach ($filer as $sti) {
        $filnavn = basename($sti);
        if (in_array($filnavn, $koerte, true)) {
            continue;
        }

        $sql = file_get_contents($sti);
        if ($sql === false) {
            throw new RuntimeException("Kunne ikke læse $filnavn");
        }

        // Bemærk: DDL (CREATE/ALTER/DROP TABLE) laver et implicit COMMIT i
        // MySQL/MariaDB, så en enkelt SQL-transaktion om hele filen giver
        // ikke reel atomicitet. Fejler en fil midtvejs, er nogle sætninger
        // allerede anvendt - ret fejlen og kør igen (CREATE ... IF NOT
        // EXISTS og idempotente INSERT-sætninger gør migrationerne sikre at
        // genafspille).
        try {
            foreach (del_sql_i_saetninger($sql) as $saetning) {
                $pdo->exec($saetning);
            }
            $stmt = $pdo->prepare('INSERT INTO skema_migrationer (fil) VALUES (?)');
            $stmt->execute([$filnavn]);
            $antalKoert++;
        } catch (Throwable $e) {
            throw new RuntimeException("Fejl i $filnavn: " . $e->getMessage(), 0, $e);
        }
    }

    return $antalKoert;
}

/**
 * Deler en .sql-fil i enkeltstående sætninger ved topniveau-semikolon.
 * Migrationsfilerne er skrevet uden semikolon inde i strengliteraler, så en
 * simpel linjebaseret opdeling er tilstrækkelig og undgår afhængighed af
 * PDO's multi-statement-understøttelse.
 *
 * @return list<string>
 */
function del_sql_i_saetninger(string $sql): array
{
    $rensetLinjer = [];
    foreach (explode("\n", $sql) as $linje) {
        $trimmet = ltrim($linje);
        if (str_starts_with($trimmet, '--')) {
            continue;
        }
        $rensetLinjer[] = $linje;
    }

    $saetninger = explode(';', implode("\n", $rensetLinjer));
    $resultat = [];
    foreach ($saetninger as $saetning) {
        $saetning = trim($saetning);
        if ($saetning !== '') {
            $resultat[] = $saetning;
        }
    }
    return $resultat;
}
