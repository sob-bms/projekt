<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

$dbHost = env('DB_HOST', 'localhost');
$dbName = env('DB_NAME', 'bms_projekt');
$dbUser = env('DB_USER', 'bms_projekt');
$dbPass = env('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Databaseforbindelse fejlede. Tjek .env (DB_HOST/DB_NAME/DB_USER/DB_PASS).');
}
