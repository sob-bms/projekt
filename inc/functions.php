<?php
declare(strict_types=1);

const STATUS_LISTE = ['Tilbud', 'Igangværende', 'Afsluttet', 'Tabt'];

function e(?string $vaerdi): string
{
    return htmlspecialchars($vaerdi ?? '', ENT_QUOTES, 'UTF-8');
}

function formatKr($beloeb): string
{
    if ($beloeb === null || $beloeb === '') {
        return '–';
    }
    return number_format((float)$beloeb, 0, ',', '.') . ' kr.';
}

function formatDato(?string $dato): string
{
    if (!$dato) {
        return '–';
    }
    return date('d-m-Y', strtotime($dato));
}

function statusKlasse(string $status): string
{
    return match ($status) {
        'Tilbud' => 'tilbud',
        'Igangværende' => 'igang',
        'Afsluttet' => 'afsluttet',
        'Tabt' => 'tabt',
        default => 'ukendt',
    };
}

function sorteringsLink(string $kolonne, string $label): string
{
    $params = $_GET;
    $aktivKolonne = ($_GET['sort'] ?? 'navn') === $kolonne;
    $nuvaerendeRetning = $_GET['retning'] ?? 'asc';
    $params['sort'] = $kolonne;
    $params['retning'] = ($aktivKolonne && $nuvaerendeRetning === 'asc') ? 'desc' : 'asc';
    $pil = $aktivKolonne ? ($nuvaerendeRetning === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8') . '">' . e($label) . $pil . '</a>';
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
