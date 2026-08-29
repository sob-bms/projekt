#!/usr/bin/env php
<?php

declare(strict_types=1);

// Importerer projekter fra Excel-projektoversigten.
//
// Brug:
//   php bin/importer.php <sti-til-fil.xlsx> --dry-run   (viser kun opsummering, skriver intet)
//   php bin/importer.php <sti-til-fil.xlsx>              (kører den endelige import)

require __DIR__ . '/../inc/cli_bootstrap.php';
require __DIR__ . '/../inc/import/XlsxReader.php';
require __DIR__ . '/../inc/import/Importer.php';

$argv = $argv ?? [];
$sti = null;
$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($sti === null) {
        $sti = $arg;
    }
}

if ($sti === null) {
    fwrite(STDERR, "Brug: php bin/importer.php <sti-til-fil.xlsx> [--dry-run]\n");
    fwrite(STDERR, "Læg evt. filen i data/import/ (uden for git) og peg herpå.\n");
    exit(1);
}
if (!is_file($sti)) {
    fwrite(STDERR, "Filen findes ikke: $sti\n");
    exit(1);
}

echo ($dryRun ? "DRY RUN - " : '') . "Importerer fra: $sti\n";

$reader = new XlsxReader($sti);
$importer = new Importer($pdo, $reader);
$resultat = $importer->koer($dryRun);

echo "\n== Opsummering ==\n";
echo 'Nye projekter:       ' . $resultat['nye'] . "\n";
echo 'Opdaterede projekter: ' . $resultat['opdaterede'] . "\n";
echo 'Oversprungne rækker:  ' . $resultat['oversprungne'] . "\n";
echo 'Advarsler:            ' . count($resultat['advarsler']) . "\n";
echo 'Fejl:                 ' . count($resultat['fejl']) . "\n";

if ($resultat['advarsler']) {
    echo "\n== Advarsler (kræver evt. manuel gennemgang) ==\n";
    foreach ($resultat['advarsler'] as $a) {
        echo "- $a\n";
    }
}
if ($resultat['fejl']) {
    echo "\n== Fejl ==\n";
    foreach ($resultat['fejl'] as $f) {
        echo "- $f\n";
    }
}

if ($dryRun) {
    echo "\nDette var en dry-run - der er IKKE skrevet noget til databasen.\n";
    echo "Kør uden --dry-run for at gennemføre importen.\n";
}

exit($resultat['fejl'] ? 1 : 0);
