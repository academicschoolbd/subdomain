/* ===========================================================================
   institution.bd / smartschool.bd — v5pro shared frontend module
   Modern ES6+ module. Exposes window.App for page scripts.
   =========================================================================== */

const API_BASE = '/api';
const STORAGE_TOKEN = 'inst_jwt';
const STORAGE_USER = 'inst_user';
const THEME_KEY = 'inst_theme';

// ─── Theme Management ────────────────────────────────────────────────────────

function applyTheme(mode) {
  document.documentElement.setAttribute('data-bs-theme', mode);
  document.documentElement.setAttribute('data-theme', mode);
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.content = mode === 'dark' ? '#0b1220' : '#0f766e';
  try { localStorage.setItem(THEME_KEY, mode); } catch {}
  renderThemeToggle();
}

function currentTheme() {
  try { const s = localStorage.getItem(THEME_KEY); if (s === 'dark' || s === 'light') return s; } catch {}
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function toggleTheme() {
  applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

function renderThemeToggle() {
  document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
    const dark = currentTheme() === 'dark';
    btn.setAttribute('aria-label', dark ? 'Switch to light' : 'Switch to dark');
    btn.innerHTML = dark
      ? '<i class="bi bi-sun-fill"></i>'
      : '<i class="bi bi-moon-stars-fill"></i>';
  });
}


// ─── Brand Theme (admin-controllable color) ──────────────────────────────────

function hexToRgb(hex) {
  if (!hex) return null;
  hex = String(hex).trim().replace(/^#/, '');
  if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  if (!/^[0-9a-f]{6}$/i.test(hex)) return null;
  return [parseInt(hex.slice(0,2),16), parseInt(hex.slice(2,4),16), parseInt(hex.slice(4,6),16)];
}

function rgbToHex(r,g,b) {
  const c = n => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2,'0');
  return '#' + c(r) + c(g) + c(b);
}

function mixColor(hex, target, weight) {
  const a = hexToRgb(hex), b = hexToRgb(target);
  if (!a || !b) return hex;
  return rgbToHex(a[0]+(b[0]-a[0])*weight, a[1]+(b[1]-a[1])*weight, a[2]+(b[2]-a[2])*weight);
}

function rgba(hex, alpha) {
  const c = hexToRgb(hex);
  return c ? `rgba(${c[0]},${c[1]},${c[2]},${alpha})` : `rgba(15,118,110,${alpha})`;
}

function applyBrandTheme(primary, primaryDark) {
  if (!hexToRgb(primary)) return;
  if (!primaryDark || !hexToRgb(primaryDark)) primaryDark = mixColor(primary, '#ffffff', 0.3);
  const css = `:root{--c-primary:${primary};--c-primary-700:${mixColor(primary,'#000',0.12)};--c-primary-50:${rgba(primary,0.08)};--c-primary-100:${rgba(primary,0.18)};--c-link:${primary};}html[data-theme="dark"]{--c-primary:${primaryDark};--c-primary-700:${mixColor(primaryDark,'#000',0.10)};--c-primary-50:${rgba(primaryDark,0.12)};--c-primary-100:${rgba(primaryDark,0.22)};--c-link:${primaryDark};}`;
  let el = document.getElementById('theme-vars');
  if (!el) { el = document.createElement('style'); el.id = 'theme-vars'; document.head.appendChild(el); }
  el.textContent = css;
  if (_settingsCache?.theme) {
    _settingsCache.theme.primary = primary;
    _settingsCache.theme.primary_dark = primaryDark;
  }
}

// ─── API Client ──────────────────────────────────────────────────────────────

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
  try { res = await fetch(url, init); }
  catch { throw { detail: 'Network error — please try again.', status: 0 }; }

  let data = null;
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    try { data = await res.json(); } catch {}
  } else if (!res.ok) {
    data = { detail: await res.text() };
  }
  if (!res.ok) {
    const err = data?.detail ? data : { detail: 'Request failed', status: res.status };
    err.status = res.status;
    throw err;
  }
  return data;
}

function qs(params) {
  if (!params) return '';
  const parts = Object.entries(params)
    .filter(([,v]) => v != null && v !== '')
    .map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
  return parts.length ? '?' + parts.join('&') : '';
}


// ─── Auth State ──────────────────────────────────────────────────────────────

function getToken() { try { return localStorage.getItem(STORAGE_TOKEN); } catch { return null; } }
function getUser() { try { return JSON.parse(localStorage.getItem(STORAGE_USER) || 'null'); } catch { return null; } }

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
function isAdmin() { const u = getUser(); return !!(u?.is_admin); }

function applyVisibilityGates() {
  const authed = isAuthed(), admin = isAdmin();
  document.querySelectorAll('[data-auth-only]').forEach(el => { el.hidden = !authed; });
  document.querySelectorAll('[data-admin-only]').forEach(el => { el.hidden = !admin; });
  document.querySelectorAll('[data-guest-only]').forEach(el => { el.hidden = authed; });
}

function renderUserChip() {
  applyVisibilityGates();
  const host = document.querySelector('[data-user-chip]');
  if (!host) return;
  host.innerHTML = '';
  const user = getUser();

  if (!user) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-primary btn-sm d-flex align-items-center gap-2';
    btn.setAttribute('data-open-auth', '');
    btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i><span class="d-none d-sm-inline">Sign in</span>';
    btn.addEventListener('click', () => openAuthModal());
    host.appendChild(btn);
    return;
  }

  // Authed: avatar + link
  const link = document.createElement('a');
  link.href = user.is_admin ? '/admin.php' : '/dashboard.php';
  link.className = 'd-flex align-items-center gap-2 text-decoration-none';
  link.title = user.name || user.email || 'Account';

  const avatar = document.createElement('span');
  avatar.className = 'd-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold';
  avatar.style.cssText = 'width:34px;height:34px;background:var(--c-primary);font-size:0.8rem;';
  if (user.avatar_url) {
    avatar.innerHTML = `<img src="${escapeHtml(user.avatar_url)}" alt="" class="rounded-circle" style="width:100%;height:100%;object-fit:cover;">`;
  } else {
    avatar.textContent = (user.name || user.email || '?')[0].toUpperCase();
  }
  link.appendChild(avatar);

  const name = document.createElement('span');
  name.className = 'd-none d-md-inline fw-semibold small';
  name.textContent = user.name || user.email || 'Account';
  link.appendChild(name);
  host.appendChild(link);
}


// ─── Settings Cache ──────────────────────────────────────────────────────────

let _settingsCache = null;
async function getSettings() {
  if (_settingsCache) return _settingsCache;
  _settingsCache = await api('/settings');
  return _settingsCache;
}

// ─── Auth Modal ──────────────────────────────────────────────────────────────

let _authModal = null;
let _authModalInstance = null;
let _modalMode = 'signin';

function createAuthModal() {
  if (_authModal) return _authModal;
  const div = document.createElement('div');
  div.className = 'modal fade modal-v5 auth-modal';
  div.id = 'authModal';
  div.tabIndex = -1;
  div.innerHTML = `
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" data-modal-title>Welcome back</h5>
            <p class="text-muted small mb-0" data-modal-sub>Sign in to manage your subdomains.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="d-grid gap-2 mb-3" data-modal-providers>
            <div class="skeleton-v5" style="height:44px;"></div>
          </div>
          <div class="text-center text-muted small mb-3" data-auth-divider>
            <span class="bg-body px-2 position-relative" style="z-index:1;">or with email</span>
            <hr class="position-relative" style="margin-top:-10px;z-index:0;">
          </div>
          <form data-auth-form novalidate>
            <div class="mb-3" data-field="name" hidden>
              <label class="form-label small fw-semibold">Full name</label>
              <input type="text" class="form-control" name="name" autocomplete="name" placeholder="Your name">
              <div class="invalid-feedback" data-err="name"></div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Email address</label>
              <input type="email" class="form-control" name="email" autocomplete="email" placeholder="you@example.com" required>
              <div class="invalid-feedback" data-err="email"></div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Password</label>
              <input type="password" class="form-control" name="password" autocomplete="current-password" placeholder="At least 8 characters" required>
              <div class="invalid-feedback" data-err="password"></div>
            </div>
            <div class="mb-3" data-field="mobile" hidden>
              <label class="form-label small fw-semibold">Mobile <span class="text-muted">(optional)</span></label>
              <input type="tel" class="form-control" name="mobile" autocomplete="tel" placeholder="01XXXXXXXXX">
            </div>
            <button type="submit" class="btn btn-primary w-100" data-auth-submit>Sign in</button>
            <div class="text-center mt-3 small">
              <span data-switch-text>New here?</span>
              <button type="button" class="btn btn-link btn-sm p-0" data-switch>Create an account</button>
            </div>
            <div class="text-center mt-2 small" data-forgot-row>
              <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-forgot>Forgot password?</button>
            </div>
          </form>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-center">
          <small class="text-muted">By continuing you agree to our <a href="/terms.php">terms</a> and <a href="/privacy.php">privacy policy</a>.</small>
        </div>
      </div>
    </div>`;
  document.body.appendChild(div);
  _authModal = div;

  // Wire events
  div.querySelector('[data-switch]').addEventListener('click', () => {
    setAuthMode(_modalMode === 'signin' ? 'signup' : 'signin');
  });
  div.querySelector('[data-forgot]').addEventListener('click', onForgotClick);
  div.querySelector('[data-auth-form]').addEventListener('submit', onAuthSubmit);

  _authModalInstance = new bootstrap.Modal(div);
  return div;
}


function setAuthMode(mode) {
  _modalMode = mode === 'signup' ? 'signup' : 'signin';
  if (!_authModal) return;
  const title = _authModal.querySelector('[data-modal-title]');
  const sub = _authModal.querySelector('[data-modal-sub]');
  const nameF = _authModal.querySelector('[data-field="name"]');
  const mobF = _authModal.querySelector('[data-field="mobile"]');
  const submit = _authModal.querySelector('[data-auth-submit]');
  const swT = _authModal.querySelector('[data-switch-text]');
  const swB = _authModal.querySelector('[data-switch]');
  const fRow = _authModal.querySelector('[data-forgot-row]');

  if (_modalMode === 'signup') {
    title.textContent = 'Create your account';
    sub.textContent = 'Free forever — no credit card needed.';
    nameF.hidden = false; mobF.hidden = false;
    submit.textContent = 'Create account';
    swT.textContent = 'Already have an account?';
    swB.textContent = 'Sign in instead';
    if (fRow) fRow.hidden = true;
  } else {
    title.textContent = 'Welcome back';
    sub.textContent = 'Sign in to manage your subdomains.';
    nameF.hidden = true; mobF.hidden = true;
    submit.textContent = 'Sign in';
    swT.textContent = 'New here?';
    swB.textContent = 'Create an account';
    if (fRow) fRow.hidden = false;
  }
  // Clear errors
  _authModal.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  _authModal.querySelectorAll('[data-err]').forEach(el => { el.textContent = ''; });
}

async function onForgotClick() {
  if (!_authModal) return;
  const form = _authModal.querySelector('[data-auth-form]');
  const emailInp = form.querySelector('input[name="email"]');
  const email = (emailInp?.value || '').trim().toLowerCase();
  if (!email || !/.+@.+\..+/.test(email)) {
    emailInp.classList.add('is-invalid');
    const err = form.querySelector('[data-err="email"]');
    if (err) err.textContent = 'Enter your email first, then click "Forgot password".';
    emailInp?.focus();
    return;
  }
  try {
    const r = await api('/auth/forgot-password', { method: 'POST', body: { email } });
    if (r?.reset_url) {
      toast('Demo mode — opening reset link.', 'success');
      window.open(r.reset_url, '_blank', 'noopener');
    } else {
      toast('If an account exists, a reset link has been sent.', 'success');
    }
  } catch (e) {
    toast(e?.detail || 'Could not send reset link.', 'error');
  }
}

async function onAuthSubmit(ev) {
  ev.preventDefault();
  const form = ev.currentTarget;
  const submit = form.querySelector('[data-auth-submit]');
  const data = Object.fromEntries(new FormData(form).entries());
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('[data-err]').forEach(el => { el.textContent = ''; });
  submit.disabled = true;
  const origLabel = submit.textContent;
  submit.textContent = _modalMode === 'signup' ? 'Creating...' : 'Signing in...';
  try {
    const path = _modalMode === 'signup' ? '/auth/register' : '/auth/login';
    const body = _modalMode === 'signup'
      ? { name: data.name || '', email: data.email || '', password: data.password || '', mobile: data.mobile || '' }
      : { email: data.email || '', password: data.password || '' };
    const r = await api(path, { method: 'POST', body });
    if (r?.token) {
      setSession(r.token, r.user);
      closeAuthModal();
      toast(_modalMode === 'signup' ? 'Account created!' : 'Signed in.', 'success');
      if (typeof window.__onAuthSuccess === 'function') window.__onAuthSuccess(r.user);
      else setTimeout(() => location.reload(), 300);
    }
  } catch (e) {
    const errs = e?.errors || {};
    let any = false;
    for (const [k, v] of Object.entries(errs)) {
      const inp = form.querySelector(`input[name="${k}"]`);
      const err = form.querySelector(`[data-err="${k}"]`);
      if (inp) inp.classList.add('is-invalid');
      if (err) { err.textContent = v; any = true; }
    }
    if (!any) toast(e?.detail || 'Sign-in failed.', 'error');
  } finally {
    submit.disabled = false;
    submit.textContent = origLabel;
  }
}


async function openAuthModal(opts = {}) {
  createAuthModal();
  const mode = opts.mode === 'signup' ? 'signup' : 'signin';
  setAuthMode(mode);
  _authModalInstance.show();

  // Load OAuth providers
  const providersHost = _authModal.querySelector('[data-modal-providers]');
  const divider = _authModal.querySelector('[data-auth-divider]');
  providersHost.innerHTML = '<div class="skeleton-v5" style="height:44px;border-radius:8px;"></div>';

  let s;
  try { s = await getSettings(); } catch { providersHost.innerHTML = ''; divider.hidden = true; return; }

  const providers = s?.auth?.oauth_providers || [];
  providersHost.innerHTML = '';
  if (!providers.length) { divider.hidden = true; return; }
  divider.hidden = false;

  const next = opts.next || (location.pathname + location.search);
  providers.forEach(p => {
    const a = document.createElement('a');
    a.className = 'oauth-btn';
    a.href = `/api/auth/oauth/${p.id}/start?next=${encodeURIComponent(next)}`;
    a.innerHTML = `<img src="/assets/img/${p.id}.svg" alt=""><span>Continue with ${escapeHtml(p.label)}</span>`;
    providersHost.appendChild(a);
  });
}

function closeAuthModal() {
  _authModalInstance?.hide();
}

function requireAuth(opts) {
  if (isAuthed()) return Promise.resolve(getUser());
  openAuthModal(opts);
  return Promise.reject({ detail: 'Sign-in required' });
}

// ─── Toast Notifications ─────────────────────────────────────────────────────

function toast(message, type = 'info') {
  const host = document.getElementById('toastHost') || document.body;
  const el = document.createElement('div');
  const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-dark';
  el.className = `toast show align-items-center text-white ${bgClass} border-0`;
  el.setAttribute('role', 'alert');
  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${escapeHtml(message)}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
  host.appendChild(el);
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 4000);
}

// ─── WhatsApp Float ──────────────────────────────────────────────────────────

async function injectWhatsAppFloat() {
  try {
    const s = await getSettings();
    const url = s?.whatsapp?.support_url;
    if (!url || document.querySelector('.wa-float')) return;
    const a = document.createElement('a');
    a.className = 'wa-float';
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener';
    a.title = 'Chat on WhatsApp';
    a.innerHTML = '<img src="/assets/img/whatsapp.svg" alt="WhatsApp">';
    document.body.appendChild(a);
  } catch {}
}


// ─── Utilities ───────────────────────────────────────────────────────────────

function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}

function debounce(fn, ms = 250) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function fmtDate(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  } catch { return iso; }
}

function initialsOf(name) {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  return (parts[0]?.[0] || '').toUpperCase() + (parts[1]?.[0] || '').toUpperCase() || name[0].toUpperCase();
}

// ─── Navbar Scroll Effect ────────────────────────────────────────────────────

function wireNavScroll() {
  const nav = document.querySelector('.navbar-v5');
  if (!nav) return;
  const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 10);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

// ─── Boot ────────────────────────────────────────────────────────────────────

function boot() {
  // Theme
  applyTheme(currentTheme());
  document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
    btn.addEventListener('click', toggleTheme);
  });

  // Nav
  wireNavScroll();
  renderUserChip();

  // Wire all [data-open-auth] buttons
  document.querySelectorAll('[data-open-auth]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const mode = btn.getAttribute('data-mode') || 'signin';
      openAuthModal({ mode });
    });
  });

  // Sign-out buttons
  document.querySelectorAll('[data-signout]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      clearSession();
      location.href = '/';
    });
  });

  // Footer year
  document.querySelectorAll('[data-year]').forEach(el => {
    el.textContent = new Date().getFullYear();
  });

  // WhatsApp float
  injectWhatsAppFloat();

  // WhatsApp links from settings
  getSettings().then(s => {
    const wa = s?.whatsapp || {};
    document.querySelectorAll('[data-wa-cta], [data-wa-footer-link]').forEach(el => {
      if (wa.community_url) el.href = wa.community_url;
    });
    document.querySelectorAll('[data-wa-footer-support]').forEach(el => {
      if (wa.support_url) el.href = wa.support_url;
    });
    if (wa.community_title) {
      document.querySelectorAll('[data-wa-title]').forEach(el => { el.textContent = wa.community_title; });
    }
    if (wa.community_subtitle) {
      document.querySelectorAll('[data-wa-sub]').forEach(el => { el.textContent = wa.community_subtitle; });
    }
  }).catch(() => {});
}

// Run boot on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}


// ─── Export as window.App (for page-specific scripts) ────────────────────────

window.App = Object.freeze({
  api,
  qs,
  getToken,
  getUser,
  setSession,
  clearSession,
  isAuthed,
  isAdmin,
  getSettings,
  openAuthModal,
  closeAuthModal,
  requireAuth,
  toast,
  escapeHtml,
  debounce,
  fmtDate,
  initialsOf,
  applyBrandTheme,
  applyTheme,
  currentTheme,
});
