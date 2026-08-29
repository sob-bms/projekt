<?php
require __DIR__ . '/inc/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$projekt = [
    'navn' => '', 'adresse' => '', 'status' => 'Tilbud', 'projektsum' => '',
    'salgsansvarlig' => '', 'kontaktperson_navn' => '', 'kontaktperson_telefon' => '',
    'kontaktperson_email' => '', 'opstartsdato' => '', 'slutdato' => '', 'noter' => '',
];
$tilknyttedeUe = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM projekter WHERE id = ?');
    $stmt->execute([$id]);
    $fundet = $stmt->fetch();
    if (!$fundet) {
        http_response_code(404);
        die('Projekt ikke fundet.');
    }
    $projekt = $fundet;

    $stmt = $pdo->prepare('SELECT underentreprenor_id, aftalt_sum FROM projekt_underentreprenorer WHERE projekt_id = ?');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $row) {
        $tilknyttedeUe[$row['underentreprenor_id']] = $row['aftalt_sum'];
    }
}

$alleUe = $pdo->query('SELECT id, navn, fag FROM underentreprenorer ORDER BY navn')->fetchAll();

require __DIR__ . '/inc/header.php';
?>
<h1><?= $id ? 'Rediger projekt' : 'Nyt projekt' ?></h1>
<form method="post" action="projekt-gem.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

    <label>Navn *
        <input type="text" name="navn" required value="<?= e($projekt['navn']) ?>">
    </label>
    <label>Adresse
        <input type="text" name="adresse" value="<?= e($projekt['adresse']) ?>">
    </label>
    <label>Status
        <select name="status">
            <?php foreach (STATUS_LISTE as $s): ?>
                <option value="<?= e($s) ?>" <?= $projekt['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Projektsum (kr.)
        <input type="number" step="0.01" name="projektsum" value="<?= e((string)$projekt['projektsum']) ?>">
    </label>
    <label>Salgsansvarlig
        <input type="text" name="salgsansvarlig" value="<?= e($projekt['salgsansvarlig']) ?>">
    </label>
    <label>Kontaktperson
        <input type="text" name="kontaktperson_navn" value="<?= e($projekt['kontaktperson_navn']) ?>">
    </label>
    <label>Telefon
        <input type="text" name="kontaktperson_telefon" value="<?= e($projekt['kontaktperson_telefon']) ?>">
    </label>
    <label>E-mail
        <input type="email" name="kontaktperson_email" value="<?= e($projekt['kontaktperson_email']) ?>">
    </label>
    <label>Opstartsdato
        <input type="date" name="opstartsdato" value="<?= e($projekt['opstartsdato']) ?>">
    </label>
    <label>Slutdato
        <input type="date" name="slutdato" value="<?= e($projekt['slutdato']) ?>">
    </label>
    <label>Noter
        <textarea name="noter" rows="4"><?= e($projekt['noter']) ?></textarea>
    </label>

    <fieldset>
        <legend>Underentreprenører</legend>
        <table id="ue-tabel">
            <thead><tr><th>Underentreprenør</th><th>Aftalt sum (kr.)</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($tilknyttedeUe as $ueId => $sum):
                    $ue = null;
                    foreach ($alleUe as $kandidat) {
                        if ((int)$kandidat['id'] === (int)$ueId) {
                            $ue = $kandidat;
                            break;
                        }
                    }
                    if (!$ue) {
                        continue;
                    }
                ?>
                <tr>
                    <td>
                        <?= e($ue['navn']) ?>
                        <input type="hidden" name="ue_id[]" value="<?= (int)$ueId ?>">
                    </td>
                    <td><input type="number" step="0.01" name="ue_sum[]" value="<?= e((string)$sum) ?>"></td>
                    <td><button type="button" class="fjern-ue link-knap">Fjern</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <label>Tilføj underentreprenør
            <select id="ue-vaelg">
                <option value="">– vælg –</option>
                <?php foreach ($alleUe as $ue): ?>
                    <option value="<?= (int)$ue['id'] ?>" data-navn="<?= e($ue['navn']) ?>">
                        <?= e($ue['navn']) ?><?= $ue['fag'] ? ' (' . e($ue['fag']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="tilfoej-ue">Tilføj</button>
        </label>
    </fieldset>

    <button type="submit">Gem</button>
    <a href="index.php">Annullér</a>
</form>
<script src="assets/app.js"></script>
<?php require __DIR__ . '/inc/footer.php'; ?>
