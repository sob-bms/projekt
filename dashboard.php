<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_login();

$filterSide = 'dashboard';
$gemtFilter = hent_gemt_filter($pdo, (int)$bruger['id'], $filterSide);
if (empty($_GET) && $gemtFilter !== null) {
    $qs = http_build_query(projekt_filter_ikke_tomme($gemtFilter));
    header('Location: dashboard.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}
$harGemtFilter = $gemtFilter !== null;

$filter = projekt_filter_fra_get($_GET);

// KPI'er og alle diagrammer bruger samme filtrerede grundlag som
// projektoversigten, så tallene altid matcher det man ser i listen.
$dash = hent_dashboard_data($pdo, $filter);
$statusPrAabenlukket = $dash['status_pr_aabenlukket'];
$antalPrSalgsresultat = $dash['antal_pr_salgsresultat'];
$ansvarligData = $dash['ansvarlig_data'];
$totalAntal = $dash['total_antal'];
$totalSum = $dash['total_sum'];
$antalTildelt = $dash['antal_tildelt'];
$antalIkkeTildelt = $dash['antal_ikke_tildelt'];
$andel = fn (int $del) => $totalAntal > 0 ? round($del / $totalAntal * 100) : 0;

$brugerListe = hent_brugere_liste($pdo);
$leadListe = $pdo->query("SELECT DISTINCT lead FROM projekter WHERE lead IS NOT NULL AND lead <> '' ORDER BY lead")->fetchAll(PDO::FETCH_COLUMN);
$byListe = $pdo->query("SELECT DISTINCT by_navn FROM projekter WHERE by_navn IS NOT NULL AND by_navn <> '' ORDER BY by_navn")->fetchAll(PDO::FETCH_COLUMN);
$harFiltre = projekt_filter_ikke_tomme($filter);

$jsonFlag = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

require __DIR__ . '/inc/header.php';
?>
<h1>Dashboard</h1>

<div class="noegletal">
    <div class="kort"><span class="tal"><?= $totalAntal ?></span><span class="label">Projekter i alt</span></div>
    <div class="kort"><span class="tal"><?= e(formatKrMio($totalSum)) ?></span><span class="label">Samlet projektsum</span></div>
    <div class="kort"><span class="tal"><?= $antalTildelt ?> (<?= $andel($antalTildelt) ?>%)</span><span class="label">Med BMS-ansvarlig</span></div>
    <div class="kort"><span class="tal"><?= $antalIkkeTildelt ?> (<?= $andel($antalIkkeTildelt) ?>%)</span><span class="label">Uden BMS-ansvarlig</span></div>
    <div class="kort"><span class="tal"><?= (int)($statusPrAabenlukket['Åben']['antal'] ?? 0) ?></span><span class="label">Åbne projekter</span></div>
    <div class="kort"><span class="tal"><?= (int)($statusPrAabenlukket['Lukket']['antal'] ?? 0) ?></span><span class="label">Lukkede projekter</span></div>
    <div class="kort"><span class="tal"><?= (int)($antalPrSalgsresultat['Vundet'] ?? 0) ?></span><span class="label">Vundne projekter</span></div>
    <div class="kort"><span class="tal"><?= (int)($antalPrSalgsresultat['Tabt'] ?? 0) ?></span><span class="label">Tabte projekter</span></div>
</div>

<div class="chart-grid">
    <div class="chart-boks">
        <h2>Projektsum fordelt på åben, lukket og annulleret</h2>
        <canvas id="chartStatusSum"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Tildelte kontra ikke tildelte projekter</h2>
        <canvas id="chartTildeling"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Åbne kontra lukkede projekter</h2>
        <canvas id="chartAabenLukket"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Antal projekter pr. primær BMS-ansvarlig</h2>
        <canvas id="chartAnsvarligAntal"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Projektsum pr. primær BMS-ansvarlig (kr.)</h2>
        <canvas id="chartAnsvarligSum"></canvas>
    </div>
</div>

<?php require __DIR__ . '/inc/filterbar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const statusPrAabenlukket = <?= json_encode($statusPrAabenlukket, $jsonFlag) ?>;
const statusLabels = <?= json_encode(AABENLUKKET_LISTE, $jsonFlag) ?>;
const statusSum = statusLabels.map((label) => statusPrAabenlukket[label] ? parseFloat(statusPrAabenlukket[label].sum) : 0);

const tildelingLabels = ['Tildelt', 'Ikke tildelt'];
const tildelingData = [<?= (int)$antalTildelt ?>, <?= (int)$antalIkkeTildelt ?>];

const aabenLukketLabels = ['Åben', 'Lukket', 'Annulleret'];
const aabenLukketData = aabenLukketLabels.map((label) => statusPrAabenlukket[label] ? parseInt(statusPrAabenlukket[label].antal, 10) : 0);

const ansvarligLabels = <?= json_encode(array_column($ansvarligData, 'navn'), $jsonFlag) ?>;
const ansvarligAntal = <?= json_encode(array_map('intval', array_column($ansvarligData, 'antal'))) ?>;
const ansvarligSum = <?= json_encode(array_map('floatval', array_column($ansvarligData, 'sum'))) ?>;

const dkTal = new Intl.NumberFormat('da-DK');

function lavSoejlediagram(id, labels, data, farve, formatterKr) {
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: farve }] },
        options: {
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            const v = ctx.parsed.y;
                            return formatterKr ? dkTal.format(v) + ' kr.' : dkTal.format(v);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v) => formatterKr ? dkTal.format(v) : v }
                }
            }
        }
    });
}

lavSoejlediagram('chartStatusSum', statusLabels, statusSum, ['#2563eb', '#059669', '#dc2626'], true);
lavSoejlediagram('chartTildeling', tildelingLabels, tildelingData, ['#2563eb', '#d97706'], false);
lavSoejlediagram('chartAabenLukket', aabenLukketLabels, aabenLukketData, ['#2563eb', '#059669', '#dc2626'], false);
lavSoejlediagram('chartAnsvarligAntal', ansvarligLabels, ansvarligAntal, '#7c3aed', false);
lavSoejlediagram('chartAnsvarligSum', ansvarligLabels, ansvarligSum, '#0891b2', true);
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
