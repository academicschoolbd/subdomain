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
    // Sync the meta theme-color so the browser chrome matches.
    const meta = document.querySelector('meta[name="theme-color"]');
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

    // 4) If a nav has a CTA host, drop a theme toggle button into it.
    const cta = document.querySelector('.nav__cta');
    if (cta && !document.querySelector('[data-theme-toggle]')) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'nav__theme-toggle';
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

  function renderUserChip() {
    const host = document.querySelector('[data-user-chip]');
    if (!host) return;
    const user = getUser();
    host.innerHTML = '';
    if (!user) {
      const btn = document.createElement('button');
      btn.className = 'btn btn--primary';
      btn.textContent = 'Sign in';
      btn.addEventListener('click', () => openAuthModal());
      host.appendChild(btn);
      return;
    }
    const wrap = document.createElement('div');
    wrap.className = 'flex';
    const avatar = document.createElement('span');
    avatar.className = 'avatar';
    if (user.avatar_url) {
      const img = document.createElement('img'); img.src = user.avatar_url; img.alt = '';
      avatar.appendChild(img);
    } else {
      avatar.textContent = (user.name || user.email || '?').slice(0, 1).toUpperCase();
    }
    const link = document.createElement('a');
    link.href = user.is_admin ? '/admin.php' : '/dashboard.php';
    link.className = 'btn btn--ghost btn--sm';
    link.style.textTransform = 'none';
    link.textContent = (user.name || user.email || 'My dashboard');
    const out = document.createElement('button');
    out.className = 'btn btn--ghost btn--sm';
    out.textContent = 'Sign out';
    out.addEventListener('click', () => { clearSession(); location.href = '/'; });
    wrap.appendChild(avatar);
    wrap.appendChild(link);
    wrap.appendChild(out);
    host.appendChild(wrap);
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
    setAuthMode(opts.mode === 'signup' ? 'signup' : 'signin');
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
      toggle.addEventListener('click', () => {
        mobile.classList.toggle('open');
      });
      // Auto-close the mobile menu when the user picks a link.
      mobile.querySelectorAll('a').forEach((a) => {
        a.addEventListener('click', () => mobile.classList.remove('open'));
      });
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
  function boot() {
    wireNav();
    injectWhatsAppFloat();
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
    getSettings,
    openAuthModal, closeAuthModal, requireAuth,
    toast, escapeHtml, initialsOf, fmtDate, debounce,
  };
})();
