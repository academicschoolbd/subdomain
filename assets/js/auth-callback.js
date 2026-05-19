/* OAuth callback landing.
 *
 * The backend redirects browsers here with:
 *   /auth/callback.php#payload=<base64url(JSON)>&next=<path>
 *
 * JSON shape:
 *   { ok: true,  token: '...', user: {...} }
 *   { ok: false, error: '...' }
 */
(function () {
  'use strict';
  const App = window.App;

  function b64urlDecode(s) {
    try {
      s = s.replace(/-/g, '+').replace(/_/g, '/');
      const pad = s.length % 4; if (pad) s += '='.repeat(4 - pad);
      return atob(s);
    } catch (e) { return ''; }
  }

  function parseFrag() {
    const out = {};
    const f = location.hash.replace(/^#/, '');
    if (!f) return out;
    f.split('&').forEach((p) => {
      const [k, v] = p.split('=');
      if (!k) return;
      out[decodeURIComponent(k)] = decodeURIComponent(v || '');
    });
    return out;
  }

  function safeNext(p) {
    if (!p || typeof p !== 'string') return '/';
    // Only allow same-origin relative paths.
    if (p.startsWith('/') && !p.startsWith('//')) return p;
    return '/';
  }

  const titleEl = document.querySelector('[data-cb-title]');
  const subEl = document.querySelector('[data-cb-sub]');
  const cancel = document.querySelector('[data-cb-cancel]');

  const frag = parseFrag();
  const payloadRaw = frag.payload || '';
  const next = safeNext(frag.next || '/');
  let payload = null;
  try { payload = JSON.parse(b64urlDecode(payloadRaw)); } catch (e) { /* */ }

  // Wipe the fragment so token doesn't linger.
  try { history.replaceState(null, '', location.pathname); } catch (e) { /* */ }

  if (!payload) {
    titleEl.textContent = 'Sign-in failed';
    subEl.textContent = 'We couldn\'t process the sign-in result. Please try again.';
    cancel.textContent = 'Back to home';
    return;
  }
  if (!payload.ok) {
    titleEl.textContent = 'Sign-in failed';
    subEl.textContent = payload.error || 'Something went wrong with the social sign-in.';
    cancel.textContent = 'Back to home';
    return;
  }
  // Success
  App.setSession(payload.token, payload.user);
  titleEl.textContent = 'Welcome' + (payload.user && payload.user.name ? ', ' + payload.user.name.split(' ')[0] : '') + '!';
  subEl.textContent = 'Redirecting you to ' + next + ' …';
  setTimeout(() => { location.replace(next); }, 600);
})();
