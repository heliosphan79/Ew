-- Voegt content toe aan site_settings: optionele content-blokken (tekst,
-- kaart, ...) die op de contactpagina verschijnen vóór het vaste
-- contactformulier. Beheerbaar via admin/settings.php, net als de
-- bestaande contactgegevens. Leeg = niets extra tonen, enkel het formulier
-- zoals vandaag.
ALTER TABLE site_settings
    ADD COLUMN content MEDIUMTEXT NOT NULL DEFAULT '[]' AFTER email;
