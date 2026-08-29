<?php
require __DIR__ . '/inc/bootstrap.php';
kraev_rolle([ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: virksomheder.php');
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
    trim($_POST['cvr'] ?? '') ?: null,
    trim($_POST['telefon'] ?? '') ?: null,
    trim($_POST['email'] ?? '') ?: null,
    trim($_POST['note'] ?? '') ?: null,
];

try {
    if ($id) {
        $stmt = $pdo->prepare('UPDATE virksomheder SET navn=?, cvr=?, telefon=?, email=?, note=? WHERE id=?');
        $stmt->execute([...$data, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO virksomheder (navn, cvr, telefon, email, note) VALUES (?,?,?,?,?)');
        $stmt->execute($data);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        die('Der findes allerede en virksomhed med det navn.');
    }
    throw $e;
}

header('Location: virksomheder.php');
exit;
