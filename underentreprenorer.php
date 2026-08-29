<?php
require __DIR__ . '/inc/bootstrap.php';

$soeg = trim($_GET['soeg'] ?? '');
$where = '';
$params = [];
if ($soeg !== '') {
    $where = 'WHERE u.navn LIKE ? OR u.fag LIKE ?';
    $like = "%$soeg%";
    $params = [$like, $like];
}

$sql = "SELECT u.*, COUNT(pu.projekt_id) AS antal_projekter
        FROM underentreprenorer u
        LEFT JOIN projekt_underentreprenorer pu ON pu.underentreprenor_id = u.id
        $where
        GROUP BY u.id
        ORDER BY u.navn";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$liste = $stmt->fetchAll();

require __DIR__ . '/inc/header.php';
?>
<div class="side-header">
    <h1>Underentreprenører</h1>
    <a class="knap" href="underentreprenor-form.php">+ Ny underentreprenør</a>
</div>

<form method="get" class="filterbar">
    <input type="text" name="soeg" placeholder="Søg på navn eller fag" value="<?= e($soeg) ?>">
    <button type="submit">Søg</button>
    <?php if ($soeg !== ''): ?><a href="underentreprenorer.php">Nulstil</a><?php endif; ?>
</form>

<table class="data-tabel">
    <thead>
        <tr><th>Navn</th><th>Fag</th><th>Telefon</th><th>E-mail</th><th>Projekter</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($liste as $ue): ?>
        <tr>
            <td><?= e($ue['navn']) ?></td>
            <td><?= e($ue['fag']) ?></td>
            <td><?= e($ue['telefon']) ?></td>
            <td><?= e($ue['email']) ?></td>
            <td><?= (int)$ue['antal_projekter'] ?></td>
            <td class="handlinger">
                <a href="underentreprenor-form.php?id=<?= (int)$ue['id'] ?>">Rediger</a>
                <form method="post" action="underentreprenor-slet.php" onsubmit="return confirm('Slet <?= e(addslashes($ue['navn'])) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$ue['id'] ?>">
                    <button type="submit" class="link-knap">Slet</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$liste): ?>
        <tr><td colspan="6">Ingen underentreprenører fundet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
