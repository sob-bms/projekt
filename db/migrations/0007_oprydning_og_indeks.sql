-- Data er migreret i tidligere trin (0003, 0005) - fjern nu de gamle,
-- erstattede fritekstfelter på projekter.
ALTER TABLE projekter
    DROP COLUMN status,
    DROP COLUMN salgsansvarlig,
    DROP COLUMN kontaktperson_navn,
    DROP COLUMN kontaktperson_telefon,
    DROP COLUMN kontaktperson_email,
    DROP COLUMN opstartsdato,
    DROP COLUMN slutdato;

ALTER TABLE projekter
    ADD CONSTRAINT chk_projekter_projektsum CHECK (projektsum IS NULL OR projektsum >= 0);

-- Krævede indekser (se README / opgavebeskrivelse): projektnavn, byggestart,
-- åben/lukket-status, salgsresultat, import-id. Relationen til
-- BMS-ansvarlig er allerede indekseret via projekt_ansvarlige.
ALTER TABLE projekter
    ADD INDEX ix_projekter_navn (navn),
    ADD INDEX ix_projekter_byggestart (byggestart_maaned),
    ADD INDEX ix_projekter_aabenlukket (aabenlukket),
    ADD INDEX ix_projekter_salgsresultat (salgsresultat),
    ADD UNIQUE INDEX ux_projekter_import (import_kilde, import_raekke);
