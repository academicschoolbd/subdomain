<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Complete your profile — institution.bd</title>
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link rel="manifest" href="/manifest.webmanifest">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>


<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg navbar-v5 sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand" href="/">
      <img src="/assets/img/logo.svg" alt="institution.bd">
      <span>
        <span>institution.bd</span>
        <span class="brand-sub">smartschool.bd</span>
      </span>
    </a>
    <div class="d-flex align-items-center gap-2 order-lg-3">
      <button class="btn-theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
        <i class="bi bi-moon-stars-fill"></i>
      </button>
      <span data-user-chip></span>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#profileCompleteNav" aria-label="Menu">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="profileCompleteNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-house me-1"></i> Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/directory.php"><i class="bi bi-grid me-1"></i> Directory</a></li>
        <li class="nav-item"><a class="nav-link" href="/claim.php"><i class="bi bi-plus-circle me-1"></i> Claim</a></li>
        <li class="nav-item"><a class="nav-link" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
      </ul>
    </div>
  </div>
</nav>


<!-- ===== NOT SIGNED IN ===== -->
<section class="py-5" data-needs-auth hidden>
  <div class="container">
    <div class="card border-0 shadow-sm text-center p-5" style="max-width:480px;margin:0 auto;">
      <div class="mb-3"><i class="bi bi-lock-fill fs-1 text-muted"></i></div>
      <h3>You're not signed in</h3>
      <p class="text-muted">Sign in to complete your profile.</p>
      <button class="btn btn-primary" data-open-auth>
        <i class="bi bi-box-arrow-in-right me-2"></i> Sign in
      </button>
    </div>
  </div>
</section>


<!-- ===== PROFILE COMPLETION FORM ===== -->
<section class="py-5" data-profile-complete-root hidden>
  <div class="container" style="max-width:560px;">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <div class="mb-3"><i class="bi bi-person-check-fill fs-1 text-primary"></i></div>
          <h3 class="mb-2">Complete your profile to continue</h3>
          <p class="text-muted small mb-0">
            We need three quick details before you can claim a subdomain. This is a
            <strong>one-time</strong> step — you won't be asked again.
          </p>
        </div>

        <div class="progress mb-4" role="progressbar" aria-label="Profile completion"
             aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
             style="height: 0.75rem;" data-progress-host>
          <div class="progress-bar bg-primary" data-progress-bar style="width:0%">0%</div>
        </div>

        <form data-profile-complete-form novalidate>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="pcName">Full name</label>
            <input type="text" class="form-control" id="pcName" name="name"
                   autocomplete="name" maxlength="120" placeholder="Your full name" required>
            <div class="form-text">As you'd like it to appear on your account.</div>
            <div class="invalid-feedback" data-err="name"></div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold" for="pcMobile">Mobile number</label>
            <input type="tel" class="form-control" id="pcMobile" name="mobile"
                   autocomplete="tel" placeholder="01XXXXXXXXX" required>
            <div class="form-text">Bangladesh mobile — starts with 01 and has 11 digits.</div>
            <div class="invalid-feedback" data-err="mobile"></div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-semibold" for="pcDob">Date of birth</label>
            <input type="date" class="form-control" id="pcDob" name="date_of_birth"
                   min="1900-01-01" required>
            <div class="form-text">Used only to verify the account holder; we never publish it.</div>
            <div class="invalid-feedback" data-err="date_of_birth"></div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg" data-submit disabled>
              <i class="bi bi-check2-circle me-1"></i> Save &amp; Continue
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>


<!-- ===== FOOTER ===== -->
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

<!-- Toast Container -->
<div class="toast-container-v5" id="toastHost"></div>

<noscript>
  <div class="alert alert-warning text-center m-3">
    JavaScript is required to complete your profile. Please enable it and reload this page.
  </div>
</noscript>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" defer></script>
<script src="/assets/js/profile-complete.js" defer></script>
</body>
</html>
