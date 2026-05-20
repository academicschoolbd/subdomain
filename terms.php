<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Terms of Service — institution.bd</title>
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link rel="manifest" href="/manifest.webmanifest">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-v5 sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/"><img src="/assets/img/logo.svg" alt=""><span><span>institution.bd</span><span class="brand-sub">smartschool.bd</span></span></a>
    <div class="d-flex align-items-center gap-2">
      <button class="btn-theme-toggle" type="button" data-theme-toggle><i class="bi bi-moon-stars-fill"></i></button>
      <span data-user-chip></span>
    </div>
  </div>
</nav>
<section class="py-5">
  <div class="container" style="max-width:780px;">
    <h1>Terms of Service</h1>
    <p class="text-muted">Last updated: January 2025</p>
    <hr>
    <h5>1. Acceptance</h5>
    <p>By using institution.bd or smartschool.bd you agree to these terms. If you don't agree, please don't use the platform.</p>
    <h5>2. Eligibility</h5>
    <p>Subdomains are available to legitimate Bangladeshi educational institutions and NGOs. Commercial entities, personal blogs, and unrelated services are not eligible.</p>
    <h5>3. Acceptable Use</h5>
    <p>You may not use your subdomain for spam, phishing, malware distribution, impersonation, adult content, hate speech, or any illegal activity under Bangladeshi or international law.</p>
    <h5>4. Verification</h5>
    <p>Admins may request verification documents (EIIN certificate, board letter, trade license, NID). Providing fraudulent documents will result in immediate suspension.</p>
    <h5>5. DNS & Uptime</h5>
    <p>We provide DNS via Cloudflare on a best-effort basis. We do not guarantee 100% uptime and are not liable for losses caused by DNS downtime.</p>
    <h5>6. Suspension & Termination</h5>
    <p>We reserve the right to suspend or terminate any subdomain that violates these terms, with or without notice. DNS records will be removed upon suspension.</p>
    <h5>7. Modifications</h5>
    <p>We may update these terms at any time. Continued use after changes constitutes acceptance.</p>
    <h5>8. Contact</h5>
    <p>Questions? Reach us via <a href="/">WhatsApp support</a>.</p>
  </div>
</section>
<footer class="footer-v5">
  <div class="container"><div class="footer-bottom d-flex justify-content-between flex-wrap gap-2"><span>&copy; <span data-year></span> institution.bd</span><span><a href="/">Home</a> &middot; <a href="/privacy.php">Privacy</a></span></div></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" type="module"></script>
</body>
</html>
