<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Claim your subdomain — institution.bd</title>
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

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-v5 sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/">
      <img src="/assets/img/logo.svg" alt="institution.bd">
      <span><span>institution.bd</span><span class="brand-sub">smartschool.bd</span></span>
    </a>
    <div class="d-flex align-items-center gap-2 order-lg-3">
      <button class="btn-theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme"><i class="bi bi-moon-stars-fill"></i></button>
      <span data-user-chip></span>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#claimNav"><span class="navbar-toggler-icon"></span></button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="claimNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/directory.php">Directory</a></li>
        <li class="nav-item"><a class="nav-link active" href="/claim.php">Claim</a></li>
        <li class="nav-item"><a class="nav-link" href="/dashboard.php">Dashboard</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Claim Wizard -->
<section class="py-5">
  <div class="container" style="max-width:780px;">
    <div class="text-center mb-4">
      <h2><i class="bi bi-plus-circle-fill text-primary me-2"></i>Claim your free subdomain</h2>
      <p class="text-muted">Choose your brand, pick a name, fill in institution details — go live in minutes.</p>
    </div>

    <!-- Wizard Steps Indicator -->
    <div class="d-flex justify-content-center gap-2 mb-4" data-wizard-steps>
      <span class="badge rounded-pill bg-primary">1. Choose name</span>
      <span class="badge rounded-pill bg-secondary-subtle text-muted">2. Institution info</span>
      <span class="badge rounded-pill bg-secondary-subtle text-muted">3. Confirmed</span>
    </div>

    <!-- Step 1: Slug Selection -->
    <div class="card border-0 shadow-sm" data-step="1">
      <div class="card-body p-4">
        <h5 class="mb-3"><i class="bi bi-globe2 me-2 text-primary"></i>Choose your subdomain</h5>
        <form data-claim-slug-form>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Subdomain name</label>
              <div class="input-group">
                <input type="text" class="form-control" name="slug" placeholder="your-school-name" required minlength="3" maxlength="40" autocomplete="off" data-slug-input>
                <select class="form-select" style="max-width:180px;" name="brand" data-brand-select>
                  <option value="institution.bd">.institution.bd</option>
                  <option value="smartschool.bd">.smartschool.bd</option>
                </select>
              </div>
              <div class="form-text" data-slug-status></div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Check availability</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Step 2: Institution Details -->
    <div class="card border-0 shadow-sm mt-3" data-step="2" hidden>
      <div class="card-body p-4">
        <h5 class="mb-3"><i class="bi bi-building me-2 text-primary"></i>Institution details</h5>
        <form data-claim-details-form>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small fw-semibold">Institution name (English)</label><input class="form-control" name="name_en" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">প্রতিষ্ঠানের নাম (বাংলা)</label><input class="form-control" name="name_bn"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Category</label>
              <select class="form-select" name="category">
                <option value="">— Select —</option>
                <option>School</option><option>College</option><option>University</option>
                <option>Madrasa</option><option>Polytechnic</option><option>Training Institute</option>
                <option>NGO</option><option>Other</option>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label small fw-semibold">EIIN <span class="text-muted">(optional)</span></label><input class="form-control" name="eiin"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Division</label><select class="form-select" name="division" data-bd-division><option value="">—</option></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">District</label><select class="form-select" name="district" data-bd-district><option value="">—</option></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Upazila</label><select class="form-select" name="upazila" data-bd-upazila><option value="">—</option></select></div>
            <div class="col-12"><label class="form-label small fw-semibold">Address</label><input class="form-control" name="address"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Contact name</label><input class="form-control" name="contact_name"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Contact phone</label><input class="form-control" name="contact_phone"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Contact email</label><input class="form-control" name="contact_email" type="email"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Website <span class="text-muted">(optional)</span></label><input class="form-control" name="website"></div>
            <div class="col-12 text-end">
              <button class="btn btn-primary" type="submit"><i class="bi bi-shield-check me-1"></i> Review & Submit</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Step 3: Success -->
    <div class="card border-0 shadow-sm mt-3 text-center p-5" data-step="3" hidden>
      <i class="bi bi-check-circle-fill text-success fs-1 mb-3 d-block"></i>
      <h4>Claim submitted successfully!</h4>
      <p class="text-muted">Your subdomain request has been sent to the admin for review. You'll be notified once it's approved.</p>
      <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="/dashboard.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i> Go to Dashboard</a>
        <a href="/directory.php" class="btn btn-outline-secondary"><i class="bi bi-grid me-1"></i> Browse Directory</a>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer-v5">
  <div class="container">
    <div class="footer-bottom d-flex justify-content-between flex-wrap gap-2">
      <span>&copy; <span data-year></span> institution.bd</span>
      <span><a href="/">Home</a> &middot; <a href="/directory.php">Directory</a> &middot; <a href="/privacy.php">Privacy</a> &middot; <a href="/terms.php">Terms</a></span>
    </div>
  </div>
</footer>

<div class="toast-container-v5" id="toastHost"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" type="module"></script>
<script src="/assets/js/bd-locations.js"></script>
<script src="/assets/js/claim.js" type="module"></script>
</body>
</html>
