<?php
/**
 * Public institution page.
 *
 * v3.2 — emits real SEO tags on the server so Google, Facebook, Twitter and
 * link-preview bots see the right title / description / OG image / JSON-LD
 * before any JavaScript runs. The visible body is still hydrated by
 * /assets/js/institution.js (which keeps working unchanged).
 */
$_brand = isset($_GET['brand']) ? (string)$_GET['brand'] : '';
$_slug  = isset($_GET['slug'])  ? (string)$_GET['slug']  : '';
$_inst  = null;
$_notFound = false;

if ($_brand !== '' && $_slug !== '') {
    try {
        require_once __DIR__ . '/api/bootstrap.php';
        $stmt = db($CONFIG)->prepare(
            "SELECT * FROM institutions
              WHERE brand = ? AND slug = ?
                AND status IN ('verified','seeded')
              LIMIT 1"
        );
        $stmt->execute([$_brand, $_slug]);
        $_inst = $stmt->fetch() ?: null;
        if (!$_inst) $_notFound = true;
    } catch (Throwable $_e) {
        // Bootstrapping the API failed — fall through to the JS fallback,
        // which will render its own "not found" card.
        $_inst = null;
    }
}

/* ---- Compute the SEO strings up-front. ---- */
$_siteUrl  = isset($CONFIG) ? rtrim((string)($CONFIG['site_url'] ?? ''), '/') : '';
if ($_siteUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$_canonical = $_brand && $_slug
    ? $_siteUrl . '/i/' . rawurlencode($_brand) . '/' . rawurlencode($_slug)
    : $_siteUrl;

$_brandLabel = isset($CONFIG) ? (string)($CONFIG['brand_name'] ?? 'institution.bd') : 'institution.bd';

if ($_inst) {
    $_subdomain  = $_inst['slug'] . '.' . $_inst['brand'];
    $_title      = ($_inst['name_en'] ?: $_subdomain) . ' — ' . $_brandLabel;
    $_descBase   = trim((string)($_inst['about_en'] ?: $_inst['about_bn'] ?: ''));
    if ($_descBase === '') {
        $_place = trim(implode(', ', array_filter([$_inst['upazila'] ?? '', $_inst['district'] ?? '', $_inst['division'] ?? ''])));
        $_descBase = ($_inst['name_en'] ?: $_subdomain)
            . ($_place ? ' · ' . $_place : '')
            . ($_inst['category'] ? ' · ' . $_inst['category'] : '')
            . '. Verified institution profile on ' . $_brandLabel . '.';
    }
    // Trim to a clean ~ 160 chars without breaking words.
    if (mb_strlen($_descBase) > 200) {
        $_descBase = rtrim(mb_substr($_descBase, 0, 197)) . '…';
    }
    $_image = $_inst['banner_url'] ?: ($_inst['logo_url'] ?: '');
    if ($_image && $_image[0] === '/') $_image = $_siteUrl . $_image;
} else {
    $_subdomain = ($_brand && $_slug) ? ($_slug . '.' . $_brand) : '';
    $_title     = $_subdomain
        ? ($_subdomain . ' — claim this subdomain | ' . $_brandLabel)
        : ('Institution not found — ' . $_brandLabel);
    $_descBase  = $_subdomain
        ? ('No institution has claimed ' . $_subdomain . ' yet. Claim it for free on ' . $_brandLabel . ' — verified subdomain in under 60 seconds.')
        : 'Browse verified Bangladeshi institutions on institution.bd and smartschool.bd.';
    $_image = '';
}

/* ---- Tiny escape helper for the head. ---- */
$h = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); };

/* ---- JSON-LD payload (only when we have a real row). ---- */
$_jsonLd = null;
if ($_inst) {
    $_addr = trim(implode(', ', array_filter([
        $_inst['address']  ?? '', $_inst['upazila'] ?? '',
        $_inst['district'] ?? '', $_inst['division'] ?? '',
    ])));
    $_jsonLd = [
        '@context'      => 'https://schema.org',
        '@type'         => ($_inst['category'] === 'ngo') ? 'NGO' : 'EducationalOrganization',
        'name'          => $_inst['name_en'],
        'alternateName' => $_inst['name_bn'] ?: null,
        'url'           => 'https://' . $_subdomain,
        'sameAs'        => $_inst['website'] ?: null,
        'logo'          => $_inst['logo_url']   ?: null,
        'image'         => $_inst['banner_url'] ?: null,
        'email'         => $_inst['contact_email'] ?: null,
        'telephone'     => $_inst['contact_phone'] ?: null,
        'description'   => $_inst['about_en'] ?: ($_inst['about_bn'] ?: null),
        'address'       => $_addr ? ['@type' => 'PostalAddress', 'streetAddress' => $_addr, 'addressCountry' => 'BD'] : null,
        'identifier'    => $_inst['eiin'] ? ['@type' => 'PropertyValue', 'propertyID' => 'EIIN', 'value' => $_inst['eiin']] : null,
    ];
    $_jsonLd = array_filter($_jsonLd, static fn($v) => $v !== null && $v !== '' && $v !== []);
}

if ($_notFound) http_response_code(404);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

  <title data-page-title><?= $h($_title) ?></title>
  <meta name="description" content="<?= $h($_descBase) ?>" />
  <link rel="canonical" href="<?= $h($_canonical) ?>" />
  <?php if ($_notFound): ?>
  <meta name="robots" content="noindex,follow" />
  <?php endif; ?>

  <meta property="og:type"        content="website" />
  <meta property="og:title"       content="<?= $h($_title) ?>" />
  <meta property="og:description" content="<?= $h($_descBase) ?>" />
  <meta property="og:url"         content="<?= $h($_canonical) ?>" />
  <meta property="og:site_name"   content="<?= $h($_brandLabel) ?>" />
  <?php if ($_image): ?>
  <meta property="og:image"       content="<?= $h($_image) ?>" />
  <meta name="twitter:image"      content="<?= $h($_image) ?>" />
  <?php endif; ?>
  <meta name="twitter:card"        content="<?= $_image ? 'summary_large_image' : 'summary' ?>" />
  <meta name="twitter:title"       content="<?= $h($_title) ?>" />
  <meta name="twitter:description" content="<?= $h($_descBase) ?>" />

  <?php if ($_jsonLd): ?>
  <script type="application/ld+json"><?= json_encode($_jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>

  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
  <?php theme_emit_head_style($CONFIG ?? require __DIR__ . '/api/config.example.php'); ?>
</head>
<body>

<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/"><img src="/assets/img/logo.svg" alt="" /><span class="nav__brand-text"><span>institution.bd</span><small>smartschool.bd</small></span></a>
    <div class="nav__links"><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a></div>
    <div class="nav__cta">
      <span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a></div>
</nav>

<section style="padding-top:24px;">
  <div class="container">
    <div data-inst-host>
      <div class="card"><div class="skeleton" style="height:180px;"></div></div>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/directory.php">Directory</a> · <a href="/claim.php">Claim</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
    <span class="footer__heart"><span class="footer__heart-ico" aria-hidden="true">&#10084;&#65039;</span> Built with love in Bangladesh</span>
  </div>
  <div class="container footer__status" aria-live="polite">
    <span class="footer__status-pill" data-platform-status>
      <span class="footer__status-dot" aria-hidden="true"></span>
      <span data-platform-status-text>All systems operational</span>
    </span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/institution.js"></script>
</body>
</html>
