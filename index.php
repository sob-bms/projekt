<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_login();

$filterSide = 'projekter';
$gemtFilter = hent_gemt_filter($pdo, (int)$bruger['id'], $filterSide);
if (empty($_GET) && $gemtFilter !== null) {
    $qs = http_build_query(projekt_filter_ikke_tomme($gemtFilter));
    header('Location: index.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}
$harGemtFilter = $gemtFilter !== null;

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
    'hovedentreprenoer' => 'hovedentreprenoer',
    'kontakt' => 'primaer_kontakt',
    'ansvarlig' => 'primaer_ansvarlig',
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

$harFiltre = projekt_filter_ikke_tomme($filter);

require __DIR__ . '/inc/header.php';
?>
<div class="side-header">
    <h1>Byggeprojekter</h1>
    <?php if (kan_redigere($bruger)): ?><a class="knap" href="projekt-form.php">+ Nyt projekt</a><?php endif; ?>
</div>

<?php require __DIR__ . '/inc/filterbar.php'; ?>

<div class="tabel-vaerktoejer">
    <div class="tabel-vaerktoejer-venstre">
        <p class="resultat-tal"><?= (int)$antalIalt ?> fundet.</p>
        <?php if (!$projekter): ?><p class="ingen-resultater">Ingen projekter fundet.</p><?php endif; ?>
    </div>
    <div class="kolonne-vaelger">
        <button type="button" class="kolonne-vaelger-knap" id="kolonne-vaelger-knap" aria-expanded="false" aria-controls="kolonne-vaelger-panel">Vis/skjul kolonner</button>
        <div class="kolonne-vaelger-panel" id="kolonne-vaelger-panel" hidden>
            <label><input type="checkbox" data-kolonne-toggle="projektsum"> Projektsum</label>
            <label><input type="checkbox" data-kolonne-toggle="by"> Adresse / by</label>
            <label><input type="checkbox" data-kolonne-toggle="byggestart"> Byggestart</label>
            <label><input type="checkbox" data-kolonne-toggle="byggeslut"> Byggeslut</label>
            <label><input type="checkbox" data-kolonne-toggle="hovedentreprenoer"> Hovedentr./kunde</label>
            <label><input type="checkbox" data-kolonne-toggle="kontakt"> Primær kontakt</label>
            <label><input type="checkbox" data-kolonne-toggle="ansvarlig"> BMS-ansvarlig</label>
            <label><input type="checkbox" data-kolonne-toggle="status"> Status</label>
            <label><input type="checkbox" data-kolonne-toggle="salg"> Salg</label>
            <label><input type="checkbox" data-kolonne-toggle="opdateret"> Senest ændret</label>
        </div>
    </div>
</div>

<div class="tabel-scroll-top" id="tabel-scroll-top"><div></div></div>
<div class="tabel-scroll" id="tabel-scroll">
<table class="data-tabel" id="data-tabel">
    <thead>
        <tr>
            <th><?= sorteringsLink('navn', 'Navn') ?></th>
            <th data-kolonne="projektsum"><?= sorteringsLink('projektsum', 'Projektsum') ?></th>
            <th data-kolonne="by"><?= sorteringsLink('by', 'Adresse / by') ?></th>
            <th data-kolonne="byggestart"><?= sorteringsLink('byggestart', 'Byggestart') ?></th>
            <th data-kolonne="byggeslut"><?= sorteringsLink('byggeslut', 'Byggeslut') ?></th>
            <th data-kolonne="hovedentreprenoer"><?= sorteringsLink('hovedentreprenoer', 'Hovedentr./kunde') ?></th>
            <th data-kolonne="kontakt"><?= sorteringsLink('kontakt', 'Primær kontakt') ?></th>
            <th data-kolonne="ansvarlig"><?= sorteringsLink('ansvarlig', 'BMS-ansvarlig') ?></th>
            <th data-kolonne="status"><?= sorteringsLink('aabenlukket', 'Status') ?></th>
            <th data-kolonne="salg"><?= sorteringsLink('salgsresultat', 'Salg') ?></th>
            <th data-kolonne="opdateret"><?= sorteringsLink('opdateret', 'Senest ændret') ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($projekter as $p): ?>
        <tr>
            <td><a href="projekt-detalje.php?id=<?= (int)$p['id'] ?>"><?= e($p['navn']) ?></a></td>
            <td data-kolonne="projektsum"><?= e(formatKrMio($p['projektsum'])) ?></td>
            <td data-kolonne="by"><?= e(trim(($p['adresse'] ?? '') . ', ' . ($p['by_navn'] ?? ''), ', ')) ?: '–' ?></td>
            <td data-kolonne="byggestart">
                <?= e(formatMaaned($p['byggestart_maaned'])) ?>
                <?php if ($p['byggestart_bekraeftet']): ?><span class="badge badge-bekraeftet" title="Byggestart bekræftet">✓</span><?php endif; ?>
            </td>
            <td data-kolonne="byggeslut"><?= e(formatMaaned($p['byggeslut_maaned'])) ?></td>
            <td data-kolonne="hovedentreprenoer"><?= e($p['hovedentreprenoer'] ?? '') ?: '–' ?></td>
            <td data-kolonne="kontakt"><?= e($p['primaer_kontakt'] ?? '') ?: '–' ?></td>
            <td data-kolonne="ansvarlig">
                <?= e($p['primaer_ansvarlig'] ?? '') ?: '<span class="ikke-tildelt">Ikke tildelt</span>' ?>
                <?php if ($p['medansvarlige']): ?><div class="medansvarlige">+ <?= e($p['medansvarlige']) ?></div><?php endif; ?>
            </td>
            <td data-kolonne="status"><span class="badge badge-<?= aabenlukket_klasse($p['aabenlukket']) ?>"><?= e($p['aabenlukket']) ?></span></td>
            <td data-kolonne="salg"><span class="badge badge-<?= salgsresultat_klasse($p['salgsresultat']) ?>"><?= e($p['salgsresultat']) ?></span></td>
            <td data-kolonne="opdateret"><?= e(formatDatoTid($p['opdateret'])) ?></td>
            <td class="handlinger">
                <a href="projekt-detalje.php?id=<?= (int)$p['id'] ?>">Åbn</a>
                <?php if (kan_redigere($bruger)): ?>
                <a href="projekt-form.php?id=<?= (int)$p['id'] ?>">Rediger</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
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
