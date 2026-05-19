<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#0f766e" />
  <title>Directory — institution.bd</title>
  <meta name="description" content="Browse verified Bangladeshi institutions on institution.bd and smartschool.bd — schools, colleges, universities, madrasas and NGOs." />
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>

<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/">
      <img src="/assets/img/logo.svg" alt="" />
      <span class="nav__brand-text"><span>institution.bd</span><small>smartschool.bd</small></span>
    </a>
    <div class="nav__links">
      <a href="/">Home</a>
      <a href="/directory.php">Directory</a>
      <a href="/claim.php">Claim a subdomain</a>
    </div>
    <div class="nav__cta">
      <span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile>
    <a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a>
  </div>
</nav>

<section style="padding-top: 36px;">
  <div class="container">
    <div class="section__head" style="text-align:left;max-width:none;margin-bottom:22px;">
      <span class="eyebrow">Directory</span>
      <h2 class="mb-0">Browse verified institutions</h2>
      <p class="text-muted">Live, AJAX-powered search across institution.bd and smartschool.bd.</p>
    </div>

    <form class="card searchbar" data-search-form>
      <div class="field"><input type="text" placeholder="Search by name, slug or EIIN…" data-q autocomplete="off" /></div>
      <div class="field">
        <select data-brand>
          <option value="">All brands</option>
          <option value="institution.bd">institution.bd</option>
          <option value="smartschool.bd">smartschool.bd</option>
        </select>
      </div>
      <div class="field">
        <select data-category>
          <option value="">All categories</option>
          <option value="school">School</option>
          <option value="college">College</option>
          <option value="university">University</option>
          <option value="madrasa">Madrasa</option>
          <option value="kindergarten">Kindergarten</option>
          <option value="polytechnic">Polytechnic</option>
          <option value="coaching">Coaching center</option>
          <option value="training">Training institute</option>
          <option value="ngo">NGO</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="field">
        <select data-division>
          <option value="">All divisions</option>
          <option>Dhaka</option><option>Chattogram</option><option>Khulna</option>
          <option>Rajshahi</option><option>Rangpur</option><option>Sylhet</option>
          <option>Mymensingh</option><option>Barishal</option>
        </select>
      </div>
      <button class="btn btn--primary" type="submit">Search</button>
    </form>

    <p class="text-muted mt-3" data-count>&nbsp;</p>

    <div class="dir-grid mt-3" data-list>
      <div class="dir-skeleton skeleton"></div><div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div><div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div><div class="dir-skeleton skeleton"></div>
    </div>

    <div class="text-center mt-5">
      <button class="btn" data-more style="display:none;">Load more</button>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/claim.php">Claim</a> · <a href="/dashboard.php">Dashboard</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/directory.js"></script>
</body>
</html>
