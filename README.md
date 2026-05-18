# Free Subdomain Platform — institution.bd & smartschool.bd  (v3.0)

A free verified-subdomain platform for every Bangladeshi school, college,
university, madrasa, polytechnic, training institute and NGO.

> **v3.0 — what's new**
> - **Bug fix** — admin document download now works (previous build sent the
>   JWT in `?_t=…` but the server only read it from the `Authorization`
>   header). The token query-string is now an accepted fallback for direct
>   browser-link endpoints (admin docs, CSV exports, sitemap when private).
> - **Forgot password** + **reset password** — single-use, 60-minute tokens.
>   In `demo_mode` the API surfaces the reset URL so admins can complete the
>   flow without an SMTP gateway. Wire your real mail provider for production.
> - **Brute-force throttle** — max 8 failed email-login attempts per
>   (email | IP) in a sliding 15-minute window. Successful login clears the
>   trail. Backed by a new `login_throttle` table.
> - **Withdraw a claim** — owners can delete their own pending / needs-info /
>   rejected claims from the dashboard. Verified or seeded entries still
>   require admin (suspend) since they may have a live DNS record.
> - **Editable profile** — the dashboard now has a top-of-page profile card
>   so users can update name / mobile / designation / division / district /
>   upazila / institution-name *after* the initial gate, plus a
>   change-password section.
> - **Admin audit log** — new tab listing every action with actor, action,
>   institution, and detail. Filter by action prefix / inst-id / search.
> - **CSV exports** — `Export claims.csv` and `Export users.csv` from the
>   admin console (UTF-8 BOM so Excel reads Bengali correctly).
> - **SEO + Sharing** — generated `/sitemap.xml`, served `robots.txt`, and
>   JSON-LD `EducationalOrganization` markup on every institution page.
> - **PWA + accessibility** — installable web app (manifest), keyboard-only
>   "Skip to main content" link, and a header dark-mode toggle that respects
>   `prefers-color-scheme` by default and persists per user.
>
> Full changelog: see "What changed since v2" below.

> **v2.1 additions**:
> - Realtime homepage tiles: **Users registered · Domains claimed · Pending · Rejected** (auto-refresh every 30 s).
> - **Profile gate** before claiming — collects Full Name, Mobile, পদবি (designation), প্রতিষ্ঠানের নাম, বিভাগ, জেলা, উপজেলা once per user.
> - All pages now served as **`.php`** files (`index.php`, `claim.php`, …). Pretty URLs (`/claim`, `/directory`, …) still work via `.htaccess`.
>
> **v2 highlights:** social sign-in (Google / Facebook / GitHub), floating
> WhatsApp support button, "Join WhatsApp community" section, fully
> responsive modern homepage with dynamic AJAX, brand-new vanilla-JS
> frontend (no build step), all pages served as PHP/HTML with a
> shared PHP 8 API.

Drops straight into **shared cPanel hosting** — pure PHP 8 + SQLite (or
MySQL if you prefer) + static HTML/CSS/JS. **No Composer, no Node, no
build step on the server.**

---

## What's in the box

```
free-subdomain_platform/
├── .htaccess              Apache rewrites: API, uploads, pretty URLs
├── index.php             Homepage (hero, dynamic stats, directory preview, WA community)
├── directory.php         AJAX directory with filters
├── claim.php             3-step claim wizard (subdomain → details → docs)
├── dashboard.php         Owner dashboard (claims, profile, images, notices, docs, withdraw, change password)
├── admin.php             Admin console — queue, reserved slugs, audit log, CSV exports
├── institution.php       Single-tenant public page (with JSON-LD structured data)
├── reset-password.php    Token-based password reset landing page (v3.0)
├── sitemap.xml.php       Generated XML sitemap of every verified/seeded tenant (v3.0)
├── robots.txt            Search-engine policy (v3.0)
├── manifest.webmanifest  PWA manifest — installable on Android/iOS (v3.0)
├── auth/
│   └── callback.php      OAuth landing — decodes token from URL fragment
├── assets/
│   ├── css/style.css      Modern, mobile-first stylesheet — light + dark themes
│   ├── js/
│   │   ├── app.js         Shared module (API client, auth modal, OAuth, theme, manifest, skip-link)
│   │   ├── home.js / directory.js / institution.js / claim.js
│   │   ├── dashboard.js / admin.js / auth-callback.js
│   │   └── reset-password.js  (v3.0)
│   └── img/               Logo, favicon, WhatsApp + Google/FB/GitHub icons
├── api/                   PHP 8 backend
│   ├── index.php          Front controller
│   ├── bootstrap.php      Loads config, init DB, init schema, seed
│   ├── config.example.php Copy to config.php and edit
│   ├── .htaccess          Locks down lib/, routes/, config files
│   ├── data/              SQLite database lives here (auto-created)
│   ├── lib/               auth, db, slug, cloudflare, http  (OAuth + login throttle helpers)
│   └── routes/            auth.php, claim.php, admin.php, public.php
├── uploads/               Writable; logos, banners, docs land here
├── database/install.sql   OPTIONAL — only for MySQL
├── router.php             Local dev only: `php -S 127.0.0.1:8080 router.php`
├── INSTALL.md             Step-by-step cPanel + OAuth setup guide
└── README.md              This file
```

---

## Features

### Public
- **One-click "⚡ Claim it now"** — type a name, the homepage button morphs
  into a pulsing primary action the moment the slug is available; one click
  jumps straight to the claim wizard.
- **Instant publish** — by default no documents are required; the claim is
  auto-verified and Cloudflare creates the DNS record on the spot. Admins
  can toggle "Require documents" back on at any time from the admin
  settings page.
- Modern flat hero with realtime tiles (verified / pending / seeded counts via AJAX).
- Slug availability checker that updates as you type (debounced, ~250 ms).
- Directory with AJAX filtering: brand, category, division, search-as-you-type, pagination.
- Per-institution page at `/i/<brand>/<slug>` (in production: `<slug>.<brand>` via wildcard DNS), including `EducationalOrganization` JSON-LD for rich Google cards.
- Notice board on each verified institution.
- "Join WhatsApp community" call-to-action band on the homepage and in the footer.
- Floating WhatsApp support button on every page (pulse animation, bottom-right corner).
- Generated `/sitemap.xml` and `robots.txt`, plus a PWA manifest so the site is installable on Android / iOS.
- Built-in **dark mode** toggle in the nav (also respects `prefers-color-scheme`).
- Keyboard-only "Skip to main content" link on every page.

### Owners — sign in (Email + password / Google / Facebook / GitHub)
- Sign in with email + password, or one-click via Google, Facebook or GitHub.
- **Forgot password** + token-based reset flow (60-min expiry, single-use).
- **Brute-force throttle** — 8 failed logins per email | IP in 15 min, then a soft lockout.
- Multi-step claim wizard collapses to 3 steps in instant-claim mode.
- Live slug-availability check that respects admin-reserved words.
- Dashboard with editable profile card + change-password expander, full claim list with status badges, **DNS / Cloudflare panel** per verified claim with a Retry-DNS button, and a per-claim **Withdraw** button (releases the slug + cleans up CF DNS).
- Tenant editor: profile, logo + banner upload, document upload (when admin requires it), notice CRUD, DNS status panel.

### Admin
- Moderation queue with filter by status + search by name / slug / EIIN.
- Per-claim detail view with all uploaded documents (private — only admins can download; the doc-download bug from v2.x is fixed).
- Four decisions: Approve · Needs info · Reject · Suspend (each with notes shown to the owner).
- On Approve → DNS record auto-created in Cloudflare. On Reject / Suspend → record deleted.
- DNS retry button for any approved claim whose Cloudflare call previously failed.
- Reserved-slugs manager (add / delete blocked subdomains).
- **Audit log** tab — every action with actor, target, action name and detail. Filter by action prefix, search, institution id.
- **CSV exports** — `Export claims.csv` and `Export users.csv` with UTF-8 BOM (Excel reads Bengali correctly).
- **Settings** tab — toggle `Instant claim`, `Require documents`, `Cloudflare auto-DNS`. The Cloudflare status banner shows whether the API token is configured for each brand.
- Stats dashboard (totals by status + total users).

### Cloudflare auto-DNS (optional)
- Paste a scoped API token + each brand's Zone ID + your server IP into `config.php`.
- Approve → POST to `/zones/{zone_id}/dns_records`.
- Reject/Suspend → DELETE the record.
- Leave the fields blank to disable — the platform works fine without it.

---

## What changed since v1

| | v1 | v2 |
|---|---|---|
| Sign-in | Phone-OTP (Bangladesh mobile) | Google / Facebook / GitHub OAuth (passwordless) |
| Frontend | React + Vite build | Vanilla HTML + CSS + JS — **no build step** |
| Homepage | Static hero + search | Dynamic stats, AJAX availability check, directory preview, WhatsApp community band |
| Support | (none) | Floating WhatsApp icon site-wide |
| Pretty URLs | SPA fallback only | Real pages with `/directory`, `/claim`, `/dashboard`, `/admin`, `/i/<brand>/<slug>` |
| Migration | n/a | Idempotent — existing v1 SQLite/MySQL data is auto-upgraded |

Phone-OTP routes are still mounted (`/api/auth/request-otp`, `/api/auth/verify-otp`)
for backwards compatibility, but the UI no longer surfaces them.

---

## Quick start — local

```bash
cd free-subdomain_platform/
cp api/config.example.php api/config.php   # then edit jwt_secret + OAuth client IDs
php -S 127.0.0.1:8080 router.php
```

Open <http://127.0.0.1:8080>.

For local OAuth testing, register each provider's app with redirect URI
`http://127.0.0.1:8080/api/auth/oauth/<provider>/callback` and update
`'site_url' => 'http://127.0.0.1:8080'` in `config.php`.

---

## Production deploy (cPanel)

See **INSTALL.md** for the full step-by-step. Short version:

1. Unzip into `public_html/` so that `public_html/index.php`, `public_html/api/`, `public_html/uploads/`, and `public_html/auth/callback.php` all exist.
2. Copy `api/config.example.php` → `api/config.php`. Edit `site_url`, `jwt_secret`, `admin_email`, OAuth client IDs + secrets, and WhatsApp numbers/links.
3. Register OAuth apps with Google / Facebook / GitHub. Redirect URIs all follow the pattern `https://institution.bd/api/auth/oauth/<provider>/callback`.
4. Make `api/data/` and `uploads/` writable (`chmod 0775`).
5. (Optional, for MySQL) create a DB in cPanel, import `database/install.sql`, set `db_driver => 'mysql'` plus credentials in `config.php`.
6. (Optional, for auto-DNS) fill the `cloudflare` block in `config.php`.
7. In Cloudflare set wildcard DNS for `*.institution.bd` and `*.smartschool.bd` → your server IP.
8. Visit `https://institution.bd/api/healthz` — you should see `{"ok":true,...}`.

That's it. The first request auto-creates the database schema, seeds the
reserved-slug list, and inserts demo data.

---

## OAuth provider quick-links

| Provider | Console | What you'll paste back |
|---|---|---|
| Google | https://console.cloud.google.com/apis/credentials | Client ID + Client secret |
| Facebook | https://developers.facebook.com/apps/ | App ID + App secret |
| GitHub | https://github.com/settings/developers | Client ID + Client secret |

Redirect URI (all three providers):
`https://institution.bd/api/auth/oauth/<provider>/callback`

You can enable **any subset** — leave a provider's `client_id` blank in
`config.php` to hide that button on the login modal. At least one
provider must be enabled.

---

## Default admin

Set `admin_email` in `config.php` to the email you'll use with Google /
Facebook / GitHub. The first time you sign in with that account, your
user row is auto-promoted to admin (`is_admin = 1`).

Legacy fallback: `admin_phone => '01700000000'` still works if you call the
phone-OTP API directly, but the UI no longer surfaces it.

---

## Tech notes

- **Backend:** plain PHP 8 + PDO (SQLite or MySQL). No Composer dependencies.
- **Frontend:** vanilla HTML + CSS + JS. No bundler, no Node, no build.
- **Auth:** stateless JWT in `Authorization: Bearer …` header (14-day TTL). OAuth state token stored in `oauth_states` for CSRF protection (10-minute TTL).
- **Token transport:** after OAuth callback, the JWT is returned to the browser as a URL **fragment** (`#payload=…`) so it never hits server logs.
- **Upload limits:** 4 MB images (logo/banner), 8 MB documents (NID/EIIN/board letter).
- **Reserved slugs:** 40+ defaults seeded on first boot. Admin UI can add/remove at any time.
- **Idempotent migrations:** the bootstrap routine adds the new `email`, `name`, `avatar_url`, `provider`, `provider_id` columns to `users` if they don't exist, so v1 → v2 upgrades just work.

---

## License

Use, modify, and ship freely. No warranty.
