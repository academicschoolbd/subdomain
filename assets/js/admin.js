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

  // ─── Placeholder loaders for dynamic panes ─────────────────────────────────

  function loadSettings() {
    const host = document.querySelector('[data-settings-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted">Platform settings panel loads dynamically from API...</p></div></div>';
  }

  function loadIntegrations() {
    const host = document.querySelector('[data-integrations-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted">Integrations panel (OAuth keys, Cloudflare config) loads from API...</p></div></div>';
  }

  function loadAdminPayments() {
    const host = document.querySelector('[data-payments-admin-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted">Payment methods configuration panel...</p></div></div>';
  }

  function loadRenewals() {
    const host = document.querySelector('[data-renewals-host]');
    if (!host || host.dataset.loaded) return;
    host.dataset.loaded = '1';
    host.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted">Renewals management panel loads from API...</p></div></div>';
  }

})();
