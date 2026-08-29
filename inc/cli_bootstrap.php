<?php

declare(strict_types=1);

// Bootstrap til kommandolinje-scripts i bin/ - ligesom inc/bootstrap.php,
// men uden session (der findes ingen HTTP-session i CLI).
require __DIR__ . '/functions.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/projekter.php';
require __DIR__ . '/projekt_gem_logic.php';
