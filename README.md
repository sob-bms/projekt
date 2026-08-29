# BMS Projekter

PHP/MySQL-værktøj til at administrere byggeprojekter: underentreprenører,
projektsum, kontaktpersoner og salgsansvarlig – med søgning/filtrering,
sortering og et dashboard med søjlediagrammer.

## Krav

- Ubuntu-server med Apache (eller Nginx) og `mod_php`/PHP-FPM
- PHP 8.1+ med `pdo_mysql`-udvidelsen
- MySQL eller MariaDB

## Installation

1. Klon repoet på serveren, fx som undermappe til jeres eksisterende site:

   ```
   cd ~/webroots/www/vaerktoejer   # eller jeres dokumentrod
   git clone https://github.com/sob-bms/projekt.git projekt
   ```

2. Opret database og bruger:

   ```sql
   CREATE DATABASE bms_projekt CHARACTER SET utf8mb4 COLLATE utf8mb4_danish_ci;
   CREATE USER 'bms_projekt'@'localhost' IDENTIFIED BY 'et-godt-password';
   GRANT ALL PRIVILEGES ON bms_projekt.* TO 'bms_projekt'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Indlæs skemaet:

   ```
   mysql -u bms_projekt -p bms_projekt < db/schema.sql
   ```

4. Opret konfigurationsfilen:

   ```
   cp inc/config.eksempel.php inc/config.php
   ```

   og udfyld `db_host`, `db_name`, `db_user`, `db_pass`.

5. Peg jeres vhost (eller en Alias, hvis det skal ligge under `/projekt` på
   det eksisterende site) på mappen. `inc/config.php` og `db/` er allerede
   beskyttet af `.htaccess`.

## Vigtigt: filer der aldrig må i git

`inc/config.php` indeholder databaseadgangskoden og ligger bevidst uden for
git (`.gitignore`). Skabelonen `inc/config.eksempel.php` viser hvilke
nøgler der skal udfyldes.

## Struktur

- `index.php` – projektliste med søgning, filtre og sortering
- `projekt-form.php` / `projekt-gem.php` / `projekt-slet.php` – opret,
  redigér og slet projekter (inkl. tilknytning af underentreprenører og
  aftalt sum pr. underentreprenør)
- `underentreprenorer.php` m.fl. – stamdata for underentreprenører
- `dashboard.php` – nøgletal og søjlediagrammer (antal/sum pr. status og
  pr. salgsansvarlig)
- `db/schema.sql` – databaseskema
- `inc/` – konfiguration, databaseforbindelse, hjælpefunktioner og fælles
  header/footer

## Dashboard og internetadgang

Diagrammerne bruger [Chart.js](https://www.chartjs.org/) hentet fra et CDN
i `dashboard.php`. Blokerer jeres intranet udgående adgang til CDN'er, så
download `chart.umd.min.js` fra Chart.js' GitHub-releases, læg den i
`assets/`, og peg `<script src="...">` i `dashboard.php` derhen i stedet.

## Adgangsbegrænsning

Der er ingen login i denne første version – adgang styres af, hvem der kan
nå serveren på intranettet. Skal det begrænses yderligere, er den nemmeste
vej en HTTP Basic Auth via Apache (`AuthType Basic` i vhost eller
`.htaccess`), eller at binde vhosten til jeres interne IP-range.

## Videre arbejde

- Login/roller, hvis flere end salgsafdelingen skal have adgang med
  forskellige rettigheder
- Historik/log over ændringer på et projekt
- Eksport til Excel/CSV
