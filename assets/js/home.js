/* ===========================================================================
   institution.bd — v5pro Homepage
   Slug search, live stats refresh, directory preview.
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  // ─── Live Stats Auto-Refresh ───────────────────────────────────────────────

  let _prevStats = window.__INITIAL_STATS__ || {};
  const REFRESH_INTERVAL = 30000;

  async function refreshStats() {
    try {
      const s = await App.api('/stats');
      animateTile('users', s.users_registered, _prevStats.users_registered);
      animateTile('claims_total', s.claims_total, _prevStats.claims_total);
      if (document.querySelector('[data-tile="claims_pending"]')) {
        animateTile('claims_pending', s.claims_pending, _prevStats.claims_pending);
      }
      if (document.querySelector('[data-tile="claims_rejected"]')) {
        animateTile('claims_rejected', s.claims_rejected, _prevStats.claims_rejected);
      }
      _prevStats = s;
    } catch {}
  }

  function animateTile(key, newVal, oldVal) {
    const el = document.querySelector(`[data-tile="${key}"]`);
    if (!el) return;
    const current = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
    const target = newVal ?? current;
    if (target === current) return;

    // Smooth counter animation
    const duration = 600;
    const start = performance.now();
    const from = current;
    const to = target;
    function step(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
      el.textContent = Math.round(from + (to - from) * eased).toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);

    // Delta indicator
    const deltaEl = document.querySelector(`[data-tile-delta="${key}"]`);
    if (deltaEl && oldVal != null && target !== oldVal) {
      const diff = target - oldVal;
      deltaEl.textContent = diff > 0 ? `+${diff} since last refresh` : `${diff} since last refresh`;
      deltaEl.style.color = diff > 0 ? 'var(--bs-success)' : 'var(--bs-danger)';
    }
  }

  // Start refresh cycle
  setInterval(refreshStats, REFRESH_INTERVAL);

  // ─── Slug Search ───────────────────────────────────────────────────────────

  const slugForm = document.querySelector('[data-slug-form]');
  const slugInput = document.querySelector('[data-slug-input]');
  const brandSelect = document.querySelector('[data-brand-select]');
  const resultCard = document.querySelector('[data-search-result]');

  if (slugForm) {
    slugForm.addEventListener('submit', async e => {
      e.preventDefault();
      const raw = (slugInput?.value || '').trim().toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
      if (!raw || raw.length < 3) { App.toast('Enter at least 3 characters', 'error'); return; }
      const brand = brandSelect?.value || 'institution.bd';

      try {
        const r = await App.api(`/check-slug?slug=${encodeURIComponent(raw)}&brand=${encodeURIComponent(brand)}`);
        showResult(r, raw, brand);
      } catch (e2) {
        App.toast(e2?.detail || 'Check failed', 'error');
      }
    });
  }

  function showResult(r, slug, brand) {
    if (!resultCard) return;
    resultCard.hidden = false;
    const ico = resultCard.querySelector('[data-result-ico]');
    const title = resultCard.querySelector('[data-result-title]');
    const sub = resultCard.querySelector('[data-result-sub]');
    const cta = resultCard.querySelector('[data-result-cta]');
    const altSection = resultCard.querySelector('[data-result-alt]');
    const altName = resultCard.querySelector('[data-alt-name]');
    const altCta = resultCard.querySelector('[data-alt-cta]');

    if (r.available) {
      ico.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-4"></i>';
      title.textContent = `${slug}.${brand} is available!`;
      sub.textContent = 'This subdomain is free — claim it now before someone else does.';
      cta.hidden = false;
      cta.href = `/claim.php?slug=${encodeURIComponent(slug)}&brand=${encodeURIComponent(brand)}`;
    } else {
      ico.innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-4"></i>';
      title.textContent = `${slug}.${brand} is taken`;
      sub.textContent = r.reason || 'Try a different name or check the other brand.';
      cta.hidden = true;
    }

    // Alt brand suggestion
    if (r.alt_available && r.alt_brand) {
      altSection.hidden = false;
      altName.textContent = `${slug}.${r.alt_brand}`;
      altCta.href = `/claim.php?slug=${encodeURIComponent(slug)}&brand=${encodeURIComponent(r.alt_brand)}`;
    } else {
      altSection.hidden = true;
    }
  }

  // ─── Featured Directory Preview ────────────────────────────────────────────

  async function loadFeatured() {
    const host = document.querySelector('[data-featured-list]');
    if (!host) return;
    try {
      const r = await App.api('/directory?status=verified&limit=6&sort=recent');
      const items = r.items || [];
      if (!items.length) { host.innerHTML = '<div class="col-12"><p class="text-muted">No verified institutions yet.</p></div>'; return; }
      host.innerHTML = items.map(i => `
        <div class="col-md-6 col-lg-4">
          <div class="dir-card-v5 d-flex gap-3">
            <span class="dir-logo">${App.escapeHtml(App.initialsOf(i.name_en || i.slug))}</span>
            <div class="flex-grow-1 min-width-0">
              <div class="fw-bold small text-truncate">${App.escapeHtml(i.name_en || i.slug)}</div>
              <div class="text-muted" style="font-size:.75rem;">${App.escapeHtml(i.subdomain)}</div>
              <div class="mt-1">
                <span class="badge rounded-pill badge-verified">Verified</span>
                <span class="badge rounded-pill badge-brand">${App.escapeHtml(i.brand)}</span>
              </div>
            </div>
          </div>
        </div>`).join('');
    } catch {
      host.innerHTML = '<div class="col-12"><p class="text-muted small">Could not load directory.</p></div>';
    }
  }

  loadFeatured();

})();
