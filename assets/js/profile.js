/* ===========================================================================
   institution.bd — v5pro Profile Edit Page
   Modern ES6+, Bootstrap 5.3 integration.
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  const needAuth = document.querySelector('[data-needs-auth]');
  const profileRoot = document.querySelector('[data-profile-root]');
  const form = document.querySelector('[data-profile-form]');

  // ─── Boot ────────────────────────────────────────────────────────────────

  if (!App.isAuthed()) {
    if (needAuth) needAuth.hidden = false;
    return;
  }

  App.api('/auth/me').then(r => {
    App.setSession(null, r.user);
    if (profileRoot) profileRoot.hidden = false;
    prefillForm(r.user);
  }).catch(e => {
    if (e?.status === 401) {
      App.clearSession();
      if (needAuth) needAuth.hidden = false;
    } else {
      App.toast(e?.detail || 'Could not load profile', 'error');
    }
  });

  // ─── Prefill Form ────────────────────────────────────────────────────────

  function prefillForm(user) {
    if (!form) return;

    const setVal = (name, val) => {
      const el = form.querySelector(`[name="${name}"]`);
      if (el) el.value = val || '';
    };

    setVal('name', user.name);
    setVal('mobile', user.mobile);
    setVal('designation_bn', user.designation_bn);
    setVal('institution_name', user.institution_name);

    // Wire BD location cascading dropdowns with pre-selected values
    const divEl = form.querySelector('[data-bd-division]');
    const distEl = form.querySelector('[data-bd-district]');
    const upaEl = form.querySelector('[data-bd-upazila]');

    if (window.BDLocations && divEl && distEl && upaEl) {
      window.BDLocations.bind(divEl, distEl, upaEl, {
        division: user.division || '',
        district: user.district || '',
        upazila: user.upazila || ''
      });
    }
  }

  // ─── Form Submit ─────────────────────────────────────────────────────────

  if (form) {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      // Clear previous validation state
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      form.querySelectorAll('[data-err]').forEach(el => { el.textContent = ''; });

      const body = Object.fromEntries(new FormData(form).entries());
      const submitBtn = form.querySelector('[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

      try {
        const r = await App.api('/auth/profile', { method: 'PATCH', body });
        App.setSession(null, r.user);
        sessionStorage.removeItem('profile_redirect_done');
        App.toast('Profile saved!', 'success');
      } catch (e) {
        if (e?.status === 422 && e?.errors) {
          for (const [field, msg] of Object.entries(e.errors)) {
            const inp = form.querySelector(`[name="${field}"]`);
            const err = form.querySelector(`[data-err="${field}"]`);
            if (inp) inp.classList.add('is-invalid');
            if (err) err.textContent = msg;
          }
        } else {
          App.toast(e?.detail || 'Save failed', 'error');
        }
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
      }
    });
  }

})();
