/* ===========================================================================
   institution.bd — v5pro Profile-Completion Gate (one-time)
   Three required fields (Full name, Mobile number, Date of birth) with a
   live 0–100% progress bar. After save, the user is redirected back to where
   they came from (?next=…) and never bounced through the gate again.
   =========================================================================== */
(function () {
  'use strict';
  const App = window.App;
  if (!App) return;

  const needAuth = document.querySelector('[data-needs-auth]');
  const root     = document.querySelector('[data-profile-complete-root]');
  const form     = document.querySelector('[data-profile-complete-form]');
  const progressHost = document.querySelector('[data-progress-host]');
  const progressBar  = document.querySelector('[data-progress-bar]');
  const submitBtn    = form ? form.querySelector('[data-submit]') : null;

  // ─── safeNext (mirrors auth-callback.js) ──────────────────────────────────

  function safeNext() {
    try {
      const p = new URLSearchParams(location.search).get('next') || '';
      if (p && typeof p === 'string' && p.startsWith('/') && !p.startsWith('//')) {
        return p;
      }
    } catch (_) { /* fall through */ }
    return '/dashboard.php';
  }

  // ─── BD mobile regex (matches server's normalize/validate) ────────────────
  // Server normalize_phone() accepts: 01XXXXXXXXX, +88… , 880…
  // Resulting +880 + 1 + 9 digits => any 01[0-9]{9} with optional +88/88 prefix.
  const BD_MOBILE_RE = /^(?:\+?88)?01[0-9]{9}$/;

  // ─── Boot ──────────────────────────────────────────────────────────────────

  if (!App.isAuthed()) {
    if (needAuth) needAuth.hidden = false;
    return;
  }

  App.api('/auth/me').then(r => {
    const user = r && r.user;
    if (!user) {
      App.clearSession();
      if (needAuth) needAuth.hidden = false;
      return;
    }
    App.setSession(null, user);

    // One-time enforcement: if the profile is already complete, never show the
    // form again — just redirect to safeNext().
    if (user.profile_complete === true) {
      location.replace(safeNext());
      return;
    }

    if (root) root.hidden = false;
    prefill(user);
    setTodayMax();
    recomputeProgress();
  }).catch(e => {
    if (e && e.status === 401) {
      App.clearSession();
      if (needAuth) needAuth.hidden = false;
    } else {
      App.toast((e && e.detail) || 'Could not load profile', 'error');
    }
  });

  // ─── Prefill name / mobile / dob from existing user row ───────────────────

  function prefill(user) {
    if (!form) return;
    const setVal = (name, val) => {
      const el = form.querySelector(`[name="${name}"]`);
      if (el && val) el.value = val;
    };
    setVal('name',   user.name);
    setVal('mobile', user.mobile);
    if (user.date_of_birth) setVal('date_of_birth', user.date_of_birth);
  }

  function setTodayMax() {
    const dob = form && form.querySelector('[name="date_of_birth"]');
    if (!dob) return;
    const d = new Date();
    const today = d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
    dob.max = today;
    if (!dob.min) dob.min = '1900-01-01';
  }

  // ─── Live progress ─────────────────────────────────────────────────────────

  const REQUIRED_FIELDS = ['name', 'mobile', 'date_of_birth'];

  function recomputeProgress() {
    if (!form || !progressBar || !progressHost) return;
    let filled = 0;
    REQUIRED_FIELDS.forEach(n => {
      const el = form.querySelector(`[name="${n}"]`);
      const v = ((el && el.value) || '').trim();
      if (v) filled++;
    });
    const percent = Math.round(filled / REQUIRED_FIELDS.length * 100);
    progressBar.style.width = percent + '%';
    progressBar.textContent = percent + '%';
    progressHost.setAttribute('aria-valuenow', String(percent));
    if (percent === 100) {
      progressBar.classList.remove('bg-primary');
      progressBar.classList.add('bg-success');
    } else {
      progressBar.classList.remove('bg-success');
      progressBar.classList.add('bg-primary');
    }
    if (submitBtn) submitBtn.disabled = (percent !== 100);
  }

  if (form) {
    REQUIRED_FIELDS.forEach(n => {
      const el = form.querySelector(`[name="${n}"]`);
      if (!el) return;
      el.addEventListener('input', recomputeProgress);
      el.addEventListener('change', recomputeProgress);
    });
  }

  // ─── Submit ────────────────────────────────────────────────────────────────

  function clearErrors() {
    if (!form) return;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('[data-err]').forEach(el => { el.textContent = ''; });
  }

  function showFieldError(name, message) {
    if (!form) return;
    const inp = form.querySelector(`[name="${name}"]`);
    const err = form.querySelector(`[data-err="${name}"]`);
    if (inp) inp.classList.add('is-invalid');
    if (err) err.textContent = message;
  }

  if (form) {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      clearErrors();

      const fd = new FormData(form);
      const body = {
        name:          ((fd.get('name')          || '') + '').trim(),
        mobile:        ((fd.get('mobile')        || '') + '').trim(),
        date_of_birth: ((fd.get('date_of_birth') || '') + '').trim(),
      };

      // Client-side guards (server is the source of truth).
      let hadError = false;
      if (!body.name) {
        showFieldError('name', 'Full name required'); hadError = true;
      } else if (body.name.length > 120) {
        showFieldError('name', 'Full name must be 120 characters or fewer'); hadError = true;
      }
      if (!body.mobile) {
        showFieldError('mobile', 'Mobile number required'); hadError = true;
      } else if (!BD_MOBILE_RE.test(body.mobile.replace(/[\s-]/g, ''))) {
        showFieldError('mobile', 'Enter a valid Bangladesh mobile (e.g. 01712345678)'); hadError = true;
      }
      if (!body.date_of_birth) {
        showFieldError('date_of_birth', 'Date of birth required'); hadError = true;
      }
      if (hadError) return;

      const origHtml = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
      }

      try {
        const r = await App.api('/auth/profile', { method: 'PATCH', body });
        App.setSession(null, r.user);
        sessionStorage.removeItem('profile_redirect_done');
        sessionStorage.setItem('profile_just_completed', '1');
        App.toast('Profile completed!', 'success');
        location.replace(safeNext());
      } catch (e) {
        if (e && e.status === 422 && e.errors) {
          for (const [field, msg] of Object.entries(e.errors)) {
            showFieldError(field, msg);
          }
        } else if (e && e.status === 401) {
          App.clearSession();
          if (needAuth) needAuth.hidden = false;
          if (root) root.hidden = true;
        } else {
          App.toast((e && e.detail) || 'Save failed', 'error');
        }
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origHtml;
        }
      }
    });
  }
})();
