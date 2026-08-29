<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_login();

$id = (int)($_GET['id'] ?? 0);
$projekt = $id ? hent_projekt_detaljer($pdo, $id) : null;
if (!$projekt) {
    http_response_code(404);
    require __DIR__ . '/inc/header.php';
    echo '<p>Projekt ikke fundet.</p><p><a href="index.php">Tilbage til projektoversigten</a></p>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_tekst'])) {
    kraev_rolle([ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR]);
    csrf_check();
    tilfoej_projekt_note($pdo, $id, (int)$bruger['id'], $_POST['note_tekst']);
    header('Location: projekt-detalje.php?id=' . $id);
    exit;
}

require __DIR__ . '/inc/header.php';

$oprettetAf = $projekt['oprettet_af'] ? hent_bruger_ved_id($pdo, (int)$projekt['oprettet_af']) : null;
$aendretAf = $projekt['aendret_af'] ? hent_bruger_ved_id($pdo, (int)$projekt['aendret_af']) : null;
?>
<div class="side-header">
    <div>
        <p class="brodkrumme"><a href="index.php">&laquo; Projektoversigt</a></p>
        <h1><?= e($projekt['navn']) ?></h1>
    </div>
    <?php if (kan_redigere($bruger)): ?><a class="knap" href="projekt-form.php?id=<?= $id ?>">Rediger projekt</a><?php endif; ?>
</div>

<div class="badge-raekke">
    <span class="badge badge-<?= aabenlukket_klasse($projekt['aabenlukket']) ?>"><?= e($projekt['aabenlukket']) ?></span>
    <span class="badge badge-<?= salgsresultat_klasse($projekt['salgsresultat']) ?>"><?= e($projekt['salgsresultat']) ?></span>
    <?php if ($projekt['byggestart_bekraeftet']): ?><span class="badge badge-bekraeftet">Byggestart bekræftet</span><?php endif; ?>
</div>

<div class="detalje-grid">
    <section class="detalje-boks">
        <h2>Projektoplysninger</h2>
        <dl class="dl-tabel">
            <dt>Lead/leadkilde</dt><dd><?= e($projekt['lead']) ?: '–' ?></dd>
            <dt>Adresse</dt><dd><?= e(trim(($projekt['adresse'] ?? '') . ' ' . ($projekt['postnummer'] ?? '') . ' ' . ($projekt['by_navn'] ?? ''))) ?: '–' ?></dd>
            <dt>Stadie</dt><dd><?= e($projekt['stadie']) ?: '–' ?></dd>
            <dt>Enterpriseform</dt><dd><?= e($projekt['enterpriseform']) ?: '–' ?></dd>
            <dt>Projektsum</dt><dd><?= e(formatKrMio($projekt['projektsum'])) ?></dd>
            <dt>Byggestart</dt><dd><?= e(formatMaaned($projekt['byggestart_maaned'])) ?><?= $projekt['byggestart_bekraeftet'] ? ' (bekræftet)' : '' ?></dd>
            <dt>Byggeslut</dt><dd><?= e(formatMaaned($projekt['byggeslut_maaned'])) ?></dd>
            <dt>Antal plan</dt><dd><?= e((string)$projekt['antal_plan']) ?: '–' ?></dd>
            <dt>Kælder</dt><dd><?= e($projekt['kaelder']) ?: '–' ?></dd>
            <dt>Antal boliger</dt><dd><?= e((string)$projekt['antal_boliger']) ?: '–' ?></dd>
            <?php if ($projekt['salgsresultat'] === 'Tabt'): ?>
            <dt>Tabt årsag</dt><dd><?= e($projekt['tabt_aarsag']) ?><?= $projekt['tabt_aarsag_note'] ? ' – ' . e($projekt['tabt_aarsag_note']) : '' ?></dd>
            <?php endif; ?>
            <?php if ($projekt['ekstern_link']): ?>
            <dt>Eksternt link</dt><dd><a href="<?= e($projekt['ekstern_link']) ?>" target="_blank" rel="noopener"><?= e($projekt['ekstern_link']) ?></a></dd>
            <?php endif; ?>
        </dl>
        <?php if ($projekt['noter']): ?>
        <h3>Bemærkninger</h3>
        <p class="forudformateret"><?= nl2br(e($projekt['noter'])) ?></p>
        <?php endif; ?>
        <?php if ($projekt['legacy_noter']): ?>
        <h3>Legacy-noter (fra import)</h3>
        <p class="forudformateret"><?= nl2br(e($projekt['legacy_noter'])) ?></p>
        <?php endif; ?>
    </section>

    <section class="detalje-boks">
        <h2>BMS-ansvarlige</h2>
        <?php if ($projekt['ansvarlige']): ?>
        <ul class="ren-liste">
            <?php foreach ($projekt['ansvarlige'] as $a): ?>
            <li><?= e($a['navn']) ?> (<?= e($a['initialer']) ?>)<?= $a['primaer'] ? ' <span class="badge badge-primaer">Primær</span>' : '' ?></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="ikke-tildelt">Ingen BMS-ansvarlig tildelt.</p>
        <?php endif; ?>

        <h2>Virksomheder</h2>
        <?php if ($projekt['virksomheder']): ?>
        <ul class="ren-liste">
            <?php foreach ($projekt['virksomheder'] as $v): ?>
            <li>
                <strong><?= e($v['navn']) ?></strong> – <?= e($v['rolle']) ?>
                <?php if ($v['fagomraade']): ?>(<?= e($v['fagomraade']) ?>)<?php endif; ?>
                <?php if ($v['aftalt_sum']): ?><br><span class="mindre">Aftalt sum: <?= e(formatKr($v['aftalt_sum'])) ?></span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>Ingen virksomheder tilknyttet.</p>
        <?php endif; ?>

        <h2>Kontaktpersoner</h2>
        <?php if ($projekt['kontakter']): ?>
        <ul class="ren-liste">
            <?php foreach ($projekt['kontakter'] as $k): ?>
            <li>
                <strong><?= e($k['navn']) ?></strong><?= $k['primaer'] ? ' <span class="badge badge-primaer">Primær</span>' : '' ?>
                <?php if ($k['stilling']): ?><br><span class="mindre"><?= e($k['stilling']) ?></span><?php endif; ?>
                <?php if ($k['telefon']): ?><br><span class="mindre">Tlf: <?= e($k['telefon']) ?></span><?php endif; ?>
                <?php if ($k['email']): ?><br><span class="mindre"><?= e($k['email']) ?></span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>Ingen kontaktpersoner tilknyttet.</p>
        <?php endif; ?>
    </section>
</div>

<section class="detalje-boks">
    <h2>Noter</h2>
    <?php if (kan_redigere($bruger)): ?>
    <form method="post" class="note-formular">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <textarea name="note_tekst" rows="2" placeholder="Skriv en note ..." required></textarea>
        <button type="submit">Tilføj note</button>
    </form>
    <?php endif; ?>
    <?php if ($projekt['projekt_noter']): ?>
    <ul class="note-liste">
        <?php foreach ($projekt['projekt_noter'] as $n): ?>
        <li>
            <p><?= nl2br(e($n['tekst'])) ?></p>
            <span class="mindre"><?= e($n['bruger_navn'] ?? 'Ukendt') ?> – <?= e(formatDatoTid($n['oprettet'])) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p>Ingen noter endnu.</p>
    <?php endif; ?>
</section>

<section class="detalje-boks">
    <h2>Historik</h2>
    <p class="mindre">
        Oprettet <?= e(formatDatoTid($projekt['oprettet'])) ?><?= $oprettetAf ? ' af ' . e($oprettetAf['navn']) : '' ?>.
        Senest ændret <?= e(formatDatoTid($projekt['opdateret'])) ?><?= $aendretAf ? ' af ' . e($aendretAf['navn']) : '' ?>.
    </p>
    <?php if ($projekt['historik']): ?>
    <table class="data-tabel">
        <thead><tr><th>Tidspunkt</th><th>Bruger</th><th>Felt</th><th>Fra</th><th>Til</th></tr></thead>
        <tbody>
            <?php foreach ($projekt['historik'] as $h): ?>
            <tr>
                <td><?= e(formatDatoTid($h['tidspunkt'])) ?></td>
                <td><?= e($h['bruger_navn'] ?? 'Ukendt') ?></td>
                <td><?= e($h['felt']) ?></td>
                <td><?= e((string)$h['gammel_vaerdi']) ?: '–' ?></td>
                <td><?= e((string)$h['ny_vaerdi']) ?: '–' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Ingen registrerede ændringer endnu.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
