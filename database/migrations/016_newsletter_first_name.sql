-- Voornaam van een nieuwsbrief-abonnee, voor persoonlijke aanspreking via
-- de {{voornaam}}-merge-tag in een nieuwsbrief. Optioneel (NULL = onbekend
-- -> generieke aanhef bij verzending). Wordt afgeleid uit het "Naam"-veld
-- bij opt-in via het contact-/evenementformulier, apart invulbaar bij
-- handmatig toevoegen, en optioneel als tweede CSV-kolom.

ALTER TABLE newsletter_subscribers ADD COLUMN first_name VARCHAR(100) DEFAULT NULL AFTER email;
