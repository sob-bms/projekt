<?php
require __DIR__ . '/inc/bootstrap.php';
kraev_rolle([ROLLE_ADMINISTRATOR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: brugere.php');
    exit;
}
csrf_check();

$id = isset($_POST['id']) ? (int)$_POST['id'] : null;
$initialer = trim($_POST['initialer'] ?? '');
$navn = trim($_POST['navn'] ?? '');
$rolle = in_array($_POST['rolle'] ?? '', ALLE_ROLLER, true) ? $_POST['rolle'] : ROLLE_LAESER;
$aktiv = !empty($_POST['aktiv']) ? 1 : 0;
$email = trim($_POST['email'] ?? '') ?: null;
$adgangskode = (string)($_POST['adgangskode'] ?? '');

if ($initialer === '' || $navn === '') {
    http_response_code(400);
    die('Initialer og navn er påkrævet.');
}
if (!$id && strlen($adgangskode) < 8) {
    http_response_code(400);
    die('Adgangskoden skal være mindst 8 tegn ved oprettelse af en ny bruger.');
}
if ($adgangskode !== '' && strlen($adgangskode) < 8) {
    http_response_code(400);
    die('Adgangskoden skal være mindst 8 tegn.');
}

try {
    if ($id) {
        if ($adgangskode !== '') {
            $stmt = $pdo->prepare(
                'UPDATE brugere SET initialer=?, navn=?, email=?, rolle=?, aktiv=?, password_hash=? WHERE id=?'
            );
            $stmt->execute([$initialer, $navn, $email, $rolle, $aktiv, password_hash($adgangskode, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE brugere SET initialer=?, navn=?, email=?, rolle=?, aktiv=? WHERE id=?'
            );
            $stmt->execute([$initialer, $navn, $email, $rolle, $aktiv, $id]);
        }
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO brugere (initialer, navn, email, rolle, aktiv, password_hash) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$initialer, $navn, $email, $rolle, $aktiv, password_hash($adgangskode, PASSWORD_DEFAULT)]);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        die('Der findes allerede en bruger med de initialer.');
    }
    throw $e;
}

header('Location: brugere.php');
exit;
