#!/bin/sh
# ---------------------------------------------------------------
# Henter seneste version fra GitHub og lægger den på plads.
#
# Køres på webhotellet over SSH:
#     cd ~/webroots/www/projekt && sh opdater.sh
#
# Kan også lægges i et cron-job, hvis siden skal opdatere sig selv.
#
# .env og data/import/*.xlsx røres ikke - de står i .gitignore og hører
# til serveren, ikke til repositoriet.
#
# .htaccess har typisk en lokal tilføjelse på serveren (HTTP Basic Auth via
# .htpasswd), som bevidst ikke er committet til git. Filen er markeret med
# git "skip-worktree" (sættes automatisk nedenfor), så git pull aldrig
# rører den. Ændres de FÆLLES regler i .htaccess i selve repoet (fx nye
# adgangsbegrænsninger), skal Basic Auth-linjerne lægges ind igen manuelt
# bagefter - spørg Claude, der kan guide dig igennem det, som sidst.
# ---------------------------------------------------------------
set -e

MAPPE=$(cd "$(dirname "$0")" && pwd)
cd "$MAPPE"

echo "Opdaterer i $MAPPE"

if [ ! -d .git ]; then
    echo "FEJL: her er ikke et git-arbejdsbibliotek. Er stien rigtig?" >&2
    exit 1
fi

if [ -f .htaccess ]; then
    git update-index --skip-worktree .htaccess 2>/dev/null || true
fi

# Advar hvis nogen har rettet direkte i andre sporede filer på serveren,
# så ændringerne ikke forsvinder ubemærket.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "ADVARSEL: der er lokale ændringer i sporede filer på serveren:" >&2
    git status --short --untracked-files=no >&2
    echo "Gem dem eller fortryd dem med 'git checkout -- <fil>' før opdatering." >&2
    exit 1
fi

git pull --ff-only

# data/import skal kunne skrives, ellers kan Excel-importens upload ikke gemme filer.
chmod 755 data data/import 2>/dev/null || true

if [ -f bin/migrer.php ]; then
    echo "Kører evt. nye databasemigrationer ..."
    php bin/migrer.php
fi

echo "Færdig. Nuværende version:"
git --no-pager log -1 --format='  %h  %ad  %s' --date=short
