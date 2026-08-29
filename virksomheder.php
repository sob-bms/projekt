<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_login();

$soeg = trim($_GET['soeg'] ?? '');
$where = '';
$params = [];
if ($soeg !== '') {
    $where = 'WHERE v.navn LIKE ? OR v.cvr LIKE ?';
    $like = "%$soeg%";
    $params = [$like, $like];
}

$sql = "SELECT v.*, COUNT(DISTINCT pv.projekt_id) AS antal_projekter
        FROM virksomheder v
        LEFT JOIN projekt_virksomheder pv ON pv.virksomhed_id = v.id
        $where
        GROUP BY v.id
        ORDER BY v.navn";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$liste = $stmt->fetchAll();

require __DIR__ . '/inc/header.php';
?>
<div class="side-header">
    <h1>Virksomheder</h1>
    <?php if (kan_redigere($bruger)): ?><a class="knap" href="virksomhed-form.php">+ Ny virksomhed</a><?php endif; ?>
</div>

<form method="get" class="filterbar">
    <input type="text" name="soeg" placeholder="Søg på navn eller CVR" value="<?= e($soeg) ?>">
    <button type="submit">Søg</button>
    <?php if ($soeg !== ''): ?><a href="virksomheder.php">Nulstil</a><?php endif; ?>
</form>

<table class="data-tabel">
    <thead>
        <tr><th>Navn</th><th>CVR</th><th>Telefon</th><th>E-mail</th><th>Projekter</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($liste as $v): ?>
        <tr>
            <td><?= e($v['navn']) ?></td>
            <td><?= e($v['cvr']) ?></td>
            <td><?= e($v['telefon']) ?></td>
            <td><?= e($v['email']) ?></td>
            <td><?= (int)$v['antal_projekter'] ?></td>
            <td class="handlinger">
                <?php if (kan_redigere($bruger)): ?>
                <a href="virksomhed-form.php?id=<?= (int)$v['id'] ?>">Rediger</a>
                <form method="post" action="virksomhed-slet.php" onsubmit="return confirm('Slet <?= e(addslashes($v['navn'])) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                    <button type="submit" class="link-knap">Slet</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$liste): ?>
        <tr><td colspan="6">Ingen virksomheder fundet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
