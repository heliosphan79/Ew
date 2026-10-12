-- Huiskamer kreeg een lichte zijbalk, een ander menu en een spiraal-logo;
-- omschrijving en kleurschema-namen volgen. Draai dit enkel als 026 al
-- uitgevoerd is (op een verse install staat dit al goed via schema.sql).
UPDATE themes
SET description = 'Een vaste, lichte zijbalk met rustig menu en leesvoortgang-ring, een linnen pagina, foto''s als scheef opgehangen kiekjes, een grote gecentreerde quote, een schermvullend mobiel menu en een spiraal-logo — warm en eerlijk, als een huiskamer. Test-thema.',
    schemes = '[{"key":"a","label":"A — Crème & terracotta"},{"key":"b","label":"B — Bos & crème"},{"key":"c","label":"C — Terracotta & klei"}]'
WHERE slug = 'huiskamer';
