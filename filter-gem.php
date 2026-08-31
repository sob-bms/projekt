<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_check();

$side = ($_POST['side'] ?? '') === 'dashboard' ? 'dashboard' : 'projekter';
$maalside = $side === 'dashboard' ? 'dashboard.php' : 'index.php';

if (($_POST['handling'] ?? '') === 'slet') {
    slet_gemt_filter($pdo, (int)$bruger['id'], $side);
    header('Location: ' . $maalside);
    exit;
}

$filter = projekt_filter_fra_get($_POST);
gem_filter_for_bruger($pdo, (int)$bruger['id'], $side, $filter);

$qs = http_build_query(projekt_filter_ikke_tomme($filter));
header('Location: ' . $maalside . ($qs !== '' ? '?' . $qs : ''));
exit;
