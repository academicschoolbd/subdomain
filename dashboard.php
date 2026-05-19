<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#0f766e" />
  <title>Control Panel — institution.bd</title>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>

<!-- ============================ NAV ============================ -->
<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/">
      <img src="/assets/img/logo.svg" alt="" />
      <span class="nav__brand-text"><span>institution.bd</span><small>smartschool.bd</small></span>
    </a>
    <div class="nav__links">
      <a href="/">Home</a>
      <a href="/directory.php">Directory</a>
      <a href="/claim.php">Claim</a>
      <a href="/dashboard.php" class="active">Dashboard</a>
    </div>
    <div class="nav__cta">
      <span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile>
    <a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a><a href="/dashboard.php">Dashboard</a>
  </div>
</nav>

<!-- ============================ NOT SIGNED IN ============================ -->
<section class="dash-shell" data-needs-auth hidden>
  <div class="container">
    <div class="card">
      <h3>You're not signed in</h3>
      <p class="text-muted">Sign in with email, Google, Facebook or GitHub to see your subdomains.</p>
      <button class="btn btn--primary" data-open-auth>Sign in</button>
    </div>
  </div>
</section>

<!-- ============================ DASHBOARD ============================ -->
<section class="dash-shell" data-dash-root hidden id="main">
  <div class="container">
    <div class="dash-grid">

      <!-- =================== SIDEBAR =================== -->
      <aside class="dash-sidebar" aria-label="Control panel navigation">
        <header class="dash-sidebar__head">
          <span class="dash-sidebar__crest" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <span>Control Panel</span>
        </header>

        <nav class="dash-nav">
          <button class="dash-nav__item active" data-pane-btn="overview" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            </span>
            <span>Overview</span>
            <span class="dash-nav__count" data-domain-count hidden>0</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="settings" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09c0 .67.39 1.27 1 1.51a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82c.24.61.84 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </span>
            <span>Settings</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="support" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </span>
            <span>Support Developer</span>
          </button>
          <a class="dash-nav__item" href="/admin.php" data-admin-only hidden>
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </span>
            <span>Admin console</span>
          </a>
        </nav>

        <footer class="dash-sidebar__foot">
          <button class="btn btn--ghost btn--sm" data-signout type="button">Sign out</button>
        </footer>
      </aside>

      <!-- =================== MAIN =================== -->
      <main class="dash-main">

        <!-- Stay-connected banner — v4.1 modern glass card with mesh gradient. -->
        <section class="connect-band" data-banner>
          <div class="connect-band__bg" aria-hidden="true">
            <span class="connect-band__blob connect-band__blob--a"></span>
            <span class="connect-band__blob connect-band__blob--b"></span>
            <span class="connect-band__blob connect-band__blob--c"></span>
          </div>
          <div class="connect-band__inner">
            <div class="connect-band__lead">
              <span class="connect-band__pill" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <span>Stay connected</span>
              </span>
              <h3 class="connect-band__title" data-banner-title>Stay Connected with institution.bd</h3>
              <p class="connect-band__sub" data-banner-sub>Get announcements, support and meet other institution owners.</p>
            </div>
            <div class="connect-band__cta">
              <a class="btn btn--primary connect-band__btn" data-banner-community href="#" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Join Our Community
              </a>
              <a class="btn connect-band__btn connect-band__btn--like" data-banner-like href="#" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Like Our Page
              </a>
              <a class="btn btn--gradient connect-band__btn connect-band__btn--creator" data-banner-creator href="#" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span data-banner-creator-text>Follow Creator</span>
              </a>
            </div>
          </div>
        </section>

        <!-- =================== OVERVIEW PANE =================== -->
        <section class="dash-pane" data-pane="overview">

          <!-- Quick-access tile row -->
          <div class="quick-grid" data-quick-host></div>

          <!-- "My Domains" card -->
          <div class="card dash-card">
            <header class="dash-card__head">
              <div>
                <h2 class="dash-card__title">My Domains</h2>
                <p class="dash-card__sub">Manage your digital identities.</p>
              </div>
              <a href="/claim.php" class="btn btn--dark">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Register New
              </a>
            </header>

            <form class="dash-card__filters" data-domains-form>
              <label class="dash-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search domains…" data-domains-q autocomplete="off" />
              </label>
              <label class="dash-filter">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <select data-domains-status>
                  <option value="any">All Statuses</option>
                  <option value="verified">Verified</option>
                  <option value="pending">Pending</option>
                  <option value="needs_info">Needs info</option>
                  <option value="rejected">Rejected</option>
                  <option value="suspended">Suspended</option>
                  <option value="seeded">Seeded</option>
                </select>
              </label>
            </form>

            <div class="dash-table-wrap">
              <table class="dash-table" data-domains-table>
                <thead>
                  <tr>
                    <th>Domain Name</th>
                    <th>Status</th>
                    <th>Registered At</th>
                    <th>Expires At</th>
                    <th class="text-right">Action</th>
                  </tr>
                </thead>
                <tbody data-domains-body>
                  <tr><td colspan="5"><div class="skeleton" style="height:48px;"></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- =================== SETTINGS PANE =================== -->
        <section class="dash-pane" data-pane="settings" hidden>
          <div class="dash-card__head" style="border:none;padding:0;">
            <div>
              <h2 class="dash-card__title">Settings</h2>
              <p class="dash-card__sub">Manage your profile and account preferences.</p>
            </div>
          </div>
          <div data-account-card></div>
        </section>

        <!-- =================== SUPPORT PANE =================== -->
        <section class="dash-pane" data-pane="support" hidden>
          <div class="dash-card__head" style="border:none;padding:0;">
            <div>
              <h2 class="dash-card__title">Support Developer</h2>
              <p class="dash-card__sub">institution.bd is free, forever — your encouragement keeps us shipping.</p>
            </div>
          </div>
          <div class="card dash-support">
            <span class="dash-support__ico" aria-hidden="true">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </span>
            <h3>Built &amp; maintained by volunteers</h3>
            <p class="text-muted">Every paisa we save on hosting goes back into adding free verified subdomains. If institution.bd helps your institution, here are three free ways to support us:</p>
            <div class="dash-support__grid">
              <a class="card dash-support__card" data-banner-creator href="#" target="_blank" rel="noopener">
                <strong>Follow the creator</strong>
                <p class="text-muted">Stay in the loop and help us reach more institutions.</p>
              </a>
              <a class="card dash-support__card" data-banner-community href="#" target="_blank" rel="noopener">
                <strong>Join the WhatsApp community</strong>
                <p class="text-muted">Ask questions, share feedback, meet other admins.</p>
              </a>
              <a class="card dash-support__card" data-banner-like href="#" target="_blank" rel="noopener">
                <strong>Like our page</strong>
                <p class="text-muted">A like really does help — it's the cheapest tip-jar we have.</p>
              </a>
            </div>
          </div>

          <!-- v3.2 — payment methods host (admin-editable from /admin → Support payments). -->
          <div data-payments-host class="mt-4"></div>
        </section>

      </main>
    </div>
  </div>
</section>

<!-- ============================ MANAGE-DOMAIN MODAL ============================ -->
<div class="modal-bg" data-manage-modal>
  <div class="modal modal--xl">
    <button class="modal__close" data-manage-close aria-label="Close">&times;</button>
    <div data-manage-body><div class="skeleton" style="height:200px;"></div></div>
  </div>
</div>

<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/directory.php">Directory</a> · <a href="/claim.php">Claim</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/bd-locations.js"></script>
<script src="/assets/js/dashboard.js"></script>
</body>
</html>
