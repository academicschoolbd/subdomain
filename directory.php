<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Directory — institution.bd</title>
  <meta name="description" content="Browse verified Bangladeshi institutions on institution.bd and smartschool.bd.">
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
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#dirNav"><span class="navbar-toggler-icon"></span></button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="dirNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="/directory.php">Directory</a></li>
        <li class="nav-item"><a class="nav-link" href="/claim.php">Claim</a></li>
        <li class="nav-item"><a class="nav-link" href="/dashboard.php">Dashboard</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Directory Header -->
<section class="py-4 bg-body-secondary border-bottom">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h2 class="mb-1"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Institution Directory</h2>
        <p class="text-muted mb-0">Browse verified institutions across Bangladesh.</p>
      </div>
      <a href="/claim.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Claim yours</a>
    </div>
  </div>
</section>

<!-- Filters + Results -->
<section class="py-4">
  <div class="container">
    <!-- Search / Filters -->
    <form class="row g-2 mb-4" data-dir-form>
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" placeholder="Search institution name..." data-dir-q autocomplete="off">
        </div>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" data-dir-brand>
          <option value="">All brands</option>
          <option value="institution.bd">institution.bd</option>
          <option value="smartschool.bd">smartschool.bd</option>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" data-dir-division>
          <option value="">All divisions</option>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" data-dir-category>
          <option value="">All categories</option>
          <option>School</option><option>College</option><option>University</option>
          <option>Madrasa</option><option>Polytechnic</option><option>NGO</option>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" data-dir-sort>
          <option value="recent">Most recent</option>
          <option value="name">Name A-Z</option>
        </select>
      </div>
    </form>

    <!-- Results Grid -->
    <div class="row g-3" data-dir-grid>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4" data-dir-pagination></div>
  </div>
</section>

<!-- Footer -->
<footer class="footer-v5">
  <div class="container">
    <div class="footer-bottom d-flex justify-content-between flex-wrap gap-2">
      <span>&copy; <span data-year></span> institution.bd</span>
      <span><a href="/">Home</a> &middot; <a href="/claim.php">Claim</a> &middot; <a href="/privacy.php">Privacy</a> &middot; <a href="/terms.php">Terms</a></span>
    </div>
  </div>
</footer>

<div class="toast-container-v5" id="toastHost"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" type="module"></script>
<script src="/assets/js/bd-locations.js"></script>
<script src="/assets/js/directory.js" type="module"></script>
</body>
</html>
