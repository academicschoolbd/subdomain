<?php try { require_once __DIR__ . '/api/bootstrap.php'; } catch (Throwable $_e) { /* DB unavailable — page still renders, JS surfaces the error */ } if (!function_exists('theme_emit_head_style')) { function theme_emit_head_style($c=null){} } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Claim your subdomain — institution.bd</title>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/style.css" />
  <?php theme_emit_head_style($CONFIG ?? null); ?>
</head>
<body>

<nav class="nav">
  <div class="container nav__inner">
    <a class="nav__brand" href="/"><img src="/assets/img/logo.svg" alt="" /><span class="nav__brand-text"><span>institution.bd</span><small>smartschool.bd</small></span></a>
    <div class="nav__links"><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a></div>
    <div class="nav__cta"><span data-user-chip></span>
      <button class="nav__toggle" data-nav-toggle aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      </button>
    </div>
  </div>
  <div class="container nav__mobile" data-nav-mobile><a href="/">Home</a><a href="/directory.php">Directory</a><a href="/claim.php">Claim</a></div>
</nav>

<section style="padding-top:36px;">
  <div class="container">
    <div class="section__head" style="text-align:left;max-width:none;margin-bottom:18px;">
      <span class="eyebrow">Step <span data-step-current>1</span> of <span data-step-total>4</span></span>
      <h2 class="mb-0">Claim your free subdomain</h2>
      <p class="text-muted">No payment, no phone OTP. Sign in once with Google / Facebook / GitHub.</p>
    </div>

    <!-- progress -->
    <div class="claim-progress" data-progress>
      <span class="active">1. Choose subdomain</span>
      <span>2. Your profile</span>
      <span>3. Institution details</span>
      <span>4. Verification</span>
    </div>

    <!-- step 1: pick subdomain -->
    <section class="card mt-3" data-step="1">
      <h3 class="mb-1">Pick your subdomain</h3>
      <p class="text-muted">3–40 characters, lowercase letters / digits / hyphens.</p>
      <form data-step1-form>
        <label class="label">Brand</label>
        <div class="seg" role="group">
          <button type="button" class="seg__btn active" data-brand="institution.bd">institution.bd</button>
          <button type="button" class="seg__btn" data-brand="smartschool.bd">smartschool.bd</button>
        </div>
        <label class="label mt-3">Subdomain</label>
        <div class="slug-row">
          <input type="text" required minlength="3" maxlength="40" autocomplete="off" placeholder="yourschoolname" data-slug />
          <span class="suffix" data-suffix>.institution.bd</span>
        </div>
        <p class="slug-status" data-slug-status></p>
        <div class="text-right">
          <button class="btn btn--primary" type="submit" data-next>Continue →</button>
        </div>
      </form>
    </section>

    <!-- step "profile": user profile gate (required before claim submission) -->
    <section class="card mt-3" data-step="profile" hidden>
      <h3 class="mb-1">Tell us about you</h3>
      <p class="text-muted">
        Before claiming a subdomain we need a few details about you and your institution.
        We use this to keep the directory authentic and to contact you about your claim.
      </p>
      <form data-profile-form class="form-grid">
        <div class="field"><label class="label" for="pf-name">Full name *</label>
          <input id="pf-name" type="text" name="name" required maxlength="120" autocomplete="name" /></div>
        <div class="field"><label class="label" for="pf-mobile">Correct mobile number *</label>
          <input id="pf-mobile" type="tel" name="mobile" required maxlength="20"
                 inputmode="tel" autocomplete="tel" placeholder="01712345678" />
          <span class="hint">Bangladesh mobile (e.g. 01712345678).</span></div>
        <div class="field"><label class="label" for="pf-designation">পদবি / Designation *</label>
          <input id="pf-designation" type="text" name="designation_bn" required maxlength="120"
                 placeholder="অধ্যক্ষ, প্রধান শিক্ষক, পরিচালক, আইটি শিক্ষক …"
                 lang="bn" /></div>
        <div class="field"><label class="label" for="pf-inst">প্রতিষ্ঠানের নাম / Institution name *</label>
          <input id="pf-inst" type="text" name="institution_name" required maxlength="200"
                 placeholder="আপনার প্রতিষ্ঠানের পূর্ণ নাম" /></div>
        <div class="field"><label class="label" for="pf-division">বিভাগ / Division *</label>
          <select id="pf-division" name="division" required>
            <option value="">— Select division —</option>
          </select></div>
        <div class="field"><label class="label" for="pf-district">জেলা / District *</label>
          <select id="pf-district" name="district" required>
            <option value="">— Select division first —</option>
          </select></div>
        <div class="field"><label class="label" for="pf-upazila">উপজেলা / Upazila / Thana *</label>
          <select id="pf-upazila" name="upazila" required>
            <option value="">— Select district first —</option>
          </select></div>
        <div class="field field--wide flex-between">
          <button class="btn" type="button" data-back="1">← Back</button>
          <button class="btn btn--primary" type="submit" data-profile-submit>Save profile &amp; continue →</button>
        </div>
      </form>
    </section>

    <!-- step 2: details -->
    <section class="card mt-3" data-step="2" hidden>
      <h3 class="mb-1">Institution details</h3>
      <p class="text-muted">You can edit any of these later from your dashboard.</p>
      <form data-step2-form class="form-grid">
        <div class="field"><label class="label">Name (English) *</label>
          <input type="text" name="name_en" required maxlength="160" /></div>
        <div class="field"><label class="label">নাম (বাংলা)</label>
          <input type="text" name="name_bn" maxlength="160" /></div>
        <div class="field"><label class="label">Category *</label>
          <select name="category" required>
            <option value="">— Select —</option>
            <option value="school">School</option>
            <option value="college">College</option>
            <option value="university">University</option>
            <option value="madrasa">Madrasa</option>
            <option value="kindergarten">Kindergarten</option>
            <option value="polytechnic">Polytechnic</option>
            <option value="coaching">Coaching center</option>
            <option value="training">Training institute</option>
            <option value="ngo">NGO / Foundation</option>
            <option value="other">Other</option>
          </select></div>
        <div class="field"><label class="label">EIIN (if applicable)</label>
          <input type="text" name="eiin" maxlength="20" inputmode="numeric" /></div>
        <div class="field"><label class="label">Division</label>
          <select name="division" data-bd-division>
            <option value="">—</option>
          </select></div>
        <div class="field"><label class="label">District</label>
          <select name="district" data-bd-district>
            <option value="">— Select division first —</option>
          </select></div>
        <div class="field"><label class="label">Upazila / Thana</label>
          <select name="upazila" data-bd-upazila>
            <option value="">— Select district first —</option>
          </select></div>
        <div class="field field--wide"><label class="label">Address</label>
          <input type="text" name="address" /></div>
        <div class="field"><label class="label">Contact name</label><input type="text" name="contact_name" /></div>
        <div class="field"><label class="label">Contact phone</label><input type="tel" name="contact_phone" /></div>
        <div class="field"><label class="label">Contact email</label><input type="email" name="contact_email" /></div>
        <div class="field"><label class="label">Website</label><input type="url" name="website" placeholder="https://…" /></div>
        <div class="field field--wide"><label class="label">About (English)</label>
          <textarea name="about_en" rows="3" maxlength="500"></textarea></div>
        <div class="field field--wide"><label class="label">পরিচিতি (বাংলা)</label>
          <textarea name="about_bn" rows="3" maxlength="500"></textarea></div>
        <div class="field field--wide flex-between">
          <button class="btn" type="button" data-back="1">← Back</button>
          <button class="btn btn--primary" type="submit">Save &amp; continue →</button>
        </div>
      </form>
    </section>

    <!-- step 3: documents -->
    <section class="card mt-3" data-step="3" hidden>
      <h3 class="mb-1">Verification document</h3>
      <p class="text-muted">Upload at least one of: EIIN certificate, board letter, trade license, or admin NID. PDF/JPG/PNG up to 8&nbsp;MB.</p>
      <form data-step3-form>
        <div class="field">
          <label class="label">Document type</label>
          <select name="doc_type" required>
            <option value="eiin_certificate">EIIN certificate</option>
            <option value="board_letter">Board letter</option>
            <option value="trade_license">Trade license</option>
            <option value="nid">Admin NID</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="field">
          <label class="label">File *</label>
          <input type="file" name="file" accept="application/pdf,image/jpeg,image/png,image/webp,image/heic" required />
        </div>
        <div class="flex-between mt-3">
          <button class="btn" type="button" data-back="2">← Back</button>
          <button class="btn btn--primary" type="submit">Submit for review</button>
        </div>
      </form>
      <p class="text-muted mt-3" style="font-size:.85rem;">Your file is private — only admin reviewers can see it.</p>
    </section>

    <!-- success -->
    <section class="card mt-3" data-step="done" hidden>
      <h3>Submitted for review</h3>
      <p>Thank you! Your claim is in our moderation queue. We typically review within 24 hours.</p>
      <div class="flex">
        <a class="btn btn--primary" href="/dashboard.php">Open dashboard</a>
        <a class="btn" href="/directory.php">Browse directory</a>
      </div>
    </section>
  </div>
</section>

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
<script src="/assets/js/bd-locations.js"></script>
<script src="/assets/js/claim.js"></script>
</body>
</html>
