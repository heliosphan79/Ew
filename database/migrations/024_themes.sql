-- Volledig instelbare visuele thema's (naast de bestaande 3 kleurschema's
-- binnen één thema): een thema bundelt een eigen style.css, een optioneel
-- eigen logo, en de labels voor zijn 3 kleurschema's. Beheerd via
-- admin/themes.php, ge-upload als zip (theme.json + style.css + optioneel
-- logo.svg/png/jpg/webp — zie admin/theme-upload.php voor de validatie).
--
-- Een thema heeft altijd exact 3 kleurschema's met vaste sleutels a/b/c —
-- dezelfde sleutels als het bestaande pages.theme_variant-veld, dus geen
-- migratie van bestaande pagina's nodig bij het wisselen van thema: enkel
-- de labels en de daadwerkelijke CSS-waarden achter a/b/c veranderen mee.
CREATE TABLE themes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(60) NOT NULL UNIQUE,
    name         VARCHAR(150) NOT NULL,
    description  VARCHAR(300) DEFAULT NULL,
    -- Bestandsnaam van het logo binnen themes/<slug>/ (logo.svg/png/jpg/
    -- webp). NULL = geen eigen logo, dan valt de header terug op het
    -- ingebouwde, geanimeerde kompasnaald-logo.
    logo_file    VARCHAR(20) DEFAULT NULL,
    -- JSON-array van exact 3 objecten {"key":"a|b|c","label":"..."} —
    -- zelfde opslagconventie als pages.content (JSON-tekst, geen native
    -- MySQL JSON-kolom), zie decode_blocks()/json_encode() elders.
    schemes      TEXT NOT NULL,
    is_builtin   TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO themes (id, slug, name, description, logo_file, schemes, is_builtin) VALUES (
    1,
    'eigen-wijzer-klassiek',
    'Eigen-Wijzer (klassiek)',
    'Het bestaande ontwerp van de site: crème, salie-groen en terracotta, met het kompasnaald-logo.',
    NULL,
    '[{"key":"a","label":"A — Crème, salie & terracotta"},{"key":"b","label":"B — Koraal"},{"key":"c","label":"C — Salie-groen"}]',
    1
);

-- Twee meegeleverde demo-thema's (themes/noir-editoriaal/,
-- themes/zacht-rond/) om het thema-systeem meteen te kunnen uitproberen
-- — zie README.md "Thema's" voor een omschrijving van elk.
INSERT IGNORE INTO themes (slug, name, description, logo_file, schemes, is_builtin) VALUES (
    'noir-editoriaal',
    'Noir Editoriaal',
    'Scherpe hoeken, harde schaduwen en vette hoofdletter-koppen — een strak, kranten-achtig tegengewicht voor het warme klassieke thema. Test-thema voor het thema-systeem.',
    'logo.svg',
    '[{"key":"a","label":"A — Scherp wit"},{"key":"b","label":"B — Inkt (donker)"},{"key":"c","label":"C — Oker"}]',
    0
);
INSERT IGNORE INTO themes (slug, name, description, logo_file, schemes, is_builtin) VALUES (
    'zacht-rond',
    'Zacht & Rond',
    'Grote afgeronde hoeken, zachte gekleurde schaduwen, een rond leestype en pastelkleuren — vriendelijk en speels tegenover het ingetogen klassieke thema. Test-thema voor het thema-systeem.',
    'logo.svg',
    '[{"key":"a","label":"A — Perzik"},{"key":"b","label":"B — Lavendel"},{"key":"c","label":"C — Muntgroen"}]',
    0
);

-- NULL = het ingebouwde thema hierboven (zelfde "leeg = standaard"-
-- conventie als de rest van site_settings). ON DELETE SET NULL zodat het
-- verwijderen van een thema de site nooit zonder actief thema achterlaat.
ALTER TABLE site_settings
    ADD COLUMN active_theme_id INT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT fk_site_settings_active_theme
        FOREIGN KEY (active_theme_id) REFERENCES themes(id) ON DELETE SET NULL;
