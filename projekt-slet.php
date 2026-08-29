<?php
require __DIR__ . '/inc/bootstrap.php';
kraev_rolle([ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM projekter WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: index.php');
exit;
