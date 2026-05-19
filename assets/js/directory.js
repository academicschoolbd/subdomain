/* Directory page — AJAX search with pagination. */
(function () {
  'use strict';
  const App = window.App;
  const PAGE = 12;
  let offset = 0;
  let total = 0;

  const y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();

  const form = document.querySelector('[data-search-form]');
  const inputQ = form.querySelector('[data-q]');
  const selBrand = form.querySelector('[data-brand]');
  const selCat = form.querySelector('[data-category]');
  const selDiv = form.querySelector('[data-division]');
  const list = document.querySelector('[data-list]');
  const count = document.querySelector('[data-count]');
  const moreBtn = document.querySelector('[data-more]');

  // Preload filters from query string (?brand=…&q=…)
  const qs = new URLSearchParams(location.search);
  if (qs.get('brand')) selBrand.value = qs.get('brand');
  if (qs.get('q')) inputQ.value = qs.get('q');
  if (qs.get('category')) selCat.value = qs.get('category');
  if (qs.get('division')) selDiv.value = qs.get('division');

  function buildParams() {
    return {
      q: inputQ.value.trim() || undefined,
      brand: selBrand.value || undefined,
      category: selCat.value || undefined,
      division: selDiv.value || undefined,
      status: 'verified',
      limit: PAGE,
      offset,
    };
  }

  async function fetchAndRender(append) {
    if (!append) { list.innerHTML = '<div class="dir-skeleton skeleton"></div>'.repeat(6); offset = 0; }
    moreBtn.style.display = 'none';
    try {
      const r = await App.api('/institutions' + App.qs(buildParams()));
      total = r.total || 0;
      if (!append) list.innerHTML = '';
      if (!r.items || !r.items.length) {
        if (!append) list.innerHTML = '<p class="text-muted">No institutions match those filters yet.</p>';
        count.textContent = '0 results';
        return;
      }
      r.items.forEach((i) => list.appendChild(card(i)));
      offset += r.items.length;
      count.textContent = (Math.min(total, offset)) + ' of ' + total + ' result' + (total === 1 ? '' : 's');
      if (offset < total) moreBtn.style.display = '';
    } catch (e) {
      list.innerHTML = '<p class="text-danger">' + App.escapeHtml(e.detail || 'Could not load institutions') + '</p>';
    }
  }

  function card(i) {
    const a = document.createElement('a');
    a.className = 'dir-card';
    a.href = '/institution.php?brand=' + encodeURIComponent(i.brand) + '&slug=' + encodeURIComponent(i.slug);
    const place = [i.upazila, i.district, i.division].filter(Boolean).join(', ');
    a.innerHTML = `
      <div class="flex">
        <span class="logo">${App.escapeHtml(App.initialsOf(i.name_en || i.slug))}</span>
        <div>
          <div class="name">${App.escapeHtml(i.name_en)}</div>
          ${i.name_bn ? `<div class="meta">${App.escapeHtml(i.name_bn)}</div>` : ''}
        </div>
      </div>
      <div class="meta" style="margin-top:6px;">${App.escapeHtml(i.subdomain)}${place ? ' · ' + App.escapeHtml(place) : ''}</div>
      <div class="badges">
        <span class="badge badge--brand">${App.escapeHtml(i.brand)}</span>
        ${i.verified ? '<span class="badge badge--verified">Verified</span>' : ''}
        ${i.category ? '<span class="badge">' + App.escapeHtml(i.category) + '</span>' : ''}
      </div>`;
    return a;
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); fetchAndRender(false); });
  [inputQ].forEach((el) => el.addEventListener('input', App.debounce(() => fetchAndRender(false), 350)));
  [selBrand, selCat, selDiv].forEach((el) => el.addEventListener('change', () => fetchAndRender(false)));
  moreBtn.addEventListener('click', () => fetchAndRender(true));

  fetchAndRender(false);
})();
