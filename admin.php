<?php
try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Admin Console — institution.bd</title>
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
        <span class="brand-sub">Admin Console</span>
      </span>
    </a>
    <div class="d-flex align-items-center gap-2 order-lg-3">
      <button class="btn-theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
        <i class="bi bi-moon-stars-fill"></i>
      </button>
      <span data-user-chip></span>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-label="Menu">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="adminNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-house me-1"></i> Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/directory.php"><i class="bi bi-grid me-1"></i> Directory</a></li>
        <li class="nav-item"><a class="nav-link" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="/admin.php"><i class="bi bi-shield-lock me-1"></i> Admin</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ===== NEEDS AUTH ===== -->
<section class="py-5" data-needs-auth hidden>
  <div class="container">
    <div class="card border-0 shadow-sm text-center p-5" style="max-width:480px;margin:0 auto;">
      <div class="mb-3"><i class="bi bi-shield-lock-fill fs-1 text-muted"></i></div>
      <h3>Admin only</h3>
      <p class="text-muted">Sign in with an admin account to access the moderation console.</p>
      <button class="btn btn-primary" data-open-auth><i class="bi bi-box-arrow-in-right me-2"></i> Sign in</button>
    </div>
  </div>
</section>

<!-- ===== NOT ADMIN ===== -->
<section class="py-5" data-not-admin hidden>
  <div class="container">
    <div class="card border-0 shadow-sm text-center p-5" style="max-width:480px;margin:0 auto;">
      <div class="mb-3"><i class="bi bi-person-x-fill fs-1 text-warning"></i></div>
      <h3>Not an admin</h3>
      <p class="text-muted">Your account doesn't have admin permissions.</p>
      <a class="btn btn-outline-primary" href="/dashboard.php"><i class="bi bi-arrow-left me-1"></i> Back to dashboard</a>
    </div>
  </div>
</section>


<!-- ===== ADMIN SHELL ===== -->
<div data-admin-root hidden id="main">
  <!-- Mobile sidebar toggle (visible < 992px) -->
  <button class="mobile-sidebar-toggle d-lg-none" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
    <i class="bi bi-list"></i>
  </button>
  <!-- Mobile sidebar backdrop -->
  <div class="mobile-sidebar-backdrop" data-sidebar-backdrop></div>
  <div class="dash-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="dash-sidebar-v5">
      <div class="sidebar-header">
        <i class="bi bi-shield-fill text-primary"></i>
        <span>Admin Console</span>
      </div>
      <nav class="sidebar-nav">
        <button class="dash-nav-item active" data-pane-btn="overview" type="button">
          <i class="bi bi-grid-1x2-fill nav-icon"></i>
          <span>Overview</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="queue" type="button">
          <i class="bi bi-check2-square nav-icon"></i>
          <span>Approval Queue</span>
          <span class="nav-badge" data-nav-pending hidden>0</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="users" type="button">
          <i class="bi bi-people-fill nav-icon"></i>
          <span>Users</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="reserved" type="button">
          <i class="bi bi-lock-fill nav-icon"></i>
          <span>Reserved Slugs</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="audit" type="button">
          <i class="bi bi-journal-text nav-icon"></i>
          <span>Audit Log</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="exports" type="button">
          <i class="bi bi-download nav-icon"></i>
          <span>Exports</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="settings" type="button">
          <i class="bi bi-sliders nav-icon"></i>
          <span>Platform Settings</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="integrations" type="button">
          <i class="bi bi-code-slash nav-icon"></i>
          <span>Integrations</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="payments" type="button">
          <i class="bi bi-credit-card-fill nav-icon"></i>
          <span>Support Payments</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="sponsors" type="button">
          <i class="bi bi-megaphone-fill nav-icon"></i>
          <span>Sponsors</span>
        </button>
        <button class="dash-nav-item" data-pane-btn="renewals" type="button">
          <i class="bi bi-arrow-repeat nav-icon"></i>
          <span>Renewals</span>
          <span class="nav-badge" data-renewals-pending hidden>0</span>
        </button>
      </nav>
      <div class="sidebar-footer">
        <a class="btn btn-sm btn-outline-secondary w-100 mb-2" href="/dashboard.php">
          <i class="bi bi-speedometer2 me-1"></i> My Dashboard
        </a>
        <button class="btn btn-sm btn-outline-danger w-100" data-signout type="button">
          <i class="bi bi-box-arrow-left me-1"></i> Sign out
        </button>
      </div>
    </aside>


    <!-- ===== MAIN CONTENT ===== -->
    <main class="dash-content">

      <!-- Admin Banner -->
      <div class="admin-banner-v5 mb-4" data-admin-banner>
        <div class="banner-icon"><i class="bi bi-shield-check"></i></div>
        <div class="flex-grow-1">
          <h6 class="mb-1" data-admin-banner-title>Moderation is ON — every claim waits for your approval.</h6>
          <p class="text-muted small mb-0" data-admin-banner-sub>New subdomains land as <code>pending</code> and stay private until you approve them.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-primary btn-sm" data-jump-pane="queue" type="button">Open queue</button>
          <button class="btn btn-outline-secondary btn-sm" data-jump-pane="settings" type="button">Settings</button>
        </div>
      </div>

      <!-- ===== OVERVIEW PANE ===== -->
      <section data-pane="overview">
        <!-- KPI Tiles -->
        <div class="row g-3 mb-4" data-kpi-host></div>

        <div class="row g-4">
          <!-- Recent Activity -->
          <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <div>
                  <h6 class="mb-0"><i class="bi bi-activity me-2 text-primary"></i>Recent Activity</h6>
                  <small class="text-muted">Last 12 admin/owner actions</small>
                </div>
                <button class="btn btn-sm btn-outline-primary" data-jump-pane="audit" type="button">Full log <i class="bi bi-arrow-right"></i></button>
              </div>
              <div class="card-body" data-activity-host>
                <div class="skeleton-v5" style="height:180px;"></div>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0"><i class="bi bi-lightning-fill me-2 text-warning"></i>Quick Actions</h6>
                <small class="text-muted">Jump straight where you need to be</small>
              </div>
              <div class="card-body d-grid gap-2">
                <button class="quick-action-v5" data-jump-pane="queue" type="button">
                  <span class="qa-icon"><i class="bi bi-check2-square"></i></span>
                  Review pending claims
                </button>
                <button class="quick-action-v5" data-jump-pane="users" type="button">
                  <span class="qa-icon"><i class="bi bi-people"></i></span>
                  Manage users / admins
                </button>
                <button class="quick-action-v5" data-jump-pane="reserved" type="button">
                  <span class="qa-icon"><i class="bi bi-lock"></i></span>
                  Reserve a slug
                </button>
                <button class="quick-action-v5" data-jump-pane="integrations" type="button">
                  <span class="qa-icon"><i class="bi bi-code-slash"></i></span>
                  OAuth / Cloudflare keys
                </button>
                <a class="quick-action-v5" href="/dashboard.php">
                  <span class="qa-icon"><i class="bi bi-speedometer2"></i></span>
                  My owner dashboard
                </a>
                <a class="quick-action-v5" href="/directory.php">
                  <span class="qa-icon"><i class="bi bi-globe2"></i></span>
                  Public directory
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>


      <!-- ===== QUEUE PANE ===== -->
      <section data-pane="queue" hidden>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div>
              <h5 class="mb-1"><i class="bi bi-check2-square me-2 text-primary"></i>Approval Queue</h5>
              <p class="text-muted small mb-0">Approve, request changes, reject or suspend claims.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-primary btn-sm" data-bulk-act="approve" type="button" disabled>Approve selected</button>
              <button class="btn btn-outline-warning btn-sm" data-bulk-act="needs_info" type="button" disabled>Needs info</button>
              <button class="btn btn-outline-danger btn-sm" data-bulk-act="reject" type="button" disabled>Reject</button>
            </div>
          </div>
          <div class="card-body">
            <form class="row g-2 mb-3" data-queue-form>
              <div class="col-md-8">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                  <input type="text" class="form-control" placeholder="Search name, slug, EIIN..." data-q autocomplete="off">
                </div>
              </div>
              <div class="col-md-4">
                <select class="form-select form-select-sm" data-status>
                  <option value="pending">Pending</option>
                  <option value="needs_info">Needs info</option>
                  <option value="rejected">Rejected</option>
                  <option value="verified">Verified</option>
                  <option value="suspended">Suspended</option>
                  <option value="seeded">Seeded</option>
                  <option value="any">Any</option>
                </select>
              </div>
            </form>
            <div class="table-responsive">
              <table class="table dash-table-v5 align-middle mb-0" data-queue-table>
                <thead>
                  <tr>
                    <th style="width:40px;"><input type="checkbox" class="form-check-input" data-bulk-toggle aria-label="Select all"></th>
                    <th>Institution</th>
                    <th>Status</th>
                    <th>Brand</th>
                    <th>Submitted</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody data-queue-body>
                  <tr><td colspan="6"><div class="skeleton-v5" style="height:48px;"></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== USERS PANE ===== -->
      <section data-pane="users" hidden>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="mb-1"><i class="bi bi-people-fill me-2 text-primary"></i>Users</h5>
            <p class="text-muted small mb-0">Promote teammates to admin or revoke access.</p>
          </div>
          <div class="card-body">
            <form class="row g-2 mb-3" data-users-form>
              <div class="col-md-8">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                  <input type="text" class="form-control" placeholder="Search email, name or mobile..." data-users-q autocomplete="off">
                </div>
              </div>
              <div class="col-md-4">
                <select class="form-select form-select-sm" data-users-role>
                  <option value="any">All roles</option>
                  <option value="admin">Admins only</option>
                  <option value="user">Owners only</option>
                </select>
              </div>
            </form>
            <div class="table-responsive">
              <table class="table dash-table-v5 align-middle mb-0" data-users-table>
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Provider</th>
                    <th>Claims</th>
                    <th>Joined</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody data-users-body>
                  <tr><td colspan="6"><div class="skeleton-v5" style="height:48px;"></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>


      <!-- ===== RESERVED PANE ===== -->
      <section data-pane="reserved" hidden>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="mb-1"><i class="bi bi-lock-fill me-2 text-primary"></i>Reserved Slugs</h5>
            <p class="text-muted small mb-0">Block subdomains from being claimed.</p>
          </div>
          <div class="card-body">
            <form class="row g-2 mb-3" data-reserved-form>
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" placeholder="Add slug (e.g. www)" name="slug" required>
              </div>
              <div class="col-md-5">
                <input type="text" class="form-control form-control-sm" placeholder="Reason (optional)" name="reason">
              </div>
              <div class="col-md-3">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-plus-lg me-1"></i> Reserve</button>
              </div>
            </form>
            <div data-reserved></div>
          </div>
        </div>
      </section>

      <!-- ===== AUDIT PANE ===== -->
      <section data-pane="audit" hidden>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="mb-1"><i class="bi bi-journal-text me-2 text-primary"></i>Audit Log</h5>
            <p class="text-muted small mb-0">Filter by action, actor email, institution ID.</p>
          </div>
          <div class="card-body">
            <form class="row g-2 mb-3" data-audit-form>
              <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" placeholder="Action prefix" name="action">
              </div>
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" placeholder="Search detail / email" name="q">
              </div>
              <div class="col-md-2">
                <input type="number" class="form-control form-control-sm" placeholder="Inst ID" name="inst_id" min="1">
              </div>
              <div class="col-md-3">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
              </div>
            </form>
            <div data-audit class="table-responsive"></div>
          </div>
        </div>
      </section>

      <!-- ===== EXPORTS PANE ===== -->
      <section data-pane="exports" hidden>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="mb-1"><i class="bi bi-download me-2 text-primary"></i>CSV Exports</h5>
            <p class="text-muted small mb-0">Download every claim or user as UTF-8 CSV.</p>
          </div>
          <div class="card-body">
            <div class="row g-4">
              <div class="col-md-6">
                <div class="export-card-v5 h-100">
                  <h6><i class="bi bi-globe2 me-2 text-primary"></i>All Claims</h6>
                  <p class="text-muted small">Slug, status, DNS state, EIIN, contact info.</p>
                  <a class="btn btn-primary btn-sm" data-export="claims"><i class="bi bi-download me-1"></i> Download claims.csv</a>
                </div>
              </div>
              <div class="col-md-6">
                <div class="export-card-v5 h-100">
                  <h6><i class="bi bi-people me-2 text-primary"></i>All Users</h6>
                  <p class="text-muted small">Email, mobile, designation, division, admin flag.</p>
                  <a class="btn btn-outline-primary btn-sm" data-export="users"><i class="bi bi-download me-1"></i> Download users.csv</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== SETTINGS PANE ===== -->
      <section data-pane="settings" hidden>
        <div data-settings-host></div>
      </section>

      <!-- ===== INTEGRATIONS PANE ===== -->
      <section data-pane="integrations" hidden>
        <div data-integrations-host></div>
      </section>

      <!-- ===== PAYMENTS PANE ===== -->
      <section data-pane="payments" hidden>
        <div data-payments-admin-host></div>
      </section>

      <!-- ===== SPONSORS PANE ===== -->
      <section data-pane="sponsors" hidden>
        <div class="mb-4">
          <h4><i class="bi bi-megaphone-fill me-2 text-primary"></i>Sponsors</h4>
          <p class="text-muted">Manage sponsor logos shown on the homepage.</p>
        </div>
        <div data-sponsors-admin-host></div>
      </section>

      <!-- ===== RENEWALS PANE ===== -->
      <section data-pane="renewals" hidden>
        <div data-renewals-host></div>
      </section>

    </main>
  </div>
</div>


<!-- ===== DETAIL MODAL ===== -->
<div class="modal fade modal-v5" id="detailModal" tabindex="-1" data-detail-modal>
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Claim Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-close-detail></button>
      </div>
      <div class="modal-body" data-detail-body>
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
<script src="/assets/js/admin.js" type="module"></script>
<script>
(function(){
  var toggle = document.querySelector('[data-sidebar-toggle]');
  var backdrop = document.querySelector('[data-sidebar-backdrop]');
  var root = document.querySelector('[data-admin-root]');
  if (!toggle || !root) return;
  function open() { root.classList.add('mobile-sidebar-open'); }
  function close() { root.classList.remove('mobile-sidebar-open'); }
  toggle.addEventListener('click', function() {
    root.classList.contains('mobile-sidebar-open') ? close() : open();
  });
  if (backdrop) backdrop.addEventListener('click', close);
  root.querySelectorAll('.dash-nav-item').forEach(function(btn) {
    btn.addEventListener('click', close);
  });
})();
</script>
</body>
</html>
