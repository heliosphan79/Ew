-- Eigen-Wijzer CMS — database schema
-- Import this once via phpMyAdmin (or `mysql -u user -p dbname < schema.sql`)
-- before running the site for the first time.

CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug              VARCHAR(150) NOT NULL UNIQUE,
    title             VARCHAR(200) NOT NULL,
    -- JSON-encoded array of content blocks (text/image/quote/list/buttons) — see includes/functions.php render_blocks().
    content           MEDIUMTEXT NOT NULL,
    -- Which of the frontend look-and-feel variants (a/b/c) this page renders with.
    theme_variant     VARCHAR(4) NOT NULL DEFAULT 'a',
    meta_description  VARCHAR(300) DEFAULT NULL,
    is_homepage       TINYINT(1) NOT NULL DEFAULT 0,
    published         TINYINT(1) NOT NULL DEFAULT 0,
    -- Whether this page gets a main-menu link. A published page with this
    -- off is still reachable at its own URL — just only via a direct link
    -- or a button block, not from the site navigation.
    show_in_menu      TINYINT(1) NOT NULL DEFAULT 1,
    nav_order         INT NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_submissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address  VARCHAR(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointment slots for the calendar block. An admin adds available
-- date+time slots (calendar.php); a visitor books one via the public
-- booking endpoint, which atomically flips status to 'booked' so two
-- people can never take the same slot.
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

-- Events (lectures, workshops, ...) for the "Evenementen" block. Registering
-- is capacity-checked atomically in public/event-register.php (transaction +
-- SELECT ... FOR UPDATE) so two visitors can never both take the last spot.
CREATE TABLE IF NOT EXISTS events (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(200) NOT NULL,
    description       TEXT NOT NULL,
    event_date        DATE NOT NULL,
    event_time        TIME DEFAULT NULL,
    location          VARCHAR(200) DEFAULT NULL,
    -- NULL = unlimited capacity.
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

-- Vaste, eenrijige tabel (id = 1) met contactgegevens voor de footer
-- (adres/telefoon/e-mail), beheerbaar via admin/settings.php. Leeg = niets
-- tonen in de footer, nooit verzinnen.
CREATE TABLE IF NOT EXISTS site_settings (
    id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    address    VARCHAR(255) DEFAULT NULL,
    phone      VARCHAR(50)  DEFAULT NULL,
    email      VARCHAR(190) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO site_settings (id) VALUES (1);
