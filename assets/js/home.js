/* ===========================================================================
   institution.bd — v5pro Homepage
   AJAX slug search, live stats refresh, directory preview.
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
      const s = await App.api('/institutions/stats');
      const t = s.totals || s;
      const next = {
        users: Number(t.users_registered ?? t.users ?? 0),
        claims_total: Number(t.claims_total ?? 0),
        claims_pending: Number(t.claims_pending ?? 0),
        claims_rejected: Number(t.claims_rejected ?? 0),
      };
      Object.keys(next).forEach(key => {
        animateTile(key, next[key], _prevStats[key === 'users' ? 'users_registered' : key]);
      });
      _prevStats = { users_registered: next.users, ...next };
    } catch {}
  }

  function animateTile(key, newVal, oldVal) {
    const el = document.querySelector(`[data-tile="${key}"]`);
    if (!el) return;
    const current = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
    const target = newVal ?? current;
    if (target === current) return;

    const duration = 600;
    const start = performance.now();
    const from = current;
    const to = target;
    function step(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
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

  // First refresh after page load, then every 30s
  setTimeout(refreshStats, 500);
  setInterval(refreshStats, REFRESH_INTERVAL);

  // ─── AJAX Slug Search (live as-you-type) ───────────────────────────────────

  const slugForm = document.querySelector('[data-slug-form]');
  const slugInput = document.querySelector('[data-slug-input]');
  const brandSelect = document.querySelector('[data-brand-select]');
  const resultCard = document.querySelector('[data-search-result]');

  let _lastSlug = '';
  let _searching = false;

  // Normalize slug input: lowercase, replace spaces/special chars with hyphens
  function normalizeSlug(raw) {
    return raw.trim().toLowerCase()
      .replace(/\s+/g, '-')
      .replace(/[^a-z0-9-]/g, '')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  // The main AJAX search function
  async function runSearch() {
    if (!slugInput || !resultCard) return;
    const raw = normalizeSlug(slugInput.value || '');
    const brand = brandSelect?.value || 'institution.bd';

    if (raw.length < 3) {
      if (raw.length > 0) {
        showSearchFeedback('info', 'Type at least 3 characters', 'Letters, digits and hyphens only.');
      } else {
        resultCard.hidden = true;
      }
      return;
    }

    // Don't re-search the same slug
    if (raw === _lastSlug && !_searching) return;
    _lastSlug = raw;

    // Show searching state
    _searching = true;
    showSearchFeedback('loading', 'Checking availability...', `Looking up ${raw}.${brand}`);

    try {
      // Try the v5 endpoint first, fall back to legacy
      let r;
      try {
        r = await App.api(`/slug-check${App.qs({ slug: raw, brand })}`);
      } catch {
        r = await App.api(`/check-slug${App.qs({ slug: raw, brand })}`);
      }

      // Only update if this is still the latest search
      if (raw !== _lastSlug) return;

      const isOk = r.available || r.claimable_placeholder;
      const normalized = r.normalized || raw;
      const fqdn = `${normalized}.${brand}`;

      if (isOk) {
        const claimUrl = `/claim.php?brand=${encodeURIComponent(brand)}&slug=${encodeURIComponent(normalized)}`;
        showResult({
          available: true,
          slug: normalized,
          brand,
          fqdn,
          claimUrl,
          hint: r.claimable_placeholder ? 'This is a placeholder — claim it to take ownership.' : 'This subdomain is free — claim it now before someone else does.',
        });

        // Check alt brand
        const altBrand = brand === 'institution.bd' ? 'smartschool.bd' : 'institution.bd';
        try {
          let altR;
          try { altR = await App.api(`/slug-check${App.qs({ slug: raw, brand: altBrand })}`); }
          catch { altR = await App.api(`/check-slug${App.qs({ slug: raw, brand: altBrand })}`); }
          if (altR.available || altR.claimable_placeholder) {
            showAltBrand(normalized, altBrand);
          }
        } catch {}
      } else {
        const suggestion = r.suggestion ? ` Try: <strong>${App.escapeHtml(r.suggestion)}.${brand}</strong>` : '';
        showResult({
          available: false,
          slug: normalized,
          brand,
          fqdn,
          reason: (r.reason || 'Already taken or reserved.') + suggestion,
        });

        // Check alt brand even when primary is taken
        const altBrand = brand === 'institution.bd' ? 'smartschool.bd' : 'institution.bd';
        try {
          let altR;
          try { altR = await App.api(`/slug-check${App.qs({ slug: raw, brand: altBrand })}`); }
          catch { altR = await App.api(`/check-slug${App.qs({ slug: raw, brand: altBrand })}`); }
          if (altR.available || altR.claimable_placeholder) {
            showAltBrand(normalized, altBrand);
          }
        } catch {}
      }
    } catch (e) {
      if (raw === _lastSlug) {
        showSearchFeedback('error', 'Check failed', e?.detail || 'Network error — please try again.');
      }
    } finally {
      _searching = false;
    }
  }

  function showSearchFeedback(type, title, sub) {
    if (!resultCard) return;
    resultCard.hidden = false;
    const ico = resultCard.querySelector('[data-result-ico]');
    const titleEl = resultCard.querySelector('[data-result-title]');
    const subEl = resultCard.querySelector('[data-result-sub]');
    const cta = resultCard.querySelector('[data-result-cta]');
    const altSection = resultCard.querySelector('[data-result-alt]');

    if (type === 'loading') {
      ico.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
    } else if (type === 'info') {
      ico.innerHTML = '<i class="bi bi-info-circle-fill text-muted fs-4"></i>';
    } else if (type === 'error') {
      ico.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>';
    }
    titleEl.textContent = title;
    subEl.textContent = sub;
    if (cta) cta.hidden = true;
    if (altSection) altSection.hidden = true;
  }

  function showResult(data) {
    if (!resultCard) return;
    resultCard.hidden = false;
    const ico = resultCard.querySelector('[data-result-ico]');
    const titleEl = resultCard.querySelector('[data-result-title]');
    const subEl = resultCard.querySelector('[data-result-sub]');
    const cta = resultCard.querySelector('[data-result-cta]');
    const altSection = resultCard.querySelector('[data-result-alt]');

    if (data.available) {
      ico.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-4"></i>';
      titleEl.textContent = `${data.fqdn} is available!`;
      subEl.textContent = data.hint;
      if (cta) {
        cta.hidden = false;
        cta.href = data.claimUrl;
        cta.textContent = 'Claim Now';
      }
    } else {
      ico.innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-4"></i>';
      titleEl.textContent = `${data.fqdn} is not available`;
      subEl.innerHTML = data.reason || 'Already taken or reserved.';
      if (cta) cta.hidden = true;
    }
    if (altSection) altSection.hidden = true;
  }

  function showAltBrand(slug, altBrand) {
    if (!resultCard) return;
    const altSection = resultCard.querySelector('[data-result-alt]');
    const altName = resultCard.querySelector('[data-alt-name]');
    const altCta = resultCard.querySelector('[data-alt-cta]');
    if (!altSection) return;
    altSection.hidden = false;
    if (altName) altName.textContent = `${slug}.${altBrand}`;
    if (altCta) altCta.href = `/claim.php?slug=${encodeURIComponent(slug)}&brand=${encodeURIComponent(altBrand)}`;
  }

  // ─── Wire Events ───────────────────────────────────────────────────────────

  // Live search as you type (debounced 350ms)
  if (slugInput) {
    const debouncedSearch = App.debounce(runSearch, 350);
    slugInput.addEventListener('input', () => {
      const val = (slugInput.value || '').trim();
      if (val.length === 0) {
        resultCard.hidden = true;
        _lastSlug = '';
      } else {
        debouncedSearch();
      }
    });
  }

  // Also search on brand change
  if (brandSelect) {
    brandSelect.addEventListener('change', () => {
      _lastSlug = ''; // Force re-search
      if ((slugInput?.value || '').trim().length >= 3) runSearch();
    });
  }

  // Form submit also triggers search (for users who hit Enter)
  if (slugForm) {
    slugForm.addEventListener('submit', e => {
      e.preventDefault();
      _lastSlug = ''; // Force fresh search
      runSearch();
    });
  }

  // ─── Featured Directory Preview ────────────────────────────────────────────

  async function loadFeatured() {
    const host = document.querySelector('[data-featured-list]');
    if (!host) return;
    try {
      let r;
      try { r = await App.api('/institutions?status=verified&limit=6'); }
      catch { r = await App.api('/directory?status=verified&limit=6&sort=recent'); }
      const items = r.items || [];
      if (!items.length) {
        host.innerHTML = '<div class="col-12"><p class="text-muted text-center">No verified institutions yet — be the first to claim!</p></div>';
        return;
      }
      host.innerHTML = items.map(i => `
        <div class="col-md-6 col-lg-4">
          <a class="dir-card-v5 d-flex gap-3 text-decoration-none" href="${i.subdomain ? `/institution.php?brand=${encodeURIComponent(i.brand)}&slug=${encodeURIComponent(i.slug)}` : '#'}">
            <span class="dir-logo">${App.escapeHtml(App.initialsOf(i.name_en || i.slug))}</span>
            <div class="flex-grow-1 overflow-hidden">
              <div class="fw-bold small text-truncate">${App.escapeHtml(i.name_en || i.slug)}</div>
              ${i.name_bn ? `<div class="text-muted small text-truncate">${App.escapeHtml(i.name_bn)}</div>` : ''}
              <div class="text-muted" style="font-size:.72rem;">${App.escapeHtml(i.subdomain || (i.slug + '.' + i.brand))}</div>
              <div class="mt-1 d-flex flex-wrap gap-1">
                <span class="badge rounded-pill badge-verified">Verified</span>
                <span class="badge rounded-pill badge-brand">${App.escapeHtml(i.brand)}</span>
                ${i.category ? `<span class="badge rounded-pill badge-muted">${App.escapeHtml(i.category)}</span>` : ''}
              </div>
            </div>
          </a>
        </div>`).join('');
    } catch {
      host.innerHTML = '<div class="col-12"><p class="text-muted small text-center">Could not load directory preview.</p></div>';
    }
  }

  loadFeatured();

})();
