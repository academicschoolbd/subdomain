/* ===========================================================================
   institution.bd / smartschool.bd — shared frontend module
   Vanilla JS. Used by every page. Exposes window.App.
   =========================================================================== */
(function () {
  'use strict';

  const API_BASE = '/api';
  const STORAGE_TOKEN = 'inst_jwt';
  const STORAGE_USER = 'inst_user';

  /* ---------------------------------------------------------------- */
  /*  v3.0: head bootstrap (manifest, dark-mode, skip-to-content link)  */
  /* ---------------------------------------------------------------- */
  const THEME_KEY = 'inst_theme'; // 'light' | 'dark'

  function applyTheme(mode) {
    const root = document.documentElement;
    root.setAttribute('data-theme', mode);
    // Sync the meta theme-color so the browser chrome matches. Note: in
    // v4.5 the inline <style id="theme-vars"> emitted server-side may
    // also publish brand-tinted theme-color metas; this dynamic update
    // wins when the user actively toggles.
    const meta = document.querySelector('meta[name="theme-color"]:not([media])');
    if (meta) meta.setAttribute('content', mode === 'dark' ? '#0b1220' : '#0f766e');
    try { localStorage.setItem(THEME_KEY, mode); } catch {}
  }
  function currentTheme() {
    let saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch {}
    if (saved === 'dark' || saved === 'light') return saved;
    // Fall back to OS preference.
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function toggleTheme() {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    renderThemeToggle();
  }
  function renderThemeToggle() {
    const host = document.querySelector('[data-theme-toggle]');
    if (!host) return;
    const dark = currentTheme() === 'dark';
    host.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    host.setAttribute('title', dark ? 'Switch to light theme' : 'Switch to dark theme');
    host.innerHTML = dark
      ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>'
      : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
  }

  /* ---------------------------------------------------------------- */
  /*  v4.5 — live brand theme (admin-controllable home color).         */
  /*                                                                  */
  /*  The server emits an inline <style id="theme-vars"> in <head> on  */
  /*  every page render so the brand color is correct on first paint. */
  /*  This helper is used after the admin saves a new color from the  */
  /*  Settings pane so the entire UI updates without a full reload.   */
  /* ---------------------------------------------------------------- */
  function _hexToRgb(hex) {
    if (!hex) return null;
    hex = String(hex).trim().toLowerCase();
    if (hex[0] !== '#') hex = '#' + hex;
    if (/^#[0-9a-f]{3}$/.test(hex)) {
      hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
    }
    if (!/^#[0-9a-f]{6}$/.test(hex)) return null;
    return [parseInt(hex.slice(1, 3), 16), parseInt(hex.slice(3, 5), 16), parseInt(hex.slice(5, 7), 16)];
  }
  function _rgbToHex(r, g, b) {
    const c = (n) => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0');
    return '#' + c(r) + c(g) + c(b);
  }
  function _mix(hex, mix, w) {
    const a = _hexToRgb(hex), b = _hexToRgb(mix);
    if (!a || !b) return hex;
    return _rgbToHex(a[0] + (b[0] - a[0]) * w, a[1] + (b[1] - a[1]) * w, a[2] + (b[2] - a[2]) * w);
  }
  function _rgba(hex, alpha) {
    const a = _hexToRgb(hex);
    if (!a) return 'rgba(15,118,110,' + alpha + ')';
    return 'rgba(' + a[0] + ',' + a[1] + ',' + a[2] + ',' + alpha + ')';
  }
  function applyBrandTheme(primary, primaryDark) {
    if (!_hexToRgb(primary)) return;
    if (!primaryDark || !_hexToRgb(primaryDark)) {
      primaryDark = _mix(primary, '#ffffff', 0.30);
    }
    const css = [
      ':root{',
      '--c-primary:' + primary + ';',
      '--c-primary-700:' + _mix(primary, '#000000', 0.12) + ';',
      '--c-primary-50:' + _rgba(primary, 0.08) + ';',
      '--c-primary-100:' + _rgba(primary, 0.18) + ';',
      '--c-link:' + primary + ';',
      '}',
      'html[data-theme="dark"]{',
      '--c-primary:' + primaryDark + ';',
      '--c-primary-700:' + _mix(primaryDark, '#000000', 0.10) + ';',
      '--c-primary-50:' + _rgba(primaryDark, 0.12) + ';',
      '--c-primary-100:' + _rgba(primaryDark, 0.22) + ';',
      '--c-link:' + primaryDark + ';',
      '}'
    ].join('');
    let host = document.getElementById('theme-vars');
    if (!host) {
      host = document.createElement('style');
      host.id = 'theme-vars';
      document.head.appendChild(host);
    }
    host.textContent = css;
    // Bust the settings cache so subsequent reads see the new color too.
    if (_settingsCache && _settingsCache.theme) {
      _settingsCache.theme.primary = primary;
      _settingsCache.theme.primary_dark = primaryDark;
    }
  }

  function bootHead() {
    // 1) Apply theme as early as possible.
    applyTheme(currentTheme());

    // 2) Inject a <link rel="manifest"> if not already present.
    if (!document.querySelector('link[rel="manifest"]')) {
      const m = document.createElement('link');
      m.rel = 'manifest';
      m.href = '/manifest.webmanifest';
      document.head.appendChild(m);
    }

    // 3) Skip-to-content link, prepended at the very top of <body>.
    if (!document.querySelector('a.skip-link')) {
      const s = document.createElement('a');
      s.className = 'skip-link';
      s.href = '#main';
      s.textContent = 'Skip to main content';
      document.body.insertBefore(s, document.body.firstChild);
      // Make sure the page has a #main landmark for the link to jump to.
      if (!document.getElementById('main')) {
        // Pick the first <section> or <main>; otherwise <body>.
        const target = document.querySelector('main, section');
        if (target && !target.id) target.id = 'main';
      }
    }

    // 4) v4.5 — drop a theme toggle button into the nav CTA. The toggle
    //    is the first child of .nav__cta so on phones the visual order
    //    is: [theme-toggle] [signin OR avatar] [hamburger] — matching the
    //    documented mobile design.
    const cta = document.querySelector('.nav__cta');
    if (cta && !document.querySelector('[data-theme-toggle]')) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'nav__icobtn nav__theme-toggle';
      btn.setAttribute('data-theme-toggle', '');
      btn.addEventListener('click', toggleTheme);
      cta.insertBefore(btn, cta.firstChild);
      renderThemeToggle();
    }
  }
  // Run as soon as this script loads (head boot must happen before paint
  // for theme to avoid a flash).
  bootHead();
  /* ---------------------------------------------------------------- */
  async function api(path, opts = {}) {
    const init = { method: opts.method || 'GET', headers: {} };
    const token = getToken();
    if (token) init.headers['Authorization'] = 'Bearer ' + token;
    if (opts.body && !(opts.body instanceof FormData)) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    } else if (opts.body instanceof FormData) {
      init.body = opts.body;
    }
    const url = path.startsWith('http') ? path : API_BASE + path;
    let res;
    try {
      res = await fetch(url, init);
    } catch (e) {
      throw { detail: 'Network error — please try again.', status: 0 };
    }
    let data = null;
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json')) {
      try { data = await res.json(); } catch (e) { /* ignore */ }
    } else if (!res.ok) {
      data = { detail: await res.text() };
    }
    if (!res.ok) {
      const err = data && data.detail ? data : { detail: 'Request failed', status: res.status };
      err.status = res.status;
      throw err;
    }
    return data;
  }

  function qs(params) {
    if (!params) return '';
    const parts = [];
    for (const k in params) {
      if (params[k] === undefined || params[k] === null || params[k] === '') continue;
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
    }
    return parts.length ? '?' + parts.join('&') : '';
  }

  /* ---------------------------------------------------------------- */
  /*  Auth state                                                       */
  /* ---------------------------------------------------------------- */
  function getToken() { try { return localStorage.getItem(STORAGE_TOKEN); } catch { return null; } }
  function getUser()  { try { return JSON.parse(localStorage.getItem(STORAGE_USER) || 'null'); } catch { return null; } }
  function setSession(token, user) {
    if (token) localStorage.setItem(STORAGE_TOKEN, token);
    if (user) localStorage.setItem(STORAGE_USER, JSON.stringify(user));
    renderUserChip();
  }
  function clearSession() {
    localStorage.removeItem(STORAGE_TOKEN);
    localStorage.removeItem(STORAGE_USER);
    renderUserChip();
  }
  function isAuthed() { return !!getToken(); }
  function isAdmin() { const u = getUser(); return !!(u && u.is_admin); }

  /**
   * v3.1 — show/hide elements based on whether someone is signed in or is
   * an admin. Used by the footer / nav so we don't leak the Admin link
   * to normal visitors.
   *
   *   <a data-auth-only  hidden …>My dashboard</a>
   *   <a data-admin-only hidden …>Admin</a>
   *   <a data-guest-only          …>Sign in</a>   (hidden once authed)
   */
  function applyVisibilityGates() {
    const authed = isAuthed();
    const admin  = isAdmin();
    document.querySelectorAll('[data-auth-only]').forEach((el) => {
      el.hidden = !authed;
    });
    document.querySelectorAll('[data-admin-only]').forEach((el) => {
      el.hidden = !admin;
    });
    document.querySelectorAll('[data-guest-only]').forEach((el) => {
      el.hidden = !!authed;
    });
  }

  function renderUserChip() {
    const host = document.querySelector('[data-user-chip]');
    // v3.1 — also reveal/hide elements gated by [data-admin-only] / [data-auth-only]
    // anywhere on the page (footer links, nav items, etc.) so the Admin tile
    // never leaks to a normal visitor.
    applyVisibilityGates();
    if (!host) return;
    const user = getUser();
    host.innerHTML = '';
    if (!user) {
      // v4.5 — explicit "Sign in" affordance. On tablet+ it's a labelled
      // pill button; on phones the same button collapses to a 40px square
      // icon (CSS handles the breakpoint). Either way it's always visible
      // — never hidden behind the hamburger menu.
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'nav__signin';
      btn.setAttribute('aria-label', 'Sign in');
      btn.setAttribute('title', 'Sign in');
      btn.innerHTML =
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>'
        + '<polyline points="10 17 15 12 10 7"/>'
        + '<line x1="15" y1="12" x2="3" y2="12"/>'
        + '</svg>'
        + '<span>Sign in</span>';
      btn.addEventListener('click', () => openAuthModal());
      host.appendChild(btn);
      return;
    }
    // Authed: avatar + name + sign-out, wrapped in .nav__chip. On phones
    // the chip collapses to just the avatar (still clickable — it links
    // to the dashboard / admin console).
    const link = document.createElement('a');
    link.href = user.is_admin ? '/admin.php' : '/dashboard.php';
    link.className = 'nav__chip';
    link.setAttribute('aria-label', user.is_admin ? 'Open admin console' : 'Open my dashboard');
    link.setAttribute('title', user.name || user.email || 'My account');
    const avatar = document.createElement('span');
    avatar.className = 'avatar';
    if (user.avatar_url) {
      const img = document.createElement('img'); img.src = user.avatar_url; img.alt = '';
      avatar.appendChild(img);
    } else {
      avatar.textContent = (user.name || user.email || '?').slice(0, 1).toUpperCase();
    }
    const name = document.createElement('span');
    name.className = 'nav__chip-name';
    name.textContent = (user.name || user.email || 'Account');
    link.appendChild(avatar);
    link.appendChild(name);
    host.appendChild(link);

    const out = document.createElement('button');
    out.type = 'button';
    out.className = 'nav__chip-out';
    out.setAttribute('aria-label', 'Sign out');
    out.setAttribute('title', 'Sign out');
    out.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    out.addEventListener('click', (e) => { e.preventDefault(); clearSession(); location.href = '/'; });
    host.appendChild(out);
  }

  /* ---------------------------------------------------------------- */
  /*  Settings (OAuth providers, WhatsApp links)                       */
  /* ---------------------------------------------------------------- */
  let _settingsCache = null;
  async function getSettings() {
    if (_settingsCache) return _settingsCache;
    _settingsCache = await api('/settings');
    return _settingsCache;
  }

  /* ---------------------------------------------------------------- */
  /*  Auth modal (OAuth + email/password)                              */
  /* ---------------------------------------------------------------- */
  let _modalEl = null;
  let _modalMode = 'signin'; // 'signin' | 'signup'

  function ensureModalEl() {
    if (_modalEl) return _modalEl;
    const wrap = document.createElement('div');
    wrap.className = 'modal-bg';
    wrap.setAttribute('role', 'dialog');
    wrap.innerHTML = `
      <div class="modal modal--auth" role="document">
        <button class="modal__close" aria-label="Close">&times;</button>

        <div class="auth-tabs" role="tablist">
          <button type="button" class="auth-tab" data-tab="signin" role="tab">Sign in</button>
          <button type="button" class="auth-tab" data-tab="signup" role="tab">Create account</button>
        </div>

        <h2 class="auth-title" data-modal-title>Welcome back</h2>
        <p class="muted auth-sub" data-modal-sub>Sign in to claim or manage your free subdomain.</p>

        <div class="oauth-list" data-modal-providers></div>

        <div class="auth-divider" data-auth-divider><span>or with email</span></div>

        <form class="auth-form" data-auth-form novalidate>
          <div class="field" data-field="name" hidden>
            <label>Full name</label>
            <input type="text" name="name" autocomplete="name" placeholder="Your name" />
            <p class="field__err" data-err="name"></p>
          </div>
          <div class="field">
            <label>Email address</label>
            <input type="email" name="email" autocomplete="email" placeholder="you@example.com" required />
            <p class="field__err" data-err="email"></p>
          </div>
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" autocomplete="current-password" placeholder="At least 8 characters" required />
            <p class="field__err" data-err="password"></p>
          </div>
          <div class="field" data-field="mobile" hidden>
            <label>Mobile <span class="muted">(optional)</span></label>
            <input type="tel" name="mobile" autocomplete="tel" placeholder="01XXXXXXXXX" />
            <p class="field__err" data-err="mobile"></p>
          </div>
          <button type="submit" class="btn btn--primary btn--block" data-auth-submit>Sign in</button>
          <p class="auth-switch">
            <span data-switch-text>New here?</span>
            <button type="button" class="link-btn" data-switch>Create an account</button>
          </p>
          <p class="auth-switch" data-forgot-row>
            <button type="button" class="link-btn" data-forgot>Forgot your password?</button>
          </p>
        </form>

        <p class="modal__legal">By continuing you agree to our <a href="/terms.php">terms</a> and <a href="/privacy.php">privacy policy</a>.</p>
      </div>`;
    wrap.addEventListener('click', (e) => { if (e.target === wrap) closeAuthModal(); });
    wrap.querySelector('.modal__close').addEventListener('click', () => closeAuthModal());
    wrap.querySelectorAll('[data-tab]').forEach((t) => {
      t.addEventListener('click', () => setAuthMode(t.getAttribute('data-tab')));
    });
    wrap.querySelector('[data-switch]').addEventListener('click', () => {
      setAuthMode(_modalMode === 'signin' ? 'signup' : 'signin');
    });
    wrap.querySelector('[data-forgot]').addEventListener('click', onForgotClick);
    wrap.querySelector('[data-auth-form]').addEventListener('submit', onAuthSubmit);
    document.body.appendChild(wrap);
    _modalEl = wrap;
    return wrap;
  }

  function setAuthMode(mode) {
    _modalMode = mode === 'signup' ? 'signup' : 'signin';
    if (!_modalEl) return;
    _modalEl.querySelectorAll('[data-tab]').forEach((t) => {
      t.classList.toggle('active', t.getAttribute('data-tab') === _modalMode);
    });
    const title = _modalEl.querySelector('[data-modal-title]');
    const sub   = _modalEl.querySelector('[data-modal-sub]');
    const nameF = _modalEl.querySelector('[data-field="name"]');
    const mobF  = _modalEl.querySelector('[data-field="mobile"]');
    const pwdIn = _modalEl.querySelector('input[name="password"]');
    const submit= _modalEl.querySelector('[data-auth-submit]');
    const swT   = _modalEl.querySelector('[data-switch-text]');
    const swB   = _modalEl.querySelector('[data-switch]');
    const fRow  = _modalEl.querySelector('[data-forgot-row]');
    if (_modalMode === 'signup') {
      title.textContent = 'Create your account';
      sub.textContent = 'Free forever — no credit card needed.';
      nameF.hidden = false; mobF.hidden = false;
      pwdIn.setAttribute('autocomplete', 'new-password');
      pwdIn.placeholder = 'At least 8 characters';
      submit.textContent = 'Create account';
      swT.textContent = 'Already have an account?';
      swB.textContent = 'Sign in instead';
      if (fRow) fRow.hidden = true;
    } else {
      title.textContent = 'Welcome back';
      sub.textContent = 'Sign in to claim or manage your free subdomain.';
      nameF.hidden = true; mobF.hidden = true;
      pwdIn.setAttribute('autocomplete', 'current-password');
      pwdIn.placeholder = 'Your password';
      submit.textContent = 'Sign in';
      swT.textContent = 'New here?';
      swB.textContent = 'Create an account';
      if (fRow) fRow.hidden = false;
    }
    // Clear any previous error/success state.
    _modalEl.querySelectorAll('[data-err]').forEach((e) => { e.textContent = ''; });
    _modalEl.querySelectorAll('.field').forEach((f) => f.classList.remove('field--error'));
  }

  /**
   * v3.0 — "Forgot password" handler. Posts the email from the modal to
   * /api/auth/forgot-password and shows feedback. In demo mode the API
   * returns a `dev_token` + `reset_url`; we surface the link so admins can
   * test without an SMTP gateway.
   */
  async function onForgotClick() {
    if (!_modalEl) return;
    const form = _modalEl.querySelector('[data-auth-form]');
    const emailInp = form.querySelector('input[name="email"]');
    const errEl = form.querySelector('[data-err="email"]');
    const email = (emailInp && emailInp.value || '').trim().toLowerCase();
    if (!email || !/.+@.+\..+/.test(email)) {
      if (errEl) errEl.textContent = 'Enter your account email above first, then click "Forgot password".';
      const f = errEl && errEl.closest('.field');
      if (f) f.classList.add('field--error');
      if (emailInp) emailInp.focus();
      return;
    }
    if (errEl) errEl.textContent = '';
    const f = errEl && errEl.closest('.field');
    if (f) f.classList.remove('field--error');

    const btn = _modalEl.querySelector('[data-forgot]');
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Sending…';
    try {
      const r = await api('/auth/forgot-password', { method: 'POST', body: { email } });
      if (r && r.reset_url) {
        // Demo-mode: show the link directly so the admin can complete the
        // flow without configuring email.
        toast('Demo mode — opening reset link in a new tab.', 'success');
        window.open(r.reset_url, '_blank', 'noopener');
      } else {
        toast('If an account exists for that email, a reset link has been sent.', 'success');
      }
    } catch (e) {
      toast((e && e.detail) || 'Could not send reset link.', 'error');
    } finally {
      btn.disabled = false; btn.textContent = orig;
    }
  }

  async function onAuthSubmit(ev) {
    ev.preventDefault();
    const form = ev.currentTarget;
    const submit = form.querySelector('[data-auth-submit]');
    const data = Object.fromEntries(new FormData(form).entries());
    form.querySelectorAll('[data-err]').forEach((e) => { e.textContent = ''; });
    form.querySelectorAll('.field').forEach((f) => f.classList.remove('field--error'));
    submit.disabled = true;
    const origLabel = submit.textContent;
    submit.textContent = _modalMode === 'signup' ? 'Creating…' : 'Signing in…';
    try {
      const path = _modalMode === 'signup' ? '/auth/register' : '/auth/login';
      const body = _modalMode === 'signup'
        ? { name: data.name || '', email: data.email || '', password: data.password || '', mobile: data.mobile || '' }
        : { email: data.email || '', password: data.password || '' };
      const r = await api(path, { method: 'POST', body });
      if (r && r.token) {
        setSession(r.token, r.user);
        closeAuthModal();
        toast(_modalMode === 'signup' ? 'Account created — welcome!' : 'Signed in.', 'success');
        // If the page declared a post-auth callback, call it; otherwise reload.
        if (typeof window.__onAuthSuccess === 'function') {
          window.__onAuthSuccess(r.user);
        } else {
          setTimeout(() => location.reload(), 250);
        }
      }
    } catch (e) {
      const errs = (e && e.errors) || {};
      let any = false;
      Object.keys(errs).forEach((k) => {
        const err = form.querySelector('[data-err="' + k + '"]');
        const field = err && err.closest('.field');
        if (err) { err.textContent = errs[k]; any = true; }
        if (field) field.classList.add('field--error');
      });
      if (!any) {
        toast((e && e.detail) || 'Sign-in failed. Please try again.', 'error');
      }
    } finally {
      submit.disabled = false;
      submit.textContent = origLabel;
    }
  }

  async function openAuthModal(opts) {
    opts = opts || {};
    const el = ensureModalEl();

    // v3.3 — honor the admin "allow manual email sign-up" toggle. We need
    // to know whether registration is allowed before deciding which tab
    // to start on, so peek at settings synchronously from cache when we
    // can; otherwise just show "sign in" and we'll re-render once
    // settings arrive below.
    let regAllowed = true;
    if (_settingsCache && _settingsCache.auth) {
      regAllowed = _settingsCache.auth.email_registration_enabled !== false;
    }
    const requestedMode = opts.mode === 'signup' ? 'signup' : 'signin';
    setAuthMode(regAllowed ? requestedMode : 'signin');
    applyEmailRegToggle(regAllowed);
    if (opts.title) el.querySelector('[data-modal-title]').textContent = opts.title;
    if (opts.sub)   el.querySelector('[data-modal-sub]').textContent = opts.sub;
    el.classList.add('open');

    const providersHost = el.querySelector('[data-modal-providers]');
    const divider = el.querySelector('[data-auth-divider]');
    providersHost.innerHTML = '<div class="skeleton" style="height:44px;border-radius:10px;"></div>';

    let s;
    try { s = await getSettings(); }
    catch (e) {
      providersHost.innerHTML = '';
      divider.hidden = true;
      return;
    }

    // Re-apply the registration toggle now that we have authoritative
    // settings (the synchronous peek may have used a stale cache).
    const allowReg = !(s.auth && s.auth.email_registration_enabled === false);
    applyEmailRegToggle(allowReg);
    if (!allowReg && _modalMode === 'signup') setAuthMode('signin');

    const providers = (s.auth && s.auth.oauth_providers) || [];
    providersHost.innerHTML = '';
    if (!providers.length) {
      divider.hidden = true;
      return;
    }
    divider.hidden = false;
    const next = opts.next || (location.pathname + location.search);
    providers.forEach((p) => {
      const a = document.createElement('a');
      a.className = 'oauth-btn oauth-btn--' + p.id;
      a.href = '/api/auth/oauth/' + p.id + '/start?next=' + encodeURIComponent(next);
      const ico = document.createElement('img');
      ico.src = '/assets/img/' + p.id + '.svg'; ico.alt = '';
      const text = document.createElement('span');
      text.textContent = 'Continue with ' + p.label;
      a.appendChild(ico); a.appendChild(text);
      providersHost.appendChild(a);
    });
  }

  function closeAuthModal() {
    if (_modalEl) _modalEl.classList.remove('open');
  }

  /**
   * v3.3 — when the admin disables "Allow manual email sign-up" we hide
   * the Create-account tab and the in-form switcher inside the modal,
   * and reword the legend so visitors aren't confused. Sign-in still
   * works exactly as before.
   */
  function applyEmailRegToggle(allowed) {
    if (!_modalEl) return;
    const tabSignup = _modalEl.querySelector('[data-tab="signup"]');
    const tabSignin = _modalEl.querySelector('[data-tab="signin"]');
    const switchRow = _modalEl.querySelector('[data-switch]');
    if (tabSignup) tabSignup.hidden = !allowed;
    if (tabSignin) tabSignin.hidden = !allowed && false; // always show sign-in
    if (switchRow) switchRow.hidden = !allowed;
    // If signup is gone, the tab row only has one item — drop it altogether
    // so the modal looks intentional rather than empty.
    const tabsRow = _modalEl.querySelector('.auth-tabs');
    if (tabsRow) tabsRow.style.display = allowed ? '' : 'none';
    if (!allowed) {
      const sub = _modalEl.querySelector('[data-modal-sub]');
      if (sub && _modalMode === 'signin') {
        sub.textContent = 'Sign in to claim or manage your free subdomain. New accounts via Google / Facebook / GitHub.';
      }
    }
  }

  /**
   * v3.3 — also gate the page-level "Create account" CTAs (the hero
   * sign-in row, the final-CTA band, the dashboard's needs-auth card).
   * Anything carrying `data-mode="signup"` gets hidden when the admin
   * has turned off manual registration.
   */
  function applyEmailRegPageGates(allowed) {
    document.querySelectorAll('[data-open-auth][data-mode="signup"]').forEach((el) => {
      el.hidden = !allowed;
    });
  }

  function requireAuth(opts) {
    if (isAuthed()) return Promise.resolve(getUser());
    openAuthModal(opts);
    return Promise.reject({ detail: 'Sign-in required' });
  }

  /* ---------------------------------------------------------------- */
  /*  WhatsApp float + nav                                              */
  /* ---------------------------------------------------------------- */
  async function injectWhatsAppFloat() {
    try {
      const s = await getSettings();
      const url = s.whatsapp && s.whatsapp.support_url;
      if (!url) return;
      if (document.querySelector('.wa-float')) return;
      const a = document.createElement('a');
      a.className = 'wa-float';
      a.href = url;
      a.target = '_blank';
      a.rel = 'noopener';
      a.title = 'Chat with us on WhatsApp';
      a.innerHTML = '<img src="/assets/img/whatsapp.svg" alt="WhatsApp" />';
      document.body.appendChild(a);
    } catch (e) { /* ignore */ }
  }

  function wireNav() {
    renderUserChip();
    const toggle = document.querySelector('[data-nav-toggle]');
    const mobile = document.querySelector('[data-nav-mobile]');
    if (toggle && mobile) {
      // v4.5 — augment the mobile drawer with sign-in / dashboard / sign-out
      // controls so authenticated actions are reachable from inside the
      // hamburger too (in addition to the always-visible icon buttons).
      _augmentMobileDrawer(mobile);
      toggle.addEventListener('click', () => {
        mobile.classList.toggle('open');
        const open = mobile.classList.contains('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      // Auto-close the mobile menu when the user picks a link or hits Esc.
      mobile.querySelectorAll('a').forEach((a) => {
        a.addEventListener('click', () => mobile.classList.remove('open'));
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobile.classList.contains('open')) {
          mobile.classList.remove('open');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
      // Close drawer if viewport widens past the desktop breakpoint while
      // it's open (avoids leftover overlay state on rotation).
      const mq = window.matchMedia('(min-width: 960px)');
      const onMq = () => { if (mq.matches) mobile.classList.remove('open'); };
      if (mq.addEventListener) mq.addEventListener('change', onMq);
      else if (mq.addListener) mq.addListener(onMq);
    }
    document.querySelectorAll('[data-open-auth]').forEach((el) => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        const mode = el.getAttribute('data-mode') || 'signin';
        openAuthModal({ mode });
      });
    });
    document.querySelectorAll('[data-signout]').forEach((el) => {
      el.addEventListener('click', (e) => { e.preventDefault(); clearSession(); location.href = '/'; });
    });

    // Sticky nav: add a subtle shadow once the user scrolls past the hero.
    const nav = document.querySelector('.nav');
    if (nav) {
      let raf = null;
      const update = () => {
        nav.classList.toggle('nav--scrolled', window.scrollY > 12);
        raf = null;
      };
      window.addEventListener('scroll', () => {
        if (raf == null) raf = requestAnimationFrame(update);
      }, { passive: true });
      update();
    }
  }

  /** v4.5 — append auth-aware items to the mobile hamburger drawer. */
  function _augmentMobileDrawer(mobile) {
    if (mobile.dataset.augmented === '1') return;
    mobile.dataset.augmented = '1';
    const div = document.createElement('div');
    div.className = 'nav__mobile-divider';
    mobile.appendChild(div);
    const guestSignIn = document.createElement('a');
    guestSignIn.href = '#';
    guestSignIn.setAttribute('data-open-auth', '');
    guestSignIn.setAttribute('data-guest-only', '');
    guestSignIn.textContent = 'Sign in';
    mobile.appendChild(guestSignIn);
    const guestSignUp = document.createElement('a');
    guestSignUp.href = '#';
    guestSignUp.setAttribute('data-open-auth', '');
    guestSignUp.setAttribute('data-mode', 'signup');
    guestSignUp.setAttribute('data-guest-only', '');
    guestSignUp.textContent = 'Create account';
    mobile.appendChild(guestSignUp);
    const dash = document.createElement('a');
    dash.href = '/dashboard.php';
    dash.setAttribute('data-auth-only', '');
    dash.hidden = true;
    dash.textContent = 'My dashboard';
    mobile.appendChild(dash);
    const adm = document.createElement('a');
    adm.href = '/admin.php';
    adm.setAttribute('data-admin-only', '');
    adm.hidden = true;
    adm.textContent = 'Admin console';
    mobile.appendChild(adm);
    const out = document.createElement('button');
    out.type = 'button';
    out.className = 'nav__mobile-signout';
    out.setAttribute('data-auth-only', '');
    out.setAttribute('data-signout', '');
    out.hidden = true;
    out.textContent = 'Sign out';
    mobile.appendChild(out);
  }

  /* ---------------------------------------------------------------- */
  /*  Toasts                                                            */
  /* ---------------------------------------------------------------- */
  function toast(msg, kind = '') {
    let host = document.querySelector('.toast-host');
    if (!host) {
      host = document.createElement('div');
      host.className = 'toast-host';
      document.body.appendChild(host);
    }
    const t = document.createElement('div');
    t.className = 'toast ' + kind;
    t.textContent = msg;
    host.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .25s'; }, 3500);
    setTimeout(() => { t.remove(); }, 3900);
  }

  /* ---------------------------------------------------------------- */
  /*  Util                                                              */
  /* ---------------------------------------------------------------- */
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function initialsOf(name) {
    return (name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  }
  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  function debounce(fn, ms) {
    let t = null;
    return function () {
      clearTimeout(t);
      const args = arguments;
      t = setTimeout(() => fn.apply(null, args), ms);
    };
  }

  /* ---------------------------------------------------------------- */
  /*  Boot                                                              */
  /* ---------------------------------------------------------------- */
  /* ---------------------------------------------------------------- */
  /*  v4.5 — scroll-reveal for sections + dynamic instant-feel UX.     */
  /*                                                                  */
  /*  Adds a subtle fade-up animation to public-page sections as they  */
  /*  enter the viewport (IntersectionObserver, no timer polling).    */
  /*  Skipped on dashboard / admin shells where instant pane switches  */
  /*  matter more than scroll choreography. Honors prefers-reduced-   */
  /*  motion automatically via the .reveal CSS rule.                  */
  /* ---------------------------------------------------------------- */
  function wireScrollReveal() {
    if (document.querySelector('.dash-shell')) return; // dashboards stay snappy
    if (!('IntersectionObserver' in window)) return;
    const targets = document.querySelectorAll(
      'section, .pro-card, .brand-card, .feature, .step, .stat-tile, .dir-card, .faq, .wa-band, .footer'
    );
    if (!targets.length) return;
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.classList.add('reveal', 'is-visible');
          io.unobserve(e.target);
        }
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });
    targets.forEach((t) => {
      // Skip the very first hero so the page doesn't fade in on landing.
      if (t.classList.contains('hero') || t.classList.contains('hero--center')) {
        t.classList.add('reveal', 'is-visible');
        return;
      }
      t.classList.add('reveal');
      io.observe(t);
    });
  }

  function boot() {
    wireNav();
    injectWhatsAppFloat();
    wireScrollReveal();
    // v3.3 — fetch settings once on boot so we can hide the "Create account"
    // CTAs across the page when the admin disables manual registration.
    // v4.5 — also applies the admin-controlled brand color in case the
    // server-emitted inline <style> wasn't available on older deploys.
    getSettings().then((s) => {
      const allowReg = !(s && s.auth && s.auth.email_registration_enabled === false);
      applyEmailRegPageGates(allowReg);
      if (s && s.theme && s.theme.primary) {
        applyBrandTheme(s.theme.primary, s.theme.primary_dark || '');
      }
    }).catch(() => { /* keep CTAs visible on settings error */ });
    if (isAuthed()) {
      // refresh user in background
      api('/auth/me').then((r) => {
        if (r && r.user) setSession(null, r.user);
      }).catch((e) => {
        if (e && e.status === 401) { clearSession(); }
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else { boot(); }

  /* ---------------------------------------------------------------- */
  /*  Public API                                                        */
  /* ---------------------------------------------------------------- */
  window.App = {
    api, qs,
    getToken, getUser, setSession, clearSession, isAuthed, isAdmin,
    applyVisibilityGates,
    getSettings,
    openAuthModal, closeAuthModal, requireAuth,
    toast, escapeHtml, initialsOf, fmtDate, debounce,
    // v4.5 — admin-controllable brand color, applied live after save.
    applyBrandTheme,
  };
})();



/* ===========================================================================
   v5 — admin / dashboard sidebar drawer (mobile-only behaviour)
   ----------------------------------------------------------------------------
   Wires every page that has a [data-dash-sidebar] aside.
     • [data-dash-drawer-open]  → opens the drawer
     • [data-dash-drawer-close] → closes the drawer
     • [data-dash-backdrop]     → backdrop element (click closes)
     • Esc key                  → closes the drawer
     • Selecting any item inside the sidebar (button, link, or an item with
       [data-pane-btn]) auto-closes the drawer — that's the "admin nav menu
       hides when you click an option" behaviour from the v5 spec.
     • Body gets .is-drawer-open while open so scroll is locked underneath.
   The whole module is a no-op on pages that don't have the sidebar markup,
   so it's safe to load globally from app.js.
   =========================================================================== */
(function () {
  'use strict';
  if (typeof document === 'undefined') return;

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else { fn(); }
  }

  ready(function () {
    var sidebar  = document.querySelector('[data-dash-sidebar]');
    if (!sidebar) return; // page has no admin/dashboard drawer

    var backdrop = document.querySelector('[data-dash-backdrop]');
    var openers  = document.querySelectorAll('[data-dash-drawer-open]');
    var closers  = document.querySelectorAll('[data-dash-drawer-close]');
    var trigger  = document.querySelector('[data-dash-drawer-open]');
    var label    = document.querySelector('[data-dash-drawer-current]');
    var navItems = sidebar.querySelectorAll('[data-pane-btn], .dash-nav__item, .dash-nav a');

    var isMobile = function () {
      return window.matchMedia && window.matchMedia('(max-width: 899px)').matches;
    };

    function openDrawer() {
      if (!isMobile()) return;
      sidebar.classList.add('is-open');
      if (backdrop) {
        backdrop.hidden = false;
        // Force a paint frame before applying .is-open so the CSS transition runs.
        requestAnimationFrame(function () { backdrop.classList.add('is-open'); });
      }
      document.body.classList.add('is-drawer-open');
      if (trigger) trigger.setAttribute('aria-expanded', 'true');
      // Move keyboard focus into the drawer for accessibility.
      var firstFocusable = sidebar.querySelector('button, a, [tabindex]');
      if (firstFocusable) {
        try { firstFocusable.focus({ preventScroll: true }); } catch (e) { firstFocusable.focus(); }
      }
    }

    function closeDrawer() {
      sidebar.classList.remove('is-open');
      if (backdrop) {
        backdrop.classList.remove('is-open');
        // Wait for the CSS transition to finish before fully hiding the
        // overlay, so the fade-out is visible.
        setTimeout(function () {
          if (!sidebar.classList.contains('is-open')) backdrop.hidden = true;
        }, 240);
      }
      document.body.classList.remove('is-drawer-open');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }

    // Expose so other scripts (admin.js / dashboard.js) can hook in.
    window.AppDrawer = {
      open: openDrawer,
      close: closeDrawer,
      isOpen: function () { return sidebar.classList.contains('is-open'); },
      setLabel: function (text) { if (label && text) label.textContent = text; }
    };

    // Wire opener buttons.
    openers.forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        if (sidebar.classList.contains('is-open')) closeDrawer();
        else openDrawer();
      });
    });

    // Wire close buttons + backdrop click.
    closers.forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        closeDrawer();
      });
    });
    if (backdrop) {
      backdrop.addEventListener('click', closeDrawer);
    }

    // Auto-close when the user picks a nav item (pane button OR plain link).
    navItems.forEach(function (item) {
      item.addEventListener('click', function () {
        if (!isMobile()) return;
        // Update the trigger button label so the user sees what they've
        // selected when the drawer collapses.
        var text = item.querySelector('span:not(.dash-nav__ico):not(.dash-nav__count)');
        if (label && text && text.textContent.trim()) {
          label.textContent = text.textContent.trim();
        }
        // Defer so any same-click navigation happens before we close.
        setTimeout(closeDrawer, 0);
      });
    });

    // Esc closes the drawer.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
        closeDrawer();
      }
    });

    // If the viewport grows past the desktop breakpoint while the drawer is
    // open, undo the body scroll-lock — the desktop layout is back.
    window.addEventListener('resize', function () {
      if (!isMobile() && document.body.classList.contains('is-drawer-open')) {
        closeDrawer();
      }
    });
  });
})();



/* ===========================================================================
   v5 — platform status pill (lives in the footer of every page).
   Hits /api/healthz once on load and flips the dot/text to indicate health.
   No-op when there's no [data-platform-status] in the DOM.
   =========================================================================== */
(function () {
  'use strict';
  function init() {
    var pill = document.querySelector('[data-platform-status]');
    if (!pill) return;
    var dot  = pill.querySelector('.footer__status-dot');
    var txt  = pill.querySelector('[data-platform-status-text]');
    if (!txt) return;

    function setState(state) {
      if (!dot) return;
      dot.classList.remove('footer__status-dot--err', 'footer__status-dot--warn');
      if (state === 'err')  dot.classList.add('footer__status-dot--err');
      if (state === 'warn') dot.classList.add('footer__status-dot--warn');
    }

    fetch('/api/healthz', { method: 'GET', credentials: 'omit' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (j) {
        if (j && j.ok) {
          setState('ok');
          txt.textContent = 'All systems operational';
        } else {
          setState('warn');
          txt.textContent = 'Degraded performance';
        }
      })
      .catch(function () {
        setState('err');
        txt.textContent = 'Status check failed';
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();
