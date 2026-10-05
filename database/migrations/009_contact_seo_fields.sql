-- Eigen titel/meta-omschrijving voor de contactpagina — die pagina is geen
-- rij in `pages`, dus had tot nu toe hardcoded SEO-tekst in contact.php.
-- Leeg = contact.php valt terug op de bestaande hardcoded tekst, nooit
-- verzinnen.
ALTER TABLE site_settings
    ADD COLUMN contact_meta_title       VARCHAR(200) DEFAULT NULL AFTER content,
    ADD COLUMN contact_meta_description VARCHAR(300) DEFAULT NULL AFTER contact_meta_title;
