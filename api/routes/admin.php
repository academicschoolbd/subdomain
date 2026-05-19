<?php
declare(strict_types=1);

/** Admin routes: moderation queue, decide, documents, stats, reserved-slug CRUD. */

function route_admin_stats(array $CONFIG): void
{
    require_admin($CONFIG);
    $pdo = db($CONFIG);
    $byStatus = $pdo->query('SELECT status, COUNT(*) c FROM institutions GROUP BY status')->fetchAll();
    $counts = ['total' => 0, 'verified' => 0, 'pending' => 0, 'needs_info' => 0, 'rejected' => 0, 'seeded' => 0, 'suspended' => 0];
    foreach ($byStatus as $r) {
        $counts['total'] += (int)$r['c'];
        $counts[$r['status']] = (int)$r['c'];
    }
    $users = (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
    $admins = (int)$pdo->query('SELECT COUNT(*) c FROM users WHERE is_admin = 1')->fetch()['c'];

    // v3.2 — recent activity feed for the new Overview pane (last 12 entries).
    $stmt = $pdo->query(
        "SELECT a.id, a.actor_user_id, a.institution_id, a.action, a.detail, a.created_at,
                u.name AS actor_name, u.email AS actor_email
           FROM audit_log a
      LEFT JOIN users u ON u.id = a.actor_user_id
       ORDER BY a.id DESC
          LIMIT 12"
    );
    $activity = array_map(static function ($r) {
        $r['id']             = (int)$r['id'];
        $r['actor_user_id']  = $r['actor_user_id'] !== null ? (int)$r['actor_user_id'] : null;
        $r['institution_id'] = $r['institution_id'] !== null ? (int)$r['institution_id'] : null;
        return $r;
    }, $stmt->fetchAll());

    // Useful denormalised numbers for the KPI strip.
    if (db_is_mysql($CONFIG)) {
        $todayQ = $pdo->query("SELECT COUNT(*) c FROM institutions WHERE status='verified' AND verified_at >= (NOW() - INTERVAL 1 DAY)");
    } else {
        $todayQ = $pdo->query("SELECT COUNT(*) c FROM institutions WHERE status='verified' AND verified_at >= datetime('now','-1 day')");
    }
    $verifiedToday = (int)($todayQ ? $todayQ->fetch()['c'] : 0);

    send_json([
        'counts' => $counts,
        'users' => $users,
        'admins' => $admins,
        'verified_today' => $verifiedToday,
        'recent_activity' => $activity,
    ]);
}

function route_admin_list_claims(array $CONFIG): void
{
    require_admin($CONFIG);
    $status = $_GET['status'] ?? 'pending';
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $where = [];
    $bind = [];
    if ($status !== 'any') { $where[] = 'status = ?'; $bind[] = $status; }
    if ($q !== '') {
        $where[] = '(name_en LIKE ? OR name_bn LIKE ? OR slug LIKE ? OR eiin LIKE ?)';
        $like = '%' . $q . '%';
        array_push($bind, $like, $like, $like, $like);
    }
    $sql = 'SELECT * FROM institutions';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = db($CONFIG)->prepare($sql);
    $stmt->execute($bind);
    $items = array_map('_claim_row', $stmt->fetchAll());
    send_json(['items' => $items]);
}

function route_admin_get_claim(array $CONFIG, int $instId): void
{
    require_admin($CONFIG);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    $inst = $stmt->fetch();
    if (!$inst) send_error('Not found', 404);
    $stmt = $pdo->prepare('SELECT id, doc_type, filename, content_type, uploaded_at FROM claim_documents WHERE institution_id = ? ORDER BY id DESC');
    $stmt->execute([$instId]);
    $docs = array_map(function ($d) {
        $d['id'] = (int)$d['id'];
        return $d;
    }, $stmt->fetchAll());
    // Owner contact info
    $owner = null;
    if (!empty($inst['owner_user_id'])) {
        $stmt = $pdo->prepare('SELECT id, phone, email, name FROM users WHERE id = ?');
        $stmt->execute([(int)$inst['owner_user_id']]);
        $owner = $stmt->fetch() ?: null;
    }
    send_json(['institution' => _claim_row($inst), 'documents' => $docs, 'owner' => $owner]);
}

function route_admin_decide(array $CONFIG, int $instId): void
{
    $admin = require_admin($CONFIG);
    $data = read_json_body();
    $decision = (string)require_param($data, 'decision'); // approve | reject | needs_info | suspend
    $notes = (string)($data['notes'] ?? '');
    $allowed = ['approve','reject','needs_info','suspend'];
    if (!in_array($decision, $allowed, true)) send_error('Invalid decision', 400);

    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    $inst = $stmt->fetch();
    if (!$inst) send_error('Not found', 404);

    $now = db_now($CONFIG);
    $dnsInfo = null;

    if ($decision === 'approve') {
        $cf = cf_create_record($CONFIG, $inst['brand'], $inst['slug']);
        $dnsInfo = $cf;
        $cfRecordId = $cf['ok'] ? $cf['record_id'] : ($inst['cf_record_id'] ?? null);
        $dnsStatus = $cf['attempted'] ? ($cf['ok'] ? 'live' : 'error') : 'manual';
        // v4.1 — start the expiry clock the moment the admin approves.
        // If the institution already had an expires_at (e.g. it was
        // verified previously and is being re-approved after suspension),
        // keep the later of the two so we never accidentally truncate.
        $termDays = max(1, settings_get_int($CONFIG, 'domain_term_days', 365));
        $newExpires = date('Y-m-d H:i:s', strtotime($now) + $termDays * 86400);
        if (!empty($inst['expires_at']) && strtotime($inst['expires_at']) > strtotime($newExpires)) {
            $newExpires = $inst['expires_at'];
        }
        $pdo->prepare(
            'UPDATE institutions SET status = ?, review_notes = ?, verified_at = ?,
             expires_at = ?, cf_record_id = ?, dns_status = ?, dns_message = ? WHERE id = ?'
        )->execute(['verified', $notes ?: null, $now, $newExpires,
                    $cfRecordId, $dnsStatus, $cf['message'], $instId]);
    } elseif ($decision === 'reject') {
        // Best-effort DNS cleanup if a record was previously created.
        if (!empty($inst['cf_record_id'])) {
            cf_delete_record($CONFIG, $inst['brand'], $inst['cf_record_id'], $inst['slug']);
        }
        $pdo->prepare(
            'UPDATE institutions SET status = ?, review_notes = ?, cf_record_id = NULL, dns_status = NULL, dns_message = NULL WHERE id = ?'
        )->execute(['rejected', $notes ?: null, $instId]);
    } elseif ($decision === 'needs_info') {
        $pdo->prepare(
            'UPDATE institutions SET status = ?, review_notes = ? WHERE id = ?'
        )->execute(['needs_info', $notes ?: null, $instId]);
    } else { // suspend
        if (!empty($inst['cf_record_id'])) {
            cf_delete_record($CONFIG, $inst['brand'], $inst['cf_record_id'], $inst['slug']);
        }
        $pdo->prepare(
            'UPDATE institutions SET status = ?, review_notes = ?, dns_status = ?, cf_record_id = NULL WHERE id = ?'
        )->execute(['suspended', $notes ?: null, 'suspended', $instId]);
    }
    audit($CONFIG, (int)$admin['id'], $instId, 'admin.decide.' . $decision, $notes);
    $stmt->execute([$instId]);
    send_json(['ok' => true, 'institution' => _claim_row($stmt->fetch()), 'dns' => $dnsInfo]);
}

function route_admin_doc_download(array $CONFIG, int $docId): void
{
    require_admin($CONFIG);
    $stmt = db($CONFIG)->prepare('SELECT filename, stored_path, content_type FROM claim_documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    if (!$row || !file_exists($row['stored_path'])) send_error('Not found', 404);
    header('Content-Type: ' . $row['content_type']);
    header('Content-Disposition: inline; filename="' . basename($row['filename']) . '"');
    header('Content-Length: ' . filesize($row['stored_path']));
    readfile($row['stored_path']);
    exit;
}

function route_admin_reserved_list(array $CONFIG): void
{
    require_admin($CONFIG);
    $stmt = db($CONFIG)->query('SELECT id, slug, reason, created_at FROM reserved_slugs ORDER BY slug ASC');
    $items = array_map(function ($r) {
        $r['id'] = (int)$r['id'];
        return $r;
    }, $stmt->fetchAll());
    send_json(['items' => $items]);
}

function route_admin_reserved_add(array $CONFIG): void
{
    require_admin($CONFIG);
    $data = read_json_body();
    $slug = normalize_slug((string)require_param($data, 'slug'));
    [$ok, $err] = slug_format_validity($slug);
    if (!$ok) send_error($err, 400);
    $reason = $data['reason'] ?? null;
    try {
        db($CONFIG)->prepare(
            'INSERT INTO reserved_slugs (slug, reason, created_at) VALUES (?,?,?)'
        )->execute([$slug, $reason, db_now($CONFIG)]);
    } catch (PDOException $e) {
        send_error('Already reserved', 409);
    }
    audit($CONFIG, null, null, 'admin.reserved.add', $slug);
    send_json(['ok' => true, 'slug' => $slug]);
}

function route_admin_reserved_delete(array $CONFIG, int $id): void
{
    require_admin($CONFIG);
    db($CONFIG)->prepare('DELETE FROM reserved_slugs WHERE id = ?')->execute([$id]);
    audit($CONFIG, null, null, 'admin.reserved.delete', (string)$id);
    send_json(['ok' => true]);
}

function route_admin_dns_retry(array $CONFIG, int $instId): void
{
    $admin = require_admin($CONFIG);
    $stmt = db($CONFIG)->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    $inst = $stmt->fetch();
    if (!$inst) send_error('Not found', 404);
    if ($inst['status'] !== 'verified') send_error('Tenant must be verified', 400);
    $cf = cf_create_record($CONFIG, $inst['brand'], $inst['slug']);
    db($CONFIG)->prepare(
        'UPDATE institutions SET cf_record_id = ?, dns_status = ?, dns_message = ? WHERE id = ?'
    )->execute([
        $cf['ok'] ? $cf['record_id'] : ($inst['cf_record_id'] ?? null),
        $cf['attempted'] ? ($cf['ok'] ? 'live' : 'error') : 'manual',
        $cf['message'],
        $instId,
    ]);
    audit($CONFIG, (int)$admin['id'], $instId, 'admin.dns.retry');
    send_json(['ok' => $cf['ok'], 'dns' => $cf]);
}

/* =================================================================== */
/*  v3.0 — audit log viewer + CSV exports                                */
/* =================================================================== */

/**
 * GET /api/admin/audit
 * Query: ?action=…&user_id=…&inst_id=…&q=…&limit=100&offset=0
 *
 * Returns the most-recent audit_log rows joined with the actor's name/email
 * so admins can quickly see "who did what". Filtered + paginated.
 */
function route_admin_audit_log(array $CONFIG): void
{
    require_admin($CONFIG);
    $action  = trim((string)($_GET['action'] ?? ''));
    $userId  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $instId  = isset($_GET['inst_id']) ? (int)$_GET['inst_id'] : 0;
    $q       = trim((string)($_GET['q'] ?? ''));
    $limit   = max(1, min(200, (int)($_GET['limit'] ?? 100)));
    $offset  = max(0, (int)($_GET['offset'] ?? 0));

    $where = [];
    $bind = [];
    if ($action !== '') { $where[] = 'a.action LIKE ?'; $bind[] = $action . '%'; }
    if ($userId > 0)    { $where[] = 'a.actor_user_id = ?'; $bind[] = $userId; }
    if ($instId > 0)    { $where[] = 'a.institution_id = ?'; $bind[] = $instId; }
    if ($q !== '') {
        $where[] = '(a.detail LIKE ? OR u.email LIKE ? OR u.name LIKE ?)';
        $like = '%' . $q . '%';
        array_push($bind, $like, $like, $like);
    }
    $sql = "SELECT a.id, a.actor_user_id, a.institution_id, a.action, a.detail, a.created_at,
                   u.email AS actor_email, u.name AS actor_name
              FROM audit_log a
         LEFT JOIN users u ON u.id = a.actor_user_id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY a.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = db($CONFIG)->prepare($sql);
    $stmt->execute($bind);
    $items = array_map(function ($r) {
        $r['id']             = (int)$r['id'];
        $r['actor_user_id']  = $r['actor_user_id'] !== null ? (int)$r['actor_user_id'] : null;
        $r['institution_id'] = $r['institution_id'] !== null ? (int)$r['institution_id'] : null;
        return $r;
    }, $stmt->fetchAll());

    // Total (unfiltered count is cheap to skip; here we count the filtered set).
    $countSql = 'SELECT COUNT(*) c FROM audit_log a LEFT JOIN users u ON u.id = a.actor_user_id';
    if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
    $stmt = db($CONFIG)->prepare($countSql);
    $stmt->execute($bind);
    $total = (int)$stmt->fetch()['c'];
    send_json(['items' => $items, 'total' => $total]);
}

function _csv_send(string $filename, array $headers, iterable $rows): void
{
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    // BOM so Excel reads UTF-8 (Bengali) correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

/** GET /api/admin/export/claims.csv — every institution row as CSV. */
function route_admin_export_claims_csv(array $CONFIG): void
{
    require_admin($CONFIG);
    $stmt = db($CONFIG)->query(
        'SELECT id, brand, slug, name_en, name_bn, category, division, district, upazila,
                eiin, contact_name, contact_phone, contact_email, website,
                status, dns_status, owner_user_id, created_at, verified_at
           FROM institutions
       ORDER BY id ASC'
    );
    $rows = $stmt->fetchAll();
    $headers = [
        'id','brand','slug','subdomain','name_en','name_bn','category','division','district',
        'upazila','eiin','contact_name','contact_phone','contact_email','website',
        'status','dns_status','owner_user_id','created_at','verified_at',
    ];
    $gen = (function () use ($rows) {
        foreach ($rows as $r) {
            yield [
                $r['id'], $r['brand'], $r['slug'], $r['slug'] . '.' . $r['brand'],
                $r['name_en'], $r['name_bn'], $r['category'], $r['division'], $r['district'],
                $r['upazila'], $r['eiin'], $r['contact_name'], $r['contact_phone'],
                $r['contact_email'], $r['website'], $r['status'], $r['dns_status'],
                $r['owner_user_id'], $r['created_at'], $r['verified_at'],
            ];
        }
    })();
    _csv_send('claims-' . date('Ymd-His') . '.csv', $headers, $gen);
}

/** GET /api/admin/export/users.csv — every user row as CSV. */
function route_admin_export_users_csv(array $CONFIG): void
{
    require_admin($CONFIG);
    $stmt = db($CONFIG)->query(
        'SELECT id, email, name, mobile, phone, provider, is_admin,
                designation_bn, institution_name, division, district, upazila,
                profile_completed_at, created_at
           FROM users
       ORDER BY id ASC'
    );
    $rows = $stmt->fetchAll();
    $headers = [
        'id','email','name','mobile','phone','provider','is_admin',
        'designation_bn','institution_name','division','district','upazila',
        'profile_completed_at','created_at',
    ];
    $gen = (function () use ($rows) {
        foreach ($rows as $r) {
            yield [
                $r['id'], $r['email'], $r['name'], $r['mobile'], $r['phone'],
                $r['provider'], $r['is_admin'],
                $r['designation_bn'], $r['institution_name'], $r['division'],
                $r['district'], $r['upazila'], $r['profile_completed_at'], $r['created_at'],
            ];
        }
    })();
    _csv_send('users-' . date('Ymd-His') . '.csv', $headers, $gen);
}

/* =================================================================== */
/*  v3.0 — admin platform settings (require_documents, instant_claim,    */
/*           cloudflare_auto_dns)                                        */
/* =================================================================== */

function route_admin_settings_get(array $CONFIG): void
{
    require_admin($CONFIG);
    $s = settings_get_all($CONFIG);
    // Surface CF configuration alongside so the UI can show the right banner.
    $cfBrands = [];
    foreach (($CONFIG['brands'] ?? []) as $b) {
        $cfBrands[$b] = cf_enabled($CONFIG, $b);
    }
    // v4.1 — `require_approval` is the new authoritative gate. Pre-v4.1
    // installs only have `require_documents`; we mirror it onto the new
    // toggle so the admin pane shows the correct state on first read.
    $reqApproval = array_key_exists('require_approval', $s)
        ? $s['require_approval'] === '1'
        : ($s['require_documents'] === '1');
    send_json([
        'settings' => [
            'require_approval'    => $reqApproval,
            'require_documents'   => $s['require_documents']   === '1',
            'instant_claim'       => $s['instant_claim']       === '1',
            'cloudflare_auto_dns' => $s['cloudflare_auto_dns'] === '1',
            'email_registration_enabled' => $s['email_registration_enabled'] === '1',
            'domain_term_days'    => (int)($s['domain_term_days']    ?? 365),
            'domain_renewal_price_bdt' => (int)($s['domain_renewal_price_bdt'] ?? 0),
            // v4.5 — brand / home theme.
            'theme_primary_hex'      => theme_norm_hex($s['theme_primary_hex']      ?? '', THEME_DEFAULT_PRIMARY),
            'theme_primary_dark_hex' => theme_norm_hex($s['theme_primary_dark_hex'] ?? '', ''),
            'theme_primary_hex_set'      => !empty($s['theme_primary_hex']),
            'theme_primary_dark_hex_set' => !empty($s['theme_primary_dark_hex']),
        ],
        'cloudflare' => [
            'configured'        => array_filter($cfBrands) ? true : false,
            'configured_brands' => array_keys(array_filter($cfBrands)),
            'all_brands'        => $CONFIG['brands'] ?? [],
        ],
    ]);
}

function route_admin_settings_set(array $CONFIG): void
{
    $admin = require_admin($CONFIG);
    $data = read_json_body();
    $boolKeys = ['require_approval', 'require_documents', 'instant_claim',
                 'cloudflare_auto_dns', 'email_registration_enabled'];
    $intKeys  = ['domain_term_days', 'domain_renewal_price_bdt'];
    $changed = [];
    foreach ($boolKeys as $k) {
        if (!array_key_exists($k, $data)) continue;
        $v = $data[$k] ? '1' : '0';
        settings_set($CONFIG, $k, $v);
        $changed[$k] = $v === '1';
    }
    foreach ($intKeys as $k) {
        if (!array_key_exists($k, $data)) continue;
        $v = max(0, (int)$data[$k]);
        // term must be at least 1 day to keep date math sane.
        if ($k === 'domain_term_days') $v = max(1, $v);
        settings_set($CONFIG, $k, (string)$v);
        $changed[$k] = $v;
    }
    // v4.5 — theme color hex strings. Empty string = "use the default".
    $hexKeys = ['theme_primary_hex', 'theme_primary_dark_hex'];
    foreach ($hexKeys as $k) {
        if (!array_key_exists($k, $data)) continue;
        $raw = is_string($data[$k]) ? trim($data[$k]) : '';
        if ($raw === '') {
            settings_set($CONFIG, $k, '');
            $changed[$k] = '';
        } else {
            $hex = theme_norm_hex($raw, '');
            if ($hex === '') send_error("Invalid hex color for $k", 400);
            settings_set($CONFIG, $k, $hex);
            $changed[$k] = $hex;
        }
    }
    audit($CONFIG, (int)$admin['id'], null, 'admin.settings.update', json_encode($changed));
    route_admin_settings_get($CONFIG);
}


/* =================================================================== */
/*  v3.1 — admin self-service integrations (OAuth / CF / WhatsApp / …)  */
/* =================================================================== */

/** GET /api/admin/integrations — list every editable integration value. */
function route_admin_integrations_get(array $CONFIG): void
{
    require_admin($CONFIG);
    send_json(integrations_admin_view($CONFIG));
}

/**
 * POST /api/admin/integrations
 * Body: { values: { 'oauth.google.client_id': '...', 'cloudflare.api_token': '...' } }
 *
 * - Empty string for a sensitive key = "leave existing value alone"
 *   (so the masked form can submit without forcing the admin to retype
 *   secrets every time).
 * - Non-empty string = overwrite.
 * - Any unknown keys are silently ignored.
 */
function route_admin_integrations_set(array $CONFIG): void
{
    $admin = require_admin($CONFIG);
    $data = read_json_body();
    $values = $data['values'] ?? [];
    if (!is_array($values)) send_error('values must be an object', 400);

    $allowed   = array_flip(integrations_known_keys());
    $sensitive = array_flip(integrations_sensitive_keys());
    $changed   = [];

    foreach ($values as $key => $val) {
        if (!isset($allowed[$key])) continue;
        // Coerce booleans / numbers to a stringy storage form.
        if (is_bool($val)) $val = $val ? '1' : '0';
        if (!is_string($val)) $val = (string)$val;
        $val = trim($val);

        if (isset($sensitive[$key]) && $val === '') {
            // Don't wipe a saved secret just because the masked field came back empty.
            continue;
        }
        integrations_set_one($CONFIG, $key, $val);
        $changed[] = $key;
    }

    audit($CONFIG, (int)$admin['id'], null, 'admin.integrations.update',
        json_encode(['keys' => $changed]));

    // Re-apply overlay so the freshly returned view matches the just-saved state.
    integrations_apply_overlay($CONFIG);
    send_json(['ok' => true, 'updated' => $changed] + integrations_admin_view($CONFIG));
}

/**
 * POST /api/admin/integrations/test
 * Body: { target: 'cloudflare' }
 *
 * Runs a live connection check against the saved credentials. Right now
 * we only verify the Cloudflare token (used for auto-DNS); other targets
 * can be plugged in later.
 */
function route_admin_integrations_test(array $CONFIG): void
{
    require_admin($CONFIG);
    $data = read_json_body();
    $target = strtolower(trim((string)($data['target'] ?? '')));
    if ($target === 'cloudflare') {
        send_json(integrations_cloudflare_test($CONFIG));
    } elseif ($target === 'telegram') {
        $result = telegram_test($CONFIG);
        send_json($result);
    }
    send_error('Unknown integration target', 400);
}

/**
 * POST /api/admin/integrations/rotate-jwt
 * Convenience endpoint: generates a fresh 64-byte hex JWT secret and
 * persists it. Returns the masked form for confirmation. After this all
 * existing tokens are invalidated, so the admin gets signed out — they'll
 * need to log in again on next request.
 */
function route_admin_integrations_rotate_jwt(array $CONFIG): void
{
    $admin = require_admin($CONFIG);
    $secret = bin2hex(random_bytes(32));
    integrations_set_one($CONFIG, 'jwt.secret', $secret);
    audit($CONFIG, (int)$admin['id'], null, 'admin.integrations.rotate_jwt');
    send_json([
        'ok' => true,
        'message' => 'JWT secret rotated. All sessions (including yours) have been invalidated — please sign in again.',
        'masked'  => integrations_mask($secret),
    ]);
}



/* =================================================================== */
/*  v3.2 — admin user management                                         */
/* =================================================================== */

/** GET /api/admin/users  — list users with optional q + role filter.
 *
 *  Query: ?q=<email/name/mobile>&role=admin|user|any&limit=…&offset=…
 *  Response: { items: [...], total: int }
 *
 *  Each item carries the fields the new admin UI needs: id, email, name,
 *  mobile, provider, is_admin, profile_complete (computed from the same
 *  required-field list as auth_me), claim_count, created_at.
 */
function route_admin_users_list(array $CONFIG): void
{
    require_admin($CONFIG);
    $q     = trim((string)($_GET['q'] ?? ''));
    $role  = strtolower(trim((string)($_GET['role'] ?? 'any')));
    $limit = max(1, min(200, (int)($_GET['limit']  ?? 50)));
    $offset= max(0, (int)($_GET['offset'] ?? 0));

    $cols = _user_select_cols($CONFIG); // shared column list (see auth.php)
    $where = [];
    $bind  = [];
    if ($q !== '') {
        $where[] = '(email LIKE ? OR name LIKE ? OR mobile LIKE ? OR phone LIKE ?)';
        $like = '%' . $q . '%';
        array_push($bind, $like, $like, $like, $like);
    }
    if ($role === 'admin')      { $where[] = 'is_admin = 1'; }
    elseif ($role === 'user')   { $where[] = 'is_admin = 0'; }

    $sql = "SELECT $cols FROM users";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = db($CONFIG)->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll();

    // Per-user claim count (single round-trip via IN clause).
    $byUserClaims = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cs = db($CONFIG)->prepare(
            "SELECT owner_user_id, COUNT(*) c
               FROM institutions
              WHERE owner_user_id IN ($placeholders)
              GROUP BY owner_user_id"
        );
        $cs->execute($ids);
        foreach ($cs->fetchAll() as $r) {
            $byUserClaims[(int)$r['owner_user_id']] = (int)$r['c'];
        }
    }

    $items = array_map(static function ($u) use ($byUserClaims) {
        $u['id']               = (int)$u['id'];
        $u['is_admin']         = (bool)($u['is_admin'] ?? 0);
        $u['profile_complete'] = profile_is_complete($u);
        $u['claim_count']      = $byUserClaims[(int)$u['id']] ?? 0;
        // Don't leak password hashes etc. – we only selected safe cols above.
        return $u;
    }, $rows);

    $countSql = 'SELECT COUNT(*) c FROM users';
    if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
    $cs = db($CONFIG)->prepare($countSql);
    $cs->execute($bind);
    $total = (int)$cs->fetch()['c'];

    send_json(['items' => $items, 'total' => $total]);
}

/** POST /api/admin/users/{id}/role  body: { is_admin: true|false } */
function route_admin_users_set_role(array $CONFIG, int $userId): void
{
    $admin = require_admin($CONFIG);
    if ((int)$admin['id'] === $userId) {
        send_error("You can't change your own admin role from this screen.", 400);
    }
    $data = read_json_body();
    if (!array_key_exists('is_admin', $data)) {
        send_error('is_admin (boolean) is required', 400);
    }
    $isAdmin = $data['is_admin'] ? 1 : 0;

    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id, email, name, is_admin FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) send_error('User not found', 404);

    // Don't allow removing the last admin — that would lock everyone out.
    if ((int)$u['is_admin'] === 1 && $isAdmin === 0) {
        $remaining = (int)$pdo->query('SELECT COUNT(*) c FROM users WHERE is_admin = 1')->fetch()['c'];
        if ($remaining <= 1) {
            send_error("Refusing to demote the last remaining admin. Promote another user first.", 409);
        }
    }

    $pdo->prepare('UPDATE users SET is_admin = ? WHERE id = ?')->execute([$isAdmin, $userId]);
    audit($CONFIG, (int)$admin['id'], null, 'admin.users.role',
        json_encode(['user_id' => $userId, 'is_admin' => (bool)$isAdmin]));

    send_json(['ok' => true, 'user_id' => $userId, 'is_admin' => (bool)$isAdmin]);
}

/** POST /api/admin/queue/bulk-decide  body: { ids: [int...], decision: approve|needs_info|reject|suspend, notes? } */
function route_admin_bulk_decide(array $CONFIG): void
{
    $admin = require_admin($CONFIG);
    $data  = read_json_body();
    $ids   = $data['ids'] ?? [];
    if (!is_array($ids) || !$ids) send_error('ids[] is required', 400);
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $decision = (string)require_param($data, 'decision');
    $allowed  = ['approve','reject','needs_info','suspend'];
    if (!in_array($decision, $allowed, true)) send_error('Invalid decision', 400);
    $notes = (string)($data['notes'] ?? '');

    $results = [];
    foreach ($ids as $instId) {
        // Reuse the single-decide handler's logic by inlining the SQL it
        // would execute. We don't call the existing function because it
        // calls send_json() and exits.
        $pdo = db($CONFIG);
        $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id = ?');
        $stmt->execute([$instId]);
        $inst = $stmt->fetch();
        if (!$inst) { $results[] = ['id' => $instId, 'ok' => false, 'detail' => 'Not found']; continue; }

        $now = db_now($CONFIG);
        if ($decision === 'approve') {
            $cf = cf_create_record($CONFIG, $inst['brand'], $inst['slug']);
            $cfRecordId = $cf['ok'] ? $cf['record_id'] : ($inst['cf_record_id'] ?? null);
            $dnsStatus  = $cf['attempted'] ? ($cf['ok'] ? 'live' : 'error') : 'manual';
            $pdo->prepare(
                'UPDATE institutions SET status = ?, review_notes = ?, verified_at = ?,
                  cf_record_id = ?, dns_status = ?, dns_message = ? WHERE id = ?'
            )->execute(['verified', $notes ?: null, $now, $cfRecordId, $dnsStatus, $cf['message'], $instId]);
            $results[] = ['id' => $instId, 'ok' => true, 'dns' => $dnsStatus];
        } elseif ($decision === 'reject') {
            if (!empty($inst['cf_record_id'])) {
                cf_delete_record($CONFIG, $inst['brand'], $inst['cf_record_id'], $inst['slug']);
            }
            $pdo->prepare(
                'UPDATE institutions SET status = ?, review_notes = ?, cf_record_id = NULL, dns_status = NULL, dns_message = NULL WHERE id = ?'
            )->execute(['rejected', $notes ?: null, $instId]);
            $results[] = ['id' => $instId, 'ok' => true];
        } elseif ($decision === 'needs_info') {
            $pdo->prepare(
                'UPDATE institutions SET status = ?, review_notes = ? WHERE id = ?'
            )->execute(['needs_info', $notes ?: null, $instId]);
            $results[] = ['id' => $instId, 'ok' => true];
        } else { // suspend
            if (!empty($inst['cf_record_id'])) {
                cf_delete_record($CONFIG, $inst['brand'], $inst['cf_record_id'], $inst['slug']);
            }
            $pdo->prepare(
                'UPDATE institutions SET status = ?, review_notes = ?, dns_status = ?, cf_record_id = NULL WHERE id = ?'
            )->execute(['suspended', $notes ?: null, 'suspended', $instId]);
            $results[] = ['id' => $instId, 'ok' => true];
        }
        audit($CONFIG, (int)$admin['id'], $instId, 'admin.bulk_decide.' . $decision, $notes);
    }
    send_json(['ok' => true, 'count' => count($results), 'results' => $results]);
}


/* =================================================================== */
/*  v3.2 — admin-managed "Support Developer" payment methods             */
/* =================================================================== */

const ADMIN_PAY_METHODS = [
    'bkash', 'nagad', 'rocket', 'upay', 'tap',
    'bank', 'card', 'paypal', 'crypto', 'other',
];

function _admin_pay_row(array $r): array
{
    return [
        'id'         => (int)$r['id'],
        'method'     => (string)$r['method'],
        'label'      => (string)$r['label'],
        'number'     => $r['number'] !== null ? (string)$r['number'] : null,
        'note'       => $r['note']   !== null ? (string)$r['note']   : null,
        'qr_url'     => $r['qr_url'] !== null ? (string)$r['qr_url'] : null,
        'sort_order' => (int)($r['sort_order'] ?? 0),
        'visible'    => (bool)($r['visible'] ?? 0),
        'created_at' => $r['created_at'] ?? null,
        'updated_at' => $r['updated_at'] ?? null,
    ];
}

function _admin_pay_validate(array $data): array
{
    $errors = [];
    $method = strtolower(trim((string)($data['method'] ?? 'other')));
    if (!in_array($method, ADMIN_PAY_METHODS, true)) {
        $errors['method'] = 'Method must be one of: ' . implode(', ', ADMIN_PAY_METHODS);
    }
    $label = trim((string)($data['label'] ?? ''));
    if ($label === '') $errors['label'] = 'Label is required.';
    if (strlen($label) > 120) $errors['label'] = 'Label is too long (max 120).';
    $number = trim((string)($data['number'] ?? ''));
    if (strlen($number) > 120) $errors['number'] = 'Number is too long (max 120).';
    $note = trim((string)($data['note'] ?? ''));
    $qr = trim((string)($data['qr_url'] ?? ''));
    if (strlen($qr) > 512) $errors['qr_url'] = 'QR URL is too long (max 512).';
    if ($qr !== '' && !preg_match('#^https?://#i', $qr) && substr($qr, 0, 1) !== '/') {
        $errors['qr_url'] = 'QR URL must be an http(s) link or /uploads path.';
    }
    $sort = (int)($data['sort_order'] ?? 0);
    $visible = !empty($data['visible']) ? 1 : 0;
    return [$errors, [
        'method' => $method, 'label' => $label,
        'number' => $number !== '' ? $number : null,
        'note'   => $note   !== '' ? $note   : null,
        'qr_url' => $qr     !== '' ? $qr     : null,
        'sort_order' => $sort,
        'visible'    => $visible,
    ]];
}

/** GET /api/admin/support/payments — every payment method (visible or not). */
function route_admin_support_payments_list(array $CONFIG): void
{
    require_admin($CONFIG);
    $stmt = db($CONFIG)->query(
        'SELECT * FROM support_payments ORDER BY sort_order ASC, id ASC'
    );
    $items = array_map('_admin_pay_row', $stmt->fetchAll());
    send_json(['items' => $items]);
}

/** POST /api/admin/support/payments — create. */
function route_admin_support_payments_create(array $CONFIG): void
{
    $admin = require_admin($CONFIG);
    $data = read_json_body();
    [$errors, $clean] = _admin_pay_validate($data);
    if ($errors) send_json(['detail' => 'Invalid input', 'errors' => $errors], 422);
    $now = db_now($CONFIG);
    $pdo = db($CONFIG);
    $pdo->prepare(
        'INSERT INTO support_payments
            (method, label, number, note, qr_url, sort_order, visible, created_at, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $clean['method'], $clean['label'], $clean['number'], $clean['note'], $clean['qr_url'],
        $clean['sort_order'], $clean['visible'], $now, $now,
    ]);
    $id = (int)$pdo->lastInsertId();
    audit($CONFIG, (int)$admin['id'], null, 'admin.support_payment.create',
        json_encode(['id' => $id, 'method' => $clean['method'], 'label' => $clean['label']]));
    $stmt = $pdo->prepare('SELECT * FROM support_payments WHERE id = ?');
    $stmt->execute([$id]);
    send_json(['ok' => true, 'item' => _admin_pay_row($stmt->fetch())]);
}

/** PATCH /api/admin/support/payments/{id} — partial update. */
function route_admin_support_payments_update(array $CONFIG, int $id): void
{
    $admin = require_admin($CONFIG);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM support_payments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) send_error('Payment method not found', 404);

    $data = read_json_body();
    $merged = [
        'method'     => $data['method']     ?? $row['method'],
        'label'      => $data['label']      ?? $row['label'],
        'number'     => array_key_exists('number', $data) ? $data['number'] : $row['number'],
        'note'       => array_key_exists('note',   $data) ? $data['note']   : $row['note'],
        'qr_url'     => array_key_exists('qr_url', $data) ? $data['qr_url'] : $row['qr_url'],
        'sort_order' => $data['sort_order'] ?? $row['sort_order'],
        'visible'    => array_key_exists('visible', $data) ? $data['visible'] : $row['visible'],
    ];
    [$errors, $clean] = _admin_pay_validate($merged);
    if ($errors) send_json(['detail' => 'Invalid input', 'errors' => $errors], 422);

    $now = db_now($CONFIG);
    $pdo->prepare(
        'UPDATE support_payments SET method = ?, label = ?, number = ?, note = ?,
            qr_url = ?, sort_order = ?, visible = ?, updated_at = ? WHERE id = ?'
    )->execute([
        $clean['method'], $clean['label'], $clean['number'], $clean['note'], $clean['qr_url'],
        $clean['sort_order'], $clean['visible'], $now, $id,
    ]);
    audit($CONFIG, (int)$admin['id'], null, 'admin.support_payment.update',
        json_encode(['id' => $id]));
    $stmt = $pdo->prepare('SELECT * FROM support_payments WHERE id = ?');
    $stmt->execute([$id]);
    send_json(['ok' => true, 'item' => _admin_pay_row($stmt->fetch())]);
}

/** DELETE /api/admin/support/payments/{id} — hard delete. */
function route_admin_support_payments_delete(array $CONFIG, int $id): void
{
    $admin = require_admin($CONFIG);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id FROM support_payments WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) send_error('Payment method not found', 404);
    $pdo->prepare('DELETE FROM support_payments WHERE id = ?')->execute([$id]);
    audit($CONFIG, (int)$admin['id'], null, 'admin.support_payment.delete', (string)$id);
    send_json(['ok' => true]);
}



/* =================================================================== */
/*  v4.1 — admin claim-document maintenance                              */
/*                                                                       */
/*  We already serve every uploaded doc inline at GET /admin/documents/  */
/*  {id} (route_admin_doc_download), which the new admin UI hosts in an  */
/*  <img> / <iframe> for instant preview without forcing a download.     */
/*  These two helpers round out the toolset:                             */
/*                                                                       */
/*    DELETE /admin/documents/{id}  — purge an obsolete doc + its file. */
/*    PATCH  /admin/documents/{id}  — reclassify (change doc_type).     */
/* =================================================================== */

function route_admin_doc_delete(array $CONFIG, int $docId): void
{
    $admin = require_admin($CONFIG);
    $pdo   = db($CONFIG);
    $stmt  = $pdo->prepare('SELECT * FROM claim_documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    if (!$row) send_error('Not found', 404);
    if (!empty($row['stored_path']) && is_file($row['stored_path'])) {
        @unlink($row['stored_path']);
    }
    $pdo->prepare('DELETE FROM claim_documents WHERE id = ?')->execute([$docId]);
    audit($CONFIG, (int)$admin['id'], (int)($row['institution_id'] ?? 0),
        'admin.doc.delete', (string)($row['filename'] ?? ''));
    send_json(['ok' => true]);
}

function route_admin_doc_patch(array $CONFIG, int $docId): void
{
    $admin = require_admin($CONFIG);
    $data  = read_json_body();
    $type  = (string)($data['doc_type'] ?? '');
    if (!in_array($type, ALLOWED_DOC_TYPES, true)) {
        send_error('Unknown doc_type', 400);
    }
    $pdo  = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id, institution_id FROM claim_documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    if (!$row) send_error('Not found', 404);
    $pdo->prepare('UPDATE claim_documents SET doc_type = ? WHERE id = ?')
        ->execute([$type, $docId]);
    audit($CONFIG, (int)$admin['id'], (int)$row['institution_id'],
        'admin.doc.update', $type);
    send_json(['ok' => true, 'doc_type' => $type]);
}

/* =================================================================== */
/*  v4.1 — admin: extend a domain's expiry manually                      */
/* =================================================================== */

/**
 * POST /api/admin/claims/{id}/extend
 *
 * Body: { days?: int, expires_at?: 'YYYY-MM-DD HH:MM:SS' }
 *
 * Either pumps `days` onto the current expiry (or now, whichever is
 * later) or sets an absolute expiry timestamp. Useful for goodwill
 * extensions, partner deals, or fixing a botched renewal without going
 * through the renewals queue.
 */
function route_admin_claim_extend(array $CONFIG, int $instId): void
{
    $admin = require_admin($CONFIG);
    $pdo   = db($CONFIG);
    $stmt  = $pdo->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    $inst = $stmt->fetch();
    if (!$inst) send_error('Not found', 404);

    $data = read_json_body();
    $abs  = trim((string)($data['expires_at'] ?? ''));
    $days = isset($data['days']) ? (int)$data['days'] : 0;
    $newExpiresAt = null;

    if ($abs !== '') {
        $ts = strtotime($abs);
        if ($ts === false) send_error('expires_at is not a valid timestamp', 400);
        $newExpiresAt = date('Y-m-d H:i:s', $ts);
    } elseif ($days > 0) {
        $base = !empty($inst['expires_at']) && strtotime($inst['expires_at']) > time()
            ? strtotime($inst['expires_at']) : time();
        $newExpiresAt = date('Y-m-d H:i:s', $base + $days * 86400);
    } else {
        send_error('Provide either days (>0) or expires_at.', 400);
    }

    $pdo->prepare('UPDATE institutions SET expires_at = ? WHERE id = ?')
        ->execute([$newExpiresAt, $instId]);
    audit($CONFIG, (int)$admin['id'], $instId, 'admin.claim.extend',
        json_encode(['from' => $inst['expires_at'] ?? null, 'to' => $newExpiresAt]));
    $stmt->execute([$instId]);
    send_json(['ok' => true, 'institution' => _claim_row($stmt->fetch())]);
}

/* =================================================================== */
/*  v4.1 — admin: pending-renewal queue                                 */
/* =================================================================== */

/** GET /api/admin/renewals?status=pending|approved|rejected|any&q=… */
function route_admin_renewals_list(array $CONFIG): void
{
    require_admin($CONFIG);
    $status = trim((string)($_GET['status'] ?? 'pending'));
    $q      = trim((string)($_GET['q'] ?? ''));
    $limit  = max(1, min(200, (int)($_GET['limit'] ?? 100)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $where = [];
    $bind  = [];
    if ($status !== 'any') {
        $where[] = 'r.status = ?';
        $bind[]  = $status;
    }
    if ($q !== '') {
        $where[] = '(i.slug LIKE ? OR i.name_en LIKE ? OR u.email LIKE ?)';
        $like = '%' . $q . '%';
        array_push($bind, $like, $like, $like);
    }

    $sql = "SELECT r.*,
                   i.brand AS i_brand, i.slug AS i_slug, i.name_en AS i_name_en,
                   u.email AS owner_email, u.name AS owner_name
              FROM domain_renewals r
         LEFT JOIN institutions i ON i.id = r.institution_id
         LEFT JOIN users u        ON u.id = r.owner_user_id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY r.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = db($CONFIG)->prepare($sql);
    $stmt->execute($bind);
    $items = array_map(function ($r) {
        $row = _renewal_row($r);
        $row['institution'] = [
            'brand'   => $r['i_brand'] ?? null,
            'slug'    => $r['i_slug']  ?? null,
            'name_en' => $r['i_name_en'] ?? null,
            'subdomain' => ($r['i_slug'] ?? '') !== ''
                ? ($r['i_slug'] . '.' . ($r['i_brand'] ?? '')) : null,
        ];
        $row['owner'] = [
            'email' => $r['owner_email'] ?? null,
            'name'  => $r['owner_name']  ?? null,
        ];
        return $row;
    }, $stmt->fetchAll());

    // Total (filtered).
    $countSql = 'SELECT COUNT(*) c FROM domain_renewals r '
              . 'LEFT JOIN institutions i ON i.id = r.institution_id '
              . 'LEFT JOIN users u        ON u.id = r.owner_user_id';
    if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
    $cs = db($CONFIG)->prepare($countSql);
    $cs->execute($bind);
    $total = (int)$cs->fetch()['c'];

    send_json(['items' => $items, 'total' => $total]);
}

/** POST /api/admin/renewals/{id}/decide  body: { decision, note? } */
function route_admin_renewal_decide(array $CONFIG, int $renewalId): void
{
    $admin = require_admin($CONFIG);
    $data  = read_json_body();
    $decision = (string)require_param($data, 'decision'); // approve | reject
    if (!in_array($decision, ['approve', 'reject'], true)) {
        send_error('decision must be approve or reject', 400);
    }
    $note = trim((string)($data['note'] ?? ''));
    if (mb_strlen($note) > 1000) $note = mb_substr($note, 0, 1000);

    $pdo  = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM domain_renewals WHERE id = ?');
    $stmt->execute([$renewalId]);
    $r = $stmt->fetch();
    if (!$r) send_error('Not found', 404);
    if ($r['status'] !== 'pending') {
        send_error('This renewal has already been decided.', 409);
    }

    $now = db_now($CONFIG);
    if ($decision === 'approve') {
        // Recompute the new expiry from the *current* institution row so
        // we don't lose calendar time if the admin sat on the request for
        // a while after the owner submitted it.
        $instId = (int)$r['institution_id'];
        $is = $pdo->prepare('SELECT expires_at FROM institutions WHERE id = ?');
        $is->execute([$instId]);
        $inst = $is->fetch();
        $base = !empty($inst['expires_at']) && strtotime($inst['expires_at']) > time()
            ? strtotime($inst['expires_at']) : time();
        $termDays = max(1, (int)$r['term_days']);
        $newExpires = date('Y-m-d H:i:s', $base + $termDays * 86400);

        $pdo->prepare('UPDATE institutions SET expires_at = ? WHERE id = ?')
            ->execute([$newExpires, $instId]);
        $pdo->prepare(
            "UPDATE domain_renewals SET status = 'approved', note = ?,
             new_expires_at = ?, decided_by_user_id = ?, decided_at = ?
             WHERE id = ?"
        )->execute([$note !== '' ? $note : null, $newExpires, (int)$admin['id'], $now, $renewalId]);
        audit($CONFIG, (int)$admin['id'], $instId, 'admin.renewal.approve',
            json_encode(['renewal_id' => $renewalId, 'new_expires_at' => $newExpires]));
    } else {
        $pdo->prepare(
            "UPDATE domain_renewals SET status = 'rejected', note = ?,
             decided_by_user_id = ?, decided_at = ? WHERE id = ?"
        )->execute([$note !== '' ? $note : null, (int)$admin['id'], $now, $renewalId]);
        audit($CONFIG, (int)$admin['id'], (int)$r['institution_id'],
            'admin.renewal.reject', json_encode(['renewal_id' => $renewalId, 'note' => $note]));
    }

    $stmt->execute([$renewalId]);
    send_json(['ok' => true, 'renewal' => _renewal_row($stmt->fetch())]);
}


/* =================================================================== */
/*  v5 — admin-managed sponsors (homepage "Sponsored By")                */
/* =================================================================== */

function _admin_sponsor_row(array $r): array
{
    return [
        'id'          => (int)$r['id'],
        'name'        => (string)$r['name'],
        'logo_url'    => (string)$r['logo_url'],
        'website_url' => $r['website_url'] !== null ? (string)$r['website_url'] : null,
        'visible'     => (bool)($r['visible'] ?? 0),
        'sort_order'  => (int)($r['sort_order'] ?? 0),
        'created_at'  => $r['created_at'] ?? null,
        'updated_at'  => $r['updated_at'] ?? null,
    ];
}

/** GET /api/admin/sponsors — every sponsor (visible or not). */
function route_admin_sponsors_list(array $CONFIG): void
{
    require_admin($CONFIG);
    $stmt = db($CONFIG)->query(
        'SELECT * FROM sponsors ORDER BY sort_order ASC, id ASC'
    );
    $items = array_map('_admin_sponsor_row', $stmt->fetchAll());
    send_json(['items' => $items]);
}

/** POST /api/admin/sponsors — create a sponsor. */
function route_admin_sponsors_create(array $CONFIG): void
{
    $admin = require_admin($CONFIG);
    $data = read_json_body();
    $name = trim((string)($data['name'] ?? ''));
    $logo_url = trim((string)($data['logo_url'] ?? ''));
    if ($name === '') send_error('Name is required', 400);
    if ($logo_url === '') send_error('Logo URL is required', 400);
    $website_url = trim((string)($data['website_url'] ?? ''));
    $visible = !empty($data['visible']) ? 1 : 0;
    $sort_order = (int)($data['sort_order'] ?? 0);
    $now = db_now($CONFIG);
    $pdo = db($CONFIG);
    $pdo->prepare(
        'INSERT INTO sponsors (name, logo_url, website_url, visible, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?)'
    )->execute([$name, $logo_url, $website_url !== '' ? $website_url : null, $visible, $sort_order, $now, $now]);
    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM sponsors WHERE id = ?');
    $stmt->execute([$id]);
    send_json(['ok' => true, 'item' => _admin_sponsor_row($stmt->fetch())]);
}

/** PATCH /api/admin/sponsors/{id} — partial update. */
function route_admin_sponsors_update(array $CONFIG, int $id): void
{
    require_admin($CONFIG);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM sponsors WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) send_error('Sponsor not found', 404);

    $data = read_json_body();
    $name = trim((string)(array_key_exists('name', $data) ? $data['name'] : $row['name']));
    $logo_url = trim((string)(array_key_exists('logo_url', $data) ? $data['logo_url'] : $row['logo_url']));
    $website_url = array_key_exists('website_url', $data) ? trim((string)$data['website_url']) : ($row['website_url'] ?? '');
    $visible = array_key_exists('visible', $data) ? (!empty($data['visible']) ? 1 : 0) : (int)$row['visible'];
    $sort_order = array_key_exists('sort_order', $data) ? (int)$data['sort_order'] : (int)$row['sort_order'];

    if ($name === '') send_error('Name is required', 400);
    if ($logo_url === '') send_error('Logo URL is required', 400);

    $now = db_now($CONFIG);
    $pdo->prepare(
        'UPDATE sponsors SET name = ?, logo_url = ?, website_url = ?, visible = ?, sort_order = ?, updated_at = ? WHERE id = ?'
    )->execute([$name, $logo_url, $website_url !== '' ? $website_url : null, $visible, $sort_order, $now, $id]);
    $stmt->execute([$id]);
    send_json(['ok' => true, 'item' => _admin_sponsor_row($stmt->fetch())]);
}

/** DELETE /api/admin/sponsors/{id} — hard delete. */
function route_admin_sponsors_delete(array $CONFIG, int $id): void
{
    require_admin($CONFIG);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT id FROM sponsors WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) send_error('Sponsor not found', 404);
    $pdo->prepare('DELETE FROM sponsors WHERE id = ?')->execute([$id]);
    send_json(['ok' => true]);
}
