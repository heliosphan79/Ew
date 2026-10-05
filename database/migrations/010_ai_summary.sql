-- Eigen, uitgebreidere AI-samenvatting van de praktijk voor llms.txt — apart
-- van meta_description (die blijft kort, voor klassieke zoekmachine-snippets).
-- Leeg = de "Over de praktijk"-sectie wordt gewoon niet getoond, nooit verzinnen.
ALTER TABLE site_settings
    ADD COLUMN ai_summary VARCHAR(2000) DEFAULT NULL AFTER contact_meta_description;
