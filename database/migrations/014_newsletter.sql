-- Nieuwsbrief: abonnees (opt-in via contactformulier/evenementinschrijving
-- of handmatig/CSV-import), de nieuwsbrieven zelf (blokken-content, zoals
-- pages.content maar met een kleinere toegestane set bloktypes), en
-- per-abonnee verzendrecords voor batch-verzending + open-/klik-tracking +
-- de unieke afmeldlink.

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email             VARCHAR(190) NOT NULL UNIQUE,
    status            ENUM('subscribed', 'unsubscribed') NOT NULL DEFAULT 'subscribed',
    source            VARCHAR(30) NOT NULL DEFAULT 'import',
    unsubscribe_token VARCHAR(64) NOT NULL UNIQUE,
    subscribed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at   DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletters (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject    VARCHAR(200) NOT NULL,
    content    MEDIUMTEXT NOT NULL,
    status     ENUM('draft', 'sending', 'sent') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at    DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
