<?php
require __DIR__ . '/inc/bootstrap.php';
kraev_rolle([ROLLE_ADMINISTRATOR]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$b = ['initialer' => '', 'navn' => '', 'email' => '', 'rolle' => ROLLE_LAESER, 'aktiv' => 1];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM brugere WHERE id = ?');
    $stmt->execute([$id]);
    $fundet = $stmt->fetch();
    if (!$fundet) {
        http_response_code(404);
        die('Bruger ikke fundet.');
    }
    $b = $fundet;
}

require __DIR__ . '/inc/header.php';
?>
<h1><?= $id ? 'Rediger bruger' : 'Ny bruger' ?></h1>
<form method="post" action="bruger-gem.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
    <label>Initialer *
        <input type="text" name="initialer" required maxlength="20" value="<?= e($b['initialer']) ?>">
    </label>
    <label>Navn *
        <input type="text" name="navn" required value="<?= e($b['navn']) ?>">
    </label>
    <label>E-mail
        <input type="email" name="email" value="<?= e($b['email']) ?>">
    </label>
    <label>Rolle
        <select name="rolle">
            <?php foreach (ALLE_ROLLER as $r): ?>
                <option value="<?= e($r) ?>" <?= $b['rolle'] === $r ? 'selected' : '' ?>><?= e(rolle_label($r)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <input type="checkbox" name="aktiv" value="1" style="display:inline-block;width:auto;" <?= $b['aktiv'] ? 'checked' : '' ?>>
        Aktiv
    </label>
    <label>Adgangskode <?= $id ? '(lad stå tom for at bevare den nuværende)' : '*' ?>
        <input type="password" name="adgangskode" <?= $id ? '' : 'required' ?> minlength="8" autocomplete="new-password">
    </label>
    <button type="submit">Gem</button>
    <a href="brugere.php">Annullér</a>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
