<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_rolle([ROLLE_ADMINISTRATOR]);

// "Slet" deaktiverer i stedet for at slette rækken: brugeren kan stå
// registreret som opretter/ændrer af tidligere projekter (audit-historik),
// og den tilknytning skal bevares selvom personen stopper.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: brugere.php');
    exit;
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0 && $id !== (int)$bruger['id']) {
    $stmt = $pdo->prepare('UPDATE brugere SET aktiv = NOT aktiv WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: brugere.php');
exit;
