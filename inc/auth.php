<?php

declare(strict_types=1);

const ROLLE_LAESER = 'laeser';
const ROLLE_REDAKTOER = 'redaktoer';
const ROLLE_ADMINISTRATOR = 'administrator';
const ALLE_ROLLER = [ROLLE_LAESER, ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR];

/**
 * Logger en allerede valideret bruger ind (sætter kun sessionen).
 */
function log_ind(array $bruger): void
{
    session_regenerate_id(true);
    $_SESSION['bruger_id'] = (int)$bruger['id'];
}

function log_ud(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Den aktuelt indloggede bruger, eller null hvis ingen/inaktiv bruger.
 */
function aktuel_bruger(): ?array
{
    static $bruger = null;
    static $slaaet_op = false;
    if ($slaaet_op) {
        return $bruger;
    }
    $slaaet_op = true;

    $id = $_SESSION['bruger_id'] ?? null;
    if (!$id) {
        return null;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM brugere WHERE id = ? AND aktiv = 1');
    $stmt->execute([$id]);
    $fundet = $stmt->fetch();
    $bruger = $fundet ?: null;
    return $bruger;
}

/**
 * Kræver at der er logget ind. Sender uindloggede videre til login.php.
 * Returnerer den indloggede brugers data.
 */
function kraev_login(): array
{
    $bruger = aktuel_bruger();
    if (!$bruger) {
        $naeste = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php?naeste=' . urlencode($naeste));
        exit;
    }
    return $bruger;
}

/**
 * Kræver login og at brugerens rolle er blandt de tilladte.
 *
 * @param list<string> $tilladteRoller
 */
function kraev_rolle(array $tilladteRoller): array
{
    $bruger = kraev_login();
    if (!in_array($bruger['rolle'], $tilladteRoller, true)) {
        http_response_code(403);
        require __DIR__ . '/header.php';
        echo '<p>Du har ikke rettigheder til at se denne side.</p>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $bruger;
}

function er_administrator(?array $bruger): bool
{
    return $bruger !== null && $bruger['rolle'] === ROLLE_ADMINISTRATOR;
}

function kan_redigere(?array $bruger): bool
{
    return $bruger !== null && in_array($bruger['rolle'], [ROLLE_REDAKTOER, ROLLE_ADMINISTRATOR], true);
}

function rolle_label(string $rolle): string
{
    return match ($rolle) {
        ROLLE_LAESER => 'Læser',
        ROLLE_REDAKTOER => 'Redaktør',
        ROLLE_ADMINISTRATOR => 'Administrator',
        default => $rolle,
    };
}
