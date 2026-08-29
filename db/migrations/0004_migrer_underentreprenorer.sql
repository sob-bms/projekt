-- Migrerer de gamle underentreprenør-tabeller ind i den normaliserede
-- virksomhedsmodel (rolle = Underentreprenør) og fjerner derefter de gamle
-- tabeller, som er erstattet af virksomheder / projekt_virksomheder.
INSERT IGNORE INTO virksomheder (navn, telefon, email, oprettet, opdateret)
SELECT TRIM(navn), telefon, email, NOW(), NOW()
FROM underentreprenorer
WHERE TRIM(navn) <> '';

INSERT INTO projekt_virksomheder (projekt_id, virksomhed_id, rolle, fagomraade, aftalt_sum)
SELECT pu.projekt_id, v.id, 'Underentreprenør', NULLIF(TRIM(u.fag), ''), pu.aftalt_sum
FROM projekt_underentreprenorer pu
INNER JOIN underentreprenorer u ON u.id = pu.underentreprenor_id
INNER JOIN virksomheder v ON v.navn = TRIM(u.navn)
ON DUPLICATE KEY UPDATE
    fagomraade = VALUES(fagomraade),
    aftalt_sum = VALUES(aftalt_sum);

DROP TABLE IF EXISTS projekt_underentreprenorer;
DROP TABLE IF EXISTS underentreprenorer;
