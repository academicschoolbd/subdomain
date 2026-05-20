<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Privacy Policy — institution.bd</title>
  <meta name="description" content="How institution.bd collects, uses, stores and protects your information.">
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
    <h1>Privacy Policy</h1>
    <p class="text-muted">Last updated: January 2025</p>
    <hr>
    <h5>1. Information We Collect</h5>
    <p>When you create an account we collect your name, email address, optional mobile number, and (if you use OAuth) basic profile information from Google, Facebook or GitHub. When you submit a claim we also collect institution details you provide.</p>
    <h5>2. How We Use It</h5>
    <p>Your information is used solely to operate the platform: authenticate you, process claims, communicate decisions, and display verified institution pages in the public directory.</p>
    <h5>3. Data Storage</h5>
    <p>Data is stored on servers secured by the platform administrator. Uploaded documents are stored on the server file system and are accessible only to admins for verification purposes.</p>
    <h5>4. Third Parties</h5>
    <p>We use Cloudflare for DNS and CDN services. OAuth providers (Google, Facebook, GitHub) receive only what is necessary for authentication. We do not sell or share your data with any other third party.</p>
    <h5>5. Your Rights</h5>
    <p>You may request deletion of your account and all associated data by contacting us via the WhatsApp support link. Verified institutions that have been published may remain in the directory for archival purposes.</p>
    <h5>6. Contact</h5>
    <p>For privacy-related questions, reach out via our <a href="/" data-wa-footer-support>WhatsApp support</a> channel.</p>
  </div>
</section>
<footer class="footer-v5">
  <div class="container"><div class="footer-bottom d-flex justify-content-between flex-wrap gap-2"><span>&copy; <span data-year></span> institution.bd</span><span><a href="/">Home</a> &middot; <a href="/terms.php">Terms</a></span></div></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" type="module"></script>
</body>
</html>
