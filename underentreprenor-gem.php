<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: underentreprenorer.php');
    exit;
}
csrf_check();

$id = isset($_POST['id']) ? (int)$_POST['id'] : null;
$navn = trim($_POST['navn'] ?? '');
if ($navn === '') {
    http_response_code(400);
    die('Navn er påkrævet.');
}

$data = [
    $navn,
    trim($_POST['fag'] ?? '') ?: null,
    trim($_POST['telefon'] ?? '') ?: null,
    trim($_POST['email'] ?? '') ?: null,
];

if ($id) {
    $stmt = $pdo->prepare('UPDATE underentreprenorer SET navn=?, fag=?, telefon=?, email=? WHERE id=?');
    $stmt->execute([...$data, $id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO underentreprenorer (navn, fag, telefon, email) VALUES (?,?,?,?)');
    $stmt->execute($data);
}

header('Location: underentreprenorer.php');
exit;
