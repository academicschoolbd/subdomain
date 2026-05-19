# institution.bd / smartschool.bd — v5pro

> Free verified subdomain platform for Bangladeshi educational institutions and NGOs.

## What's New in v5pro

### Major Upgrades
- **Bootstrap 5.3** — Full integration with Bootstrap CSS framework, responsive grid, utilities, and components
- **Bootstrap Icons** — 2000+ icons replacing inline SVGs for cleaner, more maintainable markup
- **Modern JavaScript (ES6+)** — Arrow functions, async/await, optional chaining, template literals, modules
- **Dark/Light Mode** — Bootstrap's native `data-bs-theme` with smooth toggle, persistent preference
- **Inter Font** — Modern variable font replacing Raleway for improved readability

### Dashboard Upgrades
- Card-based layout with shadows and rounded corners
- Quick KPI tiles with click-to-filter interaction
- Bootstrap table with responsive wrapper
- Tabbed manage modal (DNS, Profile, Branding, Documents)
- Smooth animations and skeleton loading states

### Admin Console Upgrades
- Sticky sidebar navigation with icon + label + badge counts
- KPI overview with one-click jump to filtered panes
- Activity feed with real-time action verbs
- Bulk approve/reject with visual count indicators
- Full-screen detail modal with Bootstrap tabs
- Quick actions grid for fast navigation

### Other Improvements
- Accordion FAQ on homepage (Bootstrap collapse)
- Animated stat counters with delta indicators
- Gradient hero with search bar
- WhatsApp float button with pulse animation
- Toast notifications using Bootstrap component
- Responsive tables across all pages
- Print-friendly styles (hides nav/sidebar)
- Accessibility: skip-link, ARIA labels, semantic HTML

## Tech Stack

| Layer | Technology |
|-------|-----------|
| CSS Framework | Bootstrap 5.3.3 |
| Icons | Bootstrap Icons 1.11 |
| Font | Inter (Google Fonts) |
| JS | Vanilla ES6+ (no build step) |
| Backend | PHP 8+ with PDO |
| DNS | Cloudflare API |
| Auth | JWT + OAuth (Google, Facebook, GitHub) |

## Quick Start

1. Copy `api/config.example.php` → `api/config.php` and fill in DB + OAuth credentials
2. Import `database/install.sql` into your MySQL/MariaDB instance
3. Point a web server (Apache/Nginx) at the project root
4. Visit `/admin.php` to configure platform settings

## File Structure

```
├── index.php          # Homepage (hero, stats, features, FAQ)
├── dashboard.php      # Owner control panel
├── admin.php          # Admin moderation console
├── claim.php          # Claim wizard (3-step)
├── directory.php      # Public verified directory
├── privacy.php        # Privacy policy
├── terms.php          # Terms of service
├── assets/
│   ├── css/style.css  # Custom theme + Bootstrap overrides
│   ├── js/
│   │   ├── app.js     # Shared module (auth, API, theme, toasts)
│   │   ├── home.js    # Homepage (stats, search, featured)
│   │   ├── dashboard.js # Owner dashboard logic
│   │   ├── admin.js   # Admin console logic
│   │   ├── claim.js   # Claim wizard logic
│   │   ├── directory.js # Directory search + pagination
│   │   └── bd-locations.js # Bangladesh divisions/districts
│   └── img/           # Logos, favicons, provider icons
├── api/               # PHP REST API
│   ├── bootstrap.php  # Config + DB init
│   ├── routes/        # API route handlers
│   └── lib/           # Helpers (auth, cloudflare, mail, etc.)
└── database/
    └── install.sql    # Schema
```

## License

MIT — Free for any Bangladeshi educational institution or NGO.
