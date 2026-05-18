<?php
declare(strict_types=1);

/**
 * v3.1 — Integrations overlay.
 *
 * Every integration value (OAuth client IDs/secrets, Cloudflare token + zones,
 * WhatsApp numbers, brand text, JWT secret, …) is persisted in the
 * `platform_settings` table so the admin can edit them from the dashboard
 * without touching `api/config.php`.
 *
 * On every request `bootstrap.php` calls `integrations_apply_overlay($CONFIG)`
 * which deep-merges the DB values onto whatever `config.php` returned.
 *
 * Keys are dotted (e.g. `oauth.google.client_id`) so they round-trip neatly
 * through a flat key/value table.
 *
 * Sensitive keys are listed in `integrations_sensitive_keys()`. The admin
 * read-endpoint masks them on the wire ("••••1234") so we never expose a
 * secret over JSON. Writers may pass an empty string to leave the value
 * untouched, or pass a new value to overwrite.
 */

/** Every integration key the admin UI knows about. */
function integrations_known_keys(): array
{
    return [
        // Site / branding
        'site.url',
        'brand.name',
        'brand.tagline',

        // OAuth
        'oauth.google.client_id',
        'oauth.google.client_secret',
        'oauth.facebook.client_id',
        'oauth.facebook.client_secret',
        'oauth.github.client_id',
        'oauth.github.client_secret',

        // Cloudflare
        'cloudflare.api_token',
        'cloudflare.zones.institution_bd',
        'cloudflare.zones.smartschool_bd',
        'cloudflare.target_type',
        'cloudflare.target_value',
        'cloudflare.proxied',

        // WhatsApp
        'whatsapp.support_number',
        'whatsapp.support_prefilled_message',
        'whatsapp.community_url',
        'whatsapp.community_title',
        'whatsapp.community_subtitle',

        // Auth / security
        'jwt.secret',
    ];
}

/** Keys we never echo back as plaintext over the API. */
function integrations_sensitive_keys(): array
{
    return [
        'oauth.google.client_secret',
        'oauth.facebook.client_secret',
        'oauth.github.client_secret',
        'cloudflare.api_token',
        'jwt.secret',
    ];
}

/** Mask a secret so the admin can confirm "yes, something's stored" without
 *  ever leaking the real value. */
function integrations_mask(string $val): string
{
    $len = strlen($val);
    if ($len === 0) return '';
    if ($len <= 4) return str_repeat('•', $len);
    $tail = substr($val, -4);
    return str_repeat('•', max(4, min(20, $len - 4))) . $tail;
}

/** Read every override row from the DB. Returns ['dotted.key' => 'value']. */
function integrations_read_all(array $CONFIG): array
{
    try {
        $rows = db($CONFIG)->query(
            "SELECT skey, svalue FROM platform_settings WHERE skey LIKE 'integrations.%'"
        )->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $key = (string)$r['skey'];
        // Strip the "integrations." namespace prefix for callers.
        if (strncmp($key, 'integrations.', 13) === 0) {
            $out[substr($key, 13)] = (string)($r['svalue'] ?? '');
        }
    }
    return $out;
}

/** Persist a single override. Empty-string deletes the row. */
function integrations_set_one(array $CONFIG, string $key, string $value): void
{
    $pdo = db($CONFIG);
    $now = db_now($CONFIG);
    $stored = 'integrations.' . $key;
    if ($value === '') {
        $pdo->prepare('DELETE FROM platform_settings WHERE skey = ?')->execute([$stored]);
        return;
    }
    if (db_is_mysql($CONFIG)) {
        $pdo->prepare(
            'INSERT INTO platform_settings (skey, svalue, updated_at) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = VALUES(updated_at)'
        )->execute([$stored, $value, $now]);
    } else {
        $pdo->prepare(
            'INSERT INTO platform_settings (skey, svalue, updated_at) VALUES (?,?,?)
             ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue, updated_at = excluded.updated_at'
        )->execute([$stored, $value, $now]);
    }
}

/**
 * Deep-merge the stored DB overrides onto $CONFIG. Mutates the array
 * in-place. Called from bootstrap.php on every request.
 *
 * Keys that map cleanly onto config.php paths are written as nested arrays
 * so the existing helpers (cf_enabled, oauth_enabled_providers, etc.) keep
 * working without modification.
 */
function integrations_apply_overlay(array &$CONFIG): void
{
    $over = integrations_read_all($CONFIG);
    if (!$over) return;

    $set = function (string $dotted, $value) use (&$CONFIG) {
        $parts = explode('.', $dotted);
        $cur = &$CONFIG;
        foreach ($parts as $i => $p) {
            if ($i === count($parts) - 1) {
                $cur[$p] = $value;
                return;
            }
            if (!isset($cur[$p]) || !is_array($cur[$p])) $cur[$p] = [];
            $cur = &$cur[$p];
        }
    };

    foreach ($over as $key => $val) {
        switch ($key) {
            // -- Site / branding --
            case 'site.url':       $set('site_url', $val); break;
            case 'brand.name':     $set('brand_name', $val); break;
            case 'brand.tagline':  $set('brand_tagline', $val); break;

            // -- OAuth providers --
            case 'oauth.google.client_id':       $set('oauth.google.client_id', $val); break;
            case 'oauth.google.client_secret':   $set('oauth.google.client_secret', $val); break;
            case 'oauth.facebook.client_id':     $set('oauth.facebook.client_id', $val); break;
            case 'oauth.facebook.client_secret': $set('oauth.facebook.client_secret', $val); break;
            case 'oauth.github.client_id':       $set('oauth.github.client_id', $val); break;
            case 'oauth.github.client_secret':   $set('oauth.github.client_secret', $val); break;

            // -- Cloudflare --
            case 'cloudflare.api_token':           $set('cloudflare.api_token', $val); break;
            case 'cloudflare.zones.institution_bd':
                // Brand key contains a dot itself, so set the leaf directly.
                if (!isset($CONFIG['cloudflare']) || !is_array($CONFIG['cloudflare'])) $CONFIG['cloudflare'] = [];
                if (!isset($CONFIG['cloudflare']['zones']) || !is_array($CONFIG['cloudflare']['zones'])) $CONFIG['cloudflare']['zones'] = [];
                $CONFIG['cloudflare']['zones']['institution.bd'] = $val;
                break;
            case 'cloudflare.zones.smartschool_bd':
                if (!isset($CONFIG['cloudflare']) || !is_array($CONFIG['cloudflare'])) $CONFIG['cloudflare'] = [];
                if (!isset($CONFIG['cloudflare']['zones']) || !is_array($CONFIG['cloudflare']['zones'])) $CONFIG['cloudflare']['zones'] = [];
                $CONFIG['cloudflare']['zones']['smartschool.bd'] = $val;
                break;
            case 'cloudflare.target_type':         $set('cloudflare.target_type', $val); break;
            case 'cloudflare.target_value':        $set('cloudflare.target_value', $val); break;
            case 'cloudflare.proxied':
                $set('cloudflare.proxied', in_array(strtolower($val), ['1','true','yes','on'], true));
                break;

            // -- WhatsApp --
            case 'whatsapp.support_number':            $set('whatsapp.support_number', $val); break;
            case 'whatsapp.support_prefilled_message': $set('whatsapp.support_prefilled_message', $val); break;
            case 'whatsapp.community_url':             $set('whatsapp.community_url', $val); break;
            case 'whatsapp.community_title':           $set('whatsapp.community_title', $val); break;
            case 'whatsapp.community_subtitle':        $set('whatsapp.community_subtitle', $val); break;

            // -- Auth / security --
            case 'jwt.secret':
                if ($val !== '') $set('jwt_secret', $val);
                break;
        }
    }
}

/**
 * Build the JSON payload for GET /api/admin/integrations. Echoes a
 * `value` (masked for secrets), an `is_set` flag, plus a few computed
 * runtime hints (oauth redirect URIs, cloudflare-configured-per-brand)
 * so the admin UI can render guidance.
 */
function integrations_admin_view(array $CONFIG): array
{
    $stored = integrations_read_all($CONFIG);
    $sensitive = array_flip(integrations_sensitive_keys());

    $values = [];
    foreach (integrations_known_keys() as $k) {
        $raw = (string)($stored[$k] ?? '');
        // Fall back to whatever the loaded $CONFIG currently has — that way an
        // admin who hasn't saved anything yet still sees the values from
        // config.php (and can choose to override them). Sensitive values are
        // masked either way.
        if ($raw === '') {
            $raw = (string)integrations_config_lookup($CONFIG, $k);
        }
        $isSensitive = isset($sensitive[$k]);
        $values[$k] = [
            'is_set'  => $raw !== '',
            'value'   => $isSensitive ? integrations_mask($raw) : $raw,
            'masked'  => $isSensitive,
        ];
    }

    // Computed redirect URIs each provider must whitelist.
    $base = rtrim((string)($CONFIG['site_url'] ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $redirects = [
        'google'   => $base . '/api/auth/oauth/google/callback',
        'facebook' => $base . '/api/auth/oauth/facebook/callback',
        'github'   => $base . '/api/auth/oauth/github/callback',
    ];

    // Cloudflare zone status, per brand.
    $cfStatus = [];
    foreach (($CONFIG['brands'] ?? []) as $b) {
        $cfStatus[$b] = cf_enabled($CONFIG, $b);
    }

    return [
        'values'    => $values,
        'redirects' => $redirects,
        'cloudflare_status' => $cfStatus,
        'site_url'  => $base,
    ];
}

/** Look up a dotted integration key against the live $CONFIG array. */
function integrations_config_lookup(array $CONFIG, string $key)
{
    $map = [
        'site.url'                              => ['site_url'],
        'brand.name'                            => ['brand_name'],
        'brand.tagline'                         => ['brand_tagline'],
        'oauth.google.client_id'                => ['oauth','google','client_id'],
        'oauth.google.client_secret'            => ['oauth','google','client_secret'],
        'oauth.facebook.client_id'              => ['oauth','facebook','client_id'],
        'oauth.facebook.client_secret'          => ['oauth','facebook','client_secret'],
        'oauth.github.client_id'                => ['oauth','github','client_id'],
        'oauth.github.client_secret'            => ['oauth','github','client_secret'],
        'cloudflare.api_token'                  => ['cloudflare','api_token'],
        'cloudflare.zones.institution_bd'       => ['cloudflare','zones','institution.bd'],
        'cloudflare.zones.smartschool_bd'       => ['cloudflare','zones','smartschool.bd'],
        'cloudflare.target_type'                => ['cloudflare','target_type'],
        'cloudflare.target_value'               => ['cloudflare','target_value'],
        'cloudflare.proxied'                    => ['cloudflare','proxied'],
        'whatsapp.support_number'               => ['whatsapp','support_number'],
        'whatsapp.support_prefilled_message'    => ['whatsapp','support_prefilled_message'],
        'whatsapp.community_url'                => ['whatsapp','community_url'],
        'whatsapp.community_title'              => ['whatsapp','community_title'],
        'whatsapp.community_subtitle'           => ['whatsapp','community_subtitle'],
        'jwt.secret'                            => ['jwt_secret'],
    ];
    if (!isset($map[$key])) return '';
    $cur = $CONFIG;
    foreach ($map[$key] as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return '';
        $cur = $cur[$p];
    }
    if (is_bool($cur)) return $cur ? '1' : '0';
    return (string)$cur;
}

/**
 * Quick connection test for the saved Cloudflare token: list zones the
 * token can see. Returns a small payload the admin UI can render.
 */
function integrations_cloudflare_test(array $CONFIG): array
{
    $cf = $CONFIG['cloudflare'] ?? [];
    $token = trim((string)($cf['api_token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'message' => 'No Cloudflare API token saved yet.'];
    }
    $r = cf_request($token, 'GET', 'https://api.cloudflare.com/client/v4/user/tokens/verify');
    if (!$r['ok']) {
        $msg = 'Cloudflare rejected the token.';
        if (isset($r['body']['errors'][0]['message'])) {
            $msg = 'Cloudflare: ' . $r['body']['errors'][0]['message'];
        }
        return ['ok' => false, 'message' => $msg, 'http' => $r['http']];
    }
    // Pull zone list for completeness.
    $zr = cf_request($token, 'GET',
        'https://api.cloudflare.com/client/v4/zones?per_page=50&status=active');
    $zones = [];
    if ($zr['ok'] && is_array($zr['body']['result'] ?? null)) {
        foreach ($zr['body']['result'] as $z) {
            $zones[] = ['name' => (string)$z['name'], 'id' => (string)$z['id']];
        }
    }
    return [
        'ok'      => true,
        'message' => 'Token verified.',
        'status'  => (string)($r['body']['result']['status'] ?? 'active'),
        'zones'   => $zones,
    ];
}
