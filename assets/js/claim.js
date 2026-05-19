/* ===========================================================================
   institution.bd — v5pro Claim Wizard (AJAX-powered)
   Flow: Check Slug → Auth Gate → Profile Gate → Privacy Agreement → Submit
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  // DOM references
  const slugForm = document.querySelector('[data-claim-slug-form]');
  const detailsForm = document.querySelector('[data-claim-details-form]');
  const statusEl = document.querySelector('[data-slug-status]');
  const stepsHost = document.querySelector('[data-wizard-steps]');

  let _selectedSlug = '';
  let _selectedBrand = '';
  let _userProfile = null;

  // ─── Pre-fill from URL params (when coming from homepage search) ───────────

  const params = new URLSearchParams(location.search);
  const preSlug = params.get('slug') || '';
  const preBrand = params.get('brand') || '';

  if (preSlug && slugForm) {
    const inp = slugForm.querySelector('[name="slug"]');
    if (inp) inp.value = preSlug;
  }
  if (preBrand && slugForm) {
    const sel = slugForm.querySelector('[name="brand"]');
    if (sel) sel.value = preBrand;
  }

  // If slug came from URL, auto-check availability on page load
  if (preSlug && preSlug.length >= 3) {
    setTimeout(() => checkSlugAvailability(preSlug, preBrand || 'institution.bd'), 300);
  }

  // ─── Step Management ───────────────────────────────────────────────────────

  function showStep(n) {
    document.querySelectorAll('[data-step]').forEach(s => {
      const stepNum = s.getAttribute('data-step');
      s.hidden = stepNum !== String(n);
    });
    // Update step indicators
    if (stepsHost) {
      const badges = stepsHost.querySelectorAll('.badge');
      badges.forEach((b, i) => {
        if (i + 1 < n) b.className = 'badge rounded-pill bg-success';
        else if (i + 1 === n) b.className = 'badge rounded-pill bg-primary';
        else b.className = 'badge rounded-pill bg-secondary-subtle text-muted';
      });
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showStatus(html, cls) {
    if (!statusEl) return;
    statusEl.innerHTML = html;
    statusEl.className = `form-text ${cls || ''}`;
  }

  // ─── Step 1: AJAX Slug Check ───────────────────────────────────────────────

  async function checkSlugAvailability(raw, brand) {
    raw = raw.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/-+/g, '-').replace(/^-|-$/g, '');
    if (!raw || raw.length < 3) {
      showStatus('<i class="bi bi-info-circle me-1"></i> Enter at least 3 characters (letters, digits, hyphens).', 'text-muted');
      return;
    }

    showStatus('<span class="spinner-border spinner-border-sm me-1"></span> Checking availability...', 'text-primary');

    try {
      let r;
      try { r = await App.api(`/slug-check${App.qs({ slug: raw, brand })}`); }
      catch { r = await App.api(`/check-slug${App.qs({ slug: raw, brand })}`); }

      const isOk = r.available || r.claimable_placeholder;
      const normalized = r.normalized || raw;

      if (isOk) {
        _selectedSlug = normalized;
        _selectedBrand = brand;
        showStatus(
          `<i class="bi bi-check-circle-fill text-success me-1"></i> <strong>${normalized}.${brand}</strong> is available! ` +
          (r.claimable_placeholder ? '<span class="text-muted">(placeholder — you can claim it)</span>' : ''),
          'text-success'
        );
        // Auto-advance: check auth then profile
        await advanceAfterSlugOk();
      } else {
        const suggestion = r.suggestion ? ` Try: <strong>${App.escapeHtml(r.suggestion)}</strong>` : '';
        showStatus(
          `<i class="bi bi-x-circle-fill text-danger me-1"></i> <strong>${normalized}.${brand}</strong> is not available. ` +
          `${App.escapeHtml(r.reason || 'Already taken or reserved.')}${suggestion}`,
          'text-danger'
        );
      }
    } catch (e) {
      showStatus(`<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> ${App.escapeHtml(e?.detail || 'Check failed — try again.')}`, 'text-warning');
    }
  }

  // Wire slug form submit
  if (slugForm) {
    slugForm.addEventListener('submit', e => {
      e.preventDefault();
      const inp = slugForm.querySelector('[name="slug"]');
      const brand = slugForm.querySelector('[name="brand"]')?.value || 'institution.bd';
      checkSlugAvailability(inp?.value || '', brand);
    });

    // Also check as you type (debounced)
    const slugInput = slugForm.querySelector('[name="slug"]');
    if (slugInput) {
      const debounced = App.debounce(() => {
        const brand = slugForm.querySelector('[name="brand"]')?.value || 'institution.bd';
        const val = (slugInput.value || '').trim();
        if (val.length >= 3) {
          checkSlugAvailability(val, brand);
        } else if (val.length > 0) {
          showStatus('<i class="bi bi-info-circle me-1"></i> Type at least 3 characters.', 'text-muted');
        } else {
          showStatus('', '');
        }
      }, 400);
      slugInput.addEventListener('input', debounced);
    }

    // Re-check on brand change
    const brandSel = slugForm.querySelector('[name="brand"]');
    if (brandSel) {
      brandSel.addEventListener('change', () => {
        const val = (slugForm.querySelector('[name="slug"]')?.value || '').trim();
        if (val.length >= 3) checkSlugAvailability(val, brandSel.value);
      });
    }
  }

  // ─── Auth + Profile Gate ───────────────────────────────────────────────────

  async function advanceAfterSlugOk() {
    // 1. Require authentication
    if (!App.isAuthed()) {
      try {
        await App.requireAuth({
          mode: 'signup',
          title: `Sign in to claim ${_selectedSlug}.${_selectedBrand}`,
          sub: 'Create a free account — no payment needed.',
        });
      } catch {
        return; // User closed the auth modal
      }
    }

    // 2. Check if profile is complete
    try {
      const r = await App.api('/auth/me');
      _userProfile = r.user;
      App.setSession(null, r.user);

      if (!r.user.profile_complete) {
        // Profile incomplete — show details form (step 2) for them to fill
        prefillDetailsForm(r.user);
        showStep(2);
      } else {
        // Profile complete — go straight to privacy agreement
        prefillDetailsForm(r.user);
        showPrivacyDialog();
      }
    } catch (e) {
      if (e?.status === 401) {
        App.clearSession();
        App.openAuthModal({ mode: 'signup' });
      } else {
        App.toast(e?.detail || 'Could not verify your profile', 'error');
      }
    }
  }

  // ─── Step 2: Institution Details ───────────────────────────────────────────

  function prefillDetailsForm(user) {
    if (!detailsForm || !user) return;
    const set = (name, val) => {
      const el = detailsForm.querySelector(`[name="${name}"]`);
      if (el && !el.value && val) el.value = val;
    };
    set('name_en', user.institution_name || '');
    set('contact_name', user.name || '');
    set('contact_phone', user.mobile || '');
    set('contact_email', user.email || '');

    // Wire BD locations cascade
    if (window.BDLocations) {
      const divEl = detailsForm.querySelector('[data-bd-division]');
      const distEl = detailsForm.querySelector('[data-bd-district]');
      const upaEl = detailsForm.querySelector('[data-bd-upazila]');
      if (divEl) {
        window.BDLocations.bind(divEl, distEl, upaEl, {
          division: user.division || '',
          district: user.district || '',
          upazila: user.upazila || '',
        });
      }
    }
  }

  if (detailsForm) {
    detailsForm.addEventListener('submit', e => {
      e.preventDefault();
      if (!App.isAuthed()) { App.openAuthModal({ mode: 'signup' }); return; }
      // Show privacy agreement before final submission
      showPrivacyDialog();
    });
  }

  // ─── Privacy Policy Agreement Dialog ───────────────────────────────────────

  function showPrivacyDialog() {
    // Create and show a Bootstrap modal for privacy agreement
    let modal = document.getElementById('privacyAgreeModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'privacyAgreeModal';
      modal.className = 'modal fade';
      modal.tabIndex = -1;
      modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary fs-4"></i>
                <h5 class="modal-title mb-0">Confirm your claim</h5>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-light border mb-3">
                <div class="d-flex align-items-start gap-2">
                  <i class="bi bi-globe2 text-primary mt-1"></i>
                  <div>
                    <strong data-confirm-domain></strong>
                    <div class="text-muted small" data-confirm-brand></div>
                  </div>
                </div>
              </div>
              <p class="small text-muted mb-3">
                By submitting this claim, you confirm that:
              </p>
              <ul class="small text-muted mb-3">
                <li>You represent a legitimate Bangladeshi educational institution or NGO</li>
                <li>The information you provided is accurate and truthful</li>
                <li>You agree to the <a href="/terms.php" target="_blank">Terms of Service</a> and <a href="/privacy.php" target="_blank">Privacy Policy</a></li>
                <li>You understand the admin may request verification documents</li>
              </ul>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="agreePrivacy" data-agree-check>
                <label class="form-check-label small fw-semibold" for="agreePrivacy">
                  I agree to the terms, privacy policy, and acceptable use policy
                </label>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" data-confirm-submit disabled>
                <i class="bi bi-send me-1"></i> Submit Claim to Admin
              </button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modal);

      // Wire checkbox to enable/disable submit
      const checkbox = modal.querySelector('[data-agree-check]');
      const submitBtn = modal.querySelector('[data-confirm-submit]');
      checkbox.addEventListener('change', () => {
        submitBtn.disabled = !checkbox.checked;
      });

      // Wire submit button
      submitBtn.addEventListener('click', () => submitClaim(modal));
    }

    // Fill in the domain info
    modal.querySelector('[data-confirm-domain]').textContent = `${_selectedSlug}.${_selectedBrand}`;
    modal.querySelector('[data-confirm-brand]').textContent = `Brand: ${_selectedBrand}`;

    // Reset state
    const checkbox = modal.querySelector('[data-agree-check]');
    const submitBtn = modal.querySelector('[data-confirm-submit]');
    checkbox.checked = false;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Submit Claim to Admin';

    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  }

  // ─── Final Submission ──────────────────────────────────────────────────────

  async function submitClaim(modal) {
    const submitBtn = modal.querySelector('[data-confirm-submit]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

    // Collect form data from step 2 (if visible)
    const body = { slug: _selectedSlug, brand: _selectedBrand };
    if (detailsForm) {
      const fd = new FormData(detailsForm);
      fd.forEach((v, k) => { if (v) body[k] = v; });
    }

    try {
      const r = await App.api('/claims', { method: 'POST', body });

      // Close modal
      const bsModal = bootstrap.Modal.getInstance(modal);
      if (bsModal) bsModal.hide();

      // Show success
      if (r.auto_verified) {
        App.toast(`${_selectedSlug}.${_selectedBrand} is now LIVE!`, 'success');
      } else {
        App.toast('Claim submitted for admin review!', 'success');
      }
      showStep(3);
    } catch (e) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Submit Claim to Admin';

      if (e?.profile_incomplete) {
        // Close modal, show details form
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
        App.toast('Please complete your profile first', 'error');
        showStep(2);
      } else {
        App.toast(e?.detail || 'Submission failed — please try again.', 'error');
      }
    }
  }

  // ─── BD Locations (cascading dropdowns) ────────────────────────────────────

  if (window.BDLocations) {
    const divSel = document.querySelector('[data-bd-division]');
    const distSel = document.querySelector('[data-bd-district]');
    const upaSel = document.querySelector('[data-bd-upazila]');
    if (divSel && distSel && upaSel) {
      window.BDLocations.bind(divSel, distSel, upaSel, {});
    }
  }

})();
