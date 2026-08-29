<?php
require __DIR__ . '/inc/bootstrap.php';

if (aktuel_bruger()) {
    header('Location: index.php');
    exit;
}

$fejl = null;
$naeste = $_GET['naeste'] ?? $_POST['naeste'] ?? 'index.php';
if (!str_starts_with($naeste, '/') && str_contains($naeste, '://')) {
    $naeste = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $initialer = trim($_POST['initialer'] ?? '');
    $adgangskode = (string)($_POST['adgangskode'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM brugere WHERE initialer = ? AND aktiv = 1');
    $stmt->execute([$initialer]);
    $bruger = $stmt->fetch();

    if ($bruger && $bruger['password_hash'] && password_verify($adgangskode, $bruger['password_hash'])) {
        log_ind($bruger);
        header('Location: ' . ($naeste !== '' ? $naeste : 'index.php'));
        exit;
    }
    $fejl = 'Forkerte initialer eller adgangskode.';
}

require __DIR__ . '/inc/header.php';
?>
<div class="login-boks">
    <h1>Log ind</h1>
    <?php if ($fejl): ?><p class="fejlbesked"><?= e($fejl) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="naeste" value="<?= e($naeste) ?>">
        <label>Initialer
            <input type="text" name="initialer" autofocus required autocomplete="username">
        </label>
        <label>Adgangskode
            <input type="password" name="adgangskode" required autocomplete="current-password">
        </label>
        <button type="submit">Log ind</button>
    </form>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
