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
    //   - verified via the v3.0 instant-claim flow (no docs ever uploaded).
    // Admins can always withdraw via this route (suspend is the moderation path).
    $statusOk = in_array($inst['status'], ['pending', 'needs_info', 'rejected'], true);
    if (!$statusOk && $inst['status'] === 'verified') {
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
