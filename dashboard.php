<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#0f766e" />
  <title>My dashboard — institution.bd</title>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>

<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/"><img src="/assets/img/logo.svg" alt="" /><span class="nav__brand-text"><span>institution.bd</span><small>smartschool.bd</small></span></a>
    <div class="nav__links"><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a><a href="/dashboard.php">Dashboard</a></div>
    <div class="nav__cta"><span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a><a href="/dashboard.php">Dashboard</a></div>
</nav>

<section style="padding-top:24px;">
  <div class="container">
    <div class="section__head" style="text-align:left;max-width:none;margin-bottom:14px;">
      <span class="eyebrow">Dashboard</span>
      <h2 class="mb-0">My institutions</h2>
      <p class="text-muted">All your claims — track verification, edit profile, post notices.</p>
    </div>

    <div data-needs-auth hidden>
      <div class="card">
        <h3>You're not signed in</h3>
        <p class="text-muted">Sign in with Google, Facebook or GitHub to see your claims.</p>
        <button class="btn btn--primary" data-open-auth>Sign in</button>
      </div>
    </div>

    <div data-account-card></div>

    <div data-list>
      <div class="card"><div class="skeleton" style="height:120px;"></div></div>
    </div>

    <div class="text-center mt-4">
      <a class="btn btn--primary" href="/claim.php">+ Claim another subdomain</a>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/directory.php">Directory</a> · <a href="/claim.php">Claim</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/dashboard.js"></script>
</body>
</html>
