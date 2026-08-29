#!/usr/bin/env php
<?php

declare(strict_types=1);

// Opretter (eller opgraderer) en administrator-bruger. Bruges til at
// bootstrappe den første adgang til systemet, da der ikke er
// selvregistrering.
//
// Brug: php bin/opret-admin.php <initialer> <fulde navn> <adgangskode> [email]

require __DIR__ . '/../inc/cli_bootstrap.php';

$argv = $argv ?? [];
if (count($argv) < 4) {
    fwrite(STDERR, "Brug: php bin/opret-admin.php <initialer> <fulde navn> <adgangskode> [email]\n");
    exit(1);
}

$initialer = trim($argv[1]);
$navn = trim($argv[2]);
$adgangskode = $argv[3];
$email = trim($argv[4] ?? '') ?: null;

if ($initialer === '' || $navn === '') {
    fwrite(STDERR, "Initialer og navn må ikke være tomme.\n");
    exit(1);
}
if (strlen($adgangskode) < 8) {
    fwrite(STDERR, "Adgangskoden skal være mindst 8 tegn.\n");
    exit(1);
}

$hash = password_hash($adgangskode, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM brugere WHERE initialer = ?');
$stmt->execute([$initialer]);
$eksisterende = $stmt->fetch();

if ($eksisterende) {
    $stmt = $pdo->prepare(
        'UPDATE brugere SET navn = ?, email = ?, password_hash = ?, rolle = ?, aktiv = 1 WHERE id = ?'
    );
    $stmt->execute([$navn, $email, $hash, ROLLE_ADMINISTRATOR, $eksisterende['id']]);
    echo "Opdaterede eksisterende bruger '$initialer' til administrator.\n";
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO brugere (initialer, navn, email, password_hash, rolle, aktiv) VALUES (?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([$initialer, $navn, $email, $hash, ROLLE_ADMINISTRATOR]);
    echo "Oprettede ny administrator '$initialer'.\n";
}
