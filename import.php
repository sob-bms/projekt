<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/import/XlsxReader.php';
require __DIR__ . '/inc/import/Importer.php';

$bruger = kraev_rolle([ROLLE_ADMINISTRATOR]);

$importMappe = __DIR__ . '/data/import';
if (!is_dir($importMappe)) {
    mkdir($importMappe, 0775, true);
}

$fejlbesked = null;
$resultat = null;
$dryRun = true;
$valgtFil = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_FILES['xlsx_fil']) && $_FILES['xlsx_fil']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['xlsx_fil']['error'] !== UPLOAD_ERR_OK) {
            $fejlbesked = 'Filupload fejlede.';
        } else {
            $originaltNavn = $_FILES['xlsx_fil']['name'];
            if (strtolower((string)pathinfo($originaltNavn, PATHINFO_EXTENSION)) !== 'xlsx') {
                $fejlbesked = 'Kun .xlsx-filer kan uploades.';
            } else {
                $sikkertNavn = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($originaltNavn));
                $maalSti = $importMappe . '/' . date('Y-m-d_His') . '_' . $sikkertNavn;
                if (!move_uploaded_file($_FILES['xlsx_fil']['tmp_name'], $maalSti)) {
                    $fejlbesked = 'Kunne ikke gemme den uploadede fil.';
                } else {
                    $valgtFil = basename($maalSti);
                }
            }
        }
    } elseif (isset($_POST['fil'])) {
        $valgtFil = basename($_POST['fil']);
        $dryRun = !empty($_POST['dry_run']);

        $fuldSti = $importMappe . '/' . $valgtFil;
        if (!is_file($fuldSti)) {
            $fejlbesked = 'Filen findes ikke længere i data/import/.';
        } else {
            try {
                $reader = new XlsxReader($fuldSti);
                $importer = new Importer($pdo, $reader);
                $resultat = $importer->koer($dryRun);
            } catch (Throwable $e) {
                $fejlbesked = 'Import fejlede: ' . $e->getMessage();
            }
        }
    }
}

$filer = glob($importMappe . '/*.xlsx') ?: [];
rsort($filer);

require __DIR__ . '/inc/header.php';
?>
<h1>Excel-import</h1>
<p class="mindre">
    Importerer projekter fra arket "Projekter" (data fra række 4). Kun tilgængelig for administratorer.
    Kør altid en dry-run først og gennemgå advarslerne, inden den endelige import køres.
</p>

<?php if ($fejlbesked): ?><p class="fejlbesked"><?= e($fejlbesked) ?></p><?php endif; ?>

<section class="detalje-boks">
    <h2>1. Upload en projektoversigt (.xlsx)</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Vælg fil
            <input type="file" name="xlsx_fil" accept=".xlsx" required>
        </label>
        <button type="submit">Upload</button>
    </form>
    <p class="mindre">
        Filen lægges i <code>data/import/</code> på serveren (uden for git). Har du ikke adgang til at
        uploade herfra, kan filen i stedet placeres direkte i <code>data/import/</code> på serveren, fx via
        <code>scp</code>, hvorefter den vil fremgå af listen nedenfor.
    </p>
</section>

<section class="detalje-boks">
    <h2>2. Kør import</h2>
    <?php if (!$filer): ?>
    <p>Ingen filer fundet i <code>data/import/</code> endnu.</p>
    <?php else: ?>
    <table class="data-tabel">
        <thead><tr><th>Fil</th><th></th><th></th></tr></thead>
        <tbody>
            <?php foreach ($filer as $sti): $navn = basename($sti); ?>
            <tr>
                <td><?= e($navn) ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="fil" value="<?= e($navn) ?>">
                        <input type="hidden" name="dry_run" value="1">
                        <button type="submit">Dry-run</button>
                    </form>
                </td>
                <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('Kør den endelige import af <?= e(addslashes($navn)) ?>? Dette skriver til databasen.');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="fil" value="<?= e($navn) ?>">
                        <button type="submit">Kør endelig import</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php if ($resultat): ?>
<section class="detalje-boks">
    <h2>Resultat: <?= $dryRun ? 'Dry-run (intet er skrevet til databasen)' : 'Endelig import gennemført' ?></h2>
    <div class="noegletal">
        <div class="kort"><span class="tal"><?= (int)$resultat['nye'] ?></span><span class="label">Nye projekter</span></div>
        <div class="kort"><span class="tal"><?= (int)$resultat['opdaterede'] ?></span><span class="label">Opdaterede projekter</span></div>
        <div class="kort"><span class="tal"><?= (int)$resultat['oversprungne'] ?></span><span class="label">Oversprungne rækker</span></div>
        <div class="kort"><span class="tal"><?= count($resultat['advarsler']) ?></span><span class="label">Advarsler</span></div>
        <div class="kort"><span class="tal"><?= count($resultat['fejl']) ?></span><span class="label">Fejl</span></div>
    </div>

    <?php if ($resultat['fejl']): ?>
    <h3>Fejl</h3>
    <ul>
        <?php foreach ($resultat['fejl'] as $f): ?><li class="fejlbesked"><?= e($f) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($resultat['advarsler']): ?>
    <h3>Advarsler - kræver evt. manuel gennemgang</h3>
    <ul>
        <?php foreach ($resultat['advarsler'] as $a): ?><li><?= e($a) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
