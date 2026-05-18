/* ===========================================================================
   institution.bd — Owner dashboard
   v3.2 — sidebar shell, quick-access tiles, "My Domains" table, modal manager
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;

  /* Footer year. */
  const yEl = document.querySelector('[data-year]');
  if (yEl) yEl.textContent = new Date().getFullYear();

  const needAuth   = document.querySelector('[data-needs-auth]');
  const dashRoot   = document.querySelector('[data-dash-root]');
  const manageBg   = document.querySelector('[data-manage-modal]');
  const manageBody = document.querySelector('[data-manage-body]');

  /* The user must be signed in to even see the dashboard shell. */
  if (!App.isAuthed()) {
    if (needAuth) needAuth.hidden = false;
    return;
  }

  /* In-memory cache of /claims/mine so the table can re-render without a
     network round-trip every time the search box changes. */
  let _claims = [];
  let _filter = { q: '', status: 'any' };

  /* ---------------------------------------------------------------- */
  /*  Boot — verify session, load user/settings, render UI             */
  /* ---------------------------------------------------------------- */
  App.api('/auth/me').then((r) => {
    App.setSession(null, r.user);
    if (dashRoot) dashRoot.hidden = false;
    renderBanner();
    renderAccountCard();
    wirePaneSwitcher();
    wireFilters();
    wireManageModal();
    loadDomains();
  }).catch((e) => {
    if (e && e.status === 401) {
      App.clearSession();
      if (needAuth) needAuth.hidden = false;
      if (dashRoot) dashRoot.hidden = true;
    } else {
      App.toast((e && e.detail) || 'Could not load your account', 'error');
    }
  });

  /* ---------------------------------------------------------------- */
  /*  Banner — Stay Connected (community + page-like + creator follow) */
  /* ---------------------------------------------------------------- */
  async function renderBanner() {
    const titleEl   = document.querySelector('[data-banner-title]');
    const subEl     = document.querySelector('[data-banner-sub]');
    const commLink  = document.querySelector('[data-banner-community]');
    const likeLink  = document.querySelector('[data-banner-like]');
    const credLink  = document.querySelector('[data-banner-creator]');
    const credText  = document.querySelector('[data-banner-creator-text]');

    let s = null;
    try { s = await App.getSettings(); } catch (e) { return; }

    const wa  = (s && s.whatsapp) || {};
    const site = (s && s.site) || {};
    const brand = site.name || 'institution.bd';
    if (titleEl) titleEl.textContent = `Stay Connected with ${brand}`;
    if (subEl)   subEl.textContent   = wa.community_subtitle || 'Join our community for support, tips & updates. Like our page to never miss new features!';

    // Community URL — falls back to disabling the button if no link is set
    // (admin can configure it from /admin → Integrations).
    document.querySelectorAll('[data-banner-community]').forEach((el) => {
      if (wa.community_url) {
        el.setAttribute('href', wa.community_url);
        el.classList.remove('is-disabled');
      } else if (wa.support_url) {
        el.setAttribute('href', wa.support_url);
      } else {
        el.setAttribute('href', '#');
        el.classList.add('is-disabled');
        el.addEventListener('click', (e) => {
          e.preventDefault();
          App.toast('Community link not configured yet. Ask the admin to set it.', 'error');
        });
      }
    });

    // "Like our page" + "Follow creator" — point at the WhatsApp support
    // chat as a sensible default; admins can later expose dedicated FB
    // links via the integrations panel if they want.
    document.querySelectorAll('[data-banner-like], [data-banner-creator]').forEach((el) => {
      const url = wa.support_url || wa.community_url || '#';
      el.setAttribute('href', url);
      if (url === '#') {
        el.classList.add('is-disabled');
        el.addEventListener('click', (e) => { e.preventDefault(); });
      }
    });

    // The original screenshot says "Follow Creator: Aminul" — keep the same
    // pattern, but only render a name if one is configured. Otherwise we
    // just show "Follow Creator".
    const creator = (site && site.creator_name) || '';
    if (credText) {
      credText.textContent = creator ? ('Follow Creator: ' + creator) : 'Follow Creator';
    }
    if (credLink && !credLink.getAttribute('href')) credLink.setAttribute('href', '#');
  }

  /* ---------------------------------------------------------------- */
  /*  Sidebar pane switcher                                            */
  /* ---------------------------------------------------------------- */
  function wirePaneSwitcher() {
    const buttons = document.querySelectorAll('[data-pane-btn]');
    const panes   = document.querySelectorAll('[data-pane]');
    buttons.forEach((b) => {
      b.addEventListener('click', () => {
        const name = b.getAttribute('data-pane-btn');
        buttons.forEach((x) => x.classList.toggle('active', x === b));
        panes.forEach((p) => { p.hidden = p.getAttribute('data-pane') !== name; });
        // The Settings pane mounts the account-card the first time it's
        // opened so we don't waste a render on the Overview path.
        if (name === 'settings') renderAccountCard();
        if (name === 'support')  renderSupportPayments();
      });
    });

    // Sign-out button in the sidebar foot.
    document.querySelectorAll('[data-signout]').forEach((b) => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        App.clearSession();
        location.href = '/';
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Quick-access tiles (Overview)                                    */
  /* ---------------------------------------------------------------- */
  function renderQuickTiles() {
    const host = document.querySelector('[data-quick-host]');
    if (!host) return;
    const total      = _claims.length;
    const verified   = _claims.filter((c) => c.status === 'verified').length;
    const pending    = _claims.filter((c) => c.status === 'pending').length;
    const actionable = _claims.filter((c) => c.status === 'needs_info' || c.status === 'rejected' || c.status === 'suspended' || (c.status === 'verified' && c.dns_status === 'error')).length;

    const tile = (kind, ico, num, label, hint, filter) => `
      <button class="quick-tile quick-tile--${kind}" type="button" data-quick-filter="${filter || ''}">
        <span class="quick-tile__ico" aria-hidden="true">${ico}</span>
        <span class="quick-tile__num">${num}</span>
        <span class="quick-tile__lab">${label}</span>
        ${hint ? `<span class="quick-tile__hint">${hint}</span>` : ''}
      </button>`;

    host.innerHTML =
      tile('total',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
        total, 'Total domains', 'Across both brands', 'any')
      + tile('verified',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        verified, 'Verified & live', verified ? 'SSL on, DNS published' : 'Awaiting first approval', 'verified')
      + tile('pending',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        pending, 'Pending review', pending ? 'Usually < 24 h' : 'You\'re all caught up', 'pending')
      + tile('action',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        actionable, 'Action needed', actionable ? 'Tap to review' : 'Nothing for you to fix', 'action');

    host.querySelectorAll('[data-quick-filter]').forEach((b) => {
      b.addEventListener('click', () => {
        const f = b.getAttribute('data-quick-filter') || 'any';
        const sel = document.querySelector('[data-domains-status]');
        // "action" is a synthetic filter — map it onto needs_info.
        const target = (f === 'action') ? 'needs_info' : f;
        if (sel) sel.value = target;
        _filter.status = target;
        renderDomainsTable();
        // Smooth-scroll to the table for clarity.
        document.querySelector('[data-domains-table]')
          ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Domains table                                                    */
  /* ---------------------------------------------------------------- */
  function wireFilters() {
    const form  = document.querySelector('[data-domains-form]');
    const qEl   = document.querySelector('[data-domains-q]');
    const sEl   = document.querySelector('[data-domains-status]');
    if (!form) return;
    form.addEventListener('submit', (e) => e.preventDefault());
    if (qEl) {
      const debounced = App.debounce(() => {
        _filter.q = (qEl.value || '').toLowerCase().trim();
        renderDomainsTable();
      }, 120);
      qEl.addEventListener('input', debounced);
    }
    if (sEl) {
      sEl.addEventListener('change', () => {
        _filter.status = sEl.value;
        renderDomainsTable();
      });
    }
  }

  function loadDomains() {
    const tbody = document.querySelector('[data-domains-body]');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5"><div class="skeleton" style="height:48px;"></div></td></tr>';
    App.api('/claims/mine').then((r) => {
      _claims = (r && r.claims) || [];
      const cnt = document.querySelector('[data-domain-count]');
      if (cnt) {
        cnt.textContent = _claims.length;
        cnt.hidden = _claims.length === 0;
      }
      renderQuickTiles();
      renderDomainsTable();
    }).catch((e) => {
      if (e && e.status === 401) {
        App.clearSession();
        if (needAuth) needAuth.hidden = false;
        if (dashRoot) dashRoot.hidden = true;
        return;
      }
      if (tbody) tbody.innerHTML =
        '<tr><td colspan="5" class="text-danger">' +
        App.escapeHtml((e && e.detail) || 'Could not load') +
        '</td></tr>';
    });
  }

  function statusPill(s) {
    const map = {
      verified:  ['Verified', 'badge--verified'],
      pending:   ['Pending',  'badge--pending'],
      needs_info:['Needs info','badge--warning'],
      rejected:  ['Rejected', 'badge--danger'],
      seeded:    ['Seeded',   'badge--muted'],
      suspended: ['Suspended','badge--danger'],
    };
    const [label, cls] = map[s] || [s, ''];
    return `<span class="badge ${cls}">${App.escapeHtml(label)}</span>`;
  }

  function renderDomainsTable() {
    const tbody = document.querySelector('[data-domains-body]');
    if (!tbody) return;

    let rows = _claims.slice();
    if (_filter.status && _filter.status !== 'any') {
      rows = rows.filter((c) => c.status === _filter.status);
    }
    if (_filter.q) {
      rows = rows.filter((c) =>
        (c.subdomain || '').toLowerCase().includes(_filter.q) ||
        (c.name_en   || '').toLowerCase().includes(_filter.q) ||
        (c.slug      || '').toLowerCase().includes(_filter.q)
      );
    }

    if (!rows.length) {
      tbody.innerHTML = `
        <tr><td colspan="5">
          <div class="dash-empty">
            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <p>${_claims.length === 0
                ? 'No domains found. <a href="/claim.php" class="link">Register one now</a>'
                : 'No domains match this filter.'}</p>
          </div>
        </td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map((c) => {
      const expires = c.verified_at
        ? '<span class="text-muted">Free · no expiry</span>'
        : '<span class="text-muted">—</span>';
      return `
        <tr data-row="${c.id}">
          <td>
            <div class="dash-domain">
              <span class="dash-domain__logo">${App.escapeHtml(App.initialsOf(c.name_en || c.slug))}</span>
              <div>
                <div class="dash-domain__name">${App.escapeHtml(c.subdomain || (c.slug + '.' + c.brand))}</div>
                <div class="dash-domain__meta">${App.escapeHtml(c.name_en || c.slug)}${c.dns_status ? ' · DNS · ' + App.escapeHtml(c.dns_status) : ''}</div>
              </div>
            </div>
          </td>
          <td>${statusPill(c.status)}</td>
          <td>${App.escapeHtml(App.fmtDate(c.created_at))}</td>
          <td>${expires}</td>
          <td class="text-right">
            <div class="dash-row-actions">
              ${c.status === 'verified'
                ? `<a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" href="https://${App.escapeHtml(c.subdomain)}">Open</a>`
                : ''}
              <button class="btn btn--sm" type="button" data-manage="${c.id}">Manage</button>
            </div>
          </td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-manage]').forEach((b) => {
      b.addEventListener('click', () => openManage(parseInt(b.getAttribute('data-manage'), 10)));
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Manage modal — full edit/upload/notice forms                     */
  /* ---------------------------------------------------------------- */
  function wireManageModal() {
    const close = document.querySelector('[data-manage-close]');
    if (close) close.addEventListener('click', closeManage);
    if (manageBg) manageBg.addEventListener('click', (e) => {
      if (e.target === manageBg) closeManage();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && manageBg && manageBg.classList.contains('open')) closeManage();
    });
  }
  function closeManage() {
    if (manageBg) manageBg.classList.remove('open');
  }

  function imgBlock(url, label) {
    if (!url) return `<div class="upload-thumb upload-thumb--empty">${App.escapeHtml(label)}</div>`;
    return `<img src="${App.escapeHtml(url)}" alt="" class="upload-thumb" />`;
  }

  function dnsPanel(c) {
    if (c.status !== 'verified') return '';
    const fqdn = c.subdomain;
    const livePill = c.dns_status === 'live'
      ? '<span class="badge badge--verified">Live · auto-SSL</span>'
      : (c.dns_status === 'manual'
          ? '<span class="badge">Manual DNS</span>'
          : (c.dns_status === 'error'
              ? '<span class="badge badge--danger">DNS error</span>'
              : '<span class="badge">Pending</span>'));
    return `
      <section class="dns-panel">
        <header class="flex-between" style="flex-wrap:wrap;gap:10px;">
          <div>
            <h4 style="margin:0;">DNS &amp; Cloudflare</h4>
            <p class="text-muted" style="margin:.2em 0;">Where requests for <code>${App.escapeHtml(fqdn)}</code> currently go.</p>
          </div>
          ${livePill}
        </header>
        ${c.dns_message ? `<p class="text-muted" style="margin:.4em 0;font-size:.88rem;">${App.escapeHtml(c.dns_message)}</p>` : ''}
        <div class="flex" style="gap:8px;flex-wrap:wrap;margin-top:10px;">
          <a class="btn btn--sm" target="_blank" rel="noopener" href="https://${App.escapeHtml(fqdn)}">Open ${App.escapeHtml(fqdn)} →</a>
          <button class="btn btn--sm" data-dns-retry="${c.id}" type="button">Retry DNS</button>
        </div>
      </section>`;
  }

  function openManage(id) {
    const c = _claims.find((x) => x.id === id);
    if (!c) { App.toast('Could not find that domain', 'error'); return; }
    manageBg.classList.add('open');
    manageBody.innerHTML = `
      <header class="manage-head">
        <div>
          <h2 style="margin:0;">${App.escapeHtml(c.name_en || c.subdomain)}</h2>
          <p class="text-muted" style="margin:.2em 0;"><code>${App.escapeHtml(c.subdomain)}</code></p>
          <div class="badges">${statusPill(c.status)} <span class="badge badge--brand">${App.escapeHtml(c.brand)}</span></div>
        </div>
      </header>

      ${c.review_notes ? `<p class="text-muted mt-3"><strong>Reviewer note:</strong> ${App.escapeHtml(c.review_notes)}</p>` : ''}

      ${dnsPanel(c)}

      <div class="grid-2 mt-3">
        <div>
          <h4>Profile</h4>
          <form data-form-profile="${c.id}" class="form-grid">
            <div class="field"><label class="label">Name (English)</label><input name="name_en" value="${App.escapeHtml(c.name_en || '')}" /></div>
            <div class="field"><label class="label">নাম (বাংলা)</label><input name="name_bn" value="${App.escapeHtml(c.name_bn || '')}" /></div>
            <div class="field"><label class="label">Category</label><input name="category" value="${App.escapeHtml(c.category || '')}" /></div>
            <div class="field"><label class="label">EIIN</label><input name="eiin" value="${App.escapeHtml(c.eiin || '')}" /></div>
            <div class="field"><label class="label">Division</label>
              <select name="division" data-bd-division>
                <option value="">— Select division —</option>
              </select></div>
            <div class="field"><label class="label">District</label>
              <select name="district" data-bd-district>
                <option value="">— Select division first —</option>
              </select></div>
            <div class="field"><label class="label">Upazila</label>
              <select name="upazila" data-bd-upazila>
                <option value="">— Select district first —</option>
              </select></div>
            <div class="field field--wide"><label class="label">Address</label><input name="address" value="${App.escapeHtml(c.address || '')}" /></div>
            <div class="field"><label class="label">Contact name</label><input name="contact_name" value="${App.escapeHtml(c.contact_name || '')}" /></div>
            <div class="field"><label class="label">Contact phone</label><input name="contact_phone" value="${App.escapeHtml(c.contact_phone || '')}" /></div>
            <div class="field"><label class="label">Contact email</label><input name="contact_email" value="${App.escapeHtml(c.contact_email || '')}" /></div>
            <div class="field"><label class="label">Website</label><input name="website" value="${App.escapeHtml(c.website || '')}" /></div>
            <div class="field field--wide"><label class="label">About (English)</label><textarea name="about_en" rows="3">${App.escapeHtml(c.about_en || '')}</textarea></div>
            <div class="field field--wide"><label class="label">পরিচিতি (বাংলা)</label><textarea name="about_bn" rows="3">${App.escapeHtml(c.about_bn || '')}</textarea></div>
            <div class="field field--wide text-right"><button class="btn btn--primary" type="submit">Save changes</button></div>
          </form>
        </div>
        <div>
          <h4>Images</h4>
          <p class="label">Logo</p>
          ${imgBlock(c.logo_url, 'No logo yet')}
          <form data-form-image="${c.id}" data-kind="logo"><input type="file" name="file" accept="image/jpeg,image/png,image/webp" /><button class="btn btn--sm mt-2" type="submit">Upload logo</button></form>
          <p class="label mt-3">Banner</p>
          ${imgBlock(c.banner_url, 'No banner yet')}
          <form data-form-image="${c.id}" data-kind="banner"><input type="file" name="file" accept="image/jpeg,image/png,image/webp" /><button class="btn btn--sm mt-2" type="submit">Upload banner</button></form>

          <h4 class="mt-4">Documents</h4>
          <form data-form-doc="${c.id}">
            <select name="doc_type">
              <option value="eiin_certificate">EIIN certificate</option>
              <option value="board_letter">Board letter</option>
              <option value="trade_license">Trade license</option>
              <option value="nid">Admin NID</option>
              <option value="other">Other</option>
            </select>
            <input type="file" name="file" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic" class="mt-2" />
            <button class="btn btn--sm mt-2" type="submit">Upload document</button>
          </form>
          <div data-docs="${c.id}" class="docs-list mt-2"></div>
        </div>
      </div>

      ${c.status === 'verified' ? `
      <div class="mt-4">
        <h4>Notices</h4>
        <form data-form-notice="${c.id}" class="form-grid">
          <div class="field field--wide"><label class="label">Title</label><input name="title" required /></div>
          <div class="field field--wide"><label class="label">Body</label><textarea name="body" rows="3" required></textarea></div>
          <div class="field field--wide flex-between">
            <label><input type="checkbox" name="pinned" /> Pin to top</label>
            <button class="btn btn--primary" type="submit">Publish notice</button>
          </div>
        </form>
        <div data-notices="${c.id}" class="mt-3"></div>
      </div>` : ''}

      ${c.status === 'verified' ? `
      <div class="mt-4">
        <h4>DNS records</h4>
        <div data-dns-host="${c.id}"></div>
      </div>` : ''}

      <footer class="manage-foot mt-4">
        ${['pending','needs_info','rejected'].includes(c.status)
          ? `<button class="btn btn--ghost btn--danger-text" data-withdraw="${c.id}" type="button">Withdraw claim</button>`
          : '<span></span>'}
        <button class="btn" type="button" data-manage-close-2>Close</button>
      </footer>
    `;
    wireManageInternals(c.id);
    loadDocs(c.id);
    if (c.status === 'verified') loadNotices(c.id);
    if (c.status === 'verified') loadDnsRecords(c.id);
    const x = manageBody.querySelector('[data-manage-close-2]');
    if (x) x.addEventListener('click', closeManage);

    // BD-locations cascade for the per-claim profile form inside the modal.
    if (window.BDLocations) {
      const root = manageBody.querySelector('[data-form-profile]');
      if (root) {
        window.BDLocations.bind(
          root.querySelector('[data-bd-division]'),
          root.querySelector('[data-bd-district]'),
          root.querySelector('[data-bd-upazila]'),
          { division: c.division || '', district: c.district || '', upazila: c.upazila || '' }
        );
      }
    }
  }

  function wireManageInternals(id) {
    const root = manageBody;

    root.querySelectorAll('[data-form-profile]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(f);
        const body = {};
        fd.forEach((v, k) => { body[k] = v; });
        try {
          await App.api('/tenant/' + id + '/site', { method: 'PATCH', body });
          App.toast('Profile saved', 'success');
          loadDomains(); // refresh card meta
        } catch (e2) { App.toast(e2.detail || 'Save failed', 'error'); }
      });
    });

    root.querySelectorAll('[data-form-image]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const kind = f.getAttribute('data-kind');
        const fd = new FormData(f);
        if (!fd.get('file') || fd.get('file').size === 0) { App.toast('Pick a file', 'error'); return; }
        fd.append('kind', kind);
        try {
          await App.api('/tenant/' + id + '/upload-image', { method: 'POST', body: fd });
          App.toast(kind + ' updated', 'success');
          await reloadAndReopen(id);
        } catch (e2) { App.toast(e2.detail || 'Upload failed', 'error'); }
      });
    });

    root.querySelectorAll('[data-form-doc]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(f);
        if (!fd.get('file') || fd.get('file').size === 0) { App.toast('Pick a file', 'error'); return; }
        try {
          await App.api('/claims/' + id + '/documents', { method: 'POST', body: fd });
          App.toast('Document uploaded', 'success');
          f.reset();
          loadDocs(id);
        } catch (e2) { App.toast(e2.detail || 'Upload failed', 'error'); }
      });
    });

    root.querySelectorAll('[data-form-notice]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(f);
        const body = { title: fd.get('title'), body: fd.get('body'), pinned: !!fd.get('pinned') };
        try {
          await App.api('/tenant/' + id + '/notices', { method: 'POST', body });
          App.toast('Notice published', 'success');
          f.reset();
          loadNotices(id);
        } catch (e2) { App.toast(e2.detail || 'Publish failed', 'error'); }
      });
    });

    root.querySelectorAll('[data-withdraw]').forEach((b) => {
      b.addEventListener('click', async () => {
        if (!confirm('Permanently withdraw this claim? Uploaded documents will be deleted. This cannot be undone.')) return;
        try {
          await App.api('/claims/' + id, { method: 'DELETE' });
          App.toast('Claim withdrawn', 'success');
          closeManage();
          loadDomains();
        } catch (e2) { App.toast(e2.detail || 'Withdraw failed', 'error'); }
      });
    });

    root.querySelectorAll('[data-dns-retry]').forEach((b) => {
      b.addEventListener('click', async () => {
        b.disabled = true;
        const orig = b.textContent;
        b.textContent = 'Retrying…';
        try {
          const r = await App.api('/admin/claims/' + id + '/dns-retry', { method: 'POST' });
          App.toast(r.ok ? 'DNS retry submitted' : (r.dns && r.dns.message) || 'DNS error',
                    r.ok ? 'success' : 'error');
          await reloadAndReopen(id);
        } catch (e2) {
          App.toast(e2.status === 403
            ? 'Only admins can retry DNS — please contact support.'
            : (e2.detail || 'Retry failed'), 'error');
        } finally {
          b.disabled = false; b.textContent = orig;
        }
      });
    });
  }

  /* Re-fetch the user's claims and re-open the same modal so the latest
     image/profile/DNS data shows up immediately after a save. */
  async function reloadAndReopen(id) {
    try {
      const r = await App.api('/claims/mine');
      _claims = (r && r.claims) || [];
      renderQuickTiles();
      renderDomainsTable();
      const stillThere = _claims.find((c) => c.id === id);
      if (stillThere) openManage(id);
    } catch (e) { /* keep modal as-is */ }
  }

  function loadDocs(id) {
    const host = manageBody.querySelector('[data-docs="' + id + '"]');
    if (!host) return;
    host.innerHTML = '<div class="skeleton" style="height:30px;"></div>';
    App.api('/tenant/' + id + '/site').then((r) => {
      const docs = r.documents || [];
      if (!docs.length) { host.innerHTML = '<p class="text-muted" style="font-size:.9rem;">No documents uploaded yet.</p>'; return; }
      host.innerHTML = docs.map((d) =>
        '<div class="doc-row"><span>📎 ' + App.escapeHtml(d.doc_type) + ' · ' + App.escapeHtml(d.filename) +
        '</span><span class="text-muted">' + App.escapeHtml(App.fmtDate(d.uploaded_at)) + '</span></div>'
      ).join('');
    });
  }

  function loadNotices(id) {
    const host = manageBody.querySelector('[data-notices="' + id + '"]');
    if (!host) return;
    host.innerHTML = '<div class="skeleton" style="height:30px;"></div>';
    App.api('/tenant/' + id + '/notices').then((r) => {
      const list = r.notices || [];
      if (!list.length) { host.innerHTML = '<p class="text-muted">No notices yet.</p>'; return; }
      host.innerHTML = list.map((n) => `
        <div class="notice-row">
          <div>${n.pinned ? '<span class="badge badge--brand">Pinned</span> ' : ''}<strong>${App.escapeHtml(n.title)}</strong>
            <p style="margin:.3em 0;white-space:pre-wrap;">${App.escapeHtml(n.body)}</p>
            <small class="text-muted">${App.escapeHtml(App.fmtDate(n.created_at))}</small>
          </div>
          <button class="btn btn--ghost btn--sm" data-del-notice="${id}:${n.id}">Delete</button>
        </div>`).join('');
      host.querySelectorAll('[data-del-notice]').forEach((b) => {
        b.addEventListener('click', async () => {
          if (!confirm('Delete this notice?')) return;
          const [iid, nid] = b.getAttribute('data-del-notice').split(':');
          await App.api('/tenant/' + iid + '/notices/' + nid, { method: 'DELETE' });
          loadNotices(iid);
        });
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /*  Settings — account profile + change password                     */
  /* ---------------------------------------------------------------- */
  function renderAccountCard() {
    const u = App.getUser() || {};
    const host = document.querySelector('[data-account-card]');
    if (!host) return;
    const v = (x) => App.escapeHtml(x || '');
    host.innerHTML = `
      <article class="card">
        <div class="flex-between" style="flex-wrap:wrap;gap:8px;">
          <div>
            <h3 style="margin:0;">Your profile</h3>
            <p class="text-muted" style="margin:.2em 0;">Used on every claim you submit.</p>
          </div>
          ${u.profile_complete
              ? '<span class="badge badge--verified">Profile complete</span>'
              : '<span class="badge badge--warning">Profile incomplete</span>'}
        </div>
        <form data-account-form class="form-grid mt-3">
          <div class="field"><label class="label">Full name</label>
            <input name="name" value="${v(u.name)}" required maxlength="120" /></div>
          <div class="field"><label class="label">Mobile (Bangladesh)</label>
            <input name="mobile" value="${v(u.mobile || u.phone)}" required maxlength="20" placeholder="01712345678" /></div>
          <div class="field"><label class="label">পদবি / Designation</label>
            <input name="designation_bn" value="${v(u.designation_bn)}" required maxlength="120" /></div>
          <div class="field"><label class="label">প্রতিষ্ঠানের নাম / Institution name</label>
            <input name="institution_name" value="${v(u.institution_name)}" required maxlength="200" /></div>
          <div class="field"><label class="label">বিভাগ / Division</label>
            <select name="division" required>
              <option value="">— Select division —</option>
            </select></div>
          <div class="field"><label class="label">জেলা / District</label>
            <select name="district" required>
              <option value="">— Select division first —</option>
            </select></div>
          <div class="field field--wide"><label class="label">উপজেলা / Upazila / Thana</label>
            <select name="upazila" required>
              <option value="">— Select district first —</option>
            </select></div>
          <div class="field field--wide text-right">
            <button class="btn btn--primary" type="submit">Save profile</button>
          </div>
        </form>

        <details class="mt-3">
          <summary style="cursor:pointer;font-weight:600;">Change password</summary>
          <form data-pwd-form class="form-grid mt-2">
            <div class="field"><label class="label">Current password ${u && u.provider === 'email' ? '<span class="text-danger">*</span>' : '<span class="text-muted">(only if you set one)</span>'}</label>
              <input name="current_password" type="password" autocomplete="current-password" /></div>
            <div class="field"><label class="label">New password (8+ chars)</label>
              <input name="new_password" type="password" minlength="8" autocomplete="new-password" required /></div>
            <div class="field field--wide text-right">
              <button class="btn" type="submit">Update password</button>
            </div>
          </form>
        </details>
      </article>`;

    // Wire the BD-locations cascade for the Settings pane account form. It
    // round-trips with the existing free-text values: anything the user had
    // saved before that doesn't match the canonical list is preserved as a
    // "(saved)" sentinel option.
    if (window.BDLocations) {
      const f = host.querySelector('[data-account-form]');
      if (f) {
        window.BDLocations.bind(
          f.querySelector('[name="division"]'),
          f.querySelector('[name="district"]'),
          f.querySelector('[name="upazila"]'),
          { division: u.division || '', district: u.district || '', upazila: u.upazila || '' }
        );
      }
    }

    host.querySelector('[data-account-form]').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget);
      const body = {};
      fd.forEach((v, k) => { body[k] = (v || '').toString().trim(); });
      try {
        const r = await App.api('/auth/profile', { method: 'PATCH', body });
        if (r && r.user) { App.setSession(null, r.user); renderAccountCard(); }
        App.toast('Profile saved', 'success');
      } catch (e) {
        if (e && e.errors) {
          const first = Object.keys(e.errors)[0];
          App.toast(e.errors[first], 'error');
        } else { App.toast(e.detail || 'Save failed', 'error'); }
      }
    });

    host.querySelector('[data-pwd-form]').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget);
      const body = {
        current_password: (fd.get('current_password') || '').toString(),
        new_password: (fd.get('new_password') || '').toString(),
      };
      try {
        await App.api('/auth/change-password', { method: 'POST', body });
        App.toast('Password updated', 'success');
        ev.currentTarget.reset();
      } catch (e) {
        if (e && e.errors) {
          const first = Object.keys(e.errors)[0];
          App.toast(e.errors[first], 'error');
        } else { App.toast(e.detail || 'Could not update password', 'error'); }
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /*  v3.2 — Support Developer payment methods (admin-managed)         */
  /* ---------------------------------------------------------------- */
  const PAY_BRAND = {
    bkash:  { label: 'bKash',  color: '#e2136e' },
    nagad:  { label: 'Nagad',  color: '#f47216' },
    rocket: { label: 'Rocket', color: '#8a2be2' },
    upay:   { label: 'Upay',   color: '#ec1b23' },
    tap:    { label: 'Tap',    color: '#0ea5e9' },
    bank:   { label: 'Bank',   color: '#0f766e' },
    card:   { label: 'Card',   color: '#1f2937' },
    paypal: { label: 'PayPal', color: '#003087' },
    crypto: { label: 'Crypto', color: '#f59e0b' },
    other:  { label: 'Other',  color: '#475569' },
  };
  let _paymentsLoaded = false;
  function renderSupportPayments() {
    const host = document.querySelector('[data-payments-host]');
    if (!host || _paymentsLoaded) return;
    host.innerHTML = '<div class="card"><div class="skeleton" style="height:120px;"></div></div>';
    App.api('/support/payments').then((r) => {
      _paymentsLoaded = true;
      const items = (r && r.items) || [];
      if (!items.length) { host.innerHTML = ''; return; }
      host.innerHTML = `
        <article class="card dash-support">
          <h3 style="margin-top:0;">Support us with a tip</h3>
          <p class="text-muted">Every contribution helps us keep institution.bd free for everyone. Send to any of the methods below.</p>
          <div class="pay-grid">${items.map(payCard).join('')}</div>
        </article>`;
      host.querySelectorAll('[data-copy-pay]').forEach((b) => {
        b.addEventListener('click', async (e) => {
          e.preventDefault();
          const text = b.getAttribute('data-copy-pay') || '';
          try {
            await navigator.clipboard.writeText(text);
            App.toast('Copied "' + text + '"', 'success');
          } catch { App.toast('Could not copy', 'error'); }
        });
      });
    }).catch(() => { host.innerHTML = ''; });
  }
  function payCard(p) {
    const meta = PAY_BRAND[p.method] || PAY_BRAND.other;
    const num = (p.number || '').trim();
    return `
      <div class="pay-card">
        <header class="pay-card__head">
          <span class="pay-card__pill" style="background:${meta.color};">${App.escapeHtml(meta.label)}</span>
          <strong>${App.escapeHtml(p.label)}</strong>
        </header>
        ${num ? `
          <div class="pay-card__num">
            <code>${App.escapeHtml(num)}</code>
            <button type="button" class="btn btn--sm" data-copy-pay="${App.escapeHtml(num)}">Copy</button>
          </div>` : ''}
        ${p.note ? `<p class="pay-card__note">${App.escapeHtml(p.note)}</p>` : ''}
        ${p.qr_url ? `<a class="pay-card__qr" href="${App.escapeHtml(p.qr_url)}" target="_blank" rel="noopener">View QR →</a>` : ''}
      </div>`;
  }

  /* ---------------------------------------------------------------- */
  /*  v3.2 — DNS records manager (verified domains only)               */
  /* ---------------------------------------------------------------- */
  const DNS_TYPES = ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS'];

  function dnsTypePill(type, status) {
    const cls = status === 'live' ? 'badge--verified'
              : status === 'error' ? 'badge--danger'
              : 'badge';
    return `<span class="badge ${cls}">${App.escapeHtml(type)}${status && status !== 'live' ? ' · ' + App.escapeHtml(status) : ''}</span>`;
  }

  async function loadDnsRecords(claimId) {
    const host = manageBody && manageBody.querySelector('[data-dns-host="' + claimId + '"]');
    if (!host) return;
    host.innerHTML = '<div class="skeleton" style="height:80px;"></div>';
    try {
      const r = await App.api('/tenant/' + claimId + '/dns');
      const items = r.items || [];
      const cfHint = r.cf_configured
        ? '<span class="text-muted">Records publish to Cloudflare automatically.</span>'
        : '<span class="text-muted">Cloudflare is not configured — records save locally only; an admin must publish them upstream.</span>';
      host.innerHTML = `
        <div class="flex-between" style="flex-wrap:wrap;gap:10px;margin-bottom:10px;">
          <p class="text-muted" style="margin:0;">Manage DNS records for <code>${App.escapeHtml(r.subdomain)}</code>. ${cfHint}</p>
          <button class="btn btn--sm btn--primary" data-dns-add="${claimId}" type="button">+ Add record</button>
        </div>
        ${items.length ? `
          <div class="dash-table-wrap">
            <table class="dash-table dns-table">
              <thead><tr>
                <th>Type</th><th>Name</th><th>Content</th><th>TTL</th><th>Proxied</th><th class="text-right">Action</th>
              </tr></thead>
              <tbody>${items.map((d) => dnsRow(d, claimId, r.subdomain)).join('')}</tbody>
            </table>
          </div>` : `
          <div class="dash-empty"><p>No DNS records yet — add one to start pointing this subdomain at your hosting.</p></div>`}
      `;
      wireDnsActions(claimId, r.subdomain, items);
    } catch (e) {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml((e && e.detail) || 'Could not load DNS records') + '</p>';
    }
  }
  function dnsRow(d, claimId, subdomain) {
    const fqdn = (d.name === '@' || d.name === '' || d.name === subdomain) ? subdomain : (d.name + '.' + subdomain);
    return `
      <tr data-dns-row="${d.id}">
        <td>${dnsTypePill(d.type, d.cf_status || '')}</td>
        <td><code>${App.escapeHtml(fqdn)}</code></td>
        <td><code style="word-break:break-all;">${App.escapeHtml(d.content)}</code>${d.priority !== null && d.priority !== undefined ? ' <span class="text-muted">(prio ' + d.priority + ')</span>' : ''}</td>
        <td>${d.ttl === 1 ? 'Auto' : App.escapeHtml(String(d.ttl))}</td>
        <td>${d.proxied ? '☁︎' : '<span class="text-muted">—</span>'}</td>
        <td class="text-right">
          <button class="btn btn--sm" data-dns-edit="${d.id}" type="button">Edit</button>
          <button class="btn btn--ghost btn--sm btn--danger-text" data-dns-del="${d.id}" type="button">Delete</button>
        </td>
      </tr>`;
  }
  function wireDnsActions(claimId, subdomain, items) {
    const root = manageBody.querySelector('[data-dns-host="' + claimId + '"]');
    if (!root) return;
    root.querySelectorAll('[data-dns-add]').forEach((b) => {
      b.addEventListener('click', () => openDnsForm(claimId, subdomain, null));
    });
    root.querySelectorAll('[data-dns-edit]').forEach((b) => {
      const id = parseInt(b.getAttribute('data-dns-edit'), 10);
      const rec = items.find((x) => x.id === id);
      b.addEventListener('click', () => rec && openDnsForm(claimId, subdomain, rec));
    });
    root.querySelectorAll('[data-dns-del]').forEach((b) => {
      const id = parseInt(b.getAttribute('data-dns-del'), 10);
      b.addEventListener('click', async () => {
        if (!confirm('Delete this DNS record? This cannot be undone.')) return;
        try {
          await App.api('/tenant/' + claimId + '/dns/' + id, { method: 'DELETE' });
          App.toast('Record deleted', 'success');
          loadDnsRecords(claimId);
        } catch (e) {
          App.toast((e && e.detail) || 'Delete failed', 'error');
        }
      });
    });
  }
  function openDnsForm(claimId, subdomain, rec) {
    const host = manageBody.querySelector('[data-dns-host="' + claimId + '"]');
    if (!host) return;
    const isEdit = !!rec;
    const safe = (v) => App.escapeHtml(v == null ? '' : String(v));
    host.innerHTML = `
      <article class="card dns-form">
        <header class="flex-between" style="flex-wrap:wrap;gap:10px;">
          <h4 style="margin:0;">${isEdit ? 'Edit DNS record' : 'Add DNS record'}</h4>
          <button class="btn btn--ghost btn--sm" type="button" data-dns-cancel>Cancel</button>
        </header>
        <form data-dns-save="${claimId}" class="form-grid mt-2">
          <div class="field"><label class="label">Type</label>
            <select name="type" required>
              ${DNS_TYPES.map((t) => `<option value="${t}" ${rec && rec.type === t ? 'selected' : ''}>${t}</option>`).join('')}
            </select>
          </div>
          <div class="field"><label class="label">Name</label>
            <input name="name" value="${safe(rec ? rec.name : '@')}" placeholder="@ for apex, or e.g. www, mail" required maxlength="120" />
            <p class="hint">Use <code>@</code> for the apex (<code>${safe(subdomain)}</code>). Other labels are prepended.</p>
          </div>
          <div class="field field--wide"><label class="label">Content / Value</label>
            <input name="content" value="${safe(rec ? rec.content : '')}" placeholder="e.g. 203.0.113.10  or  hostname.example.com" required maxlength="512" />
          </div>
          <div class="field"><label class="label">TTL (seconds)</label>
            <input name="ttl" type="number" min="0" value="${safe(rec ? rec.ttl : 1)}" />
            <p class="hint">1 = automatic / Cloudflare-default.</p>
          </div>
          <div class="field" data-dns-mx ${rec && rec.type === 'MX' ? '' : 'hidden'}>
            <label class="label">Priority (MX only)</label>
            <input name="priority" type="number" min="0" max="65535" value="${safe(rec && rec.priority != null ? rec.priority : 10)}" />
          </div>
          <div class="field field--wide" data-dns-proxy ${rec && ['A','AAAA','CNAME'].includes(rec.type) ? '' : 'hidden'}>
            <label class="toggle-row">
              <input type="checkbox" name="proxied" ${rec && rec.proxied ? 'checked' : ''} />
              <span><strong>Proxy through Cloudflare (orange cloud)</strong><span class="text-muted"> — Cloudflare's automatic SSL and cache.</span></span>
            </label>
          </div>
          <div class="field field--wide text-right">
            <button class="btn btn--primary" type="submit">${isEdit ? 'Save changes' : 'Create record'}</button>
          </div>
        </form>
      </article>`;
    const form    = host.querySelector('[data-dns-save]');
    const typeSel = form.querySelector('[name=type]');
    const mxField = form.querySelector('[data-dns-mx]');
    const proxyF  = form.querySelector('[data-dns-proxy]');
    typeSel.addEventListener('change', () => {
      const t = typeSel.value;
      mxField.hidden = (t !== 'MX');
      proxyF.hidden  = !['A', 'AAAA', 'CNAME'].includes(t);
    });
    host.querySelector('[data-dns-cancel]').addEventListener('click', () => loadDnsRecords(claimId));
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const payload = {
        type:     (fd.get('type') || 'A').toString(),
        name:     (fd.get('name') || '@').toString().trim() || '@',
        content:  (fd.get('content') || '').toString().trim(),
        ttl:      parseInt(fd.get('ttl') || '1', 10),
        priority: fd.get('priority') ? parseInt(fd.get('priority'), 10) : null,
        proxied:  !!fd.get('proxied'),
      };
      try {
        if (isEdit) {
          await App.api('/tenant/' + claimId + '/dns/' + rec.id, { method: 'PATCH', body: payload });
          App.toast('Record updated', 'success');
        } else {
          await App.api('/tenant/' + claimId + '/dns', { method: 'POST', body: payload });
          App.toast('Record created', 'success');
        }
        loadDnsRecords(claimId);
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
})();
