<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    log_ud();
}

header('Location: login.php');
exit;
