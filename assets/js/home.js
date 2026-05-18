/* Homepage dynamic behaviour. Depends on app.js. */
(function () {
  'use strict';
  const App = window.App;

  // Year stamp
  const y = document.querySelector('[data-year]');
  if (y) y.textContent = new Date().getFullYear();

  // ---- WhatsApp community band + footer link ----
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

  // ---- Hero stats (verified / pending / seeded) + 4-tile realtime grid ----
  const liveTiles = {
    users:        document.querySelector('[data-tile="users"]'),
    claims_total: document.querySelector('[data-tile="claims_total"]'),
    pending:      document.querySelector('[data-tile="pending"]'),
    rejected:     document.querySelector('[data-tile="rejected"]'),
  };
  const liveTilesPrev = { users: null, claims_total: null, pending: null, rejected: null };

  function animateNumber(el, from, to) {
    if (!el) return;
    el.parentElement && el.parentElement.classList.remove('is-loading');
    from = Number.isFinite(from) ? from : 0;
    to = Number.isFinite(to) ? to : 0;
    if (from === to) { el.textContent = String(to); return; }
    const start = performance.now();
    const dur = 700;
    function frame(now) {
      const t = Math.min(1, (now - start) / dur);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = Math.round(from + (to - from) * eased);
      el.textContent = String(val);
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
      const pending  = Number(t.claims_pending  ?? 0);

      // 4-tile realtime grid.
      const next = {
        users:        Number(t.users_registered ?? 0),
        claims_total: Number(t.claims_total ?? 0),
        pending:      pending,
        rejected:     Number(t.claims_rejected ?? 0),
      };
      Object.keys(liveTiles).forEach((k) => {
        const el = liveTiles[k];
        const prev = liveTilesPrev[k];
        animateNumber(el, prev == null ? next[k] : prev, next[k]);
        setDelta(k, prev, next[k]);
        liveTilesPrev[k] = next[k];
      });

      // Hero counter pill — "1,234 institutions have already joined"
      const heroTxt = document.querySelector('[data-hero-counter-text]');
      if (heroTxt) {
        const users  = next.users;
        const claims = next.claims_total;
        heroTxt.textContent =
          users.toLocaleString() + ' users · ' +
          claims.toLocaleString() + ' subdomain' + (claims === 1 ? '' : 's') + ' claimed so far';
      }
    } catch (e) {
      // network blip — leave tiles as-is
    }
  }

  refreshStats();
  let refreshTimer = setInterval(refreshStats, 30000);
  // pause polling while the tab is hidden, resume when visible
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(refreshTimer); }
    else { refreshStats(); refreshTimer = setInterval(refreshStats, 30000); }
  });

  // ---- Featured (recently verified) ----
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

  // ---- Slug availability check ----
  const slugInput = document.querySelector('[data-slug-input]');
  const brandSel = document.querySelector('[data-brand-select]');
  const status = document.querySelector('[data-slug-status]');
  const claim = document.querySelector('[data-slug-claim]');
  const form = document.querySelector('[data-slug-form]');

  let _claimUrl = null; // populated when a slug is available

  function setStatus(msg, cls) {
    if (!status) return;
    status.className = 'slug-status ' + (cls || '');
    status.textContent = msg || '';
  }

  function setClaimReady(ready, url) {
    if (!claim) return;
    if (ready) {
      claim.removeAttribute('aria-disabled');
      claim.classList.add('btn--ready');
      claim.textContent = '⚡ Claim it now →';
      _claimUrl = url || null;
    } else {
      claim.setAttribute('aria-disabled', 'true');
      claim.classList.remove('btn--ready');
      claim.textContent = 'Search';
      _claimUrl = null;
    }
  }

  const check = App.debounce(async () => {
    if (!slugInput || !brandSel) return;
    const raw = (slugInput.value || '').trim().toLowerCase();
    if (raw.length < 3) { setStatus('Type at least 3 characters', 'checking'); setClaimReady(false); return; }
    setStatus('Checking…', 'checking');
    try {
      const r = await App.api('/slug-check' + App.qs({ slug: raw, brand: brandSel.value }));
      if (r.available) {
        setStatus('✓ ' + r.normalized + '.' + brandSel.value + ' is available — one click to claim', 'ok');
        setClaimReady(true, '/claim.php?brand=' + encodeURIComponent(brandSel.value)
                     + '&slug=' + encodeURIComponent(r.normalized));
      } else if (r.claimable_placeholder) {
        setStatus('⚑ ' + r.normalized + '.' + brandSel.value + ' is a placeholder — you can claim it', 'ok');
        setClaimReady(true, '/claim.php?brand=' + encodeURIComponent(brandSel.value)
                     + '&slug=' + encodeURIComponent(r.normalized));
      } else {
        const sug = r.suggestion ? ' Try: ' + r.suggestion + '.' + brandSel.value : '';
        setStatus('✗ ' + (r.reason || 'not available') + '.' + sug, 'bad');
        setClaimReady(false);
      }
    } catch (e) {
      setStatus(e.detail || 'Could not check right now', 'bad');
      setClaimReady(false);
    }
  }, 250); // a bit faster — the user feels instant feedback

  if (slugInput) slugInput.addEventListener('input', check);
  if (brandSel) brandSel.addEventListener('change', check);
  if (form) form.addEventListener('submit', (e) => {
    e.preventDefault();
    // Available + URL set → jump straight to the claim wizard.
    if (_claimUrl) { location.href = _claimUrl; return; }
    check();
  });
  // Also: if the user clicks the (now-pulsing) primary button directly,
  // honour the same shortcut.
  if (claim) claim.addEventListener('click', (e) => {
    if (claim.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return; }
    if (_claimUrl) { e.preventDefault(); location.href = _claimUrl; }
  });

  // ---- "Sign in & claim" button -> open auth modal (handled by app.js)
})();
