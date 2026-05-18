<?php
declare(strict_types=1);

/**
 * Cloudflare DNS helper.
 *
 * Reads $CONFIG['cloudflare'] for credentials. If the token or the relevant
 * zone id is missing the helpers no-op gracefully so the platform still works
 * without DNS automation. The result array tells the caller what happened:
 *
 *   [
 *     'attempted' => true|false,   // did we make any HTTP call
 *     'ok'        => true|false,
 *     'record_id' => '...' | null,
 *     'message'   => human readable
 *   ]
 */

function cf_enabled(array $CONFIG, string $brand): bool
{
    $cf = $CONFIG['cloudflare'] ?? [];
    $token = trim((string)($cf['api_token'] ?? ''));
    $zone = trim((string)($cf['zones'][$brand] ?? ''));
    $target = trim((string)($cf['target_value'] ?? ''));
    return $token !== '' && $zone !== '' && $target !== '';
}

function cf_request(string $token, string $method, string $url, ?array $body = null): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['http' => 0, 'ok' => false, 'error' => $err, 'body' => null];
    }
    $decoded = json_decode($resp, true);
    return ['http' => $code, 'ok' => $code >= 200 && $code < 300, 'body' => $decoded, 'raw' => $resp];
}

function cf_create_record(array $CONFIG, string $brand, string $slug): array
{
    if (!cf_enabled($CONFIG, $brand)) {
        return ['attempted' => false, 'ok' => false, 'record_id' => null,
                'message' => 'Cloudflare not configured for ' . $brand];
    }
    $cf = $CONFIG['cloudflare'];
    $token = $cf['api_token'];
    $zoneId = $cf['zones'][$brand];
    $type = strtoupper($cf['target_type'] ?? 'A');
    $proxied = (bool)($cf['proxied'] ?? true);
    $body = [
        'type' => $type,
        'name' => $slug, // CF appends zone automatically when name is the label
        'content' => $cf['target_value'],
        'ttl' => 1,           // 1 = auto
        'proxied' => $proxied,
        'comment' => "free-subdomain-platform: tenant {$slug}.{$brand}",
    ];
    $r = cf_request($token, 'POST', "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", $body);
    if ($r['ok'] && isset($r['body']['result']['id'])) {
        return [
            'attempted' => true, 'ok' => true,
            'record_id' => $r['body']['result']['id'],
            'message' => 'DNS record created',
        ];
    }
    // If CF says the record already exists, try to find it and treat as success.
    $existing = cf_find_record($CONFIG, $brand, $slug);
    if ($existing['record_id'] !== null) {
        return ['attempted' => true, 'ok' => true, 'record_id' => $existing['record_id'],
                'message' => 'DNS record already existed'];
    }
    $msg = 'Cloudflare error';
    if (isset($r['body']['errors'][0]['message'])) {
        $msg = 'Cloudflare: ' . $r['body']['errors'][0]['message'];
    } elseif (!empty($r['error'])) {
        $msg = 'Cloudflare network: ' . $r['error'];
    }
    return ['attempted' => true, 'ok' => false, 'record_id' => null, 'message' => $msg];
}

function cf_find_record(array $CONFIG, string $brand, string $slug): array
{
    if (!cf_enabled($CONFIG, $brand)) {
        return ['attempted' => false, 'record_id' => null];
    }
    $cf = $CONFIG['cloudflare'];
    $zoneId = $cf['zones'][$brand];
    $fqdn = "{$slug}.{$brand}";
    $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records?name=" . urlencode($fqdn);
    $r = cf_request($cf['api_token'], 'GET', $url);
    $id = $r['body']['result'][0]['id'] ?? null;
    return ['attempted' => true, 'record_id' => $id];
}

function cf_delete_record(array $CONFIG, string $brand, ?string $recordId, ?string $slug = null): array
{
    if (!cf_enabled($CONFIG, $brand)) {
        return ['attempted' => false, 'ok' => false, 'message' => 'CF not configured'];
    }
    if (!$recordId && $slug) {
        $recordId = cf_find_record($CONFIG, $brand, $slug)['record_id'];
    }
    if (!$recordId) {
        return ['attempted' => true, 'ok' => true, 'message' => 'no record to delete'];
    }
    $cf = $CONFIG['cloudflare'];
    $zoneId = $cf['zones'][$brand];
    $r = cf_request($cf['api_token'], 'DELETE',
        "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$recordId}");
    return [
        'attempted' => true,
        'ok' => $r['ok'],
        'message' => $r['ok'] ? 'DNS record deleted' : 'CF delete failed',
    ];
}

/* ---------------------------------------------------------------- */
/*  v3.2 — generic owner-editable DNS records (any type)             */
/* ---------------------------------------------------------------- */

/**
 * Create an arbitrary DNS record on Cloudflare for a verified tenant.
 * The `name` parameter is the *label* (e.g. "@", "www", "mail" relative to
 * `<slug>.<brand>`). When CF isn't configured the helper no-ops with a
 * `manual` status so the row is still saved locally — that way the admin
 * can publish it to upstream DNS by hand.
 *
 * Returns:
 *   ['attempted' => bool, 'ok' => bool, 'record_id' => string|null,
 *    'status' => 'live'|'manual'|'error', 'message' => string]
 */
function cf_create_arbitrary_record(
    array $CONFIG,
    string $brand,
    string $slug,
    string $type,
    string $name,
    string $content,
    int $ttl = 1,
    ?int $priority = null,
    bool $proxied = false
): array {
    $type = strtoupper(trim($type));
    if (!cf_enabled($CONFIG, $brand)) {
        return ['attempted' => false, 'ok' => false, 'record_id' => null,
                'status' => 'manual', 'message' => 'Cloudflare not configured — record saved locally only.'];
    }
    $cf = $CONFIG['cloudflare'];
    $token = $cf['api_token'];
    $zoneId = $cf['zones'][$brand];
    // Build the FQDN: "@" / "" / the bare slug all collapse to the apex of
    // the tenant subdomain. Anything else is prefixed.
    $sub = $slug . '.' . $brand;
    $label = trim($name);
    $isApex = ($label === '' || $label === '@' || strcasecmp($label, $sub) === 0);
    $fqdn = $isApex ? $sub : ($label . '.' . $sub);

    // Cloudflare auto-proxies only A/AAAA/CNAME.
    $supportsProxy = in_array($type, ['A', 'AAAA', 'CNAME'], true);
    $body = [
        'type'    => $type,
        'name'    => $fqdn,
        'content' => $content,
        'ttl'     => $ttl > 0 ? $ttl : 1,
        'proxied' => $supportsProxy ? $proxied : false,
        'comment' => 'tenant ' . $sub . ' (owner-managed)',
    ];
    if ($type === 'MX' && $priority !== null) {
        $body['priority'] = $priority;
    }
    $r = cf_request($token, 'POST',
        "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", $body);
    if ($r['ok'] && isset($r['body']['result']['id'])) {
        return ['attempted' => true, 'ok' => true,
                'record_id' => (string)$r['body']['result']['id'],
                'status' => 'live', 'message' => 'DNS record created'];
    }
    $msg = 'Cloudflare error';
    if (isset($r['body']['errors'][0]['message'])) {
        $msg = 'Cloudflare: ' . $r['body']['errors'][0]['message'];
    } elseif (!empty($r['error'])) {
        $msg = 'Cloudflare network: ' . $r['error'];
    }
    return ['attempted' => true, 'ok' => false, 'record_id' => null,
            'status' => 'error', 'message' => $msg];
}

/** Update an existing record. Pass the same fields. */
function cf_update_arbitrary_record(
    array $CONFIG,
    string $brand,
    string $slug,
    string $recordId,
    string $type,
    string $name,
    string $content,
    int $ttl = 1,
    ?int $priority = null,
    bool $proxied = false
): array {
    $type = strtoupper(trim($type));
    if (!cf_enabled($CONFIG, $brand) || $recordId === '') {
        return ['attempted' => false, 'ok' => false, 'record_id' => $recordId ?: null,
                'status' => 'manual', 'message' => 'Cloudflare not configured — record updated locally only.'];
    }
    $cf = $CONFIG['cloudflare'];
    $token = $cf['api_token'];
    $zoneId = $cf['zones'][$brand];
    $sub = $slug . '.' . $brand;
    $label = trim($name);
    $isApex = ($label === '' || $label === '@' || strcasecmp($label, $sub) === 0);
    $fqdn = $isApex ? $sub : ($label . '.' . $sub);

    $supportsProxy = in_array($type, ['A', 'AAAA', 'CNAME'], true);
    $body = [
        'type'    => $type,
        'name'    => $fqdn,
        'content' => $content,
        'ttl'     => $ttl > 0 ? $ttl : 1,
        'proxied' => $supportsProxy ? $proxied : false,
        'comment' => 'tenant ' . $sub . ' (owner-managed)',
    ];
    if ($type === 'MX' && $priority !== null) {
        $body['priority'] = $priority;
    }
    $r = cf_request($token, 'PUT',
        "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$recordId}", $body);
    if ($r['ok']) {
        return ['attempted' => true, 'ok' => true, 'record_id' => $recordId,
                'status' => 'live', 'message' => 'DNS record updated'];
    }
    $msg = 'Cloudflare error';
    if (isset($r['body']['errors'][0]['message'])) {
        $msg = 'Cloudflare: ' . $r['body']['errors'][0]['message'];
    } elseif (!empty($r['error'])) {
        $msg = 'Cloudflare network: ' . $r['error'];
    }
    return ['attempted' => true, 'ok' => false, 'record_id' => $recordId,
            'status' => 'error', 'message' => $msg];
}
