-- Voegt site_settings toe: een vaste, eenrijige tabel (id = 1) met
-- contactgegevens voor de footer (adres/telefoon/e-mail), beheerbaar via
-- admin/settings.php. Leeg = niets tonen in de footer, nooit verzinnen.
CREATE TABLE IF NOT EXISTS site_settings (
    id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    address    VARCHAR(255) DEFAULT NULL,
    phone      VARCHAR(50)  DEFAULT NULL,
    email      VARCHAR(190) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO site_settings (id) VALUES (1);
