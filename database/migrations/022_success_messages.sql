-- Instelbare bevestigingsberichten na het contactformulier en een
-- evenement-interesse-opgave. Leeg = de hardcoded standaardtekst blijft
-- gebruikt. event_registration_success_message ondersteunt een
-- {{titel}}-merge-tag voor de evenementnaam.
ALTER TABLE site_settings
    ADD COLUMN contact_success_message VARCHAR(500) DEFAULT NULL,
    ADD COLUMN event_registration_success_message VARCHAR(500) DEFAULT NULL;
