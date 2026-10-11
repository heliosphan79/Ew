-- Optioneel telefoonnummer bij een evenement-interesse-opgave, naast
-- naam/e-mailadres.
ALTER TABLE event_registrations ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER email;
