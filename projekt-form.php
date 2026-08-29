<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_rolle([ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$projekt = [
    'navn' => '', 'lead' => '', 'adresse' => '', 'postnummer' => '', 'by_navn' => '',
    'stadie' => '', 'enterpriseform' => '', 'projektsum' => '',
    'byggestart_maaned' => '', 'byggestart_bekraeftet' => 0, 'byggeslut_maaned' => '',
    'aabenlukket' => 'Åben', 'salgsresultat' => 'Ikke afgjort', 'tabt_aarsag' => '', 'tabt_aarsag_note' => '',
    'antal_plan' => '', 'kaelder' => '', 'antal_boliger' => '', 'ekstern_link' => '', 'noter' => '',
];
$tilknyttedeAnsvarlige = []; // bruger_id => primaer(bool)
$tilknyttedeVirksomheder = [];
$tilknyttedeKontakter = [];

if ($id) {
    $fundet = hent_projekt_detaljer($pdo, $id);
    if (!$fundet) {
        http_response_code(404);
        die('Projekt ikke fundet.');
    }
    $projekt = $fundet;
    foreach ($fundet['ansvarlige'] as $a) {
        $tilknyttedeAnsvarlige[(int)$a['id']] = (bool)$a['primaer'];
    }
    $tilknyttedeVirksomheder = $fundet['virksomheder'];
    $tilknyttedeKontakter = $fundet['kontakter'];
}

$alleBrugere = hent_brugere_liste($pdo);
$alleVirksomheder = hent_virksomheder_liste($pdo);

require __DIR__ . '/inc/header.php';
?>
<h1><?= $id ? 'Rediger projekt' : 'Nyt projekt' ?></h1>
<form method="post" action="projekt-gem.php" id="projekt-formular">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

    <fieldset>
        <legend>Projektoplysninger</legend>
        <label>Projekt-/sagsnavn *
            <input type="text" name="navn" required value="<?= e($projekt['navn']) ?>">
        </label>
        <label>Lead/leadkilde
            <input type="text" name="lead" value="<?= e($projekt['lead']) ?>">
        </label>
        <label>Adresse
            <input type="text" name="adresse" value="<?= e($projekt['adresse']) ?>">
        </label>
        <label>Postnummer
            <input type="text" name="postnummer" maxlength="10" value="<?= e($projekt['postnummer']) ?>">
        </label>
        <label>By
            <input type="text" name="by_navn" value="<?= e($projekt['by_navn']) ?>">
        </label>
        <label>Stadie
            <input type="text" name="stadie" value="<?= e($projekt['stadie']) ?>">
        </label>
        <label>Enterpriseform
            <input type="text" name="enterpriseform" value="<?= e($projekt['enterpriseform']) ?>">
        </label>
        <label>Projektsum (kr.)
            <input type="number" step="0.01" min="0" name="projektsum" value="<?= e((string)$projekt['projektsum']) ?>">
        </label>
    </fieldset>

    <fieldset>
        <legend>Tidsplan</legend>
        <label>Byggestart
            <input type="month" name="byggestart_maaned" value="<?= e($projekt['byggestart_maaned']) ?>">
        </label>
        <label class="inline">
            <input type="checkbox" name="byggestart_bekraeftet" value="1" style="display:inline-block;width:auto;" <?= $projekt['byggestart_bekraeftet'] ? 'checked' : '' ?>>
            Byggestart bekræftet
        </label>
        <label>Byggeslut
            <input type="month" name="byggeslut_maaned" value="<?= e($projekt['byggeslut_maaned']) ?>">
        </label>
    </fieldset>

    <fieldset>
        <legend>Status og salg</legend>
        <label>Åben/lukket
            <select name="aabenlukket">
                <?php foreach (AABENLUKKET_LISTE as $s): ?>
                    <option value="<?= e($s) ?>" <?= $projekt['aabenlukket'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Salgsresultat
            <select name="salgsresultat" id="salgsresultat-vaelg">
                <?php foreach (SALGSRESULTAT_LISTE as $s): ?>
                    <option value="<?= e($s) ?>" <?= $projekt['salgsresultat'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div id="tabt-aarsag-felter" <?= $projekt['salgsresultat'] === 'Tabt' ? '' : 'hidden' ?>>
            <label>Tabt årsag
                <select name="tabt_aarsag">
                    <option value="">– vælg –</option>
                    <?php foreach (TABT_AARSAG_LISTE as $s): ?>
                        <option value="<?= e($s) ?>" <?= $projekt['tabt_aarsag'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Uddybning ved "Andet"
                <input type="text" name="tabt_aarsag_note" maxlength="500" value="<?= e($projekt['tabt_aarsag_note']) ?>">
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>Øvrige oplysninger</legend>
        <label>Antal plan
            <input type="number" step="1" min="0" name="antal_plan" value="<?= e((string)$projekt['antal_plan']) ?>">
        </label>
        <label>Kælder
            <select name="kaelder">
                <option value="">– ukendt –</option>
                <option value="Ja" <?= $projekt['kaelder'] === 'Ja' ? 'selected' : '' ?>>Ja</option>
                <option value="Nej" <?= $projekt['kaelder'] === 'Nej' ? 'selected' : '' ?>>Nej</option>
            </select>
        </label>
        <label>Antal boliger
            <input type="number" step="1" min="0" name="antal_boliger" value="<?= e((string)$projekt['antal_boliger']) ?>">
        </label>
        <label>Eksternt link
            <input type="url" name="ekstern_link" value="<?= e($projekt['ekstern_link']) ?>">
        </label>
        <label>Bemærkninger
            <textarea name="noter" rows="4"><?= e($projekt['noter']) ?></textarea>
        </label>
    </fieldset>

    <fieldset>
        <legend>BMS-ansvarlige</legend>
        <p class="mindre">Vælg de(n) interne, der er ansvarlige for projektet, og markér højst én som primær.</p>
        <table class="data-tabel data-tabel-kompakt">
            <thead><tr><th>Tilknyt</th><th>Navn</th><th>Primær</th></tr></thead>
            <tbody>
                <?php foreach ($alleBrugere as $b): ?>
                <tr>
                    <td><input type="checkbox" name="ansvarlig_id[]" value="<?= (int)$b['id'] ?>" <?= isset($tilknyttedeAnsvarlige[$b['id']]) ? 'checked' : '' ?>></td>
                    <td><?= e($b['navn']) ?> (<?= e($b['initialer']) ?>)</td>
                    <td><input type="radio" name="primaer_ansvarlig_id" value="<?= (int)$b['id'] ?>" <?= (($tilknyttedeAnsvarlige[$b['id']] ?? false) === true) ? 'checked' : '' ?>></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </fieldset>

    <fieldset>
        <legend>Virksomheder</legend>
        <table id="virksomhed-tabel">
            <thead><tr><th>Virksomhed</th><th>Rolle</th><th>Fagområde</th><th>Aftalt sum (kr.)</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($tilknyttedeVirksomheder as $i => $tv): ?>
                <tr>
                    <td>
                        <?= e($tv['navn']) ?>
                        <input type="hidden" name="virksomhed_id[]" value="<?= (int)$tv['virksomhed_id'] ?>">
                    </td>
                    <td>
                        <select name="virksomhed_rolle[]">
                            <?php foreach (VIRKSOMHED_ROLLE_LISTE as $r): ?>
                                <option value="<?= e($r) ?>" <?= $tv['rolle'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="virksomhed_fag[]" value="<?= e($tv['fagomraade']) ?>"></td>
                    <td><input type="number" step="0.01" name="virksomhed_sum[]" value="<?= e((string)$tv['aftalt_sum']) ?>"></td>
                    <td><button type="button" class="fjern-raekke link-knap">Fjern</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <label>Tilføj virksomhed
            <select id="virksomhed-vaelg">
                <option value="">– vælg –</option>
                <?php foreach ($alleVirksomheder as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" data-navn="<?= e($v['navn']) ?>"><?= e($v['navn']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="tilfoej-virksomhed">Tilføj</button>
        </label>
        <p class="mindre">Mangler virksomheden på listen? <a href="virksomhed-form.php" target="_blank" rel="noopener">Opret den her</a> og genindlæs siden.</p>
    </fieldset>

    <fieldset>
        <legend>Kontaktpersoner</legend>
        <table id="kontakt-tabel">
            <thead><tr><th>Navn</th><th>Stilling</th><th>Telefon</th><th>E-mail</th><th>Primær</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($tilknyttedeKontakter as $i => $k): ?>
                <tr>
                    <td><input type="text" name="kontakt_navn[]" value="<?= e($k['navn']) ?>"></td>
                    <td><input type="text" name="kontakt_stilling[]" value="<?= e($k['stilling']) ?>"></td>
                    <td><input type="text" name="kontakt_telefon[]" value="<?= e($k['telefon']) ?>"></td>
                    <td><input type="email" name="kontakt_email[]" value="<?= e($k['email']) ?>"></td>
                    <td><input type="radio" name="primaer_kontakt_index" value="<?= (int)$i ?>" <?= $k['primaer'] ? 'checked' : '' ?>></td>
                    <td><button type="button" class="fjern-raekke link-knap">Fjern</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" id="tilfoej-kontakt">+ Tilføj kontaktperson</button>
    </fieldset>

    <button type="submit">Gem</button>
    <a href="<?= $id ? 'projekt-detalje.php?id=' . (int)$id : 'index.php' ?>">Annullér</a>
</form>
<script src="assets/app.js"></script>
<?php require __DIR__ . '/inc/footer.php'; ?>
