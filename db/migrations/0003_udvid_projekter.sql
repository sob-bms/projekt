-- Udvider projekter med de nye CRM-felter. Eksisterende kolonner
-- (navn, adresse, projektsum, noter, oprettet, opdateret) genbruges uændret.
ALTER TABLE projekter
    ADD COLUMN lead VARCHAR(150) DEFAULT NULL AFTER navn,
    ADD COLUMN postnummer VARCHAR(10) DEFAULT NULL AFTER adresse,
    ADD COLUMN by_navn VARCHAR(150) DEFAULT NULL AFTER postnummer,
    ADD COLUMN stadie VARCHAR(100) DEFAULT NULL AFTER by_navn,
    ADD COLUMN enterpriseform VARCHAR(100) DEFAULT NULL AFTER stadie,
    ADD COLUMN byggestart_maaned CHAR(7) DEFAULT NULL AFTER enterpriseform,
    ADD COLUMN byggestart_bekraeftet TINYINT(1) NOT NULL DEFAULT 0 AFTER byggestart_maaned,
    ADD COLUMN byggeslut_maaned CHAR(7) DEFAULT NULL AFTER byggestart_bekraeftet,
    ADD COLUMN aabenlukket ENUM('Åben', 'Lukket', 'Annulleret') NOT NULL DEFAULT 'Åben' AFTER byggeslut_maaned,
    ADD COLUMN salgsresultat ENUM('Ikke afgjort', 'Forhandling/dialog', 'Vundet', 'Tabt') NOT NULL DEFAULT 'Ikke afgjort' AFTER aabenlukket,
    ADD COLUMN tabt_aarsag ENUM('Pris', 'Relationer', 'Andet') DEFAULT NULL AFTER salgsresultat,
    ADD COLUMN tabt_aarsag_note VARCHAR(500) DEFAULT NULL AFTER tabt_aarsag,
    ADD COLUMN antal_plan SMALLINT UNSIGNED DEFAULT NULL AFTER noter,
    ADD COLUMN kaelder VARCHAR(10) DEFAULT NULL AFTER antal_plan,
    ADD COLUMN antal_boliger SMALLINT UNSIGNED DEFAULT NULL AFTER kaelder,
    ADD COLUMN ekstern_link VARCHAR(500) DEFAULT NULL AFTER antal_boliger,
    ADD COLUMN legacy_noter TEXT DEFAULT NULL AFTER ekstern_link,
    ADD COLUMN oprettet_af INT UNSIGNED DEFAULT NULL AFTER opdateret,
    ADD COLUMN aendret_af INT UNSIGNED DEFAULT NULL AFTER oprettet_af,
    ADD COLUMN import_kilde VARCHAR(50) DEFAULT NULL AFTER aendret_af,
    ADD COLUMN import_raekke INT UNSIGNED DEFAULT NULL AFTER import_kilde,
    ADD COLUMN import_raa_data TEXT DEFAULT NULL AFTER import_raekke,
    ADD CONSTRAINT fk_projekter_oprettet_af FOREIGN KEY (oprettet_af) REFERENCES brugere(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_projekter_aendret_af FOREIGN KEY (aendret_af) REFERENCES brugere(id) ON DELETE SET NULL;

-- Overfør data fra det gamle firdelte statusfelt til de nye, mere
-- specifikke felter (stadie / åben-lukket / salgsresultat), så ingen
-- eksisterende oplysninger går tabt.
UPDATE projekter SET
    stadie = COALESCE(stadie, status),
    aabenlukket = CASE WHEN status IN ('Afsluttet', 'Tabt') THEN 'Lukket' ELSE 'Åben' END,
    salgsresultat = CASE
        WHEN status = 'Afsluttet' THEN 'Vundet'
        WHEN status = 'Tabt' THEN 'Tabt'
        ELSE 'Ikke afgjort'
    END
WHERE status IS NOT NULL;

-- Normalisér de gamle opstarts-/slutdatoer (DATE) til måned/år-felterne.
UPDATE projekter SET
    byggestart_maaned = DATE_FORMAT(opstartsdato, '%Y-%m')
WHERE opstartsdato IS NOT NULL AND byggestart_maaned IS NULL;

UPDATE projekter SET
    byggeslut_maaned = DATE_FORMAT(slutdato, '%Y-%m')
WHERE slutdato IS NOT NULL AND byggeslut_maaned IS NULL;
