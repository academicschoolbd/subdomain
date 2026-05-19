/* Homepage dynamic behaviour. Depends on app.js.
   v3.2 — SSR-seeded stats (no loading state), redesigned search result card. */
(function () {
  'use strict';
  const App = window.App;

  // Year stamp.
  const yEl = document.querySelector('[data-year]');
  if (yEl) yEl.textContent = new Date().getFullYear();

  /* ------------------------------------------------------------------ */
  /*  WhatsApp community band + footer link                              */
  /* ------------------------------------------------------------------ */
  App.getSettings().then((s) => {
    const wa = s.whatsapp || {};
    const band = document.querySelector('[data-wa-band]');
    if (band) {
      if (wa.community_url) {
        const cta = band.querySelector('[data-wa-cta]');
        if (cta) cta.href = wa.community_url;
        const t = band.querySelector('[data-wa-title]');
        if (t && wa.community_title) t.textContent = wa.community_title;
        const sub = band.querySelector('[data-wa-sub]');
        if (sub && wa.community_subtitle) sub.textContent = wa.community_subtitle;
      } else {
        const cta = band.querySelector('[data-wa-cta]');
        if (cta) {
          cta.textContent = 'Community coming soon';
          cta.removeAttribute('href');
          cta.setAttribute('aria-disabled', 'true');
          cta.style.opacity = '.7';
          cta.style.cursor = 'not-allowed';
        }
      }
    }
    const f1 = document.querySelector('[data-wa-footer-link]');
    if (f1) {
      if (wa.community_url) f1.href = wa.community_url;
      else { f1.removeAttribute('href'); f1.textContent = 'Community coming soon'; }
    }
    const f2 = document.querySelector('[data-wa-footer-support]');
    if (f2) {
      if (wa.support_url) f2.href = wa.support_url;
      else { f2.removeAttribute('href'); f2.textContent = 'Support coming soon'; }
    }
  }).catch(() => {});

  /* ------------------------------------------------------------------ */
  /*  Live realtime stats — SSR-seeded, refresh every 30 s, no skeleton  */
  /* ------------------------------------------------------------------ */
  const liveTiles = {
    users:        document.querySelector('[data-tile="users"]'),
    claims_total: document.querySelector('[data-tile="claims_total"]'),
    pending:      document.querySelector('[data-tile="pending"]'),
    rejected:     document.querySelector('[data-tile="rejected"]'),
  };
  // Seed previous values from the SSR-rendered numbers so the first refresh
  // animates only genuine deltas (no jump from 0 → real).
  const seed = window.__INITIAL_STATS__ || {};
  const liveTilesPrev = {
    users:        Number.isFinite(seed.users_registered) ? Number(seed.users_registered) : null,
    claims_total: Number.isFinite(seed.claims_total)     ? Number(seed.claims_total)     : null,
    pending:      Number.isFinite(seed.claims_pending)   ? Number(seed.claims_pending)   : null,
    rejected:     Number.isFinite(seed.claims_rejected)  ? Number(seed.claims_rejected)  : null,
  };

  function animateNumber(el, from, to) {
    if (!el) return;
    el.parentElement && el.parentElement.classList.remove('is-loading');
    from = Number.isFinite(from) ? from : 0;
    to = Number.isFinite(to) ? to : 0;
    if (from === to) { el.textContent = to.toLocaleString(); return; }
    const start = performance.now();
    const dur = 700;
    function frame(now) {
      const t = Math.min(1, (now - start) / dur);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = Math.round(from + (to - from) * eased);
      el.textContent = val.toLocaleString();
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function setDelta(key, prev, next) {
    const el = document.querySelector('[data-tile-delta="' + key + '"]');
    if (!el) return;
    if (prev === null || prev === undefined || prev === next) {
      el.textContent = '';
      el.style.color = '';
      return;
    }
    const diff = next - prev;
    if (diff === 0) { el.textContent = ''; return; }
    const sign = diff > 0 ? '+' : '';
    el.textContent = sign + diff + ' since last refresh';
    el.style.color = diff > 0 ? 'var(--c-success)' : 'var(--c-danger)';
    el.style.opacity = '1';
    setTimeout(() => { el.style.transition = 'opacity 1.2s ease'; el.style.opacity = '.55'; }, 2400);
  }

  async function refreshStats() {
    try {
      const r = await App.api('/institutions/stats');
      const t = r.totals || {};
      const next = {
        users:        Number(t.users_registered ?? 0),
        claims_total: Number(t.claims_total     ?? 0),
        pending:      Number(t.claims_pending   ?? 0),
        rejected:     Number(t.claims_rejected  ?? 0),
      };
      Object.keys(liveTiles).forEach((k) => {
        const el = liveTiles[k];
        const prev = liveTilesPrev[k];
        animateNumber(el, prev == null ? next[k] : prev, next[k]);
        setDelta(k, prev, next[k]);
        liveTilesPrev[k] = next[k];
      });
    } catch (e) {
      // network blip — leave SSR-rendered tiles as-is
    }
  }
  // Kick off the first network refresh after SSR paint, then every 30 s.
  setTimeout(refreshStats, 1500);
  let refreshTimer = setInterval(refreshStats, 30000);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(refreshTimer); }
    else { refreshStats(); refreshTimer = setInterval(refreshStats, 30000); }
  });

  /* ------------------------------------------------------------------ */
  /*  Featured (recently verified) directory cards                       */
  /* ------------------------------------------------------------------ */
  App.api('/institutions?status=verified&limit=6').then((r) => {
    const host = document.querySelector('[data-featured-list]');
    if (!host) return;
    if (!r.items || !r.items.length) {
      host.innerHTML = '<p class="text-muted">No verified institutions yet — be the first to claim!</p>';
      return;
    }
    host.innerHTML = '';
    r.items.forEach((i) => host.appendChild(renderInstCard(i)));
  }).catch(() => {});

  function renderInstCard(i) {
    const a = document.createElement('a');
    a.className = 'dir-card';
    a.href = '/institution.php?brand=' + encodeURIComponent(i.brand) + '&slug=' + encodeURIComponent(i.slug);
    const initials = App.initialsOf(i.name_en || i.slug);
    const place = [i.upazila, i.district, i.division].filter(Boolean).join(', ');
    a.innerHTML = `
      <div class="flex">
        <span class="logo">${App.escapeHtml(initials)}</span>
        <div>
          <div class="name">${App.escapeHtml(i.name_en)}</div>
          ${i.name_bn ? `<div class="meta">${App.escapeHtml(i.name_bn)}</div>` : ''}
        </div>
      </div>
      <div class="meta" style="margin-top:6px;">
        ${App.escapeHtml(i.subdomain)}${place ? ' · ' + App.escapeHtml(place) : ''}
      </div>
      <div class="badges">
        <span class="badge badge--brand">${App.escapeHtml(i.brand)}</span>
        ${i.verified ? '<span class="badge badge--verified">Verified</span>' : ''}
        ${i.category ? '<span class="badge">' + App.escapeHtml(i.category) + '</span>' : ''}
      </div>`;
    return a;
  }

  /* ------------------------------------------------------------------ */
  /*  Search → result card                                               */
  /* ------------------------------------------------------------------ */
  const slugInput = document.querySelector('[data-slug-input]');
  const brandSel  = document.querySelector('[data-brand-select]');
  const form      = document.querySelector('[data-slug-form]');
  const submitBtn = document.querySelector('[data-slug-claim]');
  const result    = document.querySelector('[data-search-result]');
  const resIco    = document.querySelector('[data-result-ico]');
  const resTitle  = document.querySelector('[data-result-title]');
  const resSub    = document.querySelector('[data-result-sub]');
  const resCta    = document.querySelector('[data-result-cta]');
  const resAlt    = document.querySelector('[data-result-alt]');
  const altName   = document.querySelector('[data-alt-name]');
  const altCta    = document.querySelector('[data-alt-cta]');

  const ICO_OK   = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
  const ICO_BAD  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
  const ICO_INFO = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

  function hideResult() {
    if (result) result.hidden = true;
  }

  function showResult({ kind, title, sub, cta, alt }) {
    if (!result) return;
    result.hidden = false;
    result.classList.remove('search-result--ok', 'search-result--bad', 'search-result--info');
    result.classList.add('search-result--' + (kind || 'info'));
    if (resIco)   resIco.innerHTML = kind === 'ok' ? ICO_OK : (kind === 'bad' ? ICO_BAD : ICO_INFO);
    if (resTitle) resTitle.textContent = title || '';
    if (resSub)   resSub.textContent   = sub   || '';
    if (resCta) {
      if (cta && cta.url) {
        resCta.hidden = false;
        resCta.textContent = cta.label || 'Claim Now';
        resCta.setAttribute('href', cta.url);
      } else {
        resCta.hidden = true;
      }
    }
    if (resAlt) {
      if (alt && alt.fqdn) {
        resAlt.hidden = false;
        if (altName) altName.textContent = alt.fqdn;
        if (altCta)  altCta.setAttribute('href', alt.url || '#');
      } else {
        resAlt.hidden = true;
      }
    }
  }

  async function checkBrand(slug, brand) {
    try {
      const r = await App.api('/slug-check' + App.qs({ slug, brand }));
      return { brand, ...r };
    } catch (e) {
      return { brand, available: false, normalized: slug, reason: (e && e.detail) || 'check failed' };
    }
  }

  async function runSearch() {
    if (!slugInput || !brandSel) return;
    const raw   = (slugInput.value || '').trim().toLowerCase();
    const brand = brandSel.value;
    if (raw.length < 3) {
      showResult({
        kind: 'info',
        title: 'Type at least 3 characters',
        sub: 'Letters, digits and hyphens only — minimum 3 characters.',
      });
      return;
    }
    showResult({ kind: 'info', title: 'Searching…', sub: 'Checking availability across both brands.' });

    // Check the chosen brand first; in parallel check the sibling brand for
    // the "Also available" suggestion.
    const sibling = brand === 'institution.bd' ? 'smartschool.bd' : 'institution.bd';
    const [primary, alt] = await Promise.all([
      checkBrand(raw, brand),
      checkBrand(raw, sibling),
    ]);

    const isOk = primary.available || primary.claimable_placeholder;
    const fqdn = (primary.normalized || raw) + '.' + brand;

    if (isOk) {
      const claimUrl = '/claim.php?brand=' + encodeURIComponent(brand)
                     + '&slug=' + encodeURIComponent(primary.normalized);
      const altOk    = alt.available || alt.claimable_placeholder;
      const altFqdn  = (alt.normalized || raw) + '.' + sibling;
      const altUrl   = '/claim.php?brand=' + encodeURIComponent(sibling)
                     + '&slug=' + encodeURIComponent(alt.normalized || raw);
      showResult({
        kind: 'ok',
        title: fqdn + ' is available!',
        sub: primary.claimable_placeholder
          ? 'This is a verified-placeholder. Claim it now to take ownership.'
          : 'Claim it now before someone else does.',
        cta: { url: claimUrl, label: 'Claim Now' },
        alt: altOk ? { fqdn: altFqdn, url: altUrl } : null,
      });
    } else {
      const altOk    = alt.available || alt.claimable_placeholder;
      const altFqdn  = (alt.normalized || raw) + '.' + sibling;
      const altUrl   = '/claim.php?brand=' + encodeURIComponent(sibling)
                     + '&slug=' + encodeURIComponent(alt.normalized || raw);
      const sug      = primary.suggestion ? ' Try ' + primary.suggestion + '.' + brand : '';
      showResult({
        kind: 'bad',
        title: fqdn + ' is not available',
        sub: (primary.reason || 'Already taken or reserved.') + sug,
        cta: null,
        alt: altOk ? { fqdn: altFqdn, url: altUrl } : null,
      });
    }
  }

  // Live availability hint: as the user types, hide stale results and
  // soft-prompt them — but don't fire requests until they hit Search.
  if (slugInput) {
    slugInput.addEventListener('input', App.debounce(() => {
      const raw = (slugInput.value || '').trim();
      if (raw.length === 0) { hideResult(); return; }
      // Live preflight check — same as before, but now drives the fancy card.
      runSearch();
    }, 300));
  }
  if (brandSel) brandSel.addEventListener('change', () => {
    if ((slugInput.value || '').trim().length >= 3) runSearch();
  });
  if (form) form.addEventListener('submit', (e) => { e.preventDefault(); runSearch(); });
})();
