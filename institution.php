<?php
/**
 * Public institution page — v5pro.
 * Server-renders SEO tags (title, OG, Twitter Card, JSON-LD).
 * Body hydrated by /assets/js/institution.js.
 */
$_brand = isset($_GET['brand']) ? (string)$_GET['brand'] : '';
$_slug  = isset($_GET['slug'])  ? (string)$_GET['slug']  : '';
$_inst  = null;
$_notFound = false;

if ($_brand !== '' && $_slug !== '') {
    try {
        require_once __DIR__ . '/api/bootstrap.php';
        $stmt = db($CONFIG)->prepare(
            "SELECT * FROM institutions WHERE brand = ? AND slug = ? AND status IN ('verified','seeded') LIMIT 1"
        );
        $stmt->execute([$_brand, $_slug]);
        $_inst = $stmt->fetch() ?: null;
        if (!$_inst) $_notFound = true;
    } catch (Throwable $_e) { $_inst = null; }
}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }

$_siteUrl = isset($CONFIG) ? rtrim((string)($CONFIG['site_url'] ?? ''), '/') : '';
if ($_siteUrl === '') { $_siteUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'); }
$_canonical = $_brand && $_slug ? $_siteUrl . '/i/' . rawurlencode($_brand) . '/' . rawurlencode($_slug) : $_siteUrl;
$_brandLabel = isset($CONFIG) ? (string)($CONFIG['brand_name'] ?? 'institution.bd') : 'institution.bd';

if ($_inst) {
    $_subdomain = $_inst['slug'] . '.' . $_inst['brand'];
    $_title = ($_inst['name_en'] ?: $_subdomain) . ' — ' . $_brandLabel;
    $_descBase = trim((string)($_inst['about_en'] ?: $_inst['about_bn'] ?: ''));
    if ($_descBase === '') {
        $_place = trim(implode(', ', array_filter([$_inst['upazila']??'', $_inst['district']??'', $_inst['division']??''])));
        $_descBase = ($_inst['name_en'] ?: $_subdomain) . ($_place ? ' · '.$_place : '') . '. Verified on ' . $_brandLabel . '.';
    }
    if (mb_strlen($_descBase) > 200) $_descBase = rtrim(mb_substr($_descBase, 0, 197)) . '…';
    $_image = $_inst['banner_url'] ?: ($_inst['logo_url'] ?: '');
    if ($_image && $_image[0] === '/') $_image = $_siteUrl . $_image;
} else {
    $_subdomain = ($_brand && $_slug) ? ($_slug . '.' . $_brand) : '';
    $_title = $_subdomain ? ($_subdomain . ' — ' . $_brandLabel) : ('Institution not found — ' . $_brandLabel);
    $_descBase = $_subdomain ? ('No institution has claimed ' . $_subdomain . ' yet.') : 'Browse institutions on ' . $_brandLabel;
    $_image = '';
}
$h = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); };

$_jsonLd = null;
if ($_inst) {
    $_addr = trim(implode(', ', array_filter([$_inst['address']??'', $_inst['upazila']??'', $_inst['district']??'', $_inst['division']??''])));
    $_jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => ($_inst['category'] === 'ngo') ? 'NGO' : 'EducationalOrganization',
        'name' => $_inst['name_en'],
        'alternateName' => $_inst['name_bn'] ?: null,
        'url' => 'https://' . $_subdomain,
        'logo' => $_inst['logo_url'] ?: null,
        'image' => $_inst['banner_url'] ?: null,
        'email' => $_inst['contact_email'] ?: null,
        'telephone' => $_inst['contact_phone'] ?: null,
        'description' => $_inst['about_en'] ?: null,
        'address' => $_addr ? ['@type' => 'PostalAddress', 'streetAddress' => $_addr, 'addressCountry' => 'BD'] : null,
    ], static fn($v) => $v !== null && $v !== '');
}
if ($_notFound) http_response_code(404);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= $h($_title) ?></title>
  <meta name="description" content="<?= $h($_descBase) ?>">
  <link rel="canonical" href="<?= $h($_canonical) ?>">
  <?php if ($_notFound): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= $h($_title) ?>">
  <meta property="og:description" content="<?= $h($_descBase) ?>">
  <meta property="og:url" content="<?= $h($_canonical) ?>">
  <?php if ($_image): ?><meta property="og:image" content="<?= $h($_image) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= $_image ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= $h($_title) ?>">
  <meta name="twitter:description" content="<?= $h($_descBase) ?>">
  <?php if ($_jsonLd): ?>
  <script type="application/ld+json"><?= json_encode($_jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link rel="manifest" href="/manifest.webmanifest">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-v5 sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/">
      <img src="/assets/img/logo.svg" alt="">
      <span><span>institution.bd</span><span class="brand-sub">smartschool.bd</span></span>
    </a>
    <div class="d-flex align-items-center gap-2 order-lg-3">
      <button class="btn-theme-toggle" type="button" data-theme-toggle><i class="bi bi-moon-stars-fill"></i></button>
      <span data-user-chip></span>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#instNav"><span class="navbar-toggler-icon"></span></button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="instNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/directory.php">Directory</a></li>
        <li class="nav-item"><a class="nav-link" href="/claim.php">Claim</a></li>
        <li class="nav-item" data-auth-only hidden><a class="nav-link" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i> My Dashboard <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(আমার ড্যাশবোর্ড)</span></a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Institution Content (hydrated by JS) -->
<section class="py-4">
  <div class="container">
    <div data-inst-host>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <div class="skeleton-v5" style="height:200px;"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer-v5">
  <div class="container">
    <div class="footer-bottom d-flex justify-content-between flex-wrap gap-2">
      <span>&copy; <span data-year></span> institution.bd</span>
      <span>
        <a href="/">Home</a> &middot;
        <a href="/directory.php">Directory</a> &middot;
        <a href="/claim.php">Claim</a> &middot;
        <a href="/privacy.php">Privacy</a> &middot;
        <a href="/terms.php">Terms</a>
      </span>
    </div>
  </div>
</footer>

<div class="toast-container-v5" id="toastHost"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/institution.js"></script>
</body>
</html>
