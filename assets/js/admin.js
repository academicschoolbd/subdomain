/* ===========================================================================
   institution.bd — v5pro Admin Console
   Modern ES6+, Bootstrap 5.3 integration.
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  const needAuth = document.querySelector('[data-needs-auth]');
  const notAdmin = document.querySelector('[data-not-admin]');
  const adminRoot = document.querySelector('[data-admin-root]');
  const detailBody = document.querySelector('[data-detail-body]');
  let detailModalInstance = null;

  let _stats = null;
  let _claims = [];
  let _users = [];
  const _filter = { queue: { q: '', status: 'pending' }, users: { q: '', role: 'any' } };
  const _selectedIds = new Set();

  // ─── Boot ────────────────────────────────────────────────────────────────

  if (!App.isAuthed()) { if (needAuth) needAuth.hidden = false; return; }

  App.api('/auth/me').then(r => {
    App.setSession(null, r.user);
    if (!r.user?.is_admin) { if (notAdmin) notAdmin.hidden = false; return; }
    if (adminRoot) adminRoot.hidden = false;
    wireDetailModal();
    wirePaneSwitcher();
    wireQueue();
    wireUsers();
    wireReserved();
    wireAudit();
    wireExports();
    loadOverview();
  }).catch(e => {
    if (e?.status === 401) { App.clearSession(); if (needAuth) needAuth.hidden = false; }
    else App.toast(e?.detail || 'Could not load admin console', 'error');
  });


  // ─── Pane Switcher ─────────────────────────────────────────────────────────

  function activatePane(name) {
    document.querySelectorAll('[data-pane-btn]').forEach(b => b.classList.toggle('active', b.getAttribute('data-pane-btn') === name));
    document.querySelectorAll('[data-pane]').forEach(p => { p.hidden = p.getAttribute('data-pane') !== name; });
    if (name === 'queue') loadQueue();
    if (name === 'users') loadUsers();
    if (name === 'reserved') loadReserved();
    if (name === 'audit') loadAudit();
    if (name === 'settings') loadSettings();
    if (name === 'integrations') loadIntegrations();
    if (name === 'payments') loadAdminPayments();
    if (name === 'sponsors') loadAdminSponsors();
    if (name === 'renewals') loadRenewals();
  }

  function wirePaneSwitcher() {
    document.querySelectorAll('[data-pane-btn]').forEach(b => {
      b.addEventListener('click', () => activatePane(b.getAttribute('data-pane-btn')));
    });
    document.querySelectorAll('[data-jump-pane]').forEach(b => {
      b.addEventListener('click', e => { e.preventDefault(); activatePane(b.getAttribute('data-jump-pane')); });
    });
    document.querySelectorAll('[data-signout]').forEach(b => {
      b.addEventListener('click', e => { e.preventDefault(); App.clearSession(); location.href = '/'; });
    });
  }

  // ─── Detail Modal ──────────────────────────────────────────────────────────

  function wireDetailModal() {
    const el = document.getElementById('detailModal');
    if (el) detailModalInstance = new bootstrap.Modal(el);
  }

  function statusBadge(s) {
    const map = { verified:'badge-verified', pending:'badge-pending', needs_info:'badge-warning', rejected:'badge-danger', seeded:'badge-muted', suspended:'badge-danger' };
    return `<span class="badge rounded-pill ${map[s]||''}">${App.escapeHtml(s)}</span>`;
  }

  // ─── Overview ──────────────────────────────────────────────────────────────

  function loadOverview() {
    App.api('/admin/stats').then(r => {
      _stats = r;
      renderKpiTiles(r);
      renderActivity(r.recent_activity || []);
      const navBadge = document.querySelector('[data-nav-pending]');
      if (navBadge) { const p = r.counts?.pending || 0; navBadge.textContent = p; navBadge.hidden = p === 0; }

      App.api('/admin/settings').then(s => {
        const reqApproval = !!(s?.settings?.require_approval);
        const banner = document.querySelector('[data-admin-banner]');
        const t = document.querySelector('[data-admin-banner-title]');
        const sub = document.querySelector('[data-admin-banner-sub]');
        if (banner) banner.classList.toggle('is-warning', !reqApproval);
        if (t) t.textContent = reqApproval ? 'Moderation is ON — every claim waits for your approval.' : 'Moderation is OFF — claims auto-verify.';
        if (sub) sub.innerHTML = reqApproval
          ? 'New subdomains land as <code>pending</code> until you approve them.'
          : 'Every claim goes <code>verified</code> immediately. Turn moderation ON in <strong>Settings</strong> if abuse appears.';
      }).catch(() => {});
    }).catch(e => App.toast(e?.detail || 'Failed to load stats', 'error'));
  }


  function renderKpiTiles(r) {
    const host = document.querySelector('[data-kpi-host]');
    if (!host) return;
    const c = r.counts || {};
    host.innerHTML = `
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-jump-pane="queue">
          <div class="tile-icon" style="background:rgba(37,99,235,.1);color:#2563eb;"><i class="bi bi-hourglass-split"></i></div>
          <div class="tile-num">${c.pending || 0}</div>
          <div class="tile-label">Pending Review</div>
          <div class="tile-hint">${c.pending ? 'Tap to open queue' : 'All caught up'}</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-jump-pane="queue" data-jump-status="verified">
          <div class="tile-icon" style="background:rgba(22,163,74,.1);color:#15803d;"><i class="bi bi-check-circle-fill"></i></div>
          <div class="tile-num">${c.verified || 0}</div>
          <div class="tile-label">Verified Live</div>
          <div class="tile-hint">Across all brands</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-jump-pane="queue" data-jump-status="needs_info">
          <div class="tile-icon" style="background:rgba(220,38,38,.1);color:#dc2626;"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div class="tile-num">${(c.needs_info||0) + (c.rejected||0) + (c.suspended||0)}</div>
          <div class="tile-label">Needs Attention</div>
          <div class="tile-hint">Needs-info / rejected / suspended</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-jump-pane="users">
          <div class="tile-icon"><i class="bi bi-people-fill"></i></div>
          <div class="tile-num">${r.users || 0}</div>
          <div class="tile-label">Registered Users</div>
          <div class="tile-hint">${r.admins || 0} admin(s)</div>
        </div>
      </div>`;

    host.querySelectorAll('[data-jump-pane]').forEach(b => {
      b.addEventListener('click', () => {
        const status = b.getAttribute('data-jump-status');
        if (status) { _filter.queue.status = status; const sel = document.querySelector('[data-status]'); if (sel) sel.value = status; }
        activatePane(b.getAttribute('data-jump-pane'));
      });
    });
  }

  // ─── Activity Feed ─────────────────────────────────────────────────────────

  function renderActivity(items) {
    const host = document.querySelector('[data-activity-host]');
    if (!host) return;
    if (!items.length) { host.innerHTML = '<p class="text-muted small">No recent activity.</p>'; return; }

    const verb = action => {
      const map = { 'admin.decide.approve':'approved', 'admin.decide.reject':'rejected', 'admin.decide.needs_info':'asked info on', 'claim.create':'submitted', 'claim.withdraw':'withdrew' };
      return map[action] || action;
    };

    host.innerHTML = items.slice(0, 10).map(a => `
      <div class="activity-item-v5">
        <span class="activity-dot"></span>
        <div class="flex-grow-1">
          <p class="mb-0 small"><strong>${App.escapeHtml(a.actor_name || a.actor_email || 'System')}</strong> ${App.escapeHtml(verb(a.action))}
            ${a.institution_id ? `<a href="#" data-detail="${a.institution_id}">claim #${a.institution_id}</a>` : ''}</p>
          <small class="text-muted">${App.escapeHtml(App.fmtDate(a.created_at))} &middot; <code class="small">${App.escapeHtml(a.action)}</code></small>
        </div>
      </div>`).join('');

    host.querySelectorAll('[data-detail]').forEach(b => {
      b.addEventListener('click', e => { e.preventDefault(); openDetail(parseInt(b.getAttribute('data-detail'), 10)); });
    });
  }


  // ─── Queue ─────────────────────────────────────────────────────────────────

  function wireQueue() {
    const form = document.querySelector('[data-queue-form]');
    const qEl = form?.querySelector('[data-q]');
    const sEl = form?.querySelector('[data-status]');
    if (form) form.addEventListener('submit', e => e.preventDefault());
    if (qEl) { const d = App.debounce(() => { _filter.queue.q = (qEl.value||'').trim(); loadQueue(); }, 250); qEl.addEventListener('input', d); }
    if (sEl) sEl.addEventListener('change', () => { _filter.queue.status = sEl.value; loadQueue(); });

    const tog = document.querySelector('[data-bulk-toggle]');
    if (tog) tog.addEventListener('change', () => {
      document.querySelectorAll('[data-row-check]').forEach(c => { c.checked = tog.checked; const id = parseInt(c.getAttribute('data-row-check'),10); tog.checked ? _selectedIds.add(id) : _selectedIds.delete(id); });
      updateBulkButtons();
    });
    document.querySelectorAll('[data-bulk-act]').forEach(b => {
      b.addEventListener('click', () => bulkDecide(b.getAttribute('data-bulk-act')));
    });
  }

  function loadQueue() {
    const tbody = document.querySelector('[data-queue-body]');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6"><div class="skeleton-v5" style="height:48px;"></div></td></tr>';
    _selectedIds.clear(); updateBulkButtons();

    App.api('/admin/claims' + App.qs({ status: _filter.queue.status || 'pending', q: _filter.queue.q || '', limit: 100 })).then(r => {
      _claims = r.items || [];
      renderQueueRows(tbody);
    }).catch(e => { tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${App.escapeHtml(e?.detail||'Load failed')}</td></tr>`; });
  }

  function renderQueueRows(tbody) {
    if (!_claims.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4"><i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i><p class="text-muted mb-0">No claims match this filter.</p></td></tr>`;
      return;
    }
    tbody.innerHTML = _claims.map(c => `
      <tr>
        <td><input type="checkbox" class="form-check-input" data-row-check="${c.id}"></td>
        <td>
          <div class="dash-domain-cell">
            <span class="dash-domain-logo">${App.escapeHtml(App.initialsOf(c.name_en||c.slug))}</span>
            <div>
              <div class="dash-domain-name">${App.escapeHtml(c.subdomain)}</div>
              <div class="dash-domain-meta">${App.escapeHtml(c.name_en||c.slug)}${c.eiin ? ' &middot; EIIN '+App.escapeHtml(c.eiin) : ''}</div>
            </div>
          </div>
        </td>
        <td>${statusBadge(c.status)}</td>
        <td><span class="badge badge-brand">${App.escapeHtml(c.brand)}</span></td>
        <td class="small text-muted">${App.escapeHtml(App.fmtDate(c.created_at))}</td>
        <td class="text-end"><button class="btn btn-primary btn-sm" data-detail="${c.id}"><i class="bi bi-eye me-1"></i>Review</button></td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-row-check]').forEach(c => {
      c.addEventListener('change', () => { const id = parseInt(c.getAttribute('data-row-check'),10); c.checked ? _selectedIds.add(id) : _selectedIds.delete(id); updateBulkButtons(); });
    });
    tbody.querySelectorAll('[data-detail]').forEach(b => {
      b.addEventListener('click', () => openDetail(parseInt(b.getAttribute('data-detail'), 10)));
    });
  }

  function updateBulkButtons() {
    const n = _selectedIds.size;
    document.querySelectorAll('[data-bulk-act]').forEach(b => {
      b.disabled = n === 0;
      const base = { approve:'Approve', needs_info:'Needs info', reject:'Reject' }[b.getAttribute('data-bulk-act')] || '';
      b.textContent = n > 0 ? `${base} (${n})` : `${base} selected`;
    });
  }

  async function bulkDecide(decision) {
    if (!_selectedIds.size) return;
    const ids = [..._selectedIds];
    let notes = '';
    if (['reject','needs_info'].includes(decision)) notes = (prompt(`Note for ${ids.length} owner(s):`, '') || '').trim();
    if (!confirm(`${decision.toUpperCase()} ${ids.length} claim(s)?`)) return;
    try {
      const r = await App.api('/admin/queue/bulk-decide', { method: 'POST', body: { ids, decision, notes } });
      App.toast(`${decision} applied to ${r.count} claim(s)`, 'success');
      _selectedIds.clear(); loadOverview(); loadQueue();
    } catch (e) { App.toast(e?.detail || 'Bulk action failed', 'error'); }
  }


  // ─── Detail Modal ──────────────────────────────────────────────────────────

  function openDetail(id) {
    if (!detailModalInstance || !detailBody) return;
    detailBody.innerHTML = '<div class="skeleton-v5" style="height:200px;"></div>';
    detailModalInstance.show();
    App.api('/admin/claims/' + id).then(r => renderDetail(r)).catch(e => {
      detailBody.innerHTML = `<p class="text-danger">${App.escapeHtml(e?.detail || 'Could not load')}</p>`;
    });
  }

  function renderDetail(r) {
    const c = r.institution;
    const docs = r.documents || [];
    const owner = r.owner;
    const tok = encodeURIComponent(App.getToken() || '');

    detailBody.innerHTML = `
      <h5 class="mb-1">${App.escapeHtml(c.name_en || c.subdomain)}</h5>
      <p class="text-muted small"><code>${App.escapeHtml(c.subdomain)}</code> ${statusBadge(c.status)}</p>
      <div class="row g-3 mt-2">
        <div class="col-lg-6">
          <h6 class="fw-semibold mb-2">Details</h6>
          <table class="table table-sm small">
            <tr><th class="text-muted">Brand</th><td>${App.escapeHtml(c.brand)}</td></tr>
            <tr><th class="text-muted">Slug</th><td>${App.escapeHtml(c.slug)}</td></tr>
            <tr><th class="text-muted">Name EN</th><td>${App.escapeHtml(c.name_en||'—')}</td></tr>
            <tr><th class="text-muted">Name BN</th><td>${App.escapeHtml(c.name_bn||'—')}</td></tr>
            <tr><th class="text-muted">Category</th><td>${App.escapeHtml(c.category||'—')}</td></tr>
            <tr><th class="text-muted">EIIN</th><td>${App.escapeHtml(c.eiin||'—')}</td></tr>
            <tr><th class="text-muted">Division</th><td>${App.escapeHtml(c.division||'—')}</td></tr>
            <tr><th class="text-muted">District</th><td>${App.escapeHtml(c.district||'—')}</td></tr>
            <tr><th class="text-muted">Upazila</th><td>${App.escapeHtml(c.upazila||'—')}</td></tr>
            <tr><th class="text-muted">Contact</th><td>${App.escapeHtml(c.contact_name||'')} ${App.escapeHtml(c.contact_phone||'')}</td></tr>
            <tr><th class="text-muted">DNS</th><td>${App.escapeHtml(c.dns_status||'—')}</td></tr>
            <tr><th class="text-muted">Owner</th><td>${App.escapeHtml(owner ? (owner.name||owner.email) : '—')}</td></tr>
          </table>
        </div>
        <div class="col-lg-6">
          <h6 class="fw-semibold mb-2">Documents (${docs.length})</h6>
          ${docs.length ? docs.map(d => `
            <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-center">
              <div>
                <span class="badge bg-secondary-subtle text-secondary">${App.escapeHtml(d.doc_type)}</span>
                <small class="text-muted ms-2">${App.escapeHtml(d.filename)}</small>
              </div>
              <a class="btn btn-sm btn-outline-primary" target="_blank" href="/api/admin/documents/${d.id}?_t=${tok}"><i class="bi bi-eye"></i></a>
            </div>`).join('') : '<p class="text-muted small">No documents uploaded.</p>'}
          ${c.about_en ? `<h6 class="mt-3 fw-semibold">About</h6><p class="small">${App.escapeHtml(c.about_en)}</p>` : ''}
        </div>
      </div>
      <hr>
      <h6 class="fw-semibold">Decision</h6>
      <form data-decide="${c.id}">
        <div class="mb-3"><label class="form-label small">Notes (shown to owner)</label><textarea class="form-control form-control-sm" name="notes" rows="2"></textarea></div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-success btn-sm" data-act="approve" type="button"><i class="bi bi-check-lg me-1"></i>Approve & DNS</button>
          <button class="btn btn-warning btn-sm" data-act="needs_info" type="button"><i class="bi bi-question-circle me-1"></i>Needs info</button>
          <button class="btn btn-danger btn-sm" data-act="reject" type="button"><i class="bi bi-x-lg me-1"></i>Reject</button>
          <button class="btn btn-outline-secondary btn-sm" data-act="suspend" type="button">Suspend</button>
          ${c.status==='verified' && c.dns_status!=='live' ? '<button class="btn btn-outline-primary btn-sm" data-act="dns-retry" type="button">Retry DNS</button>' : ''}
        </div>
      </form>`;

    const form = detailBody.querySelector(`[data-decide="${c.id}"]`);
    form.querySelectorAll('[data-act]').forEach(b => {
      b.addEventListener('click', async () => {
        const act = b.getAttribute('data-act');
        const notes = form.querySelector('[name=notes]').value;
        try {
          if (act === 'dns-retry') {
            await App.api(`/admin/claims/${c.id}/dns-retry`, { method: 'POST' });
            App.toast('DNS retry submitted', 'success');
          } else {
            await App.api(`/admin/claims/${c.id}/decide`, { method: 'POST', body: { decision: act, notes } });
            App.toast('Decision: ' + act, 'success');
          }
          detailModalInstance.hide();
          loadOverview(); loadQueue();
        } catch (e) { App.toast(e?.detail || 'Action failed', 'error'); }
      });
    });
  }


  // ─── Users ─────────────────────────────────────────────────────────────────

  function wireUsers() {
    const form = document.querySelector('[data-users-form]');
    const qEl = form?.querySelector('[data-users-q]');
    const rEl = form?.querySelector('[data-users-role]');
    if (form) form.addEventListener('submit', e => e.preventDefault());
    if (qEl) { const d = App.debounce(() => { _filter.users.q = (qEl.value||'').trim(); loadUsers(); }, 250); qEl.addEventListener('input', d); }
    if (rEl) rEl.addEventListener('change', () => { _filter.users.role = rEl.value; loadUsers(); });
  }

  function loadUsers() {
    const tbody = document.querySelector('[data-users-body]');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6"><div class="skeleton-v5" style="height:48px;"></div></td></tr>';
    App.api('/admin/users' + App.qs({ q: _filter.users.q, role: _filter.users.role === 'any' ? '' : _filter.users.role, limit: 100 })).then(r => {
      _users = r.items || [];
      if (!_users.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No users found.</td></tr>'; return; }
      tbody.innerHTML = _users.map(u => `
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold" style="width:32px;height:32px;background:var(--c-primary);font-size:.75rem;">${App.escapeHtml((u.name||u.email||'?')[0].toUpperCase())}</span>
              <div><div class="fw-semibold small">${App.escapeHtml(u.name||'—')}</div><div class="text-muted" style="font-size:.75rem;">${App.escapeHtml(u.email)}</div></div>
            </div>
          </td>
          <td><span class="badge ${u.is_admin?'bg-primary-subtle text-primary':'bg-secondary-subtle text-secondary'}">${u.is_admin?'Admin':'Owner'}</span></td>
          <td class="small text-muted">${App.escapeHtml(u.provider||'email')}</td>
          <td class="small">${u.claims_count ?? '—'}</td>
          <td class="small text-muted">${App.escapeHtml(App.fmtDate(u.created_at))}</td>
          <td class="text-end">
            <button class="btn btn-sm ${u.is_admin?'btn-outline-warning':'btn-outline-primary'}" data-toggle-admin="${u.id}" data-current="${u.is_admin?1:0}">
              ${u.is_admin ? '<i class="bi bi-person-dash me-1"></i>Demote' : '<i class="bi bi-person-up me-1"></i>Promote'}
            </button>
          </td>
        </tr>`).join('');

      tbody.querySelectorAll('[data-toggle-admin]').forEach(b => {
        b.addEventListener('click', async () => {
          const uid = b.getAttribute('data-toggle-admin');
          const current = b.getAttribute('data-current') === '1';
          const newRole = current ? 'user' : 'admin';
          if (!confirm(`${current ? 'Demote' : 'Promote'} this user to ${newRole}?`)) return;
          try {
            await App.api(`/admin/users/${uid}/role`, { method: 'POST', body: { role: newRole } });
            App.toast(`User role updated to ${newRole}`, 'success');
            loadUsers();
          } catch (e) { App.toast(e?.detail || 'Failed', 'error'); }
        });
      });
    }).catch(e => { tbody.innerHTML = `<tr><td colspan="6" class="text-danger">${App.escapeHtml(e?.detail||'Failed')}</td></tr>`; });
  }


  // ─── Reserved Slugs ────────────────────────────────────────────────────────

  function wireReserved() {
    const form = document.querySelector('[data-reserved-form]');
    if (form) form.addEventListener('submit', async e => {
      e.preventDefault();
      const fd = new FormData(form);
      const slug = (fd.get('slug') || '').trim();
      const reason = (fd.get('reason') || '').trim();
      if (!slug) return;
      try {
        await App.api('/admin/reserved', { method: 'POST', body: { slug, reason } });
        App.toast(`"${slug}" reserved`, 'success');
        form.reset();
        loadReserved();
      } catch (e2) { App.toast(e2?.detail || 'Reserve failed', 'error'); }
    });
  }

  function loadReserved() {
    const host = document.querySelector('[data-reserved]');
    if (!host) return;
    host.innerHTML = '<div class="skeleton-v5" style="height:60px;"></div>';
    App.api('/admin/reserved').then(r => {
      const items = r.items || [];
      if (!items.length) { host.innerHTML = '<p class="text-muted small">No reserved slugs.</p>'; return; }
      host.innerHTML = `<div class="list-group list-group-flush">${items.map(i => `
        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
          <div><code>${App.escapeHtml(i.slug)}</code> ${i.reason ? `<small class="text-muted ms-2">— ${App.escapeHtml(i.reason)}</small>` : ''}</div>
          <button class="btn btn-sm btn-outline-danger" data-del-reserved="${i.id}"><i class="bi bi-trash"></i></button>
        </div>`).join('')}</div>`;
      host.querySelectorAll('[data-del-reserved]').forEach(b => {
        b.addEventListener('click', async () => {
          if (!confirm('Release this reserved slug?')) return;
          try { await App.api(`/admin/reserved/${b.getAttribute('data-del-reserved')}`, { method: 'DELETE' }); App.toast('Released', 'success'); loadReserved(); }
          catch (e2) { App.toast(e2?.detail || 'Failed', 'error'); }
        });
      });
    }).catch(e => { host.innerHTML = `<p class="text-danger small">${App.escapeHtml(e?.detail||'Failed')}</p>`; });
  }

  // ─── Audit Log ─────────────────────────────────────────────────────────────

  function wireAudit() {
    const form = document.querySelector('[data-audit-form]');
    if (form) form.addEventListener('submit', e => { e.preventDefault(); loadAudit(); });
  }

  function loadAudit() {
    const host = document.querySelector('[data-audit]');
    if (!host) return;
    const form = document.querySelector('[data-audit-form]');
    const fd = form ? Object.fromEntries(new FormData(form).entries()) : {};
    host.innerHTML = '<div class="skeleton-v5" style="height:120px;"></div>';
    App.api('/admin/audit' + App.qs({ action: fd.action, q: fd.q, inst_id: fd.inst_id, limit: 50 })).then(r => {
      const items = r.items || [];
      if (!items.length) { host.innerHTML = '<p class="text-muted small">No audit entries.</p>'; return; }
      host.innerHTML = `<table class="table table-sm dash-table-v5 small"><thead><tr><th>Time</th><th>Action</th><th>Actor</th><th>Detail</th></tr></thead><tbody>${items.map(a => `
        <tr>
          <td class="text-muted text-nowrap">${App.escapeHtml(App.fmtDate(a.created_at))}</td>
          <td><code>${App.escapeHtml(a.action)}</code></td>
          <td>${App.escapeHtml(a.actor_email || a.actor_name || '—')}</td>
          <td class="text-muted">${App.escapeHtml((a.detail||'').slice(0,80))}</td>
        </tr>`).join('')}</tbody></table>`;
    }).catch(e => { host.innerHTML = `<p class="text-danger">${App.escapeHtml(e?.detail||'Failed')}</p>`; });
  }

  // ─── Exports ───────────────────────────────────────────────────────────────

  function wireExports() {
    document.querySelectorAll('[data-export]').forEach(b => {
      b.addEventListener('click', e => {
        e.preventDefault();
        const type = b.getAttribute('data-export');
        const token = App.getToken();
        window.open(`/api/admin/exports/${type}?_t=${encodeURIComponent(token)}`, '_blank');
      });
    });
  }


  // ─── Settings Pane ──────────────────────────────────────────────────────────

  const THEME_SWATCHES = [
    '#0f766e','#2563eb','#7c3aed','#db2777',
    '#ea580c','#16a34a','#0891b2','#4f46e5'
  ];

  function loadSettings() {
    const host = document.querySelector('[data-settings-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    App.api('/admin/settings').then(r => {
      const s = r.settings || {};
      renderSettingsForm(host, s);
    }).catch(e => {
      host.innerHTML = `<div class="alert alert-danger">${App.escapeHtml(e?.detail || 'Failed to load settings')}</div>`;
    });
  }

  function renderSettingsForm(host, s) {
    const swatchesHtml = THEME_SWATCHES.map(c =>
      `<button type="button" class="btn p-0 border-2 rounded-circle me-2 mb-2" data-swatch="${c}"
        style="width:32px;height:32px;background:${c};${s.theme_primary_hex === c ? 'border-color:var(--bs-body-color);box-shadow:0 0 0 2px var(--bs-body-color)' : 'border-color:transparent'}"></button>`
    ).join('');

    host.innerHTML = `
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-palette me-2"></i>Theme Color</h6>
          <div class="mb-3">
            <div class="d-flex flex-wrap align-items-center mb-2">${swatchesHtml}</div>
            <div class="input-group" style="max-width:220px;">
              <span class="input-group-text"><i class="bi bi-hash"></i></span>
              <input type="text" class="form-control form-control-sm" data-theme-hex
                value="${App.escapeHtml(s.theme_primary_hex || '')}" placeholder="0f766e" maxlength="7">
              <span class="input-group-text p-0 overflow-hidden" style="width:38px;">
                <input type="color" class="border-0 w-100 h-100" style="cursor:pointer;"
                  data-theme-picker value="${s.theme_primary_hex || '#0f766e'}">
              </span>
            </div>
            <small class="text-muted">Leave blank to use the default teal.</small>
          </div>
          <hr>

          <h6 class="fw-semibold mb-3"><i class="bi bi-gear me-2"></i>Platform Toggles</h6>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="set_require_approval" ${s.require_approval ? 'checked' : ''}>
                <label class="form-check-label" for="set_require_approval">Require admin approval</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="set_require_documents" ${s.require_documents ? 'checked' : ''}>
                <label class="form-check-label" for="set_require_documents">Require documents</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="set_instant_claim" ${s.instant_claim ? 'checked' : ''}>
                <label class="form-check-label" for="set_instant_claim">Instant claim (skip queue)</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="set_cloudflare_auto_dns" ${s.cloudflare_auto_dns ? 'checked' : ''}>
                <label class="form-check-label" for="set_cloudflare_auto_dns">Cloudflare auto-DNS</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="set_email_registration" ${s.email_registration_enabled ? 'checked' : ''}>
                <label class="form-check-label" for="set_email_registration">Email registration enabled</label>
              </div>
            </div>
          </div>
          <hr>

          <h6 class="fw-semibold mb-3"><i class="bi bi-calendar3 me-2"></i>Domain Terms</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Term length (days)</label>
              <input type="number" class="form-control" id="set_term_days" min="1" value="${s.domain_term_days || 365}">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Renewal price (BDT)</label>
              <input type="number" class="form-control" id="set_renewal_price" min="0" value="${s.domain_renewal_price_bdt || 0}">
            </div>
          </div>
          <button class="btn btn-primary" data-save-settings><i class="bi bi-check-lg me-1"></i>Save Settings</button>
        </div>
      </div>`;

    // Wire swatches
    host.querySelectorAll('[data-swatch]').forEach(btn => {
      btn.addEventListener('click', () => {
        const hex = btn.getAttribute('data-swatch');
        host.querySelector('[data-theme-hex]').value = hex;
        host.querySelector('[data-theme-picker]').value = hex;
        host.querySelectorAll('[data-swatch]').forEach(b => { b.style.borderColor = 'transparent'; b.style.boxShadow = 'none'; });
        btn.style.borderColor = 'var(--bs-body-color)';
        btn.style.boxShadow = '0 0 0 2px var(--bs-body-color)';
      });
    });

    // Wire color picker
    const picker = host.querySelector('[data-theme-picker]');
    const hexInput = host.querySelector('[data-theme-hex]');
    if (picker) picker.addEventListener('input', () => { hexInput.value = picker.value; });
    if (hexInput) hexInput.addEventListener('input', () => {
      let v = hexInput.value.trim().replace(/^#/, '');
      if (/^[0-9a-f]{6}$/i.test(v)) picker.value = '#' + v;
    });


    // Wire save
    host.querySelector('[data-save-settings]').addEventListener('click', async () => {
      const btn = host.querySelector('[data-save-settings]');
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
      let themeHex = (hexInput.value || '').trim().replace(/^#/, '');
      if (themeHex && !/^[0-9a-f]{6}$/i.test(themeHex)) { App.toast('Invalid hex color', 'error'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Settings'; return; }
      const body = {
        require_approval: host.querySelector('#set_require_approval').checked,
        require_documents: host.querySelector('#set_require_documents').checked,
        instant_claim: host.querySelector('#set_instant_claim').checked,
        cloudflare_auto_dns: host.querySelector('#set_cloudflare_auto_dns').checked,
        email_registration_enabled: host.querySelector('#set_email_registration').checked,
        domain_term_days: parseInt(host.querySelector('#set_term_days').value, 10) || 365,
        domain_renewal_price_bdt: parseInt(host.querySelector('#set_renewal_price').value, 10) || 0,
        theme_primary_hex: themeHex ? '#' + themeHex : '',
      };
      try {
        const res = await App.api('/admin/settings', { method: 'POST', body });
        App.toast('Settings saved', 'success');
        if (body.theme_primary_hex && App.applyBrandTheme) App.applyBrandTheme(body.theme_primary_hex);
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Settings';
      } catch (e) {
        App.toast(e?.detail || 'Save failed', 'error');
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Settings';
      }
    });
  }


  // ─── Integrations Pane ─────────────────────────────────────────────────────

  function loadIntegrations() {
    const host = document.querySelector('[data-integrations-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    App.api('/admin/integrations').then(r => {
      renderIntegrationsForm(host, r);
    }).catch(e => {
      host.innerHTML = `<div class="alert alert-danger">${App.escapeHtml(e?.detail || 'Failed to load integrations')}</div>`;
    });
  }

  function intVal(values, key) {
    const v = values[key];
    if (!v) return '';
    return v.masked ? '' : (v.value || '');
  }

  function intIsSet(values, key) {
    return !!(values[key]?.is_set);
  }

  function secretField(id, label, values, key, placeholder) {
    const isSet = intIsSet(values, key);
    const hint = isSet ? '<small class="text-success"><i class="bi bi-check-circle me-1"></i>Saved</small>' : '';
    return `<div class="mb-3">
      <label class="form-label small fw-semibold">${label} ${hint}</label>
      <input type="password" class="form-control form-control-sm" id="${id}"
        placeholder="${isSet ? '••••••••••••••••' : placeholder}" autocomplete="new-password">
    </div>`;
  }


  function renderIntegrationsForm(host, data) {
    const v = data.values || {};
    const redirects = data.redirects || {};

    host.innerHTML = `
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-globe me-2"></i>Site & Branding</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Site URL</label>
              <input type="url" class="form-control form-control-sm" id="int_site_url" value="${App.escapeHtml(intVal(v, 'site.url'))}" placeholder="https://institution.bd">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Brand Name</label>
              <input type="text" class="form-control form-control-sm" id="int_brand_name" value="${App.escapeHtml(intVal(v, 'brand.name'))}" placeholder="institution.bd">
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-shield-lock me-2"></i>OAuth Providers</h6>
          <div class="row g-3">
            <div class="col-lg-4">
              <h6 class="small text-muted mb-2"><i class="bi bi-google me-1"></i>Google</h6>
              <div class="mb-2"><label class="form-label small">Client ID</label>
                <input type="text" class="form-control form-control-sm" id="int_google_id" value="${App.escapeHtml(intVal(v, 'oauth.google.client_id'))}"></div>
              ${secretField('int_google_secret', 'Client Secret', v, 'oauth.google.client_secret', 'Google client secret')}
              <small class="text-muted">Redirect: <code>${App.escapeHtml(redirects.google || '')}</code></small>
            </div>
            <div class="col-lg-4">
              <h6 class="small text-muted mb-2"><i class="bi bi-facebook me-1"></i>Facebook</h6>
              <div class="mb-2"><label class="form-label small">Client ID</label>
                <input type="text" class="form-control form-control-sm" id="int_fb_id" value="${App.escapeHtml(intVal(v, 'oauth.facebook.client_id'))}"></div>
              ${secretField('int_fb_secret', 'Client Secret', v, 'oauth.facebook.client_secret', 'Facebook client secret')}
              <small class="text-muted">Redirect: <code>${App.escapeHtml(redirects.facebook || '')}</code></small>
            </div>
            <div class="col-lg-4">
              <h6 class="small text-muted mb-2"><i class="bi bi-github me-1"></i>GitHub</h6>
              <div class="mb-2"><label class="form-label small">Client ID</label>
                <input type="text" class="form-control form-control-sm" id="int_gh_id" value="${App.escapeHtml(intVal(v, 'oauth.github.client_id'))}"></div>
              ${secretField('int_gh_secret', 'Client Secret', v, 'oauth.github.client_secret', 'GitHub client secret')}
              <small class="text-muted">Redirect: <code>${App.escapeHtml(redirects.github || '')}</code></small>
            </div>
          </div>
        </div>
      </div>


      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-cloud me-2"></i>Cloudflare DNS</h6>
          <div class="row g-3">
            <div class="col-md-6">
              ${secretField('int_cf_token', 'API Token', v, 'cloudflare.api_token', 'Cloudflare API token')}
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Zone ID (institution.bd)</label>
              <input type="text" class="form-control form-control-sm" id="int_cf_zone_inst" value="${App.escapeHtml(intVal(v, 'cloudflare.zones.institution_bd'))}">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Zone ID (smartschool.bd)</label>
              <input type="text" class="form-control form-control-sm" id="int_cf_zone_ss" value="${App.escapeHtml(intVal(v, 'cloudflare.zones.smartschool_bd'))}">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Target type</label>
              <select class="form-select form-select-sm" id="int_cf_target_type">
                <option value="CNAME" ${intVal(v,'cloudflare.target_type')==='CNAME'?'selected':''}>CNAME</option>
                <option value="A" ${intVal(v,'cloudflare.target_type')==='A'?'selected':''}>A</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Target value</label>
              <input type="text" class="form-control form-control-sm" id="int_cf_target_val" value="${App.escapeHtml(intVal(v, 'cloudflare.target_value'))}" placeholder="e.g. your-server.example.com">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="int_cf_proxied" ${intVal(v,'cloudflare.proxied')==='1'?'checked':''}>
                <label class="form-check-label small" for="int_cf_proxied">Proxied</label>
              </div>
              <button class="btn btn-outline-primary btn-sm" data-cf-test><i class="bi bi-arrow-repeat me-1"></i>Test</button>
            </div>
          </div>
          <div class="mt-2" data-cf-test-result></div>
        </div>
      </div>


      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-whatsapp me-2"></i>WhatsApp Support</h6>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Support number</label>
              <input type="text" class="form-control form-control-sm" id="int_wa_number" value="${App.escapeHtml(intVal(v, 'whatsapp.support_number'))}" placeholder="8801XXXXXXXXX">
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Prefilled message</label>
              <input type="text" class="form-control form-control-sm" id="int_wa_msg" value="${App.escapeHtml(intVal(v, 'whatsapp.support_prefilled_message'))}" placeholder="Hi, I need help with...">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Community URL</label>
              <input type="url" class="form-control form-control-sm" id="int_wa_community" value="${App.escapeHtml(intVal(v, 'whatsapp.community_url'))}">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Community title</label>
              <input type="text" class="form-control form-control-sm" id="int_wa_title" value="${App.escapeHtml(intVal(v, 'whatsapp.community_title'))}">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Community subtitle</label>
              <input type="text" class="form-control form-control-sm" id="int_wa_subtitle" value="${App.escapeHtml(intVal(v, 'whatsapp.community_subtitle'))}">
            </div>
          </div>
        </div>
      </div>


      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-envelope me-2"></i>Email / SMTP</h6>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Transport</label>
              <select class="form-select form-select-sm" id="int_mail_transport">
                <option value="smtp" ${intVal(v,'mail.transport')==='smtp'?'selected':''}>SMTP</option>
                <option value="mail" ${intVal(v,'mail.transport')==='mail'?'selected':''}>PHP mail()</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">From email</label>
              <input type="email" class="form-control form-control-sm" id="int_mail_from_email" value="${App.escapeHtml(intVal(v, 'mail.from_email'))}">
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold">From name</label>
              <input type="text" class="form-control form-control-sm" id="int_mail_from_name" value="${App.escapeHtml(intVal(v, 'mail.from_name'))}">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">SMTP host</label>
              <input type="text" class="form-control form-control-sm" id="int_smtp_host" value="${App.escapeHtml(intVal(v, 'mail.smtp_host'))}" placeholder="smtp.gmail.com">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Port</label>
              <input type="number" class="form-control form-control-sm" id="int_smtp_port" value="${App.escapeHtml(intVal(v, 'mail.smtp_port'))}" placeholder="587">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Secure</label>
              <select class="form-select form-select-sm" id="int_smtp_secure">
                <option value="" ${intVal(v,'mail.smtp_secure')===''?'selected':''}>None</option>
                <option value="tls" ${intVal(v,'mail.smtp_secure')==='tls'?'selected':''}>TLS</option>
                <option value="ssl" ${intVal(v,'mail.smtp_secure')==='ssl'?'selected':''}>SSL</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">SMTP user</label>
              <input type="text" class="form-control form-control-sm" id="int_smtp_user" value="${App.escapeHtml(intVal(v, 'mail.smtp_user'))}">
            </div>
            <div class="col-md-4">
              ${secretField('int_smtp_pass', 'SMTP password', v, 'mail.smtp_pass', 'SMTP password')}
            </div>
          </div>
        </div>
      </div>


      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-key me-2"></i>JWT Secret</h6>
          <div class="d-flex align-items-end gap-3">
            <div class="flex-grow-1">
              ${secretField('int_jwt_secret', 'JWT Secret', v, 'jwt.secret', 'JWT signing key')}
            </div>
            <button class="btn btn-outline-danger btn-sm mb-3" data-rotate-jwt><i class="bi bi-arrow-clockwise me-1"></i>Rotate</button>
          </div>
        </div>
      </div>

      <button class="btn btn-primary" data-save-integrations><i class="bi bi-check-lg me-1"></i>Save All Integrations</button>
    `;

    // Wire CF test
    host.querySelector('[data-cf-test]')?.addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      const result = host.querySelector('[data-cf-test-result]');
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
      try {
        const r = await App.api('/admin/integrations/test', { method: 'POST', body: { target: 'cloudflare' } });
        if (r.ok) {
          const zoneList = (r.zones || []).map(z => `<code>${App.escapeHtml(z.name)}</code> (${App.escapeHtml(z.id)})`).join(', ');
          result.innerHTML = `<div class="alert alert-success small py-2 mb-0"><i class="bi bi-check-circle me-1"></i>${App.escapeHtml(r.message)}${zoneList ? '<br>Zones: ' + zoneList : ''}</div>`;
        } else {
          result.innerHTML = `<div class="alert alert-danger small py-2 mb-0"><i class="bi bi-x-circle me-1"></i>${App.escapeHtml(r.message)}${r.hint ? '<br><small>' + App.escapeHtml(r.hint) + '</small>' : ''}</div>`;
        }
      } catch (err) {
        result.innerHTML = `<div class="alert alert-danger small py-2 mb-0">${App.escapeHtml(err?.detail || 'Test failed')}</div>`;
      }
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Test';
    });


    // Wire rotate JWT
    host.querySelector('[data-rotate-jwt]')?.addEventListener('click', async () => {
      if (!confirm('Rotate JWT secret? All sessions (including yours) will be invalidated.')) return;
      try {
        const r = await App.api('/admin/integrations/rotate-jwt', { method: 'POST' });
        App.toast(r.message || 'JWT rotated', 'success');
      } catch (err) { App.toast(err?.detail || 'Rotation failed', 'error'); }
    });

    // Wire save all
    host.querySelector('[data-save-integrations]')?.addEventListener('click', async () => {
      const btn = host.querySelector('[data-save-integrations]');
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      const values = {
        'site.url': host.querySelector('#int_site_url').value.trim(),
        'brand.name': host.querySelector('#int_brand_name').value.trim(),
        'oauth.google.client_id': host.querySelector('#int_google_id').value.trim(),
        'oauth.google.client_secret': host.querySelector('#int_google_secret').value.trim(),
        'oauth.facebook.client_id': host.querySelector('#int_fb_id').value.trim(),
        'oauth.facebook.client_secret': host.querySelector('#int_fb_secret').value.trim(),
        'oauth.github.client_id': host.querySelector('#int_gh_id').value.trim(),
        'oauth.github.client_secret': host.querySelector('#int_gh_secret').value.trim(),
        'cloudflare.api_token': host.querySelector('#int_cf_token').value.trim(),
        'cloudflare.zones.institution_bd': host.querySelector('#int_cf_zone_inst').value.trim(),
        'cloudflare.zones.smartschool_bd': host.querySelector('#int_cf_zone_ss').value.trim(),
        'cloudflare.target_type': host.querySelector('#int_cf_target_type').value,
        'cloudflare.target_value': host.querySelector('#int_cf_target_val').value.trim(),
        'cloudflare.proxied': host.querySelector('#int_cf_proxied').checked ? '1' : '0',
        'whatsapp.support_number': host.querySelector('#int_wa_number').value.trim(),
        'whatsapp.support_prefilled_message': host.querySelector('#int_wa_msg').value.trim(),
        'whatsapp.community_url': host.querySelector('#int_wa_community').value.trim(),
        'whatsapp.community_title': host.querySelector('#int_wa_title').value.trim(),
        'whatsapp.community_subtitle': host.querySelector('#int_wa_subtitle').value.trim(),
        'mail.transport': host.querySelector('#int_mail_transport').value,
        'mail.from_email': host.querySelector('#int_mail_from_email').value.trim(),
        'mail.from_name': host.querySelector('#int_mail_from_name').value.trim(),
        'mail.smtp_host': host.querySelector('#int_smtp_host').value.trim(),
        'mail.smtp_port': host.querySelector('#int_smtp_port').value.trim(),
        'mail.smtp_secure': host.querySelector('#int_smtp_secure').value,
        'mail.smtp_user': host.querySelector('#int_smtp_user').value.trim(),
        'mail.smtp_pass': host.querySelector('#int_smtp_pass').value.trim(),
        'jwt.secret': host.querySelector('#int_jwt_secret').value.trim(),
      };

      try {
        await App.api('/admin/integrations', { method: 'POST', body: { values } });
        App.toast('Integrations saved', 'success');
      } catch (err) { App.toast(err?.detail || 'Save failed', 'error'); }
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save All Integrations';
    });
  }


  // ─── Admin Payments Pane ───────────────────────────────────────────────────

  let _payMethods = [];
  let _payEditing = null;

  function loadAdminPayments() {
    const host = document.querySelector('[data-payments-admin-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    fetchPayMethods(host);
  }

  async function fetchPayMethods(host) {
    if (!host) host = document.querySelector('[data-payments-admin-host]');
    try {
      const r = await App.api('/admin/support/payments');
      _payMethods = r.items || [];
      renderPayMethods(host);
    } catch (e) {
      host.innerHTML = `<div class="alert alert-danger">${App.escapeHtml(e?.detail || 'Failed')}</div>`;
    }
  }

  function renderPayMethods(host) {
    const rows = _payMethods.map(m => `
      <tr>
        <td><span class="badge bg-secondary-subtle text-secondary">${App.escapeHtml(m.method)}</span></td>
        <td>${App.escapeHtml(m.label)}</td>
        <td class="small text-muted">${App.escapeHtml(m.number || '—')}</td>
        <td>
          <div class="form-check form-switch d-inline-block">
            <input class="form-check-input" type="checkbox" data-pay-toggle="${m.id}" ${m.visible ? 'checked' : ''}>
          </div>
        </td>
        <td class="small">${m.sort_order}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" data-pay-edit="${m.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" data-pay-del="${m.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');


    host.innerHTML = `
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0"><i class="bi bi-credit-card me-2"></i>Payment Methods</h6>
            <button class="btn btn-primary btn-sm" data-pay-add><i class="bi bi-plus-lg me-1"></i>Add Method</button>
          </div>
          ${_payMethods.length ? `
          <div class="table-responsive">
            <table class="table table-sm align-middle dash-table-v5">
              <thead><tr><th>Method</th><th>Label</th><th>Number</th><th>Visible</th><th>Order</th><th></th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>` : '<p class="text-muted small">No payment methods configured yet.</p>'}
          <div data-pay-form-host></div>
        </div>
      </div>`;

    // Wire toggle visibility
    host.querySelectorAll('[data-pay-toggle]').forEach(cb => {
      cb.addEventListener('change', async () => {
        const id = cb.getAttribute('data-pay-toggle');
        try {
          await App.api(`/admin/support/payments/${id}`, { method: 'PATCH', body: { visible: cb.checked } });
          App.toast('Visibility updated', 'success');
        } catch (e) { App.toast(e?.detail || 'Failed', 'error'); cb.checked = !cb.checked; }
      });
    });

    // Wire edit
    host.querySelectorAll('[data-pay-edit]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-pay-edit'), 10);
        _payEditing = _payMethods.find(m => m.id === id) || null;
        renderPayForm(host);
      });
    });

    // Wire delete
    host.querySelectorAll('[data-pay-del]').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this payment method?')) return;
        const id = btn.getAttribute('data-pay-del');
        try {
          await App.api(`/admin/support/payments/${id}`, { method: 'DELETE' });
          App.toast('Deleted', 'success');
          host.dataset.loaded = ''; loadAdminPayments();
        } catch (e) { App.toast(e?.detail || 'Failed', 'error'); }
      });
    });

    // Wire add
    host.querySelector('[data-pay-add]')?.addEventListener('click', () => {
      _payEditing = null;
      renderPayForm(host);
    });
  }


  function renderPayForm(host) {
    const formHost = host.querySelector('[data-pay-form-host]');
    if (!formHost) return;
    const m = _payEditing;
    const methodOptions = ['bkash','nagad','rocket','upay','bank','card','paypal','crypto','other']
      .map(o => `<option value="${o}" ${m?.method === o ? 'selected' : ''}>${o.charAt(0).toUpperCase() + o.slice(1)}</option>`).join('');

    formHost.innerHTML = `
      <hr>
      <h6 class="fw-semibold small">${m ? 'Edit' : 'Add'} Payment Method</h6>
      <form data-pay-form>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small">Method</label>
            <select class="form-select form-select-sm" name="method">${methodOptions}</select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Label</label>
            <input type="text" class="form-control form-control-sm" name="label" value="${App.escapeHtml(m?.label || '')}" required placeholder="e.g. Personal bKash">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Number</label>
            <input type="text" class="form-control form-control-sm" name="number" value="${App.escapeHtml(m?.number || '')}" placeholder="01XXXXXXXXX">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Sort order</label>
            <input type="number" class="form-control form-control-sm" name="sort_order" value="${m?.sort_order ?? 0}" min="0">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Note</label>
            <input type="text" class="form-control form-control-sm" name="note" value="${App.escapeHtml(m?.note || '')}" placeholder="Optional instructions">
          </div>
          <div class="col-md-4">
            <label class="form-label small">QR URL</label>
            <input type="text" class="form-control form-control-sm" name="qr_url" value="${App.escapeHtml(m?.qr_url || '')}" placeholder="/uploads/qr.png or https://...">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="visible" ${m ? (m.visible ? 'checked' : '') : 'checked'}>
              <label class="form-check-label small">Visible</label>
            </div>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>${m ? 'Update' : 'Create'}</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-pay-cancel>Cancel</button>
        </div>
      </form>`;

    formHost.querySelector('[data-pay-cancel]').addEventListener('click', () => { formHost.innerHTML = ''; });
    formHost.querySelector('[data-pay-form]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const body = {
        method: fd.get('method'),
        label: fd.get('label'),
        number: fd.get('number'),
        note: fd.get('note'),
        qr_url: fd.get('qr_url'),
        sort_order: parseInt(fd.get('sort_order'), 10) || 0,
        visible: !!e.target.querySelector('[name=visible]').checked,
      };
      try {
        if (m) {
          await App.api(`/admin/support/payments/${m.id}`, { method: 'PATCH', body });
        } else {
          await App.api('/admin/support/payments', { method: 'POST', body });
        }
        App.toast(m ? 'Updated' : 'Created', 'success');
        host.dataset.loaded = ''; loadAdminPayments();
      } catch (err) { App.toast(err?.detail || 'Save failed', 'error'); }
    });
  }


  // ─── Sponsors Pane ─────────────────────────────────────────────────────────

  let _sponsors = [];
  let _sponsorEditing = null;

  function loadAdminSponsors() {
    const host = document.querySelector('[data-sponsors-admin-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    fetchSponsors(host);
  }

  async function fetchSponsors(host) {
    if (!host) host = document.querySelector('[data-sponsors-admin-host]');
    try {
      const r = await App.api('/admin/sponsors');
      _sponsors = r.items || [];
      renderSponsors(host);
    } catch (e) {
      host.innerHTML = `<div class="alert alert-danger">${App.escapeHtml(e?.detail || 'Failed to load sponsors')}</div>`;
    }
  }

  function renderSponsors(host) {
    const rows = _sponsors.map(s => `
      <tr>
        <td>${App.escapeHtml(s.name)}</td>
        <td class="small text-muted text-truncate" style="max-width:180px;">${App.escapeHtml(s.logo_url)}</td>
        <td class="small text-muted text-truncate" style="max-width:150px;">${App.escapeHtml(s.website_url || '-')}</td>
        <td>
          <div class="form-check form-switch d-inline-block">
            <input class="form-check-input" type="checkbox" data-sponsor-toggle="${s.id}" ${s.visible ? 'checked' : ''}>
          </div>
        </td>
        <td class="small">${s.sort_order}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" data-sponsor-edit="${s.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" data-sponsor-del="${s.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');

    host.innerHTML = `
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0"><i class="bi bi-megaphone me-2"></i>Sponsor Logos</h6>
            <button class="btn btn-primary btn-sm" data-sponsor-add><i class="bi bi-plus-lg me-1"></i>Add Sponsor</button>
          </div>
          ${_sponsors.length ? `
          <div class="table-responsive">
            <table class="table table-sm align-middle dash-table-v5">
              <thead><tr><th>Name</th><th>Logo URL</th><th>Website</th><th>Visible</th><th>Sort</th><th></th></tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>` : '<p class="text-muted small">No sponsors configured yet.</p>'}
          <div data-sponsor-form-host></div>
        </div>
      </div>`;

    // Wire toggle visibility
    host.querySelectorAll('[data-sponsor-toggle]').forEach(cb => {
      cb.addEventListener('change', async () => {
        const id = cb.getAttribute('data-sponsor-toggle');
        try {
          await App.api(`/admin/sponsors/${id}`, { method: 'PATCH', body: { visible: cb.checked } });
          App.toast('Visibility updated', 'success');
        } catch (e) { App.toast(e?.detail || 'Failed', 'error'); cb.checked = !cb.checked; }
      });
    });

    // Wire edit
    host.querySelectorAll('[data-sponsor-edit]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-sponsor-edit'), 10);
        _sponsorEditing = _sponsors.find(s => s.id === id) || null;
        renderSponsorForm(host);
      });
    });

    // Wire delete
    host.querySelectorAll('[data-sponsor-del]').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this sponsor?')) return;
        const id = btn.getAttribute('data-sponsor-del');
        try {
          await App.api(`/admin/sponsors/${id}`, { method: 'DELETE' });
          App.toast('Deleted', 'success');
          host.dataset.loaded = ''; loadAdminSponsors();
        } catch (e) { App.toast(e?.detail || 'Failed', 'error'); }
      });
    });

    // Wire add
    host.querySelector('[data-sponsor-add]')?.addEventListener('click', () => {
      _sponsorEditing = null;
      renderSponsorForm(host);
    });
  }

  function renderSponsorForm(host) {
    const formHost = host.querySelector('[data-sponsor-form-host]');
    if (!formHost) return;
    const s = _sponsorEditing;

    formHost.innerHTML = `
      <hr>
      <h6 class="fw-semibold small">${s ? 'Edit' : 'Add'} Sponsor</h6>
      <form data-sponsor-form>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small">Name</label>
            <input type="text" class="form-control form-control-sm" name="name" value="${App.escapeHtml(s?.name || '')}" required placeholder="Sponsor name">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Logo URL</label>
            <input type="text" class="form-control form-control-sm" name="logo_url" value="${App.escapeHtml(s?.logo_url || '')}" required placeholder="https://... or /uploads/logo.png">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Website URL</label>
            <input type="text" class="form-control form-control-sm" name="website_url" value="${App.escapeHtml(s?.website_url || '')}" placeholder="https://example.com">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Sort order</label>
            <input type="number" class="form-control form-control-sm" name="sort_order" value="${s?.sort_order ?? 0}" min="0">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="visible" ${s ? (s.visible ? 'checked' : '') : 'checked'}>
              <label class="form-check-label small">Visible</label>
            </div>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>${s ? 'Update' : 'Create'}</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-sponsor-cancel>Cancel</button>
        </div>
      </form>`;

    formHost.querySelector('[data-sponsor-cancel]').addEventListener('click', () => { formHost.innerHTML = ''; });
    formHost.querySelector('[data-sponsor-form]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const body = {
        name: fd.get('name'),
        logo_url: fd.get('logo_url'),
        website_url: fd.get('website_url'),
        sort_order: parseInt(fd.get('sort_order'), 10) || 0,
        visible: !!e.target.querySelector('[name=visible]').checked,
      };
      try {
        if (s) {
          await App.api(`/admin/sponsors/${s.id}`, { method: 'PATCH', body });
        } else {
          await App.api('/admin/sponsors', { method: 'POST', body });
        }
        App.toast(s ? 'Updated' : 'Created', 'success');
        host.dataset.loaded = ''; loadAdminSponsors();
      } catch (err) { App.toast(err?.detail || 'Save failed', 'error'); }
    });
  }


  // ─── Renewals Pane ─────────────────────────────────────────────────────────

  let _renewalFilter = { status: 'pending', q: '' };

  function loadRenewals() {
    const host = document.querySelector('[data-renewals-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    renderRenewalsPane(host);
    fetchRenewals(host);
  }

  function renderRenewalsPane(host) {
    host.innerHTML = `
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat me-2"></i>Domain Renewals</h6>
          <form class="row g-2 mb-3" data-renewals-filter>
            <div class="col-auto">
              <select class="form-select form-select-sm" data-ren-status>
                <option value="pending" selected>Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="any">All</option>
              </select>
            </div>
            <div class="col">
              <input type="text" class="form-control form-control-sm" data-ren-q placeholder="Search domain, owner...">
            </div>
          </form>
          <div data-renewals-table></div>
        </div>
      </div>`;

    const statusSel = host.querySelector('[data-ren-status]');
    const qInput = host.querySelector('[data-ren-q]');
    statusSel.addEventListener('change', () => { _renewalFilter.status = statusSel.value; fetchRenewals(host); });
    const debouncedSearch = App.debounce(() => { _renewalFilter.q = (qInput.value || '').trim(); fetchRenewals(host); }, 300);
    qInput.addEventListener('input', debouncedSearch);
    host.querySelector('[data-renewals-filter]').addEventListener('submit', e => e.preventDefault());
  }


  async function fetchRenewals(host) {
    const tbody = host.querySelector('[data-renewals-table]');
    if (!tbody) return;
    tbody.innerHTML = '<div class="skeleton-v5" style="height:80px;"></div>';

    try {
      const r = await App.api('/admin/renewals' + App.qs({ status: _renewalFilter.status, q: _renewalFilter.q }));
      const items = r.items || [];

      // Update sidebar badge for pending count
      if (_renewalFilter.status === 'pending' || _renewalFilter.status === 'any') {
        const badge = document.querySelector('[data-nav-renewals-pending]');
        const pendingCount = _renewalFilter.status === 'pending' ? items.length : items.filter(i => i.status === 'pending').length;
        if (badge) { badge.textContent = pendingCount; badge.hidden = pendingCount === 0; }
      }

      if (!items.length) {
        tbody.innerHTML = '<p class="text-muted small text-center py-3"><i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>No renewals match this filter.</p>';
        return;
      }

      const statusBadgeRen = (s) => {
        const map = { pending: 'bg-warning-subtle text-warning', approved: 'bg-success-subtle text-success', rejected: 'bg-danger-subtle text-danger' };
        return `<span class="badge ${map[s] || 'bg-secondary-subtle text-secondary'}">${App.escapeHtml(s)}</span>`;
      };

      tbody.innerHTML = `
        <div class="table-responsive">
          <table class="table table-sm align-middle dash-table-v5">
            <thead><tr><th>Domain</th><th>Owner</th><th>Price</th><th>Term</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>${items.map(i => `
              <tr>
                <td><code>${App.escapeHtml(i.institution?.subdomain || '—')}</code></td>
                <td class="small">${App.escapeHtml(i.owner?.name || i.owner?.email || '—')}</td>
                <td class="small">${i.amount_bdt != null ? App.escapeHtml(String(i.amount_bdt)) + ' BDT' : '—'}</td>
                <td class="small">${i.term_days || '—'} days</td>
                <td>${statusBadgeRen(i.status)}</td>
                <td class="small text-muted">${App.escapeHtml(App.fmtDate(i.created_at))}</td>
                <td class="text-end">${i.status === 'pending' ? `
                  <button class="btn btn-sm btn-success me-1" data-ren-decide="${i.id}" data-decision="approve"><i class="bi bi-check-lg"></i></button>
                  <button class="btn btn-sm btn-danger" data-ren-decide="${i.id}" data-decision="reject"><i class="bi bi-x-lg"></i></button>
                ` : ''}</td>
              </tr>`).join('')}</tbody>
          </table>
        </div>`;


      // Wire approve/reject buttons
      tbody.querySelectorAll('[data-ren-decide]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = btn.getAttribute('data-ren-decide');
          const decision = btn.getAttribute('data-decision');
          let note = '';
          if (decision === 'reject') {
            note = (prompt('Rejection note (optional):') || '').trim();
          }
          if (!confirm(`${decision === 'approve' ? 'Approve' : 'Reject'} this renewal?`)) return;
          btn.disabled = true;
          try {
            await App.api(`/admin/renewals/${id}/decide`, { method: 'POST', body: { decision, note } });
            App.toast(`Renewal ${decision}d`, 'success');
            fetchRenewals(host);
          } catch (err) {
            App.toast(err?.detail || 'Action failed', 'error');
            btn.disabled = false;
          }
        });
      });
    } catch (e) {
      tbody.innerHTML = `<div class="alert alert-danger small">${App.escapeHtml(e?.detail || 'Failed to load renewals')}</div>`;
    }
  }

})();
