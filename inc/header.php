<?php
$huidigeSide = basename($_SERVER['SCRIPT_NAME']);
$indloggetBruger = function_exists('aktuel_bruger') ? aktuel_bruger() : null;
?>
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
        <?php if ($indloggetBruger): ?>
        <nav>
            <a href="index.php" class="<?= $huidigeSide === 'index.php' ? 'aktiv' : '' ?>">Projekter</a>
            <a href="dashboard.php" class="<?= $huidigeSide === 'dashboard.php' ? 'aktiv' : '' ?>">Dashboard</a>
            <a href="virksomheder.php" class="<?= $huidigeSide === 'virksomheder.php' ? 'aktiv' : '' ?>">Virksomheder</a>
            <?php if (er_administrator($indloggetBruger)): ?>
            <a href="brugere.php" class="<?= $huidigeSide === 'brugere.php' ? 'aktiv' : '' ?>">Brugere</a>
            <a href="import.php" class="<?= $huidigeSide === 'import.php' ? 'aktiv' : '' ?>">Excel-import</a>
            <?php endif; ?>
        </nav>
        <form method="post" action="logout.php" class="bruger-info">
            <span><?= e($indloggetBruger['navn']) ?> (<?= e(rolle_label($indloggetBruger['rolle'])) ?>)</span>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="link-knap link-knap-lys">Log ud</button>
        </form>
        <?php endif; ?>
    </div>
</header>
<main class="container">
