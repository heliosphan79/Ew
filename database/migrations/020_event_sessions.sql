-- Evenementen krijgen meerdere sessiedatums (een reeks), plus prijs en een
-- inschrijvingsdeadline. Bestaande event_date/event_time worden overgezet
-- naar een nieuwe rij in event_sessions per evenement, dan pas verwijderd —
-- geen datagegevens gaan verloren, enkel van kolom naar eigen tabel.
CREATE TABLE IF NOT EXISTS event_sessions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED NOT NULL,
    session_date  DATE NOT NULL,
    session_time  TIME DEFAULT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_sessions_event_date (event_id, session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO event_sessions (event_id, session_date, session_time)
SELECT id, event_date, event_time FROM events;

ALTER TABLE events
    DROP COLUMN event_date,
    DROP COLUMN event_time,
    ADD COLUMN price VARCHAR(50) DEFAULT NULL,
    ADD COLUMN registration_deadline DATE DEFAULT NULL;

ALTER TABLE event_registrations ADD COLUMN notify_next_series TINYINT(1) NOT NULL DEFAULT 0;
