-- Vierde meegeleverde demo-thema (themes/stilte/) — zie README.md
-- "Thema's" voor een omschrijving. Zelfde opzet als de twee bestaande
-- demo-thema's uit 024_themes.sql.
INSERT IGNORE INTO themes (slug, name, description, logo_file, schemes, is_builtin) VALUES (
    'stilte',
    'Stilte',
    'Radicale eenvoud: geen schaduwen, nauwelijks zichtbare lijnen, dunne lettertypes en veel witruimte voor een rustig, zwevend geheel. Test-thema voor het thema-systeem.',
    'logo.svg',
    '[{"key":"a","label":"A — Mist"},{"key":"b","label":"B — Schaduw"},{"key":"c","label":"C — Water"}]',
    0
);
