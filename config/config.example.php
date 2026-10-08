<?php
// Copy this file to config.php and fill in your real hosting credentials.
// config.php is gitignored on purpose — never commit real database credentials.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'eigenwijzer_cms',
        'user' => 'db_username',
        'pass' => 'db_password',
    ],
    'site' => [
        'name' => 'Eigen-Wijzer',
        'url'  => 'https://eigen-wijzer.be',
    ],

    // Google Analytics 4. Leave measurement_id empty to disable tracking
    // entirely (no script is loaded, no consent banner is shown).
    //
    // measurement_id: the GA4 "G-XXXXXXXXXX" tag, found in GA4 Admin →
    //   Data Streams → your web stream.
    //
    // The property_id/service_account_* fields are only needed for the
    // visitor-stats card on the admin Dashboard (optional — tracking works
    // without it, you'd just use analytics.google.com directly instead).
    // To set that up:
    //   1. In Google Cloud Console, create a project and enable the
    //      "Google Analytics Data API".
    //   2. Create a service account, then generate a JSON key for it.
    //   3. In GA4 Admin → Property Access Management, add the service
    //      account's email as a Viewer on your property.
    //   4. property_id is the numeric GA4 property ID (GA4 Admin → Property
    //      Settings — NOT the "G-" measurement ID).
    //   5. service_account_email and service_account_private_key come from
    //      the downloaded JSON key file (its "client_email" and
    //      "private_key" fields — keep the \n line breaks in the key as-is).
    'google_analytics' => [
        'measurement_id' => '',
        'property_id' => '',
        'service_account_email' => '',
        'service_account_private_key' => '',
    ],

    // Google reCAPTCHA v3 (invisible spam scoring on the contact form).
    // Leave both empty to disable — the form still works, just without
    // this extra check. Register your domain at
    // https://www.google.com/recaptcha/admin to get these keys.
    'recaptcha' => [
        'site_key' => '',
        'secret_key' => '',
    ],

    // Legacy fallback only — e-mail is now configured via "Instellingen →
    // E-mail" in the beheerpaneel (host, poort, login, afzender, reply-to,
    // ontvanger), stored in the database. Any field left empty there falls
    // back to the matching field here, so this section only matters until
    // someone opens that admin page and saves it once. Leave host empty to
    // disable sending entirely (submissions are still saved either way).
    'smtp' => [
        'host' => '',
        'port' => 587,
        // 'tls' (STARTTLS, usually port 587), 'ssl' (implicit TLS, usually
        // port 465), or 'none' (unencrypted — only for a trusted local
        // relay, never over the public internet).
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        // Shown as the sender. Most providers require this to match (or be
        // closely related to) the authenticated mailbox, or they'll reject
        // or flag the message.
        'from_email' => '',
        'from_name' => 'Eigen-Wijzer website',
        // Where new contact-form submissions are sent. Defaults to the
        // e-mail set under Instellingen in the beheerpaneel if left empty.
        'to_email' => '',
    ],
];
