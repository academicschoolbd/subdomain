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
    send_json(['counts' => $counts, 'users' => $users, 'admins' => $admins]);
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
        $pdo->prepare(
            'UPDATE institutions SET status = ?, review_notes = ?, verified_at = ?,
             cf_record_id = ?, dns_status = ?, dns_message = ? WHERE id = ?'
        )->execute(['verified', $notes ?: null, $now, $cfRecordId, $dnsStatus, $cf['message'], $instId]);
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
    send_json([
        'settings' => [
            'require_documents'   => $s['require_documents']   === '1',
            'instant_claim'       => $s['instant_claim']       === '1',
            'cloudflare_auto_dns' => $s['cloudflare_auto_dns'] === '1',
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
    $allowed = ['require_documents', 'instant_claim', 'cloudflare_auto_dns'];
    $changed = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $data)) continue;
        $v = $data[$k] ? '1' : '0';
        settings_set($CONFIG, $k, $v);
        $changed[$k] = $v === '1';
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
