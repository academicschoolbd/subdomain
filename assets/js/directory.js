/* ===========================================================================
   institution.bd — v5pro Directory
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  const grid = document.querySelector('[data-dir-grid]');
  const pagination = document.querySelector('[data-dir-pagination]');
  const qEl = document.querySelector('[data-dir-q]');
  const brandEl = document.querySelector('[data-dir-brand]');
  const divisionEl = document.querySelector('[data-dir-division]');
  const categoryEl = document.querySelector('[data-dir-category]');
  const sortEl = document.querySelector('[data-dir-sort]');

  let _page = 1;
  const _perPage = 12;

  // ─── Populate divisions dropdown ───────────────────────────────────────────

  if (divisionEl && typeof window.bdLocations !== 'undefined' && window.bdLocations.divisions) {
    window.bdLocations.divisions.forEach(d => {
      divisionEl.insertAdjacentHTML('beforeend', `<option value="${d}">${d}</option>`);
    });
  }

  // Pre-fill from URL
  const params = new URLSearchParams(location.search);
  if (params.get('brand') && brandEl) brandEl.value = params.get('brand');

  // ─── Wire Filters ──────────────────────────────────────────────────────────

  const form = document.querySelector('[data-dir-form]');
  if (form) form.addEventListener('submit', e => e.preventDefault());

  if (qEl) {
    const debounced = App.debounce(() => { _page = 1; loadDirectory(); }, 250);
    qEl.addEventListener('input', debounced);
  }
  [brandEl, divisionEl, categoryEl, sortEl].forEach(el => {
    if (el) el.addEventListener('change', () => { _page = 1; loadDirectory(); });
  });

  // ─── Load & Render ─────────────────────────────────────────────────────────

  async function loadDirectory() {
    if (!grid) return;
    grid.innerHTML = Array(6).fill('<div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>').join('');

    const query = {
      status: 'verified',
      q: qEl?.value?.trim() || '',
      brand: brandEl?.value || '',
      division: divisionEl?.value || '',
      category: categoryEl?.value || '',
      sort: sortEl?.value || 'recent',
      limit: _perPage,
      offset: (_page - 1) * _perPage,
    };

    try {
      const r = await App.api('/directory' + App.qs(query));
      const items = r.items || [];
      const total = r.total || items.length;

      if (!items.length) {
        grid.innerHTML = `<div class="col-12 text-center py-5">
          <i class="bi bi-search fs-1 text-muted d-block mb-2"></i>
          <p class="text-muted">No institutions found matching your filters.</p>
        </div>`;
        if (pagination) pagination.innerHTML = '';
        return;
      }

      grid.innerHTML = items.map(i => `
        <div class="col-md-6 col-lg-4">
          <a class="dir-card-v5 d-flex gap-3 h-100" href="${i.subdomain ? `https://${App.escapeHtml(i.subdomain)}` : '#'}" target="_blank" rel="noopener">
            <span class="dir-logo">${i.logo_url ? `<img src="${App.escapeHtml(i.logo_url)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">` : App.escapeHtml(App.initialsOf(i.name_en || i.slug))}</span>
            <div class="flex-grow-1 overflow-hidden">
              <div class="fw-bold small text-truncate">${App.escapeHtml(i.name_en || i.slug)}</div>
              ${i.name_bn ? `<div class="text-muted small text-truncate">${App.escapeHtml(i.name_bn)}</div>` : ''}
              <div class="text-muted" style="font-size:.72rem;">${App.escapeHtml(i.subdomain || '')}</div>
              <div class="mt-1 d-flex flex-wrap gap-1">
                <span class="badge rounded-pill badge-verified">Verified</span>
                <span class="badge rounded-pill badge-brand">${App.escapeHtml(i.brand || '')}</span>
                ${i.category ? `<span class="badge rounded-pill badge-muted">${App.escapeHtml(i.category)}</span>` : ''}
              </div>
              ${i.division ? `<div class="text-muted mt-1" style="font-size:.7rem;"><i class="bi bi-geo-alt me-1"></i>${App.escapeHtml(i.division)}${i.district ? ', ' + App.escapeHtml(i.district) : ''}</div>` : ''}
            </div>
          </a>
        </div>`).join('');

      // Pagination
      renderPagination(total);
    } catch (e) {
      grid.innerHTML = `<div class="col-12"><p class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${App.escapeHtml(e?.detail || 'Failed to load directory')}</p></div>`;
    }
  }

  function renderPagination(total) {
    if (!pagination) return;
    const totalPages = Math.ceil(total / _perPage);
    if (totalPages <= 1) { pagination.innerHTML = ''; return; }

    let html = '<nav><ul class="pagination pagination-sm">';
    html += `<li class="page-item ${_page <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${_page - 1}">&laquo;</a></li>`;
    for (let p = 1; p <= Math.min(totalPages, 7); p++) {
      html += `<li class="page-item ${p === _page ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
    }
    html += `<li class="page-item ${_page >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${_page + 1}">&raquo;</a></li>`;
    html += '</ul></nav>';
    pagination.innerHTML = html;

    pagination.querySelectorAll('[data-page]').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        const p = parseInt(a.getAttribute('data-page'), 10);
        if (p >= 1 && p !== _page) { _page = p; loadDirectory(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
      });
    });
  }

  // Initial load
  loadDirectory();

})();
