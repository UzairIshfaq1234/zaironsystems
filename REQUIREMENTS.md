# ZAIRON — Software Requirements Specification

| | |
| --- | --- |
| **Product** | ZAIRON company website, admin portal, billing and accounting system |
| **Company** | ZAIRON — *Ideas, To Reality.* |
| **Document version** | 1.1 (adds accounting phase 1: §9, D-10 to D-15) |
| **Date** | 16 October 2026 |
| **Owner** | Muhammad Uzair Ishfaq, Founder & CEO |
| **Status** | Baseline. Describes the system as built and the agreed next steps |

---

## Contents

1. [Introduction](#1-introduction)
2. [Business context](#2-business-context)
3. [Users and roles](#3-users-and-roles)
4. [System overview](#4-system-overview)
5. [Functional requirements — public website](#5-functional-requirements--public-website)
6. [Functional requirements — lead capture](#6-functional-requirements--lead-capture)
7. [Functional requirements — admin portal](#7-functional-requirements--admin-portal)
8. [Functional requirements — billing](#8-functional-requirements--billing)
9. [Functional requirements — accounting](#9-functional-requirements--accounting)
10. [Functional requirements — email](#10-functional-requirements--email)
11. [Non-functional requirements](#11-non-functional-requirements)
12. [Data requirements](#12-data-requirements)
13. [Configuration, operations and deployment](#13-configuration-operations-and-deployment)
14. [Decisions, constraints and exclusions](#14-decisions-constraints-and-exclusions)
15. [Open items before launch](#15-open-items-before-launch)
16. [Backlog and future requirements](#16-backlog-and-future-requirements)
17. [Verification](#17-verification)
18. [Appendix A — permission matrix](#appendix-a--permission-matrix)
19. [Appendix B — glossary](#appendix-b--glossary)

---

## 1. Introduction

### 1.1 Purpose
This document records every requirement agreed for ZAIRON's digital platform up to the date above. It is the
reference for future development: new work should state which requirements it adds or changes, and this
document should be updated in the same change.

### 1.2 Scope
The platform has four parts:

1. **Public website**: marketing site presenting ZAIRON's services and capturing project enquiries.
2. **Admin portal** (`/admin`): a separate, access-controlled application for managing enquiries, subscribers,
   users, roles, billing and accounting.
3. **Billing**: clients, quotes, invoices, payments and a client-facing document page.
4. **Accounting**: double-entry books (chart of accounts, journal, expenses, vendors) and financial reports,
   delivered in phases (§9).

### 1.3 Requirement conventions
- Every requirement has an ID: `FR-<AREA>-<NN>` (functional) or `NFR-<AREA>-<NN>` (non-functional).
- **Priority** (MoSCoW): **M** = Must, **S** = Should, **C** = Could.
- **Status**: ✅ **Implemented** (built and tested), 🟡 **Partial**, ⬜ **Planned**.
- "Must not" statements are binding constraints. Several record explicit decisions by the owner (see §14).

### 1.4 References
| Document | Location |
| --- | --- |
| Developer guide (setup, structure, operations) | `README.md` |
| Permission catalog | `config/permissions.php` |
| Website copy and content | `resources/content/*.json` |
| Automated tests | `tests/Feature/*` |

---

## 2. Business context

### 2.1 Company
ZAIRON is a software engineering company founded by **Muhammad Uzair Ishfaq** (Founder & CEO) and
**Khadija Uzair** (Co-Founder). The name grows from *Uzair*, "the helper". The logo is a "Z" made of two
halves meeting at a single node.

### 2.2 Brand identity
| Element | Value |
| --- | --- |
| Slogan | Ideas, To Reality. |
| Primary colour | Maroon `#800000` |
| Supporting colours | Deep maroon `#5C0000`, Glow `#B3142C`, Night `#0B0808`, Blush `#F2D9D9` |
| Typefaces | Sora (headings), Manrope (body), JetBrains Mono (code/labels) |
| Visual style | Dark, glass-effect surfaces, maroon glows, subtle motion |

### 2.3 Service catalogue
Eleven services in four groups ("pillars"). Each service has its own page (FR-WEB-04).

| Pillar | Services |
| --- | --- |
| **01 Build** | Software Engineering · Web Development · App Development |
| **02 Intelligence** | AI & Computer Vision · Data Engineering & Extraction |
| **03 Automate** | Automation Engineering · Mobile Automation · API & Platform Integration |
| **04 Run & Protect** | Quality Engineering · DevOps & Cloud · Cyber Security |

### 2.4 Engagement options ("How would you like to start?")
| Option | Typical duration | Notes |
| --- | --- | --- |
| Discovery sprint | 1–2 weeks | Scope, architecture and a fixed, itemised quote |
| Build & launch | Typically 6–12 weeks | Marked "Most popular" |
| Ongoing partnership | Monthly | Support, maintenance, new features |

### 2.5 Business goals
- G1: Present ZAIRON as a premium, trustworthy engineering partner.
- G2: Turn visitors into qualified project enquiries.
- G3: Let the team respond to, track and convert enquiries efficiently.
- G4: Quote, invoice and collect payment professionally from one system.
- G5: Keep the team's access controlled and auditable.

---

## 3. Users and roles

### 3.1 External users
| User | Description |
| --- | --- |
| **Visitor** | Anyone browsing the public website |
| **Prospect** | A visitor who submits an enquiry or subscribes |
| **Client** | A company/person ZAIRON quotes or invoices; views documents via a secure link |

### 3.2 Internal users (admin portal)
Access is role-based. A role is a named set of permissions (Appendix A). A user may hold several roles.

| Role | Purpose | Editable |
| --- | --- | --- |
| **Super Admin** | Full access to everything | System role: cannot be edited or deleted |
| **Manager** | Runs day-to-day operations | Yes |
| **Sales** | Follows up on enquiries | Yes |
| **Marketing** | Manages the newsletter audience | Yes |
| **Accountant** | Quotes, invoices, payments, billing settings, expenses, the books and accounting settings | Yes |

Super Admins can create further roles with any combination of permissions.

---

## 4. System overview

### 4.1 Architecture
```
Browser ──► Laravel 13 (PHP)
             ├─ Public website shell (Blade) ──► React 19 SPA (React Router)
             ├─ Public form API  /api/contact, /api/newsletter
             ├─ Client documents /billing/{quote|invoice}/{token}   (server-rendered)
             ├─ Admin portal shell /admin ──► separate React 19 SPA
             ├─ Admin JSON API   /admin/api/*   (session auth + permissions)
             ├─ Queue worker  → emails
             └─ Scheduler     → overdue reminders, token clean-up
Database: SQLite (development) · MySQL/PostgreSQL (production, recommended)
```

### 4.2 Technology stack (mandatory)
| Layer | Technology |
| --- | --- |
| Backend | Laravel 13, PHP ≥ 8.3 |
| Frontend | React 19 + React Router 7, plain SPA. **Must not use Inertia** |
| Build | Vite 8 with `laravel-vite-plugin` |
| Styling | Hand-written CSS design system with tokens. **Tailwind is not used** |
| Fonts | Self-hosted via `@fontsource` (no third-party font requests) |
| 3D | Three.js, lazy-loaded, About page only |
| Auth / RBAC | Laravel session auth + in-house roles & permissions |

The public website and admin portal are **separate bundles**. Visitors never download admin code.

---

## 5. Functional requirements — public website

| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-WEB-01 | The site provides these pages: Home, Services, 11 service pages, Work, About, Careers, Insights, Contact, Privacy Policy, Terms of Service and a 404 page. | M | ✅ |
| FR-WEB-02 | The header shows the logo, main navigation (Services, Work, About, Insights, Careers, Contact) and a **Start a project** button. The active page is indicated. | M | ✅ |
| FR-WEB-03 | **Services mega menu**: hovering or clicking "Services" opens a panel listing all 11 services grouped by pillar, each with icon and tagline, plus a "Book a free call" card and "Explore all services" link. Keyboard accessible (Escape closes, focus returns). On mobile it becomes an expandable section inside the menu. | M | ✅ |
| FR-WEB-04 | Each service has its own page (`/services/{slug}`) with: hero (breadcrumb, pillar label, headline, lead), a visual (hologram, or vision preview for AI), capabilities (6), deliverables, "ideal for" list, 4-step process, tools, a **Start a project** section, FAQs, related services and a closing call to action. | M | ✅ |
| FR-WEB-05 | Service pages use the wording **"Start a project"** and **"Contact us"**. The word "enroll" **must not** be used anywhere. | M | ✅ |
| FR-WEB-06 | The Start a project section on a service page shows the three engagement options (§2.4) as selectable cards; choosing one pre-selects it in the form below. | M | ✅ |
| FR-WEB-07 | Home page: hero ("Ideas, engineered into reality."), client logo marquee, product preview window, services grid, 5-step process, "Why ZAIRON" with 4 stats, selected work, technology list, testimonials, FAQ and closing call to action. | M | ✅ |
| FR-WEB-08 | The Home page hero **must not** contain a 3D scene (owner decision). | M | ✅ |
| FR-WEB-09 | Work page lists case studies with category filters and "Load more". | M | ✅ |
| FR-WEB-10 | Insights page lists articles with a featured article, category filters and pagination. | M | ✅ |
| FR-WEB-11 | Careers page shows perks and open roles with team filters; **Apply now** opens an email to the company address with the role in the subject. | M | ✅ |
| FR-WEB-12 | About page shows the story, mission, vision, values, founders and an interactive 3D logo (static logo fallback when WebGL is unavailable). | S | ✅ |
| FR-WEB-13 | Contact page shows contact methods (email, phone/WhatsApp, address), the project form (FR-LEAD-01), "what happens next" steps, a map placeholder and FAQs. A `?service=<slug>` link pre-selects that service. | M | ✅ |
| FR-WEB-14 | Privacy Policy and Terms of Service pages exist and are linked from the footer and the form's consent checkbox. | M | ✅ |
| FR-WEB-15 | Footer: brand summary, newsletter form, services/company/resources/contact columns, social links, copyright. Links with no destination are hidden rather than pointing to `#`. | M | ✅ |
| FR-WEB-16 | Website copy (services, FAQs, case studies, articles, roles, testimonials, stats, legal text) lives in version-controlled JSON files under `resources/content/`, read by both the website and the server. | M | ✅ |
| FR-WEB-17 | Each page has its own server-rendered title, description, canonical URL, Open Graph/Twitter tags and structured data (Organization, WebSite, Service, FAQPage, BreadcrumbList as relevant). Navigating within the site updates these tags. | M | ✅ |
| FR-WEB-18 | `sitemap.xml` and `robots.txt` are generated automatically; `robots.txt` disallows `/api/` and `/admin`. | M | ✅ |
| FR-WEB-19 | Unknown URLs return a real HTTP 404 status with the branded 404 page. | M | ✅ |
| FR-WEB-20 | Visual effects: section reveal animations, anagram/scramble effect on highlighted words and section labels, 3D tilt with holographic sheen on cards, scroll progress bar, animated glowing borders on featured cards, CSS hologram on service pages. | S | ✅ |
| FR-WEB-21 | All motion respects the visitor's "reduce motion" setting (still versions shown). | M | ✅ |

---

## 6. Functional requirements — lead capture

### 6.1 Project enquiry form
Used on the Contact page and, preset to the service, on every service page.

| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-LEAD-01 | Fields and rules (validated in the browser **and** on the server): | M | ✅ |
| | • Full name: required, ≤ 120 chars | | |
| | • Work email: required, valid (RFC), ≤ 190 chars | | |
| | • Company: required, ≤ 160 chars | | |
| | • Phone: optional, ≤ 40 chars, digits and `+ - ( ) .` only | | |
| | • What do you need: optional multi-select from 10 fixed options (contact page) | | |
| | • How would you like to start: optional, one of the 3 engagement options (service pages) | | |
| | • Estimated budget: optional, one of `< $5k`, `$5k – $15k`, `$15k – $50k`, `$50k+`, `Not sure yet` | | |
| | • Project details: required, 10–5000 chars | | |
| | • Attachment: optional, PDF/DOC/DOCX, ≤ 10 MB, checked by file content | | |
| | • Consent to the privacy policy: required | | |
| FR-LEAD-02 | Submissions record the originating service page and chosen engagement option, if any. | M | ✅ |
| FR-LEAD-03 | On success the form is replaced by a confirmation message; the visitor and team are emailed (FR-MAIL-01/02). | M | ✅ |
| FR-LEAD-04 | Errors are shown next to each field; server-side problems (expired session, rate limit, oversized file) show a clear message. | M | ✅ |

### 6.2 Newsletter
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-LEAD-05 | Visitors can subscribe with an email address (footer and Insights page). Emails are stored lower-cased and unique. | M | ✅ |
| FR-LEAD-06 | The response is identical for new and existing subscribers, so the form cannot reveal who is subscribed. | M | ✅ |
| FR-LEAD-07 | Sending newsletters to subscribers. | S | ⬜ (BL-02) |

---

## 7. Functional requirements — admin portal

The admin portal is at `/admin`. It is a separate application with its own design (sidebar layout,
dark and light themes) and is never indexed by search engines.

### 7.1 Authentication
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-AUTH-01 | Sign in with email and password, with an optional "keep me signed in". | M | ✅ |
| FR-AUTH-02 | At most **5 sign-in attempts per minute** per email + IP address. | M | ✅ |
| FR-AUTH-03 | Wrong password, unknown email and deactivated account all return the **same** error message. | M | ✅ |
| FR-AUTH-04 | Forgot password: an emailed reset link valid for **60 minutes**. The response never reveals whether the email has an account. | M | ✅ |
| FR-AUTH-05 | Password rule: at least **10 characters** with upper- and lower-case letters and a number. | M | ✅ |
| FR-AUTH-06 | A deactivated user is signed out on their next request. | M | ✅ |
| FR-AUTH-07 | Changing your password signs out your other devices. | S | ✅ |
| FR-AUTH-08 | Two-factor authentication for admin users. | S | ⬜ (BL-03) |

### 7.2 Roles and permissions
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-RBAC-01 | A **Super Admin** exists from setup (created by command; see §13.3). | M | ✅ |
| FR-RBAC-02 | Super Admins create roles, tick permissions from the catalog (Appendix A, grouped) and assign roles to users. | M | ✅ |
| FR-RBAC-03 | Every admin API action is checked on the server against the user's permissions. Hiding buttons in the interface is cosmetic only. | M | ✅ |
| FR-RBAC-04 | The Super Admin role passes every check and **cannot be edited or deleted**. | M | ✅ |
| FR-RBAC-05 | Privilege-escalation rules: (a) nobody can grant permissions they don't hold; (b) only a Super Admin can assign the Super Admin role or edit/delete a Super Admin; (c) nobody can change their own roles, deactivate or delete themselves; (d) the **last Super Admin** cannot be removed, demoted or deactivated. | M | ✅ |
| FR-RBAC-06 | The sidebar shows only the sections a user has permission for. | M | ✅ |

### 7.3 Users
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-USR-01 | List users with search: name, email, roles, status (Active / Invited / Disabled), last sign-in. | M | ✅ |
| FR-USR-02 | **Invite** a user (name, email, roles). They receive an email link valid for **72 hours** to set their own password. Admins never know or set a user's password. | M | ✅ |
| FR-USR-03 | Edit name, email, roles and active status; resend a pending invitation; delete a user. | M | ✅ |
| FR-USR-04 | Profile page: every user can edit their own name/email and change their password. | M | ✅ |

### 7.4 Overview dashboard
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-DASH-01 | Stat tiles: enquiries this week (with change vs last week), awaiting reply, projects won, subscribers. | M | ✅ |
| FR-DASH-02 | Billing tiles (for billing users): outstanding, overdue, paid this month, open quotes. Amounts are grouped by currency and **never summed across currencies**. | M | ✅ |
| FR-DASH-03 | Charts: enquiries per day (last 30 days, with hover and keyboard read-out), enquiries by status, top services. | M | ✅ |
| FR-DASH-04 | Latest enquiries and recent activity lists. | M | ✅ |

### 7.5 Enquiries
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ENQ-01 | List with search (name, email, company) and filters (status, service), paginated. | M | ✅ |
| FR-ENQ-02 | Status pipeline: **New → Contacted → In progress → Won / Lost**. | M | ✅ |
| FR-ENQ-03 | Detail view: all submitted fields, internal notes (team only), contact details, request details, technical data (IP, browser), reply-by-email button. | M | ✅ |
| FR-ENQ-04 | Attachments download securely (permission required; the storage path is never exposed). | M | ✅ |
| FR-ENQ-05 | **Create client & quote**: turns an enquiry into a client (or reuses an existing client with the same email) and opens a new quote. | M | ✅ |
| FR-ENQ-06 | Delete an enquiry (removes its attachment). | M | ✅ |

### 7.6 Subscribers
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-SUB-01 | List and search subscribers; remove a subscriber. | M | ✅ |
| FR-SUB-02 | Export as CSV. Cells beginning with `= + - @` are neutralised against spreadsheet formula injection. | M | ✅ |

### 7.7 Activity log
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACT-01 | Record sign-ins, failed sign-ins, sign-outs, password resets/changes, user/role changes, enquiry status/notes/downloads/deletions, subscriber removals/exports, and all billing actions, with user, time and IP. | M | ✅ |
| FR-ACT-02 | Searchable, paginated activity log page. | M | ✅ |

### 7.8 Portal experience
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-UX-01 | Dark and light themes; choice remembered per browser. | S | ✅ |
| FR-UX-02 | Fully responsive: collapsible sidebar on desktop, slide-out drawer on mobile, tables become stacked cards on phones. | M | ✅ |
| FR-UX-03 | Toast notifications for results; confirmation dialogs for destructive actions. | M | ✅ |

---

## 8. Functional requirements — billing

### 8.1 Clients
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-BILL-01 | Clients have: contact name, company, email, phone, billing address, tax number, internal notes and an optional source enquiry. | M | ✅ |
| FR-BILL-02 | List/search clients; client page shows details plus all their quotes and invoices. A client with documents cannot be deleted. | M | ✅ |

### 8.2 Quotes and invoices (common)
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-BILL-03 | Line items (1–100): description, quantity (≤ 2 decimals), unit price (≤ 2 decimals). Document-level discount (amount, applied before tax) and tax rate (%). | M | ✅ |
| FR-BILL-04 | All money is stored and calculated in **integer cents**. The editor previews totals live, and they must match the server exactly. A discount can never make a total negative. | M | ✅ |
| FR-BILL-05 | **Every ISO 4217 currency** (`config/currencies.php`). Amounts show 2 decimals, except currencies without minor units (e.g. JPY), identically in the portal, emails and client pages. Invoices in a currency other than the base currency (FR-ACC-03) carry an **exchange rate**, required before sending. | M | ✅ |
| FR-PORTAL-01 | **Top-bar search** (Ctrl/⌘K): grouped results across enquiries, invoices, quotes, clients, expenses, vendors, accounts and team; each group only appears for users who may open that area. Empty query lists the pages the user can reach. | M | ✅ |
| FR-PORTAL-02 | **Notifications** (bell): each user's own alerts for new enquiries, quotes accepted/declined, payments, first client views and overdue reminders, with unread count and mark-all-read. | M | ✅ |
| FR-PORTAL-03 | **Sidebar**: collapsible to an icon rail that remembers its state; thin, themed scrollbars; the ZAIRON mark stays centred in both states. | S | ✅ |
| FR-BILL-06 | Automatic sequential numbering: quotations **`Q-ZAIRON-001`, `Q-ZAIRON-002`…** and invoices **`ZRON-001`, `ZRON-002`…**, each counting separately from 001. Older documents keep their numbers and do not affect the sequence. | M | ✅ |
| FR-BILL-24 | **Terms are not typed per document.** Standard quotation terms, quotation note and invoice terms are set once in Billing settings and printed on every document. A quotation's PDF always fits on **one A4 page** (up to about 50 line items; the text shrinks as needed). | M | ✅ |
| FR-BILL-07 | Documents are created as **drafts**. Drafts are never visible to clients. | M | ✅ |
| FR-BILL-08 | **Send** emails the client (or another address) with an optional personal message and a secure link (FR-BILL-15). | M | ✅ |
| FR-BILL-09 | Admin document view: preview, status, timeline (created, sent, viewed by client, accepted/paid), copy client link, open client view. | M | ✅ |

### 8.3 Quotes
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-BILL-10 | Statuses: Draft → Sent → Accepted / Declined → Invoiced. "Expired" is shown when a sent quote passes its valid-until date. | M | ✅ |
| FR-BILL-11 | Clients can **accept or decline online** from the link. Only the first answer counts, and the team is emailed. Admins can also record the answer manually. | M | ✅ |
| FR-BILL-12 | **Convert to invoice** in one click (copies client, items, tax, discount, notes, terms). A quote can be invoiced only once; declined quotes cannot be. | M | ✅ |

### 8.4 Invoices and payments
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-BILL-13 | Statuses: Draft, Sent, Partly paid, Paid, Void. "Overdue" is shown when a sent/partly paid invoice passes its due date. | M | ✅ |
| FR-BILL-14 | Record payments (amount, date ≤ today, method, reference, the bank/cash account it was deposited to, and, for foreign-currency invoices, the rate actually received). Partial payments are supported; a payment cannot exceed the balance. Status and balance update automatically; removing a payment recalculates them. Methods: Bank transfer, Card, Cash, Cheque, PayPal, Stripe, Other. | M | ✅ |
| FR-BILL-15 | Only drafts can be deleted; sent invoices are **voided** (kept on record). Invoices with payments, and paid or void invoices, cannot be edited. | M | ✅ |
| FR-BILL-16 | **Overdue reminders**: a manual "Send reminder" button, plus an automatic daily job (09:00) that emails each overdue invoice at most once every 7 days, when enabled in settings. | M | ✅ |

### 8.5 Client-facing document page
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-BILL-17 | Reached only via an unguessable 48-character token link (`/billing/{quote|invoice}/{token}`). Not indexed, not cached, rate-limited. | M | ✅ |
| FR-BILL-18 | A4-style branded document showing company details, client, dates, items, totals, payments/balance, payment instructions, notes and terms, with a Paid/Void stamp where relevant. | M | ✅ |
| FR-BILL-19 | **Print / Save as PDF** from the browser with a clean print layout. | M | ✅ |
| FR-BILL-20 | The first client view is recorded (admin previews don't count). | S | ✅ |
| FR-BILL-21 | **PDF**: every quote and invoice can be downloaded or previewed as a branded A4 PDF (admin, list and detail pages; and a "Download PDF" button for the client). Sending or reminding **attaches the PDF** to the email and **saves that exact copy** on the private disk (`storage/app/private/documents/{quotes|invoices}/{number}/`), downloadable afterwards as "Copy emailed to the client". | M | ✅ |
| FR-BILL-22 | Online card payment ("Pay now") on invoices. | C | ⬜ (BL-07) |

### 8.6 Billing settings
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-BILL-23 | Editable: company name, billing email (reply-to), phone, address, tax/NTN number, default currency, default tax rate, invoice due days (default 14), quote validity days (default 30), payment instructions (bank details), default terms, automatic reminders on/off. | M | ✅ |

---

## 9. Functional requirements — accounting

Double-entry accounting, delivered in four phases. **Phase 1 (accounting core) is implemented.** Phases 2–4
are specified here so the design stays consistent; their status is ⬜ until built.

| Phase | Scope | Status |
| --- | --- | --- |
| 1. Accounting core | Chart of accounts, automatic journal, expenses and vendors, all currencies with exchange rates, financial reports, period lock | ✅ |
| 2. Bank reconciliation | Bank statement upload (ZAIRON Excel template), automatic matching, reconciliation report | ⬜ |
| 3. Tax | Fully configurable tax types and rates, withholding tax, tax reports per period | ⬜ |
| 4. Extras | Credit notes, recurring invoices, cash-flow statement, then projects and timesheets | ⬜ |

### 9.1 Books and settings
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-01 | The books use **double entry**: every journal entry has at least two lines and total debits equal total credits, checked before anything is saved. Amounts are stored in integer hundredths of the base currency. | M | ✅ |
| FR-ACC-02 | **Accrual basis**: income is recognised when an invoice is sent, expenses on the bill date; receivables and payables track what is still unpaid. | M | ✅ |
| FR-ACC-03 | **Base currency** of the books is a setting (default **PKR**, D-10). It can only change while the journal is empty. | M | ✅ |
| FR-ACC-04 | **Financial year** start month is a setting (default **July**, so July–June, D-11). Reports offer "this financial year to date", "last financial year", quarter and month presets, labelled e.g. "FY 2026–27". | M | ✅ |
| FR-ACC-05 | **Period lock**: the books can be closed up to a date. Nothing dated on or before it can be added, changed or deleted, whether by an invoice, payment, expense or manual entry, until the period is reopened. Closing and reopening are logged. | M | ✅ |

### 9.2 Chart of accounts
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-06 | A starter chart of accounts is created on install (`config/accounting.php`): cash, bank, receivables, payables, sales tax, withholding tax, capital, drawings, retained earnings, revenue, exchange gain/loss and common expense categories (salaries, rent, software, hosting, marketing, contractors, bank charges…). | M | ✅ |
| FR-ACC-07 | Accounts have a code (unique), name, type (asset, liability, equity, income, expense), optional "holds money" flag (bank or cash) and description, and can be deactivated. | M | ✅ |
| FR-ACC-08 | **System accounts** (the ones automatic postings use) can be renamed but not retyped, deactivated or deleted. An account with postings cannot be deleted or change type. | M | ✅ |
| FR-ACC-09 | The chart shows each account's balance today, grouped by type; each account opens its **general ledger** (FR-ACC-20). | M | ✅ |

### 9.3 Automatic postings
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-10 | Sending an invoice posts: Dr Accounts receivable / Cr Revenue and Cr Sales tax payable, at the invoice's exchange rate. Editing a sent invoice updates the entry; voiding removes it. Drafts are never posted. | M | ✅ |
| FR-ACC-11 | Recording a payment posts: Dr the chosen bank/cash account (at the rate received) / Cr Accounts receivable (at the invoice rate). The difference is booked to **Exchange gain / loss**. The payment that settles an invoice clears the exact remaining receivable, so rounding never leaves cents behind. | M | ✅ |
| FR-ACC-12 | Postings are kept in step with their document automatically. Only entries that actually change are rewritten, and every change runs in one database transaction with the document change. A missing exchange rate or a closed period rolls the whole action back with a clear message. | M | ✅ |
| FR-ACC-13 | Automatic entries cannot be edited or deleted by hand. The entry links back to its invoice or expense, which is where it changes. | M | ✅ |
| FR-ACC-14 | `php artisan zairon:ledger-sync` re-posts every invoice and expense (safe to repeat), e.g. after installing accounting on existing data. | S | ✅ |

### 9.4 Expenses and vendors
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-15 | Vendors: name, email, phone, tax/NTN number, notes. A vendor with expenses cannot be deleted. Vendors can be added from inside the expense form. | M | ✅ |
| FR-ACC-16 | Expenses (`EXP-YYYY-0001`): description, vendor, category (an expense account, or an asset account for things bought and kept), bill date, due date, reference, any currency with exchange rate, amount, and whether and when it was paid and from which bank/cash account. | M | ✅ |
| FR-ACC-17 | Posting: on the bill date Dr category / Cr Accounts payable; when paid, Dr Accounts payable / Cr the bank/cash account. Status shows Paid, Unpaid or Overdue (past the due date, or the bill date if no due date). | M | ✅ |
| FR-ACC-18 | **Receipts**: optional PDF/JPG/PNG/WebP up to 5 MB, stored privately under a random name, downloadable only by signed-in users with accounting access (downloads are logged). | M | ✅ |

### 9.5 Journal and reports
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-19 | **Journal**: every entry (`JE-YYYY-0001`) with date, description, source (invoice, expense or manual) and lines; searchable and filterable. **Manual entries** (opening balances, capital, salaries, depreciation, corrections) are created in an editor that shows the running difference and only posts when balanced. | M | ✅ |
| FR-ACC-20 | **General ledger** per account for any period: opening balance, each posting with a running balance, closing balance. Income/expense accounts open at zero each financial year. | M | ✅ |
| FR-ACC-21 | **Trial balance** as of any date, with a balanced / out-of-balance indicator. | M | ✅ |
| FR-ACC-22 | **Profit & loss** for any period: income and expense accounts, totals, net profit or loss. | M | ✅ |
| FR-ACC-23 | **Balance sheet** as of any date: assets, liabilities, equity, plus profit for the current financial year; earlier years' profit is shown inside Retained earnings. It must show Assets = Liabilities + Equity. | M | ✅ |
| FR-ACC-24 | **Receivables and payables aging** (today): unpaid invoices / expenses by days overdue in buckets: not yet due, 1–30, 31–60, 61–90, over 90, in the original currency and the base currency. | M | ✅ |
| FR-ACC-25 | Every report can be **printed** (clean print layout) and **exported to CSV** (spreadsheet-formula safe). Report lines link to the account's ledger. | M | ✅ |
| FR-ACC-26 | **Accounting overview**: cash & bank, receivable, payable, profit for the financial year, income vs expenses by month (chart with legend, tooltips and a screen-reader table), bank/cash balances, sales tax payable and a **books check** (trial balance, balance sheet, missing exchange rates, period lock). | M | ✅ |

### 9.6 Phase 2 — bank reconciliation (planned)
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-30 | Statements are uploaded as an **Excel file in ZAIRON's own template** (D-14), one per bank account and period. Columns: Date, Description, Reference, Money out, Money in, Balance. | M | ⬜ |
| FR-ACC-31 | A **"Download template"** button provides the template with a few sample (dummy) rows and instructions. | M | ⬜ |
| FR-ACC-32 | Upload validates every row (dates, amounts, one of money in/out, running balance) and shows a preview with errors before importing. Re-uploading the same lines never imports them twice. | M | ⬜ |
| FR-ACC-33 | **Automatic matching** of statement lines to recorded payments and expenses by amount, date window (± a few days) and reference/invoice number; manual match and unmatch. | M | ⬜ |
| FR-ACC-34 | Three outcomes: **matched**; **in the bank but not in the books** (e.g. bank charges, an unrecorded payment), which can be turned into an expense or payment in one click; **in the books but not in the bank** (e.g. not yet cleared, recorded twice). | M | ⬜ |
| FR-ACC-35 | **Reconciliation report**: statement closing balance vs book balance, with the difference explained; a reconciled period can then be closed (FR-ACC-05). | M | ⬜ |

### 9.7 Phase 3 — tax (planned)
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-40 | **Fully configurable tax types** (D-13): name, rate, whether it is collected on sales or paid on purchases, the ledger account it posts to, and active dates. Examples: provincial sales tax on services, 0% export of services, withholding tax. | M | ⬜ |
| FR-ACC-41 | Invoice and expense lines can carry tax types, replacing the single document tax rate. | M | ⬜ |
| FR-ACC-42 | Withholding tax deducted by clients is recorded with the payment and tracked as a tax credit. | M | ⬜ |
| FR-ACC-43 | Tax report per month/quarter: tax collected vs paid by type, ready for the accountant's filing. | M | ⬜ |

### 9.8 Phase 4 — extras (planned)
| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-ACC-50 | Credit notes and refunds against sent invoices. | S | ⬜ |
| FR-ACC-51 | Recurring invoices (e.g. monthly retainers). | S | ⬜ |
| FR-ACC-52 | Cash-flow statement. | S | ⬜ |
| FR-ACC-53 | Projects and timesheets, with invoicing of logged time. | C | ⬜ |

---

### 9.9 People, attendance, leave, reviews and payroll
Employees are on the payroll. Each has a separate **employee portal** login (`/portal`), and the admin manages them under **People** and **Payroll**. The default rules are in `config/hr.php` and can be changed there.

| ID | Requirement | Pri. | Status |
| --- | --- | --- | --- |
| FR-HR-01 | Employee records: name, code (EMP-001…), email, phone, joined date, status (active or has left), basic salary, bank details and shift times. Department and job title are picked from managed lists (People → Departments & allowances), each maintained on its own; an entry in use can be renamed but not deleted. A person who has left loses portal access. | M | ✅ |
| FR-HR-02 | **Attendance:** employees check in and out from the portal. A check-in is late when it is more than 15 minutes after the shift start. Managers can set or correct any day's times, with a note. A monthly report shows days in, late days and hours. | M | ✅ |
| FR-HR-03 | **Leave:** annual (14 days, up to 30 carried over from a year the person was employed), sick (8) and casual (10) leave, plus unpaid leave without a limit. Employees request leave in the portal and can cancel a pending request. Managers approve or reject; approval fails if the balance is not enough. Requests may not overlap, and a single request is at most 90 days. | M | ✅ |
| FR-HR-04 | **Performance reviews:** a manager writes a review per period with a 1–5 score, strengths, improvements and goals. The employee reads it in the portal and can reply, which marks it as read. | M | ✅ |
| FR-HR-05 | **Employee portal:** a separate sign-in (own session and password, rate-limited, same message for wrong details). Invitations and password resets by email, valid 72 hours. Employees see only their own records. | M | ✅ |
| FR-PAY-01 | **Monthly payroll run:** one salary slip per active employee. Gross is basic salary plus each allowance (several allowance types per person, each with its own amount), less unpaid leave at salary ÷ 30 per day. The slip shows every line. Income tax is withheld from the annualised gross using the bands in `config/hr.php`. Net pay = gross − tax. | M | ✅ |
| FR-PAY-02 | A run can be rebuilt while it is a draft. **Posting** a run locks it and records it in the books: salary expense = gross, with net pay and withheld tax as liabilities. | M | ✅ |
| FR-PAY-03 | Salary slips are one-page PDFs. Admins with payroll access can download any slip. Employees can download their own, and only once the month is posted. | M | ✅ |
| FR-PAY-04 | Salary figures and bank details are visible only to people with `payroll.view`. Managers do not have it by default. | M | ✅ |

**Placeholder values to confirm:** the income-tax bands in `config/hr.php` are placeholders, not the current official schedule. Have the accountant confirm them before the first payroll is posted (OI-14). Leave allowances and the 30-day salary convention are also defaults that the owner should confirm (OI-15).
---

## 10. Functional requirements — email

All emails are branded HTML, **queued** (sent by a background worker), and escape everything users type.
Visitor text is never rendered as Markdown or links.

| ID | Email | Recipient | Trigger | Status |
| --- | --- | --- | --- | --- |
| FR-MAIL-01 | New project enquiry (with "Open in dashboard") | `ZAIRON_INBOX` if set, otherwise every active admin who can view enquiries | Enquiry submitted | ✅ |
| FR-MAIL-02 | "We received your message" (does not repeat the visitor's message, to prevent spam relay) | Visitor | Enquiry submitted | ✅ |
| FR-MAIL-03 | Admin invitation (72 h link) | New admin user | User invited / resent | ✅ |
| FR-MAIL-04 | Password reset (60 min link) | Admin user | Forgot password | ✅ |
| FR-MAIL-05 | Quote / invoice (with optional personal message, payment instructions) | Client | Send | ✅ |
| FR-MAIL-06 | Overdue payment reminder | Client | Manual or daily job | ✅ |
| FR-MAIL-07 | Quote accepted / declined | Admins with billing access | Client answers online | ✅ |
| FR-MAIL-08 | Honeypot (bot) submissions trigger **no** emails. | — | — | ✅ |

---

## 11. Non-functional requirements

### 11.1 Security
| ID | Requirement | Status |
| --- | --- | --- |
| NFR-SEC-01 | Strict Content-Security-Policy with a per-request nonce: scripts only from the site, no inline scripts/`eval`, no framing (`frame-ancestors 'none'`), `object-src 'none'`. | ✅ |
| NFR-SEC-02 | Headers on every response: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP; HSTS on HTTPS in production; `X-Powered-By` removed. | ✅ |
| NFR-SEC-03 | CSRF protection on all state-changing requests (cross-site requests without a token are rejected with 419). | ✅ |
| NFR-SEC-04 | Rate limits: public forms 5/min and 30/day per IP; admin sign-in 5/min per email+IP; password reset 5/min per IP; client document links 30/min per IP. | ✅ |
| NFR-SEC-05 | Honeypot field on public forms; bots get a normal-looking success response and nothing is stored or sent. | ✅ |
| NFR-SEC-06 | Server-side validation of all input, with values restricted to known lists where applicable. | ✅ |
| NFR-SEC-07 | Uploads are checked by content, stored on the private disk under random names, never web-accessible, never emailed. | ✅ |
| NFR-SEC-08 | Admin API responses: `Cache-Control: no-store`, `X-Robots-Tag: noindex`. | ✅ |
| NFR-SEC-09 | Canonical URLs and email links are built from `APP_URL`, never from the request host. | ✅ |
| NFR-SEC-10 | No default accounts with known passwords are ever created. | ✅ |
| NFR-SEC-11 | Production: `APP_DEBUG=false`, HTTPS, secure and encrypted session cookies. | ⬜ (deployment) |

### 11.2 Performance
| ID | Requirement | Status |
| --- | --- | --- |
| NFR-PERF-01 | Pages other than Home are code-split and load on demand; the 3D library (~145 KB gzip) loads only on the About page, after first paint. | ✅ |
| NFR-PERF-02 | Fonts are self-hosted, latin subsets only. | ✅ |
| NFR-PERF-03 | Animations pause off-screen and in background tabs. | ✅ |
| NFR-PERF-04 | Email sending never delays a web request (queued). | ✅ |

### 11.3 Responsiveness and accessibility
| ID | Requirement | Status |
| --- | --- | --- |
| NFR-UX-01 | Every page works with no horizontal scrolling at 390, 768, 1024 and 1440 px widths (verified). | ✅ |
| NFR-UX-02 | Keyboard accessible navigation, menus, dialogs and forms; visible focus styles; skip-to-content links. | ✅ |
| NFR-UX-03 | Form errors linked to their fields (`aria-invalid`, `aria-describedby`); live regions for results. | ✅ |
| NFR-UX-04 | Charts have text alternatives (hidden data table, keyboard read-out); status is never conveyed by colour alone. | ✅ |
| NFR-UX-05 | Reduced-motion preference honoured site-wide. | ✅ |

### 11.4 Maintainability
| ID | Requirement | Status |
| --- | --- | --- |
| NFR-MNT-01 | DRY: shared components (cards, sections, buttons, forms, tables, modals), one service-page template, one shared email layout, one quote/invoice implementation for both document types. | ✅ |
| NFR-MNT-02 | Website copy in `resources/content/*.json` is the single source of truth for both the website and server-side SEO. | ✅ |
| NFR-MNT-03 | The permission catalog lives in one file (`config/permissions.php`). | ✅ |
| NFR-MNT-04 | Automated feature tests cover pages, security headers, forms, emails, admin auth/RBAC, billing and accounting (§17). New features must add tests. | ✅ |

### 11.5 Browser support
Current versions of Chrome, Edge, Firefox and Safari (desktop and mobile). The 3D effect falls back to a static
logo where WebGL is unavailable.

---

## 12. Data requirements

| Table | Purpose | Key fields |
| --- | --- | --- |
| `users` | Admin users | name, email, password (hashed), is_active, invited_at, last_login_at |
| `roles`, `role_user` | Roles and assignments | name, slug, description, permissions (JSON), is_system |
| `password_reset_tokens`, `user_invitation_tokens` | Reset (60 min) and invitation (72 h) tokens, kept separate | email, token, created_at |
| `enquiries` | Project enquiries | contact fields, services, service, plan, budget, details, brief_path/name, status, notes, ip_address, user_agent |
| `subscribers` | Newsletter sign-ups | email (unique), ip_address |
| `activity_logs` | Audit trail | user_id, action, description, subject, properties, ip_address |
| `clients` | Billable customers | name, company, email, phone, address, tax_number, notes, enquiry_id |
| `quotes`, `invoices` | Billing documents | number, client_id, status, dates, currency, exchange_rate (invoices), tax_rate, *_cents totals, notes, terms, public_token, sent/viewed timestamps |
| `line_items` | Document lines (shared) | document (morph), description, quantity, unit_cents, amount_cents, sort |
| `payments` | Invoice payments | invoice_id, amount_cents, exchange_rate, account_id (deposited to), paid_on, method, reference |
| `accounts` | Chart of accounts | code (unique), name, type, subtype (bank, cash, receivable, payable, tax, retained, revenue, fx), is_system, is_active |
| `journal_entries` | Journal entries | number, date, description, source (morph: invoice/expense) + source_key, is_manual, created_by |
| `journal_lines` | Entry lines (base currency) | journal_entry_id, account_id, debit_cents, credit_cents, memo |
| `vendors` | Suppliers | name, email, phone, tax_number, notes |
| `expenses` | Bills and purchases | number, vendor_id, account_id (category), date, due_date, description, reference, currency, exchange_rate, amount_cents, paid_on, paid_from_account_id, receipt_path/name |
| `settings` | Key/value settings | `billing`, `accounting` (base currency, financial-year start, lock date) |
| `sessions`, `cache`, `jobs`, `failed_jobs` | Framework infrastructure | — |

**Retention and privacy:** enquiry and subscriber data is kept until deleted by an admin. The Privacy Policy
commits to responding to access/deletion requests within 30 days. A formal retention schedule is open (OI-07).

---

## 13. Configuration, operations and deployment

### 13.1 Environment variables (beyond Laravel defaults)
| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public base URL; used for canonical URLs, sitemap and email links |
| `ZAIRON_INBOX` | Optional single address for enquiry notifications |
| `CSP_FRAME_SRC` | Extra allowed frame sources (e.g. Google Maps embed) |
| `MAIL_*` | SMTP settings; `MAIL_MAILER=log` writes emails to the log in development |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_NAME`, `SUPERADMIN_PASSWORD` | Optional first super admin for `db:seed` |
| `ACCOUNTING_BASE_CURRENCY` | Default base currency of the books before it is set in the portal (default `PKR`) |
| `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT` | Must be `true` in production |

### 13.2 Required background processes
| Process | Command | Required for |
| --- | --- | --- |
| Queue worker | `php artisan queue:work` (kept alive by Supervisor/systemd) | All emails |
| Scheduler | cron `* * * * * php artisan schedule:run` | Overdue reminders (daily 09:00), expired token clean-up |

### 13.3 Setup commands
| Command | Purpose |
| --- | --- |
| `php artisan migrate` | Create/upgrade the database |
| `php artisan db:seed` | Create the system and starter roles (and a super admin if `SUPERADMIN_EMAIL` is set) |
| `php artisan zairon:superadmin you@domain.com --name="Name"` | Create or promote a super admin (prints a generated password once) |
| `php artisan zairon:invoice-reminders` | Send due overdue reminders now (normally run by the scheduler) |
| `php artisan zairon:ledger-sync` | Post all invoices, payments and expenses to the journal (safe to repeat) |
| `npm run build` | Build the website, admin and document stylesheets |
| `php artisan test` | Run the automated test suite |

### 13.4 Hosting requirements
- PHP ≥ 8.3 with `gd`, `intl`, `openssl`, `pdo`, `mbstring`, `fileinfo`, `zip`.
- `upload_max_filesize ≥ 12M` and `post_max_size ≥ 16M` (10 MB enquiry attachments).
- **MySQL 8** with the **InnoDB** engine (forced in `config/database.php`; transactions and foreign keys are
  required). Development uses the local WAMP MySQL database `zairon`; automated tests use in-memory SQLite.
- HTTPS with a valid certificate; web root pointed at `public/`.
- Node.js only at build time.

---

## 14. Decisions, constraints and exclusions

These were explicitly decided by the owner and **must be respected** unless the owner changes them.

| # | Decision |
| --- | --- |
| D-01 | Stack is **Laravel + plain React SPA**. **Inertia must not be used.** |
| D-02 | The visual design follows the original ZAIRON HTML/Figma design (dark maroon "glass" system). |
| D-03 | **No website content manager (CMS).** One was built and then removed at the owner's request. Website copy is maintained in `resources/content/*.json`. Do not reintroduce a CMS without the owner's approval. |
| D-04 | **No 3D scene on the Home page** (removed at the owner's request). 3D remains only on the About page. |
| D-05 | Service pages say **"Start a project" / "Contact us"**, never "enroll". |
| D-06 | Each service has its own dedicated page, reachable from a Services mega menu. |
| D-07 | The admin portal is visually and technically separate from the public website. |
| D-08 | Roles and permissions are implemented in-house (a third-party package could not be installed in the development environment). |
| D-09 | Quotes/invoices are PDFs generated on the server with `dompdf/dompdf` (pure PHP, no browser needed on the server), using the brand fonts bundled in `resources/fonts` (SIL Open Font License). The online client page remains as well. |
| D-10 | **All ISO currencies** are supported. The books are kept in one **base currency, default PKR** (changeable until the first posting); other currencies are converted at an exchange rate entered by the user. Rates are not fetched online. |
| D-11 | **Financial year: July–June.** |
| D-12 | Accounting is **double-entry on an accrual basis**, built in-house (no external accounting package). |
| D-13 | **Taxes are fully configurable** by the user (no rates hard-coded), because the applicable taxes were not yet confirmed (Phase 3). |
| D-14 | Bank statements are imported from an **Excel file in a ZAIRON-defined template**, with a "Download template" button providing a sample file. Bank-specific formats and PDF statements are not supported (Phase 2). |
| D-15 | Amounts are displayed with 2 decimals (none for currencies without minor units, e.g. JPY), identically in the browser and on the server. |

---

## 15. Open items before launch

| ID | Item | Owner |
| --- | --- | --- |
| OI-01 | **Verify contact details:** email `zairon@sales.com` (likely meant `sales@zairon.com`) and phone `+92 314567789` (one digit short). Update `resources/content/site.json`. | Owner |
| OI-02 | Replace placeholders: `[CLIENT LOGO]`, `[XX]` stats, `[CLIENT NAME]` case studies, testimonials, founder photos, `[OFFICE ADDRESS]`, `[DATE]` on articles. | Owner |
| OI-03 | Review all service-page copy (written during development) for accuracy against what ZAIRON actually offers. | Owner |
| OI-04 | Legal review of the Privacy Policy and Terms of Service templates. | Owner + counsel |
| OI-05 | Provide social, founder LinkedIn/GitHub URLs (hidden until set) and a 1200×630 `og-image.png`. | Owner |
| OI-06 | Fill **Billing settings**: company address, tax/NTN number, bank/payment instructions, default currency and tax. | Owner |
| OI-07 | Decide a data-retention period for enquiries, subscribers and activity logs. | Owner |
| OI-08 | Production setup: domain and `APP_URL`, HTTPS, SMTP credentials, queue worker, scheduler, backups (NFR-SEC-11). | Developer |
| OI-09 | Change the generated super admin password on first sign-in. | Owner |
| OI-10 | Add the Google Maps embed on the Contact page (and allow it via `CSP_FRAME_SRC`). | Developer |
| OI-11 | **Confirm PKR as the base currency** in Accounting settings before the first invoice is sent; it is fixed after that. | Owner |
| OI-12 | Enter **opening balances** (bank, cash, capital, any unpaid bills) as a manual journal entry dated the start of the books. | Owner + accountant |
| OI-14 | **Confirm the income-tax bands** in config/hr.php with the accountant against the current FBR schedule before posting the first payroll. | Owner + accountant |
| OI-15 | **Confirm leave policy and salary conventions:** 14 annual / 8 sick / 10 casual days, up to 30 carried over, unpaid leave at salary ÷ 30 per day. | Owner |
| OI-13 | Have the accountant review the starter chart of accounts and, in Phase 3, the tax types and rates before relying on figures for filing. | Owner + accountant |

---

## 16. Backlog and future requirements

| ID | Feature | Description | Pri. |
| --- | --- | --- | --- |
| BL-01 | Job applications | Application form with CV upload on Careers; candidate list in the admin with stages (New → Interview → Offer / Rejected) and email notifications. | S |
| BL-02 | Newsletter sending | Write and send campaigns from the admin; double opt-in confirmation; mandatory unsubscribe link; send log. | S |
| BL-03 | Two-factor authentication | TOTP (authenticator app) for admin users, with recovery codes; option for Super Admin to require it. | S |
| BL-04 | ~~PDF attachments~~ | Done: see FR-BILL-21. | — |
| BL-05 | Error monitoring | Alerting for server and front-end errors. | S |
| BL-06 | Analytics | Privacy-friendly visitor statistics (no tracking cookies preferred). | C |
| BL-07 | Online payments | "Pay now" on invoices via Stripe or a local gateway, with automatic payment recording. | C |
| BL-08 | Backups | Automated daily database and private-file backups with restore testing. | M (before launch) |
| BL-09 | Case study / article detail pages | Long-form pages added **in code/JSON** (not via a CMS, per D-03). | C |
| BL-10 | Accounting phase 2: bank reconciliation | FR-ACC-30 to FR-ACC-35. | M (next) |
| BL-11 | Accounting phase 3: tax | FR-ACC-40 to FR-ACC-43. | M |
| BL-12 | Accounting phase 4: extras | FR-ACC-50 to FR-ACC-53. | S |

---

## 17. Verification

The automated suite (`php artisan test`) currently has **83 feature tests, all passing**:

| Area | What is verified |
| --- | --- |
| Pages & SEO | Every page renders with correct meta; real 404s; safe structured data; sitemap/robots; API paths never served the website |
| Security headers | CSP with matching per-request nonce, hardening headers on pages and API |
| Enquiries | Storage, private attachments, file-type/size limits, validation, honeypot, rate limit, both emails, recipient fallback, email escaping |
| Newsletter | Subscribe, no enumeration, validation, honeypot |
| Admin | Separate noindexed portal, super-admin command, sign-in/out, identical errors, rate limit, deactivation, password reset, permission enforcement, role creation, escalation prevention, last-super-admin protection, invitations, enquiry management, CSV formula safety, no-cache headers |
| Billing | Cent-exact totals, yearly numbering, validation, quote send/view/accept/convert, payments and statuses, reminders once a week, permissions, unguessable links, voiding |
| Accounting | Starter chart, July–June years, all currencies and decimal display, exchange rate required before sending, invoice/payment/expense postings, exchange gain/loss, no rounding residue in receivables, reversal on payment removal and voiding, period lock (including rollback), balanced manual entries, retained-earnings roll-over, trial balance and balance sheet always balance, aging buckets, protected system accounts, base-currency lock, receipts, permissions |

Manual verification was also performed in a browser at 390/768/1024/1440 px, including keyboard
navigation of menus and dialogs, with no console errors or security-policy violations.

---

## Appendix A — permission matrix

✓ = granted. Super Admin implicitly holds every permission.

| Permission | Description | Manager | Sales | Marketing | Accountant |
| --- | --- | :-: | :-: | :-: | :-: |
| `dashboard.view` | View the overview and statistics | ✓ | ✓ | ✓ | ✓ |
| `enquiries.view` | View enquiries and download attachments | ✓ | ✓ | | ✓ |
| `enquiries.update` | Change status and add internal notes | ✓ | ✓ | | |
| `enquiries.delete` | Delete enquiries | ✓ | | | |
| `billing.view` | View clients, quotes, invoices and revenue | ✓ | | | ✓ |
| `billing.manage` | Create, edit and send quotes and invoices; record payments | ✓ | | | ✓ |
| `billing.delete` | Delete drafts and void invoices | | | | ✓ |
| `settings.manage` | Edit company and billing settings | | | | ✓ |
| `accounting.view` | View the ledger, expenses and financial reports | ✓ | | | ✓ |
| `accounting.manage` | Record expenses and vendors, post journal entries, edit the chart of accounts | | | | ✓ |
| `accounting.close` | Change accounting settings and close or reopen periods | | | | ✓ |
| `subscribers.view` | View newsletter subscribers | ✓ | | ✓ | |
| `subscribers.export` | Export subscribers as CSV | ✓ | | ✓ | |
| `subscribers.delete` | Remove subscribers | | | | |
| `users.view` | View admin users | ✓ | | | |
| `users.create` | Invite new users | | | | |
| `users.update` | Edit users, roles and access | | | | |
| `users.delete` | Delete users | | | | |
| `roles.view` | View roles and their permissions | | | | |
| `roles.create` | Create roles | | | | |
| `roles.update` | Edit roles and their permissions | | | | |
| `roles.delete` | Delete roles | | | | |
| `activity.view` | View the activity log | ✓ | | | |

Starter roles are created once by the seeder; Super Admins can change them afterwards in the portal.

---

## Appendix B — glossary

| Term | Meaning |
| --- | --- |
| **Enquiry** | A project request submitted through the website form |
| **Pillar** | One of the four service groups: Build, Intelligence, Automate, Run & Protect |
| **Engagement option** | Discovery sprint, Build & launch or Ongoing partnership |
| **Client link** | The unguessable URL that lets a client view a quote or invoice without signing in |
| **Honeypot** | A hidden form field that only bots fill in; such submissions are silently discarded |
| **RBAC** | Role-based access control: permissions are granted through roles |
| **CSP** | Content-Security-Policy, the browser rule set limiting what a page may load or run |
| **Queue worker** | Background process that sends emails without slowing down the website |
| **Scheduler** | Background timer that runs daily jobs such as overdue reminders |
| **Base currency** | The single currency the books are kept in (PKR by default); other currencies are converted into it |
| **Journal entry** | One balanced accounting transaction: debits on some accounts equal credits on others |
| **Accrual basis** | Income and costs are recorded when invoiced/billed, not when cash moves; unpaid amounts sit in receivables/payables |
| **Trial balance** | List of every account's balance; total debits must equal total credits |
| **Aging** | Unpaid invoices or bills grouped by how many days they are overdue |
| **Period lock** | A date up to which the books are closed and cannot change |
| **Reconciliation** | Checking the bank statement line by line against the books (Phase 2) |
