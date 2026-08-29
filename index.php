<?php
require __DIR__ . '/inc/bootstrap.php';

$status = $_GET['status'] ?? '';
$salgsansvarlig = $_GET['salgsansvarlig'] ?? '';
$soeg = trim($_GET['soeg'] ?? '');

$sortKolonner = [
    'navn' => 'p.navn',
    'status' => 'p.status',
    'projektsum' => 'p.projektsum',
    'salgsansvarlig' => 'p.salgsansvarlig',
    'opstartsdato' => 'p.opstartsdato',
];
$sort = $_GET['sort'] ?? 'navn';
if (!isset($sortKolonner[$sort])) {
    $sort = 'navn';
}
$retning = ($_GET['retning'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($salgsansvarlig !== '') {
    $where[] = 'p.salgsansvarlig = ?';
    $params[] = $salgsansvarlig;
}
if ($soeg !== '') {
    $where[] = '(p.navn LIKE ? OR p.adresse LIKE ? OR p.kontaktperson_navn LIKE ?)';
    $like = "%$soeg%";
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT p.* FROM projekter p $whereSql ORDER BY {$sortKolonner[$sort]} $retning";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projekter = $stmt->fetchAll();

$salgsansvarlige = $pdo->query(
    "SELECT DISTINCT salgsansvarlig FROM projekter WHERE salgsansvarlig IS NOT NULL AND salgsansvarlig <> '' ORDER BY salgsansvarlig"
)->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/inc/header.php';
?>
<div class="side-header">
    <h1>Byggeprojekter</h1>
    <a class="knap" href="projekt-form.php">+ Nyt projekt</a>
</div>

<form method="get" class="filterbar">
    <input type="text" name="soeg" placeholder="Søg på navn, adresse eller kontakt" value="<?= e($soeg) ?>">
    <select name="status">
        <option value="">Alle statusser</option>
        <?php foreach (STATUS_LISTE as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="salgsansvarlig">
        <option value="">Alle salgsansvarlige</option>
        <?php foreach ($salgsansvarlige as $s): ?>
            <option value="<?= e($s) ?>" <?= $salgsansvarlig === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filtrér</button>
    <?php if ($status || $salgsansvarlig || $soeg): ?><a href="index.php">Nulstil</a><?php endif; ?>
</form>

<table class="data-tabel">
    <thead>
        <tr>
            <th><?= sorteringsLink('navn', 'Navn') ?></th>
            <th><?= sorteringsLink('status', 'Status') ?></th>
            <th><?= sorteringsLink('projektsum', 'Projektsum') ?></th>
            <th><?= sorteringsLink('salgsansvarlig', 'Salgsansvarlig') ?></th>
            <th><?= sorteringsLink('opstartsdato', 'Opstart') ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($projekter as $p): ?>
        <tr>
            <td><a href="projekt-form.php?id=<?= (int)$p['id'] ?>"><?= e($p['navn']) ?></a></td>
            <td><span class="badge badge-<?= statusKlasse($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= e(formatKr($p['projektsum'])) ?></td>
            <td><?= e($p['salgsansvarlig']) ?></td>
            <td><?= e(formatDato($p['opstartsdato'])) ?></td>
            <td class="handlinger">
                <a href="projekt-form.php?id=<?= (int)$p['id'] ?>">Rediger</a>
                <form method="post" action="projekt-slet.php" onsubmit="return confirm('Slet projektet <?= e(addslashes($p['navn'])) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="link-knap">Slet</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$projekter): ?>
        <tr><td colspan="6">Ingen projekter fundet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
