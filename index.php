<?php
/**
 * v5pro — Home page
 * Server-renders live stats for instant first paint.
 */
$_initialStats = ['users_registered' => 0, 'claims_total' => 0];
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
    ];
} catch (Throwable $_e) {}
if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} }
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>institution.bd — Free verified subdomains for Bangladeshi institutions</title>
  <meta name="description" content="Claim a free institution.bd or smartschool.bd subdomain for your school, college, university, madrasa or NGO.">
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link rel="manifest" href="/manifest.webmanifest">
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Custom v5pro -->
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>


<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg navbar-v5 sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/">
      <img src="/assets/img/logo.svg" alt="institution.bd">
      <span>
        <span>institution.bd</span>
        <span class="brand-sub">smartschool.bd</span>
      </span>
    </a>
    <div class="d-flex align-items-center gap-2 order-lg-3">
      <button class="btn-theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
        <i class="bi bi-moon-stars-fill"></i>
      </button>
      <span data-user-chip></span>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Menu">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse order-lg-2" id="mainNav">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="#brands">Brands</a></li>
        <li class="nav-item"><a class="nav-link" href="#how">How it works</a></li>
        <li class="nav-item"><a class="nav-link" href="/directory.php">Directory</a></li>
        <li class="nav-item" data-auth-only hidden><a class="nav-link" href="/dashboard.php"><i class="bi bi-speedometer2 me-1"></i> My Dashboard <span lang="bn" style="font-family: 'Noto Sans Bengali', 'Inter', sans-serif;">(আমার ড্যাশবোর্ড)</span></a></li>
        <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
      </ul>
    </div>
  </div>
</nav>


<!-- ===== HERO ===== -->
<header class="hero-v5" id="main">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <span class="hero-eyebrow">
          <span class="pulse-dot"></span>
          Free forever &middot; Instant DNS &middot; Cloudflare SSL
        </span>
        <h1>A free home on the web for<br><span class="text-gradient">every Bangladeshi institution.</span></h1>
        <p class="hero-sub mb-4">Claim a verified <strong>.institution.bd</strong> or <strong>.smartschool.bd</strong> subdomain in under a minute. Auto SSL via Cloudflare, Bengali ready, no documents required.</p>

        <form class="search-hero-v5 mb-3" data-slug-form aria-label="Check subdomain">
          <input type="text" required minlength="3" maxlength="40" autocomplete="off"
                 placeholder="Type your school / college name..." data-slug-input>
          <select data-brand-select aria-label="Brand">
            <option value="institution.bd">.institution.bd</option>
            <option value="smartschool.bd">.smartschool.bd</option>
          </select>
          <button type="submit" class="btn btn-primary" data-slug-claim>
            <i class="bi bi-search me-1"></i> Search
          </button>
        </form>

        <div class="search-result-v5" data-search-result hidden role="status" aria-live="polite">
          <div class="d-flex align-items-center gap-3">
            <span data-result-ico></span>
            <div class="flex-grow-1">
              <div class="fw-bold" data-result-title></div>
              <p class="text-muted small mb-0" data-result-sub></p>
            </div>
            <a class="btn btn-primary btn-sm" data-result-cta href="#" hidden>Claim Now</a>
          </div>
          <div class="mt-2 pt-2 border-top" data-result-alt hidden>
            <small class="text-muted">Also available:</small>
            <div class="d-flex align-items-center gap-2 mt-1">
              <code data-alt-name></code>
              <a class="btn btn-outline-primary btn-sm" data-alt-cta href="#">Claim</a>
            </div>
          </div>
        </div>

        <ul class="list-unstyled d-flex flex-wrap gap-3 mt-3 small text-muted">
          <li><i class="bi bi-check-circle-fill text-success me-1"></i> Live in &lt; 60 seconds</li>
          <li><i class="bi bi-check-circle-fill text-success me-1"></i> No documents required</li>
          <li><i class="bi bi-check-circle-fill text-success me-1"></i> Auto SSL via Cloudflare</li>
          <li><i class="bi bi-check-circle-fill text-success me-1"></i> Bengali + English ready</li>
        </ul>

        <p class="mt-3 small" data-guest-only>
          Already have a subdomain?
          <a href="#" data-open-auth>Sign in</a> &middot;
          <a href="#" data-open-auth data-mode="signup">Create account</a>
          &middot; Google &middot; Facebook &middot; GitHub &middot; Email
        </p>
      </div>
    </div>
  </div>
</header>


<!-- ===== LIVE STATS ===== -->
<section class="py-5" id="live-stats">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <span class="badge bg-primary-subtle text-primary fw-semibold mb-2">Realtime</span>
        <h2 class="mb-0">Platform activity right now</h2>
      </div>
      <span class="d-inline-flex align-items-center gap-2 small text-muted">
        <span class="pulse-dot" style="width:8px;height:8px;border-radius:50%;background:var(--c-primary);display:inline-block;"></span>
        Live &middot; auto-refresh every 30s
      </span>
    </div>
    <div class="row g-3" data-live-stats>
      <div class="col-6">
        <div class="stat-tile-v5">
          <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
          <span class="stat-num" data-tile="users"><?= number_format($_initialStats['users_registered']) ?></span>
          <span class="stat-label">Total Members (মোট সদস্য)</span>
          <span class="stat-delta" data-tile-delta="users"></span>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-tile-v5">
          <div class="stat-icon"><i class="bi bi-globe2"></i></div>
          <span class="stat-num" data-tile="claims_total"><?= number_format($_initialStats['claims_total']) ?></span>
          <span class="stat-label">Total Registered Domains (মোট নিবন্ধিত ডোমেইন)</span>
          <span class="stat-delta" data-tile-delta="claims_total"></span>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ===== BENGALI USE-CASE CARD ===== -->
<section class="py-5 bg-body-secondary" id="use-cases">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4">
          <h3 class="mb-4 text-center" style="font-family:'Noto Sans Bengali',sans-serif;font-weight:700;">কোন উদ্দেশ্যে আপনি একটি ডোমেইন পেতে পারেন?</h3>
          <div class="row g-3">
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-mortarboard-fill fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">স্কুল</span>
                <small class="d-block text-muted">School</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-building fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">কলেজ</span>
                <small class="d-block text-muted">College</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-book-fill fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">মাদ্রাসা</span>
                <small class="d-block text-muted">Madrasa</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-bank2 fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">বিশ্ববিদ্যালয়</span>
                <small class="d-block text-muted">University</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-pencil-square fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">কোচিং সেন্টার</span>
                <small class="d-block text-muted">Coaching Center</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-gear-fill fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">পলিটেকনিক</span>
                <small class="d-block text-muted">Polytechnic</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-person-workspace fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">প্রশিক্ষণ কেন্দ্র</span>
                <small class="d-block text-muted">Training Center</small>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="text-center p-3 rounded-3 bg-body-tertiary">
                <i class="bi bi-heart-fill fs-4 text-primary d-block mb-1"></i>
                <span style="font-family:'Noto Sans Bengali',sans-serif;font-weight:600;">এনজিও</span>
                <small class="d-block text-muted">NGO</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ===== BRANDS ===== -->
<section id="brands" class="py-5 bg-body-secondary">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-subtle text-primary fw-semibold mb-2">Two free brands</span>
      <h2>Pick the subdomain that fits</h2>
      <p class="text-muted">Whichever brand suits your institution, sign-up and claim are exactly the same.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6">
        <a class="brand-card-v5 d-block h-100" href="/claim.php?brand=institution.bd">
          <span class="badge bg-primary-subtle text-primary mb-2">Popular</span>
          <div class="brand-name mb-2">.institution.bd</div>
          <h5>Universities &middot; Colleges &middot; NGOs</h5>
          <p class="text-muted">Ideal for higher-ed institutions, polytechnics, training centres, foundations and non-profits across Bangladesh.</p>
          <span class="text-primary fw-semibold">Claim a .institution.bd subdomain <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <div class="col-md-6">
        <a class="brand-card-v5 brand-alt d-block h-100" href="/claim.php?brand=smartschool.bd">
          <span class="badge bg-info-subtle text-info mb-2">For schools</span>
          <div class="brand-name mb-2">.smartschool.bd</div>
          <h5>Schools &middot; Madrasas &middot; Coaching</h5>
          <p class="text-muted">Built for K-12 schools, kindergartens, madrasas and coaching centres &mdash; a clean, parent-friendly subdomain.</p>
          <span class="text-primary fw-semibold">Claim a .smartschool.bd subdomain <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
    </div>
  </div>
</section>


<!-- ===== HOW IT WORKS ===== -->
<section id="how" class="py-5 bg-body-secondary">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-subtle text-primary fw-semibold mb-2">How it works</span>
      <h2>From subdomain idea to live site in 4 steps</h2>
      <p class="text-muted">Most institutions are live within one working day.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3">
        <div class="step-card-v5 h-100">
          <span class="step-number">1</span>
          <h5>Sign in</h5>
          <p class="text-muted mb-0">Use email + password or one-click with Google, Facebook, GitHub.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="step-card-v5 h-100">
          <span class="step-number">2</span>
          <h5>Pick your subdomain</h5>
          <p class="text-muted mb-0">Live availability check. Choose institution.bd or smartschool.bd.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="step-card-v5 h-100">
          <span class="step-number">3</span>
          <h5>Verify (optional)</h5>
          <p class="text-muted mb-0">Upload EIIN certificate, board letter or NID for a verified badge.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="step-card-v5 h-100">
          <span class="step-number">4</span>
          <h5>Go live</h5>
          <p class="text-muted mb-0">Cloudflare creates your DNS record automatically. You're online!</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== DIRECTORY PREVIEW ===== -->
<section class="py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div>
        <span class="badge bg-primary-subtle text-primary fw-semibold mb-1">Realtime</span>
        <h2 class="mb-0" style="font-family:'Noto Sans Bengali',sans-serif;">সাম্প্রতিক নিবন্ধিত ডোমেইন</h2>
        <p class="text-muted mb-0 small">Recent Registered Domains</p>
      </div>
      <a href="/directory.php" class="btn btn-outline-primary btn-sm">View full directory <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3" data-featured-list>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
      <div class="col-md-6 col-lg-4"><div class="skeleton-v5" style="height:130px;"></div></div>
    </div>
  </div>
</section>


<!-- ===== SPONSORED BY ===== -->
<section class="py-5 bg-body-secondary" id="sponsors" data-sponsors-section hidden>
  <div class="container">
    <div class="text-center mb-4">
      <span class="badge bg-primary-subtle text-primary fw-semibold mb-2">Our Sponsors</span>
      <h2>Sponsored By</h2>
      <p class="text-muted">Grateful to our sponsors who help keep this platform free.</p>
    </div>
    <div class="row g-3 justify-content-center" data-sponsors-list></div>
  </div>
</section>


<!-- ===== WHATSAPP COMMUNITY ===== -->
<section class="py-4" id="community">
  <div class="container">
    <div class="wa-band-v5">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <div class="wa-band-icon"><img src="/assets/img/whatsapp.svg" alt=""></div>
          <h2 data-wa-title>Join our WhatsApp community</h2>
          <p data-wa-sub>Get announcements, support and meet other institution owners from across Bangladesh.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="#" class="btn btn-light btn-lg" data-wa-cta target="_blank" rel="noopener">
            <i class="bi bi-whatsapp me-2"></i> Join community
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="py-5 bg-body-secondary">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-subtle text-primary fw-semibold mb-2">FAQ</span>
      <h2>Frequently asked questions</h2>
      <p class="text-muted">Everything you need to know about our free verified subdomains.</p>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="accordion faq-v5" id="faqAccordion">
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">Is it really free?</button>
            </h3>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Yes &mdash; 100% free for verified Bangladeshi educational institutions and NGOs. No credit card, no setup fee, no yearly renewal.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Can my school use it as the main website?</button>
            </h3>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Absolutely. Once verified, point any web hosting to your subdomain via DNS &mdash; or use the built-in institution page.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Who can claim a subdomain?</button>
            </h3>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Any registered Bangladeshi school, college, university, madrasa, polytechnic, training institute or NGO.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">What DNS records do you support?</button>
            </h3>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">A, AAAA, CNAME, TXT, MX and NS records are supported. Manage them from your dashboard.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">What about email sign-in?</button>
            </h3>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">You can sign up with email + password. OAuth via Google, Facebook and GitHub is offered as a convenience.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">What if I violate the terms?</button>
            </h3>
            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body">Accounts found violating our <a href="/terms.php">terms</a> will be suspended with the DNS record removed.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ===== FINAL CTA ===== -->
<section class="py-5" id="cta">
  <div class="container">
    <div class="cta-band-v5">
      <span class="badge bg-white bg-opacity-25 text-white mb-2">Ready when you are</span>
      <h2>Give your institution a home on the web.</h2>
      <p>Free, verified, Cloudflare-backed. One-minute sign-up.</p>
      <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
        <a href="#" class="btn btn-light btn-lg" data-open-auth data-mode="signup" data-guest-only>
          <i class="bi bi-rocket-takeoff me-2"></i> Create your free account
        </a>
        <a href="/dashboard.php" class="btn btn-outline-light btn-lg" data-auth-only hidden>Go to my dashboard <i class="bi bi-arrow-right"></i></a>
        <a href="/directory.php" class="btn btn-outline-light btn-lg">Browse the directory</a>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer-v5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4 footer-brand">
        <a class="d-flex align-items-center gap-2 text-white text-decoration-none mb-3" href="/">
          <img src="/assets/img/logo.svg" alt="" width="32" height="32">
          <span class="fw-bold">institution.bd</span>
        </a>
        <p>Free verified subdomains for every Bangladeshi institution. Built for educators, NGOs and the open web.</p>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Platform</h6>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="/directory.php">Directory</a></li>
          <li class="mb-2"><a href="/claim.php">Claim</a></li>
          <li class="mb-2"><a href="/dashboard.php" data-auth-only hidden>Dashboard</a></li>
          <li class="mb-2"><a href="/admin.php" data-admin-only hidden>Admin</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Brands</h6>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="/directory.php?brand=institution.bd">institution.bd</a></li>
          <li class="mb-2"><a href="/directory.php?brand=smartschool.bd">smartschool.bd</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Community</h6>
        <ul class="list-unstyled">
          <li class="mb-2"><a data-wa-footer-link href="#" target="_blank">WhatsApp community</a></li>
          <li class="mb-2"><a data-wa-footer-support href="#" target="_blank">WhatsApp support</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Legal</h6>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="/privacy.php">Privacy policy</a></li>
          <li class="mb-2"><a href="/terms.php">Terms of service</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom d-flex justify-content-between flex-wrap gap-2">
      <span>&copy; <span data-year></span> institution.bd &mdash; All rights reserved.</span>
      <span><a href="/privacy.php">Privacy</a> &middot; <a href="/terms.php">Terms</a> &middot; Made with &#10084; in Bangladesh</span>
    </div>
  </div>
</footer>

<!-- Toast Container -->
<div class="toast-container-v5" id="toastHost"></div>

<!-- Scripts -->
<script>window.__INITIAL_STATS__ = <?= json_encode($_initialStats) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js" type="module"></script>
<script src="/assets/js/home.js" type="module"></script>
</body>
</html>
