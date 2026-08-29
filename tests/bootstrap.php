<?php
declare(strict_types=1);

// Sikkerhedsstopklods: testene nulstiller tabeller mellem hver test, så vi
// skal aldrig risikere at køre dem mod udviklings- eller produktionsdata.
$dbNavn = getenv('DB_NAME') ?: '';
if (!str_contains($dbNavn, 'test')) {
    fwrite(STDERR, "DB_NAME (\"$dbNavn\") indeholder ikke \"test\" - afbryder for en sikkerheds skyld.\n");
    fwrite(STDERR, "Sæt DB_NAME til en dedikeret testdatabase (se phpunit.xml.dist).\n");
    exit(1);
}

require __DIR__ . '/../inc/cli_bootstrap.php';
require __DIR__ . '/../inc/migrator.php';
require __DIR__ . '/fixtures/XlsxFixtureBuilder.php';

koer_migrationer($pdo);

// PHPUnit inkluderer denne fil fra en metode, ikke fra topniveau-scope, så
// $pdo (sat af inc/db.php) er ikke automatisk tilgængelig via "global $pdo"
// i testklasserne. $GLOBALS er derimod altid den samme superglobale array
// uanset scope, så vi eksponerer $pdo eksplicit her.
$GLOBALS['pdo'] = $pdo;
