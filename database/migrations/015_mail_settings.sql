-- E-mailinstellingen (SMTP-server, afzender, reply-to, ontvanger voor
-- contactmeldingen), voortaan instelbaar via admin/mail-settings.php in
-- plaats van enkel via config/config.php. Eenrijige tabel (id = 1), zelfde
-- patroon als site_settings. Elk veld is NULL zolang het niet via het
-- beheerpaneel is opgeslagen — includes/functions.php's
-- resolve_mail_config() valt dan per veld terug op config.php's
-- (verouderde) 'smtp'-sectie, zodat bestaande mailconfiguratie na deze
-- update meteen blijft werken totdat iemand de nieuwe pagina opent.

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
