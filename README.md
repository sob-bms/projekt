# BMS Projekter

Internt, webbaseret CRM-værktøj til administration af byggeprojekter:
underentreprenører/virksomheder, projektsum, kontaktpersoner og
BMS-ansvarlige – med søgning/filtrering/sortering, en projektdetaljeside,
Excel-import af eksisterende data og et dashboard med søjlediagrammer.

PHP 8 + MySQL/MariaDB, ingen framework, PDO med forberedte forespørgsler og
CSRF-tokens på alle formularer.

## Krav

- Ubuntu-server (eller lignende) med Apache/Nginx og PHP-FPM/mod_php
- PHP 8.1+ med `pdo_mysql`, `zip` og `simplexml`-udvidelserne
- MySQL 8+ eller MariaDB 10.6+
- [Composer](https://getcomposer.org/) (kun til at installere PHPUnit til test – ikke et
  runtime-krav for selve applikationen)

## 1. Lokal opstart

```bash
git clone https://github.com/sob-bms/projekt.git
cd projekt
composer install            # installerer kun testværktøjet PHPUnit
cp .env.example .env         # udfyld DB_HOST/DB_NAME/DB_USER/DB_PASS
```

Opret database og bruger:

```sql
CREATE DATABASE bms_projekt CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'bms_projekt'@'localhost' IDENTIFIED BY 'et-godt-password';
GRANT ALL PRIVILEGES ON bms_projekt.* TO 'bms_projekt'@'localhost';
FLUSH PRIVILEGES;
```

Kør migrationerne (se afsnit 3) og opret en administrator (afsnit 4), og start
en lokal server til udvikling:

```bash
php -S localhost:8000
```

Besøg <http://localhost:8000/login.php>.

## 2. Miljøvariabler

Konfiguration sker via `.env` (kopieret fra `.env.example`), som ligger
bevidst uden for git (se `.gitignore`) og **aldrig må committes**:

| Variabel | Beskrivelse |
|---|---|
| `DB_HOST` | Databasehost, fx `localhost` |
| `DB_NAME` | Databasenavn, fx `bms_projekt` |
| `DB_USER` | Databasebruger |
| `DB_PASS` | Databaseadgangskode |
| `APP_ENV` | `udvikling` eller `produktion` (kun informativt pt.) |
| `SESSION_NAME` | Navn på PHP-sessionscookien (kan ændres hvis flere PHP-sites deler domæne) |

I produktion kan de samme nøgler i stedet sættes som rigtige
miljøvariabler (fx i Apache-vhosten med `SetEnv` eller i en systemd-unit) –
`.env`-filen er blot en bekvem lokal fallback (`inc/env.php` sætter kun en
variabel, hvis den ikke allerede findes i miljøet).

## 3. Databasemigration

Skemaet vedligeholdes som en række filer i `db/migrations/`, nummereret i
rækkefølge. Kør dem med:

```bash
php bin/migrer.php
```

Scriptet er sikkert at køre gentagne gange: allerede kørte filer registreres
i tabellen `skema_migrationer` og springes over. Det virker både på en helt
tom database og på en eksisterende installation, der tidligere har kørt det
nu forældede `db/schema.sql` (migration 0001 opretter de oprindelige
tabeller med `IF NOT EXISTS`, og efterfølgende migrationer udvider og
migrerer data til den nye datamodel – bl.a. fra det gamle firdelte
statusfelt, den enkelte kontaktperson og de gamle underentreprenør-tabeller).

`db/schema.sql` er bevaret af historiske årsager, men bruges ikke længere –
brug altid `bin/migrer.php`.

**Bemærk om DDL:** MySQL/MariaDB laver et implicit COMMIT ved
`CREATE`/`ALTER`/`DROP TABLE`, så en migrationsfil kører ikke i én atomisk
SQL-transaktion. Fejler en fil midtvejs, rettes fejlen og scriptet køres
igen – alle migrationer er skrevet til at være sikre at genafspille
(`IF NOT EXISTS`, `INSERT IGNORE`/`ON DUPLICATE KEY UPDATE`).

## 4. Oprettelse af administrator

Der er ingen selvregistrering. Opret (eller opgradér en eksisterende bruger
til) administrator via kommandolinjen:

```bash
php bin/opret-admin.php SOB "Søren Sob" et-godt-password sob@bms.dk
```

Log herefter ind på `/login.php` med initialerne og adgangskoden.
Yderligere brugere (læser/redaktør/administrator) oprettes under
"Brugere" i menuen (kun synlig for administratorer).

### Roller

| Rolle | Rettigheder |
|---|---|
| Læser | Se projekter og dashboard |
| Redaktør | Herudover oprette/redigere projekter, virksomheder og tilføje noter |
| Administrator | Herudover administrere brugere og køre Excel-import |

## 5. Excel-import

Importerer projekter fra arket "Projekter" i en projektoversigt i samme
format som det oprindelige regneark (data fra række 4, kolonner A-W – se
`inc/import/Importer.php` for den fulde kolonne-mapping).

**Tag altid en databasebackup før en endelig import** (se afsnit 8) – en
import kan oprette/opdatere mange projekter på én gang.

### Dry-run (anbefalet første skridt)

```bash
php bin/importer.php /sti/til/Projektoversigt.xlsx --dry-run
```

Viser en opsummering (nye/opdaterede/oversprungne rækker samt advarsler og
fejl) uden at skrive noget til databasen. Gennemgå advarslerne – de peger på
data der ikke kunne tolkes helt sikkert (fx en byggestart skrevet som "?",
en BMS-ansvarlig skrevet som fritekst, eller en entreprenørliste der ikke
kunne opdeles sikkert i flere virksomheder) og som med fordel kan ryddes op
i regnearket eller efterfølgende i systemet.

### Endelig import

```bash
php bin/importer.php /sti/til/Projektoversigt.xlsx
```

Kører i én databasetransaktion. Importen er **idempotent**: hver
projektrække får et import-id (kilde + rækkenummer), så en gentagen import
af samme fil opdaterer de samme projekter i stedet for at oprette dubletter.

### Via browseren

Administratorer kan i stedet uploade og køre importen under
"Excel-import" i menuen (samme dry-run/endelig-import-funktionalitet). Har
serveren ikke udgående adgang, eller skal filen lægges direkte på serveren
(fx via `scp`), placeres den i `data/import/` (mappen ligger uden for git),
hvorefter den automatisk fremgår af listen på importsiden.

Den oprindelige Excel-kildefil må aldrig committes til git – `data/import/`
er derfor gitignored på nær en `.gitkeep`.

## 6. Testkommandoer

Testene kører **udelukkende** mod en dedikeret testdatabase (aldrig
udviklings- eller produktionsdatabasen) og nulstiller dens tabeller mellem
hver test. `tests/bootstrap.php` afbryder med en fejl, hvis `DB_NAME` ikke
indeholder ordet "test", som en sikkerhedsstopklods.

```sql
CREATE DATABASE bms_projekt_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
GRANT ALL PRIVILEGES ON bms_projekt_test.* TO 'bms_projekt'@'localhost';
```

```bash
composer install
vendor/bin/phpunit
```

`phpunit.xml.dist` sætter `DB_NAME=bms_projekt_test`; de øvrige
forbindelsesoplysninger (host/bruger/kodeord) læses fra `.env` som normalt.
Testene kører selv de nødvendige migrationer mod testdatabasen og bygger en
lille syntetisk `.xlsx`-testfil i hånden (`tests/fixtures/XlsxFixtureBuilder.php`)
for at teste Excel-importen uden at bruge/committe rigtige kundedata.

## 7. Produktion/intranet-opsætning

- Kør på virksomhedens intranet; peg vhosten (eller en `Alias`, hvis det
  skal ligge under en understi på et eksisterende site) på projektmappen.
- Sæt `.env` (eller rigtige miljøvariabler) med produktionens
  databaseoplysninger – aldrig i git.
- Opdatér til seneste version på serveren med `sh opdater.sh` (kræver rent
  arbejdstræ - scriptet stopper med en advarsel, hvis nogen har rettet
  direkte på serveren, og kører `php bin/migrer.php` automatisk til sidst).
  `.htaccess` har typisk en lokal Basic Auth-tilføjelse på serveren, som
  scriptet lader stå urørt via git "skip-worktree".
- Kører du ikke `opdater.sh`, så husk selv `php bin/migrer.php` efter hver
  opdatering, der indeholder nye filer i `db/migrations/`.
- `Chart.js` hentes fra et CDN i `dashboard.php`. Blokerer intranettet
  udgående adgang til CDN'er, downloades `chart.umd.min.js` fra Chart.js'
  GitHub-releases i stedet, lægges i `assets/`, og `<script src="...">` i
  `dashboard.php` peges herpå.
- Koden er skrevet uden hardkodede stier eller host-specifikke antagelser ud
  over selve `.env`, så den kan flyttes til en anden server uden ændringer i
  koden.
- Ingen hemmeligheder ligger i git – kun `.env.example` (skabelon).
- `composer install` (og dermed `vendor/`) er kun nødvendigt for at køre
  testene (PHPUnit er en dev-afhængighed) – applikationen har ingen
  runtime-afhængigheder og kræver ikke Composer i produktion.
- `.htaccess` blokerer adgang til `.git/`, `.env`/andre punktum-filer,
  `.sql`/`.md`-filer samt mapperne `inc/`, `db/`, `bin/`, `data/`, `tests/`
  direkte udefra.

## 8. Backup før import

Tag altid en dump af databasen, inden en endelig Excel-import køres på en
server med rigtige data:

```bash
mysqldump -u bms_projekt -p bms_projekt > backup-foer-import-$(date +%Y%m%d-%H%M).sql
```

Gendannelse ved behov: `mysql -u bms_projekt -p bms_projekt < backup-foer-import-....sql`

## Struktur

- `index.php` – projektoversigt (søgning, filtre, sortering, paginering)
- `projekt-detalje.php` – fuld projektvisning (virksomheder, kontakter,
  ansvarlige, noter, historik)
- `projekt-form.php` / `projekt-gem.php` / `projekt-slet.php` – opret,
  redigér og slet projekter
- `virksomheder.php` m.fl. – normaliseret stamdata for virksomheder (kunde,
  hovedentreprenør, underentreprenør, rådgiver, leverandør, andet)
- `brugere.php` m.fl. – administration af interne brugere/BMS-ansvarlige
  (kun administrator)
- `dashboard.php` – nøgletal og søjlediagrammer, filtreret som oversigten
- `import.php` / `bin/importer.php` – Excel-import (web hhv. kommandolinje)
- `login.php` / `logout.php` – simpelt sessionsbaseret login
- `db/migrations/` – databaseskema, kørt via `bin/migrer.php`
- `inc/` – konfiguration (`env.php`, `db.php`), autentificering
  (`auth.php`), domænelogik (`projekter.php`, `projekt_gem_logic.php`),
  Excel-import (`import/`) og fælles header/footer
- `tests/` – PHPUnit-tests mod en dedikeret testdatabase

## Videre arbejde

- Rollebaseret adgang pr. projekt (i dag er redaktør/administrator globalt)
- Eksport til Excel/CSV
- Vedhæftede filer/billeder på projekter (indlejrede billeder i det
  oprindelige regneark er bevidst ikke importeret automatisk, jf.
  opgavebeskrivelsen – se importens advarselsliste)
