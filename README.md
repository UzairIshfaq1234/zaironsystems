# ZAIRON website

Ideas, To Reality. Marketing site for ZAIRON, built with **Laravel 13** (backend, SEO, forms, security) and
**React 19 + React Router** (front end), bundled by **Vite**. No Inertia: Laravel serves one HTML shell and
React renders every page.

## Run it locally

Needs PHP ≥ 8.3, MySQL 8 (e.g. WAMP) and Node.js. On this Windows machine PHP isn't on PATH, so use
`C:\wamp64\bin\php\php8.4.15\php.exe` wherever `php` appears below.

**First time only**
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# Create the database (WAMP MySQL on 3306, user root, no password):
mysql -u root -e "CREATE DATABASE zairon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate
php artisan db:seed                                   # roles (+ super admin if SUPERADMIN_EMAIL is set)
php artisan zairon:superadmin you@domain.com --name="Your Name"   # or create the super admin this way
npm run build
```
Fill in the `MAIL_*` lines in `.env` (see below), then check them with `php artisan zairon:mail-test you@domain.com`.

**Every time** (three terminals, all in the project folder)
```bash
php artisan serve --port=8001   # website http://127.0.0.1:8001, admin http://127.0.0.1:8001/admin
php artisan queue:work          # sends emails; without it nothing is emailed
php artisan schedule:work       # daily jobs: overdue invoice reminders
```
Use `npm run dev` instead of `npm run build` only while editing the front end (hot reload).
**After changing `.env`, stop and restart `queue:work`**: a running worker keeps the old settings.
Port 8001 because 8000 is used by another local project; keep `APP_URL` in `.env` on the same port, since email
links use it.

For production: `npm run build`, then serve `public/` with PHP-FPM behind Nginx or Apache.

**Gmail as the mailer:** turn on 2-step verification, create an *App password* (Google Account → Security →
App passwords) and use it as `MAIL_PASSWORD` with `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_SCHEME=`
(empty), and `MAIL_USERNAME` / `MAIL_FROM_ADDRESS` set to that Gmail address. Gmail allows ~500 emails a day; use a
transactional provider (Brevo, Mailgun, SES) in production.

Tests: `php artisan test`

## Where things live

| What | Where |
| --- | --- |
| **All site copy** (services, FAQs, roles, posts, contact details, SEO titles) | `resources/content/*.json` |
| Pages | `resources/js/pages/` |
| Shared components (buttons, cards, sections, forms) | `resources/js/components/` |
| Design tokens (colors, fonts, spacing) | `resources/css/tokens.css` |
| Routes | `routes/web.php` (server) and `resources/js/App.jsx` (React) |
| Server-side SEO, JSON-LD, sitemap | `app/Support/Seo.php`, `app/Http/Controllers/PageController.php` |
| Contact and newsletter endpoints | `app/Http/Controllers/EnquiryController.php`, `SubscriberController.php` |
| Security headers / CSP | `app/Http/Middleware/SecurityHeaders.php` |

The JSON files under `resources/content` are read by **both** React and Laravel, so a change there updates the
page, its meta tags, its structured data and the sitemap at once.

### Adding a page
1. Add its SEO entry to `resources/content/pages.json` (the server returns 404 for paths not listed there).
2. Create the component in `resources/js/pages/` and add a `<Route>` in `resources/js/App.jsx`.

### Services, mega menu and service pages
Every service has its own page at `/services/{slug}`, built from one template (`resources/js/pages/ServiceDetail.jsx`).
To add or edit a service, change `resources/content/services.json`:
- `items` – name, icon, tagline (shown in the mega menu) and short text.
- `pillars` – the four groups in the mega menu and on `/services`.
- `details.{slug}` – the page copy: hero, capabilities, deliverables, "ideal for", process, tools, CTA.
FAQs per service live in `faqs.json`; the "How would you like to start?" plans live in `engagement.json`.
The route, mega menu, meta tags, sitemap and JSON-LD all pick changes up automatically.

### Visual effects
- `resources/js/components/fx/LogoScene.jsx` – WebGL 3D logo (Three.js), lazy-loaded on Home and About, paused
  off-screen, still frame for reduced-motion users, static logo if WebGL is unavailable.
- `resources/js/components/fx/HoloVisual.jsx` – CSS 3D hologram on service pages.
- `resources/js/components/fx/Scramble.jsx` – anagram decode on highlighted words and section labels.
- `data-tilt="N"` on any element gives it 3D tilt + holographic sheen (strength N degrees).
- All motion respects the visitor's "reduce motion" setting.

## Admin portal (`/admin`)

A separate React app (`resources/js/admin`, own stylesheet `resources/css/admin.css`) with its own JSON API
(`routes/admin.php`). Visitors to the website never download admin code.

- **First super admin:** `php artisan zairon:superadmin you@domain.com --name="Your Name"` (prints a generated
  password once), or set `SUPERADMIN_*` in `.env` and run `php artisan db:seed`.
- **Roles & permissions:** the permission catalog is `config/permissions.php`. Super admins create roles in the
  portal, tick permissions, and assign roles to users. Every API route checks its permission server-side.
  Built-in guardrails: nobody can grant permissions they don't hold, only super admins can assign the Super Admin
  role or edit super admins, nobody can change their own roles or delete themselves, and the last super admin
  can't be removed. The Super Admin system role can't be edited or deleted.
- **Users** are invited by email and set their own password (72-hour link). Deactivated users are signed out
  on their next request.
- **Sign-in security:** 5 attempts/minute per email+IP, identical errors for unknown/disabled accounts,
  session regeneration, password reset by email, strong password rules, activity log of every change.
- Modules: Overview (stats + charts), Enquiries (status pipeline, notes, private attachment download),
  Subscribers (CSV export, formula-injection safe), Users, Roles, Activity log, Profile. Dark/light theme.

## Quotes & invoices (Admin → Billing)

Clients → **Quotes** → **Invoices** → **Payments**. Permissions: `billing.view`, `billing.manage`, `billing.delete`,
`settings.manage` (the starter **Accountant** role has them all).

- Turn a won enquiry into a client and draft quote with **Create client & quote**.
- Line items, discount, tax and any ISO currency, with totals calculated in integer cents (no rounding drift).
  Quotes are numbered `Q-2026-0001`; invoices `ZRON-YYYYMMDDHHMMSSmmm`, the creation time in Pakistan time to the
  millisecond (e.g. `ZRON-20261016143052123`; timezone set by `ZAIRON_TIMEZONE`).
- **Send** emails the client a secure, unguessable link to a printable page (Print / Save as PDF). Clients can
  **accept or decline quotes online**, and the team is emailed. An accepted quote converts into an invoice in one
  click.
- Record full or partial payments (status updates automatically). Overdue invoices are flagged, and
  **automatic weekly reminders** go out when enabled in Billing settings.
- Company details, default currency, tax, due days, bank/payment instructions and terms are under **Billing settings**.
- **PDFs** (`app/Support/DocumentPdf.php`, template `resources/views/pdf/document.blade.php`, dompdf): download or
  preview any quote/invoice from the admin, and clients get a "Download PDF" button. Every send and reminder attaches
  the PDF and saves that exact copy under `storage/app/private/documents/` ("Copy emailed to the client" in the admin).

## Accounting (Admin → Accounting)

Double-entry books on an accrual basis, in one **base currency** (default **PKR**, set in Accounting settings and
fixed once anything is posted) with a **July–June** financial year. Permissions: `accounting.view`,
`accounting.manage`, `accounting.close` (the starter **Accountant** role has them all; Manager can view).

- **Automatic postings** (`app/Support/Ledger.php`): sending an invoice posts receivable / revenue / sales tax;
  payments post bank / receivable with any **exchange gain or loss**; expenses post category / payable, then
  payable / bank when paid. Postings follow their document; only changed entries are rewritten, inside the same
  transaction, so a closed period or missing rate rolls the whole action back.
- **Any ISO currency** (`config/currencies.php`). Foreign-currency invoices need an exchange rate before sending;
  payments record the rate actually received.
- **Expenses & vendors** with private receipt uploads (`storage/app/private/receipts`).
- **Journal** with manual entries (opening balances, salaries, corrections) that must balance before posting.
- **Reports** (`app/Support/Reports.php`): general ledger, trial balance, profit & loss, balance sheet,
  receivables/payables aging, all printable and exportable to CSV. Income/expense accounts reset each financial
  year and earlier profit shows inside Retained earnings.
- **Period lock**: close the books up to a date; nothing on or before it can change until reopened.
- Starter chart of accounts: `config/accounting.php`. After installing on existing data run
  `php artisan zairon:ledger-sync` once.

Phases 2 (bank reconciliation), 3 (tax) and 4 (credit notes, recurring invoices…) are specified in
`REQUIREMENTS.md` §9.

## Background processes

| Process | Command | Why |
| --- | --- | --- |
| Queue worker | `php artisan queue:work` | Sends every email |
| Scheduler | `php artisan schedule:work` (locally) or a cron entry `* * * * * php artisan schedule:run` | Daily overdue-invoice reminders, token clean-up |

## Email

All emails are queued, so **run a worker**: `php artisan queue:work` (keep it alive with Supervisor/systemd in
production). Locally `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`; set the SMTP lines in `.env`
to send real ones.

| Email | Sent to | When |
| --- | --- | --- |
| New project enquiry | `ZAIRON_INBOX`, or every active admin who can view enquiries | Contact / service-page form |
| "We received your message" | The visitor | Contact / service-page form |
| Invitation | New admin user | Invited from Users |
| Password reset | Admin user | "Forgot password" on the sign-in page |

Templates live in `resources/views/mail` and share one branded layout (`resources/views/components/mail-layout.blade.php`).

## Security

- **CSP** with a per-request nonce: scripts only from this origin, no inline scripts or `eval`, no framing
  (`frame-ancestors 'none'`). Plus `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`,
  COOP/CORP, and HSTS on HTTPS in production.
- **Forms**: CSRF-protected, rate-limited (5/min, 30/day per IP), honeypot spam trap, strict server-side
  validation that mirrors the client checks (services and budgets limited to known values).
- **Uploads**: PDF/DOC/DOCX up to 10 MB, checked by file content, stored on the private disk
  (`storage/app/private/briefs`) under random names, never web-accessible and never emailed.
- **Emails** use a fixed subject; visitor input is HTML-escaped and never parsed as Markdown.
- Newsletter gives the same response for new and existing emails (no address enumeration).
- Fonts are self-hosted (no third-party requests). Canonical URLs come from `APP_URL`, not the request host.

## Before launch

1. Set `APP_URL` to the real domain, plus `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
2. Fill in the placeholders in `resources/content/*.json`: `[CLIENT LOGO]`, `[XX]` stats (use numbers, e.g.
   `"value": 40`, and they animate), `[CLIENT NAME]` case studies, testimonials, founder photos, `[OFFICE ADDRESS]`, `[DATE]`.
3. Check the contact details in `site.json`: email `zairon@sales.com` (probably meant `sales@zairon.com`) and
   phone `+92 314567789` (one digit short).
4. Add social, founder and Privacy/Terms URLs in `site.json`. Links with an empty URL stay hidden.
5. Add `public/og-image.png` (1200×630) for link previews. It is picked up automatically.
6. Set `ZAIRON_INBOX` to receive enquiry emails and configure `MAIL_*`.
7. PHP upload limits: `upload_max_filesize = 12M` and `post_max_size = 16M`, so 10 MB briefs fit.
