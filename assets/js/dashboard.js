/* ===========================================================================
   institution.bd — v5pro Owner Dashboard
   Modern ES6+, Bootstrap 5.3 integration.
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  const needAuth = document.querySelector('[data-needs-auth]');
  const dashRoot = document.querySelector('[data-dash-root]');
  const manageBody = document.querySelector('[data-manage-body]');
  let manageModalInstance = null;

  let _claims = [];
  let _filter = { q: '', status: 'any' };

  // ─── Boot ────────────────────────────────────────────────────────────────

  if (!App.isAuthed()) {
    if (needAuth) needAuth.hidden = false;
    return;
  }

  App.api('/auth/me').then(r => {
    App.setSession(null, r.user);
    if (dashRoot) dashRoot.hidden = false;
    renderBanner();
    renderAccountCard();
    wirePaneSwitcher();
    wireFilters();
    wireManageModal();
    loadDomains();
  }).catch(e => {
    if (e?.status === 401) {
      App.clearSession();
      if (needAuth) needAuth.hidden = false;
    } else {
      App.toast(e?.detail || 'Could not load account', 'error');
    }
  });

  // ─── Banner ──────────────────────────────────────────────────────────────

  async function renderBanner() {
    let s;
    try { s = await App.getSettings(); } catch { return; }
    const wa = s?.whatsapp || {};
    const site = s?.site || {};
    const titleEl = document.querySelector('[data-banner-title]');
    const subEl = document.querySelector('[data-banner-sub]');
    if (titleEl) titleEl.textContent = `Stay Connected with ${site.name || 'institution.bd'}`;
    if (subEl) subEl.textContent = wa.community_subtitle || 'Join our community for support, tips & updates.';

    document.querySelectorAll('[data-banner-community]').forEach(el => {
      if (wa.community_url) el.href = wa.community_url;
      else if (wa.support_url) el.href = wa.support_url;
    });
    document.querySelectorAll('[data-banner-like], [data-banner-creator]').forEach(el => {
      el.href = wa.support_url || wa.community_url || '#';
    });
    const credText = document.querySelector('[data-banner-creator-text]');
    if (credText) credText.textContent = site.creator_name ? `Follow: ${site.creator_name}` : 'Follow Creator';
  }


  // ─── Pane Switcher ─────────────────────────────────────────────────────────

  function wirePaneSwitcher() {
    document.querySelectorAll('[data-pane-btn]').forEach(btn => {
      btn.addEventListener('click', () => activatePane(btn.getAttribute('data-pane-btn')));
    });
    document.querySelectorAll('[data-signout]').forEach(btn => {
      btn.addEventListener('click', e => { e.preventDefault(); App.clearSession(); location.href = '/'; });
    });
  }

  function activatePane(name) {
    document.querySelectorAll('[data-pane-btn]').forEach(b => {
      b.classList.toggle('active', b.getAttribute('data-pane-btn') === name);
    });
    document.querySelectorAll('[data-pane]').forEach(p => {
      p.hidden = p.getAttribute('data-pane') !== name;
    });
    if (name === 'settings') renderAccountCard();
    if (name === 'support') renderSupportPayments();
  }

  // ─── Quick Tiles ───────────────────────────────────────────────────────────

  function renderQuickTiles() {
    const host = document.querySelector('[data-quick-host]');
    if (!host) return;
    const total = _claims.length;
    const verified = _claims.filter(c => c.status === 'verified').length;
    const pending = _claims.filter(c => c.status === 'pending').length;
    const actionable = _claims.filter(c => ['needs_info','rejected','suspended'].includes(c.status) || (c.status==='verified' && c.dns_status==='error')).length;

    host.innerHTML = `
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-quick-filter="any">
          <div class="tile-icon"><i class="bi bi-globe2"></i></div>
          <div class="tile-num">${total}</div>
          <div class="tile-label">Total Domains</div>
          <div class="tile-hint">Across both brands</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-quick-filter="verified">
          <div class="tile-icon" style="background:rgba(22,163,74,.1);color:#15803d;"><i class="bi bi-check-circle-fill"></i></div>
          <div class="tile-num">${verified}</div>
          <div class="tile-label">Verified & Live</div>
          <div class="tile-hint">${verified ? 'SSL on, DNS published' : 'Awaiting approval'}</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-quick-filter="pending">
          <div class="tile-icon" style="background:rgba(37,99,235,.1);color:#2563eb;"><i class="bi bi-hourglass-split"></i></div>
          <div class="tile-num">${pending}</div>
          <div class="tile-label">Pending Review</div>
          <div class="tile-hint">${pending ? 'Usually < 24h' : 'All caught up'}</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="quick-tile-v5" data-quick-filter="needs_info">
          <div class="tile-icon" style="background:rgba(220,38,38,.1);color:#dc2626;"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div class="tile-num">${actionable}</div>
          <div class="tile-label">Action Needed</div>
          <div class="tile-hint">${actionable ? 'Tap to review' : 'Nothing to fix'}</div>
        </div>
      </div>`;

    host.querySelectorAll('[data-quick-filter]').forEach(tile => {
      tile.addEventListener('click', () => {
        const f = tile.getAttribute('data-quick-filter');
        const sel = document.querySelector('[data-domains-status]');
        if (sel) sel.value = f;
        _filter.status = f;
        renderDomainsTable();
      });
    });
  }


  // ─── Domains Table ─────────────────────────────────────────────────────────

  function wireFilters() {
    const form = document.querySelector('[data-domains-form]');
    const qEl = document.querySelector('[data-domains-q]');
    const sEl = document.querySelector('[data-domains-status]');
    if (form) form.addEventListener('submit', e => e.preventDefault());
    if (qEl) {
      const d = App.debounce(() => { _filter.q = (qEl.value || '').toLowerCase().trim(); renderDomainsTable(); }, 150);
      qEl.addEventListener('input', d);
    }
    if (sEl) sEl.addEventListener('change', () => { _filter.status = sEl.value; renderDomainsTable(); });
  }

  function loadDomains() {
    const tbody = document.querySelector('[data-domains-body]');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5"><div class="skeleton-v5" style="height:48px;"></div></td></tr>';
    App.api('/claims/mine').then(r => {
      _claims = r?.claims || [];
      const cnt = document.querySelector('[data-domain-count]');
      if (cnt) { cnt.textContent = _claims.length; cnt.hidden = _claims.length === 0; }
      renderQuickTiles();
      renderDomainsTable();
    }).catch(e => {
      if (e?.status === 401) { App.clearSession(); if (needAuth) needAuth.hidden = false; if (dashRoot) dashRoot.hidden = true; return; }
      if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="text-danger">${App.escapeHtml(e?.detail || 'Load failed')}</td></tr>`;
    });
  }

  function statusBadge(s) {
    const map = { verified:'badge-verified', pending:'badge-pending', needs_info:'badge-warning', rejected:'badge-danger', seeded:'badge-muted', suspended:'badge-danger' };
    return `<span class="badge rounded-pill ${map[s] || ''}">${App.escapeHtml(s)}</span>`;
  }

  function expiryCell(c) {
    if (c.status !== 'verified') return '<span class="text-muted">—</span>';
    if (!c.expires_at) return '<span class="text-muted small">No expiry</span>';
    const d = App.fmtDate(c.expires_at);
    const dt = c.days_to_expiry;
    if (dt == null) return App.escapeHtml(d);
    if (dt < 0) return `<span class="badge bg-danger-subtle text-danger">Expired ${-dt}d ago</span>`;
    if (dt <= 30) return `<span class="badge bg-warning-subtle text-warning">In ${dt}d</span>`;
    return `<span class="text-muted small">${App.escapeHtml(d)}</span>`;
  }

  function renderDomainsTable() {
    const tbody = document.querySelector('[data-domains-body]');
    if (!tbody) return;
    let rows = _claims.slice();
    if (_filter.status && _filter.status !== 'any') rows = rows.filter(c => c.status === _filter.status);
    if (_filter.q) rows = rows.filter(c => (c.subdomain||'').toLowerCase().includes(_filter.q) || (c.name_en||'').toLowerCase().includes(_filter.q));

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4">
        <i class="bi bi-globe2 fs-1 text-muted d-block mb-2"></i>
        <p class="text-muted mb-0">${_claims.length === 0 ? 'No domains yet. <a href="/claim.php">Register one now</a>' : 'No domains match this filter.'}</p>
      </td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map(c => `
      <tr>
        <td>
          <div class="dash-domain-cell">
            <span class="dash-domain-logo">${App.escapeHtml(App.initialsOf(c.name_en || c.slug))}</span>
            <div>
              <div class="dash-domain-name">${App.escapeHtml(c.subdomain || (c.slug+'.'+c.brand))}</div>
              <div class="dash-domain-meta">${App.escapeHtml(c.name_en || c.slug)}</div>
            </div>
          </div>
        </td>
        <td>${statusBadge(c.status)}</td>
        <td class="small text-muted">${App.escapeHtml(App.fmtDate(c.created_at))}</td>
        <td>${expiryCell(c)}</td>
        <td class="text-end">
          <div class="d-flex gap-1 justify-content-end flex-wrap">
            ${c.status==='verified' ? `<a class="btn btn-outline-primary btn-sm" target="_blank" href="https://${App.escapeHtml(c.subdomain)}"><i class="bi bi-box-arrow-up-right"></i></a>` : ''}
            <button class="btn btn-primary btn-sm" type="button" data-manage="${c.id}"><i class="bi bi-pencil-square me-1"></i>Manage</button>
          </div>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-manage]').forEach(b => {
      b.addEventListener('click', () => openManage(parseInt(b.getAttribute('data-manage'), 10)));
    });
  }


  // ─── Manage Modal ──────────────────────────────────────────────────────────

  function wireManageModal() {
    const el = document.getElementById('manageModal');
    if (el) manageModalInstance = new bootstrap.Modal(el);
  }

  function openManage(id) {
    const c = _claims.find(x => x.id === id);
    if (!c) { App.toast('Domain not found', 'error'); return; }
    if (!manageModalInstance) return;

    const isVerified = c.status === 'verified';
    manageBody.innerHTML = `
      <div class="mb-3">
        <h5 class="mb-1">${App.escapeHtml(c.name_en || c.subdomain)}</h5>
        <p class="text-muted small mb-2"><code>${App.escapeHtml(c.subdomain)}</code> ${statusBadge(c.status)} <span class="badge badge-brand">${App.escapeHtml(c.brand)}</span></p>
        ${c.review_notes ? `<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i><strong>Reviewer:</strong> ${App.escapeHtml(c.review_notes)}</div>` : ''}
      </div>
      <ul class="nav nav-tabs" role="tablist">
        ${isVerified ? '<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-dns">DNS</a></li>' : ''}
        <li class="nav-item"><a class="nav-link ${isVerified ? '' : 'active'}" data-bs-toggle="tab" href="#tab-profile">Profile</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-brand">Branding</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-docs">Documents</a></li>
      </ul>
      <div class="tab-content pt-3">
        ${isVerified ? `
        <div class="tab-pane fade show active" id="tab-dns">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge ${c.dns_status==='live' ? 'bg-success' : 'bg-warning'}">${App.escapeHtml(c.dns_status || 'pending')}</span>
            <a class="btn btn-sm btn-outline-primary" target="_blank" href="https://${App.escapeHtml(c.subdomain)}">Open site <i class="bi bi-box-arrow-up-right ms-1"></i></a>
          </div>
          <div data-dns-host="${c.id}"><p class="text-muted">DNS management panel loads here...</p></div>
        </div>` : ''}
        <div class="tab-pane fade ${isVerified ? '' : 'show active'}" id="tab-profile">
          <form data-form-profile="${c.id}">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">Name (English)</label><input class="form-control form-control-sm" name="name_en" value="${App.escapeHtml(c.name_en||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Name (Bengali)</label><input class="form-control form-control-sm" name="name_bn" value="${App.escapeHtml(c.name_bn||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Category</label><input class="form-control form-control-sm" name="category" value="${App.escapeHtml(c.category||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">EIIN</label><input class="form-control form-control-sm" name="eiin" value="${App.escapeHtml(c.eiin||'')}"></div>
              <div class="col-md-4"><label class="form-label small fw-semibold">Division</label><select class="form-select form-select-sm" name="division" data-bd-division></select></div>
              <div class="col-md-4"><label class="form-label small fw-semibold">District</label><select class="form-select form-select-sm" name="district" data-bd-district></select></div>
              <div class="col-md-4"><label class="form-label small fw-semibold">Upazila</label><select class="form-select form-select-sm" name="upazila" data-bd-upazila></select></div>
              <div class="col-12"><label class="form-label small fw-semibold">Address</label><input class="form-control form-control-sm" name="address" value="${App.escapeHtml(c.address||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Contact name</label><input class="form-control form-control-sm" name="contact_name" value="${App.escapeHtml(c.contact_name||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Contact phone</label><input class="form-control form-control-sm" name="contact_phone" value="${App.escapeHtml(c.contact_phone||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Contact email</label><input class="form-control form-control-sm" name="contact_email" value="${App.escapeHtml(c.contact_email||'')}"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Website</label><input class="form-control form-control-sm" name="website" value="${App.escapeHtml(c.website||'')}"></div>
              <div class="col-12"><label class="form-label small fw-semibold">About (English)</label><textarea class="form-control form-control-sm" name="about_en" rows="3">${App.escapeHtml(c.about_en||'')}</textarea></div>
              <div class="col-12"><label class="form-label small fw-semibold">About (Bengali)</label><textarea class="form-control form-control-sm" name="about_bn" rows="3">${App.escapeHtml(c.about_bn||'')}</textarea></div>
              <div class="col-12 text-end"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Save changes</button></div>
            </div>
          </form>
        </div>
        <div class="tab-pane fade" id="tab-brand">
          <p class="text-muted small">Upload your logo and banner for the public institution page.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Logo</label>
              ${c.logo_url ? `<img src="${App.escapeHtml(c.logo_url)}" class="rounded mb-2" style="max-height:80px;">` : '<div class="bg-body-secondary rounded p-3 text-muted text-center mb-2">No logo</div>'}
              <form data-form-image="${c.id}" data-kind="logo"><input type="file" class="form-control form-control-sm" name="file" accept="image/*"><button class="btn btn-sm btn-outline-primary mt-2" type="submit">Upload</button></form>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Banner</label>
              ${c.banner_url ? `<img src="${App.escapeHtml(c.banner_url)}" class="rounded mb-2" style="max-height:80px;width:100%;object-fit:cover;">` : '<div class="bg-body-secondary rounded p-3 text-muted text-center mb-2">No banner</div>'}
              <form data-form-image="${c.id}" data-kind="banner"><input type="file" class="form-control form-control-sm" name="file" accept="image/*"><button class="btn btn-sm btn-outline-primary mt-2" type="submit">Upload</button></form>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="tab-docs">
          <p class="text-muted small">Upload verification documents. Only admins can view these.</p>
          <form data-form-doc="${c.id}" class="d-flex gap-2 flex-wrap mb-3">
            <select class="form-select form-select-sm" name="doc_type" style="max-width:180px;">
              <option value="eiin_certificate">EIIN certificate</option>
              <option value="board_letter">Board letter</option>
              <option value="trade_license">Trade license</option>
              <option value="nid">Admin NID</option>
              <option value="other">Other</option>
            </select>
            <input type="file" class="form-control form-control-sm" name="file" accept="application/pdf,image/*" style="max-width:240px;">
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Upload</button>
          </form>
          <div data-docs="${c.id}"></div>
        </div>
      </div>`;

    // Wire profile save
    const pForm = manageBody.querySelector(`[data-form-profile="${c.id}"]`);
    if (pForm) {
      pForm.addEventListener('submit', async ev => {
        ev.preventDefault();
        const body = Object.fromEntries(new FormData(pForm).entries());
        try {
          await App.api(`/claims/${c.id}`, { method: 'PATCH', body });
          App.toast('Profile saved!', 'success');
          loadDomains();
        } catch (e) { App.toast(e?.detail || 'Save failed', 'error'); }
      });
    }

    // Wire image uploads
    manageBody.querySelectorAll('[data-form-image]').forEach(form => {
      form.addEventListener('submit', async ev => {
        ev.preventDefault();
        const kind = form.getAttribute('data-kind');
        const fd = new FormData(form);
        try {
          await App.api(`/claims/${c.id}/upload/${kind}`, { method: 'POST', body: fd });
          App.toast(`${kind} uploaded!`, 'success');
          loadDomains();
          openManage(c.id);
        } catch (e) { App.toast(e?.detail || 'Upload failed', 'error'); }
      });
    });

    // Wire doc upload
    const dForm = manageBody.querySelector(`[data-form-doc="${c.id}"]`);
    if (dForm) {
      dForm.addEventListener('submit', async ev => {
        ev.preventDefault();
        const fd = new FormData(dForm);
        try {
          await App.api(`/claims/${c.id}/documents`, { method: 'POST', body: fd });
          App.toast('Document uploaded!', 'success');
        } catch (e) { App.toast(e?.detail || 'Upload failed', 'error'); }
      });
    }

    manageModalInstance.show();
  }


  // ─── Account Card (Settings pane) ──────────────────────────────────────────

  function renderAccountCard() {
    const host = document.querySelector('[data-account-card]');
    if (!host || host.dataset.rendered) return;
    host.dataset.rendered = '1';
    const user = App.getUser();
    if (!user) return;
    host.innerHTML = `
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-4">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold" style="width:56px;height:56px;background:var(--c-primary);font-size:1.2rem;">
              ${user.avatar_url ? `<img src="${App.escapeHtml(user.avatar_url)}" class="rounded-circle" style="width:100%;height:100%;object-fit:cover;">` : App.escapeHtml((user.name||user.email||'?')[0].toUpperCase())}
            </span>
            <div>
              <h5 class="mb-0">${App.escapeHtml(user.name || 'User')}</h5>
              <small class="text-muted">${App.escapeHtml(user.email || '')}</small>
              ${user.is_admin ? '<span class="badge bg-primary-subtle text-primary ms-2">Admin</span>' : ''}
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Full Name</label>
              <input class="form-control form-control-sm" value="${App.escapeHtml(user.name||'')}" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email</label>
              <input class="form-control form-control-sm" value="${App.escapeHtml(user.email||'')}" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Mobile</label>
              <input class="form-control form-control-sm" value="${App.escapeHtml(user.mobile||'Not set')}" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Provider</label>
              <input class="form-control form-control-sm" value="${App.escapeHtml(user.provider||'email')}" disabled>
            </div>
          </div>
        </div>
      </div>`;
  }

  // ─── Support Payments ──────────────────────────────────────────────────────

  function renderSupportPayments() {
    const host = document.querySelector('[data-payments-host]');
    if (!host || host.dataset.rendered) return;
    host.dataset.rendered = '1';
    App.getSettings().then(s => {
      const methods = s?.payments?.methods || [];
      if (!methods.length) return;
      host.innerHTML = `
        <div class="card border-0 shadow-sm mt-3">
          <div class="card-header bg-transparent border-bottom py-3">
            <h6 class="mb-0"><i class="bi bi-credit-card me-2 text-primary"></i>Payment Methods</h6>
          </div>
          <div class="card-body">
            <div class="row g-3">
              ${methods.map(m => `
                <div class="col-md-6">
                  <div class="border rounded p-3">
                    <h6 class="mb-1">${App.escapeHtml(m.label || m.type)}</h6>
                    <p class="text-muted small mb-0">${App.escapeHtml(m.details || '')}</p>
                  </div>
                </div>`).join('')}
            </div>
          </div>
        </div>`;
    }).catch(() => {});
  }

})();
