-- Migration: adds events + event_registrations for the new "Evenementen"
-- block (announcing lectures/workshops with optional capacity-limited
-- registration).
--
-- Only needed if you already ran an older schema.sql. Fresh installs
-- should just (re)import the current database/schema.sql instead.

CREATE TABLE IF NOT EXISTS events (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(200) NOT NULL,
    description       TEXT NOT NULL,
    event_date        DATE NOT NULL,
    event_time        TIME DEFAULT NULL,
    location          VARCHAR(200) DEFAULT NULL,
    capacity          INT UNSIGNED DEFAULT NULL,
    published         TINYINT(1) NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_registrations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address    VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
