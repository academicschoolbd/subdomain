<?php
declare(strict_types=1);

/** Auth routes: request-otp, verify-otp, me. */

function route_auth_request_otp(array $CONFIG): void
{
    $data = read_json_body();
    $phone = normalize_phone((string)require_param($data, 'phone'));
    if (!is_valid_bd_phone($phone)) {
        send_error('Enter a valid Bangladesh mobile number (e.g. 01712345678).');
    }
    // Light rate-limit: max 5 OTPs per phone per hour.
    $stmt = db($CONFIG)->prepare(
        "SELECT COUNT(*) c FROM otps WHERE phone = ? AND created_at > ?"
    );
    $stmt->execute([$phone, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$stmt->fetch()['c'] >= 5) {
        send_error('Too many OTP requests. Try again in an hour.', 429);
    }
    $code = create_otp($CONFIG, $phone);
    audit($CONFIG, null, null, 'auth.request_otp', json_encode(['phone' => $phone]));
    $resp = ['ok' => true, 'phone' => $phone, 'expires_in_seconds' => 600];
    if (!empty($CONFIG['demo_mode'])) {
        $resp['dev_code'] = $code; // shown only in demo mode for testing
        $resp['demo_note'] = 'Demo mode: code returned in response. Set demo_mode = false in config.php for production and wire SMS gateway.';
    }
    send_json($resp);
}

function route_auth_verify_otp(array $CONFIG): void
{
    $data = read_json_body();
    $phone = normalize_phone((string)require_param($data, 'phone'));
    $code = (string)require_param($data, 'code');
    if (!verify_otp($CONFIG, $phone, $code)) {
        send_error('Invalid or expired code.', 401);
    }
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id, phone, email, name, is_admin FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    if (!$user) {
        $pdo->prepare(
            'INSERT INTO users (phone, is_admin, created_at) VALUES (?,?,?)'
        )->execute([$phone, 0, db_now($CONFIG)]);
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
    }
    $user['is_admin'] = (bool)$user['is_admin'];
    $token = jwt_issue($CONFIG, (int)$user['id'], $user['is_admin']);
    audit($CONFIG, (int)$user['id'], null, 'auth.login');
    send_json(['ok' => true, 'token' => $token, 'user' => $user]);
}

function route_auth_me(array $CONFIG): void
{
    $u = require_user($CONFIG);
    send_json(['user' => $u]);
}

/* =================================================================== */
/*  Email + password auth (fallback when OAuth is unavailable)           */
/* =================================================================== */

/** Normalize emails for lookup: trim + lowercase. */
function _norm_email(string $e): string
{
    return strtolower(trim($e));
}

/** Basic email format check — RFC-grade is overkill for sign-up. */
function _is_valid_email(string $e): bool
{
    return (bool)filter_var($e, FILTER_VALIDATE_EMAIL);
}

/**
 * POST /api/auth/register
 * Body: { name, email, password, mobile? }
 * Creates an account and returns { ok, token, user }.
 */
function route_auth_register(array $CONFIG): void
{
    // v3.3 — admins can disable manual email/password sign-up entirely.
    // Existing accounts can still log in and OAuth providers still work,
    // but new self-service registrations are blocked.
    if (!settings_get_bool($CONFIG, 'email_registration_enabled', true)) {
        send_json([
            'ok' => false,
            'errors' => ['email' => 'Manual sign-up is currently disabled. Please use one of the social providers, or contact the admin.'],
            'detail' => 'Manual email registration is disabled.',
        ], 403);
    }
    $data = read_json_body();
    $name     = trim((string)($data['name'] ?? ''));
    $email    = _norm_email((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $mobile   = trim((string)($data['mobile'] ?? ''));

    $errors = [];
    if ($name === '')                            $errors['name']     = 'Please enter your name.';
    if ($email === '')                           $errors['email']    = 'Email is required.';
    elseif (!_is_valid_email($email))            $errors['email']    = 'That email looks invalid.';
    if (strlen($password) < 8)                   $errors['password'] = 'Password must be at least 8 characters.';
    if ($mobile !== '' && !is_valid_bd_phone($mobile)) {
        $errors['mobile'] = 'Enter a valid Bangladesh mobile or leave blank.';
    }
    if ($errors) {
        send_json(['ok' => false, 'errors' => $errors], 422);
    }

    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch() ?: null;

    if ($existing && !empty($existing['password_hash'])) {
        send_json([
            'ok' => false,
            'errors' => ['email' => 'An account with this email already exists. Try signing in instead.'],
        ], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $mobileNorm = $mobile !== '' ? normalize_phone($mobile) : null;
    $now = db_now($CONFIG);

    if ($existing) {
        // OAuth-only account — attach a password so the user can sign in by email too.
        $pdo->prepare(
            'UPDATE users
                SET password_hash = ?,
                    name = COALESCE(NULLIF(name, ""), ?),
                    mobile = COALESCE(NULLIF(mobile, ""), ?)
              WHERE id = ?'
        )->execute([$hash, $name, $mobileNorm, (int)$existing['id']]);
        $userId = (int)$existing['id'];
    } else {
        $isAdmin = 0;
        $adminEmail = _norm_email((string)($CONFIG['admin_email'] ?? ''));
        if ($adminEmail !== '' && $adminEmail === $email) {
            $isAdmin = 1;
        }
        $pdo->prepare(
            'INSERT INTO users (email, name, mobile, password_hash, provider, is_admin, created_at)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$email, $name, $mobileNorm, $hash, 'email', $isAdmin, $now]);
        $userId = (int)$pdo->lastInsertId();
    }

    $cols = _user_select_cols($CONFIG);
    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $user['is_admin'] = (bool)$user['is_admin'];
    $user['profile_complete'] = profile_is_complete($user);
    $user['profile_missing']  = profile_missing_fields($user);

    $token = jwt_issue($CONFIG, $userId, $user['is_admin']);
    audit($CONFIG, $userId, null, 'auth.register.email');
    send_json(['ok' => true, 'token' => $token, 'user' => $user]);
}

/**
 * POST /api/auth/login
 * Body: { email, password }
 * Returns { ok, token, user } or 401.
 */
function route_auth_login(array $CONFIG): void
{
    $data = read_json_body();
    $email    = _norm_email((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($email === '' || $password === '') {
        send_json(['ok' => false, 'errors' => ['email' => 'Enter your email and password.']], 422);
    }

    // v3.0 — sliding-window brute-force throttle: max 8 failed attempts per
    // (email + IP) in 15 minutes. Successful logins reset the counter for
    // that email so a forgotten password followed by a correct one isn't
    // punished.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (auth_throttle_blocked($CONFIG, $email, $ip)) {
        send_json([
            'ok' => false,
            'errors' => ['password' => 'Too many failed attempts. Please wait 15 minutes or reset your password.'],
        ], 429);
    }

    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id, password_hash, is_admin FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch() ?: null;

    if (!$row || empty($row['password_hash']) || !password_verify($password, (string)$row['password_hash'])) {
        auth_throttle_record($CONFIG, $email, $ip, false);
        // Constant-ish response — same shape whether the email is unknown or
        // the password is wrong, so attackers can't enumerate accounts.
        send_json(['ok' => false, 'errors' => ['password' => 'Incorrect email or password.']], 401);
    }
    auth_throttle_record($CONFIG, $email, $ip, true);

    $userId = (int)$row['id'];
    $isAdmin = (bool)$row['is_admin'];
    $cols = _user_select_cols($CONFIG);
    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $user['is_admin'] = (bool)$user['is_admin'];
    $user['profile_complete'] = profile_is_complete($user);
    $user['profile_missing']  = profile_missing_fields($user);

    $token = jwt_issue($CONFIG, $userId, $isAdmin);
    audit($CONFIG, $userId, null, 'auth.login.email');
    send_json(['ok' => true, 'token' => $token, 'user' => $user]);
}

/* ---------- v3.0: brute-force throttle helpers (login_throttle table) ---------- */

function auth_throttle_record(array $CONFIG, string $ident, string $ip, bool $success): void
{
    try {
        db($CONFIG)->prepare(
            'INSERT INTO login_throttle (ident, ip, success, created_at) VALUES (?,?,?,?)'
        )->execute([substr($ident, 0, 190), $ip ?: null, $success ? 1 : 0, db_now($CONFIG)]);
        if ($success) {
            // Successful login wipes the recent-failure trail for that email.
            db($CONFIG)->prepare(
                'DELETE FROM login_throttle WHERE ident = ? AND success = 0'
            )->execute([$ident]);
        }
    } catch (PDOException $e) { /* non-fatal */ }
}

function auth_throttle_blocked(array $CONFIG, string $ident, string $ip): bool
{
    $cutoff = date('Y-m-d H:i:s', time() - 15 * 60);
    try {
        $stmt = db($CONFIG)->prepare(
            'SELECT COUNT(*) c FROM login_throttle
              WHERE success = 0 AND created_at > ?
                AND (ident = ? OR (ip IS NOT NULL AND ip = ?))'
        );
        $stmt->execute([$cutoff, $ident, $ip]);
        return (int)$stmt->fetch()['c'] >= 8;
    } catch (PDOException $e) {
        return false;
    }
}

/* ---------- v3.0: password-reset routes ---------- */

/**
 * POST /api/auth/forgot-password
 * Body: { email }
 * Always returns ok:true (never reveals whether the address is registered).
 * In demo_mode, the reset URL + token are returned in the response so admins
 * can test without an SMTP gateway. In production, plug an SMTP/transactional
 * mail provider here and email the link.
 */
function route_auth_forgot_password(array $CONFIG): void
{
    $data = read_json_body();
    $email = _norm_email((string)($data['email'] ?? ''));
    if ($email === '' || !_is_valid_email($email)) {
        // Same response shape regardless — don't enumerate accounts.
        send_json(['ok' => true]);
    }
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch() ?: null;

    $resp = ['ok' => true];
    if ($row) {
        $token = bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $exp = date('Y-m-d H:i:s', time() + 60 * 60); // 60 minutes
        $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, used, created_at) VALUES (?,?,?,?,?)'
        )->execute([(int)$row['id'], $hash, $exp, 0, db_now($CONFIG)]);
        audit($CONFIG, (int)$row['id'], null, 'auth.forgot_password');

        $base = rtrim((string)($CONFIG['site_url'] ?? ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $resetUrl = $base . '/reset-password.php?token=' . $token;
        $brand    = (string)($CONFIG['brand_name'] ?? 'institution.bd');

        // v3.2 — actually send the email when SMTP / mail() is configured.
        // The transport is picked automatically by mail_send().
        $sent = mail_send($CONFIG, [
            'to'      => $email,
            'subject' => 'Reset your ' . $brand . ' password',
            'text'    => "Hi,\n\n"
                       . "We received a request to reset your $brand password. Click the link below within 60 minutes to choose a new one:\n\n"
                       . $resetUrl . "\n\n"
                       . "If you didn't request this, you can safely ignore this message — your password won't change.\n\n"
                       . "— $brand",
            'html'    => '<p>Hi,</p>'
                       . '<p>We received a request to reset your <strong>' . htmlspecialchars($brand) . '</strong> password. Click the button below within 60 minutes to choose a new one:</p>'
                       . '<p><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;padding:10px 18px;background:#0f766e;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Reset password</a></p>'
                       . '<p style="font-size:.85rem;color:#64748b;">Or copy this URL: <code>' . htmlspecialchars($resetUrl) . '</code></p>'
                       . '<p style="font-size:.85rem;color:#64748b;">If you didn\'t request this, you can safely ignore this message — your password won\'t change.</p>'
                       . '<p>— ' . htmlspecialchars($brand) . '</p>',
        ]);
        audit($CONFIG, (int)$row['id'], null, 'auth.reset_email.' . ($sent ? 'sent' : 'failed'));

        if (!empty($CONFIG['demo_mode'])) {
            // Demo only — surface the URL so admins can test without email.
            $resp['reset_url'] = $resetUrl;
            $resp['dev_token'] = $token;
            $resp['dev_note']  = 'Demo mode: token returned in response. In production we email reset_url to the user.';
        }
        // Production: never leak the token; only ok:true on the wire.
    }
    send_json($resp);
}

/**
 * POST /api/auth/reset-password
 * Body: { token, password }
 * Consumes the token (single-use), updates the password, returns a fresh JWT.
 */
function route_auth_reset_password(array $CONFIG): void
{
    $data = read_json_body();
    $token    = trim((string)($data['token'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $errors = [];
    if ($token === '')                $errors['token']    = 'Reset link is missing or malformed.';
    if (strlen($password) < 8)        $errors['password'] = 'Password must be at least 8 characters.';
    if ($errors) send_json(['ok' => false, 'errors' => $errors], 422);

    $hash = hash('sha256', $token);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare(
        'SELECT id, user_id, expires_at, used FROM password_resets WHERE token_hash = ? LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch() ?: null;
    if (!$row || (int)$row['used'] !== 0 || strtotime($row['expires_at']) < time()) {
        send_json(['ok' => false, 'errors' => ['token' => 'This reset link has expired or already been used. Please request a new one.']], 410);
    }
    $pwHash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([$pwHash, (int)$row['user_id']]);
    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
        ->execute([(int)$row['id']]);
    // Best-effort: clear any other unused tokens for this user.
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used = 0')
        ->execute([(int)$row['user_id']]);
    audit($CONFIG, (int)$row['user_id'], null, 'auth.reset_password');

    // Sign the user in straight away.
    $stmt = $pdo->prepare('SELECT id, is_admin FROM users WHERE id = ?');
    $stmt->execute([(int)$row['user_id']]);
    $u = $stmt->fetch();
    $tok = jwt_issue($CONFIG, (int)$u['id'], (bool)$u['is_admin']);

    $cols = _user_select_cols($CONFIG);
    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([(int)$u['id']]);
    $user = $stmt->fetch();
    $user['is_admin'] = (bool)$user['is_admin'];
    $user['profile_complete'] = profile_is_complete($user);
    $user['profile_missing']  = profile_missing_fields($user);
    send_json(['ok' => true, 'token' => $tok, 'user' => $user]);
}

/**
 * POST /api/auth/change-password — for signed-in users.
 * Body: { current_password?, new_password }
 * If the account already has a password, current_password is required.
 * If the account is OAuth-only (no password yet), current_password may be omitted.
 */
function route_auth_change_password(array $CONFIG): void
{
    $u = require_user($CONFIG);
    $data = read_json_body();
    $cur = (string)($data['current_password'] ?? '');
    $next = (string)($data['new_password'] ?? '');

    $errors = [];
    if (strlen($next) < 8) $errors['new_password'] = 'New password must be at least 8 characters.';
    if ($errors) send_json(['ok' => false, 'errors' => $errors], 422);

    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$u['id']]);
    $row = $stmt->fetch();
    $hadPwd = !empty($row['password_hash']);
    if ($hadPwd) {
        if (!password_verify($cur, (string)$row['password_hash'])) {
            send_json(['ok' => false, 'errors' => ['current_password' => 'Current password is incorrect.']], 401);
        }
    }
    $pwHash = password_hash($next, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$pwHash, (int)$u['id']]);
    audit($CONFIG, (int)$u['id'], null, 'auth.change_password');
    send_json(['ok' => true]);
}

/**
 * PATCH /api/auth/profile  —  save the "tell us about yourself" fields the
 * user is required to complete before claiming a subdomain.
 *
 * Body (all required to mark the profile complete):
 *   name, mobile, designation_bn, institution_name, division, district, upazila
 */
function route_auth_profile_patch(array $CONFIG): void
{
    $u = require_user($CONFIG);
    $data = read_json_body();

    $get = static function (array $d, string $k): string {
        return trim((string)($d[$k] ?? ''));
    };

    $name             = $get($data, 'name');
    $mobile           = $get($data, 'mobile');
    $designation_bn   = $get($data, 'designation_bn');
    $institution_name = $get($data, 'institution_name');
    $division         = $get($data, 'division');
    $district         = $get($data, 'district');
    $upazila          = $get($data, 'upazila');

    // Validation: mobile must look like a BD number; everything else just non-empty.
    $errors = [];
    if ($name === '')             $errors['name']             = 'Full name required';
    if ($mobile === '')           $errors['mobile']           = 'Mobile number required';
    elseif (!is_valid_bd_phone($mobile)) {
        $errors['mobile'] = 'Enter a valid Bangladesh mobile (e.g. 01712345678)';
    }
    if ($designation_bn === '')   $errors['designation_bn']   = 'পদবি / Designation required';
    if ($institution_name === '') $errors['institution_name'] = 'প্রতিষ্ঠানের নাম / Institution name required';
    if ($division === '')         $errors['division']         = 'বিভাগ / Division required';
    if ($district === '')         $errors['district']         = 'জেলা / District required';
    if ($upazila === '')          $errors['upazila']          = 'উপজেলা / Upazila required';

    if ($errors) {
        send_json(['ok' => false, 'errors' => $errors], 422);
    }

    $mobileNorm = normalize_phone($mobile);
    $pdo = db($CONFIG);
    $pdo->prepare(
        'UPDATE users SET
            name = ?, mobile = ?, designation_bn = ?, institution_name = ?,
            division = ?, district = ?, upazila = ?, profile_completed_at = ?
         WHERE id = ?'
    )->execute([
        $name, $mobileNorm, $designation_bn, $institution_name,
        $division, $district, $upazila, db_now($CONFIG), (int)$u['id'],
    ]);
    audit($CONFIG, (int)$u['id'], null, 'auth.profile.update');

    // Return fresh user row so the frontend can update localStorage.
    $cols = _user_select_cols($CONFIG);
    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([(int)$u['id']]);
    $user = $stmt->fetch();
    $user['is_admin'] = (bool)$user['is_admin'];
    $user['profile_complete'] = profile_is_complete($user);
    $user['profile_missing']  = profile_missing_fields($user);
    send_json(['ok' => true, 'user' => $user]);
}

/* =================================================================== */
/*  OAuth                                                                */
/* =================================================================== */

function _oauth_html_redirect(string $url): void
{
    // Tiny HTML redirect so we can pass a token in the URL fragment (#token=…)
    // without it ever hitting the server logs.
    $safe = htmlspecialchars($url, ENT_QUOTES);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><meta charset=utf-8><title>Signing you in…</title>
<meta name=\"robots\" content=\"noindex\">
<script>window.location.replace(" . json_encode($url) . ");</script>
<p>Redirecting… If nothing happens, <a href=\"{$safe}\">click here</a>.</p>";
    exit;
}

function _oauth_finish_redirect(array $CONFIG, ?string $next, array $result): void
{
    // Land the user on /auth/callback.php with the result in the URL fragment.
    $base = rtrim((string)($CONFIG['site_url'] ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    // Encode result as a URL-safe base64 JSON blob.
    $payload = rtrim(strtr(base64_encode(json_encode($result)), '+/', '-_'), '=');
    $next = $next !== null && $next !== '' ? $next : '/';
    $url = $base . '/auth/callback.php#payload=' . $payload
         . '&next=' . rawurlencode($next);
    _oauth_html_redirect($url);
}

function route_auth_oauth_start(array $CONFIG, string $provider): void
{
    $enabled = oauth_enabled_providers($CONFIG);
    if (!isset($enabled[$provider])) {
        send_error('OAuth provider not configured: ' . $provider, 404);
    }
    $next = (string)($_GET['next'] ?? '/');
    $state = oauth_create_state($CONFIG, $provider, $next);
    $meta = $enabled[$provider];
    $params = [
        'client_id'     => $CONFIG['oauth'][$provider]['client_id'],
        'redirect_uri'  => oauth_redirect_uri($CONFIG, $provider),
        'response_type' => 'code',
        'scope'         => $meta['scope'],
        'state'         => $state,
    ];
    if ($provider === 'google') {
        $params['access_type'] = 'online';
        $params['prompt'] = 'select_account';
    }
    if ($provider === 'github') {
        $params['allow_signup'] = 'true';
    }
    $url = $meta['auth_url'] . '?' . http_build_query($params);
    header('Location: ' . $url, true, 302);
    exit;
}

function route_auth_oauth_callback(array $CONFIG, string $provider): void
{
    $enabled = oauth_enabled_providers($CONFIG);
    if (!isset($enabled[$provider])) {
        send_error('OAuth provider not configured: ' . $provider, 404);
    }
    $code = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    $err = (string)($_GET['error'] ?? '');
    if ($err) {
        _oauth_finish_redirect($CONFIG, null, [
            'ok' => false,
            'error' => $err . ': ' . (string)($_GET['error_description'] ?? ''),
        ]);
    }
    if ($code === '' || $state === '') {
        _oauth_finish_redirect($CONFIG, null, ['ok' => false, 'error' => 'Missing code/state']);
    }
    $st = oauth_consume_state($CONFIG, $state, $provider);
    if (!$st) {
        _oauth_finish_redirect($CONFIG, null, ['ok' => false, 'error' => 'Invalid or expired state']);
    }
    $profile = oauth_fetch_profile($CONFIG, $provider, $code);
    if (!$profile || ($profile['provider_id'] === '')) {
        _oauth_finish_redirect($CONFIG, $st['redirect'], ['ok' => false, 'error' => 'Could not retrieve profile from ' . $provider]);
    }
    $user = oauth_upsert_user($CONFIG, $profile);
    $token = jwt_issue($CONFIG, (int)$user['id'], (bool)$user['is_admin']);
    audit($CONFIG, (int)$user['id'], null, 'auth.oauth.' . $provider);
    _oauth_finish_redirect($CONFIG, $st['redirect'], [
        'ok' => true,
        'token' => $token,
        'user' => $user,
    ]);
}
