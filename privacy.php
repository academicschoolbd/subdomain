<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#0f766e" />
  <title>Privacy Policy — institution.bd</title>
  <meta name="description" content="How institution.bd and smartschool.bd collect, use, store and protect your information." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
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
      <a href="/privacy.php" aria-current="page">Privacy</a>
      <a href="/terms.php">Terms</a>
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
    <h1 style="margin-bottom:8px;">Privacy Policy</h1>
    <p class="text-muted" data-effective-date>Effective date: <strong>1 January 2026</strong>. Last updated: <strong>1 January 2026</strong>.</p>

    <article class="card mt-3">
      <p>
        institution.bd and smartschool.bd ("the Platform", "we", "our", "us") are a free, verified-subdomain service for Bangladeshi schools, colleges, universities, madrasas, polytechnics, training institutes and NGOs. This Privacy Policy explains what information we collect, why we collect it, how we use it, who we share it with, and the rights you have over it.
      </p>
      <p>
        By using the Platform you agree to this Policy. If you don't agree, please don't use the Platform. For any questions, write to <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> or reach us on WhatsApp via the floating button at the bottom-right of every page.
      </p>

      <h2 class="mt-4" id="data-we-collect">1. Information we collect</h2>
      <h3>1.1 Information you give us</h3>
      <ul>
        <li><strong>OAuth profile</strong> — when you sign in with Google, Facebook or GitHub we receive your <em>name</em>, <em>email address</em>, <em>profile picture URL</em>, and the <em>provider's user ID</em>. We never see or store your password.</li>
        <li><strong>Profile details (required before claiming)</strong> — Full Name, Correct Mobile Number, পদবি (Designation), প্রতিষ্ঠানের নাম (Institution Name), বিভাগ (Division), জেলা (District), উপজেলা (Upazila).</li>
        <li><strong>Claim details</strong> — chosen subdomain, brand (institution.bd or smartschool.bd), institution type, EIIN / board / registration number, address, and any verification documents you upload (e.g. EIIN certificate, board letter, NID, trade license).</li>
        <li><strong>Content you publish</strong> — logo, banner, "about" text, notices, announcements and any other content you choose to post on your institution page.</li>
      </ul>

      <h3>1.2 Information collected automatically</h3>
      <ul>
        <li>Basic web-server logs (IP address, user-agent, request URL, timestamp) kept for security and abuse-prevention.</li>
        <li>A single login token (JWT) stored in your browser's <code>localStorage</code> so you stay signed in. We do <em>not</em> set advertising cookies or third-party trackers.</li>
      </ul>

      <h2 class="mt-4" id="how-we-use">2. How we use your information</h2>
      <ul>
        <li>To verify that your institution actually exists and that you are authorised to claim its subdomain.</li>
        <li>To create and display your institution's directory entry and public page.</li>
        <li>To contact you (by email, mobile or WhatsApp) about your claim status, important notices, or questions from our moderation team.</li>
        <li>To prevent abuse, spam, and impersonation, and to comply with applicable law.</li>
        <li>To improve the Platform — aggregate counts of users registered, claims approved / pending / rejected are shown publicly on the homepage, but they never identify individuals.</li>
      </ul>

      <h2 class="mt-4" id="who-we-share">3. Who we share your information with</h2>
      <p>
        We do <strong>not</strong> sell your data. We share it only with the following service providers, and only as needed to operate the Platform:
      </p>
      <ul>
        <li><strong>OAuth providers</strong> (Google, Facebook, GitHub) — they receive nothing more than the standard OAuth handshake from your sign-in.</li>
        <li><strong>Cloudflare</strong> — when an admin approves your claim, we create a DNS record on Cloudflare so that <code>your-slug.institution.bd</code> points at our server. Cloudflare also provides SSL and CDN for the directory.</li>
        <li><strong>Hosting provider</strong> — for storing your data on the server you actually visit.</li>
        <li><strong>WhatsApp</strong> — only if <em>you</em> tap the floating support button or join the community link. We never push-message you without you initiating the conversation.</li>
        <li><strong>Law-enforcement</strong> — when we are legally required to disclose information by a valid order from a Bangladeshi authority.</li>
      </ul>

      <h2 class="mt-4" id="public-info">4. What is public, what is private</h2>
      <p>
        Once your claim is approved, the following becomes <strong>public</strong> on your institution's directory page: your institution name, subdomain, division / district / upazila, type, logo / banner, and any notices you publish.
      </p>
      <p>
        The following stays <strong>private</strong> and is visible only to platform admins: your mobile number, email address, OAuth identifiers, uploaded verification documents (EIIN certificate, NID, trade license), and any other identity proofs you submit during the claim flow.
      </p>

      <h2 class="mt-4" id="retention">5. How long we keep your data</h2>
      <ul>
        <li>Account data (OAuth identity + profile details) — for as long as your account exists.</li>
        <li>Verification documents — for the lifetime of the claim, so we can re-verify on dispute, then deleted on request.</li>
        <li>Web-server logs — automatically rotated every 30 days.</li>
        <li>If you delete your account, we delete all personal data within 30 days, except the bare minimum needed to comply with anti-abuse and legal obligations.</li>
      </ul>

      <h2 class="mt-4" id="your-rights">6. Your rights</h2>
      <p>You have the right to:</p>
      <ul>
        <li><strong>See</strong> the information we hold about you (from your dashboard, or by email request).</li>
        <li><strong>Correct</strong> any inaccurate details (directly editable on the profile page).</li>
        <li><strong>Delete</strong> your account and all associated personal data.</li>
        <li><strong>Withdraw</strong> consent for non-essential processing (e.g. opting out of the WhatsApp community).</li>
      </ul>
      <p>To exercise any of these rights, email <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a>.</p>

      <h2 class="mt-4" id="security">7. Security</h2>
      <p>
        We use HTTPS everywhere (via Cloudflare), bcrypt / signed JWTs for authentication, and strict file-type / size checks on uploaded documents. No system is 100% secure — please use a strong password on your OAuth provider and never share your login token. If you suspect your account has been compromised, sign out everywhere from your provider's dashboard and contact us.
      </p>

      <h2 class="mt-4" id="children">8. Children</h2>
      <p>
        The Platform is meant to be operated by adult administrators of an institution. We do not knowingly collect personal data from children under 13. If you believe a minor has signed up, please contact us and we will remove the account.
      </p>

      <h2 class="mt-4" id="changes">9. Changes to this policy</h2>
      <p>
        We may update this policy from time to time. When we do, we'll change the "last updated" date at the top of this page. Material changes will be announced on the homepage and via the WhatsApp community.
      </p>

      <h2 class="mt-4" id="contact">10. Contact</h2>
      <p>
        Questions, complaints or data-subject requests? Write to <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> or tap the floating WhatsApp button at the bottom-right of any page.
      </p>
    </article>

    <p class="text-muted mt-3" style="font-size:.85rem;">
      See also: <a href="/terms.php">Terms of service</a> · <a href="/">Home</a>
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
    // Footer year
    var y = document.querySelector('[data-year]'); if (y) y.textContent = new Date().getFullYear();
    // Surface the admin contact email from /api/settings (if exposed)
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
