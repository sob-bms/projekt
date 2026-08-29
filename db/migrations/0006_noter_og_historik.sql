-- Løbende projektnotater.
CREATE TABLE IF NOT EXISTS projekt_noter (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projekt_id INT UNSIGNED NOT NULL,
    tekst TEXT NOT NULL,
    oprettet_af INT UNSIGNED DEFAULT NULL,
    oprettet TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_projekt_noter_projekt (projekt_id),
    FOREIGN KEY (projekt_id) REFERENCES projekter(id) ON DELETE CASCADE,
    FOREIGN KEY (oprettet_af) REFERENCES brugere(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simpel ændringshistorik: hvem ændrede hvilket felt hvornår.
CREATE TABLE IF NOT EXISTS projekt_historik (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projekt_id INT UNSIGNED NOT NULL,
    bruger_id INT UNSIGNED DEFAULT NULL,
    felt VARCHAR(50) NOT NULL,
    gammel_vaerdi TEXT,
    ny_vaerdi TEXT,
    tidspunkt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_projekt_historik_projekt (projekt_id),
    FOREIGN KEY (projekt_id) REFERENCES projekter(id) ON DELETE CASCADE,
    FOREIGN KEY (bruger_id) REFERENCES brugere(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
