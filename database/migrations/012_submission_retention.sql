-- Bewaartermijn (in dagen) voor contactberichten en evenement-inschrijvingen.
-- NULL = voor altijd bewaren (het bestaande gedrag, blijft de standaard).
ALTER TABLE site_settings
    ADD COLUMN submission_retention_days SMALLINT UNSIGNED DEFAULT NULL AFTER contact_form_background;
