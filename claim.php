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
        <li class="nav-item" data-auth-only hidden><a class="nav-link" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i> My Dashboard <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(আমার ড্যাশবোর্ড)</span></a></li>
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
        <h5 class="mb-3"><i class="bi bi-building me-2 text-primary"></i>Institution details <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;" class="text-muted small fw-normal">(প্রতিষ্ঠানের তথ্য)</span></h5>
        <form data-claim-details-form>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Institution name in English <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(প্রতিষ্ঠানের নাম ইংরেজিতে)</span></label>
              <input class="form-control" name="name_en" required>
              <div class="form-text"><span>Full official name as you want it displayed</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">পূর্ণ অফিসিয়াল নাম</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">প্রতিষ্ঠানের নাম বাংলায়</span> (Institution name in Bangla)</label>
              <input class="form-control" name="name_bn" lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">
              <div class="form-text"><span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">বাংলায় প্রতিষ্ঠানের পূর্ণ নাম</span> &mdash; <span>optional</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Category <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(ক্যাটেগরি)</span></label>
              <select class="form-select" name="category" required>
                <option value="">— Select —</option>
                <option>School</option><option>College</option><option>University</option>
                <option>Madrasa</option><option>Polytechnic</option><option>Training Institute</option>
                <option>NGO</option><option>Other</option>
              </select>
              <div class="form-text"><span>Pick the closest match</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">সবচেয়ে কাছাকাছি অপশন বেছে নিন</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">EIIN <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(EIIN নম্বর)</span> <span class="text-muted">(optional)</span></label>
              <input class="form-control" name="eiin">
              <div class="form-text"><span>Education Board EIIN if you have one</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">ঐচ্ছিক</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Division <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(বিভাগ)</span></label>
              <select class="form-select" name="division" data-bd-division><option value="">—</option></select>
              <div class="form-text"><span>Select your division</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">বিভাগ নির্বাচন করুন</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">District <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(জেলা)</span></label>
              <select class="form-select" name="district" data-bd-district><option value="">—</option></select>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Upazila / Thana <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(উপজেলা / থানা)</span></label>
              <select class="form-select" name="upazila" data-bd-upazila><option value="">—</option></select>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Address <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(ঠিকানা)</span></label>
              <input class="form-control" name="address">
              <div class="form-text"><span>Street, area, post office</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">রোড, এলাকা, ডাকঘর</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contact person name <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(যোগাযোগের ব্যক্তির নাম)</span></label>
              <input class="form-control" name="contact_name">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contact phone <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(যোগাযোগের নম্বর)</span></label>
              <input class="form-control" name="contact_phone">
              <div class="form-text"><span>Bangladesh mobile, e.g. 01712345678</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">বাংলাদেশী মোবাইল</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Contact email <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(যোগাযোগের ইমেইল)</span></label>
              <input class="form-control" name="contact_email" type="email">
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Website <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(ওয়েবসাইট)</span> <span class="text-muted">(optional)</span></label>
              <input class="form-control" name="website">
              <div class="form-text"><span>Optional &mdash; must start with http:// or https://</span> &mdash; <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">ঐচ্ছিক</span></div>
              <div class="invalid-feedback"></div>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary" type="submit"><i class="bi bi-shield-check me-1"></i> Review &amp; Submit</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Step 3: Success -->
    <div class="card border-0 shadow-sm mt-3 text-center p-5" data-step="3" hidden>
      <i class="bi bi-check-circle-fill text-success fs-1 mb-3 d-block"></i>
      <h4>Congratulations! Claim submitted!</h4>
      <p class="text-muted">Your subdomain request has been sent to the admin for review. You'll be notified once it's approved.</p>
      <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="/dashboard.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i> Go to Dashboard</a>
        <a href="/directory.php" class="btn btn-outline-secondary"><i class="bi bi-grid me-1"></i> Browse Directory</a>
      </div>
    </div>

    <!-- WhatsApp Community Card (shown after success) -->
    <div class="card border-0 shadow mt-3" data-step-wa hidden style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);">
      <div class="card-body p-4 text-center text-white">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white mb-3" style="width:56px;height:56px;">
          <i class="bi bi-whatsapp fs-3" style="color:#25D366;"></i>
        </div>
        <h5 class="text-white mb-2">Join our WhatsApp community!</h5>
        <p class="small mb-3" style="opacity:.9;">Get claim status updates, platform announcements, meet other institution owners, and get instant support.</p>
        <a href="#" class="btn btn-light btn-lg fw-semibold" data-wa-join-link target="_blank" rel="noopener">
          <i class="bi bi-whatsapp me-2"></i> Join WhatsApp Group
        </a>
        <p class="mt-2 mb-0 small" style="opacity:.7;">Free to join, completely opt-in</p>
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
