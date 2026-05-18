<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#0f766e" />
  <title>Admin — institution.bd</title>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>

<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/"><img src="/assets/img/logo.svg" alt="" /><span class="nav__brand-text"><span>institution.bd</span><small>Admin console</small></span></a>
    <div class="nav__links"><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/dashboard.php">My dashboard</a></div>
    <div class="nav__cta"><span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/dashboard.php">Dashboard</a></div>
</nav>

<section style="padding-top:24px;">
  <div class="container">
    <div data-needs-auth hidden>
      <div class="card">
        <h3>Admin only</h3>
        <p class="text-muted">Sign in with an admin account to access the moderation queue.</p>
        <button class="btn btn--primary" data-open-auth>Sign in</button>
      </div>
    </div>

    <div data-not-admin hidden>
      <div class="card">
        <h3>Not an admin</h3>
        <p class="text-muted">Your account is signed in but doesn't have admin permissions. Ask the platform administrator to promote your account.</p>
        <a class="btn" href="/dashboard.php">Back to dashboard</a>
      </div>
    </div>

    <div data-admin hidden>
      <div class="section__head" style="text-align:left;max-width:none;margin-bottom:14px;">
        <span class="eyebrow">Admin</span>
        <h2 class="mb-0">Moderation console</h2>
      </div>

      <div class="stat-grid" data-stats>
        <div class="stat skeleton"></div><div class="stat skeleton"></div>
        <div class="stat skeleton"></div><div class="stat skeleton"></div>
      </div>

      <div class="tabs mt-4">
        <button class="tab active" data-tab="queue">Queue</button>
        <button class="tab" data-tab="reserved">Reserved slugs</button>
        <button class="tab" data-tab="audit">Audit log</button>
        <button class="tab" data-tab="exports">Exports</button>
        <button class="tab" data-tab="settings">Settings</button>
        <button class="tab" data-tab="integrations">Integrations</button>
      </div>

      <!-- queue -->
      <section data-pane="queue">
        <form class="card searchbar mt-3" data-queue-form>
          <div class="field">
            <select data-status>
              <option value="pending">Pending</option>
              <option value="needs_info">Needs info</option>
              <option value="rejected">Rejected</option>
              <option value="verified">Verified</option>
              <option value="suspended">Suspended</option>
              <option value="seeded">Seeded</option>
              <option value="any">Any</option>
            </select>
          </div>
          <div class="field"><input type="text" placeholder="Search name / slug / EIIN" data-q /></div>
          <button class="btn btn--primary" type="submit">Filter</button>
        </form>
        <div data-queue><div class="card"><div class="skeleton" style="height:80px;"></div></div></div>
      </section>

      <!-- reserved -->
      <section data-pane="reserved" hidden>
        <form class="card searchbar mt-3" data-reserved-form>
          <div class="field"><input type="text" placeholder="Add reserved slug (e.g. www)" name="slug" required /></div>
          <div class="field"><input type="text" placeholder="Reason (optional)" name="reason" /></div>
          <button class="btn btn--primary" type="submit">Reserve</button>
        </form>
        <div data-reserved class="mt-3"></div>
      </section>

      <!-- v3.0: audit log -->
      <section data-pane="audit" hidden>
        <form class="card searchbar mt-3" data-audit-form>
          <div class="field"><input type="text" placeholder="Action prefix (e.g. admin.decide)" name="action" /></div>
          <div class="field"><input type="text" placeholder="Search detail / actor email" name="q" /></div>
          <div class="field"><input type="number" placeholder="Inst ID" name="inst_id" min="1" /></div>
          <button class="btn btn--primary" type="submit">Filter</button>
        </form>
        <div data-audit class="mt-3"></div>
      </section>

      <!-- v3.0: exports -->
      <section data-pane="exports" hidden>
        <div class="card mt-3">
          <h3 style="margin-top:0;">CSV exports</h3>
          <p class="text-muted">Download every claim or every user as a UTF-8 CSV (Excel-friendly).</p>
          <div class="flex" style="gap:10px;flex-wrap:wrap;">
            <a class="btn btn--primary" data-export="claims">Export claims.csv</a>
            <a class="btn" data-export="users">Export users.csv</a>
          </div>
        </div>
      </section>

      <!-- v3.0: platform settings -->
      <section data-pane="settings" hidden>
        <div data-settings-host></div>
      </section>

      <!-- v3.1: integrations (OAuth, Cloudflare, WhatsApp, branding, JWT) -->
      <section data-pane="integrations" hidden>
        <div data-integrations-host></div>
      </section>
    </div>
  </div>
</section>

<!-- claim-detail modal -->
<div class="modal-bg" data-detail-modal>
  <div class="modal modal--xl">
    <button class="modal__close" data-close-detail>&times;</button>
    <div data-detail-body><div class="skeleton" style="height:200px;"></div></div>
  </div>
</div>

<footer class="footer">
  <div class="container footer__bottom"><span>© <span data-year></span> institution.bd</span><span><a href="/">Home</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span></div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
