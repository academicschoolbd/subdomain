<?php
declare(strict_types=1);

/** Phone normalization, OTP, JWT, current-user helpers. */

function normalize_phone(string $phone): string
{
    $phone = trim(str_replace([' ', '-'], '', $phone));
    if ($phone === '') return '';
    if ($phone[0] === '+') return $phone;
    if (str_starts_with($phone, '88')) return '+' . $phone;
    if (str_starts_with($phone, '0')) return '+88' . $phone;
    return $phone;
}

function is_valid_bd_phone(string $phone): bool
{
    $p = normalize_phone($phone);
    if (!str_starts_with($p, '+880')) return false;
    $rest = substr($p, 4);
    return strlen($rest) === 10 && $rest[0] === '1' && ctype_digit($rest);
}

/**
 * v5pro — validate an ISO `YYYY-MM-DD` date-of-birth string.
 *
 * Accepts only strict `YYYY-MM-DD` (no time component, no slashes), confirms
 * the string round-trips through DateTimeImmutable (so e.g. 1995-02-30 is
 * rejected), is on or after 1900-01-01, is not in the future, and represents
 * an age of at least 5 years (sanity bound — toddlers don't claim domains).
 */
function is_valid_dob(string $dob): bool
{
    $dob = trim($dob);
    if ($dob === '') return false;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
    if ($d === false) return false;
    if ($d->format('Y-m-d') !== $dob) return false;
    $min = new DateTimeImmutable('1900-01-01');
    if ($d < $min) return false;
    $today = new DateTimeImmutable('today');
    if ($d > $today) return false;
    $ageYears = (int)$today->diff($d)->y;
    if ($ageYears < 5) return false;
    return true;
}

function generate_otp(): string
{
    $code = (string)random_int(0, 999999);
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function create_otp(array $CONFIG, string $phone): string
{
    $code = generate_otp();
    $expires = date('Y-m-d H:i:s', time() + 10 * 60);
    db($CONFIG)->prepare(
        'INSERT INTO otps (phone, code, expires_at, used, created_at) VALUES (?,?,?,?,?)'
    )->execute([$phone, $code, $expires, 0, db_now($CONFIG)]);
    return $code;
}

function verify_otp(array $CONFIG, string $phone, string $code): bool
{
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare(
        'SELECT id, expires_at FROM otps WHERE phone = ? AND code = ? AND used = 0 ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$phone, $code]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if (strtotime($row['expires_at']) < time()) return false;
    $pdo->prepare('UPDATE otps SET used = 1 WHERE id = ?')->execute([$row['id']]);
    return true;
}

/* ----- JWT (HS256) ----- */
function b64url_encode(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_decode(string $s): string
{
    $r = strlen($s) % 4;
    if ($r) $s .= str_repeat('=', 4 - $r);
    return base64_decode(strtr($s, '-_', '+/'));
}

function jwt_issue(array $CONFIG, int $userId, bool $isAdmin): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload = [
        'sub' => (string)$userId,
        'is_admin' => $isAdmin,
        'iat' => $now,
        'exp' => $now + 60 * 60 * 24 * 14, // 14 days
    ];
    $h = b64url_encode(json_encode($header));
    $p = b64url_encode(json_encode($payload));
    $sig = b64url_encode(hash_hmac('sha256', "$h.$p", $CONFIG['jwt_secret'], true));
    return "$h.$p.$sig";
}

function jwt_decode(array $CONFIG, string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $sig] = $parts;
    $expected = b64url_encode(hash_hmac('sha256', "$h.$p", $CONFIG['jwt_secret'], true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(b64url_decode($p), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && $payload['exp'] < time()) return null;
    return $payload;
}

function read_bearer_token(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($hdr === '' && function_exists('apache_request_headers')) {
        $hdrs = apache_request_headers();
        foreach ($hdrs as $k => $v) {
            if (strcasecmp($k, 'authorization') === 0) { $hdr = $v; break; }
        }
    }
    if (!$hdr || !preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) {
        // v3.0 — for routes that need to be opened directly in a browser tab
        // (e.g. admin document download links and CSV exports), fall back to a
        // `?_t=…` query-string token. Only used as a *read* token; same JWT.
        $q = $_GET['_t'] ?? '';
        if (is_string($q) && $q !== '') return trim($q);
        return null;
    }
    return trim($m[1]);
}

/**
 * Fields that make up the v5pro user-profile gate before claiming a subdomain.
 *
 * SINGLE SOURCE OF TRUTH — propagated automatically through profile_is_complete()
 * and profile_missing_fields(), which are read by /api/auth/me, /api/auth/register,
 * /api/auth/login, /api/auth/reset-password, /api/auth/profile, and
 * /api/admin/users. The gate triplet is intentionally narrow (full name, mobile
 * number, date of birth) so brand-new users can clear it on the dedicated
 * /profile-complete page without filling the broader 7-field editor. The
 * historical institution / location / designation fields remain valid optional
 * columns on the profile patch endpoint and are still collected during the
 * claim wizard step 2 — they are simply not part of the gate.
 */
function profile_required_fields(): array
{
    return ['name', 'mobile', 'date_of_birth'];
}

/** True if the row contains every profile_required_fields() value (non-empty). */
function profile_is_complete(array $u): bool
{
    foreach (profile_required_fields() as $f) {
        $v = $u[$f] ?? '';
        if (!is_string($v)) $v = (string)$v;
        if (trim($v) === '') return false;
    }
    return true;
}

/** Which required profile fields are still missing (for client UI). */
function profile_missing_fields(array $u): array
{
    $out = [];
    foreach (profile_required_fields() as $f) {
        $v = $u[$f] ?? '';
        if (!is_string($v)) $v = (string)$v;
        if (trim($v) === '') $out[] = $f;
    }
    return $out;
}

/** Columns selected for the "me" payload. New profile cols may not exist
 *  on pre-migration DBs; we filter to the columns that are present. */
function _user_select_cols(array $CONFIG): string
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $all = ['id','phone','email','name','avatar_url','provider','is_admin',
            'mobile','date_of_birth','designation_bn','institution_name','division','district','upazila',
            'profile_completed_at'];
    $present = db_columns(db($CONFIG), 'users', db_is_mysql($CONFIG));
    $keep = array_values(array_intersect($all, $present));
    if (!$keep) $keep = ['id','phone','email','name','is_admin'];
    $cache = implode(', ', $keep);
    return $cache;
}

function current_user(array $CONFIG): ?array
{
    $token = read_bearer_token();
    if (!$token) return null;
    $payload = jwt_decode($CONFIG, $token);
    if (!$payload) return null;
    $cols = _user_select_cols($CONFIG);
    $stmt = db($CONFIG)->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([(int)$payload['sub']]);
    $user = $stmt->fetch();
    if (!$user) return null;
    $user['is_admin'] = (bool)$user['is_admin'];
    // Always surface profile flags for the frontend.
    $user['profile_complete'] = profile_is_complete($user);
    $user['profile_missing']  = profile_missing_fields($user);
    return $user;
}

/* =================================================================== */
/*  OAuth helpers                                                       */
/* =================================================================== */

/**
 * Provider table — Authorization URL, Token URL, Userinfo URL, default scopes.
 */
function oauth_providers(): array
{
    return [
        'google' => [
            'auth_url'  => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'user_url'  => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope'     => 'openid email profile',
            'label'     => 'Google',
        ],
        'facebook' => [
            'auth_url'  => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'user_url'  => 'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)',
            'scope'     => 'email,public_profile',
            'label'     => 'Facebook',
        ],
        'github' => [
            'auth_url'  => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url'  => 'https://api.github.com/user',
            'scope'     => 'read:user user:email',
            'label'     => 'GitHub',
        ],
    ];
}

function oauth_enabled_providers(array $CONFIG): array
{
    $out = [];
    foreach (oauth_providers() as $name => $meta) {
        $cid = trim((string)($CONFIG['oauth'][$name]['client_id'] ?? ''));
        if ($cid !== '') $out[$name] = $meta;
    }
    return $out;
}

function oauth_redirect_uri(array $CONFIG, string $provider): string
{
    $base = rtrim((string)($CONFIG['site_url'] ?? ''), '/');
    if ($base === '') {
        // Derive from request as a fallback.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    return $base . '/api/auth/oauth/' . $provider . '/callback';
}

function oauth_create_state(array $CONFIG, string $provider, ?string $redirect = null): string
{
    $state = bin2hex(random_bytes(24));
    $exp = date('Y-m-d H:i:s', time() + 10 * 60);
    db($CONFIG)->prepare(
        'INSERT INTO oauth_states (state, provider, redirect, expires_at, created_at) VALUES (?,?,?,?,?)'
    )->execute([$state, $provider, $redirect, $exp, db_now($CONFIG)]);
    return $state;
}

function oauth_consume_state(array $CONFIG, string $state, string $provider): ?array
{
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id, provider, redirect, expires_at FROM oauth_states WHERE state = ? LIMIT 1');
    $stmt->execute([$state]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $pdo->prepare('DELETE FROM oauth_states WHERE id = ?')->execute([$row['id']]);
    if ($row['provider'] !== $provider) return null;
    if (strtotime($row['expires_at']) < time()) return null;
    return $row;
}

/**
 * HTTP helper for OAuth token + userinfo exchanges. Returns
 * ['http' => int, 'body' => decoded array|null, 'raw' => string|null].
 */
function http_request(string $method, string $url, array $opts = []): array
{
    $ch = curl_init();
    $headers = $opts['headers'] ?? [];
    $headers[] = 'User-Agent: free-subdomain-platform/2.0';
    $body = $opts['body'] ?? null;
    $form = $opts['form'] ?? null;
    if ($form !== null) {
        $body = http_build_query($form);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        return ['http' => 0, 'body' => null, 'raw' => null];
    }
    $decoded = json_decode($resp, true);
    return ['http' => $code, 'body' => is_array($decoded) ? $decoded : null, 'raw' => $resp];
}

/**
 * Exchange OAuth `code` for an access token and fetch the user profile.
 * Returns a normalized profile:
 *   ['provider' => 'google', 'provider_id' => '...', 'email' => '...',
 *    'name' => '...', 'avatar_url' => '...'].
 * Returns null on failure.
 */
function oauth_fetch_profile(array $CONFIG, string $provider, string $code): ?array
{
    $providers = oauth_providers();
    if (!isset($providers[$provider])) return null;
    $cid = trim((string)($CONFIG['oauth'][$provider]['client_id'] ?? ''));
    $sec = trim((string)($CONFIG['oauth'][$provider]['client_secret'] ?? ''));
    if ($cid === '' || $sec === '') return null;
    $meta = $providers[$provider];

    $tokenForm = [
        'client_id'     => $cid,
        'client_secret' => $sec,
        'code'          => $code,
        'grant_type'    => 'authorization_code',
        'redirect_uri'  => oauth_redirect_uri($CONFIG, $provider),
    ];
    $tokenHeaders = ['Accept: application/json'];
    $tokenResp = http_request('POST', $meta['token_url'], [
        'form' => $tokenForm,
        'headers' => $tokenHeaders,
    ]);
    if ($tokenResp['http'] < 200 || $tokenResp['http'] >= 300) return null;
    $accessToken = $tokenResp['body']['access_token'] ?? null;
    if (!$accessToken) return null;

    // Fetch userinfo.
    $userResp = http_request('GET', $meta['user_url'], [
        'headers' => ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'],
    ]);
    if ($userResp['http'] < 200 || $userResp['http'] >= 300) return null;
    $u = $userResp['body'] ?? [];

    switch ($provider) {
        case 'google':
            return [
                'provider'    => 'google',
                'provider_id' => (string)($u['sub'] ?? ''),
                'email'       => (string)($u['email'] ?? ''),
                'name'        => (string)($u['name'] ?? ''),
                'avatar_url'  => (string)($u['picture'] ?? ''),
            ];
        case 'facebook':
            return [
                'provider'    => 'facebook',
                'provider_id' => (string)($u['id'] ?? ''),
                'email'       => (string)($u['email'] ?? ''),
                'name'        => (string)($u['name'] ?? ''),
                'avatar_url'  => (string)($u['picture']['data']['url'] ?? ''),
            ];
        case 'github':
            $email = (string)($u['email'] ?? '');
            if ($email === '') {
                // GitHub returns email separately when private.
                $emails = http_request('GET', 'https://api.github.com/user/emails', [
                    'headers' => ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'],
                ]);
                if (is_array($emails['body'] ?? null)) {
                    foreach ($emails['body'] as $e) {
                        if (!empty($e['primary']) && !empty($e['verified'])) {
                            $email = (string)$e['email']; break;
                        }
                    }
                    if ($email === '') {
                        foreach ($emails['body'] as $e) {
                            if (!empty($e['email'])) { $email = (string)$e['email']; break; }
                        }
                    }
                }
            }
            return [
                'provider'    => 'github',
                'provider_id' => (string)($u['id'] ?? ''),
                'email'       => $email,
                'name'        => (string)($u['name'] ?? $u['login'] ?? ''),
                'avatar_url'  => (string)($u['avatar_url'] ?? ''),
            ];
    }
    return null;
}

/**
 * Find an existing user by (provider, provider_id) or by email; otherwise
 * create one. Updates name/avatar/provider on every login.
 * Returns the user row.
 */
function oauth_upsert_user(array $CONFIG, array $profile): array
{
    $pdo = db($CONFIG);
    $now = db_now($CONFIG);
    $provider = $profile['provider'];
    $providerId = $profile['provider_id'];
    $email = trim((string)$profile['email']);
    $name = trim((string)$profile['name']);
    $avatar = trim((string)$profile['avatar_url']);

    // 1) provider + provider_id
    $stmt = $pdo->prepare('SELECT * FROM users WHERE provider = ? AND provider_id = ? LIMIT 1');
    $stmt->execute([$provider, $providerId]);
    $u = $stmt->fetch() ?: null;

    // 2) fall back to email
    if (!$u && $email !== '') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch() ?: null;
    }

    if (!$u) {
        // Optionally promote the configured admin email on first sign-in.
        $isAdmin = 0;
        $adminEmail = trim((string)($CONFIG['admin_email'] ?? ''));
        if ($adminEmail !== '' && strcasecmp($adminEmail, $email) === 0) {
            $isAdmin = 1;
        }
        $pdo->prepare(
            'INSERT INTO users (email, name, avatar_url, provider, provider_id, is_admin, created_at)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$email ?: null, $name ?: null, $avatar ?: null, $provider, $providerId, $isAdmin, $now]);
        $id = (int)$pdo->lastInsertId();
    } else {
        // Update missing fields and refresh display info.
        $id = (int)$u['id'];
        $pdo->prepare(
            'UPDATE users SET
                provider = COALESCE(NULLIF(provider, ""), ?),
                provider_id = COALESCE(NULLIF(provider_id, ""), ?),
                email = COALESCE(NULLIF(email, ""), ?),
                name  = CASE WHEN ? <> "" THEN ? ELSE name END,
                avatar_url = CASE WHEN ? <> "" THEN ? ELSE avatar_url END
             WHERE id = ?'
        )->execute([
            $provider, $providerId, $email ?: null,
            $name, $name ?: null,
            $avatar, $avatar ?: null,
            $id,
        ]);
        // If the configured admin email matches, promote.
        $adminEmail = trim((string)($CONFIG['admin_email'] ?? ''));
        if ($adminEmail !== '' && $email !== '' && strcasecmp($adminEmail, $email) === 0) {
            $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$id]);
        }
    }
    $cols = _user_select_cols($CONFIG);
    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    $user['is_admin'] = (bool)$user['is_admin'];
    $user['profile_complete'] = profile_is_complete($user);
    $user['profile_missing']  = profile_missing_fields($user);
    return $user;
}

function require_user(array $CONFIG): array
{
    $u = current_user($CONFIG);
    if (!$u) send_error('Missing or invalid token', 401);
    return $u;
}

function require_admin(array $CONFIG): array
{
    $u = require_user($CONFIG);
    if (!$u['is_admin']) send_error('Admin only', 403);
    return $u;
}
