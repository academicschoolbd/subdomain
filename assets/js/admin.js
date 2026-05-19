/* ===========================================================================
   institution.bd — Admin console
   v3.2 — sidebar shell, KPI overview, queue with bulk-decide, users mgmt,
         reserved slugs, audit log, exports, platform settings, integrations
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;

  /* Footer year. */
  const yEl = document.querySelector('[data-year]');
  if (yEl) yEl.textContent = new Date().getFullYear();

  const needAuth   = document.querySelector('[data-needs-auth]');
  const notAdmin   = document.querySelector('[data-not-admin]');
  const adminRoot  = document.querySelector('[data-admin-root]');
  const detailBg   = document.querySelector('[data-detail-modal]');
  const detailBody = document.querySelector('[data-detail-body]');

  /* Caches so the table panes don't refetch unnecessarily. */
  let _stats = null;
  let _claims = [];
  let _users  = [];
  const _filter = {
    queue:  { q: '', status: 'pending' },
    users:  { q: '', role: 'any' },
  };
  /* Set of selected institution IDs for the queue bulk action. */
  const _selectedIds = new Set();

  /* ---------------------------------------------------------------- */
  /*  Boot                                                             */
  /* ---------------------------------------------------------------- */
  if (!App.isAuthed()) {
    if (needAuth) needAuth.hidden = false;
    return;
  }

  App.api('/auth/me').then((r) => {
    App.setSession(null, r.user);
    if (!r.user || !r.user.is_admin) {
      if (notAdmin) notAdmin.hidden = false;
      return;
    }
    if (adminRoot) adminRoot.hidden = false;
    wirePaneSwitcher();
    wireDetailModal();
    wireQueue();
    wireUsers();
    wireReserved();
    wireAudit();
    wireExports();
    loadOverview();
  }).catch((e) => {
    if (e && e.status === 401) {
      App.clearSession();
      if (needAuth) needAuth.hidden = false;
    } else {
      App.toast((e && e.detail) || 'Could not load admin console', 'error');
    }
  });

  /* ---------------------------------------------------------------- */
  /*  Pane switcher                                                    */
  /* ---------------------------------------------------------------- */
  function activatePane(name) {
    document.querySelectorAll('[data-pane-btn]').forEach((b) => {
      b.classList.toggle('active', b.getAttribute('data-pane-btn') === name);
    });
    document.querySelectorAll('[data-pane]').forEach((p) => {
      p.hidden = p.getAttribute('data-pane') !== name;
    });
    // Lazy-load each pane on first open.
    if (name === 'queue')        loadQueue();
    if (name === 'users')        loadUsers();
    if (name === 'reserved')     loadReserved();
    if (name === 'audit')        loadAudit();
    if (name === 'settings')     loadSettings();
    if (name === 'integrations') loadIntegrations();
    if (name === 'payments')     loadAdminPayments();
    if (name === 'renewals')     loadRenewals();

    // v4.5 — instant-response feel:
    //  1) Close the global mobile drawer (hamburger menu) if it's open.
    //  2) On phones / tablets where the sidebar collapses to a horizontal
    //     strip, scroll the active pane into view at the top of the
    //     viewport so the user can immediately read the new content
    //     instead of staring at the unchanged sidebar.
    const drawer = document.querySelector('[data-nav-mobile]');
    if (drawer && drawer.classList.contains('open')) {
      drawer.classList.remove('open');
      const t = document.querySelector('[data-nav-toggle]');
      if (t) t.setAttribute('aria-expanded', 'false');
    }
    if (window.matchMedia && window.matchMedia('(max-width: 899px)').matches) {
      const main = document.querySelector('.dash-main');
      if (main && main.scrollIntoView) {
        main.scrollIntoView({ behavior: 'auto', block: 'start' });
      }
    }
  }

  function wirePaneSwitcher() {
    document.querySelectorAll('[data-pane-btn]').forEach((b) => {
      b.addEventListener('click', () => activatePane(b.getAttribute('data-pane-btn')));
    });
    document.querySelectorAll('[data-jump-pane]').forEach((b) => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        activatePane(b.getAttribute('data-jump-pane'));
        // Scroll to top so the user lands on the pane head.
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
    document.querySelectorAll('[data-signout]').forEach((b) => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        App.clearSession();
        location.href = '/';
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Status pill helpers                                              */
  /* ---------------------------------------------------------------- */
  function statusBadge(s) {
    const map = {
      verified: 'badge--verified',
      pending:  'badge--pending',
      needs_info: 'badge--warning',
      rejected: 'badge--danger',
      seeded:   'badge--muted',
      suspended: 'badge--danger',
    };
    return `<span class="badge ${map[s] || ''}">${App.escapeHtml(s)}</span>`;
  }

  /* ---------------------------------------------------------------- */
  /*  Overview — KPI tiles, recent activity                            */
  /* ---------------------------------------------------------------- */
  function loadOverview() {
    App.api('/admin/stats').then((r) => {
      _stats = r;
      renderKpiTiles(r);
      renderActivity(r.recent_activity || []);
      // Sidebar pending-count badge.
      const navBadge = document.querySelector('[data-nav-pending]');
      if (navBadge) {
        const p = (r.counts && r.counts.pending) || 0;
        navBadge.textContent = p;
        navBadge.hidden = p === 0;
      }
      // Update the banner copy when moderation is OFF (so admins know they
      // are running in instant-claim mode). v4.1 — read the new
      // require_approval toggle (legacy installs surface require_documents
      // as a fallback, see route_admin_settings_get).
      App.api('/admin/settings').then((s) => {
        const reqApproval = !!(s && s.settings && s.settings.require_approval);
        const t = document.querySelector('[data-admin-banner-title]');
        const sub = document.querySelector('[data-admin-banner-sub]');
        const banner = document.querySelector('[data-admin-banner]');
        if (banner) banner.classList.toggle('dash-banner--warn', !reqApproval);
        if (t) {
          t.textContent = reqApproval
            ? 'Moderation is ON — every claim waits for your approval.'
            : 'Moderation is OFF — claims auto-verify on submit.';
        }
        if (sub) {
          sub.innerHTML = reqApproval
            ? 'New subdomains land as <code>pending</code> and stay private until you approve them. You can flip moderation OFF in <strong>Platform settings</strong> to restore the v3.0 instant-claim flow.'
            : 'Every claim goes <code>verified</code> immediately and Cloudflare publishes a record on the spot. Flip moderation back ON in <strong>Platform settings</strong> if abuse appears.';
        }
      }).catch(() => { /* keep default banner */ });
    }).catch((e) => {
      App.toast((e && e.detail) || 'Failed to load stats', 'error');
    });
  }

  function renderKpiTiles(r) {
    const host = document.querySelector('[data-kpi-host]');
    if (!host) return;
    const c = r.counts || {};
    const tile = (kind, ico, num, lab, hint, jumpTo, status) => `
      <button class="quick-tile quick-tile--${kind}" type="button"
              ${jumpTo ? `data-jump-pane="${jumpTo}"` : ''}
              ${status ? `data-jump-status="${status}"` : ''}>
        <span class="quick-tile__ico" aria-hidden="true">${ico}</span>
        <span class="quick-tile__num">${num}</span>
        <span class="quick-tile__lab">${lab}</span>
        ${hint ? `<span class="quick-tile__hint">${hint}</span>` : ''}
      </button>`;

    host.innerHTML =
      tile('pending',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        c.pending || 0, 'Pending review', (c.pending || 0) ? 'Tap to open queue' : 'You\'re all caught up', 'queue', 'pending')
      + tile('verified',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        c.verified || 0, 'Verified live', (r.verified_today ? r.verified_today + ' approved in last 24 h' : 'Across all brands'), 'queue', 'verified')
      + tile('action',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        (c.needs_info || 0) + (c.rejected || 0) + (c.suspended || 0), 'Needs attention',
        'Needs-info / rejected / suspended', 'queue', 'needs_info')
      + tile('total',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        r.users || 0, 'Registered users', (r.admins || 0) + ' admin(s)', 'users');

    host.querySelectorAll('[data-jump-pane]').forEach((b) => {
      b.addEventListener('click', () => {
        const status = b.getAttribute('data-jump-status');
        if (status) {
          _filter.queue.status = status;
          // Mirror to the queue dropdown so it shows the active filter when
          // the queue pane mounts.
          const sel = document.querySelector('[data-queue-form] [data-status]');
          if (sel) sel.value = status;
        }
        activatePane(b.getAttribute('data-jump-pane'));
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Activity feed                                                    */
  /* ---------------------------------------------------------------- */
  function activityActor(a) {
    if (a.actor_name)  return a.actor_name;
    if (a.actor_email) return a.actor_email;
    if (a.actor_user_id) return 'user #' + a.actor_user_id;
    return 'system';
  }
  function activityVerb(action) {
    // "admin.decide.approve" -> "approved", etc.
    const map = {
      'admin.decide.approve':     'approved',
      'admin.decide.reject':      'rejected',
      'admin.decide.needs_info':  'asked for more info on',
      'admin.decide.suspend':     'suspended',
      'admin.bulk_decide.approve':'bulk-approved',
      'admin.bulk_decide.reject': 'bulk-rejected',
      'admin.bulk_decide.needs_info':'bulk-asked-info on',
      'admin.bulk_decide.suspend':'bulk-suspended',
      'admin.dns.retry':          'retried DNS for',
      'admin.reserved.add':       'reserved a slug',
      'admin.reserved.delete':    'released a reserved slug',
      'admin.settings.update':    'updated platform settings',
      'admin.users.role':         'changed a user role',
      'admin.integrations.update':'updated integrations',
      'admin.integrations.rotate_jwt':'rotated the JWT secret',
      'claim.create':             'submitted a claim',
      'claim.withdraw':           'withdrew a claim',
      'claim.upload_doc':         'uploaded a document for',
      'claim.auto_dns.live':      'auto-published DNS for',
      'claim.auto_dns.error':     'failed auto-DNS for',
      'tenant.site_update':       'updated the site profile of',
      'tenant.upload_image':      'uploaded an image to',
      'tenant.notice.create':     'posted a notice on',
      'tenant.notice.delete':     'deleted a notice on',
    };
    return map[action] || action;
  }

  function renderActivity(items) {
    const host = document.querySelector('[data-activity-host]');
    if (!host) return;
    if (!items.length) {
      host.innerHTML = '<p class="text-muted">No recent activity yet.</p>';
      return;
    }
    host.innerHTML = items.map((a) => `
      <div class="activity-item">
        <span class="activity-item__dot" aria-hidden="true"></span>
        <div class="activity-item__body">
          <p>
            <strong>${App.escapeHtml(activityActor(a))}</strong>
            ${App.escapeHtml(activityVerb(a.action))}
            ${a.institution_id ? `<a href="#" data-detail="${a.institution_id}">claim #${a.institution_id}</a>` : ''}
            ${a.detail && !a.institution_id ? `<span class="text-muted">— ${App.escapeHtml(truncate(a.detail, 80))}</span>` : ''}
          </p>
          <small class="text-muted">${App.escapeHtml(App.fmtDate(a.created_at))} · <code>${App.escapeHtml(a.action)}</code></small>
        </div>
      </div>`).join('');
    host.querySelectorAll('[data-detail]').forEach((b) => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        openDetail(parseInt(b.getAttribute('data-detail'), 10));
      });
    });
  }
  function truncate(s, n) {
    s = String(s || '');
    return s.length <= n ? s : s.slice(0, n - 1) + '…';
  }

  /* ---------------------------------------------------------------- */
  /*  Queue pane                                                       */
  /* ---------------------------------------------------------------- */
  function wireQueue() {
    const form  = document.querySelector('[data-queue-form]');
    const qEl   = form && form.querySelector('[data-q]');
    const sEl   = form && form.querySelector('[data-status]');
    if (form) form.addEventListener('submit', (e) => e.preventDefault());
    if (qEl) {
      const debounced = App.debounce(() => {
        _filter.queue.q = (qEl.value || '').trim();
        loadQueue();
      }, 250);
      qEl.addEventListener('input', debounced);
    }
    if (sEl) {
      sEl.addEventListener('change', () => {
        _filter.queue.status = sEl.value;
        loadQueue();
      });
    }
    // Bulk select-all checkbox.
    const tog = document.querySelector('[data-bulk-toggle]');
    if (tog) {
      tog.addEventListener('change', () => {
        const want = tog.checked;
        document.querySelectorAll('[data-row-check]').forEach((c) => {
          c.checked = want;
          const id = parseInt(c.getAttribute('data-row-check'), 10);
          if (want) _selectedIds.add(id); else _selectedIds.delete(id);
        });
        updateBulkButtons();
      });
    }
    // Bulk action buttons.
    document.querySelectorAll('[data-bulk-act]').forEach((b) => {
      b.addEventListener('click', () => bulkDecide(b.getAttribute('data-bulk-act')));
    });
  }

  function loadQueue() {
    const tbody = document.querySelector('[data-queue-body]');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6"><div class="skeleton" style="height:48px;"></div></td></tr>';
    _selectedIds.clear();
    updateBulkButtons();

    const params = {
      status: _filter.queue.status || 'pending',
      q: _filter.queue.q || '',
      limit: 100,
    };
    App.api('/admin/claims' + App.qs(params)).then((r) => {
      _claims = r.items || [];
      renderQueueRows(tbody);
    }).catch((e) => {
      tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${App.escapeHtml((e && e.detail) || 'Load failed')}</td></tr>`;
    });
  }

  function renderQueueRows(tbody) {
    if (!_claims.length) {
      tbody.innerHTML = `
        <tr><td colspan="6">
          <div class="dash-empty">
            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p>No claims match this filter.</p>
          </div>
        </td></tr>`;
      return;
    }
    tbody.innerHTML = _claims.map((c) => `
      <tr data-row="${c.id}">
        <td class="t-check"><input type="checkbox" data-row-check="${c.id}" aria-label="Select" /></td>
        <td>
          <div class="dash-domain">
            <span class="dash-domain__logo">${App.escapeHtml(App.initialsOf(c.name_en || c.slug))}</span>
            <div>
              <div class="dash-domain__name">${App.escapeHtml(c.subdomain)}</div>
              <div class="dash-domain__meta">${App.escapeHtml(c.name_en || c.slug)}${c.eiin ? ' · EIIN ' + App.escapeHtml(c.eiin) : ''}</div>
            </div>
          </div>
        </td>
        <td>${statusBadge(c.status)}</td>
        <td><span class="badge badge--brand">${App.escapeHtml(c.brand)}</span></td>
        <td>${App.escapeHtml(App.fmtDate(c.created_at))}</td>
        <td class="text-right">
          <button class="btn btn--primary btn--sm" type="button" data-detail="${c.id}">Review</button>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-row-check]').forEach((c) => {
      c.addEventListener('change', () => {
        const id = parseInt(c.getAttribute('data-row-check'), 10);
        if (c.checked) _selectedIds.add(id); else _selectedIds.delete(id);
        updateBulkButtons();
      });
    });
    tbody.querySelectorAll('[data-detail]').forEach((b) => {
      b.addEventListener('click', () => openDetail(parseInt(b.getAttribute('data-detail'), 10)));
    });
  }

  function updateBulkButtons() {
    const n = _selectedIds.size;
    document.querySelectorAll('[data-bulk-act]').forEach((b) => {
      b.disabled = n === 0;
      const label = b.getAttribute('data-bulk-act');
      const base = {
        approve: 'Approve selected',
        needs_info: 'Needs info',
        reject: 'Reject',
      }[label] || label;
      b.textContent = n > 0 ? `${base} (${n})` : base;
    });
    const tog = document.querySelector('[data-bulk-toggle]');
    if (tog) tog.checked = n > 0 && n === document.querySelectorAll('[data-row-check]').length;
  }

  async function bulkDecide(decision) {
    if (!_selectedIds.size) return;
    const ids = Array.from(_selectedIds);
    let notes = '';
    if (decision === 'reject' || decision === 'needs_info') {
      notes = (prompt(`Optional note shown to all ${ids.length} owner(s):`, '') || '').trim();
    }
    if (!confirm(`${decision.toUpperCase()} ${ids.length} claim(s)?`)) return;
    try {
      const r = await App.api('/admin/queue/bulk-decide', {
        method: 'POST',
        body: { ids, decision, notes },
      });
      App.toast(`${decision} applied to ${r.count} claim(s)`, 'success');
      _selectedIds.clear();
      loadOverview();
      loadQueue();
    } catch (e) {
      App.toast((e && e.detail) || 'Bulk action failed', 'error');
    }
  }

  /* ---------------------------------------------------------------- */
  /*  Detail modal — single-claim review (kept from v3.0)              */
  /* ---------------------------------------------------------------- */
  function wireDetailModal() {
    const close = document.querySelector('[data-close-detail]');
    if (close) close.addEventListener('click', closeDetail);
    if (detailBg) detailBg.addEventListener('click', (e) => {
      if (e.target === detailBg) closeDetail();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && detailBg && detailBg.classList.contains('open')) closeDetail();
    });
  }
  function closeDetail() {
    if (detailBg) detailBg.classList.remove('open');
  }
  function openDetail(id) {
    if (!detailBg || !detailBody) return;
    detailBody.innerHTML = '<div class="skeleton" style="height:200px;"></div>';
    detailBg.classList.add('open');
    App.api('/admin/claims/' + id).then((r) => renderDetail(r)).catch((e) => {
      detailBody.innerHTML = `<p class="text-danger">${App.escapeHtml((e && e.detail) || 'Could not load')}</p>`;
    });
  }
  function renderDetail(r) {
    const c = r.institution;
    const docs = r.documents || [];
    const owner = r.owner;
    const expiryHtml = (() => {
      if (!c.expires_at) return '—';
      const d  = App.fmtDate(c.expires_at);
      const dt = c.days_to_expiry;
      if (dt == null) return App.escapeHtml(d);
      if (dt < 0)  return `${App.escapeHtml(d)} <span class="badge badge--danger">expired ${-dt}d ago</span>`;
      if (dt <= 30) return `${App.escapeHtml(d)} <span class="badge badge--warning">in ${dt}d</span>`;
      return `${App.escapeHtml(d)} <span class="text-muted">(in ${dt}d)</span>`;
    })();
    const fields = [
      ['Brand', c.brand], ['Slug', c.slug], ['Subdomain', c.subdomain],
      ['Name (EN)', c.name_en], ['Name (BN)', c.name_bn], ['Category', c.category],
      ['EIIN', c.eiin], ['Division', c.division], ['District', c.district], ['Upazila', c.upazila],
      ['Address', c.address], ['Contact name', c.contact_name],
      ['Contact phone', c.contact_phone], ['Contact email', c.contact_email], ['Website', c.website],
      ['Status', c.status], ['DNS status', c.dns_status || '—'],
      ['Verified at', c.verified_at ? App.fmtDate(c.verified_at) : '—'],
      ['Owner', owner ? (owner.name || owner.email || ('user#' + owner.id)) : '—'],
    ];
    const tok = encodeURIComponent(App.getToken() || '');
    const docTypes = ['eiin_certificate', 'board_letter', 'trade_license', 'nid', 'selfie', 'other'];
    detailBody.innerHTML = `
      <h2 style="margin-top:0;">${App.escapeHtml(c.name_en || c.subdomain)}</h2>
      <p class="text-muted"><code>${App.escapeHtml(c.subdomain)}</code> · ${statusBadge(c.status)}
        ${c.expires_at ? ' · expires ' + expiryHtml : ''}
      </p>
      <div class="grid-2 mt-3">
        <table class="kv">${fields.map(([k, v]) => `<tr><th>${App.escapeHtml(k)}</th><td>${App.escapeHtml(v || '—')}</td></tr>`).join('')}
          <tr><th>Expires</th><td>${expiryHtml}</td></tr>
        </table>
        <div>
          <h4 class="flex-between" style="margin-top:0;">
            <span>Documents <span class="text-muted" style="font-weight:400;">(${docs.length})</span></span>
          </h4>
          ${docs.length
            ? '<div class="docs-admin">' + docs.map((d) => `
                <article class="doc-admin" data-doc-row="${d.id}">
                  <header class="doc-admin__head">
                    <select class="doc-admin__type" data-doc-type="${d.id}">
                      ${docTypes.map((t) => `<option value="${t}" ${t === d.doc_type ? 'selected' : ''}>${App.escapeHtml(t)}</option>`).join('')}
                    </select>
                    <span class="text-muted doc-admin__name">${App.escapeHtml(d.filename)}</span>
                    <div class="doc-admin__actions">
                      <button class="btn btn--ghost btn--sm" type="button" data-doc-toggle="${d.id}" data-mime="${App.escapeHtml(d.content_type || '')}">Preview</button>
                      <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" href="/api/admin/documents/${d.id}?_t=${tok}">Open</a>
                      <button class="btn btn--ghost btn--sm btn--danger-text" type="button" data-doc-del="${d.id}">Delete</button>
                    </div>
                  </header>
                  <div class="doc-admin__preview" data-doc-preview="${d.id}" hidden></div>
                </article>
              `).join('') + '</div>'
            : '<p class="text-muted">No documents.</p>'}
          ${c.about_en ? `<h4 class="mt-3">About (English)</h4><p>${App.escapeHtml(c.about_en)}</p>` : ''}
          ${c.about_bn ? `<h4 class="mt-3">পরিচিতি</h4><p style="font-family:'Noto Sans Bengali',sans-serif;">${App.escapeHtml(c.about_bn)}</p>` : ''}
        </div>
      </div>

      ${c.status === 'verified' ? `
        <h4 class="mt-4">Domain expiry</h4>
        <form data-extend="${c.id}" class="form-grid form-grid--2">
          <div class="field">
            <label class="label">Extend by (days)</label>
            <input name="days" type="number" min="1" max="3650" value="365" />
            <p class="hint">Leave the absolute date empty and just press <strong>Extend</strong> to bump from the current expiry.</p>
          </div>
          <div class="field">
            <label class="label">Or set absolute expiry (optional)</label>
            <input name="expires_at" type="text" placeholder="YYYY-MM-DD HH:MM:SS" />
          </div>
          <div class="field field--wide text-right">
            <button class="btn" type="submit">Extend expiry</button>
          </div>
        </form>
      ` : ''}

      <h4 class="mt-4">Decision</h4>
      <form data-decide="${c.id}" class="form-grid">
        <div class="field field--wide"><label class="label">Notes (shown to owner)</label><textarea name="notes" rows="2"></textarea></div>
        <div class="field field--wide flex" style="flex-wrap:wrap;gap:8px;">
          <button class="btn btn--primary" data-act="approve" type="button">Approve &amp; create DNS</button>
          <button class="btn" data-act="needs_info" type="button">Needs info</button>
          <button class="btn btn--danger" data-act="reject" type="button">Reject</button>
          <button class="btn btn--ghost" data-act="suspend" type="button">Suspend</button>
          ${c.status === 'verified' && c.dns_status !== 'live' ? '<button class="btn btn--sm" data-act="dns-retry" type="button">Retry DNS</button>' : ''}
        </div>
      </form>`;

    // Wire decide buttons.
    const form = detailBody.querySelector('[data-decide]');
    form.querySelectorAll('button[data-act]').forEach((b) => {
      b.addEventListener('click', async () => {
        const act = b.getAttribute('data-act');
        const notes = form.querySelector('[name=notes]').value;
        try {
          if (act === 'dns-retry') {
            const r2 = await App.api('/admin/claims/' + c.id + '/dns-retry', { method: 'POST' });
            App.toast(r2.ok ? 'DNS retry submitted' : (r2.dns && r2.dns.message) || 'DNS error', r2.ok ? 'success' : 'error');
          } else {
            await App.api('/admin/claims/' + c.id + '/decide', { method: 'POST', body: { decision: act, notes } });
            App.toast('Decision: ' + act, 'success');
          }
          closeDetail();
          loadOverview();
          loadQueue();
        } catch (e2) { App.toast((e2 && e2.detail) || 'Action failed', 'error'); }
      });
    });

    // Wire extend form (verified only).
    const extForm = detailBody.querySelector('[data-extend]');
    if (extForm) {
      extForm.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(extForm);
        const days = parseInt(fd.get('days') || '0', 10);
        const abs  = (fd.get('expires_at') || '').toString().trim();
        const body = {};
        if (abs) body.expires_at = abs; else body.days = days;
        try {
          await App.api('/admin/claims/' + c.id + '/extend', { method: 'POST', body });
          App.toast('Expiry extended', 'success');
          openDetail(c.id);
        } catch (e2) { App.toast((e2 && e2.detail) || 'Extend failed', 'error'); }
      });
    }

    // Wire doc preview / type / delete.
    detailBody.querySelectorAll('[data-doc-toggle]').forEach((b) => {
      b.addEventListener('click', () => {
        const id = b.getAttribute('data-doc-toggle');
        const mime = (b.getAttribute('data-mime') || '').toLowerCase();
        const host = detailBody.querySelector('[data-doc-preview="' + id + '"]');
        if (!host) return;
        if (host.hidden) {
          const url = '/api/admin/documents/' + id + '?_t=' + tok;
          if (mime.startsWith('image/')) {
            host.innerHTML = `<img src="${url}" alt="" class="doc-admin__img" />`;
          } else if (mime === 'application/pdf') {
            host.innerHTML = `<iframe class="doc-admin__pdf" src="${url}" title="Document preview"></iframe>`;
          } else {
            host.innerHTML = `<p class="text-muted">Preview not supported for <code>${App.escapeHtml(mime || 'unknown')}</code>. <a target="_blank" rel="noopener" href="${url}">Open in a new tab →</a></p>`;
          }
          host.hidden = false;
          b.textContent = 'Hide';
        } else {
          host.hidden = true;
          host.innerHTML = '';
          b.textContent = 'Preview';
        }
      });
    });
    detailBody.querySelectorAll('[data-doc-type]').forEach((sel) => {
      sel.addEventListener('change', async () => {
        const id = sel.getAttribute('data-doc-type');
        try {
          await App.api('/admin/documents/' + id, { method: 'PATCH', body: { doc_type: sel.value } });
          App.toast('Document type updated', 'success');
        } catch (e) { App.toast((e && e.detail) || 'Update failed', 'error'); }
      });
    });
    detailBody.querySelectorAll('[data-doc-del]').forEach((b) => {
      b.addEventListener('click', async () => {
        const id = b.getAttribute('data-doc-del');
        if (!confirm('Delete this document permanently? The file will be removed from disk.')) return;
        try {
          await App.api('/admin/documents/' + id, { method: 'DELETE' });
          App.toast('Document deleted', 'success');
          openDetail(c.id);
        } catch (e) { App.toast((e && e.detail) || 'Delete failed', 'error'); }
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Users pane                                                       */
  /* ---------------------------------------------------------------- */
  function wireUsers() {
    const form = document.querySelector('[data-users-form]');
    if (!form) return;
    form.addEventListener('submit', (e) => e.preventDefault());
    const qEl = form.querySelector('[data-users-q]');
    const rEl = form.querySelector('[data-users-role]');
    if (qEl) {
      const debounced = App.debounce(() => {
        _filter.users.q = (qEl.value || '').trim();
        loadUsers();
      }, 250);
      qEl.addEventListener('input', debounced);
    }
    if (rEl) {
      rEl.addEventListener('change', () => {
        _filter.users.role = rEl.value;
        loadUsers();
      });
    }
  }

  function loadUsers() {
    const tbody = document.querySelector('[data-users-body]');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6"><div class="skeleton" style="height:48px;"></div></td></tr>';
    const params = {
      q: _filter.users.q || '',
      role: _filter.users.role || 'any',
      limit: 100,
    };
    App.api('/admin/users' + App.qs(params)).then((r) => {
      _users = r.items || [];
      renderUsersRows(tbody);
    }).catch((e) => {
      tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${App.escapeHtml((e && e.detail) || 'Load failed')}</td></tr>`;
    });
  }

  function renderUsersRows(tbody) {
    if (!_users.length) {
      tbody.innerHTML = `<tr><td colspan="6"><div class="dash-empty"><p>No users match this filter.</p></div></td></tr>`;
      return;
    }
    const me = App.getUser() || {};
    tbody.innerHTML = _users.map((u) => {
      const isSelf = (u.id === me.id);
      const rolePill = u.is_admin
        ? '<span class="badge badge--brand">Admin</span>'
        : '<span class="badge badge--muted">Owner</span>';
      const provider = u.provider ? App.escapeHtml(u.provider) : 'email';
      const profile = u.profile_complete
        ? '<span class="badge badge--verified">profile complete</span>'
        : '<span class="badge badge--warning">profile incomplete</span>';
      const action = isSelf
        ? '<span class="text-muted">(you)</span>'
        : (u.is_admin
            ? `<button class="btn btn--sm btn--danger-text" type="button" data-set-role="${u.id}:0">Demote</button>`
            : `<button class="btn btn--sm btn--primary" type="button" data-set-role="${u.id}:1">Make admin</button>`);
      return `
        <tr data-row="${u.id}">
          <td>
            <div class="dash-domain">
              <span class="dash-domain__logo">${App.escapeHtml(App.initialsOf(u.name || u.email || ''))}</span>
              <div>
                <div class="dash-domain__name" style="font-family:inherit;">${App.escapeHtml(u.name || '(no name)')}</div>
                <div class="dash-domain__meta">${App.escapeHtml(u.email || u.phone || u.mobile || '—')} · ${profile}</div>
              </div>
            </div>
          </td>
          <td>${rolePill}</td>
          <td><code>${provider}</code></td>
          <td>${u.claim_count || 0}</td>
          <td>${App.escapeHtml(App.fmtDate(u.created_at))}</td>
          <td class="text-right">${action}</td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-set-role]').forEach((b) => {
      b.addEventListener('click', () => {
        const [idStr, flagStr] = b.getAttribute('data-set-role').split(':');
        setUserRole(parseInt(idStr, 10), flagStr === '1');
      });
    });
  }

  async function setUserRole(userId, makeAdmin) {
    const verb = makeAdmin ? 'promote to admin' : 'demote to owner';
    const u = _users.find((x) => x.id === userId);
    if (!u) return;
    if (!confirm(`Are you sure you want to ${verb} ${u.name || u.email || ('user #' + userId)}?`)) return;
    try {
      await App.api('/admin/users/' + userId + '/role', {
        method: 'POST',
        body: { is_admin: makeAdmin },
      });
      App.toast('Role updated', 'success');
      loadUsers();
      loadOverview();
    } catch (e) {
      App.toast((e && e.detail) || 'Could not change role', 'error');
    }
  }

  /* ---------------------------------------------------------------- */
  /*  Reserved slugs pane                                              */
  /* ---------------------------------------------------------------- */
  function wireReserved() {
    const form = document.querySelector('[data-reserved-form]');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      try {
        await App.api('/admin/reserved-slugs', {
          method: 'POST',
          body: { slug: fd.get('slug'), reason: fd.get('reason') },
        });
        App.toast('Reserved', 'success');
        form.reset();
        loadReserved();
      } catch (e2) { App.toast((e2 && e2.detail) || 'Failed', 'error'); }
    });
  }
  function loadReserved() {
    const host = document.querySelector('[data-reserved]');
    if (!host) return;
    host.innerHTML = '<div class="skeleton" style="height:60px;"></div>';
    App.api('/admin/reserved-slugs').then((r) => {
      if (!r.items.length) {
        host.innerHTML = '<p class="text-muted" style="padding:14px 24px;">No reserved slugs yet.</p>';
        return;
      }
      host.innerHTML = '<table class="dash-table"><thead><tr><th>Slug</th><th>Reason</th><th class="text-right">Action</th></tr></thead><tbody>'
        + r.items.map((i) => `
          <tr>
            <td><code>${App.escapeHtml(i.slug)}</code></td>
            <td>${App.escapeHtml(i.reason || '—')}</td>
            <td class="text-right"><button class="btn btn--sm btn--danger-text" type="button" data-del-res="${i.id}">Delete</button></td>
          </tr>`).join('')
        + '</tbody></table>';
      host.querySelectorAll('[data-del-res]').forEach((b) => {
        b.addEventListener('click', async () => {
          if (!confirm('Remove reservation?')) return;
          await App.api('/admin/reserved-slugs/' + b.getAttribute('data-del-res'), { method: 'DELETE' });
          loadReserved();
        });
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Audit log pane                                                   */
  /* ---------------------------------------------------------------- */
  function wireAudit() {
    const form = document.querySelector('[data-audit-form]');
    if (!form) return;
    form.addEventListener('submit', (e) => { e.preventDefault(); loadAudit(); });
  }
  function loadAudit() {
    const host = document.querySelector('[data-audit]');
    const form = document.querySelector('[data-audit-form]');
    if (!host || !form) return;
    const fd = new FormData(form);
    const params = {
      action:  (fd.get('action')  || '').toString().trim() || undefined,
      q:       (fd.get('q')       || '').toString().trim() || undefined,
      inst_id: (fd.get('inst_id') || '').toString().trim() || undefined,
      limit: 100,
    };
    host.innerHTML = '<div class="skeleton" style="height:80px;"></div>';
    App.api('/admin/audit' + App.qs(params)).then((r) => {
      if (!r.items.length) { host.innerHTML = '<p class="text-muted" style="padding:14px 24px;">No audit entries match.</p>'; return; }
      host.innerHTML = '<table class="dash-table"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Inst</th><th>Detail</th></tr></thead><tbody>'
        + r.items.map((a) => `
          <tr>
            <td>${App.escapeHtml(App.fmtDate(a.created_at))}</td>
            <td>${App.escapeHtml(a.actor_name || a.actor_email || (a.actor_user_id ? '#' + a.actor_user_id : 'system'))}</td>
            <td><code>${App.escapeHtml(a.action)}</code></td>
            <td>${a.institution_id ? '#' + a.institution_id : '—'}</td>
            <td style="max-width:380px;word-break:break-word;">${App.escapeHtml(a.detail || '')}</td>
          </tr>`).join('')
        + '</tbody></table>'
        + (r.total ? `<p class="text-muted" style="padding:8px 24px;">Showing ${r.items.length} of ${r.total} entries.</p>` : '');
    }).catch((e) => { host.innerHTML = '<p class="text-danger" style="padding:14px 24px;">' + App.escapeHtml((e && e.detail) || 'Load failed') + '</p>'; });
  }

  /* ---------------------------------------------------------------- */
  /*  CSV export buttons                                               */
  /* ---------------------------------------------------------------- */
  function wireExports() {
    document.querySelectorAll('[data-export]').forEach((a) => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const which = a.getAttribute('data-export');
        const url = '/api/admin/export/' + which + '.csv?_t=' + encodeURIComponent(App.getToken() || '');
        const w = window.open(url, '_blank');
        if (!w) location.href = url;
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Platform settings pane                                           */
  /* ---------------------------------------------------------------- */
  /**
   * v4.5 — theme color picker. Renders a row of preset swatches plus a
   * custom <input type="color"> + hex text input. The first swatch is
   * always "Default" (empty value, falls back to platform default).
   */
  const THEME_PRESETS = [
    { name: 'Default teal',  hex: '#0f766e' },
    { name: 'Bottle green',  hex: '#15803d' },
    { name: 'Royal blue',    hex: '#1d4ed8' },
    { name: 'Indigo',        hex: '#4338ca' },
    { name: 'Purple',        hex: '#7c3aed' },
    { name: 'Pink',          hex: '#db2777' },
    { name: 'Crimson',       hex: '#be123c' },
    { name: 'Orange',        hex: '#ea580c' },
    { name: 'Amber',         hex: '#b45309' },
    { name: 'Slate',         hex: '#334155' },
  ];

  function themePickerBlock(s) {
    const cur = (s && s.theme_primary_hex) ? String(s.theme_primary_hex).toLowerCase() : '';
    const explicit = !!(s && s.theme_primary_hex_set);
    const swatchHtml = THEME_PRESETS.map((p) => {
      const active = explicit && cur === p.hex.toLowerCase();
      return `<button type="button" class="theme-swatch ${active ? 'active' : ''}"
        style="--swatch:${p.hex};"
        data-theme-swatch="${p.hex}"
        title="${App.escapeHtml(p.name)}"
        aria-label="${App.escapeHtml(p.name)}"></button>`;
    }).join('');
    const initialHex = explicit ? cur : '';
    const initialDark = (s && s.theme_primary_dark_hex_set) ? String(s.theme_primary_dark_hex || '') : '';
    return `
      <div class="theme-picker">
        <div class="theme-swatches">${swatchHtml}</div>
        <div class="theme-custom">
          <label class="text-muted" style="font-size:.85rem;font-weight:600;">Custom hex
            <input type="color" data-theme-color value="${App.escapeHtml(initialHex || '#0f766e')}" aria-label="Pick custom hex" />
          </label>
          <input type="text" name="theme_primary_hex"
            data-theme-hex
            value="${App.escapeHtml(initialHex)}"
            placeholder="#0f766e"
            maxlength="7"
            autocomplete="off"
            spellcheck="false" />
          <span class="theme-preview">
            <span class="theme-preview-pill" data-theme-preview>Preview</span>
          </span>
        </div>
        <details class="mt-2" style="margin-top:10px;">
          <summary style="cursor:pointer;font-weight:600;font-size:.88rem;color:var(--c-muted);">
            Pin a custom dark-mode hex (optional)
          </summary>
          <div class="theme-custom mt-2" style="margin-top:10px;">
            <label class="text-muted" style="font-size:.85rem;font-weight:600;">Dark hex
              <input type="color" data-theme-dark-color value="${App.escapeHtml(initialDark || '#14b8a6')}" aria-label="Pick dark-mode hex" />
            </label>
            <input type="text" name="theme_primary_dark_hex"
              data-theme-dark-hex
              value="${App.escapeHtml(initialDark)}"
              placeholder="auto (lightened from above)"
              maxlength="7"
              autocomplete="off"
              spellcheck="false" />
          </div>
        </details>
      </div>`;
  }

  function _isHex(v) { return /^#([0-9a-f]{6}|[0-9a-f]{3})$/i.test(String(v || '').trim()); }
  function _expandHex(v) {
    v = String(v || '').trim().toLowerCase();
    if (v[0] !== '#') v = '#' + v;
    if (/^#[0-9a-f]{3}$/.test(v)) {
      v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
    }
    return v;
  }

  function wireThemePicker(host) {
    const swatches = host.querySelectorAll('[data-theme-swatch]');
    const colorInp = host.querySelector('[data-theme-color]');
    const hexInp   = host.querySelector('[data-theme-hex]');
    const darkColorInp = host.querySelector('[data-theme-dark-color]');
    const darkHexInp   = host.querySelector('[data-theme-dark-hex]');
    const preview  = host.querySelector('[data-theme-preview]');
    if (!hexInp) return;

    function syncPreview() {
      const v = (hexInp.value || '').trim();
      if (_isHex(v)) {
        preview.style.background = _expandHex(v);
      } else {
        preview.style.background = ''; // fall back to current css var
      }
      // Toggle active swatch.
      swatches.forEach((sw) => {
        const m = sw.getAttribute('data-theme-swatch').toLowerCase();
        sw.classList.toggle('active', _isHex(v) && _expandHex(v) === m);
      });
    }
    swatches.forEach((sw) => {
      sw.addEventListener('click', (e) => {
        e.preventDefault();
        const hex = sw.getAttribute('data-theme-swatch');
        hexInp.value = hex;
        if (colorInp) colorInp.value = hex;
        syncPreview();
      });
    });
    if (colorInp) {
      colorInp.addEventListener('input', () => {
        hexInp.value = colorInp.value;
        syncPreview();
      });
    }
    hexInp.addEventListener('input', () => {
      const v = hexInp.value.trim();
      if (_isHex(v) && colorInp) colorInp.value = _expandHex(v);
      syncPreview();
    });
    if (darkColorInp && darkHexInp) {
      darkColorInp.addEventListener('input', () => { darkHexInp.value = darkColorInp.value; });
      darkHexInp.addEventListener('input', () => {
        const v = darkHexInp.value.trim();
        if (_isHex(v)) darkColorInp.value = _expandHex(v);
      });
    }
    syncPreview();
  }

  function loadSettings() {
    const host = document.querySelector('[data-settings-host]');
    if (!host) return;
    host.innerHTML = '<div class="card mt-3"><div class="skeleton" style="height:120px;"></div></div>';
    App.api('/admin/settings').then((r) => {
      const s = r.settings || {};
      const cf = r.cloudflare || {};
      const cfBanner = cf.configured
        ? `<div class="cf-banner cf-banner--ok">
             <strong>Cloudflare auto-DNS active</strong> on
             <code>${(cf.configured_brands || []).map(App.escapeHtml).join('</code>, <code>') || '—'}</code>.
             New verified claims publish a record automatically.
           </div>`
        : `<div class="cf-banner cf-banner--warn">
             <strong>Cloudflare auto-DNS not configured.</strong>
             Add an API token + zone IDs in the <a href="#" data-jump-pane="integrations">Integrations</a> pane to enable instant DNS.
             Until then, approved claims are marked <code>manual</code>.
           </div>`;
      host.innerHTML = `
        <div class="card dash-card">
          <header class="dash-card__head">
            <div>
              <h2 class="dash-card__title">Platform settings</h2>
              <p class="dash-card__sub">Two independent gates control the claim flow: <strong>approval</strong> (does an admin have to bless every claim?) and <strong>documents</strong> (do owners have to upload proof?). Changes take effect immediately for new claims.</p>
            </div>
          </header>
          <div style="padding:18px 24px 22px;">
            ${cfBanner}

            <form data-settings-form class="mt-3">

              <h4 class="setting-group__title">Home theme color</h4>
              <p class="text-muted" style="margin:4px 0 12px;font-size:.92rem;">
                Pick the brand color that paints buttons, links and accents across every public page. Pick a preset swatch or punch in a custom hex — the dark-mode variant auto-derives from your light hex unless you override it.
              </p>
              ${themePickerBlock(s)}

              <h4 class="setting-group__title mt-4">Moderation</h4>
              <label class="toggle-row">
                <input type="checkbox" name="require_approval" ${s.require_approval ? 'checked' : ''} />
                <span><strong>Require admin approval (recommended)</strong><span class="text-muted"> — every new claim lands as <code>pending</code> and stays private until you approve it. Cloudflare DNS is only published on approve.</span></span>
              </label>
              <label class="toggle-row">
                <input type="checkbox" name="require_documents" ${s.require_documents ? 'checked' : ''} />
                <span><strong>Documents required at claim time</strong><span class="text-muted"> — when ON, the homepage claim wizard prompts the owner to upload at least one verification doc (EIIN certificate, board letter, NID, etc.) before submission. This is independent from approval — turn it OFF for instant onboarding without proof, or ON to collect docs even if approval is OFF.</span></span>
              </label>
              <label class="toggle-row">
                <input type="checkbox" name="instant_claim" ${s.instant_claim ? 'checked' : ''} />
                <span><strong>Show "Claim it now" CTA</strong><span class="text-muted"> — purely cosmetic copy on the homepage. Has no effect on the moderation flow.</span></span>
              </label>

              <h4 class="setting-group__title mt-4">Domain term &amp; renewal</h4>
              <div class="form-grid form-grid--2 mt-2">
                <div class="field">
                  <label class="label" for="set-term-days">Domain term (days)</label>
                  <input id="set-term-days" name="domain_term_days" type="number" min="1" max="36500"
                         value="${App.escapeHtml(String(s.domain_term_days || 365))}" />
                  <p class="hint">Used to compute the expiry date when a claim is verified, and the new expiry on every renewal. 365 = 1 year.</p>
                </div>
                <div class="field">
                  <label class="label" for="set-renew-price">Renewal price (BDT)</label>
                  <input id="set-renew-price" name="domain_renewal_price_bdt" type="number" min="0" max="1000000"
                         value="${App.escapeHtml(String(s.domain_renewal_price_bdt || 0))}" />
                  <p class="hint">0 = renewals are free + auto-extend on owner click. Anything &gt; 0 routes the request through the new <a href="#" data-jump-pane="renewals">Renewals queue</a> (owner sees the configured payment methods).</p>
                </div>
              </div>

              <h4 class="setting-group__title mt-4">Sign-up &amp; DNS</h4>
              <label class="toggle-row">
                <input type="checkbox" name="cloudflare_auto_dns" ${s.cloudflare_auto_dns ? 'checked' : ''} />
                <span><strong>Cloudflare auto-DNS</strong><span class="text-muted"> — automatically create / update the DNS record on Cloudflare when a claim is verified.</span></span>
              </label>
              <label class="toggle-row">
                <input type="checkbox" name="email_registration_enabled" ${s.email_registration_enabled ? 'checked' : ''} />
                <span><strong>Allow manual email sign-up</strong><span class="text-muted"> — when ON, visitors can create an account with email + password from the homepage. Turn OFF to force everyone through Google / Facebook / GitHub OAuth (existing email accounts can still sign in).</span></span>
              </label>

              <div class="text-right mt-3"><button class="btn btn--primary" type="submit">Save settings</button></div>
            </form>
          </div>
        </div>`;
      host.querySelector('[data-settings-form]').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const body = {
          require_approval:     fd.get('require_approval') ? true : false,
          require_documents:    fd.get('require_documents') ? true : false,
          instant_claim:        fd.get('instant_claim') ? true : false,
          cloudflare_auto_dns:  fd.get('cloudflare_auto_dns') ? true : false,
          email_registration_enabled: fd.get('email_registration_enabled') ? true : false,
          domain_term_days:     parseInt(fd.get('domain_term_days') || '365', 10),
          domain_renewal_price_bdt: parseInt(fd.get('domain_renewal_price_bdt') || '0', 10),
          // v4.5 — theme color (empty string = use platform default).
          theme_primary_hex:      String(fd.get('theme_primary_hex') || '').trim(),
          theme_primary_dark_hex: String(fd.get('theme_primary_dark_hex') || '').trim(),
        };
        try {
          const r = await App.api('/admin/settings', { method: 'POST', body });
          App.toast('Settings saved.', 'success');
          // v4.5 — apply the freshly saved brand color live, so the admin
          // sees the new theme without a hard reload.
          if (r && r.settings) {
            const newPrimary = r.settings.theme_primary_hex || '';
            const newDark    = r.settings.theme_primary_dark_hex || '';
            if (newPrimary && App.applyBrandTheme) {
              App.applyBrandTheme(newPrimary, newDark);
            }
          }
          loadSettings();
          loadOverview();
        } catch (e) { App.toast((e && e.detail) || 'Could not save', 'error'); }
      });
      // Re-wire any data-jump-pane links rendered inside the settings card.
      host.querySelectorAll('[data-jump-pane]').forEach((b) => {
        b.addEventListener('click', (e) => {
          e.preventDefault();
          activatePane(b.getAttribute('data-jump-pane'));
        });
      });
      // v4.5 — wire the theme color picker (swatches + custom hex inputs)
      // so swatch clicks update both the hidden inputs and the live preview.
      wireThemePicker(host);
    }).catch((e) => {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml((e && e.detail) || 'Load failed') + '</p>';
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Integrations pane (preserved from v3.1)                          */
  /* ---------------------------------------------------------------- */
  function loadIntegrations() {
    const host = document.querySelector('[data-integrations-host]');
    if (!host) return;
    host.innerHTML = '<div class="card mt-3"><div class="skeleton" style="height:160px;"></div></div>';
    App.api('/admin/integrations').then((r) => renderIntegrations(host, r)).catch((e) => {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml((e && e.detail) || 'Load failed') + '</p>';
    });
  }

  function renderIntegrations(host, r) {
    const v = r.values || {};
    const redirects = r.redirects || {};
    const cfStatus  = r.cloudflare_status || {};
    const val    = (k) => (v[k] && v[k].value) || '';
    const isSet  = (k) => !!(v[k] && v[k].is_set);
    const masked = (k) => !!(v[k] && v[k].masked);

    function fld(key, label, opts) {
      opts = opts || {};
      const t = opts.type || 'text';
      const ph = opts.placeholder || '';
      const help = opts.help || '';
      const present = isSet(key);
      const id = 'int-' + key.replace(/\./g, '-');
      const statusPill = present
        ? '<span class="badge badge--verified" style="margin-left:6px;">saved</span>'
        : '<span class="badge badge--muted" style="margin-left:6px;">not set</span>';
      let inputHtml = '';
      if (t === 'textarea') {
        inputHtml = `<textarea id="${id}" name="${App.escapeHtml(key)}" rows="2" placeholder="${App.escapeHtml(ph)}">${App.escapeHtml(val(key))}</textarea>`;
      } else {
        const isSecret = masked(key);
        const inputType = isSecret ? 'password' : t;
        const inputPh = isSecret ? (present ? val(key) : (ph || 'paste secret here')) : ph;
        const inputVal = isSecret ? '' : val(key);
        inputHtml = `<input id="${id}" name="${App.escapeHtml(key)}" type="${inputType}"
          placeholder="${App.escapeHtml(inputPh)}"
          value="${App.escapeHtml(inputVal)}"
          autocomplete="off" spellcheck="false" />`;
      }
      return `
        <div class="field${opts.wide ? ' field--wide' : ''}">
          <label class="label" for="${id}">${App.escapeHtml(label)} ${statusPill}</label>
          ${inputHtml}
          ${help ? `<p class="hint">${App.escapeHtml(help)}</p>` : ''}
        </div>`;
    }

    const cfBrandsHtml = Object.keys(cfStatus).map((b) => {
      const ok = !!cfStatus[b];
      return `<span class="badge ${ok ? 'badge--verified' : 'badge--muted'}" style="margin-right:6px;">
        <code>${App.escapeHtml(b)}</code> ${ok ? 'configured' : 'not configured'}
      </span>`;
    }).join('');

    host.innerHTML = `
      <div class="card dash-card">
        <header class="dash-card__head">
          <div>
            <h2 class="dash-card__title">Integrations</h2>
            <p class="dash-card__sub">Edit every secret, API key and external service from here. Saved values overlay <code>api/config.php</code> on the next request.</p>
          </div>
          <span class="badge">v3.1</span>
        </header>

        <form data-integrations-form style="padding:18px 24px 22px;">

          <details class="card" open style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Branding &amp; site URL</summary>
            <div class="form-grid mt-3">
              ${fld('site.url', 'Public site URL', { placeholder: 'https://institution.bd', help: 'Used to build OAuth redirect URIs and absolute links. No trailing slash.' })}
              ${fld('brand.name', 'Brand name', { placeholder: 'institution.bd' })}
              ${fld('brand.tagline', 'Brand tagline', { placeholder: 'Free verified subdomains for Bangladeshi institutions', wide: true })}
              ${fld('nameservers', 'Nameservers (comma- or space-separated)', { placeholder: 'amir.ns.cloudflare.com, tia.ns.cloudflare.com', help: 'Shown on every owner\'s "Nameservers" tab. Leave blank to use the Cloudflare placeholder.', wide: true })}
            </div>
          </details>

          <details class="card" style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Social sign-in (OAuth)</summary>
            <p class="text-muted mt-2" style="margin-top:8px;">Whitelist this redirect URI in each provider's developer console:</p>
            ${oauthBlock('google',   'Google',   redirects.google,   v)}
            ${oauthBlock('facebook', 'Facebook', redirects.facebook, v)}
            ${oauthBlock('github',   'GitHub',   redirects.github,   v)}
          </details>

          <details class="card" style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Cloudflare auto-DNS</summary>
            <div class="mt-2" style="margin:10px 0 14px;">${cfBrandsHtml}</div>
            <div class="form-grid">
              ${fld('cloudflare.api_token', 'API token', { placeholder: 'cf-token', help: 'Token must have Zone:DNS:Edit permission for the zones below.', wide: true })}
              ${fld('cloudflare.zones.institution_bd', 'Zone ID — institution.bd', { placeholder: 'zone id from Cloudflare dashboard' })}
              ${fld('cloudflare.zones.smartschool_bd', 'Zone ID — smartschool.bd', { placeholder: 'zone id from Cloudflare dashboard' })}
              ${fld('cloudflare.target_type', 'Record type', { placeholder: 'A', help: 'A, AAAA or CNAME' })}
              ${fld('cloudflare.target_value', 'Record value', { placeholder: 'e.g. 203.0.113.10  (or hostname for CNAME)' })}
            </div>
            <label class="toggle-row mt-3">
              <input type="checkbox" name="cloudflare.proxied" ${['1','true','yes','on'].includes(String(val('cloudflare.proxied')).toLowerCase()) ? 'checked' : ''} />
              <span><strong>Proxy through Cloudflare (orange cloud)</strong><span class="text-muted"> — gives every tenant Cloudflare's automatic SSL + cache.</span></span>
            </label>
            <div class="flex mt-3" style="gap:10px;flex-wrap:wrap;">
              <button type="button" class="btn" data-test-cf>Test Cloudflare token</button>
              <span data-test-cf-result class="text-muted"></span>
            </div>
          </details>

          <details class="card" style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">WhatsApp support &amp; community</summary>
            <div class="form-grid mt-3">
              ${fld('whatsapp.support_number', 'Support number', { placeholder: '8801712345678', help: 'International form WITHOUT +, dashes or spaces.' })}
              ${fld('whatsapp.support_prefilled_message', 'Prefilled message', { placeholder: 'Hi! I need help with institution.bd' })}
              ${fld('whatsapp.community_url', 'Community invite URL', { placeholder: 'https://chat.whatsapp.com/…', wide: true })}
              ${fld('whatsapp.community_title', 'Community card title', { placeholder: 'Join our WhatsApp community' })}
              ${fld('whatsapp.community_subtitle', 'Community card subtitle', { placeholder: 'Get announcements, support and meet other admins.' })}
            </div>
          </details>

          <details class="card" style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Outbound email (password reset)</summary>
            <p class="text-muted mt-2" style="margin-top:8px;">Pick "SMTP" to use Gmail, SendGrid, Mailgun, SES or your cPanel mailbox. Pick "PHP mail()" to use the server's local sendmail (zero config, weaker deliverability).</p>
            <div class="form-grid mt-3">
              <div class="field">
                <label class="label">Transport</label>
                <select name="mail.transport">
                  <option value="mail" ${val('mail.transport') === 'mail' || val('mail.transport') === '' ? 'selected' : ''}>PHP mail() — local sendmail</option>
                  <option value="smtp" ${val('mail.transport') === 'smtp' ? 'selected' : ''}>SMTP (recommended)</option>
                </select>
              </div>
              ${fld('mail.from_email', 'From address',  { placeholder: 'no-reply@institution.bd' })}
              ${fld('mail.from_name',  'From name',     { placeholder: 'institution.bd' })}
              ${fld('mail.smtp_host',  'SMTP host',     { placeholder: 'smtp.gmail.com' })}
              ${fld('mail.smtp_port',  'SMTP port',     { placeholder: '587' })}
              <div class="field">
                <label class="label">Encryption</label>
                <select name="mail.smtp_secure">
                  <option value="tls" ${val('mail.smtp_secure') === 'tls' || val('mail.smtp_secure') === '' ? 'selected' : ''}>STARTTLS (587)</option>
                  <option value="ssl" ${val('mail.smtp_secure') === 'ssl' ? 'selected' : ''}>Implicit TLS (465)</option>
                  <option value=""    ${val('mail.smtp_secure') === 'none' ? 'selected' : ''}>None (port 25, not recommended)</option>
                </select>
              </div>
              ${fld('mail.smtp_user',  'SMTP username', { placeholder: 'apikey or full email' })}
              ${fld('mail.smtp_pass',  'SMTP password / API key', { placeholder: 'paste secret' })}
            </div>
          </details>

          <details class="card" style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Auth &amp; security</summary>
            <div class="form-grid mt-3">
              ${fld('jwt.secret', 'JWT signing secret', { placeholder: '64-char hex string', help: 'Rotating this signs everyone out (including you).', wide: true })}
            </div>
            <div class="flex mt-3" style="gap:10px;flex-wrap:wrap;">
              <button type="button" class="btn btn--danger" data-rotate-jwt>Rotate JWT secret</button>
              <span class="text-muted">Generates a fresh 64-byte hex secret server-side.</span>
            </div>
          </details>

          <div class="text-right mt-4">
            <button class="btn btn--primary" type="submit">Save all integrations</button>
          </div>
        </form>
      </div>`;

    wireIntegrationsForm(host);
  }

  function oauthBlock(id, label, redirectUri, v) {
    const present = !!(v['oauth.' + id + '.client_id'] && v['oauth.' + id + '.client_id'].is_set);
    return `
      <div class="card" style="background:var(--c-bg);box-shadow:none;border-style:dashed;margin:14px 0;padding:16px 18px;">
        <div class="flex-between" style="flex-wrap:wrap;gap:10px;">
          <h4 style="margin:0;">${App.escapeHtml(label)}
            <span class="badge ${present ? 'badge--verified' : 'badge--muted'}" style="margin-left:6px;">
              ${present ? 'enabled' : 'disabled'}
            </span>
          </h4>
          <div class="flex" style="gap:6px;flex-wrap:wrap;">
            <code style="font-size:.8rem;background:rgba(15,23,42,.06);padding:2px 8px;border-radius:6px;">${App.escapeHtml(redirectUri || '')}</code>
            <button type="button" class="btn btn--sm" data-copy="${App.escapeHtml(redirectUri || '')}">Copy URI</button>
          </div>
        </div>
        <div class="form-grid mt-3">
          <div class="field">
            <label class="label">Client ID</label>
            <input type="text" name="oauth.${id}.client_id" value="${App.escapeHtml((v['oauth.' + id + '.client_id'] && v['oauth.' + id + '.client_id'].value) || '')}" placeholder="paste client id" autocomplete="off" spellcheck="false" />
          </div>
          <div class="field">
            <label class="label">Client secret</label>
            <input type="password" name="oauth.${id}.client_secret" value=""
                   placeholder="${App.escapeHtml((v['oauth.' + id + '.client_secret'] && v['oauth.' + id + '.client_secret'].is_set) ? (v['oauth.' + id + '.client_secret'].value || '••••••••') : 'paste client secret')}"
                   autocomplete="off" spellcheck="false" />
          </div>
        </div>
      </div>`;
  }

  function wireIntegrationsForm(host) {
    host.querySelectorAll('[data-copy]').forEach((b) => {
      b.addEventListener('click', async () => {
        const text = b.getAttribute('data-copy') || '';
        try {
          await navigator.clipboard.writeText(text);
          App.toast('Copied to clipboard', 'success');
        } catch (e) {
          const ta = document.createElement('textarea');
          ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); App.toast('Copied', 'success'); }
          catch { App.toast('Copy failed', 'error'); }
          ta.remove();
        }
      });
    });

    const testBtn = host.querySelector('[data-test-cf]');
    const testOut = host.querySelector('[data-test-cf-result]');
    if (testBtn) {
      testBtn.addEventListener('click', async () => {
        testBtn.disabled = true;
        const orig = testBtn.textContent;
        testBtn.textContent = 'Testing…';
        testOut.textContent = '';
        try {
          const r = await App.api('/admin/integrations/test', { method: 'POST', body: { target: 'cloudflare' } });
          if (r.ok) {
            const zones = (r.zones || []).map((z) => z.name).join(', ');
            const via   = r.method ? ' <span class="text-muted">(via <code>' + App.escapeHtml(r.method) + '</code>)</span>' : '';
            testOut.innerHTML = '<span class="text-success">✓ ' + App.escapeHtml(r.message || 'OK') + '</span>' + via
              + (zones ? ' — visible zones: <code>' + App.escapeHtml(zones) + '</code>' : '');
            App.toast('Cloudflare token verified', 'success');
          } else {
            const hint = r.hint ? '<br><small class="text-muted">' + App.escapeHtml(r.hint) + '</small>' : '';
            testOut.innerHTML = '<span class="text-danger">✗ ' + App.escapeHtml(r.message || 'Token rejected') + '</span>' + hint;
            App.toast(r.message || 'Cloudflare rejected the token', 'error');
          }
        } catch (e) {
          testOut.innerHTML = '<span class="text-danger">✗ ' + App.escapeHtml((e && e.detail) || 'Test failed') + '</span>';
        } finally {
          testBtn.disabled = false;
          testBtn.textContent = orig;
        }
      });
    }

    const rotateBtn = host.querySelector('[data-rotate-jwt]');
    if (rotateBtn) {
      rotateBtn.addEventListener('click', async () => {
        if (!confirm('Rotate the JWT secret? This signs out EVERY user — including you. Continue?')) return;
        try {
          const r = await App.api('/admin/integrations/rotate-jwt', { method: 'POST' });
          App.toast(r.message || 'JWT rotated', 'success');
          App.clearSession();
          setTimeout(() => { location.href = '/'; }, 800);
        } catch (e) {
          App.toast((e && e.detail) || 'Rotate failed', 'error');
        }
      });
    }

    const form = host.querySelector('[data-integrations-form]');
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(form);
      const values = {};
      fd.forEach((val, key) => { values[key] = val; });
      form.querySelectorAll('input[type=checkbox]').forEach((c) => {
        values[c.name] = c.checked ? '1' : '0';
      });
      const submit = form.querySelector('button[type=submit]');
      submit.disabled = true;
      const orig = submit.textContent;
      submit.textContent = 'Saving…';
      try {
        await App.api('/admin/integrations', { method: 'POST', body: { values } });
        App.toast('Integrations saved', 'success');
        loadIntegrations();
      } catch (e) {
        App.toast((e && e.detail) || 'Save failed', 'error');
      } finally {
        submit.disabled = false;
        submit.textContent = orig;
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /*  v3.2 — Admin "Support payments" CRUD                             */
  /* ---------------------------------------------------------------- */
  const ADMIN_PAY_METHODS = [
    { id: 'bkash',  label: 'bKash' },
    { id: 'nagad',  label: 'Nagad' },
    { id: 'rocket', label: 'Rocket' },
    { id: 'upay',   label: 'Upay' },
    { id: 'tap',    label: 'Tap' },
    { id: 'bank',   label: 'Bank transfer' },
    { id: 'card',   label: 'Card' },
    { id: 'paypal', label: 'PayPal' },
    { id: 'crypto', label: 'Crypto' },
    { id: 'other',  label: 'Other' },
  ];

  function loadAdminPayments() {
    const host = document.querySelector('[data-payments-admin-host]');
    if (!host) return;
    host.innerHTML = '<div class="card mt-3"><div class="skeleton" style="height:120px;"></div></div>';
    App.api('/admin/support/payments').then((r) => {
      renderAdminPayments(host, r.items || []);
    }).catch((e) => {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml((e && e.detail) || 'Load failed') + '</p>';
    });
  }

  function renderAdminPayments(host, items) {
    host.innerHTML = `
      <div class="card dash-card">
        <header class="dash-card__head">
          <div>
            <h2 class="dash-card__title">Support payments</h2>
            <p class="dash-card__sub">Manage the payment methods shown on the dashboard's "Support Developer" pane. Hidden rows are saved but not shown to owners.</p>
          </div>
          <button class="btn btn--primary btn--sm" type="button" data-pay-add>+ Add method</button>
        </header>
        <div class="dash-table-wrap">
          <table class="dash-table">
            <thead><tr>
              <th>Method</th><th>Label</th><th>Number / handle</th><th>Visible</th><th>Order</th><th class="text-right">Action</th>
            </tr></thead>
            <tbody data-pay-body>
              ${items.length ? items.map(adminPayRow).join('') : `
                <tr><td colspan="6"><div class="dash-empty"><p>No payment methods yet — click "Add method" above.</p></div></td></tr>`}
            </tbody>
          </table>
        </div>
        <div data-pay-form-host style="padding:0 24px 22px;"></div>
      </div>`;
    host.querySelector('[data-pay-add]').addEventListener('click', () => openAdminPayForm(null));
    host.querySelectorAll('[data-pay-edit]').forEach((b) => {
      b.addEventListener('click', () => {
        const id = parseInt(b.getAttribute('data-pay-edit'), 10);
        const it = items.find((x) => x.id === id);
        if (it) openAdminPayForm(it);
      });
    });
    host.querySelectorAll('[data-pay-toggle]').forEach((b) => {
      b.addEventListener('click', async () => {
        const id = parseInt(b.getAttribute('data-pay-toggle'), 10);
        const it = items.find((x) => x.id === id);
        if (!it) return;
        try {
          await App.api('/admin/support/payments/' + id, {
            method: 'PATCH', body: { visible: !it.visible },
          });
          App.toast('Visibility updated', 'success');
          loadAdminPayments();
        } catch (e) { App.toast((e && e.detail) || 'Failed', 'error'); }
      });
    });
    host.querySelectorAll('[data-pay-del]').forEach((b) => {
      b.addEventListener('click', async () => {
        const id = parseInt(b.getAttribute('data-pay-del'), 10);
        if (!confirm('Permanently delete this payment method?')) return;
        try {
          await App.api('/admin/support/payments/' + id, { method: 'DELETE' });
          App.toast('Deleted', 'success');
          loadAdminPayments();
        } catch (e) { App.toast((e && e.detail) || 'Delete failed', 'error'); }
      });
    });
  }

  function adminPayRow(p) {
    const visBadge = p.visible
      ? '<span class="badge badge--verified">Visible</span>'
      : '<span class="badge badge--muted">Hidden</span>';
    return `
      <tr>
        <td><code>${App.escapeHtml(p.method)}</code></td>
        <td><strong>${App.escapeHtml(p.label)}</strong>${p.note ? '<div class="text-muted" style="font-size:.85rem;">' + App.escapeHtml(p.note) + '</div>' : ''}</td>
        <td><code>${App.escapeHtml(p.number || '—')}</code></td>
        <td>${visBadge}</td>
        <td>${p.sort_order}</td>
        <td class="text-right">
          <button class="btn btn--sm" type="button" data-pay-edit="${p.id}">Edit</button>
          <button class="btn btn--sm" type="button" data-pay-toggle="${p.id}">${p.visible ? 'Hide' : 'Show'}</button>
          <button class="btn btn--ghost btn--sm btn--danger-text" type="button" data-pay-del="${p.id}">Delete</button>
        </td>
      </tr>`;
  }

  function openAdminPayForm(it) {
    const host = document.querySelector('[data-pay-form-host]');
    if (!host) return;
    const isEdit = !!it;
    const safe = (v) => App.escapeHtml(v == null ? '' : String(v));
    const sel = (m) => ADMIN_PAY_METHODS.map((p) =>
      `<option value="${p.id}" ${m === p.id ? 'selected' : ''}>${App.escapeHtml(p.label)}</option>`
    ).join('');
    host.innerHTML = `
      <article class="card mt-3" style="background:var(--c-bg);">
        <header class="flex-between" style="flex-wrap:wrap;gap:10px;">
          <h4 style="margin:0;">${isEdit ? 'Edit payment method' : 'Add payment method'}</h4>
          <button class="btn btn--ghost btn--sm" type="button" data-pay-cancel>Cancel</button>
        </header>
        <form data-pay-save="${isEdit ? it.id : ''}" class="form-grid mt-2">
          <div class="field"><label class="label">Method</label>
            <select name="method" required>${sel(it ? it.method : 'bkash')}</select>
          </div>
          <div class="field"><label class="label">Label *</label>
            <input name="label" required maxlength="120" value="${safe(it ? it.label : '')}" placeholder="e.g. bKash (Personal)" />
          </div>
          <div class="field"><label class="label">Number / handle</label>
            <input name="number" maxlength="120" value="${safe(it ? it.number : '')}" placeholder="01XXXXXXXXX" />
          </div>
          <div class="field"><label class="label">Sort order</label>
            <input name="sort_order" type="number" value="${safe(it ? it.sort_order : 100)}" />
          </div>
          <div class="field field--wide"><label class="label">Note (shown below the number)</label>
            <textarea name="note" rows="2" maxlength="500">${safe(it ? it.note : '')}</textarea>
          </div>
          <div class="field field--wide"><label class="label">QR image URL (optional)</label>
            <input name="qr_url" type="url" maxlength="512" value="${safe(it ? it.qr_url : '')}" placeholder="https://… or /uploads/…" />
          </div>
          <div class="field field--wide">
            <label class="toggle-row">
              <input type="checkbox" name="visible" ${(!it || it.visible) ? 'checked' : ''} />
              <span><strong>Visible to owners</strong><span class="text-muted"> — shown on the dashboard "Support Developer" pane.</span></span>
            </label>
          </div>
          <div class="field field--wide text-right">
            <button class="btn btn--primary" type="submit">${isEdit ? 'Save changes' : 'Create'}</button>
          </div>
        </form>
      </article>`;
    host.querySelector('[data-pay-cancel]').addEventListener('click', () => { host.innerHTML = ''; });
    const form = host.querySelector('[data-pay-save]');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const payload = {
        method:     (fd.get('method') || 'other').toString(),
        label:      (fd.get('label')  || '').toString().trim(),
        number:     (fd.get('number') || '').toString().trim(),
        note:       (fd.get('note')   || '').toString().trim(),
        qr_url:     (fd.get('qr_url') || '').toString().trim(),
        sort_order: parseInt(fd.get('sort_order') || '100', 10),
        visible:    !!fd.get('visible'),
      };
      try {
        if (isEdit) {
          await App.api('/admin/support/payments/' + it.id, { method: 'PATCH', body: payload });
          App.toast('Saved', 'success');
        } else {
          await App.api('/admin/support/payments', { method: 'POST', body: payload });
          App.toast('Created', 'success');
        }
        host.innerHTML = '';
        loadAdminPayments();
      } catch (err) {
        if (err && err.errors) {
          const first = Object.keys(err.errors)[0];
          App.toast(err.errors[first], 'error');
        } else {
          App.toast((err && err.detail) || 'Save failed', 'error');
        }
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /*  v4.1 — Renewals pane                                              */
  /* ---------------------------------------------------------------- */
  let _renewalsFilter = { status: 'pending', q: '' };
  function loadRenewals() {
    const host = document.querySelector('[data-renewals-host]');
    if (!host) return;
    host.innerHTML = `
      <div class="card dash-card">
        <header class="dash-card__head">
          <div>
            <h2 class="dash-card__title">Domain renewals</h2>
            <p class="dash-card__sub">Owners pay the configured renewal fee, then submit a request from their dashboard. Approve once you've confirmed the payment, and the expiry extends by the configured term.</p>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" data-renewals-refresh>Refresh</button>
        </header>
        <form class="dash-card__filters" data-renewals-form>
          <label class="dash-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search by slug, name or owner email…" data-renewals-q autocomplete="off" />
          </label>
          <label class="dash-filter">
            <select data-renewals-status>
              <option value="pending"  ${_renewalsFilter.status === 'pending'  ? 'selected' : ''}>Pending only</option>
              <option value="approved" ${_renewalsFilter.status === 'approved' ? 'selected' : ''}>Approved</option>
              <option value="rejected" ${_renewalsFilter.status === 'rejected' ? 'selected' : ''}>Rejected</option>
              <option value="any"      ${_renewalsFilter.status === 'any'      ? 'selected' : ''}>All</option>
            </select>
          </label>
        </form>
        <div data-renewals-body>
          <div class="dash-empty"><div class="skeleton" style="height:80px;width:100%;"></div></div>
        </div>
      </div>`;

    host.querySelector('[data-renewals-refresh]').addEventListener('click', loadRenewalsRows);
    host.querySelector('[data-renewals-form]').addEventListener('submit', (e) => e.preventDefault());
    const qEl = host.querySelector('[data-renewals-q]');
    const sEl = host.querySelector('[data-renewals-status]');
    const debounced = App.debounce(() => {
      _renewalsFilter.q = (qEl.value || '').trim();
      loadRenewalsRows();
    }, 250);
    qEl.addEventListener('input', debounced);
    sEl.addEventListener('change', () => {
      _renewalsFilter.status = sEl.value;
      loadRenewalsRows();
    });
    loadRenewalsRows();
  }

  function loadRenewalsRows() {
    const host = document.querySelector('[data-renewals-body]');
    if (!host) return;
    host.innerHTML = '<div class="dash-empty"><div class="skeleton" style="height:80px;width:100%;"></div></div>';
    const params = { status: _renewalsFilter.status, q: _renewalsFilter.q, limit: 100 };
    App.api('/admin/renewals' + App.qs(params)).then((r) => {
      const items = r.items || [];
      // Update sidebar pending-count badge.
      const badge = document.querySelector('[data-renewals-pending]');
      if (badge && _renewalsFilter.status === 'pending') {
        badge.textContent = String(items.length);
        badge.hidden = items.length === 0;
      }
      if (!items.length) {
        host.innerHTML = `<div class="dash-empty"><p>No renewal requests${_renewalsFilter.status === 'pending' ? ' pending' : ''}.</p></div>`;
        return;
      }
      host.innerHTML = `
        <div class="dash-table-wrap">
          <table class="dash-table">
            <thead><tr>
              <th>Domain</th><th>Owner</th><th>Price</th><th>Term</th>
              <th>Status</th><th>Submitted</th><th class="text-right">Action</th>
            </tr></thead>
            <tbody>${items.map(renderRenewalRow).join('')}</tbody>
          </table>
        </div>`;
      host.querySelectorAll('[data-renew-decide]').forEach((b) => {
        b.addEventListener('click', () => decideRenewal(b));
      });
    }).catch((e) => {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml((e && e.detail) || 'Load failed') + '</p>';
    });
  }
  function renderRenewalRow(r) {
    const inst = r.institution || {};
    const ow = r.owner || {};
    const statusBadge =
      r.status === 'pending'  ? '<span class="badge badge--warning">Pending</span>' :
      r.status === 'approved' ? '<span class="badge badge--verified">Approved</span>' :
      r.status === 'rejected' ? '<span class="badge badge--danger">Rejected</span>' :
                                '<span class="badge">' + App.escapeHtml(r.status) + '</span>';
    const message = r.owner_message
      ? '<div class="text-muted" style="font-size:.82rem;margin-top:4px;">"' + App.escapeHtml(r.owner_message) + '"</div>' : '';
    return `
      <tr data-renew-row="${r.id}">
        <td>
          <div class="dash-domain">
            <span class="dash-domain__logo">${App.escapeHtml(App.initialsOf(inst.name_en || inst.slug || ''))}</span>
            <div>
              <div class="dash-domain__name">${App.escapeHtml(inst.subdomain || (inst.slug + '.' + inst.brand))}</div>
              <div class="dash-domain__meta">${App.escapeHtml(inst.name_en || inst.slug || '')}</div>
              ${message}
            </div>
          </div>
        </td>
        <td>${App.escapeHtml(ow.name || ow.email || '—')}</td>
        <td>${r.price_bdt > 0 ? '৳ ' + r.price_bdt : '<span class="text-muted">Free</span>'}</td>
        <td>${r.term_days} d</td>
        <td>${statusBadge}</td>
        <td>${App.escapeHtml(App.fmtDate(r.created_at))}</td>
        <td class="text-right">
          ${r.status === 'pending' ? `
            <button class="btn btn--sm btn--primary" data-renew-decide="approve:${r.id}" type="button">Approve</button>
            <button class="btn btn--sm btn--danger" data-renew-decide="reject:${r.id}" type="button">Reject</button>
          ` : (r.new_expires_at ? '<span class="text-muted">→ ' + App.escapeHtml(App.fmtDate(r.new_expires_at)) + '</span>' : '—')}
        </td>
      </tr>`;
  }
  async function decideRenewal(btn) {
    const [decision, idStr] = (btn.getAttribute('data-renew-decide') || '').split(':');
    const id = parseInt(idStr, 10);
    if (!decision || !id) return;
    let note = '';
    if (decision === 'reject') {
      note = prompt('Reject this renewal — note for the owner (optional):', '') || '';
      if (note === null) return;
    } else {
      if (!confirm('Approve this renewal? Expiry will extend immediately.')) return;
    }
    try {
      await App.api('/admin/renewals/' + id + '/decide', { method: 'POST', body: { decision, note } });
      App.toast('Renewal ' + (decision === 'approve' ? 'approved' : 'rejected'), 'success');
      loadRenewalsRows();
      loadOverview();
    } catch (e) {
      App.toast((e && e.detail) || 'Action failed', 'error');
    }
  }

  // Refresh the sidebar pending badge on first load + every overview refresh.
  function refreshRenewalsBadge() {
    App.api('/admin/renewals?status=pending&limit=1').then((r) => {
      const badge = document.querySelector('[data-renewals-pending]');
      if (!badge) return;
      const total = (r && r.total) || (r && r.items ? r.items.length : 0);
      badge.textContent = String(total);
      badge.hidden = total === 0;
    }).catch(() => { /* badge stays hidden */ });
  }
  // Hook into the overview refresh — re-run after every loadOverview() call.
  const _origLoadOverview = loadOverview;
  loadOverview = function () {
    _origLoadOverview();
    refreshRenewalsBadge();
  };
})();
