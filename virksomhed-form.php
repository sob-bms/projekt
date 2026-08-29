<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_rolle([ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$v = ['navn' => '', 'cvr' => '', 'telefon' => '', 'email' => '', 'note' => ''];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM virksomheder WHERE id = ?');
    $stmt->execute([$id]);
    $fundet = $stmt->fetch();
    if (!$fundet) {
        http_response_code(404);
        die('Virksomhed ikke fundet.');
    }
    $v = $fundet;
}

require __DIR__ . '/inc/header.php';
?>
<h1><?= $id ? 'Rediger virksomhed' : 'Ny virksomhed' ?></h1>
<form method="post" action="virksomhed-gem.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
    <label>Navn *
        <input type="text" name="navn" required value="<?= e($v['navn']) ?>">
    </label>
    <label>CVR
        <input type="text" name="cvr" value="<?= e($v['cvr']) ?>">
    </label>
    <label>Telefon
        <input type="text" name="telefon" value="<?= e($v['telefon']) ?>">
    </label>
    <label>E-mail
        <input type="email" name="email" value="<?= e($v['email']) ?>">
    </label>
    <label>Note
        <textarea name="note" rows="3"><?= e($v['note']) ?></textarea>
    </label>
    <button type="submit">Gem</button>
    <a href="virksomheder.php">Annullér</a>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
