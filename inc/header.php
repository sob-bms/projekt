<?php $huidigeSide = basename($_SERVER['SCRIPT_NAME']); ?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BMS Projekter</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a href="index.php" class="logo">BMS Projekter</a>
        <nav>
            <a href="index.php" class="<?= $huidigeSide === 'index.php' ? 'aktiv' : '' ?>">Projekter</a>
            <a href="underentreprenorer.php" class="<?= $huidigeSide === 'underentreprenorer.php' ? 'aktiv' : '' ?>">Underentreprenører</a>
            <a href="dashboard.php" class="<?= $huidigeSide === 'dashboard.php' ? 'aktiv' : '' ?>">Dashboard</a>
        </nav>
    </div>
</header>
<main class="container">
