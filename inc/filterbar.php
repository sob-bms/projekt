<?php
/**
 * Delt filterbar til projektoversigten og dashboardet, så de to sider altid
 * filtrerer ens (samme felter som byg_projekt_where() forstår).
 *
 * De mest brugte filtre (søgning, byggestart, BMS-ansvarlig) står altid
 * fremme; "Leadkilde", "By" og "Status" er 2. prioritet og ligger derfor
 * samlet i en sammenfoldet <details> for at spare plads, men foldes
 * automatisk ud hvis et af dem allerede er sat.
 *
 * Forventede variabler fra den inkluderende side:
 * @var array $filter        Normaliseret filter, se projekt_filter_fra_get()
 * @var array $brugerListe   Aktive BMS-ansvarlige, se hent_brugere_liste()
 * @var array $leadListe     Distinkte leadkilder
 * @var array $byListe       Distinkte byer
 * @var array $harFiltre     Ikke-tomme filterfelter, se projekt_filter_ikke_tomme()
 * @var string $filterSide   'projekter' eller 'dashboard'
 * @var bool $harGemtFilter  Har brugeren allerede gemt et filter for denne side
 */

$filterAktionsside = $filterSide === 'dashboard' ? 'dashboard.php' : 'index.php';
$flereFiltreAktive = $filter['aabenlukket'] !== '' || $filter['salgsresultat'] !== ''
    || $filter['lead'] !== '' || $filter['by'] !== '';
?>
<form method="get" class="filterbar filterbar-kompakt">
    <input type="text" name="soeg" placeholder="Søg på navn, adresse, by, virksomhed, kontakt, notat" value="<?= e($filter['soeg']) ?>" class="soegefelt">

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

    <div class="filterbar-bund">
        <details class="filter-flere" <?= $flereFiltreAktive ? 'open' : '' ?>>
            <summary>Flere filtre (leadkilde, by, status)</summary>
            <div class="filter-flere-indhold">
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
            </div>
        </details>

        <div class="filterbar-knapper">
            <button type="submit">Filtrér</button>
            <?php if ($harFiltre): ?><a href="<?= e($filterAktionsside) ?>?nulstil=1" class="nulstil-knap">Nulstil alle filtre</a><?php endif; ?>
        </div>
    </div>
</form>

<div class="gem-filter-raekke">
    <form method="post" action="filter-gem.php" class="gem-filter-form">
        <input type="hidden" name="side" value="<?= e($filterSide) ?>">
        <input type="hidden" name="handling" value="gem">
        <?php foreach (filter_til_skjulte_felter($harFiltre) as [$navn, $vaerdi]): ?>
            <input type="hidden" name="<?= e($navn) ?>" value="<?= e($vaerdi) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="link-knap">Gem disse filtre</button>
    </form>
    <?php if ($harGemtFilter): ?>
    <form method="post" action="filter-gem.php" class="glem-filter-form">
        <input type="hidden" name="side" value="<?= e($filterSide) ?>">
        <input type="hidden" name="handling" value="slet">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <span class="mindre">Der er gemt et filter for denne side - det anvendes automatisk næste gang.</span>
        <button type="submit" class="link-knap">Glem gemt filter</button>
    </form>
    <?php endif; ?>
</div>
