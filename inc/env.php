<?php

declare(strict_types=1);

// Simpel .env-indlæser. Kræver ingen Composer-pakke: .env-filen er
// nøgle=værdi pr. linje og ligger bevidst uden for git (se .gitignore).
// Rigtige miljøvariabler (fx sat af Apache/systemd i produktion) har altid
// forrang og bliver ikke overskrevet.
if (!function_exists('indlaes_env')) {
    function indlaes_env(string $sti): void
    {
        if (!is_file($sti)) {
            return;
        }
        foreach (file($sti, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linje) {
            $linje = trim($linje);
            if ($linje === '' || str_starts_with($linje, '#')) {
                continue;
            }
            [$noegle, $vaerdi] = array_pad(explode('=', $linje, 2), 2, '');
            $noegle = trim($noegle);
            $vaerdi = trim(trim($vaerdi), "\"'");
            if ($noegle !== '' && getenv($noegle) === false) {
                putenv("$noegle=$vaerdi");
                $_ENV[$noegle] = $vaerdi;
            }
        }
    }

    function env(string $noegle, ?string $standard = null): ?string
    {
        $vaerdi = getenv($noegle);
        return $vaerdi !== false ? $vaerdi : $standard;
    }
}

indlaes_env(dirname(__DIR__) . '/.env');
