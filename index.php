<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_login();

$filter = projekt_filter_fra_get($_GET);
$hvor = byg_projekt_where($filter);

$sortKolonner = [
    'navn' => 'p.navn',
    'projektsum' => 'p.projektsum',
    'by' => 'p.by_navn',
    'byggestart' => 'p.byggestart_maaned',
    'byggeslut' => 'p.byggeslut_maaned',
    'aabenlukket' => 'p.aabenlukket',
    'salgsresultat' => 'p.salgsresultat',
    'opdateret' => 'p.opdateret',
];
$sort = $_GET['sort'] ?? 'opdateret';
if (!isset($sortKolonner[$sort])) {
    $sort = 'opdateret';
}
$retning = ($_GET['retning'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$prSide = 25;
$side = max(1, (int)($_GET['side'] ?? 1));

$antalStmt = $pdo->prepare("SELECT COUNT(*) FROM projekter p WHERE {$hvor['sql']}");
$antalStmt->execute($hvor['params']);
$antalIalt = (int)$antalStmt->fetchColumn();
$antalSider = max(1, (int)ceil($antalIalt / $prSide));
$side = min($side, $antalSider);
$offset = ($side - 1) * $prSide;

$sql = "SELECT p.*,
        (SELECT b.navn FROM projekt_ansvarlige pa JOIN brugere b ON b.id = pa.bruger_id
         WHERE pa.projekt_id = p.id AND pa.primaer = 1 LIMIT 1) AS primaer_ansvarlig,
        (SELECT GROUP_CONCAT(b2.navn SEPARATOR ', ') FROM projekt_ansvarlige pa2 JOIN brugere b2 ON b2.id = pa2.bruger_id
         WHERE pa2.projekt_id = p.id AND pa2.primaer = 0) AS medansvarlige,
        (SELECT v.navn FROM projekt_virksomheder pv JOIN virksomheder v ON v.id = pv.virksomhed_id
         WHERE pv.projekt_id = p.id AND pv.rolle IN ('Hovedentreprenør', 'Kunde')
         ORDER BY FIELD(pv.rolle, 'Hovedentreprenør', 'Kunde') LIMIT 1) AS hovedentreprenoer,
        (SELECT k.navn FROM kontaktpersoner k WHERE k.projekt_id = p.id AND k.primaer = 1 LIMIT 1) AS primaer_kontakt
        FROM projekter p
        WHERE {$hvor['sql']}
        ORDER BY {$sortKolonner[$sort]} $retning, p.id DESC
        LIMIT $prSide OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($hvor['params']);
$projekter = $stmt->fetchAll();

$brugerListe = hent_brugere_liste($pdo);
$leadListe = $pdo->query(
    "SELECT DISTINCT lead FROM projekter WHERE lead IS NOT NULL AND lead <> '' ORDER BY lead"
)->fetchAll(PDO::FETCH_COLUMN);
$byListe = $pdo->query(
    "SELECT DISTINCT by_navn FROM projekter WHERE by_navn IS NOT NULL AND by_navn <> '' ORDER BY by_navn"
)->fetchAll(PDO::FETCH_COLUMN);

$harFiltre = array_filter($filter, fn ($v) => $v !== '' && $v !== [] && $v !== false);

require __DIR__ . '/inc/header.php';
?>
<div class="side-header">
    <h1>Byggeprojekter</h1>
    <?php if (kan_redigere($bruger)): ?><a class="knap" href="projekt-form.php">+ Nyt projekt</a><?php endif; ?>
</div>

<form method="get" class="filterbar filterbar-udvidet">
    <input type="text" name="soeg" placeholder="Søg på navn, adresse, by, virksomhed, kontakt, notat" value="<?= e($filter['soeg']) ?>" class="soegefelt">

    <fieldset class="filter-gruppe">
        <legend>Byggestart</legend>
        <label class="inline">Fra <input type="month" name="byggestart_fra" value="<?= e($filter['byggestart_fra']) ?>"></label>
        <label class="inline">Til <input type="month" name="byggestart_til" value="<?= e($filter['byggestart_til']) ?>"></label>
        <select name="byggestart_status">
            <option value="">Alle</option>
            <option value="bekraeftet" <?= $filter['byggestart_status'] === 'bekraeftet' ? 'selected' : '' ?>>Bekræftet</option>
            <option value="ikke_bekraeftet" <?= $filter['byggestart_status'] === 'ikke_bekraeftet' ? 'selected' : '' ?>>Ikke bekræftet</option>
            <option value="ukendt" <?= $filter['byggestart_status'] === 'ukendt' ? 'selected' : '' ?>>Ukendt dato</option>
        </select>
    </fieldset>

    <fieldset class="filter-gruppe">
        <legend>BMS-ansvarlig</legend>
        <select name="ansvarlig[]" multiple size="3" class="ansvarlig-multi">
            <?php foreach ($brugerListe as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= in_array((int)$b['id'], $filter['ansvarlig'], true) ? 'selected' : '' ?>><?= e($b['navn']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="inline"><input type="checkbox" name="kun_primaer" value="1" <?= $filter['kun_primaer'] ? 'checked' : '' ?>> Kun som primær</label>
        <select name="ansvarlig_tildeling">
            <option value="">Alle</option>
            <option value="tildelt" <?= $filter['ansvarlig_tildeling'] === 'tildelt' ? 'selected' : '' ?>>Tildelt</option>
            <option value="ikke_tildelt" <?= $filter['ansvarlig_tildeling'] === 'ikke_tildelt' ? 'selected' : '' ?>>Ikke tildelt</option>
        </select>
    </fieldset>

    <fieldset class="filter-gruppe">
        <legend>Status</legend>
        <select name="aabenlukket">
            <option value="">Åben/lukket - alle</option>
            <?php foreach (AABENLUKKET_LISTE as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter['aabenlukket'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="salgsresultat">
            <option value="">Salgsresultat - alle</option>
            <?php foreach (SALGSRESULTAT_LISTE as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter['salgsresultat'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </fieldset>

    <select name="lead">
        <option value="">Leadkilde - alle</option>
        <?php foreach ($leadListe as $l): ?>
            <option value="<?= e($l) ?>" <?= $filter['lead'] === $l ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="by">
        <option value="">By - alle</option>
        <?php foreach ($byListe as $b): ?>
            <option value="<?= e($b) ?>" <?= $filter['by'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrér</button>
    <?php if ($harFiltre): ?><a href="index.php" class="nulstil-knap">Nulstil alle filtre</a><?php endif; ?>
</form>

<p class="resultat-tal"><?= (int)$antalIalt ?> projekt(er) fundet.</p>

<div class="tabel-scroll">
<table class="data-tabel">
    <thead>
        <tr>
            <th><?= sorteringsLink('navn', 'Navn') ?></th>
            <th><?= sorteringsLink('projektsum', 'Projektsum') ?></th>
            <th><?= sorteringsLink('by', 'Adresse / by') ?></th>
            <th><?= sorteringsLink('byggestart', 'Byggestart') ?></th>
            <th><?= sorteringsLink('byggeslut', 'Byggeslut') ?></th>
            <th>Hovedentr./kunde</th>
            <th>Primær kontakt</th>
            <th>BMS-ansvarlig</th>
            <th><?= sorteringsLink('aabenlukket', 'Status') ?></th>
            <th><?= sorteringsLink('salgsresultat', 'Salg') ?></th>
            <th><?= sorteringsLink('opdateret', 'Senest ændret') ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($projekter as $p): ?>
        <tr>
            <td><a href="projekt-detalje.php?id=<?= (int)$p['id'] ?>"><?= e($p['navn']) ?></a></td>
            <td><?= e(formatKrMio($p['projektsum'])) ?></td>
            <td><?= e(trim(($p['adresse'] ?? '') . ', ' . ($p['by_navn'] ?? ''), ', ')) ?: '–' ?></td>
            <td>
                <?= e(formatMaaned($p['byggestart_maaned'])) ?>
                <?php if ($p['byggestart_bekraeftet']): ?><span class="badge badge-bekraeftet" title="Byggestart bekræftet">✓</span><?php endif; ?>
            </td>
            <td><?= e(formatMaaned($p['byggeslut_maaned'])) ?></td>
            <td><?= e($p['hovedentreprenoer'] ?? '') ?: '–' ?></td>
            <td><?= e($p['primaer_kontakt'] ?? '') ?: '–' ?></td>
            <td>
                <?= e($p['primaer_ansvarlig'] ?? '') ?: '<span class="ikke-tildelt">Ikke tildelt</span>' ?>
                <?php if ($p['medansvarlige']): ?><div class="medansvarlige">+ <?= e($p['medansvarlige']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge badge-<?= aabenlukket_klasse($p['aabenlukket']) ?>"><?= e($p['aabenlukket']) ?></span></td>
            <td><span class="badge badge-<?= salgsresultat_klasse($p['salgsresultat']) ?>"><?= e($p['salgsresultat']) ?></span></td>
            <td><?= e(formatDatoTid($p['opdateret'])) ?></td>
            <td class="handlinger">
                <a href="projekt-detalje.php?id=<?= (int)$p['id'] ?>">Åbn</a>
                <?php if (kan_redigere($bruger)): ?>
                <a href="projekt-form.php?id=<?= (int)$p['id'] ?>">Rediger</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$projekter): ?>
        <tr><td colspan="12">Ingen projekter fundet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php if ($antalSider > 1): ?>
<nav class="paginering">
    <?php if ($side > 1): ?><a href="<?= e(byg_link(['side' => $side - 1])) ?>">&laquo; Forrige</a><?php endif; ?>
    <span>Side <?= $side ?> af <?= $antalSider ?></span>
    <?php if ($side < $antalSider): ?><a href="<?= e(byg_link(['side' => $side + 1])) ?>">Næste &raquo;</a><?php endif; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
