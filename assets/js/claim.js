/* ===========================================================================
   institution.bd — v5pro Claim Wizard
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  const slugForm = document.querySelector('[data-claim-slug-form]');
  const detailsForm = document.querySelector('[data-claim-details-form]');
  const statusEl = document.querySelector('[data-slug-status]');
  const steps = document.querySelectorAll('[data-wizard-steps] .badge');

  let _selectedSlug = '';
  let _selectedBrand = '';

  // Pre-fill from URL params
  const params = new URLSearchParams(location.search);
  if (params.get('slug')) {
    const inp = slugForm?.querySelector('[name="slug"]');
    if (inp) inp.value = params.get('slug');
  }
  if (params.get('brand')) {
    const sel = slugForm?.querySelector('[name="brand"]');
    if (sel) sel.value = params.get('brand');
  }

  // ─── Step 1: Check slug ────────────────────────────────────────────────────

  if (slugForm) {
    slugForm.addEventListener('submit', async e => {
      e.preventDefault();
      const inp = slugForm.querySelector('[name="slug"]');
      const raw = (inp?.value || '').trim().toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
      const brand = slugForm.querySelector('[name="brand"]')?.value || 'institution.bd';

      if (!raw || raw.length < 3) {
        showStatus('Enter at least 3 characters.', 'text-danger');
        return;
      }

      showStatus('<span class="spinner-border spinner-border-sm me-1"></span> Checking...', 'text-muted');

      try {
        const r = await App.api(`/check-slug?slug=${encodeURIComponent(raw)}&brand=${encodeURIComponent(brand)}`);
        if (r.available) {
          showStatus(`<i class="bi bi-check-circle-fill text-success me-1"></i> <strong>${raw}.${brand}</strong> is available!`, 'text-success');
          _selectedSlug = raw;
          _selectedBrand = brand;
          // Require auth before showing step 2
          try {
            await App.requireAuth({ mode: 'signup' });
            showStep(2);
          } catch {}
        } else {
          showStatus(`<i class="bi bi-x-circle-fill text-danger me-1"></i> <strong>${raw}.${brand}</strong> is taken. ${r.reason || 'Try another name.'}`, 'text-danger');
        }
      } catch (err) {
        showStatus(`<i class="bi bi-exclamation-triangle me-1"></i> ${App.escapeHtml(err?.detail || 'Check failed')}`, 'text-danger');
      }
    });
  }

  function showStatus(html, cls) {
    if (!statusEl) return;
    statusEl.innerHTML = html;
    statusEl.className = `form-text ${cls}`;
  }

  function showStep(n) {
    document.querySelectorAll('[data-step]').forEach(s => {
      s.hidden = parseInt(s.getAttribute('data-step'), 10) !== n;
    });
    steps.forEach((s, i) => {
      s.className = i < n ? 'badge rounded-pill bg-success' : i === n - 1 ? 'badge rounded-pill bg-primary' : 'badge rounded-pill bg-secondary-subtle text-muted';
    });
  }

  // ─── Step 2: Submit details ────────────────────────────────────────────────

  if (detailsForm) {
    detailsForm.addEventListener('submit', async e => {
      e.preventDefault();
      if (!App.isAuthed()) { App.openAuthModal({ mode: 'signup' }); return; }

      const fd = Object.fromEntries(new FormData(detailsForm).entries());
      fd.slug = _selectedSlug;
      fd.brand = _selectedBrand;

      const btn = detailsForm.querySelector('[type="submit"]');
      const orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

      try {
        await App.api('/claims', { method: 'POST', body: fd });
        App.toast('Claim submitted successfully!', 'success');
        showStep(3);
      } catch (err) {
        App.toast(err?.detail || 'Submission failed.', 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
      }
    });
  }

  // ─── BD Locations (cascading dropdowns) ────────────────────────────────────

  if (typeof window.bdLocations !== 'undefined') {
    const divSel = document.querySelector('[data-bd-division]');
    const distSel = document.querySelector('[data-bd-district]');
    const upaSel = document.querySelector('[data-bd-upazila]');
    if (divSel && window.bdLocations.divisions) {
      window.bdLocations.divisions.forEach(d => {
        divSel.insertAdjacentHTML('beforeend', `<option value="${d}">${d}</option>`);
      });
    }
  }

})();
