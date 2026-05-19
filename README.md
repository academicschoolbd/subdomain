# Free Subdomain Platform — institution.bd & smartschool.bd  (v4.0)

A free verified-subdomain platform for every Bangladeshi school, college,
university, madrasa, polytechnic, training institute and NGO.

> **v4.0 — what's new**
> - **Mobile-first home page (no more pinch-to-zoom).** Reworked every
>   breakpoint on the public site so the hero, search box, live-stats
>   tiles, brand cards, features, FAQ, footer and final CTA all fit
>   inside a 320 px viewport without horizontal scroll. Long FQDNs and
>   subdomain `<code>` blocks now wrap at any character; the hero
>   headline auto-clamps to fit even on the narrowest phones.
> - **Auto Cloudflare DNS sync — only on approved domains.** Owner DNS
>   record create / edit / delete already pushes to Cloudflare via the
>   stored API token. v4 hardens the gate: the API rejects DNS reads /
>   writes on every non-verified status (`pending`, `needs_info`,
>   `rejected`, `seeded`, `suspended`) with a friendlier message
>   ("approved by an admin"), and the dashboard Manage modal hides the
>   whole DNS tab until approval. Pending claims simply cannot publish
>   DNS — local or upstream.
> - **Owner DNS panel no longer shows "Retry DNS".** Retrying a stuck
>   Cloudflare push is an operator concern; the button stayed on the
>   admin console only. The owner card is now just a status pill plus
>   "Open `<subdomain>` →".
> - **Dashboard, admin, claim, directory, institution, auth pages** all
>   re-audited for narrow-screen behaviour: tab strips scroll instead of
>   wrapping, banner CTAs go full-width, tables stay inside their
>   wrappers, and any flex row that previously overflowed (`.flex-between`)
>   now wraps with a hard `min-width:0`.
>
> **v3.3 — what shipped before**
> - Mobile nav fix after sign-in (predictable order, fixed-width hit
>   targets), GitHub-icon visible in dark mode, admin toggle to disable
>   manual email registration, scoped Cloudflare token validator,
>   harder secret masking.

> **v3.2 highlights**
> - **Owner-managed DNS records.** Every verified tenant gets a real DNS
>   editor in the Manage modal — A / AAAA / CNAME / TXT / MX / NS, with TTL,
>   priority and Cloudflare-proxy toggle. Records publish to Cloudflare
>   automatically when configured, or save locally with a `manual` flag
>   when the upstream API isn't reachable.
> - **Admin-managed "Support Developer" payments.** New `support_payments`
>   table (seeded with bKash / Nagad / Rocket). Admin → "Support payments"
>   pane lets you Add / Edit / Hide / Show / Delete every method, with
>   QR URL, sort order and a free-form note. Owners see only the visible
>   ones on the dashboard's Support pane, with one-click Copy on the number.
> - **Polished homepage search result card.** Green-tick "X.bd is available
>   — Claim Now" / red-cross with suggestion when taken, plus an "Also
>   available: X.smartschool.bd" sibling row.
> - **Live stats with no skeleton flash.** The four homepage tiles
>   (Users / Domains claimed / Pending / Rejected) are server-rendered on
>   first paint, then auto-refresh every 30 s with `+N since last refresh`
>   deltas.
> - **Trimmed home page.** Removed testimonials, the "trusted by every
>   division" trust strip, the who-it's-for grid, and the compare-with-
>   paid-com band. Final flow is hero → live stats → brands → features →
>   how it works → directory → community → FAQ → CTA.
> - **Real outbound email (SMTP).** New `api/lib/mail.php` — a
>   dependency-free SMTP client with `mail()` fallback. Wires into the
>   forgot-password flow so reset links actually arrive in users' inboxes.
>   Configured from `/admin → Integrations → Outbound email`.
> - **Per-tenant SEO.** `/i/<brand>/<slug>` now ships server-side
>   `<title>`, OpenGraph, Twitter Card and JSON-LD tags so Google, Facebook,
>   WhatsApp and link-preview bots see the right thing before any JS runs.
> - **Per-user claim throttle.** Non-admin accounts can submit at most
>   five new claims per hour (counted via `audit_log`); spam onboarding is
>   a non-issue.

> **v3.0 highlights** — forgot-password + token reset, 8/15-min brute-force
> throttle, withdraw-claim, editable owner profile + change-password,
> admin audit log, CSV exports, sitemap.xml, robots.txt, JSON-LD,
> PWA manifest, dark-mode toggle, skip-to-main link, settings panel.

> **v2 highlights** — social sign-in (Google / Facebook / GitHub),
> floating WhatsApp button, "Join community" band, vanilla-JS frontend,
> shared PHP 8 API.

Drops straight into **shared cPanel hosting** — pure PHP 8 + SQLite (or
MySQL) + static HTML/CSS/JS. **No Composer, no Node, no build step.**

---

## What's in the box

```
free-subdomain_platform/
├── .htaccess              Apache rewrites: API, uploads, pretty URLs
├── index.php              Homepage (hero, live stats, polished search card,
│                          brands, features, how-it-works, directory preview,
│                          WA community, FAQ, CTA)
├── directory.php          AJAX directory with filters
├── claim.php              Claim wizard (subdomain → details → docs)
├── dashboard.php          Owner dashboard — domains table + manage modal,
│                          DNS records editor, settings, Support Developer
├── admin.php              Admin console — overview, queue (bulk decide),
│                          users, reserved slugs, audit log, exports,
│                          settings, integrations (incl. SMTP), payments
├── institution.php        Single-tenant page — server-rendered <title>,
│                          OG / Twitter / JSON-LD for crawlers
├── reset-password.php     Token-based password reset landing
├── sitemap.xml.php        Generated XML sitemap
├── robots.txt             Search-engine policy
├── manifest.webmanifest   PWA manifest — installable on Android/iOS
├── auth/callback.php      OAuth landing — decodes JWT from URL fragment
├── assets/
│   ├── css/style.css       Modern, mobile-first, light + dark themes
│   ├── js/                 Vanilla JS — no bundler
│   │   ├── app.js          Shared (API client, auth modal, OAuth, theme)
│   │   ├── home.js
│   │   ├── directory.js
│   │   ├── institution.js  Tenant page hydration
│   │   ├── claim.js        Claim wizard
│   │   ├── dashboard.js    Owner dashboard + DNS editor + payments pane
│   │   ├── admin.js        Admin console (every pane)
│   │   ├── auth-callback.js
│   │   ├── reset-password.js
│   │   └── bd-locations.js Division → district → upazila cascade
│   └── img/                Logo, favicon, WhatsApp / Google / FB / GitHub
├── api/                    PHP 8 backend
│   ├── index.php           Front controller
│   ├── bootstrap.php       Loads config + integrations overlay, init schema
│   ├── config.example.php  Copy to config.php and edit
│   ├── .htaccess           Locks down lib/, routes/, config files
│   ├── data/               SQLite database lives here
│   ├── lib/
│   │   ├── auth.php        JWT, OAuth, login throttle, password reset
│   │   ├── cloudflare.php  Cloudflare DNS — single-record + arbitrary CRUD
│   │   ├── db.php          PDO + migrations + seeds (incl. v3.2 tables)
│   │   ├── http.php        send_json / send_error helpers
│   │   ├── integrations.php  Admin overlay (oauth, cloudflare, mail, jwt)
│   │   ├── mail.php        v3.2 — SMTP client + mail() fallback
│   │   └── slug.php
│   └── routes/             auth.php, claim.php, admin.php, public.php
├── uploads/                Writable; logos, banners, docs land here
├── database/install.sql    Optional — only for MySQL
├── router.php              Local dev only
├── INSTALL.md              Step-by-step cPanel guide
└── README.md               This file
```

---

## Features

### Public
- Polished search-result card on the homepage (green tick + Claim Now,
  or red cross with suggestion + sibling-brand row).
- Live realtime stats (Users / Domains claimed / Pending / Rejected) —
  server-seeded on first paint, auto-refresh every 30 s.
- AJAX directory with filters (brand, category, division, search-as-you-type).
- Per-institution page at `/i/<brand>/<slug>` (or `<slug>.<brand>` via
  wildcard DNS) with **server-rendered** title, OpenGraph, Twitter Card
  and `EducationalOrganization` / `NGO` JSON-LD.
- Notice board on each verified institution.
- "Join WhatsApp community" band + floating WhatsApp support button.
- Generated `/sitemap.xml`, served `robots.txt`, PWA manifest.
- Dark-mode toggle (respects OS preference, persists per user).
- "Skip to main content" keyboard link.

### Owners
- Email + password sign-in, or one-click via Google / Facebook / GitHub.
- **Forgot password** — emails a single-use 60-minute reset link via SMTP.
- 8/15-minute brute-force throttle on email login.
- Profile gate: name + mobile + designation + institution + division /
  district / upazila — collected once before the first claim.
- Dashboard with sidebar — Overview, Settings, Support Developer.
- Live "My Domains" table with status pills, search, status filter.
- **Manage modal per claim** — edit profile, upload logo / banner / docs,
  publish notices, **manage DNS records** (A/AAAA/CNAME/TXT/MX/NS, with
  TTL, priority, Cloudflare proxy toggle), and Withdraw button.
- 5/hour claim submission rate-limit (admins exempt).

### Admin
- Sidebar: Overview, Approval queue, Users, Reserved slugs, Audit log,
  Exports, Platform settings, Integrations, **Support payments**.
- KPI tiles + recent-activity feed on the Overview pane.
- Approval queue with **bulk decide** — checkbox-select rows, then
  Approve / Needs info / Reject in one click.
- Per-claim detail modal with all uploaded documents (auth-gated).
- Cloudflare auto-DNS on Approve; record deletion on Reject / Suspend.
- DNS retry button for any verified claim whose CF call failed.
- Reserved-slugs CRUD.
- Users pane — promote / demote teammates (the last admin can't be demoted).
- Audit log with filter by action prefix, search, institution id.
- CSV exports (claims, users) — UTF-8 BOM so Excel reads Bengali correctly.
- **Platform settings** — toggle `Require admin approval`, `Show "Claim
  it now" CTA`, `Cloudflare auto-DNS`. Banner shows whether CF is
  configured for each brand.
- **Integrations** — every secret, API key and external service edited
  inline (OAuth client_id/secret per provider, Cloudflare token + zones,
  WhatsApp number + community URL, **outbound email / SMTP**, JWT secret
  with one-click rotate).
- **Support payments** — manage the methods shown on the dashboard's
  Support Developer pane (bKash / Nagad / Rocket / Bank / PayPal /
  Crypto / Other), with QR URL, sort order, hidden flag.

### Cloudflare auto-DNS (optional but recommended)
- Paste a scoped API token + each brand's Zone ID + your server IP into
  the Integrations panel.
- Verified claims publish their A record automatically (proxied = orange
  cloud → free SSL + cache).
- Owner-managed records (A, AAAA, CNAME, TXT, MX, NS) push to the same
  zone via the same token.

### Outbound email
- `api/lib/mail.php` — dependency-free SMTP client with `mail()` fallback.
- Configured from Admin → Integrations → Outbound email. Picks SMTP
  automatically when `mail.smtp_host` is set, otherwise falls back to
  PHP's `mail()` (cPanel's local sendmail).
- Used by the password-reset flow (and any future transactional mail).

---

## Quick start — local

```bash
cd free-subdomain_platform/
cp api/config.example.php api/config.php   # edit jwt_secret + admin_email
php -S 127.0.0.1:8080 router.php
```

Open <http://127.0.0.1:8080>. The first request creates the SQLite DB
and seeds the reserved-slug list + 12 demo institutions.

---

## Production launch checklist (v4.0 final)

Before flipping the public DNS, walk through this list once.

1. **`api/config.php`** copied from `api/config.example.php`, with:
   - `'site_url'` set to `https://institution.bd` (no trailing slash).
   - `'jwt_secret'` set to a 64-char random string
     (`php -r "echo bin2hex(random_bytes(32));"`).
   - `'admin_email'` set to your real email — first sign-in with that
     account becomes admin automatically.
   - `'demo_mode' => false`.
2. **Database** — for production switch `'db_driver'` to `'mysql'` and fill
   in the credentials. Schedule a daily `mysqldump`.
3. **HTTPS** — Cloudflare Universal SSL (orange cloud) for the apex,
   plus Advanced Cert Manager / Let's Encrypt for `*.institution.bd` and
   `*.smartschool.bd`.
4. **Wildcard DNS** — point `*.institution.bd` and `*.smartschool.bd` at
   your server IP, both proxied.
5. **Cloudflare auto-DNS** — Admin → Integrations → Cloudflare. Paste an
   API token (`Zone:DNS:Edit` scope on both zones) + each Zone ID +
   `target_value` (your server IPv4). Click **Test Cloudflare token**.
6. **Outbound email** — Admin → Integrations → Outbound email. Pick SMTP
   and paste a working host / port / user / pass (Gmail, SendGrid,
   Mailgun, SES or your cPanel mailbox all work). Test with the
   forgot-password flow on a throwaway account.
7. **OAuth (optional)** — register apps at Google / Facebook / GitHub,
   paste client_id + client_secret in Admin → Integrations → Social sign-in.
   The redirect URIs are shown next to each provider with a Copy button.
8. **WhatsApp** — set the support number + community invite URL in
   Admin → Integrations → WhatsApp.
9. **Permissions** — `api/data/` and `uploads/` writable by the PHP user
   (`chmod 0775`).
10. **Backups** — daily snapshot of `api/data/app.db` (or your MySQL
    dump) + `uploads/` to off-site storage.

That's the whole list. After the first request to `/api/healthz` the
schema is migrated and the seeds run automatically — including the new
v3.2 `dns_records`, `support_payments` tables.

---

## Where things live

| Action | Where |
|---|---|
| Add / remove reserved slugs | `/admin` → Reserved slugs tab |
| Moderate claims | `/admin` → Approval queue (bulk decide supported) |
| Promote / demote admins | `/admin` → Users tab |
| Retry DNS for a stuck claim | Click the claim → DNS panel → Retry DNS |
| Edit OAuth / Cloudflare / SMTP / WhatsApp | `/admin` → Integrations |
| Toggle admin moderation on/off | `/admin` → Platform settings |
| Manage payment methods on Support Developer | `/admin` → Support payments |
| Edit your institution's profile | `/dashboard` → click *Manage* |
| Edit DNS records (owner) | `/dashboard` → click *Manage* on a verified claim → DNS records |
| Audit log | `/admin` → Audit log tab |
| CSV exports | `/admin` → Exports tab |
| Reset everything (dev) | Delete `api/data/app.db` — next request re-seeds |

---

## License

Use, modify, and ship freely. No warranty.
