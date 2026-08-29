<?php
declare(strict_types=1);

const AABENLUKKET_LISTE = ['Åben', 'Lukket', 'Annulleret'];
const SALGSRESULTAT_LISTE = ['Ikke afgjort', 'Forhandling/dialog', 'Vundet', 'Tabt'];
const TABT_AARSAG_LISTE = ['Pris', 'Relationer', 'Andet'];
const VIRKSOMHED_ROLLE_LISTE = ['Kunde', 'Hovedentreprenør', 'Underentreprenør', 'Rådgiver', 'Leverandør', 'Andet'];
const DANSKE_MAANEDER = [
    1 => 'Januar', 2 => 'Februar', 3 => 'Marts', 4 => 'April', 5 => 'Maj', 6 => 'Juni',
    7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December',
];

function e(?string $vaerdi): string
{
    return htmlspecialchars($vaerdi ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formatterer et beløb (i grundvaluta, kr.) med dansk talformat.
 */
function formatKr($beloeb): string
{
    if ($beloeb === null || $beloeb === '') {
        return '–';
    }
    return number_format((float)$beloeb, 0, ',', '.') . ' kr.';
}

/**
 * Formatterer et beløb som mio. kr. når det er stort nok til at være mere
 * læsevenligt sådan, ellers som almindelige kr.
 */
function formatKrMio($beloeb): string
{
    if ($beloeb === null || $beloeb === '') {
        return '–';
    }
    $beloeb = (float)$beloeb;
    if (abs($beloeb) >= 1_000_000) {
        return number_format($beloeb / 1_000_000, 1, ',', '.') . ' mio. kr.';
    }
    return formatKr($beloeb);
}

function formatDato(?string $dato): string
{
    if (!$dato) {
        return '–';
    }
    return date('d-m-Y', strtotime($dato));
}

function formatDatoTid(?string $datoTid): string
{
    if (!$datoTid) {
        return '–';
    }
    return date('d-m-Y H:i', strtotime($datoTid));
}

/**
 * Formatterer en "YYYY-MM"-værdi som fx "Marts 2026". Viser rå værdi hvis
 * den ikke kan tolkes (fx en tilbageværende "?"), og "–" hvis tom.
 */
function formatMaaned(?string $yyyyMm): string
{
    if (!$yyyyMm) {
        return '–';
    }
    if (!preg_match('/^(\d{4})-(\d{2})$/', $yyyyMm, $m)) {
        return e($yyyyMm);
    }
    $maaned = (int)$m[2];
    if (!isset(DANSKE_MAANEDER[$maaned])) {
        return e($yyyyMm);
    }
    return DANSKE_MAANEDER[$maaned] . ' ' . $m[1];
}

/**
 * Validerer og normaliserer en byggestart/byggeslut-værdi til "YYYY-MM".
 * Accepterer "YYYY-M" (fx "2027-9") og normaliserer til "YYYY-MM".
 * Returnerer null for tom værdi. Kaster InvalidArgumentException hvis
 * værdien ikke er tom, men heller ikke kan tolkes sikkert som en måned.
 */
function normaliser_maaned(?string $raa): ?string
{
    $raa = trim((string)$raa);
    if ($raa === '') {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{1,2})$/', $raa, $m)) {
        throw new InvalidArgumentException("Kan ikke tolkes som en måned (forventet YYYY-MM): \"$raa\"");
    }
    $aar = (int)$m[1];
    $maaned = (int)$m[2];
    if ($maaned < 1 || $maaned > 12 || $aar < 1900 || $aar > 2200) {
        throw new InvalidArgumentException("Kan ikke tolkes som en måned (forventet YYYY-MM): \"$raa\"");
    }
    return sprintf('%04d-%02d', $aar, $maaned);
}

function aabenlukket_klasse(string $status): string
{
    return match ($status) {
        'Åben' => 'aaben',
        'Lukket' => 'lukket',
        'Annulleret' => 'annulleret',
        default => 'ukendt',
    };
}

function salgsresultat_klasse(string $resultat): string
{
    return match ($resultat) {
        'Vundet' => 'vundet',
        'Tabt' => 'tabt',
        'Forhandling/dialog' => 'dialog',
        default => 'ukendt',
    };
}

/**
 * Bygger et link til den aktuelle side med udvalgte query-parametre
 * overskrevet/tilføjet, og alle øvrige (fx filtre) bevaret.
 */
function byg_link(array $overskriv, ?string $basisSti = null): string
{
    $params = array_merge($_GET, $overskriv);
    foreach ($params as $noegle => $vaerdi) {
        if ($vaerdi === null || $vaerdi === '') {
            unset($params[$noegle]);
        }
    }
    $sti = $basisSti ?? basename($_SERVER['SCRIPT_NAME']);
    $qs = http_build_query($params);
    return $sti . ($qs !== '' ? '?' . $qs : '');
}

function sorteringsLink(string $kolonne, string $label): string
{
    $aktivKolonne = ($_GET['sort'] ?? 'navn') === $kolonne;
    $nuvaerendeRetning = $_GET['retning'] ?? 'asc';
    $nyRetning = ($aktivKolonne && $nuvaerendeRetning === 'asc') ? 'desc' : 'asc';
    $pil = $aktivKolonne ? ($nuvaerendeRetning === 'asc' ? ' ▲' : ' ▼') : '';
    $link = byg_link(['sort' => $kolonne, 'retning' => $nyRetning, 'side' => null]);
    return '<a href="' . e($link) . '">' . e($label) . $pil . '</a>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Ugyldig eller udløbet formular. Gå tilbage og prøv igen.');
    }
}
