<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: underentreprenorer.php');
    exit;
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM underentreprenorer WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: underentreprenorer.php');
exit;
