/* Admin console: stats, queue, claim detail, reserved slugs. */
(function () {
  'use strict';
  const App = window.App;

  const y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();

  const needAuth = document.querySelector('[data-needs-auth]');
  const notAdmin = document.querySelector('[data-not-admin]');
  const admin = document.querySelector('[data-admin]');
  const detailModal = document.querySelector('[data-detail-modal]');
  const detailBody = document.querySelector('[data-detail-body]');

  if (!App.isAuthed()) { needAuth.hidden = false; return; }

  function gate() {
    App.api('/auth/me').then((r) => {
      App.setSession(null, r.user);
      if (!r.user || !r.user.is_admin) { notAdmin.hidden = false; return; }
      admin.hidden = false;
      loadStats();
      loadQueue();
      wireTabs();
      wireReserved();
    }).catch((e) => {
      if (e.status === 401) { App.clearSession(); needAuth.hidden = false; }
      else App.toast(e.detail || 'Error', 'error');
    });
  }

  function loadStats() {
    App.api('/admin/stats').then((r) => {
      const c = r.counts;
      document.querySelector('[data-stats]').innerHTML = `
        <div class="stat"><span class="num">${c.pending}</span><span class="lab">Pending</span></div>
        <div class="stat"><span class="num">${c.verified}</span><span class="lab">Verified</span></div>
        <div class="stat"><span class="num">${c.needs_info}</span><span class="lab">Needs info</span></div>
        <div class="stat"><span class="num">${c.rejected + c.suspended}</span><span class="lab">Rejected / suspended</span></div>`;
    });
  }

  /* ---------- queue ---------- */
  const queueForm = document.querySelector('[data-queue-form]');
  const queueHost = document.querySelector('[data-queue]');
  queueForm.addEventListener('submit', (e) => { e.preventDefault(); loadQueue(); });
  queueForm.querySelector('[data-status]').addEventListener('change', () => loadQueue());

  function statusBadge(s) {
    const map = { verified:'badge--verified', pending:'badge--pending', needs_info:'badge--warning', rejected:'badge--danger', seeded:'badge--muted', suspended:'badge--danger' };
    return `<span class="badge ${map[s]||''}">${s}</span>`;
  }

  function loadQueue() {
    const status = queueForm.querySelector('[data-status]').value;
    const q = queueForm.querySelector('[data-q]').value.trim();
    queueHost.innerHTML = '<div class="card"><div class="skeleton" style="height:80px;"></div></div>';
    App.api('/admin/claims' + App.qs({ status, q })).then((r) => {
      if (!r.items.length) { queueHost.innerHTML = '<p class="text-muted">No claims match.</p>'; return; }
      queueHost.innerHTML = r.items.map((c) => `
        <article class="card admin-row" data-row="${c.id}">
          <div class="flex" style="gap:14px;align-items:flex-start;">
            <span class="logo">${App.escapeHtml(App.initialsOf(c.name_en || c.slug))}</span>
            <div style="flex:1;min-width:240px;">
              <h3 style="margin:0;">${App.escapeHtml(c.name_en || c.slug)}</h3>
              <p class="text-muted" style="margin:.2em 0;"><code>${App.escapeHtml(c.subdomain)}</code> · ${App.escapeHtml(c.category || '')} ${c.division ? '· ' + App.escapeHtml(c.division) : ''}</p>
              <div class="badges">${statusBadge(c.status)} <span class="badge badge--brand">${App.escapeHtml(c.brand)}</span></div>
            </div>
            <button class="btn btn--primary btn--sm" data-detail="${c.id}">Review</button>
          </div>
          ${c.review_notes ? `<p class="text-muted mt-2"><strong>Notes:</strong> ${App.escapeHtml(c.review_notes)}</p>` : ''}
        </article>`).join('');
      queueHost.querySelectorAll('[data-detail]').forEach((b) => {
        b.addEventListener('click', () => openDetail(b.getAttribute('data-detail')));
      });
    }).catch((e) => { queueHost.innerHTML = '<p class="text-danger">' + App.escapeHtml(e.detail || 'Load failed') + '</p>'; });
  }

  /* ---------- detail modal ---------- */
  function openDetail(id) {
    detailBody.innerHTML = '<div class="skeleton" style="height:200px;"></div>';
    detailModal.classList.add('open');
    App.api('/admin/claims/' + id).then((r) => renderDetail(r));
  }
  document.querySelector('[data-close-detail]').addEventListener('click', () => detailModal.classList.remove('open'));
  detailModal.addEventListener('click', (e) => { if (e.target === detailModal) detailModal.classList.remove('open'); });

  function renderDetail(r) {
    const c = r.institution;
    const docs = r.documents || [];
    const owner = r.owner;
    const fields = [
      ['Brand', c.brand], ['Slug', c.slug], ['Subdomain', c.subdomain],
      ['Name (EN)', c.name_en], ['Name (BN)', c.name_bn], ['Category', c.category],
      ['EIIN', c.eiin], ['Division', c.division], ['District', c.district], ['Upazila', c.upazila],
      ['Address', c.address], ['Contact name', c.contact_name],
      ['Contact phone', c.contact_phone], ['Contact email', c.contact_email], ['Website', c.website],
      ['Status', c.status], ['DNS status', c.dns_status || '—'],
      ['Owner', owner ? (owner.name || owner.email || ('user#' + owner.id)) : '—'],
    ];
    detailBody.innerHTML = `
      <h2 style="margin-top:0;">${App.escapeHtml(c.name_en || c.subdomain)}</h2>
      <p class="text-muted"><code>${App.escapeHtml(c.subdomain)}</code> · ${statusBadge(c.status)}</p>
      <div class="grid-2 mt-3">
        <table class="kv">${fields.map(([k,v]) => `<tr><th>${App.escapeHtml(k)}</th><td>${App.escapeHtml(v || '—')}</td></tr>`).join('')}</table>
        <div>
          <h4>Documents</h4>
          ${docs.length ? docs.map((d) => `<div class="doc-row"><span>📎 ${App.escapeHtml(d.doc_type)} · ${App.escapeHtml(d.filename)}</span><a class="btn btn--sm" target="_blank" rel="noopener" href="/api/admin/documents/${d.id}?_t=${encodeURIComponent(App.getToken() || '')}">View</a></div>`).join('') : '<p class="text-muted">No documents.</p>'}
          ${c.about_en ? `<h4 class="mt-3">About (English)</h4><p>${App.escapeHtml(c.about_en)}</p>` : ''}
          ${c.about_bn ? `<h4 class="mt-3">পরিচিতি</h4><p style="font-family:'Noto Sans Bengali',sans-serif;">${App.escapeHtml(c.about_bn)}</p>` : ''}
        </div>
      </div>
      <h4 class="mt-4">Decision</h4>
      <form data-decide="${c.id}" class="form-grid">
        <div class="field field--wide"><label class="label">Notes (shown to owner)</label><textarea name="notes" rows="2"></textarea></div>
        <div class="field field--wide flex" style="flex-wrap:wrap;gap:8px;">
          <button class="btn btn--primary" data-act="approve">Approve &amp; create DNS</button>
          <button class="btn" data-act="needs_info">Needs info</button>
          <button class="btn btn--danger" data-act="reject">Reject</button>
          <button class="btn btn--ghost" data-act="suspend">Suspend</button>
          ${c.status === 'verified' && c.dns_status !== 'live' ? '<button class="btn btn--sm" data-act="dns-retry">Retry DNS</button>' : ''}
        </div>
      </form>`;
    const form = detailBody.querySelector('[data-decide]');
    form.querySelectorAll('button[data-act]').forEach((b) => {
      b.addEventListener('click', async (e) => {
        e.preventDefault();
        const act = b.getAttribute('data-act');
        const notes = form.querySelector('[name=notes]').value;
        try {
          if (act === 'dns-retry') {
            const r = await App.api('/admin/claims/' + c.id + '/dns-retry', { method: 'POST' });
            App.toast(r.ok ? 'DNS retry submitted' : (r.dns && r.dns.message) || 'DNS error', r.ok ? 'success' : 'error');
          } else {
            await App.api('/admin/claims/' + c.id + '/decide', { method: 'POST', body: { decision: act, notes } });
            App.toast('Decision: ' + act, 'success');
          }
          detailModal.classList.remove('open');
          loadStats();
          loadQueue();
        } catch (e2) { App.toast(e2.detail || 'Action failed', 'error'); }
      });
    });
  }

  /* ---------- tabs ---------- */
  function wireTabs() {
    document.querySelectorAll('.tab').forEach((t) => {
      t.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach((x) => x.classList.remove('active'));
        t.classList.add('active');
        const name = t.getAttribute('data-tab');
        document.querySelectorAll('[data-pane]').forEach((p) => { p.hidden = p.getAttribute('data-pane') !== name; });
        if (name === 'reserved') loadReserved();
        if (name === 'audit')    loadAudit();
        if (name === 'exports')  wireExports();
        if (name === 'settings') loadSettings();
        if (name === 'integrations') loadIntegrations();
      });
    });
  }

  /* ---------- v3.0: platform settings pane ---------- */
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
             <code>${(cf.configured_brands||[]).map(App.escapeHtml).join('</code>, <code>') || '—'}</code>.
             New verified claims publish a record automatically.
           </div>`
        : `<div class="cf-banner cf-banner--warn">
             <strong>Cloudflare auto-DNS not configured.</strong>
             Add an API token + zone IDs in <code>api/config.php</code> to enable instant DNS.
             Until then, approved claims are marked <code>manual</code>.
           </div>`;
      host.innerHTML = `
        <div class="card mt-3">
          <h3 style="margin-top:0;">Platform settings</h3>
          <p class="text-muted">Toggle the moderation policy and DNS automation. Changes take effect immediately for new claims.</p>
          ${cfBanner}
          <form data-settings-form class="mt-3">
            <label class="toggle-row">
              <input type="checkbox" name="instant_claim" ${s.instant_claim ? 'checked' : ''} />
              <span><strong>Instant claim</strong><span class="text-muted"> — show the one-click "Claim it now" button on the homepage and skip the verification step.</span></span>
            </label>
            <label class="toggle-row">
              <input type="checkbox" name="require_documents" ${s.require_documents ? 'checked' : ''} />
              <span><strong>Require documents</strong><span class="text-muted"> — when ON, every claim lands as <code>pending</code> and must be approved by an admin (with proof of EIIN / NID / trade-licence).</span></span>
            </label>
            <label class="toggle-row">
              <input type="checkbox" name="cloudflare_auto_dns" ${s.cloudflare_auto_dns ? 'checked' : ''} />
              <span><strong>Cloudflare auto-DNS</strong><span class="text-muted"> — automatically create / update the DNS record on Cloudflare when a claim is verified.</span></span>
            </label>
            <div class="text-right mt-3"><button class="btn btn--primary" type="submit">Save settings</button></div>
          </form>
        </div>`;
      host.querySelector('[data-settings-form]').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const body = {
          instant_claim: fd.get('instant_claim') ? true : false,
          require_documents: fd.get('require_documents') ? true : false,
          cloudflare_auto_dns: fd.get('cloudflare_auto_dns') ? true : false,
        };
        try {
          await App.api('/admin/settings', { method: 'POST', body });
          App.toast('Settings saved.', 'success');
          loadSettings();
        } catch (e) { App.toast(e.detail || 'Could not save', 'error'); }
      });
    }).catch((e) => {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml(e.detail || 'Load failed') + '</p>';
    });
  }

  /* ---------- v3.0: audit log pane ---------- */
  function wireAudit() {
    const form = document.querySelector('[data-audit-form]');
    if (form && !form._wired) {
      form._wired = true;
      form.addEventListener('submit', (e) => { e.preventDefault(); loadAudit(); });
    }
  }
  function loadAudit() {
    wireAudit();
    const host = document.querySelector('[data-audit]');
    const form = document.querySelector('[data-audit-form]');
    const fd = new FormData(form);
    const params = {
      action:  (fd.get('action')  || '').toString().trim() || undefined,
      q:       (fd.get('q')       || '').toString().trim() || undefined,
      inst_id: (fd.get('inst_id') || '').toString().trim() || undefined,
      limit: 100,
    };
    host.innerHTML = '<div class="card"><div class="skeleton" style="height:80px;"></div></div>';
    App.api('/admin/audit' + App.qs(params)).then((r) => {
      if (!r.items.length) { host.innerHTML = '<p class="text-muted">No audit entries match.</p>'; return; }
      host.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Inst</th><th>Detail</th></tr></thead><tbody>'
        + r.items.map((a) => `
          <tr>
            <td>${App.escapeHtml(a.created_at || '')}</td>
            <td>${App.escapeHtml(a.actor_name || a.actor_email || (a.actor_user_id ? '#' + a.actor_user_id : 'system'))}</td>
            <td><code>${App.escapeHtml(a.action)}</code></td>
            <td>${a.institution_id ? '#' + a.institution_id : '—'}</td>
            <td style="max-width:380px;word-break:break-word;">${App.escapeHtml(a.detail || '')}</td>
          </tr>`).join('')
        + '</tbody></table></div>'
        + (r.total ? `<p class="text-muted mt-2">Showing ${r.items.length} of ${r.total} entries.</p>` : '');
    }).catch((e) => { host.innerHTML = '<p class="text-danger">' + App.escapeHtml(e.detail || 'Load failed') + '</p>'; });
  }

  /* ---------- v3.0: CSV export buttons ---------- */
  function wireExports() {
    document.querySelectorAll('[data-export]').forEach((a) => {
      if (a._wired) return;
      a._wired = true;
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const which = a.getAttribute('data-export'); // claims | users
        const url = '/api/admin/export/' + which + '.csv?_t=' + encodeURIComponent(App.getToken() || '');
        // Open in a new tab — browser will treat it as a download because of
        // the Content-Disposition: attachment header.
        const w = window.open(url, '_blank');
        if (!w) location.href = url;
      });
    });
  }

  /* ---------- reserved slugs ---------- */
  function wireReserved() {
    document.querySelector('[data-reserved-form]').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      try {
        await App.api('/admin/reserved-slugs', { method: 'POST', body: { slug: fd.get('slug'), reason: fd.get('reason') } });
        App.toast('Reserved', 'success'); e.target.reset(); loadReserved();
      } catch (e2) { App.toast(e2.detail || 'Failed', 'error'); }
    });
  }
  function loadReserved() {
    const host = document.querySelector('[data-reserved]');
    host.innerHTML = '<div class="skeleton" style="height:60px;"></div>';
    App.api('/admin/reserved-slugs').then((r) => {
      if (!r.items.length) { host.innerHTML = '<p class="text-muted">No reserved slugs yet.</p>'; return; }
      host.innerHTML = '<table class="kv">' + r.items.map((i) => `
        <tr><td><code>${App.escapeHtml(i.slug)}</code></td><td>${App.escapeHtml(i.reason || '')}</td>
          <td><button class="btn btn--sm btn--danger" data-del-res="${i.id}">Delete</button></td></tr>`).join('') + '</table>';
      host.querySelectorAll('[data-del-res]').forEach((b) => {
        b.addEventListener('click', async () => {
          if (!confirm('Remove reservation?')) return;
          await App.api('/admin/reserved-slugs/' + b.getAttribute('data-del-res'), { method: 'DELETE' });
          loadReserved();
        });
      });
    });
  }

  /* ---------- v3.1: Integrations (OAuth, Cloudflare, WhatsApp, JWT, branding) ---------- */
  function loadIntegrations() {
    const host = document.querySelector('[data-integrations-host]');
    if (!host) return;
    host.innerHTML = '<div class="card mt-3"><div class="skeleton" style="height:160px;"></div></div>';
    App.api('/admin/integrations').then((r) => {
      renderIntegrations(host, r);
    }).catch((e) => {
      host.innerHTML = '<p class="text-danger">' + App.escapeHtml(e.detail || 'Load failed') + '</p>';
    });
  }

  function renderIntegrations(host, r) {
    const v = r.values || {};
    const redirects = r.redirects || {};
    const cfStatus = r.cloudflare_status || {};
    const val = (k) => (v[k] && v[k].value) || '';
    const isSet = (k) => !!(v[k] && v[k].is_set);
    const masked = (k) => !!(v[k] && v[k].masked);
    const placeholder = (k) => masked(k) && isSet(k) ? val(k) : '';

    // Render a single field. type=text|password|textarea|checkbox.
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
      } else if (t === 'checkbox') {
        const checked = ['1','true','yes','on'].includes(String(val(key)).toLowerCase()) ? 'checked' : '';
        inputHtml = `<label class="toggle-row" style="padding:6px 0;border:none;">
          <input type="checkbox" id="${id}" name="${App.escapeHtml(key)}" ${checked} />
          <span><strong>${App.escapeHtml(label)}</strong>${help ? '<span class="text-muted"> — ' + App.escapeHtml(help) + '</span>' : ''}</span>
        </label>`;
        return `<div class="field field--wide">${inputHtml}</div>`;
      } else {
        // password fields render the masked stored value as the placeholder
        // and submit empty by default (= "leave alone").
        const isSecret = masked(key);
        const inputType = isSecret ? 'password' : t;
        const inputPh = isSecret ? (present ? placeholder(key) : (ph || 'paste secret here')) : ph;
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

    function copyBtn(text) {
      return `<button type="button" class="btn btn--sm" data-copy="${App.escapeHtml(text)}">Copy</button>`;
    }

    const cfBrandsHtml = Object.keys(cfStatus).map((b) => {
      const ok = !!cfStatus[b];
      return `<span class="badge ${ok ? 'badge--verified' : 'badge--muted'}" style="margin-right:6px;">
        <code>${App.escapeHtml(b)}</code> ${ok ? 'configured' : 'not configured'}
      </span>`;
    }).join('');

    host.innerHTML = `
      <div class="card mt-3">
        <div class="flex-between" style="flex-wrap:wrap;gap:10px;">
          <div>
            <h3 style="margin:0;">Integrations</h3>
            <p class="text-muted" style="margin:.2em 0;">Edit every secret, API key and external service from here. Saved values overlay <code>api/config.php</code> on the next request.</p>
          </div>
          <span class="badge">v3.1</span>
        </div>

        <form data-integrations-form class="mt-3">

          <!-- Branding ---------------------------------------------------- -->
          <details class="card" open style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Branding & site URL</summary>
            <div class="form-grid mt-3">
              ${fld('site.url', 'Public site URL', { placeholder: 'https://institution.bd', help: 'Used to build OAuth redirect URIs and absolute links. No trailing slash.' })}
              ${fld('brand.name', 'Brand name', { placeholder: 'institution.bd' })}
              ${fld('brand.tagline', 'Brand tagline', { placeholder: 'Free verified subdomains for Bangladeshi institutions', wide: true })}
            </div>
          </details>

          <!-- OAuth ------------------------------------------------------- -->
          <details class="card" style="margin:18px 0;padding:18px 20px;box-shadow:none;">
            <summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">Social sign-in (OAuth)</summary>
            <p class="text-muted mt-2" style="margin-top:8px;">Whitelist this redirect URI in each provider's developer console:</p>

            ${oauthBlock('google',   'Google',   redirects.google,   v)}
            ${oauthBlock('facebook', 'Facebook', redirects.facebook, v)}
            ${oauthBlock('github',   'GitHub',   redirects.github,   v)}
          </details>

          <!-- Cloudflare -------------------------------------------------- -->
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

          <!-- WhatsApp ---------------------------------------------------- -->
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

          <!-- Auth / security --------------------------------------------- -->
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
    // Copy buttons (redirect URIs).
    host.querySelectorAll('[data-copy]').forEach((b) => {
      b.addEventListener('click', async () => {
        const text = b.getAttribute('data-copy') || '';
        try {
          await navigator.clipboard.writeText(text);
          App.toast('Copied to clipboard', 'success');
        } catch (e) {
          // Fallback: select-and-copy via a temporary textarea.
          const ta = document.createElement('textarea');
          ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); App.toast('Copied', 'success'); }
          catch { App.toast('Copy failed', 'error'); }
          ta.remove();
        }
      });
    });

    // Cloudflare test.
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
            testOut.innerHTML = '<span class="text-success">✓ ' + App.escapeHtml(r.message || 'OK')
              + (zones ? ' — visible zones: <code>' + App.escapeHtml(zones) + '</code>' : '') + '</span>';
            App.toast('Cloudflare token verified', 'success');
          } else {
            testOut.innerHTML = '<span class="text-danger">✗ ' + App.escapeHtml(r.message || 'Token rejected') + '</span>';
            App.toast(r.message || 'Cloudflare rejected the token', 'error');
          }
        } catch (e) {
          testOut.innerHTML = '<span class="text-danger">✗ ' + App.escapeHtml(e.detail || 'Test failed') + '</span>';
        } finally {
          testBtn.disabled = false;
          testBtn.textContent = orig;
        }
      });
    }

    // Rotate JWT.
    const rotateBtn = host.querySelector('[data-rotate-jwt]');
    if (rotateBtn) {
      rotateBtn.addEventListener('click', async () => {
        if (!confirm('Rotate the JWT secret? This signs out EVERY user — including you. Continue?')) return;
        try {
          const r = await App.api('/admin/integrations/rotate-jwt', { method: 'POST' });
          App.toast(r.message || 'JWT rotated', 'success');
          // We're now signed out. Redirect to home so the user can log back in.
          App.clearSession();
          setTimeout(() => { location.href = '/'; }, 800);
        } catch (e) {
          App.toast(e.detail || 'Rotate failed', 'error');
        }
      });
    }

    // Submit.
    const form = host.querySelector('[data-integrations-form]');
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(form);
      const values = {};
      fd.forEach((val, key) => { values[key] = val; });
      // Pick up unchecked checkboxes (FormData omits them).
      form.querySelectorAll('input[type=checkbox]').forEach((c) => {
        if (!(c.name in values)) values[c.name] = '0';
        else values[c.name] = '1';
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
        App.toast(e.detail || 'Save failed', 'error');
      } finally {
        submit.disabled = false;
        submit.textContent = orig;
      }
    });
  }

  gate();
})();
