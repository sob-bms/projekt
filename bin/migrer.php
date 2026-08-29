#!/usr/bin/env php
<?php

declare(strict_types=1);

// Kører de SQL-migrationer i db/migrations/, der endnu ikke er anvendt på
// denne database. Sikkert at køre gentagne gange (og på både en helt tom
// database og en eksisterende installation).
//
// Brug: php bin/migrer.php

require __DIR__ . '/../inc/cli_bootstrap.php';
require __DIR__ . '/../inc/migrator.php';

try {
    $antalKoert = koer_migrationer($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($antalKoert === 0) {
    echo "Ingen nye migrationer - databasen er opdateret.\n";
} else {
    echo "$antalKoert migration(er) kørt.\n";
}
