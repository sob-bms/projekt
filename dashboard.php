<?php
require __DIR__ . '/inc/bootstrap.php';

$statusData = $pdo->query(
    'SELECT status, COUNT(*) AS antal, COALESCE(SUM(projektsum),0) AS sum FROM projekter GROUP BY status'
)->fetchAll();

$salgData = $pdo->query(
    "SELECT COALESCE(NULLIF(salgsansvarlig,''),'Ikke angivet') AS salgsansvarlig,
            COUNT(*) AS antal, COALESCE(SUM(projektsum),0) AS sum
     FROM projekter GROUP BY salgsansvarlig ORDER BY sum DESC"
)->fetchAll();

$totalAntal = array_sum(array_column($statusData, 'antal'));
$totalSum = array_sum(array_column($statusData, 'sum'));

$jsonFlag = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

require __DIR__ . '/inc/header.php';
?>
<h1>Dashboard</h1>
<div class="noegletal">
    <div class="kort"><span class="tal"><?= (int)$totalAntal ?></span><span class="label">Projekter i alt</span></div>
    <div class="kort"><span class="tal"><?= e(formatKr($totalSum)) ?></span><span class="label">Samlet projektsum</span></div>
</div>

<div class="chart-grid">
    <div class="chart-boks">
        <h2>Antal projekter pr. status</h2>
        <canvas id="chartStatusAntal"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Projektsum pr. status (kr.)</h2>
        <canvas id="chartStatusSum"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Projektsum pr. salgsansvarlig (kr.)</h2>
        <canvas id="chartSalgSum"></canvas>
    </div>
    <div class="chart-boks">
        <h2>Antal projekter pr. salgsansvarlig</h2>
        <canvas id="chartSalgAntal"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const statusLabels = <?= json_encode(array_column($statusData, 'status'), $jsonFlag) ?>;
const statusAntal = <?= json_encode(array_map('intval', array_column($statusData, 'antal'))) ?>;
const statusSum = <?= json_encode(array_map('floatval', array_column($statusData, 'sum'))) ?>;

const salgLabels = <?= json_encode(array_column($salgData, 'salgsansvarlig'), $jsonFlag) ?>;
const salgSum = <?= json_encode(array_map('floatval', array_column($salgData, 'sum'))) ?>;
const salgAntal = <?= json_encode(array_map('intval', array_column($salgData, 'antal'))) ?>;

function lavSoejlediagram(id, labels, data, farve) {
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: farve }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}

lavSoejlediagram('chartStatusAntal', statusLabels, statusAntal, '#2563eb');
lavSoejlediagram('chartStatusSum', statusLabels, statusSum, '#0891b2');
lavSoejlediagram('chartSalgSum', salgLabels, salgSum, '#7c3aed');
lavSoejlediagram('chartSalgAntal', salgLabels, salgAntal, '#059669');
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
