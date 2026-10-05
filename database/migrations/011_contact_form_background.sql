-- Optionele achtergrond ('none'/'accent'/'surface') voor het vaste
-- contactformulier zelf, zelfde opties als op een gewone content-blok.
ALTER TABLE site_settings
    ADD COLUMN contact_form_background VARCHAR(10) NOT NULL DEFAULT 'none' AFTER ai_summary;
