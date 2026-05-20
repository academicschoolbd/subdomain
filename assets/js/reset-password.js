/* Reset-password page (v3.0).
 * Reads ?token=… from the URL, posts it with the new password to
 * /api/auth/reset-password, then signs the user in.
 */
(function () {
  'use strict';
  const App = window.App;

  const y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();

  const form = document.querySelector('[data-reset-form]');
  const submit = document.querySelector('[data-reset-submit]');
  const sub = document.querySelector('[data-reset-sub]');

  const params = new URLSearchParams(location.search);
  const token = (params.get('token') || '').trim();
  if (!token) {
    sub.textContent = 'This reset link is missing or malformed. Request a new one from the sign-in screen.';
    submit.disabled = true;
    return;
  }

  function err(field, msg) {
    const el = form.querySelector('[data-err="' + field + '"]');
    if (el) el.textContent = msg || '';
    const f = el && el.closest('.field');
    if (f) f.classList.toggle('field--error', !!msg);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err('password', ''); err('confirm', '');
    const fd = new FormData(form);
    const pwd = (fd.get('password') || '').toString();
    const cnf = (fd.get('confirm') || '').toString();
    if (pwd.length < 8) { err('password', 'Password must be at least 8 characters.'); return; }
    if (pwd !== cnf)    { err('confirm', 'Passwords do not match.'); return; }

    submit.disabled = true;
    const orig = submit.textContent;
    submit.textContent = 'Resetting…';
    try {
      const r = await App.api('/auth/reset-password', { method: 'POST', body: { token, password: pwd } });
      if (r && r.token) {
        App.setSession(r.token, r.user);
        App.toast('Password updated — you are signed in.', 'success');
        setTimeout(() => { location.replace('/dashboard.php'); }, 600);
      }
    } catch (e2) {
      const errs = (e2 && e2.errors) || {};
      if (errs.token)    err('password', errs.token);
      if (errs.password) err('password', errs.password);
      if (!errs.token && !errs.password) {
        App.toast((e2 && e2.detail) || 'Could not reset password.', 'error');
      }
    } finally {
      submit.disabled = false;
      submit.textContent = orig;
    }
  });
})();
