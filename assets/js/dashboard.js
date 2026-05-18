/* Owner dashboard: list claims, edit profile, manage notices, upload logo/banner. */
(function () {
  'use strict';
  const App = window.App;

  const y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();

  const list = document.querySelector('[data-list]');
  const needAuth = document.querySelector('[data-needs-auth]');

  if (!App.isAuthed()) {
    needAuth.hidden = false; list.hidden = true; return;
  }

  /* ---------- v3.0: profile + change-password card ---------- */
  function renderAccountCard() {
    const u = App.getUser() || {};
    const accountHost = document.querySelector('[data-account-card]');
    if (!accountHost) return;
    const v = (x) => App.escapeHtml(x || '');
    accountHost.innerHTML = `
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
              <option value="">— Select —</option>
              ${['Dhaka','Chattogram','Khulna','Rajshahi','Rangpur','Sylhet','Mymensingh','Barishal']
                .map((d) => `<option ${u.division===d?'selected':''}>${d}</option>`).join('')}
            </select></div>
          <div class="field"><label class="label">জেলা / District</label>
            <input name="district" value="${v(u.district)}" required maxlength="80" /></div>
          <div class="field field--wide"><label class="label">উপজেলা / Upazila / Thana</label>
            <input name="upazila" value="${v(u.upazila)}" required maxlength="80" /></div>
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
    accountHost.querySelector('[data-account-form]').addEventListener('submit', async (ev) => {
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
    accountHost.querySelector('[data-pwd-form]').addEventListener('submit', async (ev) => {
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
  renderAccountCard();

  function statusBadge(s) {
    const map = {
      verified: 'badge--verified',
      pending: 'badge--pending',
      needs_info: 'badge--warning',
      rejected: 'badge--danger',
      seeded: 'badge--muted',
      suspended: 'badge--danger',
    };
    return `<span class="badge ${map[s] || ''}">${s}</span>`;
  }

  function dnsBadge(dns) {
    if (!dns) return '';
    const label = { live: 'DNS · live', error: 'DNS · error', manual: 'DNS · manual', suspended: 'DNS · suspended' }[dns] || ('DNS · ' + dns);
    const cls = dns === 'live' ? 'badge--verified' : (dns === 'error' ? 'badge--danger' : 'badge--muted');
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function dnsPanel(c) {
    if (c.status !== 'verified') return '';
    const fqdn = c.subdomain;
    const livePill = c.dns_status === 'live'
      ? `<span class="badge badge--verified">Live · auto-SSL</span>`
      : (c.dns_status === 'manual'
          ? `<span class="badge">Manual DNS</span>`
          : (c.dns_status === 'error'
              ? `<span class="badge badge--danger">DNS error</span>`
              : `<span class="badge">Pending</span>`));
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

  function imgBlock(url, label) {
    if (!url) return `<div class="upload-thumb upload-thumb--empty">${label}</div>`;
    return `<img src="${App.escapeHtml(url)}" alt="" class="upload-thumb" />`;
  }

  function claimCard(c, idx) {
    return `
      <article class="card claim-card" data-card="${c.id}">
        <header class="flex-between" style="flex-wrap:wrap;gap:10px;">
          <div>
            <h3 style="margin:0;">${App.escapeHtml(c.name_en || (c.slug + '.' + c.brand))}</h3>
            <p class="text-muted" style="margin:.2em 0;"><code>${App.escapeHtml(c.subdomain)}</code></p>
            <div class="badges">${statusBadge(c.status)} ${dnsBadge(c.dns_status)} <span class="badge badge--brand">${App.escapeHtml(c.brand)}</span></div>
          </div>
          <div class="flex">
            ${c.status === 'verified' ? `<a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" href="https://${App.escapeHtml(c.subdomain)}">Open site →</a>` : ''}
            <button class="btn btn--sm" data-toggle="${c.id}">Manage</button>
            ${['pending','needs_info','rejected'].includes(c.status)
                ? `<button class="btn btn--ghost btn--sm btn--danger-text" data-withdraw="${c.id}" title="Permanently withdraw this claim">Withdraw</button>`
                : ''}
          </div>
        </header>
        ${c.review_notes ? `<p class="text-muted mt-2"><strong>Reviewer note:</strong> ${App.escapeHtml(c.review_notes)}</p>` : ''}
        <section class="claim-card__body" id="body-${c.id}" hidden>
          ${dnsPanel(c)}
          <div class="grid-2 mt-3">
            <div>
              <h4>Profile</h4>
              <form data-form-profile="${c.id}" class="form-grid">
                <div class="field"><label class="label">Name (English)</label><input name="name_en" value="${App.escapeHtml(c.name_en || '')}" /></div>
                <div class="field"><label class="label">নাম (বাংলা)</label><input name="name_bn" value="${App.escapeHtml(c.name_bn || '')}" /></div>
                <div class="field"><label class="label">Category</label><input name="category" value="${App.escapeHtml(c.category || '')}" /></div>
                <div class="field"><label class="label">EIIN</label><input name="eiin" value="${App.escapeHtml(c.eiin || '')}" /></div>
                <div class="field"><label class="label">Division</label><input name="division" value="${App.escapeHtml(c.division || '')}" /></div>
                <div class="field"><label class="label">District</label><input name="district" value="${App.escapeHtml(c.district || '')}" /></div>
                <div class="field"><label class="label">Upazila</label><input name="upazila" value="${App.escapeHtml(c.upazila || '')}" /></div>
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
        </section>
      </article>`;
  }

  function load() {
    list.innerHTML = '<div class="card"><div class="skeleton" style="height:120px;"></div></div>';
    App.api('/claims/mine').then((r) => {
      if (!r.claims || !r.claims.length) {
        list.innerHTML = '<div class="card"><h3>No claims yet</h3><p class="text-muted">You haven\'t claimed any subdomains. Start with the claim wizard.</p><a class="btn btn--primary" href="/claim.php">Claim your subdomain →</a></div>';
        return;
      }
      list.innerHTML = r.claims.map(claimCard).join('');
      wire();
    }).catch((e) => {
      if (e.status === 401) { App.clearSession(); needAuth.hidden = false; list.hidden = true; return; }
      list.innerHTML = '<p class="text-danger">' + App.escapeHtml(e.detail || 'Could not load') + '</p>';
    });
  }

  function wire() {
    document.querySelectorAll('[data-toggle]').forEach((b) => {
      b.addEventListener('click', () => {
        const id = b.getAttribute('data-toggle');
        const body = document.getElementById('body-' + id);
        if (body.hidden) {
          body.hidden = false;
          b.textContent = 'Hide';
          loadDocs(id);
          loadNotices(id);
        } else {
          body.hidden = true; b.textContent = 'Manage';
        }
      });
    });
    document.querySelectorAll('[data-withdraw]').forEach((b) => {
      b.addEventListener('click', async () => {
        const id = b.getAttribute('data-withdraw');
        if (!confirm('Permanently withdraw this claim? Uploaded documents will be deleted. This cannot be undone.')) return;
        try {
          await App.api('/claims/' + id, { method: 'DELETE' });
          App.toast('Claim withdrawn', 'success');
          load();
        } catch (e) { App.toast(e.detail || 'Withdraw failed', 'error'); }
      });
    });
    document.querySelectorAll('[data-dns-retry]').forEach((b) => {
      b.addEventListener('click', async () => {
        const id = b.getAttribute('data-dns-retry');
        b.disabled = true;
        const orig = b.textContent;
        b.textContent = 'Retrying…';
        try {
          const r = await App.api('/admin/claims/' + id + '/dns-retry', { method: 'POST' });
          App.toast(r.ok ? 'DNS retry submitted' : (r.dns && r.dns.message) || 'DNS error', r.ok ? 'success' : 'error');
          load();
        } catch (e) {
          App.toast(e.status === 403
            ? 'Only admins can retry DNS — please contact support.'
            : (e.detail || 'Retry failed'), 'error');
        } finally {
          b.disabled = false; b.textContent = orig;
        }
      });
    });
    document.querySelectorAll('[data-form-profile]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = f.getAttribute('data-form-profile');
        const fd = new FormData(f);
        const body = {};
        fd.forEach((v, k) => { body[k] = v; });
        try {
          await App.api('/tenant/' + id + '/site', { method: 'PATCH', body });
          App.toast('Profile saved', 'success');
        } catch (e) { App.toast(e.detail || 'Save failed', 'error'); }
      });
    });
    document.querySelectorAll('[data-form-image]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = f.getAttribute('data-form-image');
        const kind = f.getAttribute('data-kind');
        const fd = new FormData(f);
        if (!fd.get('file') || fd.get('file').size === 0) { App.toast('Pick a file', 'error'); return; }
        fd.append('kind', kind);
        try {
          await App.api('/tenant/' + id + '/upload-image', { method: 'POST', body: fd });
          App.toast(kind + ' updated', 'success');
          load();
        } catch (e) { App.toast(e.detail || 'Upload failed', 'error'); }
      });
    });
    document.querySelectorAll('[data-form-doc]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = f.getAttribute('data-form-doc');
        const fd = new FormData(f);
        if (!fd.get('file') || fd.get('file').size === 0) { App.toast('Pick a file', 'error'); return; }
        try {
          await App.api('/claims/' + id + '/documents', { method: 'POST', body: fd });
          App.toast('Document uploaded', 'success');
          f.reset();
          loadDocs(id);
        } catch (e) { App.toast(e.detail || 'Upload failed', 'error'); }
      });
    });
    document.querySelectorAll('[data-form-notice]').forEach((f) => {
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = f.getAttribute('data-form-notice');
        const fd = new FormData(f);
        const body = { title: fd.get('title'), body: fd.get('body'), pinned: !!fd.get('pinned') };
        try {
          await App.api('/tenant/' + id + '/notices', { method: 'POST', body });
          App.toast('Notice published', 'success');
          f.reset();
          loadNotices(id);
        } catch (e) { App.toast(e.detail || 'Publish failed', 'error'); }
      });
    });
  }

  function loadDocs(id) {
    const host = document.querySelector('[data-docs="' + id + '"]');
    if (!host) return;
    host.innerHTML = '<div class="skeleton" style="height:30px;"></div>';
    App.api('/tenant/' + id + '/site').then((r) => {
      const docs = r.documents || [];
      if (!docs.length) { host.innerHTML = '<p class="text-muted" style="font-size:.9rem;">No documents uploaded yet.</p>'; return; }
      host.innerHTML = docs.map((d) => `<div class="doc-row"><span>📎 ${App.escapeHtml(d.doc_type)} · ${App.escapeHtml(d.filename)}</span><span class="text-muted">${App.escapeHtml(App.fmtDate(d.uploaded_at))}</span></div>`).join('');
    });
  }
  function loadNotices(id) {
    const host = document.querySelector('[data-notices="' + id + '"]');
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

  load();
})();
