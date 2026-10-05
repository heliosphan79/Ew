<?php
declare(strict_types=1);

// Minimal Google Analytics 4 Data API client for the admin Dashboard's
// visitor-stats card. No Google SDK/Composer package — just a hand-signed
// service-account JWT (RFC 7523) exchanged for a bearer token, then one
// batchRunReports call. Included only from admin/dashboard.php, not on
// every request.

const GA_CACHE_TTL_SECONDS = 1800; // 30 minuten — zie ga_fetch_report().

function ga_data_api_configured(array $config): bool
{
    $ga = $config['google_analytics'] ?? [];
    return !empty($ga['property_id'])
        && !empty($ga['service_account_email'])
        && !empty($ga['service_account_private_key']);
}

function ga_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Service-account JSON keys are downloaded with the private key's line
// breaks as literal "\n" inside a JSON string; copying just that string
// value into a single-quoted PHP string (the easy mistake to make) leaves
// those as literal backslash-n pairs instead of real newlines, which
// openssl then rejects — so normalize both forms.
function ga_normalize_private_key(string $key): string
{
    return str_replace('\\n', "\n", $key);
}

function ga_build_jwt(string $serviceAccountEmail, string $privateKeyPem): ?string
{
    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $serviceAccountEmail,
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    $signingInput = ga_base64url_encode((string) json_encode($header, JSON_UNESCAPED_SLASHES))
        . '.' . ga_base64url_encode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));

    $privateKey = openssl_pkey_get_private(ga_normalize_private_key($privateKeyPem));
    if ($privateKey === false) {
        return null;
    }

    $signature = '';
    $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$signed) {
        return null;
    }

    return $signingInput . '.' . ga_base64url_encode($signature);
}

// $error is filled with a short, human-readable reason on failure (shown
// on the admin Dashboard and written to error_log) — this step fails for
// very different reasons (malformed key vs. Google rejecting the request)
// that look identical as a plain null, so it's worth telling apart.
function ga_get_access_token(array $config, ?string &$error = null): ?string
{
    $ga = $config['google_analytics'];
    $jwt = ga_build_jwt((string) $ga['service_account_email'], (string) $ga['service_account_private_key']);
    if ($jwt === null) {
        $error = 'Kon de aanmeld-JWT niet signeren — controleer of service_account_private_key de volledige, onbeschadigde sleutel bevat.';
        error_log('GA: ' . $error);
        return null;
    }

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response)) {
        $error = 'Kon geen verbinding maken met Google (oauth2.googleapis.com): ' . $curlError;
        error_log('GA: ' . $error);
        return null;
    }
    if ($httpCode !== 200) {
        $error = 'Google wees de aanmelding af (HTTP ' . $httpCode . '): ' . substr($response, 0, 300);
        error_log('GA: ' . $error);
        return null;
    }
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['access_token'])) {
        $error = 'Onverwacht antwoord van Google bij het aanmelden.';
        error_log('GA: ' . $error . ' Response: ' . substr($response, 0, 300));
        return null;
    }
    return (string) $data['access_token'];
}

// One batchRunReports call combining the 7-day totals and the top-5 pages,
// instead of two separate requests. $error is filled with a short,
// human-readable reason on failure — see ga_get_access_token().
function ga_fetch_report_live(array $config, ?string &$error = null): ?array
{
    $propertyId = (string) ($config['google_analytics']['property_id'] ?? '');
    if ($propertyId === '') {
        $error = 'Geen property_id ingesteld.';
        return null;
    }

    $token = ga_get_access_token($config, $error);
    if ($token === null) {
        return null;
    }

    $body = json_encode([
        'requests' => [
            [
                'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
            ],
            [
                'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
                'dimensions' => [['name' => 'pagePath']],
                'metrics' => [['name' => 'screenPageViews']],
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                'limit' => 5,
            ],
        ],
    ]);

    $ch = curl_init(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($propertyId) . ':batchRunReports'
    );
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response)) {
        $error = 'Kon geen verbinding maken met Google (analyticsdata.googleapis.com): ' . $curlError;
        error_log('GA: ' . $error);
        return null;
    }
    if ($httpCode !== 200) {
        $error = 'Google wees de aanvraag af (HTTP ' . $httpCode . '): ' . substr($response, 0, 300);
        error_log('GA: ' . $error);
        return null;
    }
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['reports'][0], $data['reports'][1])) {
        $error = 'Onverwacht antwoord van Google bij het ophalen van het rapport.';
        error_log('GA: ' . $error . ' Response: ' . substr($response, 0, 300));
        return null;
    }

    $summary = $data['reports'][0];
    $topPagesReport = $data['reports'][1];

    $pages = [];
    foreach ((array) ($topPagesReport['rows'] ?? []) as $row) {
        $pages[] = [
            'path' => (string) ($row['dimensionValues'][0]['value'] ?? ''),
            'views' => (int) ($row['metricValues'][0]['value'] ?? 0),
        ];
    }

    return [
        'active_users_7d' => (int) ($summary['rows'][0]['metricValues'][0]['value'] ?? 0),
        'page_views_7d' => (int) ($summary['rows'][0]['metricValues'][1]['value'] ?? 0),
        'top_pages' => $pages,
    ];
}

// Returns one of:
//   ['status' => 'not_configured']
//   ['status' => 'ok', 'data' => [...], 'fetched_at' => 'Y-m-d H:i:s']
//   ['status' => 'stale', 'data' => [...], 'fetched_at' => 'Y-m-d H:i:s', 'error_detail' => string]  — API call failed, showing a recent cached report instead
//   ['status' => 'error', 'error_detail' => string]  — API call failed and no cached report to fall back on
function ga_fetch_report(array $config, mysqli $mysqli, bool $forceRefresh = false): array
{
    if (!ga_data_api_configured($config)) {
        return ['status' => 'not_configured'];
    }

    $cached = $mysqli->query('SELECT payload, fetched_at FROM analytics_cache WHERE id = 1')->fetch_assoc();

    if (!$forceRefresh && $cached) {
        $ageSeconds = time() - strtotime($cached['fetched_at']);
        if ($ageSeconds < GA_CACHE_TTL_SECONDS) {
            $decoded = json_decode($cached['payload'], true);
            if (is_array($decoded)) {
                return ['status' => 'ok', 'data' => $decoded, 'fetched_at' => $cached['fetched_at']];
            }
        }
    }

    $report = ga_fetch_report_live($config, $liveError);
    if ($report === null) {
        if ($cached) {
            $decoded = json_decode($cached['payload'], true);
            if (is_array($decoded)) {
                return ['status' => 'stale', 'data' => $decoded, 'fetched_at' => $cached['fetched_at'], 'error_detail' => $liveError];
            }
        }
        return ['status' => 'error', 'error_detail' => $liveError];
    }

    $payload = (string) json_encode($report, JSON_UNESCAPED_UNICODE);
    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare(
        'INSERT INTO analytics_cache (id, payload, fetched_at) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = VALUES(fetched_at)'
    );
    $stmt->bind_param('ss', $payload, $now);
    $stmt->execute();
    $stmt->close();

    return ['status' => 'ok', 'data' => $report, 'fetched_at' => $now];
}
