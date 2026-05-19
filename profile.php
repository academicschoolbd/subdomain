<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Edit Profile — institution.bd</title>
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
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#profileNav" aria-label="Menu">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="profileNav">
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
      <p class="text-muted">Sign in to edit your profile.</p>
      <button class="btn btn-primary" data-open-auth>
        <i class="bi bi-box-arrow-in-right me-2"></i> Sign in
      </button>
    </div>
  </div>
</section>


<!-- ===== PROFILE FORM ===== -->
<section class="py-5" data-profile-root hidden>
  <div class="container" style="max-width:720px;">
    <div class="mb-4">
      <h3><i class="bi bi-person-circle me-2 text-primary"></i>Edit Profile</h3>
      <p class="text-muted">Keep your profile up to date so we can verify your domain claims faster.</p>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form data-profile-form novalidate>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Full Name</label>
              <input type="text" class="form-control" name="name" autocomplete="name" placeholder="Your full name">
              <div class="invalid-feedback" data-err="name"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Mobile</label>
              <input type="tel" class="form-control" name="mobile" autocomplete="tel" placeholder="01XXXXXXXXX">
              <div class="invalid-feedback" data-err="mobile"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Designation (Bengali)</label>
              <input type="text" class="form-control" name="designation_bn" placeholder="e.g. প্রধান শিক্ষক">
              <div class="invalid-feedback" data-err="designation_bn"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Institution Name</label>
              <input type="text" class="form-control" name="institution_name" placeholder="Your institution name">
              <div class="invalid-feedback" data-err="institution_name"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Division</label>
              <select class="form-select" name="division" data-bd-division></select>
              <div class="invalid-feedback" data-err="division"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">District</label>
              <select class="form-select" name="district" data-bd-district></select>
              <div class="invalid-feedback" data-err="district"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Upazila</label>
              <select class="form-select" name="upazila" data-bd-upazila></select>
              <div class="invalid-feedback" data-err="upazila"></div>
            </div>
            <div class="col-12 text-end pt-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save Profile
              </button>
            </div>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" type="module"></script>
<script src="/assets/js/bd-locations.js"></script>
<script src="/assets/js/profile.js"></script>
</body>
</html>
