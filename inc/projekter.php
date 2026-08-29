<?php
declare(strict_types=1);

// Domænehjælpere til projekter: stamdatalister samt gem/hent af de
// tilknyttede tabeller (ansvarlige, virksomheder, kontaktpersoner, noter,
// historik). Bruges af projekt-gem.php, projekt-form.php, projekt-detalje.php
// og importeren.

function hent_brugere_liste(PDO $pdo, bool $kunAktive = true): array
{
    $sql = 'SELECT * FROM brugere' . ($kunAktive ? ' WHERE aktiv = 1' : '') . ' ORDER BY navn';
    return $pdo->query($sql)->fetchAll();
}

function hent_bruger_ved_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM brugere WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function hent_bruger_ved_initialer(PDO $pdo, string $initialer): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM brugere WHERE initialer = ?');
    $stmt->execute([trim($initialer)]);
    return $stmt->fetch() ?: null;
}

function hent_virksomheder_liste(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM virksomheder ORDER BY navn')->fetchAll();
}

function hent_virksomhed_ved_navn(PDO $pdo, string $navn): ?array
{
    $navn = trim($navn);
    if ($navn === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM virksomheder WHERE navn = ?');
    $stmt->execute([$navn]);
    return $stmt->fetch() ?: null;
}

/**
 * Finder en virksomhed på (trimmet, case-insensitivt) navn, eller opretter
 * den. Bruges både af formularer og af Excel-importen for at undgå
 * dubletter, der kun varierer i store/små bogstaver eller mellemrum.
 */
function find_eller_opret_virksomhed(PDO $pdo, string $navn): array
{
    $navn = trim($navn);
    $fundet = hent_virksomhed_ved_navn($pdo, $navn);
    if ($fundet) {
        return $fundet;
    }
    $stmt = $pdo->prepare('INSERT INTO virksomheder (navn) VALUES (?)');
    $stmt->execute([$navn]);
    return hent_virksomhed_ved_navn($pdo, $navn);
}

/**
 * Erstatter de ansvarlige på et projekt. $brugerIds er alle tilknyttede,
 * $primaerBrugerId (hvis sat og med i listen) er den primære. Er der
 * præcis én ansvarlig og ingen primær angivet, bliver den automatisk
 * primær. Håndhæver dermed "højst én primær".
 */
function synkroniser_ansvarlige(PDO $pdo, int $projektId, array $brugerIds, ?int $primaerBrugerId): void
{
    $brugerIds = array_values(array_unique(array_map('intval', array_filter($brugerIds, fn ($v) => (int)$v > 0))));

    if ($primaerBrugerId !== null && !in_array($primaerBrugerId, $brugerIds, true)) {
        $primaerBrugerId = null;
    }
    if ($primaerBrugerId === null && count($brugerIds) === 1) {
        $primaerBrugerId = $brugerIds[0];
    }

    $pdo->prepare('DELETE FROM projekt_ansvarlige WHERE projekt_id = ?')->execute([$projektId]);
    if (!$brugerIds) {
        return;
    }
    $indsaet = $pdo->prepare(
        'INSERT INTO projekt_ansvarlige (projekt_id, bruger_id, primaer) VALUES (?, ?, ?)'
    );
    foreach ($brugerIds as $brugerId) {
        $indsaet->execute([$projektId, $brugerId, $brugerId === $primaerBrugerId ? 1 : 0]);
    }
}

/**
 * Erstatter virksomhedstilknytningerne på et projekt.
 * $tilknytninger er en liste af ['virksomhed_id'=>int, 'rolle'=>string,
 * 'fagomraade'=>?string, 'aftalt_sum'=>?float].
 */
function synkroniser_virksomhedstilknytninger(PDO $pdo, int $projektId, array $tilknytninger): void
{
    $pdo->prepare('DELETE FROM projekt_virksomheder WHERE projekt_id = ?')->execute([$projektId]);
    if (!$tilknytninger) {
        return;
    }
    $indsaet = $pdo->prepare(
        'INSERT INTO projekt_virksomheder (projekt_id, virksomhed_id, rolle, fagomraade, aftalt_sum)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE fagomraade = VALUES(fagomraade), aftalt_sum = VALUES(aftalt_sum)'
    );
    foreach ($tilknytninger as $t) {
        $virksomhedId = (int)($t['virksomhed_id'] ?? 0);
        $rolle = $t['rolle'] ?? '';
        if ($virksomhedId <= 0 || !in_array($rolle, VIRKSOMHED_ROLLE_LISTE, true)) {
            continue;
        }
        $indsaet->execute([
            $projektId,
            $virksomhedId,
            $rolle,
            ($t['fagomraade'] ?? '') !== '' ? $t['fagomraade'] : null,
            ($t['aftalt_sum'] ?? '') !== '' ? (float)$t['aftalt_sum'] : null,
        ]);
    }
}

/**
 * Erstatter kontaktpersonerne på et projekt. Håndhæver højst én primær
 * kontakt (den første markerede vinder).
 */
function synkroniser_kontaktpersoner(PDO $pdo, int $projektId, array $kontakter): void
{
    $pdo->prepare('DELETE FROM kontaktpersoner WHERE projekt_id = ?')->execute([$projektId]);
    if (!$kontakter) {
        return;
    }
    $indsaet = $pdo->prepare(
        'INSERT INTO kontaktpersoner (projekt_id, virksomhed_id, navn, stilling, telefon, email, note, primaer)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $primaerSat = false;
    foreach ($kontakter as $k) {
        $navn = trim($k['navn'] ?? '');
        if ($navn === '') {
            continue;
        }
        $erPrimaer = !empty($k['primaer']) && !$primaerSat;
        if ($erPrimaer) {
            $primaerSat = true;
        }
        $indsaet->execute([
            $projektId,
            !empty($k['virksomhed_id']) ? (int)$k['virksomhed_id'] : null,
            $navn,
            ($k['stilling'] ?? '') !== '' ? $k['stilling'] : null,
            ($k['telefon'] ?? '') !== '' ? $k['telefon'] : null,
            ($k['email'] ?? '') !== '' ? $k['email'] : null,
            ($k['note'] ?? '') !== '' ? $k['note'] : null,
            $erPrimaer ? 1 : 0,
        ]);
    }
}

function log_felt_aendring(PDO $pdo, int $projektId, ?int $brugerId, string $felt, ?string $gammel, ?string $ny): void
{
    if ($gammel === $ny) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO projekt_historik (projekt_id, bruger_id, felt, gammel_vaerdi, ny_vaerdi) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$projektId, $brugerId, $felt, $gammel, $ny]);
}

function tilfoej_projekt_note(PDO $pdo, int $projektId, ?int $brugerId, string $tekst): void
{
    $tekst = trim($tekst);
    if ($tekst === '') {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO projekt_noter (projekt_id, tekst, oprettet_af) VALUES (?, ?, ?)');
    $stmt->execute([$projektId, $tekst, $brugerId]);
}

/**
 * Henter et projekt inkl. alle tilknyttede data (ansvarlige, virksomheder,
 * kontaktpersoner, noter, historik) samlet til visning på detaljesiden.
 */
function hent_projekt_detaljer(PDO $pdo, int $projektId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM projekter WHERE id = ?');
    $stmt->execute([$projektId]);
    $projekt = $stmt->fetch();
    if (!$projekt) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT b.id, b.initialer, b.navn, pa.primaer
         FROM projekt_ansvarlige pa JOIN brugere b ON b.id = pa.bruger_id
         WHERE pa.projekt_id = ? ORDER BY pa.primaer DESC, b.navn'
    );
    $stmt->execute([$projektId]);
    $projekt['ansvarlige'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT pv.rolle, pv.fagomraade, pv.aftalt_sum, v.id AS virksomhed_id, v.navn
         FROM projekt_virksomheder pv JOIN virksomheder v ON v.id = pv.virksomhed_id
         WHERE pv.projekt_id = ? ORDER BY pv.rolle, v.navn'
    );
    $stmt->execute([$projektId]);
    $projekt['virksomheder'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT * FROM kontaktpersoner WHERE projekt_id = ? ORDER BY primaer DESC, navn'
    );
    $stmt->execute([$projektId]);
    $projekt['kontakter'] = $stmt->fetchAll();

    // Bemærk: gemmes som 'projekt_noter' (ikke 'noter'), da 'noter' allerede
    // er projektets eget fritekst-bemærkningsfelt fra projekter-tabellen.
    $stmt = $pdo->prepare(
        'SELECT n.*, b.navn AS bruger_navn FROM projekt_noter n
         LEFT JOIN brugere b ON b.id = n.oprettet_af
         WHERE n.projekt_id = ? ORDER BY n.oprettet DESC'
    );
    $stmt->execute([$projektId]);
    $projekt['projekt_noter'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT h.*, b.navn AS bruger_navn FROM projekt_historik h
         LEFT JOIN brugere b ON b.id = h.bruger_id
         WHERE h.projekt_id = ? ORDER BY h.tidspunkt DESC'
    );
    $stmt->execute([$projektId]);
    $projekt['historik'] = $stmt->fetchAll();

    return $projekt;
}

/**
 * Primær BMS-ansvarlig for et projekt, hvis nogen ("navn (initialer)").
 */
function primaer_ansvarlig_label(array $ansvarlige): ?string
{
    foreach ($ansvarlige as $a) {
        if ($a['primaer']) {
            return $a['navn'];
        }
    }
    return null;
}

function medansvarlige_labels(array $ansvarlige): array
{
    return array_values(array_map(
        fn ($a) => $a['navn'],
        array_filter($ansvarlige, fn ($a) => !$a['primaer'])
    ));
}

/**
 * Læser projektfiltrene fra $_GET til en normaliseret form. Bruges af både
 * projektoversigten og dashboardet, så de to sider filtrerer ens.
 */
function projekt_filter_fra_get(array $get): array
{
    return [
        'soeg' => trim($get['soeg'] ?? ''),
        'byggestart_fra' => trim($get['byggestart_fra'] ?? ''),
        'byggestart_til' => trim($get['byggestart_til'] ?? ''),
        'byggestart_status' => in_array($get['byggestart_status'] ?? '', ['bekraeftet', 'ikke_bekraeftet', 'ukendt'], true)
            ? $get['byggestart_status'] : '',
        'ansvarlig' => array_values(array_filter(array_map('intval', (array)($get['ansvarlig'] ?? [])))),
        'ansvarlig_tildeling' => in_array($get['ansvarlig_tildeling'] ?? '', ['tildelt', 'ikke_tildelt'], true)
            ? $get['ansvarlig_tildeling'] : '',
        'kun_primaer' => !empty($get['kun_primaer']),
        'aabenlukket' => in_array($get['aabenlukket'] ?? '', AABENLUKKET_LISTE, true) ? $get['aabenlukket'] : '',
        'salgsresultat' => in_array($get['salgsresultat'] ?? '', SALGSRESULTAT_LISTE, true) ? $get['salgsresultat'] : '',
        'lead' => trim($get['lead'] ?? ''),
        'by' => trim($get['by'] ?? ''),
    ];
}

/**
 * Bygger en WHERE-fragment (uden selve "WHERE") og tilhørende
 * parameterliste ud fra et normaliseret filter fra projekt_filter_fra_get().
 *
 * @return array{sql: string, params: list<mixed>}
 */
function byg_projekt_where(array $filter): array
{
    $betingelser = [];
    $params = [];

    if ($filter['soeg'] !== '') {
        $like = '%' . $filter['soeg'] . '%';
        $betingelser[] = '(p.navn LIKE ? OR p.adresse LIKE ? OR p.by_navn LIKE ? OR p.noter LIKE ?
            OR EXISTS (SELECT 1 FROM projekt_virksomheder pv_s JOIN virksomheder v_s ON v_s.id = pv_s.virksomhed_id
                       WHERE pv_s.projekt_id = p.id AND v_s.navn LIKE ?)
            OR EXISTS (SELECT 1 FROM kontaktpersoner k_s WHERE k_s.projekt_id = p.id AND k_s.navn LIKE ?))';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    if ($filter['byggestart_fra'] !== '') {
        $betingelser[] = 'p.byggestart_maaned IS NOT NULL AND p.byggestart_maaned >= ?';
        $params[] = $filter['byggestart_fra'];
    }
    if ($filter['byggestart_til'] !== '') {
        $betingelser[] = 'p.byggestart_maaned IS NOT NULL AND p.byggestart_maaned <= ?';
        $params[] = $filter['byggestart_til'];
    }
    if ($filter['byggestart_status'] === 'bekraeftet') {
        $betingelser[] = 'p.byggestart_bekraeftet = 1';
    } elseif ($filter['byggestart_status'] === 'ikke_bekraeftet') {
        $betingelser[] = 'p.byggestart_bekraeftet = 0 AND p.byggestart_maaned IS NOT NULL';
    } elseif ($filter['byggestart_status'] === 'ukendt') {
        $betingelser[] = 'p.byggestart_maaned IS NULL';
    }

    if ($filter['ansvarlig']) {
        $pladsholdere = implode(',', array_fill(0, count($filter['ansvarlig']), '?'));
        $primaerBetingelse = $filter['kun_primaer'] ? ' AND pa_f.primaer = 1' : '';
        $betingelser[] = "EXISTS (SELECT 1 FROM projekt_ansvarlige pa_f WHERE pa_f.projekt_id = p.id
            AND pa_f.bruger_id IN ($pladsholdere)$primaerBetingelse)";
        array_push($params, ...$filter['ansvarlig']);
    }
    if ($filter['ansvarlig_tildeling'] === 'tildelt') {
        $betingelser[] = 'EXISTS (SELECT 1 FROM projekt_ansvarlige pa_t WHERE pa_t.projekt_id = p.id)';
    } elseif ($filter['ansvarlig_tildeling'] === 'ikke_tildelt') {
        $betingelser[] = 'NOT EXISTS (SELECT 1 FROM projekt_ansvarlige pa_t WHERE pa_t.projekt_id = p.id)';
    }

    if ($filter['aabenlukket'] !== '') {
        $betingelser[] = 'p.aabenlukket = ?';
        $params[] = $filter['aabenlukket'];
    }
    if ($filter['salgsresultat'] !== '') {
        $betingelser[] = 'p.salgsresultat = ?';
        $params[] = $filter['salgsresultat'];
    }
    if ($filter['lead'] !== '') {
        $betingelser[] = 'p.lead = ?';
        $params[] = $filter['lead'];
    }
    if ($filter['by'] !== '') {
        $betingelser[] = 'p.by_navn = ?';
        $params[] = $filter['by'];
    }

    return [
        'sql' => $betingelser ? implode(' AND ', array_map(fn ($b) => "($b)", $betingelser)) : '1=1',
        'params' => $params,
    ];
}

/**
 * Henter alle KPI- og diagramtal til dashboardet for et givent (allerede
 * normaliseret) filter. Udtrukket til egen funktion så den kan genbruges af
 * dashboard.php og testes uden en HTTP-request.
 *
 * Vigtigt for "ingen dobbelttælling": ansvarlig-grupperingen joiner kun på
 * den primære ansvarlige (pa.primaer = 1), så et projekt med flere
 * ansvarlige kun bidrager til ét gruppe-navn og tælles derfor kun én gang i
 * både antal og projektsum.
 */
function hent_dashboard_data(PDO $pdo, array $filter): array
{
    $hvor = byg_projekt_where($filter);

    $stmt = $pdo->prepare(
        "SELECT aabenlukket, COUNT(*) AS antal, COALESCE(SUM(projektsum),0) AS sum
         FROM projekter p WHERE {$hvor['sql']} GROUP BY aabenlukket"
    );
    $stmt->execute($hvor['params']);
    $statusData = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT salgsresultat, COUNT(*) AS antal
         FROM projekter p WHERE {$hvor['sql']} GROUP BY salgsresultat"
    );
    $stmt->execute($hvor['params']);
    $salgsresultatData = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN EXISTS(SELECT 1 FROM projekt_ansvarlige pa WHERE pa.projekt_id = p.id) THEN 1 ELSE 0 END) AS tildelt,
            SUM(CASE WHEN NOT EXISTS(SELECT 1 FROM projekt_ansvarlige pa WHERE pa.projekt_id = p.id) THEN 1 ELSE 0 END) AS ikke_tildelt
         FROM projekter p WHERE {$hvor['sql']}"
    );
    $stmt->execute($hvor['params']);
    $tildelingData = $stmt->fetch() ?: ['tildelt' => 0, 'ikke_tildelt' => 0];

    // Grupperet på primær BMS-ansvarlig - undgår dobbelttælling af
    // projektsummen, da hvert projekt højst har én primær ansvarlig.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(b.navn, 'Ikke tildelt') AS navn, COUNT(*) AS antal, COALESCE(SUM(p.projektsum),0) AS sum
         FROM projekter p
         LEFT JOIN projekt_ansvarlige pa ON pa.projekt_id = p.id AND pa.primaer = 1
         LEFT JOIN brugere b ON b.id = pa.bruger_id
         WHERE {$hvor['sql']}
         GROUP BY b.id
         ORDER BY sum DESC"
    );
    $stmt->execute($hvor['params']);
    $ansvarligData = $stmt->fetchAll();

    $totalAntal = (int)array_sum(array_column($statusData, 'antal'));
    $totalSum = (float)array_sum(array_column($statusData, 'sum'));

    return [
        'status_pr_aabenlukket' => array_column($statusData, null, 'aabenlukket'),
        'antal_pr_salgsresultat' => array_column($salgsresultatData, 'antal', 'salgsresultat'),
        'antal_tildelt' => (int)($tildelingData['tildelt'] ?? 0),
        'antal_ikke_tildelt' => (int)($tildelingData['ikke_tildelt'] ?? 0),
        'ansvarlig_data' => $ansvarligData,
        'total_antal' => $totalAntal,
        'total_sum' => $totalSum,
    ];
}
