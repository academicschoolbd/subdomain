<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <title>Reset password — institution.bd</title>
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>
<nav class="navbar navbar-v5 sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/"><img src="/assets/img/logo.svg" alt=""><span><span>institution.bd</span><span class="brand-sub">smartschool.bd</span></span></a>
    <button class="btn-theme-toggle" type="button" data-theme-toggle><i class="bi bi-moon-stars-fill"></i></button>
  </div>
</nav>
<main id="main" class="d-flex align-items-center justify-content-center" style="min-height:calc(100vh - 64px);padding:2rem 1rem;">
  <div class="card border-0 shadow-sm" style="max-width:440px;width:100%;">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width:56px;height:56px;"><i class="bi bi-key-fill fs-4"></i></div>
        <h4 class="mb-1">Reset your password</h4>
        <p class="text-muted small" data-reset-sub>Choose a new password for your account.</p>
      </div>
      <form data-reset-form novalidate>
        <div class="mb-3">
          <label class="form-label small fw-semibold">New password</label>
          <input type="password" class="form-control" name="password" required minlength="8" placeholder="At least 8 characters" autocomplete="new-password">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Confirm password</label>
          <input type="password" class="form-control" name="password_confirm" required minlength="8" placeholder="Repeat your new password" autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-100" type="submit" data-reset-submit><i class="bi bi-check-lg me-1"></i> Set new password</button>
      </form>
      <div class="text-center mt-3" data-reset-success hidden>
        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
        <h5>Password updated!</h5>
        <p class="text-muted small">You can now sign in with your new password.</p>
        <a href="/" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</a>
      </div>
      <div class="text-center mt-3" data-reset-error hidden>
        <i class="bi bi-exclamation-triangle-fill text-danger fs-1 d-block mb-2"></i>
        <h5>Reset failed</h5>
        <p class="text-muted small" data-reset-error-msg>This link may have expired or been used already.</p>
        <a href="/" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i> Back to home</a>
      </div>
    </div>
  </div>
</main>
<div class="toast-container-v5" id="toastHost"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/reset-password.js"></script>
</body>
</html>
