/* Single institution page. */
(function () {
  'use strict';
  const App = window.App;

  const y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();

  const params = new URLSearchParams(location.search);
  const brand = params.get('brand') || '';
  const slug = params.get('slug') || '';
  const host = document.querySelector('[data-inst-host]');
  if (!brand || !slug) {
    host.innerHTML = '<div class="card"><h2>Institution not found</h2><p>Pass <code>?brand=…&slug=…</code> in the URL.</p></div>';
    return;
  }
  // v3.2 — institution.php now SSRs the real <title>. Only override it on
  // the bare /institution.php?brand=…&slug=… form (where the server tag
  // still says "Institution — institution.bd").
  if (/Institution — institution\.bd/i.test(document.title)) {
    document.title = slug + '.' + brand + ' — institution.bd';
  }

  function render(inst, notices) {
    const place = [inst.upazila, inst.district, inst.division].filter(Boolean).join(', ');
    const verifiedBadge = inst.verified ? '<span class="badge badge--verified">Verified</span>'
      : '<span class="badge">Placeholder — claim available</span>';
    const banner = inst.banner_url
      ? `<div class="card" style="padding:0;overflow:hidden;"><img src="${App.escapeHtml(inst.banner_url)}" alt="" style="width:100%;display:block;"></div>`
      : '';
    const logo = inst.logo_url
      ? `<img src="${App.escapeHtml(inst.logo_url)}" alt="" style="width:72px;height:72px;border-radius:14px;object-fit:cover;border:1px solid var(--c-border);" />`
      : `<span class="logo" style="width:72px;height:72px;font-size:1.5rem;">${App.escapeHtml(App.initialsOf(inst.name_en))}</span>`;
    const aboutEn = inst.about_en ? `<p>${App.escapeHtml(inst.about_en)}</p>` : '';
    const aboutBn = inst.about_bn ? `<p lang="bn" style="font-family: 'Noto Sans Bengali', sans-serif;">${App.escapeHtml(inst.about_bn)}</p>` : '';
    let contact = '';
    if (inst.contact_name || inst.contact_phone || inst.contact_email || inst.website) {
      contact = `<div class="card">
        <h3>Contact</h3>
        ${inst.contact_name ? `<p><strong>${App.escapeHtml(inst.contact_name)}</strong></p>` : ''}
        ${inst.contact_phone ? `<p>📞 <a href="tel:${App.escapeHtml(inst.contact_phone)}">${App.escapeHtml(inst.contact_phone)}</a></p>` : ''}
        ${inst.contact_email ? `<p>✉ <a href="mailto:${App.escapeHtml(inst.contact_email)}">${App.escapeHtml(inst.contact_email)}</a></p>` : ''}
        ${inst.website ? `<p>🌐 <a href="${App.escapeHtml(inst.website)}" target="_blank" rel="noopener">${App.escapeHtml(inst.website)}</a></p>` : ''}
        ${inst.address ? `<p>📍 ${App.escapeHtml(inst.address)}</p>` : ''}
      </div>`;
    }
    const noticesHtml = !notices || !notices.length
      ? '<p class="text-muted">No notices posted yet.</p>'
      : notices.map((n) => `
        <article class="card" style="margin-bottom:12px;">
          <div class="flex-between" style="margin-bottom:6px;">
            <h3 style="margin:0;">${n.pinned ? '<span class="badge badge--brand" style="margin-right:6px;">Pinned</span>' : ''}${App.escapeHtml(n.title)}</h3>
            <span class="text-muted" style="font-size:.85rem;">${App.escapeHtml(App.fmtDate(n.created_at))}</span>
          </div>
          <p style="white-space:pre-wrap;">${App.escapeHtml(n.body)}</p>
        </article>`).join('');

    host.innerHTML = `
      ${banner}
      <div class="card" style="margin-top:14px;">
        <div class="flex" style="align-items:flex-start;gap:18px;flex-wrap:wrap;">
          ${logo}
          <div style="flex:1;min-width:240px;">
            <h1 style="margin:0;font-size:1.8rem;">${App.escapeHtml(inst.name_en)}</h1>
            ${inst.name_bn ? `<p lang="bn" style="margin:.2em 0;color:var(--c-text-muted);font-family:'Noto Sans Bengali',sans-serif;">${App.escapeHtml(inst.name_bn)}</p>` : ''}
            <div class="badges">
              <span class="badge badge--brand">${App.escapeHtml(inst.brand)}</span>
              ${verifiedBadge}
              ${inst.category ? '<span class="badge">' + App.escapeHtml(inst.category) + '</span>' : ''}
            </div>
            <p class="text-muted" style="margin-top:8px;">
              <code>${App.escapeHtml(inst.subdomain)}</code>${place ? ' · ' + App.escapeHtml(place) : ''}
              ${inst.eiin ? ' · EIIN ' + App.escapeHtml(inst.eiin) : ''}
            </p>
            ${!inst.verified ? `<a class="btn btn--primary" href="/claim.php?brand=${encodeURIComponent(inst.brand)}&slug=${encodeURIComponent(inst.slug)}">Claim this subdomain</a>` : ''}
          </div>
        </div>
      </div>

      <div class="grid-2 mt-3">
        <section>
          <div class="card">
            <h3>About</h3>
            ${aboutEn || aboutBn || '<p class="text-muted">No description yet.</p>'}
            ${aboutEn && aboutBn ? aboutBn : ''}
          </div>
          <h3 class="mt-4">Notices</h3>
          ${noticesHtml}
        </section>
        <aside>${contact || '<div class="card"><h3>Contact</h3><p class="text-muted">No contact info shared yet.</p></div>'}</aside>
      </div>`;
  }

  Promise.all([
    App.api('/i/' + encodeURIComponent(brand) + '/' + encodeURIComponent(slug)),
    App.api('/i/' + encodeURIComponent(brand) + '/' + encodeURIComponent(slug) + '/notices').catch(() => ({ notices: [] })),
  ]).then(([inst, n]) => {
    render(inst.institution, n.notices || []);
    // v3.2 — JSON-LD is now emitted server-side from institution.php so
    // crawlers see it before any JS runs. We only inject client-side as a
    // fallback when the SSR copy is missing (e.g. the legacy
    // /institution.php?brand=&slug= URL hits an error and we still want
    // search engines to pick something up on render).
    if (!document.querySelector('script[type="application/ld+json"]')) {
      injectJsonLd(inst.institution);
    }
  }).catch((e) => {
    host.innerHTML = `<div class="card">
      <h2>Institution not found</h2>
      <p class="text-muted">${App.escapeHtml(e.detail || 'This subdomain is not in our directory.')}</p>
      <a class="btn btn--primary mt-2" href="/claim.php?brand=${encodeURIComponent(brand)}&slug=${encodeURIComponent(slug)}">Claim ${App.escapeHtml(slug + '.' + brand)}</a>
    </div>`;
  });

  /**
   * v3.0 — emit JSON-LD structured data so search engines can render rich
   * cards for institution pages. We use the EducationalOrganization schema
   * since most tenants are schools / colleges / universities; categories
   * outside that (e.g. NGO) still parse fine because EducationalOrganization
   * is a subtype of Organization.
   */
  function injectJsonLd(i) {
    if (!i) return;
    const place = [i.address, i.upazila, i.district, i.division].filter(Boolean).join(', ');
    const data = {
      '@context': 'https://schema.org',
      '@type': i.category === 'ngo' ? 'NGO' : 'EducationalOrganization',
      name: i.name_en,
      alternateName: i.name_bn || undefined,
      url: 'https://' + i.subdomain,
      sameAs: i.website || undefined,
      logo: i.logo_url || undefined,
      image: i.banner_url || undefined,
      email: i.contact_email || undefined,
      telephone: i.contact_phone || undefined,
      description: i.about_en || i.about_bn || undefined,
      address: place ? { '@type': 'PostalAddress', streetAddress: place, addressCountry: 'BD' } : undefined,
      identifier: i.eiin ? { '@type': 'PropertyValue', propertyID: 'EIIN', value: i.eiin } : undefined,
    };
    Object.keys(data).forEach((k) => { if (data[k] === undefined) delete data[k]; });
    const s = document.createElement('script');
    s.type = 'application/ld+json';
    s.textContent = JSON.stringify(data);
    document.head.appendChild(s);
  }
})();
