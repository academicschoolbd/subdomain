<?php try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) { /* DB unavailable — page still renders, JS surfaces the error */ } if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Admin console — institution.bd</title>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>

<!-- ============================ NAV ============================ -->
<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/">
      <img src="/assets/img/logo.svg" alt="" />
      <span class="nav__brand-text"><span>institution.bd</span><small>Admin console</small></span>
    </a>
    <div class="nav__links">
      <a href="/">Home</a>
      <a href="/directory.php">Directory</a>
      <a href="/dashboard.php">My dashboard</a>
      <a href="/admin.php" class="active">Admin</a>
    </div>
    <div class="nav__cta">
      <span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile>
    <a href="/">Home</a><a href="/directory.php">Directory</a><a href="/dashboard.php">My dashboard</a><a href="/admin.php">Admin</a>
  </div>
</nav>

<!-- ============================ NEEDS AUTH ============================ -->
<section class="dash-shell" data-needs-auth hidden>
  <div class="container">
    <div class="card">
      <h3>Admin only</h3>
      <p class="text-muted">Sign in with an admin account to access the moderation console.</p>
      <button class="btn btn--primary" data-open-auth>Sign in</button>
    </div>
  </div>
</section>

<!-- ============================ NOT ADMIN ============================ -->
<section class="dash-shell" data-not-admin hidden>
  <div class="container">
    <div class="card">
      <h3>Not an admin</h3>
      <p class="text-muted">Your account is signed in but doesn't have admin permissions. Ask the platform administrator to promote your account.</p>
      <a class="btn" href="/dashboard.php">Back to dashboard</a>
    </div>
  </div>
</section>

<!-- ============================ ADMIN SHELL ============================ -->
<section class="dash-shell" data-admin-root hidden id="main">
  <div class="container">
    <div class="dash-grid">

      <!-- =================== SIDEBAR =================== -->
      <aside class="dash-sidebar" aria-label="Admin console navigation">
        <header class="dash-sidebar__head">
          <span class="dash-sidebar__crest" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <span>Admin console</span>
        </header>

        <nav class="dash-nav">
          <button class="dash-nav__item active" data-pane-btn="overview" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            </span>
            <span>Overview</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="queue" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h7"/></svg>
            </span>
            <span>Approval queue</span>
            <span class="dash-nav__count" data-nav-pending hidden>0</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="users" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span>Users</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="reserved" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <span>Reserved slugs</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="audit" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </span>
            <span>Audit log</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="exports" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </span>
            <span>Exports</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="settings" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09c0 .67.39 1.27 1 1.51a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82c.24.61.84 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </span>
            <span>Platform settings</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="integrations" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </span>
            <span>Integrations</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="payments" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><line x1="2" y1="11" x2="22" y2="11"/><line x1="6" y1="15" x2="10" y2="15"/></svg>
            </span>
            <span>Support payments</span>
          </button>
          <button class="dash-nav__item" data-pane-btn="renewals" type="button">
            <span class="dash-nav__ico" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 4 21 10 15 10"/></svg>
            </span>
            <span>Renewals</span>
            <span class="dash-nav__count" data-renewals-pending hidden>0</span>
          </button>
        </nav>

        <footer class="dash-sidebar__foot">
          <a class="btn btn--ghost btn--sm" href="/dashboard.php">My dashboard</a>
          <button class="btn btn--ghost btn--sm" data-signout type="button">Sign out</button>
        </footer>
      </aside>

      <!-- =================== MAIN =================== -->
      <main class="dash-main">

        <!-- Approval-mode banner (top of every pane) -->
        <section class="dash-banner dash-banner--admin" data-admin-banner>
          <span class="dash-banner__ico" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <div class="dash-banner__body">
            <h3 data-admin-banner-title>Moderation is ON — every claim waits for your approval.</h3>
            <p data-admin-banner-sub>New subdomains land as <code>pending</code> and stay private until you approve them. You can flip moderation OFF in Platform settings to restore the v3.0 instant-claim flow.</p>
          </div>
          <div class="dash-banner__cta">
            <button class="btn btn--primary" data-jump-pane="queue" type="button">Open queue</button>
            <button class="btn" data-jump-pane="settings" type="button">Settings</button>
          </div>
        </section>

        <!-- =================== OVERVIEW PANE =================== -->
        <section class="dash-pane" data-pane="overview">
          <div class="quick-grid" data-kpi-host></div>

          <div class="grid-2 mt-3">
            <div class="card dash-card">
              <header class="dash-card__head">
                <div>
                  <h2 class="dash-card__title">Recent activity</h2>
                  <p class="dash-card__sub">The last 12 admin / owner actions.</p>
                </div>
                <button class="btn btn--sm" data-jump-pane="audit" type="button">Full audit log →</button>
              </header>
              <div class="activity-feed" data-activity-host>
                <div class="skeleton" style="height:180px;"></div>
              </div>
            </div>

            <div class="card dash-card">
              <header class="dash-card__head">
                <div>
                  <h2 class="dash-card__title">Quick actions</h2>
                  <p class="dash-card__sub">Jump straight where you need to be.</p>
                </div>
              </header>
              <div class="quick-actions">
                <button class="quick-action" data-jump-pane="queue" type="button">
                  <span class="quick-action__ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h7"/></svg>
                  </span>
                  Review pending claims
                </button>
                <button class="quick-action" data-jump-pane="users" type="button">
                  <span class="quick-action__ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                  </span>
                  Manage users / admins
                </button>
                <button class="quick-action" data-jump-pane="reserved" type="button">
                  <span class="quick-action__ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  </span>
                  Reserve a slug
                </button>
                <button class="quick-action" data-jump-pane="integrations" type="button">
                  <span class="quick-action__ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                  </span>
                  OAuth / Cloudflare keys
                </button>
                <a class="quick-action" href="/dashboard.php">
                  <span class="quick-action__ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                  </span>
                  My owner dashboard
                </a>
                <a class="quick-action" href="/directory.php">
                  <span class="quick-action__ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                  </span>
                  Public directory
                </a>
              </div>
            </div>
          </div>
        </section>

        <!-- =================== QUEUE PANE =================== -->
        <section class="dash-pane" data-pane="queue" hidden>
          <div class="card dash-card">
            <header class="dash-card__head">
              <div>
                <h2 class="dash-card__title">Approval queue</h2>
                <p class="dash-card__sub">Approve, request changes, reject or suspend institution claims. Bulk actions act on every checked row.</p>
              </div>
              <div class="flex" style="gap:8px;flex-wrap:wrap;">
                <button class="btn btn--primary btn--sm" data-bulk-act="approve" type="button" disabled>Approve selected</button>
                <button class="btn btn--sm" data-bulk-act="needs_info" type="button" disabled>Needs info</button>
                <button class="btn btn--danger btn--sm" data-bulk-act="reject" type="button" disabled>Reject</button>
              </div>
            </header>
            <form class="dash-card__filters" data-queue-form>
              <label class="dash-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search name, slug, EIIN…" data-q autocomplete="off" />
              </label>
              <label class="dash-filter">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <select data-status>
                  <option value="pending">Pending</option>
                  <option value="needs_info">Needs info</option>
                  <option value="rejected">Rejected</option>
                  <option value="verified">Verified</option>
                  <option value="suspended">Suspended</option>
                  <option value="seeded">Seeded</option>
                  <option value="any">Any</option>
                </select>
              </label>
            </form>
            <div class="dash-table-wrap">
              <table class="dash-table" data-queue-table>
                <thead>
                  <tr>
                    <th class="t-check"><input type="checkbox" data-bulk-toggle aria-label="Select all" /></th>
                    <th>Institution</th>
                    <th>Status</th>
                    <th>Brand</th>
                    <th>Submitted</th>
                    <th class="text-right">Action</th>
                  </tr>
                </thead>
                <tbody data-queue-body>
                  <tr><td colspan="6"><div class="skeleton" style="height:48px;"></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- =================== USERS PANE =================== -->
        <section class="dash-pane" data-pane="users" hidden>
          <div class="card dash-card">
            <header class="dash-card__head">
              <div>
                <h2 class="dash-card__title">Users</h2>
                <p class="dash-card__sub">Promote trusted teammates to admin or revoke access. The last admin can't be demoted.</p>
              </div>
            </header>
            <form class="dash-card__filters" data-users-form>
              <label class="dash-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search email, name or mobile…" data-users-q autocomplete="off" />
              </label>
              <label class="dash-filter">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <select data-users-role>
                  <option value="any">All roles</option>
                  <option value="admin">Admins only</option>
                  <option value="user">Owners only</option>
                </select>
              </label>
            </form>
            <div class="dash-table-wrap">
              <table class="dash-table" data-users-table>
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Provider</th>
                    <th>Claims</th>
                    <th>Joined</th>
                    <th class="text-right">Action</th>
                  </tr>
                </thead>
                <tbody data-users-body>
                  <tr><td colspan="6"><div class="skeleton" style="height:48px;"></div></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- =================== RESERVED PANE =================== -->
        <section class="dash-pane" data-pane="reserved" hidden>
          <div class="card dash-card">
            <header class="dash-card__head">
              <div>
                <h2 class="dash-card__title">Reserved slugs</h2>
                <p class="dash-card__sub">Block these subdomains from being claimed. System defaults are seeded automatically.</p>
              </div>
            </header>
            <form class="dash-card__filters" data-reserved-form>
              <label class="dash-search">
                <input type="text" placeholder="Add reserved slug (e.g. www)" name="slug" required />
              </label>
              <label class="dash-search" style="flex:1;">
                <input type="text" placeholder="Reason (optional)" name="reason" />
              </label>
              <button class="btn btn--primary" type="submit">Reserve</button>
            </form>
            <div data-reserved class="reserved-list"></div>
          </div>
        </section>

        <!-- =================== AUDIT PANE =================== -->
        <section class="dash-pane" data-pane="audit" hidden>
          <div class="card dash-card">
            <header class="dash-card__head">
              <div>
                <h2 class="dash-card__title">Audit log</h2>
                <p class="dash-card__sub">Filter by action prefix, actor email, institution ID or free-text detail.</p>
              </div>
            </header>
            <form class="dash-card__filters dash-card__filters--audit" data-audit-form>
              <label class="dash-search">
                <input type="text" placeholder="Action prefix (e.g. admin.decide)" name="action" />
              </label>
              <label class="dash-search">
                <input type="text" placeholder="Search detail / actor email" name="q" />
              </label>
              <label class="dash-search">
                <input type="number" placeholder="Inst ID" name="inst_id" min="1" />
              </label>
              <button class="btn btn--primary" type="submit">Filter</button>
            </form>
            <div data-audit class="dash-table-wrap"></div>
          </div>
        </section>

        <!-- =================== EXPORTS PANE =================== -->
        <section class="dash-pane" data-pane="exports" hidden>
          <div class="card dash-card">
            <header class="dash-card__head">
              <div>
                <h2 class="dash-card__title">CSV exports</h2>
                <p class="dash-card__sub">Download every claim or every user as a UTF-8 CSV (Excel-friendly, includes Bengali columns).</p>
              </div>
            </header>
            <div class="exports-grid">
              <div class="export-card">
                <h4>All claims</h4>
                <p class="text-muted">Every institutions row — slug, status, DNS state, EIIN, contact info.</p>
                <a class="btn btn--primary" data-export="claims">Download claims.csv</a>
              </div>
              <div class="export-card">
                <h4>All users</h4>
                <p class="text-muted">Email, mobile, designation, division/district/upazila, admin flag.</p>
                <a class="btn" data-export="users">Download users.csv</a>
              </div>
            </div>
          </div>
        </section>

        <!-- =================== SETTINGS PANE =================== -->
        <section class="dash-pane" data-pane="settings" hidden>
          <div data-settings-host></div>
        </section>

        <!-- =================== INTEGRATIONS PANE =================== -->
        <section class="dash-pane" data-pane="integrations" hidden>
          <div data-integrations-host></div>
        </section>

        <!-- =================== PAYMENTS PANE =================== -->
        <section class="dash-pane" data-pane="payments" hidden>
          <div data-payments-admin-host></div>
        </section>

        <!-- =================== RENEWALS PANE (v4.1) =================== -->
        <section class="dash-pane" data-pane="renewals" hidden>
          <div data-renewals-host></div>
        </section>

      </main>
    </div>
  </div>
</section>

<!-- ============================ DETAIL MODAL ============================ -->
<div class="modal-bg" data-detail-modal>
  <div class="modal modal--xl">
    <button class="modal__close" data-close-detail aria-label="Close">&times;</button>
    <div data-detail-body><div class="skeleton" style="height:200px;"></div></div>
  </div>
</div>

<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
