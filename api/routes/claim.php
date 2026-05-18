<?php
declare(strict_types=1);

/** Claim + owner/tenant routes. */

const ALLOWED_DOC_TYPES = ['nid', 'eiin_certificate', 'board_letter', 'trade_license', 'selfie', 'other'];
const ALLOWED_DOC_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];
const ALLOWED_IMG_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_DOC_BYTES = 8 * 1024 * 1024;
const MAX_IMG_BYTES = 4 * 1024 * 1024;

function _ensure_owner(array $CONFIG, int $instId, int $userId, bool $isAdmin): array
{
    $stmt = db($CONFIG)->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    $inst = $stmt->fetch();
    if (!$inst) send_error('Institution not found', 404);
    if (!$isAdmin && (int)($inst['owner_user_id'] ?? 0) !== $userId) {
        send_error('Forbidden', 403);
    }
    return $inst;
}

function _save_upload(array $CONFIG, array $file, string $subdir, array $allowedMime, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        send_error('Upload failed', 400);
    }
    if ((int)$file['size'] > $maxBytes) {
        send_error('File too large (max ' . round($maxBytes / 1024 / 1024) . ' MB).', 413);
    }
    // Detect MIME server-side rather than trusting the browser.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: $file['type'];
    if (!in_array($mime, $allowedMime, true)) {
        send_error('Unsupported file type: ' . $mime, 415);
    }
    $base = $CONFIG['uploads_dir'];
    if (!is_dir($base)) @mkdir($base, 0775, true);
    $dir = rtrim($base, '/') . '/' . $subdir;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: '';
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $fname = bin2hex(random_bytes(8)) . '_' . time() . ($ext ? ('.' . strtolower($ext)) : '');
    $dest = $dir . '/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        send_error('Could not store upload', 500);
    }
    @chmod($dest, 0644);
    $publicPath = rtrim($CONFIG['uploads_public_prefix'], '/') . '/' . $subdir . '/' . $fname;
    return ['path' => $dest, 'public' => $publicPath, 'mime' => $mime, 'filename' => $file['name']];
}

function route_claim_submit(array $CONFIG): void
{
    $u = require_user($CONFIG);

    // Gate: profile must be complete before submitting a claim.
    if (empty($u['profile_complete'])) {
        send_json([
            'ok' => false,
            'profile_incomplete' => true,
            'missing' => $u['profile_missing'] ?? profile_missing_fields($u),
            'detail' => 'Please complete your profile (full name, mobile, designation, institution and location) before claiming a subdomain.',
        ], 422);
    }

    // v3.2 — light per-user rate limit: 5 NEW claims per hour per account.
    // Uses the audit_log we already write on every successful claim. Admins
    // are exempt so they can seed institutions in bulk.
    if (empty($u['is_admin'])) {
        try {
            $stmt = db($CONFIG)->prepare(
                "SELECT COUNT(*) c FROM audit_log
                  WHERE actor_user_id = ?
                    AND action IN ('claim.create', 'claim.seeded_taken')
                    AND created_at > ?"
            );
            $stmt->execute([(int)$u['id'], date('Y-m-d H:i:s', time() - 3600)]);
            if ((int)$stmt->fetch()['c'] >= 5) {
                send_error(
                    'Too many claims in the last hour. Please wait a bit before submitting another — '
                  . 'or contact support if you need to onboard several institutions at once.',
                    429
                );
            }
        } catch (PDOException $e) { /* never block on a counter glitch */ }
    }

    $data = read_json_body();
    $brand = require_param($data, 'brand');
    if (!in_array($brand, $CONFIG['brands'], true)) {
        send_error('Unknown brand', 400);
    }
    $rawSlug = (string)require_param($data, 'slug');
    $slug = normalize_slug($rawSlug);
    [$ok, $err] = slug_validity($CONFIG, $slug);
    if (!$ok) send_error($err, 400);
    $name_en = trim((string)require_param($data, 'name_en'));
    $category = trim((string)require_param($data, 'category'));
    $name_bn = trim((string)($data['name_bn'] ?? ''));
    $division = $data['division'] ?? null;
    $district = $data['district'] ?? null;
    $upazila = $data['upazila'] ?? null;
    $address = $data['address'] ?? null;
    $eiin = $data['eiin'] ?? null;
    $contact_name = $data['contact_name'] ?? null;
    $contact_phone = $data['contact_phone'] ?? null;
    $contact_email = $data['contact_email'] ?? null;
    $website = $data['website'] ?? null;
    $about_en = $data['about_en'] ?? null;
    $about_bn = $data['about_bn'] ?? null;

    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM institutions WHERE brand = ? AND slug = ?');
    $stmt->execute([$brand, $slug]);
    $existing = $stmt->fetch();
    $now = db_now($CONFIG);

    // v3.0 — settings-driven mode. When require_documents is OFF (default),
    // the claim auto-verifies and DNS is created right away. When ON, the
    // claim lands in "pending" and the admin must approve as before.
    $requireDocs = settings_get_bool($CONFIG, 'require_documents', false);
    $autoDns     = settings_get_bool($CONFIG, 'cloudflare_auto_dns', true);
    $initStatus  = $requireDocs ? 'pending' : 'verified';
    $verifiedAt  = $requireDocs ? null : $now;

    if ($existing) {
        if ($existing['status'] !== 'seeded') {
            send_error('This subdomain is already taken or under review.', 409);
        }
        // Claim seeded placeholder.
        $pdo->prepare(
            'UPDATE institutions SET
                name_en = COALESCE(?, name_en), name_bn = COALESCE(?, name_bn),
                category = COALESCE(?, category), division = COALESCE(?, division),
                district = COALESCE(?, district), upazila = COALESCE(?, upazila),
                address = ?, eiin = ?, contact_name = ?, contact_phone = ?,
                contact_email = ?, website = ?, about_bn = ?, about_en = ?,
                status = ?, verified_at = ?, owner_user_id = ?
             WHERE id = ?'
        )->execute([
            $name_en ?: null, $name_bn ?: null, $category ?: null, $division ?: null,
            $district ?: null, $upazila ?: null, $address, $eiin, $contact_name,
            $contact_phone, $contact_email, $website, $about_bn, $about_en,
            $initStatus, $verifiedAt,
            (int)$u['id'], (int)$existing['id'],
        ]);
        $instId = (int)$existing['id'];
        audit($CONFIG, (int)$u['id'], $instId, 'claim.seeded_taken');
    } else {
        $pdo->prepare(
            'INSERT INTO institutions
              (brand, slug, name_en, name_bn, category, division, district, upazila,
               address, eiin, contact_name, contact_phone, contact_email, website,
               about_bn, about_en, status, owner_user_id, created_at, verified_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $brand, $slug, $name_en, $name_bn ?: null, $category, $division, $district, $upazila,
            $address, $eiin, $contact_name, $contact_phone, $contact_email, $website,
            $about_bn, $about_en, $initStatus, (int)$u['id'], $now, $verifiedAt,
        ]);
        $instId = (int)$pdo->lastInsertId();
        audit($CONFIG, (int)$u['id'], $instId, 'claim.create',
              json_encode(['brand' => $brand, 'slug' => $slug, 'auto_verified' => !$requireDocs]));
    }

    // If auto-verify is on, fire Cloudflare DNS right away (best-effort).
    if (!$requireDocs && $autoDns) {
        $cf = cf_create_record($CONFIG, $brand, $slug);
        $dnsStatus = $cf['attempted'] ? ($cf['ok'] ? 'live' : 'error') : 'manual';
        $pdo->prepare(
            'UPDATE institutions SET cf_record_id = ?, dns_status = ?, dns_message = ? WHERE id = ?'
        )->execute([
            $cf['ok'] ? $cf['record_id'] : null,
            $dnsStatus,
            $cf['message'],
            $instId,
        ]);
        audit($CONFIG, (int)$u['id'], $instId, 'claim.auto_dns.' . $dnsStatus, $cf['message']);
    }

    $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    send_json(['ok' => true, 'claim' => _claim_row($stmt->fetch()), 'auto_verified' => !$requireDocs]);
}

function _claim_row(array $r): array
{
    return [
        'id' => (int)$r['id'],
        'brand' => $r['brand'],
        'slug' => $r['slug'],
        'subdomain' => $r['slug'] . '.' . $r['brand'],
        'name_en' => $r['name_en'],
        'name_bn' => $r['name_bn'],
        'category' => $r['category'],
        'division' => $r['division'],
        'district' => $r['district'],
        'upazila' => $r['upazila'],
        'address' => $r['address'] ?? null,
        'eiin' => $r['eiin'] ?? null,
        'contact_name' => $r['contact_name'] ?? null,
        'contact_phone' => $r['contact_phone'] ?? null,
        'contact_email' => $r['contact_email'] ?? null,
        'website' => $r['website'] ?? null,
        'about_bn' => $r['about_bn'] ?? null,
        'about_en' => $r['about_en'] ?? null,
        'logo_url' => $r['logo_url'] ?? null,
        'banner_url' => $r['banner_url'] ?? null,
        'status' => $r['status'],
        'review_notes' => $r['review_notes'] ?? null,
        'cf_record_id' => $r['cf_record_id'] ?? null,
        'dns_status' => $r['dns_status'] ?? null,
        'dns_message' => $r['dns_message'] ?? null,
        'verified_at' => $r['verified_at'] ?? null,
        'created_at' => $r['created_at'] ?? null,
    ];
}

function route_claim_upload_doc(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    if (!isset($_FILES['file'])) send_error('Send file as multipart field "file".', 400);
    $docType = $_POST['doc_type'] ?? 'other';
    if (!in_array($docType, ALLOWED_DOC_TYPES, true)) {
        send_error('Unknown doc_type', 400);
    }
    $info = _save_upload($CONFIG, $_FILES['file'], 'docs/inst_' . $instId, ALLOWED_DOC_MIME, MAX_DOC_BYTES);
    db($CONFIG)->prepare(
        'INSERT INTO claim_documents (institution_id, doc_type, filename, stored_path, content_type, uploaded_at) VALUES (?,?,?,?,?,?)'
    )->execute([$instId, $docType, $info['filename'], $info['path'], $info['mime'], db_now($CONFIG)]);
    $docId = (int)db($CONFIG)->lastInsertId();
    audit($CONFIG, (int)$u['id'], $instId, 'claim.upload_doc', $docType);
    send_json(['ok' => true, 'document' => [
        'id' => $docId, 'doc_type' => $docType, 'filename' => $info['filename'], 'content_type' => $info['mime'],
    ]]);
}

function route_claim_mine(array $CONFIG): void
{
    $u = require_user($CONFIG);
    $stmt = db($CONFIG)->prepare(
        'SELECT * FROM institutions WHERE owner_user_id = ? ORDER BY id DESC'
    );
    $stmt->execute([(int)$u['id']]);
    $claims = array_map('_claim_row', $stmt->fetchAll());
    send_json(['claims' => $claims]);
}

/**
 * DELETE /api/claims/{id}  —  v3.0
 * Owner withdraws a claim. Allowed while the claim is in pending / needs_info /
 * rejected. Verified or seeded claims must be released by an admin (suspend),
 * since they may already have a live DNS record and a public directory entry.
 *
 * Side effects:
 *   - claim_documents rows for this institution are deleted (rows + files)
 *   - notices for this institution are deleted
 *   - if it was a *seeded* placeholder originally, we don't reach this branch
 *     (status='seeded' is rejected at the gate above)
 *   - the institutions row itself is removed entirely
 *
 * The DNS record is *not* touched here because withdrawable statuses never
 * have one provisioned. (Approved => verified => admin-only path.)
 */
function route_claim_withdraw(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    $inst = $stmt->fetch();
    if (!$inst) send_error('Not found', 404);

    if (!$u['is_admin'] && (int)($inst['owner_user_id'] ?? 0) !== (int)$u['id']) {
        send_error('Forbidden', 403);
    }
    // Owners can withdraw while:
    //   - not verified yet (pending / needs_info / rejected), or
    //   - verified via the legacy v3.0 instant-claim flow AND moderation is
    //     currently OFF (require_documents = 0). When admin moderation is ON
    //     (the v3.2 default), an approved claim is the admin's call to
    //     suspend, not the owner's.
    // Admins can always withdraw via this route (suspend is the moderation path).
    $statusOk = in_array($inst['status'], ['pending', 'needs_info', 'rejected'], true);
    if (!$statusOk && $inst['status'] === 'verified'
        && !settings_get_bool($CONFIG, 'require_documents', false)) {
        $cnt = $pdo->prepare('SELECT COUNT(*) c FROM claim_documents WHERE institution_id = ?');
        $cnt->execute([$instId]);
        $statusOk = ((int)$cnt->fetch()['c']) === 0;  // verified-but-doc-less = self-service eligible
    }
    if (!$statusOk && !$u['is_admin']) {
        send_error(
            'This claim has uploaded documents and is verified — please contact admin to suspend it.',
            409
        );
    }

    // If a Cloudflare DNS record was provisioned, best-effort delete it now.
    if (!empty($inst['cf_record_id']) || ($inst['dns_status'] ?? '') === 'live') {
        cf_delete_record($CONFIG, $inst['brand'], $inst['cf_record_id'] ?? null, $inst['slug']);
    }

    // 1) Delete uploaded documents (rows + files on disk).
    $docs = $pdo->prepare('SELECT id, stored_path FROM claim_documents WHERE institution_id = ?');
    $docs->execute([$instId]);
    foreach ($docs->fetchAll() as $d) {
        if (!empty($d['stored_path']) && is_file($d['stored_path'])) {
            @unlink($d['stored_path']);
        }
    }
    $pdo->prepare('DELETE FROM claim_documents WHERE institution_id = ?')->execute([$instId]);

    // 2) Delete notices (none should exist on a non-verified claim, but be safe).
    $pdo->prepare('DELETE FROM notices WHERE institution_id = ?')->execute([$instId]);

    // 3) Delete the institution row itself.
    $pdo->prepare('DELETE FROM institutions WHERE id = ?')->execute([$instId]);

    audit($CONFIG, (int)$u['id'], $instId, 'claim.withdraw', json_encode([
        'brand' => $inst['brand'], 'slug' => $inst['slug'], 'prev_status' => $inst['status'],
    ]));
    send_json(['ok' => true]);
}

function route_tenant_site_get(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    $docs = db($CONFIG)->prepare('SELECT id, doc_type, filename, content_type, uploaded_at FROM claim_documents WHERE institution_id = ? ORDER BY id DESC');
    $docs->execute([$instId]);
    $documents = array_map(function ($d) {
        $d['id'] = (int)$d['id'];
        return $d;
    }, $docs->fetchAll());
    send_json(['institution' => _claim_row($inst), 'documents' => $documents]);
}

function route_tenant_site_patch(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    $data = read_json_body();
    $editable = ['name_en','name_bn','category','division','district','upazila','address',
                 'eiin','contact_name','contact_phone','contact_email','website','about_en','about_bn'];
    $sets = [];
    $bind = [];
    foreach ($editable as $k) {
        if (array_key_exists($k, $data)) {
            $sets[] = "$k = ?";
            $bind[] = $data[$k];
        }
    }
    if (!$sets) send_error('Nothing to update', 400);
    $bind[] = $instId;
    db($CONFIG)->prepare('UPDATE institutions SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($bind);
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.site_update');
    $stmt = db($CONFIG)->prepare('SELECT * FROM institutions WHERE id = ?');
    $stmt->execute([$instId]);
    send_json(['ok' => true, 'institution' => _claim_row($stmt->fetch())]);
}

function route_tenant_upload_image(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    if (!isset($_FILES['file'])) send_error('Send file as multipart field "file".', 400);
    $kind = $_POST['kind'] ?? 'logo'; // logo | banner
    if (!in_array($kind, ['logo','banner'], true)) send_error('kind must be logo or banner', 400);
    $info = _save_upload($CONFIG, $_FILES['file'], 'images/inst_' . $instId, ALLOWED_IMG_MIME, MAX_IMG_BYTES);
    $col = $kind === 'logo' ? 'logo_url' : 'banner_url';
    db($CONFIG)->prepare("UPDATE institutions SET $col = ? WHERE id = ?")->execute([$info['public'], $instId]);
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.upload_image', $kind);
    send_json(['ok' => true, 'kind' => $kind, 'url' => $info['public']]);
}

function route_tenant_notices_list(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    $stmt = db($CONFIG)->prepare('SELECT id,title,body,pinned,created_at,updated_at FROM notices WHERE institution_id = ? ORDER BY pinned DESC, id DESC');
    $stmt->execute([$instId]);
    $list = array_map(function ($n) {
        $n['id'] = (int)$n['id'];
        $n['pinned'] = (bool)$n['pinned'];
        return $n;
    }, $stmt->fetchAll());
    send_json(['notices' => $list]);
}

function route_tenant_notice_create(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    $data = read_json_body();
    $title = trim((string)require_param($data, 'title'));
    $body = trim((string)require_param($data, 'body'));
    $pinned = !empty($data['pinned']) ? 1 : 0;
    $now = db_now($CONFIG);
    db($CONFIG)->prepare(
        'INSERT INTO notices (institution_id, title, body, pinned, created_at, updated_at) VALUES (?,?,?,?,?,?)'
    )->execute([$instId, $title, $body, $pinned, $now, $now]);
    $id = (int)db($CONFIG)->lastInsertId();
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.notice.create');
    send_json(['ok' => true, 'notice' => [
        'id' => $id, 'title' => $title, 'body' => $body, 'pinned' => (bool)$pinned,
        'created_at' => $now, 'updated_at' => $now,
    ]]);
}

function route_tenant_notice_delete(array $CONFIG, int $instId, int $noticeId): void
{
    $u = require_user($CONFIG);
    _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    db($CONFIG)->prepare('DELETE FROM notices WHERE id = ? AND institution_id = ?')->execute([$noticeId, $instId]);
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.notice.delete', (string)$noticeId);
    send_json(['ok' => true]);
}


/* =================================================================== */
/*  v3.2 — owner-managed DNS records for verified subdomains            */
/* =================================================================== */

const DNS_TYPES = ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS'];

function _dns_row(array $r): array
{
    return [
        'id'            => (int)$r['id'],
        'institution_id'=> (int)$r['institution_id'],
        'type'          => (string)$r['type'],
        'name'          => (string)$r['name'],
        'content'       => (string)$r['content'],
        'ttl'           => (int)($r['ttl'] ?? 1),
        'priority'      => $r['priority'] !== null && $r['priority'] !== '' ? (int)$r['priority'] : null,
        'proxied'       => (bool)($r['proxied'] ?? 0),
        'cf_record_id'  => $r['cf_record_id'] ?? null,
        'cf_status'     => $r['cf_status'] ?? null,
        'cf_message'    => $r['cf_message'] ?? null,
        'created_at'    => $r['created_at'] ?? null,
        'updated_at'    => $r['updated_at'] ?? null,
    ];
}

function _dns_validate(array $data): array
{
    $errors = [];
    $type = strtoupper(trim((string)($data['type'] ?? 'A')));
    if (!in_array($type, DNS_TYPES, true)) {
        $errors['type'] = 'Type must be one of: ' . implode(', ', DNS_TYPES);
    }
    $name = trim((string)($data['name'] ?? '@'));
    if ($name === '') $name = '@';
    if (strlen($name) > 120) {
        $errors['name'] = 'Name is too long (max 120 chars).';
    } elseif ($name !== '@' && !preg_match('/^[a-z0-9](?:[a-z0-9._\-]*[a-z0-9])?$/i', $name)) {
        $errors['name'] = 'Name must contain only letters, digits, dots and hyphens.';
    }
    $content = trim((string)($data['content'] ?? ''));
    if ($content === '') $errors['content'] = 'Content is required.';
    if (strlen($content) > 512) $errors['content'] = 'Content is too long (max 512 chars).';
    $ttl = (int)($data['ttl'] ?? 1);
    if ($ttl < 0) $ttl = 1;
    $priority = null;
    if ($type === 'MX') {
        $priority = isset($data['priority']) && $data['priority'] !== '' ? (int)$data['priority'] : 10;
        if ($priority < 0 || $priority > 65535) $errors['priority'] = 'MX priority must be 0–65535.';
    }
    $proxied = false;
    if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
        $proxied = !empty($data['proxied']);
    }
    return [$errors, [
        'type' => $type, 'name' => $name, 'content' => $content,
        'ttl' => $ttl, 'priority' => $priority, 'proxied' => $proxied,
    ]];
}

/** GET /api/tenant/{id}/dns — list every DNS record on this institution. */
function route_tenant_dns_list(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    if ($inst['status'] !== 'verified') {
        send_error('DNS records are only available after the subdomain is verified.', 409);
    }
    $stmt = db($CONFIG)->prepare(
        'SELECT * FROM dns_records WHERE institution_id = ? ORDER BY id ASC'
    );
    $stmt->execute([$instId]);
    $items = array_map('_dns_row', $stmt->fetchAll());
    send_json([
        'items'           => $items,
        'subdomain'       => $inst['slug'] . '.' . $inst['brand'],
        'cf_configured'   => cf_enabled($CONFIG, $inst['brand']),
    ]);
}

/** POST /api/tenant/{id}/dns — create a new DNS record. */
function route_tenant_dns_create(array $CONFIG, int $instId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    if ($inst['status'] !== 'verified') {
        send_error('Subdomain must be verified before adding DNS records.', 409);
    }
    $data = read_json_body();
    [$errors, $clean] = _dns_validate($data);
    if ($errors) send_json(['detail' => 'Invalid input', 'errors' => $errors], 422);

    // Push to Cloudflare (best-effort).
    $cf = cf_create_arbitrary_record(
        $CONFIG, $inst['brand'], $inst['slug'],
        $clean['type'], $clean['name'], $clean['content'],
        $clean['ttl'], $clean['priority'], $clean['proxied']
    );

    $now = db_now($CONFIG);
    $pdo = db($CONFIG);
    $pdo->prepare(
        'INSERT INTO dns_records
          (institution_id, type, name, content, ttl, priority, proxied,
           cf_record_id, cf_status, cf_message, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $instId, $clean['type'], $clean['name'], $clean['content'],
        $clean['ttl'], $clean['priority'], $clean['proxied'] ? 1 : 0,
        $cf['record_id'], $cf['status'] ?? ($cf['ok'] ? 'live' : 'manual'),
        $cf['message'], $now, $now,
    ]);
    $id = (int)$pdo->lastInsertId();
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.dns.create',
        $clean['type'] . ' ' . $clean['name'] . ' → ' . $clean['content']);
    $stmt = $pdo->prepare('SELECT * FROM dns_records WHERE id = ?');
    $stmt->execute([$id]);
    send_json(['ok' => true, 'record' => _dns_row($stmt->fetch()), 'cf' => $cf]);
}

/** PATCH /api/tenant/{id}/dns/{rid} — update a record. */
function route_tenant_dns_update(array $CONFIG, int $instId, int $recId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    if ($inst['status'] !== 'verified') {
        send_error('Subdomain must be verified.', 409);
    }
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM dns_records WHERE id = ? AND institution_id = ?');
    $stmt->execute([$recId, $instId]);
    $row = $stmt->fetch();
    if (!$row) send_error('Record not found', 404);

    $data = read_json_body();
    // Merge incoming fields onto current row so partial updates work.
    $merged = [
        'type'     => $data['type']     ?? $row['type'],
        'name'     => $data['name']     ?? $row['name'],
        'content'  => $data['content']  ?? $row['content'],
        'ttl'      => $data['ttl']      ?? $row['ttl'],
        'priority' => array_key_exists('priority', $data) ? $data['priority'] : $row['priority'],
        'proxied'  => array_key_exists('proxied', $data)  ? $data['proxied']  : $row['proxied'],
    ];
    [$errors, $clean] = _dns_validate($merged);
    if ($errors) send_json(['detail' => 'Invalid input', 'errors' => $errors], 422);

    // Push to Cloudflare. If we previously failed to create, try to create
    // (we have no record_id); otherwise update in place.
    if (!empty($row['cf_record_id'])) {
        $cf = cf_update_arbitrary_record(
            $CONFIG, $inst['brand'], $inst['slug'], (string)$row['cf_record_id'],
            $clean['type'], $clean['name'], $clean['content'],
            $clean['ttl'], $clean['priority'], $clean['proxied']
        );
    } else {
        $cf = cf_create_arbitrary_record(
            $CONFIG, $inst['brand'], $inst['slug'],
            $clean['type'], $clean['name'], $clean['content'],
            $clean['ttl'], $clean['priority'], $clean['proxied']
        );
    }

    $now = db_now($CONFIG);
    $pdo->prepare(
        'UPDATE dns_records SET type = ?, name = ?, content = ?, ttl = ?,
         priority = ?, proxied = ?, cf_record_id = ?, cf_status = ?,
         cf_message = ?, updated_at = ? WHERE id = ?'
    )->execute([
        $clean['type'], $clean['name'], $clean['content'], $clean['ttl'],
        $clean['priority'], $clean['proxied'] ? 1 : 0,
        $cf['record_id'] ?? $row['cf_record_id'],
        $cf['status']    ?? ($cf['ok'] ? 'live' : 'manual'),
        $cf['message'],
        $now, $recId,
    ]);
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.dns.update',
        $clean['type'] . ' ' . $clean['name'] . ' → ' . $clean['content']);
    $stmt = $pdo->prepare('SELECT * FROM dns_records WHERE id = ?');
    $stmt->execute([$recId]);
    send_json(['ok' => true, 'record' => _dns_row($stmt->fetch()), 'cf' => $cf]);
}

/** DELETE /api/tenant/{id}/dns/{rid} — delete a record. */
function route_tenant_dns_delete(array $CONFIG, int $instId, int $recId): void
{
    $u = require_user($CONFIG);
    $inst = _ensure_owner($CONFIG, $instId, (int)$u['id'], $u['is_admin']);
    $pdo = db($CONFIG);
    $stmt = $pdo->prepare('SELECT * FROM dns_records WHERE id = ? AND institution_id = ?');
    $stmt->execute([$recId, $instId]);
    $row = $stmt->fetch();
    if (!$row) send_error('Record not found', 404);

    if (!empty($row['cf_record_id'])) {
        cf_delete_record($CONFIG, $inst['brand'], (string)$row['cf_record_id'], $inst['slug']);
    }
    $pdo->prepare('DELETE FROM dns_records WHERE id = ?')->execute([$recId]);
    audit($CONFIG, (int)$u['id'], $instId, 'tenant.dns.delete',
        $row['type'] . ' ' . $row['name']);
    send_json(['ok' => true]);
}
