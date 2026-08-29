<?php
require __DIR__ . '/inc/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ue = ['navn' => '', 'fag' => '', 'telefon' => '', 'email' => ''];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM underentreprenorer WHERE id = ?');
    $stmt->execute([$id]);
    $fundet = $stmt->fetch();
    if (!$fundet) {
        http_response_code(404);
        die('Underentreprenør ikke fundet.');
    }
    $ue = $fundet;
}

require __DIR__ . '/inc/header.php';
?>
<h1><?= $id ? 'Rediger underentreprenør' : 'Ny underentreprenør' ?></h1>
<form method="post" action="underentreprenor-gem.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
    <label>Navn *
        <input type="text" name="navn" required value="<?= e($ue['navn']) ?>">
    </label>
    <label>Fag
        <input type="text" name="fag" placeholder="fx tømrer, elektriker" value="<?= e($ue['fag']) ?>">
    </label>
    <label>Telefon
        <input type="text" name="telefon" value="<?= e($ue['telefon']) ?>">
    </label>
    <label>E-mail
        <input type="email" name="email" value="<?= e($ue['email']) ?>">
    </label>
    <button type="submit">Gem</button>
    <a href="underentreprenorer.php">Annullér</a>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
