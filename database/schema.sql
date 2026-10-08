-- Eigen-Wijzer CMS — database schema
-- Import this once via phpMyAdmin (or `mysql -u user -p dbname < schema.sql`)
-- before running the site for the first time.

CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    -- Zelfbedieningsherstel van het wachtwoord: een SHA-256 hash van het
    -- token (nooit het token zelf) plus vervaldatum. Zie
    -- admin/forgot-password.php en admin/reset-password.php.
    password_reset_token_hash VARCHAR(64) DEFAULT NULL,
    password_reset_expires    DATETIME DEFAULT NULL,
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
    -- JSON-encoded array of content blocks shown above the (fixed) contact
    -- form on contact.php — same block system/renderer as pages.content.
    -- No DEFAULT here: MySQL rejects a default value on TEXT/BLOB columns
    -- (error 1101), so the initial '[]' is set explicitly below instead.
    content    MEDIUMTEXT NOT NULL,
    -- Eigen SEO-titel/omschrijving voor contact.php, dat geen rij in
    -- `pages` is. Leeg = terugval op de hardcoded tekst in contact.php.
    contact_meta_title       VARCHAR(200) DEFAULT NULL,
    contact_meta_description VARCHAR(300) DEFAULT NULL,
    -- Uitgebreidere AI-samenvatting van de praktijk, apart van
    -- meta_description, gebruikt in de "Over de praktijk"-sectie van
    -- llms.txt. Leeg = die sectie wordt niet getoond.
    ai_summary VARCHAR(2000) DEFAULT NULL,
    -- Optionele achtergrond ('none'/'accent'/'surface') voor het vaste
    -- contactformulier zelf, zelfde opties als op een gewone content-blok.
    contact_form_background VARCHAR(10) NOT NULL DEFAULT 'none',
    -- Bewaartermijn (in dagen) voor contactberichten en evenement-
    -- inschrijvingen. NULL = voor altijd bewaren (de standaard).
    submission_retention_days SMALLINT UNSIGNED DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO site_settings (id, content) VALUES (1, '[]');

-- Eenrijige cache (id = 1) voor het laatst opgehaalde Google Analytics
-- Data API-rapport, getoond op het admin-Dashboard. Zie
-- includes/ga_client.php. Geen rij nodig bij installatie.
CREATE TABLE IF NOT EXISTS analytics_cache (
    id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    payload    MEDIUMTEXT NOT NULL,
    fetched_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Nieuwsbrief: abonnees (opt-in via contactformulier/evenementinschrijving
-- of handmatig/CSV-import door de beheerder), de nieuwsbrieven zelf
-- (zelfde blokken-content-model als pages.content, maar beheerd apart),
-- en per-abonnee verzendrecords die ook dienen als basis voor open-/
-- klik-tracking en de unieke afmeldlink.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email             VARCHAR(190) NOT NULL UNIQUE,
    -- Voor de {{voornaam}}-merge-tag in een nieuwsbrief. Optioneel; NULL ->
    -- generieke aanhef bij verzending.
    first_name        VARCHAR(100) DEFAULT NULL,
    status            ENUM('subscribed', 'unsubscribed') NOT NULL DEFAULT 'subscribed',
    -- Waar het adres vandaan komt, puur informatief voor in het
    -- beheerpaneel (geen functioneel gedrag hangt hiervan af).
    source            VARCHAR(30) NOT NULL DEFAULT 'import',
    -- Voor de one-click-afmeldlink — nooit het e-mailadres zelf in de URL.
    unsubscribe_token VARCHAR(64) NOT NULL UNIQUE,
    subscribed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at   DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletters (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject    VARCHAR(200) NOT NULL,
    -- JSON-array van blokken, zelfde model als pages.content maar met een
    -- kleinere toegestane set bloktypes (zie NEWSLETTER_BLOCK_TYPES in
    -- functions.php) — enkel wat betrouwbaar rendert in e-mailclients.
    content    MEDIUMTEXT NOT NULL,
    status     ENUM('draft', 'sending', 'sent', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at    DATETIME DEFAULT NULL,
    -- Meest recente SMTP-foutmelding tijdens het verzenden (bv. een
    -- geweigerde login), ververst per batch — NULL zodra een batch zonder
    -- fouten afrondt. Elke verzending wordt sowieso als sent_at gemarkeerd
    -- ongeacht of de mailserver ze effectief aanvaardde, dus dit is de
    -- enige plek waar een mislukte verzending zichtbaar wordt.
    last_send_error VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eén rij per (nieuwsbrief, abonnee) — aangemaakt in bulk zodra "Verstuur"
-- wordt geklikt, dan in batches afgewerkt (sent_at blijft NULL tot de mail
-- effectief de deur uit is). send_token drijft zowel de open-pixel als de
-- klik-tracking-redirect.
CREATE TABLE IF NOT EXISTS newsletter_sends (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    newsletter_id  INT UNSIGNED NOT NULL,
    subscriber_id  INT UNSIGNED NOT NULL,
    send_token     VARCHAR(64) NOT NULL UNIQUE,
    queued_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at        DATETIME DEFAULT NULL,
    opened_at      DATETIME DEFAULT NULL,
    FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_newsletter_subscriber (newsletter_id, subscriber_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_clicks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    send_id    INT UNSIGNED NOT NULL,
    url        VARCHAR(500) NOT NULL,
    clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (send_id) REFERENCES newsletter_sends(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- E-mailinstellingen (SMTP-server, afzender, reply-to, ontvanger voor
-- contactmeldingen), instelbaar via admin/mail-settings.php. Eenrijige
-- tabel (id = 1), zelfde patroon als site_settings. Elk veld is NULL
-- zolang het niet via het beheerpaneel is opgeslagen —
-- includes/functions.php's resolve_mail_config() valt dan per veld terug
-- op config.php's (verouderde) 'smtp'-sectie.
CREATE TABLE IF NOT EXISTS mail_settings (
    id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    host       VARCHAR(190) DEFAULT NULL,
    port       SMALLINT UNSIGNED DEFAULT NULL,
    encryption ENUM('tls', 'ssl', 'none') DEFAULT NULL,
    username   VARCHAR(190) DEFAULT NULL,
    password   VARCHAR(190) DEFAULT NULL,
    from_email VARCHAR(190) DEFAULT NULL,
    from_name  VARCHAR(190) DEFAULT NULL,
    reply_to   VARCHAR(190) DEFAULT NULL,
    to_email   VARCHAR(190) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO mail_settings (id) VALUES (1);
