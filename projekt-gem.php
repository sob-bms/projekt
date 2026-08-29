<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_rolle([ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_check();

try {
    $id = gem_projekt_fra_formular($pdo, $_POST, (int)$bruger['id']);
} catch (ProjektValideringsFejl $e) {
    http_response_code(400);
    die($e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    die($e->getMessage());
}

header('Location: projekt-detalje.php?id=' . $id);
exit;
