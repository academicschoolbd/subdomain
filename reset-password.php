<?php try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) { /* DB unavailable — page still renders, JS surfaces the error */ } if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Reset password — institution.bd</title>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>

<a class="skip-link" href="#main">Skip to main content</a>

<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/"><img src="/assets/img/logo.svg" alt="" /><span class="nav__brand-text"><span>institution.bd</span><small>smartschool.bd</small></span></a>
    <div class="nav__cta"><span data-user-chip></span></div>
  </div>
</nav>

<main id="main" class="container" style="max-width:520px;margin:0 auto;padding:48px 18px;">
  <section class="card">
    <h1 style="margin:0 0 6px;font-size:1.6rem;">Reset your password</h1>
    <p class="text-muted" data-reset-sub>Pick a new password for your institution.bd account.</p>

    <form data-reset-form class="form-grid" novalidate>
      <div class="field field--wide">
        <label class="label" for="rp-pwd">New password</label>
        <input id="rp-pwd" name="password" type="password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters" />
        <p class="field__err" data-err="password"></p>
      </div>
      <div class="field field--wide">
        <label class="label" for="rp-pwd2">Confirm password</label>
        <input id="rp-pwd2" name="confirm" type="password" minlength="8" required autocomplete="new-password" />
        <p class="field__err" data-err="confirm"></p>
      </div>
      <div class="field field--wide">
        <button class="btn btn--primary btn--block" type="submit" data-reset-submit>Reset password</button>
      </div>
    </form>

    <p class="text-muted mt-3" style="font-size:.85rem;">
      Remembered it? <a href="/" data-open-auth>Sign in instead</a>.
    </p>
  </section>
</main>

<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
    <span class="footer__heart"><span class="footer__heart-ico" aria-hidden="true">&#10084;&#65039;</span> Built with love in Bangladesh</span>
  </div>
  <div class="container footer__status" aria-live="polite">
    <span class="footer__status-pill" data-platform-status>
      <span class="footer__status-dot" aria-hidden="true"></span>
      <span data-platform-status-text>All systems operational</span>
    </span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/reset-password.js"></script>
</body>
</html>
