<?php
/**
 * Home page — server-renders the live platform stats inline so the
 * realtime tiles never show a loading skeleton on first paint. They
 * still auto-refresh every 30 s in the background.
 */
$_initialStats = ['users_registered' => 0, 'claims_total' => 0, 'claims_pending' => 0, 'claims_rejected' => 0];
try {
    require_once __DIR__ . '/api/bootstrap.php';
    $pdo = db($CONFIG);
    $byStatus = ['verified' => 0, 'pending' => 0, 'needs_info' => 0, 'rejected' => 0, 'suspended' => 0, 'seeded' => 0];
    foreach ($pdo->query("SELECT status, COUNT(*) c FROM institutions GROUP BY status")->fetchAll() as $r) {
        $byStatus[$r['status']] = (int)$r['c'];
    }
    $_initialStats = [
        'users_registered' => (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
        'claims_total'     => $byStatus['verified'] + $byStatus['pending'] + $byStatus['needs_info'] + $byStatus['rejected'] + $byStatus['suspended'],
        'claims_pending'   => $byStatus['pending'] + $byStatus['needs_info'],
        'claims_rejected'  => $byStatus['rejected'] + $byStatus['suspended'],
    ];
} catch (Throwable $_e) { /* fall back to zeros on first install */ }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>institution.bd · smartschool.bd — Free verified subdomains for Bangladeshi institutions</title>
  <meta name="description" content="Claim a free institution.bd or smartschool.bd subdomain for your school, college, university, madrasa or NGO. Verified directory, Cloudflare-backed SSL, sign in with email, Google, Facebook or GitHub." />
  <meta property="og:title" content="institution.bd — Free verified subdomains" />
  <meta property="og:description" content="One free verified subdomain for every Bangladeshi school, college, university, madrasa and NGO." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://institution.bd/" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>

<!-- ============================ NAV ============================ -->
<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/">
      <img src="/assets/img/logo.svg" alt="institution.bd logo" />
      <span class="nav__brand-text">
        <span>institution.bd</span>
        <small>smartschool.bd</small>
      </span>
    </a>
    <div class="nav__links">
      <a href="#features">Features</a>
      <a href="#brands">Brands</a>
      <a href="#how">How it works</a>
      <a href="/directory.php">Directory</a>
      <a href="#faq">FAQ</a>
    </div>
    <div class="nav__cta">
      <a href="/claim.php" class="btn btn--ghost btn--sm" style="display:none" data-md-show>Claim</a>
      <span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile>
    <a href="#features">Features</a>
    <a href="#brands">Brands</a>
    <a href="#how">How it works</a>
    <a href="/directory.php">Directory</a>
    <a href="#faq">FAQ</a>
    <a href="/privacy.php">Privacy</a>
    <a href="/terms.php">Terms</a>
  </div>
</nav>

<!-- ============================ HERO ============================ -->
<header class="hero hero--center hero--ready" id="main">
  <div class="container hero__inner">
    <span class="hero__eyebrow"><span class="dot"></span> Free forever · Instant DNS · Cloudflare-backed SSL · No card needed</span>
    <h1 class="hero__title">A free home on the web for<br><span class="hero__title--accent">every Bangladeshi institution.</span></h1>
    <p class="hero__sub">Claim a verified <strong>.institution.bd</strong> or <strong>.smartschool.bd</strong> subdomain in under a minute. Auto SSL via Cloudflare, Bengali ready, no documents required.</p>

    <form class="search-hero" data-slug-form aria-label="Check subdomain availability">
      <span class="search-hero__ico" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
      </span>
      <input type="text" required minlength="3" maxlength="40" autocomplete="off"
             placeholder="Type your school / college name…" data-slug-input aria-label="Subdomain" />
      <span class="search-hero__suffix">
        <select data-brand-select aria-label="Brand">
          <option value="institution.bd">.institution.bd</option>
          <option value="smartschool.bd">.smartschool.bd</option>
        </select>
      </span>
      <button type="submit" class="btn btn--primary search-hero__submit" data-slug-claim>Search</button>
    </form>

    <!-- v3.2 — search result card (matches the requested ready.bd-style UX). -->
    <div class="search-result" data-search-result hidden role="status" aria-live="polite">
      <div class="search-result__main">
        <span class="search-result__ico" data-result-ico aria-hidden="true"></span>
        <div class="search-result__body">
          <div class="search-result__title" data-result-title></div>
          <p class="search-result__sub" data-result-sub></p>
        </div>
        <a class="btn btn--primary" data-result-cta href="#" hidden>Claim Now</a>
      </div>
      <div class="search-result__alt" data-result-alt hidden>
        <span class="search-result__alt-label">Also available:</span>
        <div class="search-result__alt-row">
          <code data-alt-name></code>
          <a class="btn btn--outline btn--sm" data-alt-cta href="#">Claim</a>
        </div>
      </div>
    </div>

    <!-- legacy slug-status pill (kept hidden, app.js still references the data-attribute on other pages). -->
    <p class="slug-status text-center" data-slug-status hidden></p>

    <ul class="check-row" aria-label="What's included">
      <li><span class="check"></span> Live in &lt; 60 seconds</li>
      <li><span class="check"></span> No documents required</li>
      <li><span class="check"></span> Auto SSL via Cloudflare</li>
      <li><span class="check"></span> Bengali + English ready</li>
    </ul>

    <p class="hero__signin" data-guest-only>
      Already have a subdomain?
      <a href="#" data-open-auth>Sign in</a>
      <span class="hero__signin-sep">·</span>
      <a href="#" data-open-auth data-mode="signup">Create account</a>
      <span class="hero__signin-sep">·</span>
      Google · Facebook · GitHub · Email
    </p>

    <!-- v5 — Sponsor logos strip (admin can edit / hide / show via Platform settings).
         Marked with [data-hero-sponsors] so admin.js can swap items in. Hidden by default
         when there is nothing configured. -->
    <div class="hero-sponsors" data-hero-sponsors>
      <span class="hero-sponsors__label">Trusted infrastructure partners</span>
      <a class="hero-sponsors__item" href="https://metrovps.com" target="_blank" rel="noopener nofollow sponsored" data-sponsor="metrovps">
        <span class="hero-sponsors__dot" aria-hidden="true"></span>
        MetroVPS
      </a>
      <a class="hero-sponsors__item" href="https://hostomega.com" target="_blank" rel="noopener nofollow sponsored" data-sponsor="hostomega">
        <span class="hero-sponsors__dot" aria-hidden="true" style="background:#22c55e;"></span>
        Hostomega
      </a>
    </div>
  </div>
</header>

<!-- ============================ LIVE STATS ============================ -->
<section class="live-stats" id="live-stats" aria-label="Realtime platform activity">
  <div class="container">
    <div class="live-stats__head">
      <div>
        <span class="eyebrow">Realtime</span>
        <h2>Platform activity right now</h2>
      </div>
      <span class="live-stats__pulse"><span class="pulse-dot"></span> Live · auto-refresh every 30 s</span>
    </div>
    <!-- v4.5 — only two headline tiles: registered users + domains issued.
         The pending / rejected counts are admin-only metrics and live in
         the moderator dashboard now, not on the public homepage. -->
    <div class="live-stats__grid live-stats__grid--two" data-live-stats>
      <div class="stat-tile">
        <span class="stat-tile__ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </span>
        <span class="stat-tile__num" data-tile="users"><?= number_format($_initialStats['users_registered']) ?></span>
        <span class="stat-tile__lab">Total registered users</span>
        <span class="stat-tile__delta" data-tile-delta="users"></span>
      </div>
      <div class="stat-tile stat-tile--claims">
        <span class="stat-tile__ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </span>
        <span class="stat-tile__num" data-tile="claims_total"><?= number_format($_initialStats['claims_total']) ?></span>
        <span class="stat-tile__lab">Total domains registered</span>
        <span class="stat-tile__delta" data-tile-delta="claims_total"></span>
      </div>
    </div>
  </div>
</section>

<!-- ============================ BRANDS ============================ -->
<section id="brands" class="section--alt">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow">Two free brands</span>
      <h2>Pick the subdomain that fits</h2>
      <p>Whichever brand suits your institution, sign-up and claim are exactly the same.</p>
    </div>
    <div class="brand-grid">
      <a class="brand-card" href="/claim.php?brand=institution.bd">
        <span class="brand-card__tag">Popular</span>
        <span class="brand-card__name">.institution.bd</span>
        <span class="brand-card__title">Universities · Colleges · NGOs</span>
        <p class="brand-card__desc">Ideal for higher-ed institutions, polytechnics, training centres, foundations and non-profits across Bangladesh.</p>
        <span class="brand-card__cta">Claim a .institution.bd subdomain →</span>
      </a>
      <a class="brand-card brand-card--alt" href="/claim.php?brand=smartschool.bd">
        <span class="brand-card__tag brand-card__tag--alt">For schools</span>
        <span class="brand-card__name">.smartschool.bd</span>
        <span class="brand-card__title">Schools · Madrasas · Coaching</span>
        <p class="brand-card__desc">Built for K-12 schools, kindergartens, madrasas and coaching centres — a clean, parent-friendly subdomain.</p>
        <span class="brand-card__cta">Claim a .smartschool.bd subdomain →</span>
      </a>
    </div>
  </div>
</section>

<!-- ============================ FEATURES ============================ -->
<section id="features">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow">What you get</span>
      <h2>Everything an institution needs — for free</h2>
      <p>Designed for Bangladeshi schools, colleges, madrasas, polytechnics and NGOs. No credit card, no hidden charge, no ads.</p>
    </div>
    <div class="pro-grid">
      <div class="pro-card">
        <span class="pro-card__ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <h3>Free SSL certificate</h3>
        <p>Cloudflare-issued TLS certificates on every subdomain. HTTPS by default — no setup.</p>
      </div>
      <div class="pro-card">
        <span class="pro-card__ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </span>
        <h3>Auto DNS via Cloudflare</h3>
        <p>Approved claims get a Cloudflare-proxied A record automatically — instant SSL, instant cache.</p>
      </div>
      <div class="pro-card">
        <span class="pro-card__ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </span>
        <h3>Bengali + English ready</h3>
        <p>Every institution page renders বাংলা and English side-by-side, indexed cleanly by Google.</p>
      </div>
      <div class="pro-card">
        <span class="pro-card__ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </span>
        <h3>Verified directory</h3>
        <p>Admin moderation + EIIN / board-letter check keeps the public directory clean and trustworthy.</p>
      </div>
      <div class="pro-card">
        <span class="pro-card__ico" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9-5 9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5"/><path d="M3 17l9 5 9-5"/></svg>
        </span>
        <h3>Full DNS control</h3>
        <p>Once verified, manage A, AAAA, CNAME, TXT, MX and NS records yourself — straight from your dashboard.</p>
      </div>
      <div class="pro-card pro-card--wa">
        <span class="pro-card__ico pro-card__ico--wa" aria-hidden="true">
          <img src="/assets/img/whatsapp.svg" alt="" />
        </span>
        <h3>WhatsApp support</h3>
        <p>Floating WhatsApp button on every page. Get help in Bengali or English, in real time.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================ HOW IT WORKS ============================ -->
<section id="how" class="section--alt">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow">How it works</span>
      <h2>From subdomain idea to live site in 4 steps</h2>
      <p>Most institutions are live within one working day — many under a minute.</p>
    </div>
    <div class="steps steps--timeline">
      <div class="step">
        <h3>Sign in</h3>
        <p>Use email + password — or one-click with Google, Facebook, GitHub.</p>
        <span class="step__time">~ 30 seconds</span>
      </div>
      <div class="step">
        <h3>Pick your subdomain</h3>
        <p>Live availability check — reserved names auto-blocked. Choose institution.bd or smartschool.bd.</p>
        <span class="step__time">~ 30 seconds</span>
      </div>
      <div class="step">
        <h3>Verify in one upload</h3>
        <p>Optional: upload an EIIN certificate, board letter, trade licence or admin NID for a verified badge.</p>
        <span class="step__time">~ 1 minute</span>
      </div>
      <div class="step">
        <h3>Go live</h3>
        <p>On approval, Cloudflare creates your DNS record automatically and your subdomain is online.</p>
        <span class="step__time">Instant activation</span>
      </div>
    </div>
  </div>
</section>

<!-- ============================ DIRECTORY PREVIEW ============================ -->
<section>
  <div class="container">
    <div class="flex-between" style="margin-bottom:24px;">
      <div>
        <span class="eyebrow">Featured</span>
        <h2 class="mb-0">Recently verified institutions</h2>
      </div>
      <a href="/directory.php" class="btn btn--outline btn--sm">View full directory →</a>
    </div>
    <div class="dir-grid" data-featured-list>
      <div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div>
      <div class="dir-skeleton skeleton"></div>
    </div>
  </div>
</section>

<!-- ============================ WHATSAPP COMMUNITY ============================ -->
<section id="community" style="padding-top:32px;">
  <div class="container">
    <div class="wa-band" data-wa-band>
      <div>
        <div class="wa-band__icon"><img src="/assets/img/whatsapp.svg" alt="" /></div>
        <h2 data-wa-title>Join our WhatsApp community</h2>
        <p data-wa-sub>Get announcements, support and meet other institution owners and admins from across Bangladesh. Free to join, completely opt-in.</p>
      </div>
      <a href="#" class="btn btn--white btn--lg" data-wa-cta target="_blank" rel="noopener">Join community on WhatsApp</a>
    </div>
  </div>
</section>

<!-- ============================ FAQ ============================ -->
<section id="faq" class="section--alt">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow">FAQ</span>
      <h2>Frequently asked questions</h2>
      <p>Everything you need to know about our free verified subdomains.</p>
    </div>
    <div class="faq-list">
      <details class="faq" open>
        <summary>Is it really free? What's the catch?</summary>
        <p>Yes — 100% free for verified Bangladeshi educational institutions and NGOs. We're supported by infrastructure sponsors and community donations. No credit card, no setup fee, no yearly renewal.</p>
      </details>
      <details class="faq">
        <summary>Can my school use it as the main website?</summary>
        <p>Absolutely. Once your claim is verified, point any web hosting to your subdomain via DNS — or use the built-in institution page (logo, banner, about, notice board) as your free mini-site.</p>
      </details>
      <details class="faq">
        <summary>Who can claim a subdomain?</summary>
        <p>Any registered Bangladeshi school, college, university, madrasa, polytechnic, training institute or NGO. You'll need to upload one proof document (EIIN certificate, board letter, trade licence or admin NID) during the claim — only when admin moderation is ON.</p>
      </details>
      <details class="faq">
        <summary>What DNS records do you support?</summary>
        <p>A, AAAA, CNAME, TXT, MX and NS records are supported. Once your claim is verified, you can add and edit records yourself from your dashboard.</p>
      </details>
      <details class="faq">
        <summary>What if I don't want to use Google / Facebook / GitHub?</summary>
        <p>You can sign up and sign in with email + password as well. OAuth is offered as a one-click convenience — it's never required.</p>
      </details>
      <details class="faq">
        <summary>What happens if I violate the terms?</summary>
        <p>Accounts found violating our <a href="/terms.php">acceptable-use policy</a> (commercial spam, impersonation, fraud, etc.) will be suspended, with the DNS record removed. Read the full <a href="/terms.php">terms of service</a> before claiming.</p>
      </details>
    </div>
  </div>
</section>

<!-- ============================ FINAL CTA ============================ -->
<section class="cta-band">
  <div class="container text-center">
    <span class="eyebrow">Ready when you are</span>
    <h2>Give your institution a home on the web.</h2>
    <p class="text-muted">Free, verified, Cloudflare-backed. One-minute sign-up.</p>
    <div class="flex" style="justify-content:center; margin-top:18px;">
      <a href="#" class="btn btn--primary btn--lg" data-open-auth data-mode="signup" data-guest-only>Create your free account</a>
      <a href="/dashboard.php" class="btn btn--lg" data-auth-only hidden>Go to my dashboard →</a>
      <a href="/directory.php" class="btn btn--lg">Browse the directory</a>
    </div>
  </div>
</section>

<!-- ============================ FOOTER ============================ -->
<footer class="footer">
  <div class="container footer__grid">
    <div class="footer__brand">
      <a class="nav__brand" href="/" style="color:#fff;">
        <img src="/assets/img/logo.svg" alt="" />
        <span class="nav__brand-text">
          <span>institution.bd</span>
          <small style="color:#94a3b8;">smartschool.bd</small>
        </span>
      </a>
      <p style="margin-top:14px;">Free verified subdomains for every Bangladeshi institution. Built for educators, NGOs and the open web.</p>
    </div>
    <div>
      <h4>Platform</h4>
      <ul>
        <li><a href="/directory.php">Directory</a></li>
        <li><a href="/claim.php">Claim a subdomain</a></li>
        <li><a href="/dashboard.php" data-auth-only hidden>My dashboard</a></li>
        <li><a href="/admin.php" data-admin-only hidden>Admin</a></li>
      </ul>
    </div>
    <div>
      <h4>Brands</h4>
      <ul>
        <li><a href="/directory.php?brand=institution.bd">institution.bd</a></li>
        <li><a href="/directory.php?brand=smartschool.bd">smartschool.bd</a></li>
      </ul>
    </div>
    <div>
      <h4>Community</h4>
      <ul>
        <li><a data-wa-footer-link href="#" target="_blank" rel="noopener">WhatsApp community</a></li>
        <li><a data-wa-footer-support href="#" target="_blank" rel="noopener">WhatsApp support</a></li>
      </ul>
    </div>
    <div>
      <h4>Legal</h4>
      <ul>
        <li><a href="/privacy.php">Privacy policy</a></li>
        <li><a href="/terms.php">Terms of service</a></li>
      </ul>
    </div>
  </div>
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd — All rights reserved.</span>
    <span><a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
    <span class="footer__heart">
      <span class="footer__heart-ico" aria-hidden="true">&#10084;&#65039;</span>
      Built with love in Bangladesh
    </span>
  </div>
  <div class="container footer__status" aria-live="polite">
    <span class="footer__status-pill" data-platform-status>
      <span class="footer__status-dot" aria-hidden="true"></span>
      <span data-platform-status-text>All systems operational</span>
    </span>
  </div>
</footer>

<script>
  // SSR-rendered initial stats — passed to home.js so it can seed the prev
  // counters and only animate genuine changes on subsequent refreshes.
  window.__INITIAL_STATS__ = <?= json_encode($_initialStats) ?>;
</script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/home.js"></script>
</body>
</html>
