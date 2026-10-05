-- Eenrijige cache (id = 1) voor het laatst opgehaalde Google Analytics
-- Data API-rapport, getoond op het admin-Dashboard. Vermijdt dat elke
-- dashboardweergave opnieuw bij Google's API moet aankloppen (traag en
-- onderhevig aan rate limits) — zie includes/ga_client.php. Geen rij nodig
-- bij installatie: de eerste dashboardweergave na het instellen van
-- Analytics vult 'm aan.
CREATE TABLE IF NOT EXISTS analytics_cache (
    id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    payload    MEDIUMTEXT NOT NULL,
    fetched_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
