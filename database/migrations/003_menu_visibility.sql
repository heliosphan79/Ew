-- Migration: adds a per-page "show in main menu" toggle, so a published
-- page can exist without getting a navigation link — reachable only via a
-- direct link or a button block instead.
--
-- Only needed if you already ran an older schema.sql. Fresh installs
-- should just (re)import the current database/schema.sql instead.

ALTER TABLE pages
    ADD COLUMN show_in_menu TINYINT(1) NOT NULL DEFAULT 1 AFTER published;
