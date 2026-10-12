-- Vijfde meegeleverde demo-thema (themes/huiskamer/) — zie README.md
-- "Thema's" voor een omschrijving. Zelfde opzet als de andere demo-thema's
-- uit 024_themes.sql en 025_theme_stilte.sql.
INSERT IGNORE INTO themes (slug, name, description, logo_file, schemes, is_builtin) VALUES (
    'huiskamer',
    'Huiskamer',
    'Een vaste donkere zijbalk met genummerd menu en een leesvoortgang-ring, een lichte linnen pagina, foto''s als scheef opgehangen kiekjes, een grote gecentreerde quote en een schermvullend mobiel menu — warm en eerlijk, als een huiskamer. Test-thema.',
    'logo.svg',
    '[{"key":"a","label":"A — Bos & linnen"},{"key":"b","label":"B — Nacht & zand"},{"key":"c","label":"C — Leisteen & rozemarijn"}]',
    0
);
