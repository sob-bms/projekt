-- BMS Projekter – databaseskema
-- Kør som: mysql -u <bruger> -p bms_projekt < schema.sql
--
-- Opret database og bruger på forhånd, fx:
--   CREATE DATABASE bms_projekt CHARACTER SET utf8mb4 COLLATE utf8mb4_danish_ci;
--   CREATE USER 'bms_projekt'@'localhost' IDENTIFIED BY 'et-godt-password';
--   GRANT ALL PRIVILEGES ON bms_projekt.* TO 'bms_projekt'@'localhost';
--   FLUSH PRIVILEGES;

CREATE TABLE IF NOT EXISTS projekter (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    navn VARCHAR(255) NOT NULL,
    adresse VARCHAR(255) DEFAULT NULL,
    status ENUM('Tilbud','Igangværende','Afsluttet','Tabt') NOT NULL DEFAULT 'Tilbud',
    projektsum DECIMAL(14,2) DEFAULT NULL,
    salgsansvarlig VARCHAR(150) DEFAULT NULL,
    kontaktperson_navn VARCHAR(150) DEFAULT NULL,
    kontaktperson_telefon VARCHAR(50) DEFAULT NULL,
    kontaktperson_email VARCHAR(150) DEFAULT NULL,
    opstartsdato DATE DEFAULT NULL,
    slutdato DATE DEFAULT NULL,
    noter TEXT,
    oprettet TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opdateret TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS underentreprenorer (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    navn VARCHAR(255) NOT NULL,
    fag VARCHAR(100) DEFAULT NULL,
    telefon VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projekt_underentreprenorer (
    projekt_id INT UNSIGNED NOT NULL,
    underentreprenor_id INT UNSIGNED NOT NULL,
    aftalt_sum DECIMAL(14,2) DEFAULT NULL,
    PRIMARY KEY (projekt_id, underentreprenor_id),
    FOREIGN KEY (projekt_id) REFERENCES projekter(id) ON DELETE CASCADE,
    FOREIGN KEY (underentreprenor_id) REFERENCES underentreprenorer(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
