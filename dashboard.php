<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Control Panel — institution.bd</title>
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
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#dashNav" aria-label="Menu">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="dashNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-house me-1"></i> Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/directory.php"><i class="bi bi-grid me-1"></i> Directory</a></li>
        <li class="nav-item"><a class="nav-link" href="/claim.php"><i class="bi bi-plus-circle me-1"></i> Claim</a></li>
        <li class="nav-item"><a class="nav-link active" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
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
      <p class="text-muted">Sign in with email, Google, Facebook or GitHub to see your subdomains.</p>
      <button class="btn btn-primary" data-open-auth>
        <i class="bi bi-box-arrow-in-right me-2"></i> Sign in
      </button>
    </div>
  </div>
</section>

<!-- ===== DASHBOARD SHELL ===== -->
<div data-dash-root hidden id="main">
  <div class="dash-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="dash-sidebar-v5">
      <div class="sidebar-header">
        <i class="bi bi-shield-fill text-primary"></i>
        <span>Control Panel</span>
      </div>
      <nav class="sidebar-nav">
        <button class="dash-nav-item active" data-pane-btn="overview" type="button">
          <i class="bi bi-grid-1x2-fill nav-icon"></i>
          <span>Overview</span>
          <span class="nav-badge" data-domain-count hidden>0</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="settings" type="button">
          <i class="bi bi-gear-fill nav-icon"></i>
          <span>Settings</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="support" type="button">
          <i class="bi bi-heart-fill nav-icon"></i>
          <span>Support Developer</span>
        </button>
        <a class="dash-nav-item" href="/admin.php" data-admin-only hidden>
          <i class="bi bi-shield-lock-fill nav-icon"></i>
          <span>Admin Console</span>
        </a>
      </nav>
      <div class="sidebar-footer">
        <button class="btn btn-sm btn-outline-secondary w-100" data-signout type="button">
          <i class="bi bi-box-arrow-left me-1"></i> Sign out
        </button>
      </div>
    </aside>


    <!-- ===== MAIN CONTENT ===== -->
    <main class="dash-content">

      <!-- Connect Banner -->
      <section class="connect-banner-v5 mb-4" data-banner>
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="flex-grow-1">
            <h5 class="mb-1" data-banner-title>Stay Connected with institution.bd</h5>
            <p class="mb-0 small" data-banner-sub>Get announcements, support and meet other institution owners.</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-light btn-sm" data-banner-community href="#" target="_blank" rel="noopener">
              <i class="bi bi-people-fill me-1"></i> Join Community
            </a>
            <a class="btn btn-outline-light btn-sm" data-banner-like href="#" target="_blank" rel="noopener">
              <i class="bi bi-hand-thumbs-up-fill me-1"></i> Like Page
            </a>
            <a class="btn btn-outline-light btn-sm" data-banner-creator href="#" target="_blank" rel="noopener">
              <i class="bi bi-person-plus-fill me-1"></i> <span data-banner-creator-text>Follow Creator</span>
            </a>
          </div>
        </div>
      </section>

      <!-- ===== OVERVIEW PANE ===== -->
      <section data-pane="overview">

        <!-- Quick Tiles -->
        <div class="row g-3 mb-4" data-quick-host></div>

        <!-- My Domains Card -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div>
              <h5 class="mb-1"><i class="bi bi-globe2 me-2 text-primary"></i>My Domains</h5>
              <p class="text-muted small mb-0">Manage your digital identities.</p>
            </div>
            <a href="/claim.php" class="btn btn-primary btn-sm">
              <i class="bi bi-plus-lg me-1"></i> Register New
            </a>
          </div>
          <div class="card-body">
            <form class="row g-2 mb-3" data-domains-form>
              <div class="col-md-8">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                  <input type="text" class="form-control" placeholder="Search domains..." data-domains-q autocomplete="off">
                </div>
              </div>
              <div class="col-md-4">
                <select class="form-select form-select-sm" data-domains-status>
                  <option value="any">All Statuses</option>
                  <option value="verified">Verified</option>
                  <option value="pending">Pending</option>
                  <option value="needs_info">Needs info</option>
                  <option value="rejected">Rejected</option>
                  <option value="suspended">Suspended</option>
                  <option value="seeded">Seeded</option>
                </select>
              </div>
            </form>
            <div class="table-responsive">
              <table class="table dash-table-v5 align-middle mb-0" data-domains-table>
                <thead>
                  <tr>
                    <th>Domain Name</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Expires</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody data-domains-body>
                  <tr><td colspan="5"><div class="skeleton-v5 mx-auto" style="height:48px;width:100%;"></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>


      <!-- ===== SETTINGS PANE ===== -->
      <section data-pane="settings" hidden>
        <div class="mb-4">
          <h4><i class="bi bi-gear-fill me-2 text-primary"></i>Settings</h4>
          <p class="text-muted">Manage your profile and account preferences.</p>
        </div>
        <div data-account-card></div>
      </section>

      <!-- ===== SUPPORT PANE ===== -->
      <section data-pane="support" hidden>
        <div class="mb-4">
          <h4><i class="bi bi-heart-fill me-2 text-danger"></i>Support Developer</h4>
          <p class="text-muted">institution.bd is free, forever — your encouragement keeps us shipping.</p>
        </div>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-4">
            <div class="mb-3">
              <span class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle" style="width:56px;height:56px;">
                <i class="bi bi-heart-fill fs-4"></i>
              </span>
            </div>
            <h5>Built & maintained by volunteers</h5>
            <p class="text-muted">Every paisa we save on hosting goes back into adding free verified subdomains.</p>
          </div>
        </div>
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" data-banner-creator href="#" target="_blank">
              <div class="card-body">
                <h6><i class="bi bi-person-plus-fill text-primary me-1"></i> Follow the creator</h6>
                <p class="text-muted small mb-0">Stay in the loop and help us reach more institutions.</p>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" data-banner-community href="#" target="_blank">
              <div class="card-body">
                <h6><i class="bi bi-whatsapp text-success me-1"></i> Join WhatsApp community</h6>
                <p class="text-muted small mb-0">Ask questions, share feedback, meet other admins.</p>
              </div>
            </a>
          </div>
          <div class="col-md-4">
            <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" data-banner-like href="#" target="_blank">
              <div class="card-body">
                <h6><i class="bi bi-hand-thumbs-up-fill text-warning me-1"></i> Like our page</h6>
                <p class="text-muted small mb-0">A like really does help — it's the cheapest tip-jar we have.</p>
              </div>
            </a>
          </div>
        </div>
        <div data-payments-host class="mt-4"></div>
      </section>

    </main>
  </div>
</div>


<!-- ===== MANAGE DOMAIN MODAL ===== -->
<div class="modal fade modal-v5" id="manageModal" tabindex="-1" data-manage-modal>
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Manage Domain</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-manage-close></button>
      </div>
      <div class="modal-body" data-manage-body>
        <div class="skeleton-v5" style="height:200px;"></div>
      </div>
    </div>
  </div>
</div>

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
<script src="/assets/js/dashboard.js" type="module"></script>
</body>
</html>
