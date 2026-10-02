-- Voegt content toe aan site_settings: optionele content-blokken (tekst,
-- kaart, ...) die op de contactpagina verschijnen vóór het vaste
-- contactformulier. Beheerbaar via admin/settings.php, net als de
-- bestaande contactgegevens. Leeg = niets extra tonen, enkel het formulier
-- zoals vandaag.
--
-- TEXT/BLOB-kolommen mogen in MySQL geen DEFAULT hebben (fout 1101), dus de
-- kolom wordt eerst NULL toegevoegd, de bestaande rij krijgt expliciet '[]',
-- en pas daarna wordt de kolom NOT NULL gemaakt.
ALTER TABLE site_settings
    ADD COLUMN content MEDIUMTEXT NULL AFTER email;

UPDATE site_settings SET content = '[]' WHERE content IS NULL;

ALTER TABLE site_settings
    MODIFY COLUMN content MEDIUMTEXT NOT NULL;
