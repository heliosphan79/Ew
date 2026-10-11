-- Instelbare bevestigingsberichten voor de afspraakbevestiging (kalenderblok)
-- en het uitschrijfbericht (nieuwsbrief). Leeg = de hardcoded standaardtekst
-- blijft gebruikt. newsletter_unsubscribe_message ondersteunt een
-- {{site}}-merge-tag voor de sitenaam.
ALTER TABLE site_settings
    ADD COLUMN booking_success_message VARCHAR(500) DEFAULT NULL,
    ADD COLUMN newsletter_unsubscribe_message VARCHAR(500) DEFAULT NULL;
