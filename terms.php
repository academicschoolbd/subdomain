<?php try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) { /* DB unavailable — page still renders, JS surfaces the error */ } if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Terms of Service — institution.bd</title>
  <meta name="description" content="The terms that govern your use of institution.bd and smartschool.bd free verified subdomains." />
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
      <a href="/">Home</a>
      <a href="/directory.php">Directory</a>
      <a href="/claim.php">Claim a subdomain</a>
      <a href="/privacy.php">Privacy</a>
      <a href="/terms.php" aria-current="page">Terms</a>
    </div>
    <div class="nav__cta">
      <span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile>
    <a href="/">Home</a>
    <a href="/directory.php">Directory</a>
    <a href="/claim.php">Claim a subdomain</a>
    <a href="/privacy.php">Privacy</a>
    <a href="/terms.php">Terms</a>
  </div>
</nav>

<section style="padding-top:48px;">
  <div class="container" style="max-width:820px;">
    <span class="eyebrow" style="display:inline-block;padding:4px 12px;border-radius:999px;background:var(--c-primary-50);color:var(--c-primary-700);border:1px solid var(--c-primary-100);font-size:.78rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;margin-bottom:14px;">Legal</span>
    <h1 style="margin-bottom:8px;">Terms of Service</h1>
    <p class="text-muted" data-effective-date>Effective date: <strong>1 January 2026</strong>. Last updated: <strong>1 January 2026</strong>.</p>

    <article class="card mt-3">
      <p>
        These Terms ("Terms") govern your use of the institution.bd and smartschool.bd platforms (together, "the Platform"), operated as a free public utility for verified Bangladeshi institutions. By creating an account, claiming a subdomain, or using any feature of the Platform, you agree to be bound by these Terms.
      </p>

      <h2 class="mt-4" id="service">1. What the Platform provides</h2>
      <p>
        The Platform offers — at no cost — a verified <code>your-slug.institution.bd</code> or <code>your-slug.smartschool.bd</code> subdomain, an institution profile page (logo, banner, about, notices), and listing in the public directory. Admin moderation, EIIN / board verification and Cloudflare-backed DNS / SSL are included.
      </p>

      <h2 class="mt-4" id="eligibility">2. Eligibility</h2>
      <p>You may claim a subdomain only if all of the following are true:</p>
      <ul>
        <li>You represent a real Bangladeshi <strong>school, college, university, madrasa, polytechnic, training institute, or registered NGO</strong>.</li>
        <li>You are authorised by that institution (head teacher, principal, IT lead, director, or equivalent) to act on its behalf.</li>
        <li>You are at least 18 years old.</li>
        <li>You can provide one valid verification document (EIIN certificate, board letter, NID of the head of institution, trade licence, or equivalent).</li>
      </ul>

      <h2 class="mt-4" id="account">3. Your account</h2>
      <ul>
        <li>You sign in with Google, Facebook or GitHub. You're responsible for keeping that account secure.</li>
        <li>You must keep your profile (Full Name, Mobile, পদবি, প্রতিষ্ঠানের নাম, বিভাগ, জেলা, উপজেলা) accurate and up to date.</li>
        <li>You may not impersonate another person or institution or submit forged documents. Doing so is grounds for permanent ban and may be reported to the authorities.</li>
        <li>One person should not claim subdomains for institutions they do not represent.</li>
      </ul>

      <h2 class="mt-4" id="claim">4. The claim &amp; verification process</h2>
      <ol>
        <li>You pick an available subdomain and brand (institution.bd or smartschool.bd).</li>
        <li>You complete your profile.</li>
        <li>You submit institution details and at least one verification document.</li>
        <li>An admin reviews your claim, typically within 1–3 working days.</li>
        <li>On approval, Cloudflare automatically creates a DNS record and you can use your subdomain.</li>
      </ol>
      <p>
        We may request additional information at any time. We may reject, suspend, or revoke a claim if the institution does not exist, the documents are forged or mismatched, the content violates these Terms, or we are required to do so by law.
      </p>

      <h2 class="mt-4" id="acceptable-use">5. Acceptable use</h2>
      <p>You agree <strong>not</strong> to use the Platform to:</p>
      <ul>
        <li>Post or distribute illegal, hateful, defamatory, obscene, or politically inciting content.</li>
        <li>Run phishing, malware, fraudulent payment, or impersonation schemes.</li>
        <li>Spam directory visitors or harvest contact information for unsolicited marketing.</li>
        <li>Resell, sublease, or transfer the subdomain to a third party for money.</li>
        <li>Attempt to disrupt the Platform (DDoS, exploit attempts, automated abuse).</li>
        <li>Violate any law of the People's Republic of Bangladesh.</li>
      </ul>
      <p>We may suspend or terminate your account immediately and without warning for any violation.</p>

      <h2 class="mt-4" id="content">6. Your content</h2>
      <p>
        You retain all rights in the content you post (logo, banner, text, notices). By posting it, you grant us a non-exclusive, royalty-free, worldwide licence to host, cache, display, distribute, and back it up solely for the purpose of running the Platform. You confirm that you have the right to grant this licence (i.e. you own the content or have permission to use it).
      </p>

      <h2 class="mt-4" id="termination">7. Termination</h2>
      <p>
        You may delete your account and release your subdomain at any time from your dashboard. We may suspend or terminate your account if you breach these Terms, if your institution ceases to exist, or if we are required to do so by law. On termination, the subdomain is reclaimed and may be re-issued after a cooling-off period.
      </p>

      <h2 class="mt-4" id="free">8. The service is free — no warranty</h2>
      <p>
        The Platform is offered at <strong>zero cost</strong>, "as is" and "as available", without warranty of any kind. We make no promise that the service will be uninterrupted, error-free, or fit for any particular purpose. Use it at your own risk.
      </p>

      <h2 class="mt-4" id="liability">9. Limitation of liability</h2>
      <p>
        To the maximum extent permitted by law, the Platform, its operators, contributors and service providers will not be liable for any indirect, incidental, consequential, or punitive damages, or for any loss of data, revenue or goodwill arising out of your use of (or inability to use) the Platform. Where liability cannot be excluded by law, our total aggregate liability to you is limited to BDT 0 (zero), reflecting the fact that the service is free.
      </p>

      <h2 class="mt-4" id="ip">10. Trademarks &amp; intellectual property</h2>
      <p>
        "institution.bd" and "smartschool.bd" are operated by us. Cloudflare, Google, Facebook, GitHub and WhatsApp are trademarks of their respective owners; their inclusion does not imply endorsement.
      </p>

      <h2 class="mt-4" id="law">11. Governing law</h2>
      <p>
        These Terms are governed by the laws of the People's Republic of Bangladesh. Any dispute arising out of or in connection with the Platform is subject to the exclusive jurisdiction of the courts of Dhaka, Bangladesh.
      </p>

      <h2 class="mt-4" id="changes">12. Changes to these Terms</h2>
      <p>
        We may revise these Terms from time to time. The "last updated" date at the top of this page will always reflect the latest revision. Continued use of the Platform after a change means you accept the revised Terms.
      </p>

      <h2 class="mt-4" id="contact">13. Contact</h2>
      <p>
        Write to <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> or tap the floating WhatsApp button.
      </p>
    </article>

    <p class="text-muted mt-3" style="font-size:.85rem;">
      See also: <a href="/privacy.php">Privacy policy</a> · <a href="/">Home</a>
    </p>
  </div>
</section>

<!-- ============================ FOOTER ============================ -->
<footer class="footer">
  <div class="container footer__bottom">
    <span>© <span data-year></span> institution.bd</span>
    <span><a href="/">Home</a> · <a href="/directory.php">Directory</a> · <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a></span>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script>
  (function(){
    var y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();
    if (window.App && App.getSettings) {
      App.getSettings().then(function(s){
        var em = (s && s.brand && s.brand.admin_email) || null;
        if (!em) return;
        document.querySelectorAll('[data-contact-email]').forEach(function(a){
          a.href = 'mailto:' + em; a.textContent = em;
        });
      }).catch(function(){ /* ignore */ });
    }
  })();
</script>
</body>
</html>
