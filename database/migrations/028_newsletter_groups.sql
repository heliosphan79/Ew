-- Groepen voor nieuwsbrief-abonnees, zodat verschillende nieuwsbrieven naar
-- verschillende doelgroepen kunnen. Een abonnee kan in meerdere groepen
-- zitten; een nieuwsbrief gaat naar "alle actieve abonnees" of naar de
-- unie van een of meer gekozen groepen (iemand die in meerdere gekozen
-- groepen zit, krijgt de mail één keer).
CREATE TABLE newsletter_groups (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- UNIQUE met de standaardcollatie = niet hoofdlettergevoelig, dus
    -- "Counseling" en "counseling" zijn dezelfde groep (ook bij CSV-import).
    name       VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE newsletter_subscriber_groups (
    subscriber_id INT UNSIGNED NOT NULL,
    group_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (subscriber_id, group_id),
    KEY idx_group (group_id),
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES newsletter_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doelgroep per nieuwsbrief. Bewust een expliciete 'audience'-vlag i.p.v.
-- "geen doelgroep-rijen = iedereen": werd een gekozen groep later
-- verwijderd, dan zou een nieuwsbrief anders stilletjes naar alle
-- abonnees gaan. Met audience = 'groups' en geen enkele (resterende)
-- groep wordt er juist niet verstuurd.
-- Bestaande nieuwsbrieven blijven 'all' — zo zijn ze ook verstuurd.
ALTER TABLE newsletters
    ADD COLUMN audience ENUM('all', 'groups') NOT NULL DEFAULT 'all' AFTER status;

CREATE TABLE newsletter_target_groups (
    newsletter_id INT UNSIGNED NOT NULL,
    group_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (newsletter_id, group_id),
    KEY idx_group (group_id),
    FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES newsletter_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
