<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
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
    'navn' => $navn,
    'adresse' => trim($_POST['adresse'] ?? '') ?: null,
    'status' => in_array($_POST['status'] ?? '', STATUS_LISTE, true) ? $_POST['status'] : 'Tilbud',
    'projektsum' => ($_POST['projektsum'] ?? '') !== '' ? (float)$_POST['projektsum'] : null,
    'salgsansvarlig' => trim($_POST['salgsansvarlig'] ?? '') ?: null,
    'kontaktperson_navn' => trim($_POST['kontaktperson_navn'] ?? '') ?: null,
    'kontaktperson_telefon' => trim($_POST['kontaktperson_telefon'] ?? '') ?: null,
    'kontaktperson_email' => trim($_POST['kontaktperson_email'] ?? '') ?: null,
    'opstartsdato' => $_POST['opstartsdato'] ?: null,
    'slutdato' => $_POST['slutdato'] ?: null,
    'noter' => trim($_POST['noter'] ?? '') ?: null,
];

$pdo->beginTransaction();
try {
    if ($id) {
        $stmt = $pdo->prepare(
            'UPDATE projekter SET navn=?, adresse=?, status=?, projektsum=?, salgsansvarlig=?,
             kontaktperson_navn=?, kontaktperson_telefon=?, kontaktperson_email=?,
             opstartsdato=?, slutdato=?, noter=? WHERE id=?'
        );
        $stmt->execute([...array_values($data), $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO projekter (navn, adresse, status, projektsum, salgsansvarlig,
             kontaktperson_navn, kontaktperson_telefon, kontaktperson_email,
             opstartsdato, slutdato, noter) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute(array_values($data));
        $id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('DELETE FROM projekt_underentreprenorer WHERE projekt_id = ?');
    $stmt->execute([$id]);

    $ueIds = $_POST['ue_id'] ?? [];
    $ueSums = $_POST['ue_sum'] ?? [];
    $set = [];
    $indsaet = $pdo->prepare(
        'INSERT INTO projekt_underentreprenorer (projekt_id, underentreprenor_id, aftalt_sum) VALUES (?, ?, ?)'
    );
    foreach ($ueIds as $i => $ueId) {
        $ueId = (int)$ueId;
        if ($ueId <= 0 || isset($set[$ueId])) {
            continue;
        }
        $set[$ueId] = true;
        $sum = isset($ueSums[$i]) && $ueSums[$i] !== '' ? (float)$ueSums[$i] : null;
        $indsaet->execute([$id, $ueId, $sum]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    die('Kunne ikke gemme projektet.');
}

header('Location: index.php');
exit;
