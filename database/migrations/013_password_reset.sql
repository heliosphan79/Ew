-- Zelfbedieningsherstel voor het (enige) beheerderswachtwoord: een SHA-256
-- hash van het token wordt bewaard (nooit het token zelf), met een
-- vervaldatum. Zie admin/forgot-password.php en admin/reset-password.php.
ALTER TABLE admin_users
    ADD COLUMN password_reset_token_hash VARCHAR(64) DEFAULT NULL AFTER password_hash,
    ADD COLUMN password_reset_expires DATETIME DEFAULT NULL AFTER password_reset_token_hash;
