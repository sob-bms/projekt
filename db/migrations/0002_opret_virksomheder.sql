-- Normaliseret virksomhedsmodel: samme virksomhed kan bruges på flere
-- projekter, med forskellig rolle pr. projekt (kunde, hovedentreprenør,
-- underentreprenør, rådgiver, leverandør, andet).
CREATE TABLE IF NOT EXISTS virksomheder (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    navn VARCHAR(255) NOT NULL,
    cvr VARCHAR(20) DEFAULT NULL,
    telefon VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    note TEXT,
    oprettet TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opdateret TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- utf8mb4_danish_ci er en case-insensitiv collation, så UNIQUE her
    -- forhindrer allerede dubletter der kun varierer i store/små bogstaver.
    UNIQUE KEY ux_virksomheder_navn (navn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projekt_virksomheder (
    projekt_id INT UNSIGNED NOT NULL,
    virksomhed_id INT UNSIGNED NOT NULL,
    rolle ENUM('Kunde', 'Hovedentreprenør', 'Underentreprenør', 'Rådgiver', 'Leverandør', 'Andet') NOT NULL,
    fagomraade VARCHAR(100) DEFAULT NULL,
    aftalt_sum DECIMAL(14,2) DEFAULT NULL,
    PRIMARY KEY (projekt_id, virksomhed_id, rolle),
    KEY ix_projekt_virksomheder_virksomhed (virksomhed_id),
    FOREIGN KEY (projekt_id) REFERENCES projekter(id) ON DELETE CASCADE,
    FOREIGN KEY (virksomhed_id) REFERENCES virksomheder(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
