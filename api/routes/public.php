<?php
declare(strict_types=1);

/** Public routes: brands, slug-check, institutions search/get, notices. */

function _inst_card_row(array $r): array
{
    return [
        'id' => (int)$r['id'],
        'brand' => $r['brand'],
        'slug' => $r['slug'],
        'name_en' => $r['name_en'],
        'name_bn' => $r['name_bn'],
        'category' => $r['category'],
        'division' => $r['division'],
        'district' => $r['district'],
        'upazila' => $r['upazila'],
        'status' => $r['status'],
        'logo_url' => $r['logo_url'] ?? null,
        'verified' => $r['status'] === 'verified',
        'subdomain' => $r['slug'] . '.' . $r['brand'],
    ];
}

function _inst_public_row(array $r): array
{
    $card = _inst_card_row($r);
    return $card + [
        'address' => $r['address'] ?? null,
        'eiin' => $r['eiin'] ?? null,
        'contact_name' => $r['contact_name'] ?? null,
        'contact_phone' => $r['contact_phone'] ?? null,
        'contact_email' => $r['contact_email'] ?? null,
        'website' => $r['website'] ?? null,
        'banner_url' => $r['banner_url'] ?? null,
        'about_bn' => $r['about_bn'] ?? null,
        'about_en' => $r['about_en'] ?? null,
        'created_at' => $r['created_at'] ?? null,
        'verified_at' => $r['verified_at'] ?? null,
    ];
}

function route_public_settings(array $CONFIG): void
{
    // Public, safe-to-expose runtime config for the frontend.
    $oauth = [];
    foreach (oauth_enabled_providers($CONFIG) as $name => $meta) {
        $oauth[] = ['id' => $name, 'label' => $meta['label']];
    }
    $wa = $CONFIG['whatsapp'] ?? [];
    $supportNumber = preg_replace('/[^0-9]/', '', (string)($wa['support_number'] ?? ''));
    $supportMsg = (string)($wa['support_prefilled_message'] ?? '');
    $supportUrl = '';
    if ($supportNumber !== '') {
        $supportUrl = 'https://wa.me/' . $supportNumber;
        if ($supportMsg !== '') {
            $supportUrl .= '?text=' . rawurlencode($supportMsg);
        }
    }

    // v3.0 — surface the admin-toggleable platform settings + Cloudflare
    // status so the frontend can show "instant claim" or "needs review"
    // without a second round-trip.
    $platform = settings_get_all($CONFIG);
    $cfBrands = [];
    foreach (($CONFIG['brands'] ?? []) as $b) {
        $cfBrands[$b] = cf_enabled($CONFIG, $b);
    }

    send_json([
        'site' => [
            'url'      => rtrim((string)($CONFIG['site_url'] ?? ''), '/'),
            'name'     => (string)($CONFIG['brand_name'] ?? 'institution.bd'),
            'tagline'  => (string)($CONFIG['brand_tagline'] ?? ''),
            'brands'   => $CONFIG['brands'] ?? [],
            'demo_mode'=> !empty($CONFIG['demo_mode']),
        ],
        'auth' => [
            'oauth_providers' => $oauth,
            'phone_otp_enabled' => false,
            'email_password_enabled' => true,
        ],
        'whatsapp' => [
            'support_url'      => $supportUrl,
            'support_number'   => $supportNumber,
            'community_url'    => (string)($wa['community_url'] ?? ''),
            'community_title'  => (string)($wa['community_title'] ?? 'Join our WhatsApp community'),
            'community_subtitle' => (string)($wa['community_subtitle'] ?? ''),
        ],
        'platform' => [
            'require_documents'   => $platform['require_documents']   === '1',
            'instant_claim'       => $platform['instant_claim']       === '1',
            'cloudflare_auto_dns' => $platform['cloudflare_auto_dns'] === '1',
        ],
        'cloudflare' => [
            'configured'      => array_filter($cfBrands) ? true : false,
            'configured_brands' => array_keys(array_filter($cfBrands)),
        ],
    ]);
}

function route_public_brands(array $CONFIG): void
{
    $categories = [
        'institution.bd' => [
            ['id' => 'university',   'label' => 'University'],
            ['id' => 'college',      'label' => 'College'],
            ['id' => 'polytechnic',  'label' => 'Polytechnic'],
            ['id' => 'training',     'label' => 'Training Institute'],
            ['id' => 'ngo',          'label' => 'NGO / Foundation'],
            ['id' => 'other',        'label' => 'Other'],
        ],
        'smartschool.bd' => [
            ['id' => 'school',       'label' => 'School'],
            ['id' => 'madrasa',      'label' => 'Madrasa'],
            ['id' => 'kindergarten', 'label' => 'Kindergarten'],
            ['id' => 'coaching',     'label' => 'Coaching Center'],
            ['id' => 'other',        'label' => 'Other'],
        ],
    ];
    $brands = [];
    foreach ($CONFIG['brands'] as $b) {
        $brands[] = [
            'id' => $b,
            'label' => $b,
            'categories' => $categories[$b] ?? [],
        ];
    }
    send_json(['brands' => $brands]);
}

function route_public_slug_check(array $CONFIG): void
{
    $raw = $_GET['slug'] ?? '';
    $brand = $_GET['brand'] ?? '';
    if (!in_array($brand, $CONFIG['brands'], true)) {
        send_error('Unknown brand', 400);
    }
    $slug = normalize_slug((string)$raw);
    [$ok, $err] = slug_validity($CONFIG, $slug);
    if (!$ok) {
        send_json([
            'requested' => $raw, 'normalized' => $slug, 'available' => false,
            'reason' => $err, 'suggestion' => null,
        ]);
    }
    $stmt = db($CONFIG)->prepare(
        'SELECT id, status FROM institutions WHERE brand = ? AND slug = ?'
    );
    $stmt->execute([$brand, $slug]);
    $row = $stmt->fetch();
    if (!$row) {
        send_json([
            'requested' => $raw, 'normalized' => $slug, 'available' => true,
            'reason' => null, 'suggestion' => null,
        ]);
    }
    // If row is in seeded status it is "claimable" but not free in the strict
    // sense — return a flag.
    $claimable = $row['status'] === 'seeded';
    $suggestion = null;
    for ($i = 2; $i < 9; $i++) {
        $cand = $slug . '-' . $i;
        if (slug_is_reserved($CONFIG, $cand)) continue;
        $s = db($CONFIG)->prepare('SELECT 1 FROM institutions WHERE brand=? AND slug=?');
        $s->execute([$brand, $cand]);
        if (!$s->fetchColumn()) { $suggestion = $cand; break; }
    }
    send_json([
        'requested' => $raw, 'normalized' => $slug,
        'available' => false,
        'reason' => $claimable ? 'placeholder exists — you can claim it' : 'already taken',
        'claimable_placeholder' => $claimable,
        'institution_id' => (int)$row['id'],
        'suggestion' => $suggestion,
    ]);
}

function route_public_institutions(array $CONFIG): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $brand = $_GET['brand'] ?? null;
    $category = $_GET['category'] ?? null;
    $division = $_GET['division'] ?? null;
    $status = $_GET['status'] ?? 'verified';
    $limit = max(1, min(60, (int)($_GET['limit'] ?? 24)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $where = [];
    $bind = [];
    if ($status !== 'any') { $where[] = 'status = ?'; $bind[] = $status; }
    if ($brand) { $where[] = 'brand = ?'; $bind[] = $brand; }
    if ($category) { $where[] = 'category = ?'; $bind[] = $category; }
    if ($division) { $where[] = 'division = ?'; $bind[] = $division; }
    if ($q !== '') {
        $where[] = '(name_en LIKE ? OR name_bn LIKE ? OR slug LIKE ? OR eiin LIKE ?)';
        $like = '%' . $q . '%';
        array_push($bind, $like, $like, $like, $like);
    }
    $sql = 'SELECT * FROM institutions';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY (status = "verified") DESC, name_en ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
    // SQLite supports the boolean-expression ORDER BY; MySQL accepts it too.
    $stmt = db($CONFIG)->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll();
    $items = array_map('_inst_card_row', $rows);

    // Total
    $sqlc = 'SELECT COUNT(*) c FROM institutions';
    if ($where) $sqlc .= ' WHERE ' . implode(' AND ', $where);
    $stmt = db($CONFIG)->prepare($sqlc);
    $stmt->execute($bind);
    $total = (int)$stmt->fetch()['c'];
    send_json(['items' => $items, 'total' => $total]);
}

function route_public_stats(array $CONFIG): void
{
    $pdo = db($CONFIG);

    // Per-brand totals (kept for back-compat).
    $rows = $pdo->query("SELECT brand, status, COUNT(*) c FROM institutions GROUP BY brand, status")->fetchAll();
    $by_brand = [];
    foreach ($CONFIG['brands'] as $b) {
        $by_brand[$b] = ['brand' => $b, 'verified' => 0, 'pending' => 0, 'seeded' => 0, 'total' => 0];
    }
    foreach ($rows as $r) {
        $b = $r['brand'];
        if (!isset($by_brand[$b])) {
            $by_brand[$b] = ['brand' => $b, 'verified' => 0, 'pending' => 0, 'seeded' => 0, 'total' => 0];
        }
        if (isset($by_brand[$b][$r['status']])) {
            $by_brand[$b][$r['status']] = (int)$r['c'];
        }
        $by_brand[$b]['total'] += (int)$r['c'];
    }

    // Totals across all brands, broken out by status — this powers the four
    // realtime tiles on the homepage.
    $byStatus = ['verified' => 0, 'pending' => 0, 'needs_info' => 0, 'rejected' => 0, 'suspended' => 0, 'seeded' => 0];
    $statusRows = $pdo->query("SELECT status, COUNT(*) c FROM institutions GROUP BY status")->fetchAll();
    foreach ($statusRows as $r) {
        $byStatus[$r['status']] = (int)$r['c'];
    }
    // "Claimed" = a real human picked up this slug = anything that's not a
    // seeded placeholder.
    $claimsTotal     = $byStatus['verified'] + $byStatus['pending'] + $byStatus['needs_info']
                     + $byStatus['rejected'] + $byStatus['suspended'];
    $claimsRejected  = $byStatus['rejected'] + $byStatus['suspended'];

    // Users — total registered + (informational) verified-profile count.
    $usersCount = (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
    $usersWithProfile = 0;
    try {
        $usersWithProfile = (int)$pdo->query(
            "SELECT COUNT(*) c FROM users WHERE profile_completed_at IS NOT NULL"
        )->fetch()['c'];
    } catch (PDOException $e) {
        // pre-migration row layout — leave at 0
    }

    send_json([
        'brands' => array_values($by_brand),
        'totals' => [
            'users_registered'  => $usersCount,
            'users_with_profile'=> $usersWithProfile,
            'claims_total'      => $claimsTotal,
            'claims_pending'    => $byStatus['pending'] + $byStatus['needs_info'],
            'claims_verified'   => $byStatus['verified'],
            'claims_rejected'   => $claimsRejected,
            'directory_total'   => $byStatus['verified'] + $byStatus['seeded'],
            'seeded_placeholders' => $byStatus['seeded'],
        ],
        'generated_at' => date('c'),
    ]);
}

function route_public_get_institution(array $CONFIG, string $brand, string $slug): void
{
    if (!in_array($brand, $CONFIG['brands'], true)) {
        send_error('Unknown brand', 404);
    }
    $stmt = db($CONFIG)->prepare(
        "SELECT * FROM institutions WHERE brand = ? AND slug = ? AND status IN ('verified','seeded')"
    );
    $stmt->execute([$brand, $slug]);
    $row = $stmt->fetch();
    if (!$row) send_error('Not found', 404);
    send_json(['institution' => _inst_public_row($row)]);
}

function route_public_get_notices(array $CONFIG, string $brand, string $slug): void
{
    $stmt = db($CONFIG)->prepare(
        "SELECT id FROM institutions WHERE brand = ? AND slug = ? AND status = 'verified'"
    );
    $stmt->execute([$brand, $slug]);
    $row = $stmt->fetch();
    if (!$row) send_error('Not found', 404);
    $stmt = db($CONFIG)->prepare(
        "SELECT id, title, body, pinned, created_at FROM notices WHERE institution_id = ? ORDER BY pinned DESC, id DESC LIMIT 50"
    );
    $stmt->execute([(int)$row['id']]);
    $notices = array_map(function ($n) {
        $n['id'] = (int)$n['id'];
        $n['pinned'] = (bool)$n['pinned'];
        return $n;
    }, $stmt->fetchAll());
    send_json(['notices' => $notices]);
}

/**
 * GET /api/sitemap.xml — v3.0
 * A search-engine-friendly sitemap that lists every verified or seeded
 * institution page plus the static pages. The top-level /sitemap.xml shim
 * routes here so robots.txt can advertise a clean URL.
 */
function route_public_sitemap(array $CONFIG): void
{
    $base = rtrim((string)($CONFIG['site_url'] ?? ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    $static = ['/', '/directory', '/claim', '/privacy', '/terms'];
    $pdo = db($CONFIG);
    $stmt = $pdo->query(
        "SELECT brand, slug, COALESCE(verified_at, created_at) AS lastmod
           FROM institutions
          WHERE status IN ('verified','seeded')
       ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll();

    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/xml; charset=utf-8');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($static as $path) {
        echo "  <url><loc>" . htmlspecialchars($base . $path, ENT_XML1) . "</loc><changefreq>weekly</changefreq></url>\n";
    }
    foreach ($rows as $r) {
        $loc = $base . '/i/' . rawurlencode($r['brand']) . '/' . rawurlencode($r['slug']);
        $lm  = $r['lastmod'] ? date('Y-m-d', strtotime((string)$r['lastmod'])) : '';
        echo "  <url><loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>"
           . ($lm ? "<lastmod>$lm</lastmod>" : '')
           . "<changefreq>weekly</changefreq></url>\n";
    }
    echo "</urlset>\n";
    exit;
}
