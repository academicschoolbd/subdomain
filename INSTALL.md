# Free Subdomain Platform — cPanel install guide  (v3.2)

This guide gets `institution.bd` and `smartschool.bd` running on a standard
**cPanel** shared host (PHP 8.0+) with:

- **Instant-claim mode** (default) — type a name, click once, you're live.
  No documents, no admin review until you flip the toggle in `/admin`.
- **Email + password sign-in** with a built-in **forgot-password** flow
  (60-minute single-use token), and a real **SMTP gateway** so reset
  links actually arrive in users' inboxes.
- **Social sign-in** (Google / Facebook / GitHub) — optional.
- **Cloudflare auto-DNS** — approved claims get an A record automatically.
- **Owner-managed DNS records** — once verified, owners can add A / AAAA
  / CNAME / TXT / MX / NS records straight from their dashboard.
- **Admin-managed payment methods** for the dashboard's "Support
  Developer" pane (bKash / Nagad / Rocket etc.).
- **Server-side SEO** on `/i/<brand>/<slug>` — proper title / OG /
  Twitter / JSON-LD before any JS runs.
- **Per-user claim throttle** (5/hour, admins exempt).
- WhatsApp floating support button + community band.
- Dashboard with editable profile, claim withdraw, DNS status panel.
- Admin console with moderation queue (bulk decide), user roles,
  reserved slugs, audit log, CSV exports, and a Settings tab to toggle
  instant-claim / require-documents / Cloudflare-auto-DNS without
  editing any PHP.
- Auto-generated `/sitemap.xml`, `robots.txt`, PWA manifest, dark mode.

Total time: about 10 minutes (15 if you also wire OAuth, Cloudflare or SMTP).

---

## What's new in v3.0 (and how to use it)

After deploying the new build, hit `/api/healthz` once and your DB is
auto-migrated (idempotent — adds `password_resets`, `login_throttle` and
`platform_settings` tables, plus a few new columns on `users`). Nothing else
needed.

| Capability | Where it lives | Default |
|---|---|---|
| Instant claim — auto-verified, no docs | `/admin` → Settings | **ON** |
| Cloudflare auto-DNS on every verified claim | `/admin` → Settings + `api/config.php` `cloudflare` block | ON if CF is configured |
| Require documents (admin moderation) | `/admin` → Settings | OFF |
| Forgot password / reset link | Sign-in modal → "Forgot your password?" | always |
| Brute-force throttle (8 fails / 15 min) | automatic | always |
| Audit log | `/admin` → Audit log tab | always |
| CSV exports (claims, users) | `/admin` → Exports tab | always |
| Withdraw your own claim | `/dashboard` → claim card | always |
| Dark mode toggle | nav (sun / moon icon) | follows OS |
| PWA install / `manifest.webmanifest` | `<head>` of every page | always |
| Sitemap + robots.txt | `/sitemap.xml`, `/robots.txt` | always |
| JSON-LD on institution page | `/i/<brand>/<slug>` | always |

> ⚠️ **Going from v2.x → v3.0 with no admin?** The fast path: in
> `api/config.php` set `'admin_email'` to your email, then sign in once. The
> bootstrap promotes that account to admin and seeds the new
> `platform_settings` rows the first time `/admin` loads.

---

## 0. Requirements

| Thing | Minimum |
|---|---|
| PHP | 8.0+ (8.1+ recommended) |
| Extensions | `pdo_sqlite` (default) **or** `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd` |
| Apache | with `mod_rewrite` (every cPanel host has it) |
| Domains | `institution.bd` and `smartschool.bd` pointed at your cPanel server |
| OAuth apps | Google, Facebook and/or GitHub (you can enable any subset) |
| Cloudflare (optional, recommended) | both zones added to your CF account |

> If you only own one of the two brand domains you can still run the platform —
> in `api/config.php` set `'brands' => ['institution.bd']` (or just smartschool.bd).

---

## 1. Upload the files

1. Unzip `free-subdomain_platform.zip` on your laptop.
2. In cPanel → **File Manager** open `public_html/`.
3. Upload everything from the unzipped folder so that on the server you have:

   ```
   public_html/
     .htaccess
     index.php
     directory.php
     claim.php
     dashboard.php
     admin.php
     institution.php
     auth/callback.php
     assets/
     api/
     uploads/
   ```

   `index.php` must sit directly inside `public_html/`. **Do not** create an
   extra nested folder.

4. `database/` and the `.md` files are reference-only — keep them outside `public_html/`.

---

## 2. Permissions

In File Manager, right-click → **Change Permissions**:

| Path | chmod |
|---|---|
| `public_html/api/data/` | **0775** (writable by PHP) |
| `public_html/uploads/` | **0775** (writable by PHP) |
| every `.php` file inside `api/` | 0644 |
| every directory inside `api/` | 0755 |

---

## 3. Configure `api/config.php`

```bash
# Inside cPanel File Manager:
public_html/api/config.example.php  →  copy as  →  public_html/api/config.php
```

Open `public_html/api/config.php` and fill in:

```php
'site_url'    => 'https://institution.bd',   // your real public URL, NO trailing slash
'jwt_secret'  => '...64 char random string...',
'admin_email' => 'you@yourdomain',           // first OAuth login with this email becomes admin
'demo_mode'   => false,                       // can stay true while you test
```

Generate a JWT secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## 4. Set up OAuth providers — *optional*

> **You don't need OAuth at all** to launch. The platform ships with
> email + password sign-in built-in (see section 4d), and it is always
> on. Set up OAuth only if you want one-click sign-in with Google,
> Facebook or GitHub in addition.

You can enable any subset. Leave a provider's `client_id` empty to hide
its button on the login modal — email sign-in keeps working regardless.

All three providers use this redirect URI pattern:

```
https://institution.bd/api/auth/oauth/<provider>/callback
```

So the three URIs you'll paste in are:

```
https://institution.bd/api/auth/oauth/google/callback
https://institution.bd/api/auth/oauth/facebook/callback
https://institution.bd/api/auth/oauth/github/callback
```

### 4a. Google

1. Go to <https://console.cloud.google.com/apis/credentials>.
2. Create a project (or select one). Configure the **OAuth consent screen**:
   - User type: **External**
   - Scopes: `openid`, `email`, `profile`
   - Add yourself as a test user while in *Testing* mode.
3. **Credentials → Create credentials → OAuth client ID → Web application**.
4. Authorized redirect URIs: paste `https://institution.bd/api/auth/oauth/google/callback`.
5. Copy the **Client ID** and **Client secret** into `config.php`:
   ```php
   'oauth' => [
       'google' => [
           'client_id'     => '••••.apps.googleusercontent.com',
           'client_secret' => '••••',
       ],
       ...
   ],
   ```

### 4b. Facebook

1. Go to <https://developers.facebook.com/apps/>.
2. **Create App → Consumer → next**. Name it `institution.bd`.
3. **Add product → Facebook Login → Web**.
4. Site URL: `https://institution.bd`.
5. Facebook Login → Settings → **Valid OAuth redirect URIs**: paste
   `https://institution.bd/api/auth/oauth/facebook/callback`.
6. App settings → Basic — copy **App ID** and **App secret** into `config.php`:
   ```php
   'facebook' => [
       'client_id'     => '123456789012345',
       'client_secret' => '••••',
   ],
   ```
7. Switch the app to **Live** mode before launch (top toggle).

### 4c. GitHub

1. Go to <https://github.com/settings/developers> → **OAuth Apps → New OAuth App**.
2. Application name: `institution.bd`.
3. Homepage URL: `https://institution.bd`.
4. Authorization callback URL: `https://institution.bd/api/auth/oauth/github/callback`.
5. After registering, click **Generate a new client secret** and copy both
   the **Client ID** and the secret into `config.php`:
   ```php
   'github' => [
       'client_id'     => 'Ov23li••••',
       'client_secret' => '••••',
   ],
   ```

---

## 5. Configure WhatsApp

```php
'whatsapp' => [
    // International form, NO +, dashes or spaces. e.g. '8801712345678'
    'support_number'  => '8801712345678',
    'support_prefilled_message' => "Hi! I need help with institution.bd",

    // Paste your WhatsApp Community/Group invite link here.
    'community_url'   => 'https://chat.whatsapp.com/EXAMPLEINVITE',
    'community_title' => 'Join our WhatsApp community',
    'community_subtitle' => 'Get announcements, support and meet other institution owners.',
],
```

- The floating bottom-right **WhatsApp support button** opens a 1-to-1 chat
  with `support_number`.
- The homepage **community band** + the footer link point to `community_url`.
- Leave a field blank to hide that element entirely.

---

## 6. Pick a database — SQLite (default) or MySQL

### Option A — SQLite (zero config, recommended for first launch)

Nothing to do. The first request to `/api/healthz` creates
`api/data/app.db` automatically with the full schema, the reserved-slug
list, and 12 demo institutions.

> **Back this up.** Once you're live, schedule a daily download of
> `api/data/app.db`. It's the whole database.

### Option B — MySQL

1. cPanel → **MySQL Databases** → create database + user, grant all privileges.
2. cPanel → **phpMyAdmin** → select the new DB → **Import** → upload
   `database/install.sql`.
3. In `api/config.php`:
   ```php
   'db_driver'     => 'mysql',
   'db_mysql_host' => 'localhost',
   'db_mysql_port' => '3306',
   'db_mysql_name' => 'cpaneluser_subdomains',
   'db_mysql_user' => 'cpaneluser_admin',
   'db_mysql_pass' => '••••••••',
   ```
4. Hit `/api/healthz` once — schema is reconciled, reserved-slug list +
   demo data are seeded automatically.

### Upgrading an existing v1 database

The bootstrap routine is idempotent. On the first request after deploying
v2 it will:

- `ALTER TABLE users ADD COLUMN email | name | avatar_url | provider | provider_id`
  for any of those columns missing.
- `CREATE TABLE IF NOT EXISTS oauth_states (...)`.

Your v1 users (with their phone numbers + claims + uploads) are kept
intact and can continue to sign in via phone-OTP (the API still accepts
it), or re-link by signing in with an OAuth account that has the same
email.

---

## 7. DNS — wildcard for both brands

In Cloudflare:

| Type | Name | Content | Proxy |
|---|---|---|---|
| A | `institution.bd` | your server IPv4 | Proxied |
| A | `*.institution.bd` | your server IPv4 | Proxied |
| A | `smartschool.bd` | your server IPv4 | Proxied |
| A | `*.smartschool.bd` | your server IPv4 | Proxied |

The wildcard makes `<anything>.institution.bd` resolve to your server.

### SSL for the wildcards

- **Cloudflare proxy (orange-cloud) + Universal SSL** covers the apex
  but **not** the wildcard. For wildcard SSL on Cloudflare's free plan
  use **Cloudflare Advanced Certificate Manager** ($10/mo), or
- **Let's Encrypt via cPanel's AutoSSL** — works on most cPanel hosts via
  DNS-01 challenge.

---

## 8. Cloudflare auto-DNS (optional)

1. Cloudflare → **Manage Account → API Tokens → Create Token → Custom token**:
   - Permissions: `Zone` → `DNS` → `Edit`
   - Zone Resources: include both **institution.bd** and **smartschool.bd**
   - Click *Create token* and copy the value.
2. Copy each zone's **Zone ID** from Cloudflare → zone Overview page.
3. Paste into `api/config.php`:
   ```php
   'cloudflare' => [
       'api_token'   => 'cf-token-here',
       'zones'       => [
           'institution.bd' => 'institution-bd-zone-id',
           'smartschool.bd' => 'smartschool-bd-zone-id',
       ],
       'target_type'  => 'A',
       'target_value' => '203.0.113.10',   // your cPanel shared IP
       'proxied'      => true,
   ],
   ```

Now when an admin clicks **Approve** the DNS record is created in
Cloudflare automatically. On **Reject** or **Suspend** it is deleted.

---

## 9. Smoke test

After upload + config:

```
https://institution.bd/api/healthz
→ {"ok":true,"service":"free-subdomain-platform","demo_mode":...}

https://institution.bd/api/settings
→ shows oauth_providers (only those with client_id set) + whatsapp links

https://institution.bd/
→ Hero, dynamic stats, slug-availability checker, directory preview,
  WhatsApp community band, floating WhatsApp button bottom-right.

https://institution.bd/directory
→ Full AJAX directory.

https://institution.bd/i/institution.bd/dhakacollege
→ Public tenant page for Dhaka College (seeded demo).
```

If any of these return a blank page or 500, check:

- File Manager → `public_html/api/data/` and `public_html/uploads/` are 0775.
- `public_html/api/config.php` exists (copied from `config.example.php`).
- cPanel → **Errors** log for the latest PHP exception.

---

## 10. Log in as admin

1. Set `admin_email` in `config.php` to the email you'll use for OAuth.
2. Open `https://institution.bd/`, click **Sign in**.
3. Pick **Continue with Google / Facebook / GitHub** — whichever you wired up.
4. Approve the consent screen → you land back at the site, signed in.
5. The first OAuth login with `admin_email` is auto-promoted to admin.
6. Visit `/admin` to see the moderation queue.

---

## 11. Going live checklist

- [ ] Changed `jwt_secret` to a 64-char random string
- [ ] At least one OAuth provider configured + tested
- [ ] `site_url` matches your real public URL (https://, no trailing slash)
- [ ] WhatsApp support number + community link set (or intentionally blank)
- [ ] Wildcard DNS configured for both brands
- [ ] Wildcard SSL working (test `https://test.institution.bd`)
- [ ] Cloudflare auto-DNS token + zone IDs filled in (if you want auto DNS)
- [ ] `public_html/uploads/` and `public_html/api/data/` are writable
- [ ] Daily backup of `app.db` (SQLite) or your MySQL DB
- [ ] `demo_mode => false` if you don't want OTP codes exposed in API responses

---

## 12. Where things live

| Action | Where |
|---|---|
| Add / remove reserved slugs | `/admin` → Reserved slugs tab |
| Moderate claims | `/admin` → Queue |
| Retry DNS for a stuck claim | `/admin` → click claim → Retry DNS button |
| Edit a tenant's profile (as owner) | `/dashboard` → click *Manage* on the claim |
| Audit log | DB table `audit_log` |
| Reset everything | Delete `api/data/app.db` (SQLite) — next request re-seeds |

---

## 13. Troubleshooting

**`500 on /api/healthz`** — `api/data/` not writable. Set to 0775.

**`/api/auth/oauth/google/start` returns 404** — that provider has no
`client_id` in `config.php`. Add it.

**OAuth callback shows "Invalid or expired state"** — the state token
lives for 10 minutes. Just click the provider button again.

**OAuth callback shows "Could not retrieve profile"** — your client
secret is wrong, or your redirect URI in the provider's console doesn't
match exactly. Re-check the redirect URI character-for-character (no
trailing slash).

**Floating WhatsApp button doesn't appear** — `support_number` is empty
in `config.php`, or the browser blocked the `/api/settings` request.
Check the browser console.

**Upload fails with 413** — cPanel → **MultiPHP INI Editor**: raise
`upload_max_filesize` and `post_max_size` to at least `8M`.

**Cloudflare auto-DNS silently fails** — open the claim from the admin
queue, the DNS panel shows the exact Cloudflare API error. Most common
cause: token doesn't have `Zone:DNS:Edit` for that zone.

**Password-reset email never arrives** — Admin → Integrations →
Outbound email. Set transport to "SMTP", paste a working host / port /
user / pass (Gmail, SendGrid, Mailgun, Amazon SES, or the cPanel mailbox
you set up for `no-reply@<your-domain>`). Trigger another forgot-password
request and check your error log if it still fails — `mail.php` writes
one diagnostic line per failure.

**Owner can't add a DNS record** — the institution must be `verified`.
Pending / needs-info / rejected claims don't expose the DNS editor.

**Frontend loads but API returns CORS errors** — frontend and API on
different origins. Set `cors_allow_origin` to your frontend origin (or
`*` for testing).

---

## 14. Updating to a newer build

1. Back up `api/config.php`, `api/data/app.db`, and `uploads/`.
2. Delete `index.php`, `directory.php`, `claim.php`, `dashboard.php`,
   `admin.php`, `institution.php`, `auth/`, `assets/`, `api/`.
3. Upload the new build's files into `public_html/`.
4. Restore your `config.php`, `app.db`, and `uploads/`.
5. Hit `/api/healthz` once — schema is upgraded automatically (idempotent
   `CREATE TABLE IF NOT EXISTS` + column ADDs).

That's it.
