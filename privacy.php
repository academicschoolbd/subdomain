<?php try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) { /* DB unavailable — page still renders, JS surfaces the error */ } if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Privacy Policy — institution.bd</title>
  <meta name="description" content="How institution.bd and smartschool.bd collect, use, store and protect your information." />
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

      <h2 class="mt-4" id="admin-rights">9. Admin rights, subdomain revocation &amp; reassignment</h2>
      <p>
        institution.bd / smartschool.bd subdomains are issued <strong>under licence, not as a sale</strong>. The Platform admin team retains full ownership of the <code>.institution.bd</code> and <code>.smartschool.bd</code> namespace and the right to revoke, suspend or reassign any subdomain at any time, with or without notice, under the circumstances below.
      </p>
      <p><strong>A subdomain may be revoked or reassigned when:</strong></p>
      <ul>
        <li>The owner <strong>fails to provide the required verification documents</strong> (EIIN certificate, board letter, trade licence, admin NID, etc.) within the time window we ask for, when admin moderation is set to "documents required".</li>
        <li>The information supplied during the claim turns out to be false, misleading or impersonates another institution.</li>
        <li>A legitimate Bangladeshi institution (verified school, college, university, madrasa, NGO, board or government body) <strong>contacts us with a claim of legal right</strong> to that name (e.g. trademark, EIIN registration, charity registration) and the current holder is unable to demonstrate equal or stronger right after a fair review window.</li>
        <li>The subdomain is used for spam, phishing, fraud, hate speech, sexually explicit content, malware distribution, copyright infringement, or any activity that violates the <a href="/terms.php">Terms of Service</a> or applicable Bangladeshi law.</li>
        <li>The owner has not signed in for an extended period (typically 12 months) and the subdomain is dormant.</li>
        <li>The renewal window has passed without action when domain renewals are configured (see <a href="/terms.php">Terms</a>).</li>
      </ul>
      <p>
        Where a subdomain is reassigned to a different rightful holder, all institution-page content, notices and uploaded documents tied to the previous holder are permanently deleted before the new owner is given access. The previous holder is notified by email when contact information is on file.
      </p>
      <p>
        Decisions to revoke or reassign are made by the Platform admin team after a written review. The admin team's decision is final but you may appeal in writing to <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> within 14 days, and we will reconsider in good faith.
      </p>

      <h2 class="mt-4" id="changes">10. Changes to this policy</h2>
      <p>
        We may update this policy from time to time. When we do, we'll change the "last updated" date at the top of this page. Material changes will be announced on the homepage and via the WhatsApp community.
      </p>

      <h2 class="mt-4" id="contact">11. Contact</h2>
      <p>
        Questions, complaints or data-subject requests? Write to <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> or tap the floating WhatsApp button at the bottom-right of any page.
      </p>
    </article>

    <!-- ============================ BANGLA SECTION ============================ -->
    <article class="card mt-3" lang="bn" style="font-family:'Noto Sans Bengali','Raleway',sans-serif;">
      <span class="eyebrow" style="display:inline-block;padding:4px 12px;border-radius:999px;background:var(--c-primary-50);color:var(--c-primary-700);border:1px solid var(--c-primary-100);font-size:.78rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;margin-bottom:14px;">বাংলা সংস্করণ</span>
      <h2 style="margin-top:8px;">গোপনীয়তা নীতি ও প্ল্যাটফর্ম পরিচালনার নিয়মাবলী</h2>
      <p>
        <strong>institution.bd</strong> এবং <strong>smartschool.bd</strong> ("প্ল্যাটফর্ম") বাংলাদেশের স্কুল, কলেজ, বিশ্ববিদ্যালয়, মাদরাসা, পলিটেকনিক, প্রশিক্ষণকেন্দ্র ও এনজিও-র জন্য একটি বিনামূল্যে যাচাইকৃত সাবডোমেইন সেবা। নিচের নিয়মাবলী আপনার তথ্য সংগ্রহ, ব্যবহার ও সাবডোমেইন বরাদ্দ–প্রত্যাহার সংক্রান্ত আমাদের নীতি ব্যাখ্যা করে। এই প্ল্যাটফর্ম ব্যবহার করার মাধ্যমে আপনি এই শর্তসমূহে সম্মতি প্রদান করছেন।
      </p>

      <h3 class="mt-3">১. আমরা যে তথ্য সংগ্রহ করি</h3>
      <ul>
        <li><strong>সাইন-ইন তথ্য —</strong> Google, Facebook বা GitHub দিয়ে লগইন করলে আমরা আপনার নাম, ইমেইল ও প্রোফাইল ছবির URL পাই। আপনার পাসওয়ার্ড আমরা কখনো দেখি না বা সংরক্ষণ করি না।</li>
        <li><strong>প্রোফাইল তথ্য (দাবি করার পূর্বে আবশ্যক) —</strong> পূর্ণ নাম, সঠিক মোবাইল নম্বর, পদবি, প্রতিষ্ঠানের নাম, বিভাগ, জেলা এবং উপজেলা।</li>
        <li><strong>দাবি (claim) সংক্রান্ত তথ্য —</strong> সাবডোমেইন, ব্র্যান্ড, প্রতিষ্ঠানের ধরন, EIIN / বোর্ড / নিবন্ধন নম্বর, ঠিকানা এবং প্রয়োজনীয় যাচাইয়ের ডকুমেন্ট (EIIN সনদ, বোর্ড লেটার, NID, ট্রেড লাইসেন্স ইত্যাদি)।</li>
        <li><strong>সার্ভার লগ —</strong> নিরাপত্তার জন্য IP ঠিকানা, ব্রাউজার তথ্য এবং সময়।</li>
      </ul>

      <h3 class="mt-3">২. তথ্যের ব্যবহার</h3>
      <ul>
        <li>আপনার প্রতিষ্ঠানের অস্তিত্ব ও আপনার অনুমোদন যাচাই করতে।</li>
        <li>প্রতিষ্ঠানের পাবলিক ডিরেক্টরি পেজ তৈরি ও প্রদর্শন করতে।</li>
        <li>দাবির অবস্থা, গুরুত্বপূর্ণ ঘোষণা ও মডারেশন টিমের প্রশ্ন সম্পর্কে আপনাকে অবহিত রাখতে।</li>
        <li>স্প্যাম, প্রতারণা ও ছদ্মবেশী ব্যবহার রোধ করতে এবং আইনগত বাধ্যবাধকতা পূরণ করতে।</li>
      </ul>

      <h3 class="mt-3">৩. আমরা কী প্রকাশ করি, কী গোপন রাখি</h3>
      <p>
        আপনার দাবি অনুমোদিত হলে <strong>প্রতিষ্ঠানের নাম, সাবডোমেইন, বিভাগ/জেলা/উপজেলা, ধরন, লোগো, ব্যানার এবং প্রকাশিত নোটিশ</strong> পাবলিকলি দৃশ্যমান হবে। কিন্তু আপনার <strong>মোবাইল নম্বর, ইমেইল, OAuth আইডি, আপলোডকৃত যাচাইপত্র (EIIN সনদ, NID, ট্রেড লাইসেন্স ইত্যাদি)</strong> শুধুমাত্র প্ল্যাটফর্ম অ্যাডমিনদের কাছে দৃশ্যমান থাকবে — কখনো পাবলিক হবে না।
      </p>

      <h3 class="mt-3">৪. সাবডোমেইন বরাদ্দ, প্রত্যাহার ও পুনরায় বরাদ্দ — অ্যাডমিনের অধিকার</h3>
      <p>
        <strong>institution.bd</strong> ও <strong>smartschool.bd</strong> নেমস্পেসের সম্পূর্ণ মালিকানা ও নিয়ন্ত্রণ প্ল্যাটফর্মের অ্যাডমিন টিমের কাছে সংরক্ষিত। সাবডোমেইন আপনাকে <strong>লাইসেন্স হিসেবে</strong> দেওয়া হয় — সম্পূর্ণ মালিকানা হস্তান্তর করা হয় না। অ্যাডমিন টিম যেকোনো সময়, পূর্বনোটিশ সহ বা ব্যতীত, নিম্নলিখিত পরিস্থিতিতে আপনার সাবডোমেইন <strong>প্রত্যাহার (revoke), স্থগিত (suspend) বা অন্য বৈধ প্রতিষ্ঠানের নামে পুনরায় বরাদ্দ (reassign)</strong> করার পূর্ণ অধিকার সংরক্ষণ করে:
      </p>
      <ul>
        <li>
          যখন <strong>"ডকুমেন্ট আবশ্যক"</strong> মোড সক্রিয় থাকে এবং আপনি নির্ধারিত সময়ের মধ্যে প্রয়োজনীয় যাচাইপত্র (EIIN সনদ, বোর্ড লেটার, ট্রেড লাইসেন্স, অ্যাডমিন NID ইত্যাদি) <strong>আপলোড করতে ব্যর্থ হন</strong>।
        </li>
        <li>
          দাবি করার সময় আপনার দেওয়া তথ্য <strong>মিথ্যা, বিভ্রান্তিকর</strong>, কিংবা অন্য প্রতিষ্ঠানের ছদ্মবেশ হিসেবে প্রতীয়মান হয়।
        </li>
        <li>
          কোনো <strong>বৈধ বাংলাদেশি প্রতিষ্ঠান</strong> (যাচাইকৃত স্কুল, কলেজ, বিশ্ববিদ্যালয়, মাদরাসা, এনজিও, শিক্ষা বোর্ড বা সরকারি কর্তৃপক্ষ) <strong>ঐ নামের উপর তাদের আইনি অধিকার</strong> (যেমন: ট্রেডমার্ক, EIIN নিবন্ধন, চ্যারিটি নিবন্ধন) সম্পর্কে আমাদের অবহিত করেন এবং নিরপেক্ষ পর্যালোচনার পর বর্তমান ধারক সমান বা শক্তিশালী অধিকার প্রমাণ করতে ব্যর্থ হন।
        </li>
        <li>
          সাবডোমেইনটি স্প্যাম, ফিশিং, প্রতারণা, ঘৃণাত্মক বক্তব্য, অশ্লীল কন্টেন্ট, ম্যালওয়্যার, কপিরাইট লঙ্ঘন বা <a href="/terms.php">টার্মস অব সার্ভিস</a> বা প্রযোজ্য বাংলাদেশি আইনের যেকোনো লঙ্ঘনে ব্যবহৃত হলে।
        </li>
        <li>
          ডোমেইনের মালিক দীর্ঘ সময় (সাধারণত ১২ মাস) সাইন-ইন না করলে এবং সাবডোমেইনটি অব্যবহৃত থাকলে।
        </li>
        <li>
          নবায়ন (renewal) চালু থাকা অবস্থায় নবায়নের সময়সীমা পার হয়ে গেলেও কোনো পদক্ষেপ না নেওয়া হলে।
        </li>
      </ul>
      <p>
        কোনো সাবডোমেইন অন্য বৈধ প্রতিষ্ঠানের কাছে পুনরায় বরাদ্দ হলে, পূর্ববর্তী ধারকের সমস্ত পেজ কন্টেন্ট, নোটিশ ও আপলোডকৃত ডকুমেন্ট স্থায়ীভাবে মুছে ফেলা হয়। যেখানে যোগাযোগের তথ্য সংরক্ষিত রয়েছে, সেখানে পূর্ববর্তী ধারককে ইমেইলের মাধ্যমে অবহিত করা হবে।
      </p>
      <p>
        প্রত্যাহার বা পুনরায় বরাদ্দের সিদ্ধান্ত অ্যাডমিন টিম লিখিত পর্যালোচনার পর গ্রহণ করে। এই সিদ্ধান্ত চূড়ান্ত হলেও আপনি ১৪ দিনের মধ্যে <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> ঠিকানায় লিখিতভাবে আপিল করতে পারবেন এবং আমরা সদিচ্ছায় তা পুনঃবিবেচনা করব।
      </p>

      <h3 class="mt-3">৫. আপনার অধিকারসমূহ</h3>
      <ul>
        <li>আপনার সম্পর্কে সংরক্ষিত তথ্য <strong>দেখার</strong> অধিকার (ড্যাশবোর্ড থেকে বা ইমেইল অনুরোধে)।</li>
        <li>ভুল তথ্য <strong>সংশোধনের</strong> অধিকার।</li>
        <li>আপনার অ্যাকাউন্ট ও সমস্ত ব্যক্তিগত তথ্য <strong>মুছে ফেলার</strong> অধিকার।</li>
        <li>WhatsApp কমিউনিটি বা অ-অপরিহার্য প্রক্রিয়াকরণ থেকে <strong>সম্মতি প্রত্যাহারের</strong> অধিকার।</li>
      </ul>
      <p>
        উপরের যেকোনো অধিকার প্রয়োগ করতে চাইলে <a data-contact-email href="mailto:admin@institution.bd">admin@institution.bd</a> ঠিকানায় লিখুন কিংবা পেজের ডান-নিচের WhatsApp বাটনে যোগাযোগ করুন।
      </p>

      <h3 class="mt-3">৬. দায়িত্ব অস্বীকার</h3>
      <p>
        আমরা সর্বোচ্চ যত্নের সাথে প্ল্যাটফর্মটি পরিচালনা করি, তবে কোনো সিস্টেম ১০০% নিরাপদ নয়। শক্তিশালী পাসওয়ার্ড ব্যবহার করুন এবং অননুমোদিত প্রবেশের সন্দেহ হলে সাথে সাথে আমাদের জানান।
      </p>
    </article>
    <!-- ============================ /BANGLA SECTION ============================ -->

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
