-- Flere kontaktpersoner pr. projekt.
CREATE TABLE IF NOT EXISTS kontaktpersoner (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projekt_id INT UNSIGNED NOT NULL,
    virksomhed_id INT UNSIGNED DEFAULT NULL,
    navn VARCHAR(150) NOT NULL,
    stilling VARCHAR(150) DEFAULT NULL,
    telefon VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    note TEXT,
    primaer TINYINT(1) NOT NULL DEFAULT 0,
    oprettet TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opdateret TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_kontaktpersoner_projekt (projekt_id),
    FOREIGN KEY (projekt_id) REFERENCES projekter(id) ON DELETE CASCADE,
    FOREIGN KEY (virksomhed_id) REFERENCES virksomheder(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Et projekt kan have ingen, én eller flere BMS-ansvarlige. Højst én må
-- være primær (håndhæves i applikationslaget ved gem).
CREATE TABLE IF NOT EXISTS projekt_ansvarlige (
    projekt_id INT UNSIGNED NOT NULL,
    bruger_id INT UNSIGNED NOT NULL,
    primaer TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (projekt_id, bruger_id),
    KEY ix_projekt_ansvarlige_bruger (bruger_id),
    FOREIGN KEY (projekt_id) REFERENCES projekter(id) ON DELETE CASCADE,
    FOREIGN KEY (bruger_id) REFERENCES brugere(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrér den gamle fritekst-salgsansvarlig til en rigtig intern bruger.
-- Kun rene, enkelte initialer fra testdata kan migreres sikkert; da feltet
-- var fritekst uden vores nye "flere ansvarlige"-format, oprettes højst én
-- bruger pr. hidtidig værdi og sættes som primær ansvarlig.
INSERT IGNORE INTO brugere (initialer, navn, rolle, aktiv)
SELECT DISTINCT TRIM(salgsansvarlig), TRIM(salgsansvarlig), 'redaktoer', 1
FROM projekter
WHERE salgsansvarlig IS NOT NULL AND TRIM(salgsansvarlig) <> '';

INSERT IGNORE INTO projekt_ansvarlige (projekt_id, bruger_id, primaer)
SELECT p.id, b.id, 1
FROM projekter p
INNER JOIN brugere b ON b.initialer = TRIM(p.salgsansvarlig)
WHERE p.salgsansvarlig IS NOT NULL AND TRIM(p.salgsansvarlig) <> '';

-- Migrér den gamle enkelte kontaktperson (fritekstfelter) til den nye,
-- normaliserede kontaktpersoner-tabel og markér den som primær.
INSERT INTO kontaktpersoner (projekt_id, navn, telefon, email, primaer)
SELECT id, kontaktperson_navn, kontaktperson_telefon, kontaktperson_email, 1
FROM projekter
WHERE kontaktperson_navn IS NOT NULL AND TRIM(kontaktperson_navn) <> '';
