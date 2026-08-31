-- Gemt filter pr. bruger og side ('projekter' eller 'dashboard'), så hver
-- bruger kan få sit eget foretrukne filter anvendt automatisk næste gang de
-- besøger siden (uden query-parametre). filter_json indeholder den
-- normaliserede filterstruktur fra projekt_filter_fra_get() som JSON.
CREATE TABLE IF NOT EXISTS gemte_filtre (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bruger_id INT UNSIGNED NOT NULL,
    side VARCHAR(20) NOT NULL,
    filter_json TEXT NOT NULL,
    opdateret TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY ux_gemte_filtre_bruger_side (bruger_id, side),
    FOREIGN KEY (bruger_id) REFERENCES brugere(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
