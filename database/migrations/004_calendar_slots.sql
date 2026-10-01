-- Migration: adds the calendar_slots table for the new "kalenderblok"
-- (appointment booking block) and drops theme variant D in favour of
-- Wendy's three real design variants (A/B/C) — see 005 note below.
--
-- Only needed if you already ran an older schema.sql. Fresh installs
-- should just (re)import the current database/schema.sql instead.

CREATE TABLE IF NOT EXISTS calendar_slots (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_date      DATE NOT NULL,
    slot_time      TIME NOT NULL,
    status         ENUM('available', 'booked') NOT NULL DEFAULT 'available',
    booked_name    VARCHAR(150) DEFAULT NULL,
    booked_email   VARCHAR(190) DEFAULT NULL,
    booked_message TEXT DEFAULT NULL,
    booked_at      DATETIME DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slot (slot_date, slot_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Any existing page with theme_variant = 'd' falls back to 'a' automatically
-- (see normalize_theme_variant() in includes/functions.php) — no column
-- change needed here, just re-pick a variant for those pages in the editor.
