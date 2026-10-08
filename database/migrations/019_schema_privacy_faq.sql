-- LET OP: dit verwijdert het bestaande vrije-tekst adresveld. Noteer het
-- huidige adres (Instellingen → Adres) voor je deze migratie draait, en
-- vul het nadien opnieuw in via de drie nieuwe velden (straat, postcode,
-- gemeente).
--
-- Uitgebreider schema.org-markup (ProfessionalService met gestructureerd
-- adres, prijsklasse, werkgebied, links naar LinkedIn/Google Business, en
-- Wendy als Person-entiteit) en een aanwijsbare privacypagina (zelfde
-- exclusieve-vlag-patroon als is_homepage). Zie organization_schema(),
-- person_schema() en get_privacy_page_url() in includes/functions.php.
ALTER TABLE site_settings DROP COLUMN address;
ALTER TABLE site_settings
    ADD COLUMN street_address VARCHAR(150) DEFAULT NULL AFTER id,
    ADD COLUMN postal_code VARCHAR(12) DEFAULT NULL AFTER street_address,
    ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER postal_code,
    ADD COLUMN price_range VARCHAR(50) DEFAULT NULL,
    ADD COLUMN area_served VARCHAR(200) DEFAULT NULL,
    ADD COLUMN linkedin_url VARCHAR(300) DEFAULT NULL,
    ADD COLUMN google_business_url VARCHAR(300) DEFAULT NULL,
    ADD COLUMN person_name VARCHAR(150) DEFAULT NULL,
    ADD COLUMN person_job_title VARCHAR(150) DEFAULT NULL,
    ADD COLUMN person_expertise VARCHAR(500) DEFAULT NULL,
    ADD COLUMN person_bio VARCHAR(1000) DEFAULT NULL;

ALTER TABLE pages ADD COLUMN is_privacy_page TINYINT(1) NOT NULL DEFAULT 0 AFTER is_homepage;
