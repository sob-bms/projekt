<?php
declare(strict_types=1);

require __DIR__ . '/env.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(env('SESSION_NAME', 'bms_projekt_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require __DIR__ . '/functions.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/projekter.php';
require __DIR__ . '/projekt_gem_logic.php';
