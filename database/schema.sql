-- Eigen-Wijzer CMS — database schema
-- Import this once via phpMyAdmin (or `mysql -u user -p dbname < schema.sql`)
-- before running the site for the first time.

CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug              VARCHAR(150) NOT NULL UNIQUE,
    title             VARCHAR(200) NOT NULL,
    -- JSON-encoded array of content blocks (text/image/quote/list/buttons) — see includes/functions.php render_blocks().
    content           MEDIUMTEXT NOT NULL,
    -- Which of the 4 frontend look-and-feel variants (a/b/c/d) this page renders with.
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
