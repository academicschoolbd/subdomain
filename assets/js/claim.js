/* Claim wizard: pick subdomain → details → document. */
(function () {
  'use strict';
  const App = window.App;

  const y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();

  const qs = new URLSearchParams(location.search);
  let state = {
    brand: qs.get('brand') === 'smartschool.bd' ? 'smartschool.bd' : 'institution.bd',
    slug: (qs.get('slug') || '').toLowerCase(),
    inst: null, // server-returned claim after step 2
  };

  /* ---------- step navigation ---------- */
  const stepNum = document.querySelector('[data-step-current]');
  const stepTotal = document.querySelector('[data-step-total]');
  const progress = document.querySelectorAll('[data-progress] > span');
  // Map step ids -> position in the progress bar (1-indexed).
  // The "profile" gate sits between subdomain (1) and institution details (2/3).
  const stepIndex = { '1': 1, 'profile': 2, '2': 3, '3': 4, 'done': 4 };
  function showStep(n) {
    document.querySelectorAll('[data-step]').forEach((el) => { el.hidden = el.getAttribute('data-step') !== String(n); });
    const idx = stepIndex[String(n)] || 1;
    if (stepNum) stepNum.textContent = String(idx);
    progress.forEach((s, i) => { s.classList.toggle('active', i < idx); });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // v3.0 — fetch platform settings up front. When require_documents is OFF
  // (the default), we collapse the wizard to 3 steps (subdomain → profile →
  // details) and the submit button on step 2 reads "Claim & go live".
  let _platform = { require_documents: false, instant_claim: true };
  function applyPlatformMode() {
    const noDocs = !_platform.require_documents;
    const submit2 = document.querySelector('[data-step2-form] button[type="submit"]');
    if (submit2) submit2.textContent = noDocs ? '⚡ Claim & go live →' : 'Save & continue →';
    if (noDocs) {
      // Hide the "4. Verification" pill in the progress bar; total = 3.
      if (progress.length >= 4) progress[3].style.display = 'none';
      if (stepTotal) stepTotal.textContent = '3';
    } else {
      if (progress.length >= 4) progress[3].style.display = '';
      if (stepTotal) stepTotal.textContent = '4';
    }
  }
  App.getSettings().then((s) => {
    if (s && s.platform) _platform = s.platform;
    applyPlatformMode();
  }).catch(() => applyPlatformMode());

  /** Fetch the freshest user from /auth/me — falls back to the cached
   *  localStorage copy if the call fails. */
  async function getFreshUser() {
    if (!App.isAuthed()) return null;
    try {
      const r = await App.api('/auth/me');
      if (r && r.user) { App.setSession(null, r.user); return r.user; }
    } catch (e) {
      if (e && e.status === 401) { App.clearSession(); return null; }
    }
    return App.getUser();
  }

  /* ---------- step 1 ---------- */
  const brandBtns = document.querySelectorAll('[data-step="1"] .seg__btn');
  const slugInput = document.querySelector('[data-slug]');
  const suffix = document.querySelector('[data-suffix]');
  const status = document.querySelector('[data-slug-status]');
  const nextBtn = document.querySelector('[data-next]');

  function syncBrand() {
    brandBtns.forEach((b) => b.classList.toggle('active', b.getAttribute('data-brand') === state.brand));
    suffix.textContent = '.' + state.brand;
  }
  brandBtns.forEach((b) => {
    b.addEventListener('click', () => { state.brand = b.getAttribute('data-brand'); syncBrand(); check(); });
  });
  if (state.slug) slugInput.value = state.slug;
  syncBrand();

  const setStatus = (msg, cls) => { status.className = 'slug-status ' + (cls || ''); status.textContent = msg || ''; };
  let last = '';
  const check = App.debounce(async () => {
    const s = (slugInput.value || '').trim().toLowerCase();
    if (s.length < 3) { setStatus('Type at least 3 characters', 'checking'); nextBtn.disabled = true; return; }
    setStatus('Checking…', 'checking');
    last = s;
    try {
      const r = await App.api('/slug-check' + App.qs({ slug: s, brand: state.brand }));
      if (last !== s) return;
      if (r.available || r.claimable_placeholder) {
        setStatus((r.claimable_placeholder ? '⚑ ' : '✓ ') +
          r.normalized + '.' + state.brand +
          (r.claimable_placeholder ? ' is a placeholder — you can claim it' : ' is available'), 'ok');
        nextBtn.disabled = false;
        state.slug = r.normalized;
      } else {
        const sug = r.suggestion ? ' Try: ' + r.suggestion : '';
        setStatus('✗ ' + (r.reason || 'not available') + '.' + sug, 'bad');
        nextBtn.disabled = true;
      }
    } catch (e) {
      setStatus(e.detail || 'Could not check right now', 'bad');
      nextBtn.disabled = true;
    }
  }, 300);
  slugInput.addEventListener('input', check);
  if (state.slug) check();

  /** Decide what to show after step 1: either the profile gate or step 2. */
  async function advanceFromStep1() {
    const u = await getFreshUser();
    if (!u || !u.profile_complete) {
      prefillProfileForm(u);
      showStep('profile');
    } else {
      prefillStep2FromProfile(u);
      showStep(2);
    }
  }

  document.querySelector('[data-step1-form]').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!App.isAuthed()) {
      App.openAuthModal({
        title: 'Sign in to claim ' + state.slug + '.' + state.brand,
        sub: 'One click — no passwords, no phone OTP.',
        next: '/claim.php?brand=' + encodeURIComponent(state.brand) + '&slug=' + encodeURIComponent(state.slug),
      });
      return;
    }
    advanceFromStep1();
  });

  /* ---------- profile gate ---------- */
  const profileForm = document.querySelector('[data-profile-form]');

  function prefillProfileForm(u) {
    if (!profileForm || !u) return;
    const setVal = (sel, val) => {
      const el = profileForm.querySelector(sel);
      if (el && !el.value && val) el.value = val;
    };
    setVal('[name="name"]',             u.name || '');
    setVal('[name="mobile"]',           u.mobile || u.phone || '');
    setVal('[name="designation_bn"]',   u.designation_bn || '');
    setVal('[name="institution_name"]', u.institution_name || '');

    // BD-locations cascade for Division → District → Upazila. Falls back to a
    // plain three-select if bd-locations.js isn't loaded for some reason.
    const divEl  = profileForm.querySelector('[name="division"]');
    const distEl = profileForm.querySelector('[name="district"]');
    const upaEl  = profileForm.querySelector('[name="upazila"]');
    if (window.BDLocations && divEl) {
      window.BDLocations.bind(divEl, distEl, upaEl, {
        division: u.division || '',
        district: u.district || '',
        upazila:  u.upazila  || '',
      });
    } else {
      setVal('[name="division"]', u.division || '');
      setVal('[name="district"]', u.district || '');
      setVal('[name="upazila"]',  u.upazila  || '');
    }
  }

  function prefillStep2FromProfile(u) {
    if (!u) return;
    const form2 = document.querySelector('[data-step2-form]');
    if (!form2) return;
    const setVal = (name, val) => {
      const el = form2.querySelector('[name="' + name + '"]');
      if (el && !el.value && val) el.value = val;
    };
    // Carry the institution-level fields the user already filled in their profile.
    setVal('name_en',       u.institution_name || '');
    setVal('contact_name',  u.name || '');
    setVal('contact_phone', u.mobile || '');
    setVal('contact_email', u.email || '');

    // Wire the bd-locations cascade for the institution-details step (it's
    // optional here, so we keep the placeholder choices in sync but don't
    // mark them required server-side).
    const divEl  = form2.querySelector('[data-bd-division]');
    const distEl = form2.querySelector('[data-bd-district]');
    const upaEl  = form2.querySelector('[data-bd-upazila]');
    if (window.BDLocations && divEl) {
      window.BDLocations.bind(divEl, distEl, upaEl, {
        division: u.division || '',
        district: u.district || '',
        upazila:  u.upazila  || '',
      });
    } else {
      setVal('division', u.division || '');
      setVal('district', u.district || '');
      setVal('upazila',  u.upazila  || '');
    }
  }

  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(profileForm);
      const body = {};
      fd.forEach((v, k) => { body[k] = (v || '').toString().trim(); });
      // Light client-side check for a BD mobile (server re-validates).
      const m = (body.mobile || '').replace(/\D+/g, '');
      if (!(m.length === 11 && m.startsWith('01')) &&
          !(m.length === 13 && m.startsWith('8801'))) {
        App.toast('Enter a valid Bangladesh mobile (e.g. 01712345678)', 'error');
        const mob = profileForm.querySelector('[name="mobile"]');
        if (mob) mob.focus();
        return;
      }
      const submitBtn = profileForm.querySelector('[data-profile-submit]');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }
      try {
        const r = await App.api('/auth/profile', { method: 'PATCH', body });
        if (r && r.user) {
          App.setSession(null, r.user);
          prefillStep2FromProfile(r.user);
        }
        App.toast('Profile saved', 'success');
        showStep(2);
      } catch (err) {
        if (err && err.errors) {
          const first = Object.keys(err.errors)[0];
          App.toast(err.errors[first] || err.detail || 'Could not save profile', 'error');
          const fld = profileForm.querySelector('[name="' + first + '"]');
          if (fld) fld.focus();
        } else {
          App.toast(err.detail || 'Could not save profile', 'error');
        }
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save profile & continue →'; }
      }
    });
  }

  /* ---------- step 2 ---------- */
  const form2 = document.querySelector('[data-step2-form]');
  form2.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!App.isAuthed()) { App.openAuthModal({}); return; }
    const fd = new FormData(form2);
    const body = { brand: state.brand, slug: state.slug };
    fd.forEach((v, k) => { if (v !== '') body[k] = v; });
    try {
      const r = await App.api('/claims', { method: 'POST', body });
      state.inst = r.claim;
      // v3.0 — when the platform is in "no docs required" mode the claim
      // is auto-verified server-side. Skip the upload step entirely and jump
      // straight to the success screen.
      if (r.auto_verified) {
        App.toast('🎉 ' + (r.claim.subdomain || '') + ' is live!', 'success');
        renderDoneCard(r.claim);
        showStep('done');
        return;
      }
      App.toast('Saved — upload your verification document next', 'success');
      showStep(3);
    } catch (err) {
      // Server-side profile gate (defense in depth).
      if (err && err.profile_incomplete) {
        App.toast('Please complete your profile first', 'error');
        const u = await getFreshUser();
        prefillProfileForm(u);
        showStep('profile');
        return;
      }
      App.toast(err.detail || 'Could not save', 'error');
    }
  });

  /** Replace the static "Submitted for review" card with a richer success
   *  card that adapts to instant-claim vs document-review mode. */
  function renderDoneCard(claim) {
    const host = document.querySelector('[data-step="done"]');
    if (!host || !claim) return;
    const sub = claim.subdomain || '';
    const liveUrl = sub ? ('https://' + sub) : '';
    host.innerHTML = `
      <div class="text-center">
        <div class="check" style="width:48px;height:48px;margin:0 auto 14px;font-size:2rem;"></div>
        <h3 style="font-size:1.5rem;">${claim.status === 'verified' ? 'You\u2019re live!' : 'Submitted for review'}</h3>
        ${claim.status === 'verified'
          ? `<p class="text-muted">Your subdomain <code>${App.escapeHtml(sub)}</code> is ready. SSL is provisioning automatically.</p>`
          : `<p class="text-muted">Thank you! Your claim is in our moderation queue. We typically review within 24 hours.</p>`}
        <div class="flex" style="justify-content:center;flex-wrap:wrap;gap:10px;margin-top:14px;">
          ${liveUrl && claim.status === 'verified' ? `<a class="btn btn--primary" target="_blank" rel="noopener" href="${App.escapeHtml(liveUrl)}">Open ${App.escapeHtml(sub)} \u2192</a>` : ''}
          <a class="btn" href="/dashboard.php">My dashboard</a>
          <a class="btn" href="/directory.php">Browse directory</a>
        </div>
      </div>`;
  }

  document.querySelectorAll('[data-back]').forEach((b) => {
    b.addEventListener('click', () => showStep(Number(b.getAttribute('data-back'))));
  });

  /* ---------- step 3 ---------- */
  const form3 = document.querySelector('[data-step3-form]');
  form3.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!state.inst) { App.toast('Please complete the details step first', 'error'); showStep(2); return; }
    const fd = new FormData(form3);
    if (!fd.get('file') || (fd.get('file').size === 0)) { App.toast('Please choose a file', 'error'); return; }
    try {
      await App.api('/claims/' + state.inst.id + '/documents', { method: 'POST', body: fd });
      App.toast('Submitted for review', 'success');
      showStep('done');
    } catch (e) { App.toast(e.detail || 'Upload failed', 'error'); }
  });
})();
