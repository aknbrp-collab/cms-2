# Implementation Plan — Integrated Institutional CMS

Source: [req/project.md](req/project.md)

## 0. Starting point

- Fresh **Laravel 13 Livewire starter kit**: Livewire 4, Flux UI, Fortify auth (2FA + passkeys), Pest, Pint, Larastan.
- Only `User` model; SQLite; database queue; local filesystem.
- Spec says PostgreSQL + UUIDs + S3 → infra changes needed before any domain work.

## 1. Architecture decisions

| Concern | Decision |
|---|---|
| Admin UI | Livewire 4 + Flux (already installed), one panel with a **site switcher** (Pondok / Ma'had Aly / All) kept in session. Location is set by config: `ADMIN_DOMAIN=admin.pondok.tld` (subdomain, recommended) or `ADMIN_PATH=/admin` on the Pondok domain |
| Public sites | Same app on **two domains**, resolved by `ResolveSite` middleware matching `sites.domain` → `current_site()`; per-site Blade theme inspired by the mockups (see §1a). Local dev: `pondok.test`, `mahad.test`, `admin.pondok.test` via Herd |
| Language | **Indonesian only** for now. UI strings still go through `__()` so adding languages later is cheap; no translatable columns |
| Multi-site isolation | `sites` table + `BelongsToSite` trait (global scope + auto-fill `site_id`). Cross-site content only via an explicit `site_post` pivot ("publish to both") |
| Keys | `HasUuids` on every model; nullable, indexed `external_id` (+ `external_source`) where third parties are involved |
| RBAC | `spatie/laravel-permission` (no teams). **Each role carries its site scope** in `App\Enums\Role::site()`: Pondok Admin → Pondok, Ma'had Admin and Tanya Hukum Admin → Ma'had, the others → all sites. Policies check `can('{module}.{action}')` plus `$user->canAccessSite($model->site)`; Superadmin via `Gate::before`. Permission names live in `App\Support\Permissions` |
| Content workflow | Backed enum `ContentStatus`: `draft → under_review → approved → published` (+ `archived`). Only Managing Editor/Superadmin can approve |
| Media | `spatie/laravel-medialibrary` on an S3-compatible disk (`league/flysystem-aws-s3-v3`), shared library with site tag + conversions |
| SEO | Polymorphic `seo_meta` (title, description, OG image, canonical, noindex) + sitemap per site |
| Database | **PostgreSQL** (local PG 17/18 already running; `pdo_pgsql` enabled) |
| Queues | VPS: Redis + Horizon + Supervisor. Local Windows dev: `database` queue/cache (no Redis server installed) |
| Audit | Custom append-only `audit_logs` (actor, action, subject morph, before/after JSON, IP, UA). `Auditable` trait on models; integration attempts logged too. Model refuses update/delete; on Postgres add a trigger blocking UPDATE/DELETE |
| Integrations | Driver pattern: `SocialPublisher` interface (Instagram/Facebook/TikTok drivers), `SiakadClient` service; all calls via queued jobs with retry/backoff |

## 1a. Public UI design references

Source: [req/ide.html](req/ide.html) (Ma'had Aly) and [req/pdf.html](req/pdf.html) (Pondok Pesantren Darul Amanah). Both are static Tailwind CDN mockups with Material-3-style tokens, the **Plus Jakarta Sans** font and **Material Symbols** icons. */ this is only references, do not immitate 100%/*

**How to build them**
- Move each mockup's `tailwind.config` tokens into a per-site Tailwind v4 `@theme` CSS file (`resources/css/sites/mahad.css`, `pondok.css`) built by Vite. No CDN.
- Split the mockups into Blade components (`x-site.header`, `x-site.footer`, section components) under `resources/views/sites/{pondok,mahad}/`.
- Placeholder images (`lh3.googleusercontent.com`) become media-library items.
- Navigation is hard-coded in the mockups and becomes CMS `menus` with nested dropdowns.
- Site settings keep only the brand colours (primary, primary-container, secondary/gold), written into CSS variables at runtime. The rest of the token set stays per theme.

| | Ma'had Aly (`ide.html`) | Pondok (`pdf.html`) |
|---|---|---|
| Palette | primary `#003527`, container `#064E3B`, secondary `#904D00`, gold `#B45309`, dark footer `#0B1914` | primary `#005D42`, container `#047857`, gold `#EAB308`, light footer |
| Top bar | Salam in Arabic, **Hijri + Gregorian date**, **next-prayer countdown**, quick links (Maktabah Digital, Portal Siamad SSO, Jurnal OJS), **ID / AR / EN switcher** | Announcement ticker (PSB open), phone, email |
| Main nav | Beranda · Profil · Akademik · Publikasi & Riset · Berita & Agenda · Mahasantri · CTA "PMB 2024/2025" | Beranda · Profil (8 sub-pages) · Pendaftaran · Galeri (Foto/Video) · IKSADA · search · CTA "Hubungi Kami" |
| Home sections | Hero, Takhassus programs (Marhalah Ula S1 / Tsaniyah S2), Bahtsul Masail lab & OJS, Dewan Masyayikh & Asatidzah, scholarships and admission paths, Agenda & Halaqah | Featured headline, Artikel Terkini, Keislaman, Prestasi Santri, **Darul Amanah TV** (video), PSB brochure/online registration, announcements, contact & map & socials |

**Decided:** the mockups are *references*, not pixel targets. Each site gets one coherent template set (home + inner pages such as listing, detail, directory, gallery, contact, form) derived from its home style; only content differs.

**What the mockups add beyond project.md**
- ~~Multilingual ID/AR/EN~~ → Indonesian only for now; the language switcher is dropped.
- **Hijri date and prayer times widget** → a service that calculates times for the site's configured coordinates and caches them per day. The site gets latitude, longitude and calculation-method settings.
- **External system links** (Siamad SSO, OJS, Maktabah Digital) → just menu items, not integrations. *Confirm OJS is external.*
- **Bahtsul Masail compilation** (Ma'had) → fits the Tanya Hukum / publications repository. Treated as a publication type.
- **Pondok extras:** Prestasi (achievements), Ekstrakurikuler, Kegiatan, Program Pendidikan, **IKSADA** (alumni association page), Darul Amanah TV (YouTube embeds in the video gallery), site search, downloadable PSB brochure, and an online registration link or form. *Confirm whether "Pendaftaran Online" is an in-CMS form or an external link.*
- **Post categories** drive the home sections (Keislaman, Prestasi Santri …), so categories get a "show on homepage" flag and ordering.

## 2. Data model (core tables)

**Platform:** `sites`, `site_settings` (identity, logo,icon name(name in arabic calligraphy), favicon, domain, theme colors `#064E3B`/`#D97706`, header/footer, analytics/meta codes), `menus`, `menu_items`, `audit_logs`, `integration_logs`.

**Shared content (site-scoped):** `pages` (slug, template, block JSON, status), `posts` (type: news / announcement / academic_news), `categories`, `events` (calendar), `galleries` + media, `faqs`, `admission_periods` + `admission_info` (PMB), `leaders` (Pengasuh / Mudir), `seo_meta`.

**Ma'had Aly only:** `lecturers`, `programs` (Takhassus), `curricula` / `courses`, `publications` (type: research / journal / repository item, file, DOI), `journals` + `journal_issues`, `student_organizations` (DEMA, UKM).

**Pondok only:** `facilities`, `achievements` (Prestasi), `extracurriculars`. These use a shared table design and are just enabled per site. Video gallery items accept YouTube URLs (Darul Amanah TV).

**Localisation:** none for now (ID only).

Module availability per site is driven by config (`config/sites.php` → enabled modules), so menus/routes/permissions only appear where relevant.

**SIAKAD:** `siakad_articles` (external_id, payload, status draft/under_review/approved, linked `post_id` on publish), `legal_questions` (asker name/email nullable, `is_anonymous`, question, category, status, external_id, answer, answered_by, published_at), `legal_question_events` (state history).

**Social:** `social_accounts` (provider, site_id, name, external_id, **encrypted** access/refresh tokens, expires_at, status), `social_posts` (caption, media, scheduled_at, status, author), `social_post_targets` (post × account, platform overrides: caption/hashtags, status, external_post_id, attempts, last_error, published_at).

## 3. Key flows

**Content approval:** Site admin creates → submits for review → Managing Editor approves/rejects with note → publish now or `publish_at` (scheduler command flips status). Every transition audited + notification.

**SIAKAD inbound articles:** `POST /api/webhooks/siakad/articles` → verify HMAC signature + timestamp → idempotent upsert on `external_id` → status `draft` → admin reviews → on approval, materialises as a `Post` on the chosen site.

**Tanya Hukum:**
1. Public form (rate-limited, honeypot/captcha, optional anonymous) → `submitted`
2. Tanya Hukum Admin: reject (`rejected`) or forward → `ForwardQuestionToSiakad` job → `forwarded` (stores SIAKAD id)
3. `POST /api/webhooks/siakad/answers` → `answered`
4. Admin reviews/edits presentation → `published` → appears in public repository (searchable, categorised)

**Social publishing:** Composer writes base caption + media, picks accounts, per-platform tweaks with live char-limit validation → creates one `social_post_target` per account → scheduler `social:dispatch-due` (every minute) dispatches **one job per target** (`PublishSocialTarget`, `tries`, backoff, rate-limit middleware per account) so one failure never blocks others. Token-refresh job runs daily; expired accounts flagged in UI. Calendar view (FullCalendar via Alpine) with drag-to-reschedule → updates `scheduled_at`.

## 4. Roles & permissions

| Role | Scope | Key permissions |
|---|---|---|
| Superadmin | global | everything (Gate::before), settings, users, audit logs |
| Managing Editor | both sites | approve/publish any content, view all |
| Pondok Web Admin | site=pondok | CRUD pages/news/galleries/events, submit for review |
| Ma'had Aly Web Admin | site=mahad | CRUD pages/news/academic materials, submit for review |
| Social Media Manager | global or per-site | accounts, composer, calendar, publish |
| Tanya Hukum Admin | global | moderate questions, forward to SIAKAD, publish answers |

Permissions named `{module}.{action}` (e.g. `posts.publish`), seeded from an enum; tests assert the matrix.

## 5. Phased delivery

**Phase 0 — Foundation** ✅ done 2026-10-31. Keys: domain tables use UUIDs; package lookup tables (roles, permissions, media, jobs, cache) keep their bigint ids, and their polymorphic model keys are UUID.
- Give the project its own git repo (currently the repo root is `D:\`, see notes).
- Switch to PostgreSQL; S3-compatible `media` disk (local disk in dev, same config shape); app name/URLs; Herd domains.
- Install spatie/laravel-permission, spatie/laravel-medialibrary, flysystem-s3.
- `sites` + settings, `BelongsToSite`, UUID convention, `Auditable` + `audit_logs`.
- Admin shell: layout on `admin.pondok`, site switcher, role-aware sidebar, user & role management.

Phase 0 task breakdown, in order:
1. `git init` in `cms-ready` with a `.gitignore` check and an initial commit of the starter kit.
2. `.env`/`.env.example`: `pgsql` database `cms_ready` (+ `cms_ready_test` for Pest), `APP_NAME`, `ADMIN_DOMAIN`, site domains; Herd links `pondok.test`, `mahad.test`, `admin.pondok.test`. Fix the stray `siakad` name/port in `.claude/launch.json`.
3. `composer require spatie/laravel-permission spatie/laravel-medialibrary league/flysystem-aws-s3-v3`, then publish and migrate.
4. Convert `users` to UUID keys (rewrite the starter migrations while still empty) and add the `HasUuids` convention.
5. `sites` table, enum `SiteKey {pondok, mahad}`, seeder for both sites, `ResolveSite` middleware, `current_site()` helper, `BelongsToSite` trait + tests.
6. `audit_logs` + `Auditable` trait + Postgres trigger blocking UPDATE/DELETE + tests.
7. Roles/permissions seeder (6 roles, `{module}.{action}` permissions, team = site) + `Gate::before` for Superadmin + matrix tests.
8. Admin routing group on `ADMIN_DOMAIN`, Flux layout, site switcher, role-aware sidebar, Users & Roles screens.
9. `composer test` green (Pint, Larastan, Pest).

**Phase 1 — CMS core:** ✅ done 2026-10-31. Site settings and theme, menus, media library, pages (block builder), posts/news/announcements, categories, SEO, approval workflow, scheduled publishing.
- Announcements are a post *type* and use the `posts.*` permissions; categories have their own `categories.*` permissions.
- Rich text uses Trix and is sanitised server side (`App\Support\RichText`). Embeds accept only YouTube and Google Maps (`App\Support\Embed`).
- Page blocks: text, image, call to action, embed. More block types come in Phase 2 with the themes.
- Cross-site "publish to both" was not built. Each post belongs to one site; add a "copy to other site" action later if needed.
- Workflow notifications go to the database (dashboard). Email can be added per notification later.
- Scheduled publishing needs the scheduler running (`php artisan schedule:work` locally, cron + `schedule:run` on the VPS).
- Upload limit is 12 MB per file (Livewire default). Larger videos should go to YouTube.

**Phase 2a — Public themes** ✅ done 2026-10-31.
- One app serves both domains. Shared inner templates live in `resources/views/sites/shared`, theme overrides in `sites/{pondok,mahad}`, resolved by `App\Support\SiteTheme`. Each theme has its own Tailwind v4 token file (`resources/css/sites/*.css`), and the brand colours from site settings override the tokens at runtime.
- Routes: `/`, `/berita`, `/berita/{slug}`, `/pengumuman`, `/kategori/{slug}`, `/{page-slug}`, `/sitemap.xml`, `/robots.txt`, plus a themed 404.
- Ma'had top bar: Hijri date (Umm al-Qura, pesantren month names) and next-prayer countdown (`App\Support\PrayerTimes`, Kemenag angles plus 2-minute ihtiyat). Verify the times against the official Kemenag schedule for the real coordinates.
- Homepages are data-driven. Sections for programs, lecturers, agenda, gallery/TV and PMB steps are added with their modules in 2b and 2c.
- `DemoContentSeeder` fills both sites locally (runs from `DatabaseSeeder` outside production).
- App timezone is now `Asia/Jakarta` (`APP_TIMEZONE`).

**Phase 2b — Shared modules** ✅ done 2026-10-31.
- **Agenda (events) and galleries** use the full editorial workflow, like posts and pages (`App\Support\ContentTypes::REVIEWABLE` drives the review queue, scheduling and dashboard). Galleries are photo albums (bulk upload or pick from the library) or YouTube collections. The newest videos become the Pondok homepage "TV" section.
- **FAQ, leadership and admission periods** are reference content with an on/off publish switch (`App\Livewire\ReferenceList`). Site admins edit unpublished items; `{module}.publish` is needed to publish or to change published items.
- **Admissions:** periods have steps, requirements, fees, programs, a brochure (PDF via the media picker) and an "accept online registration" switch.
  - Public pages: `/penerimaan`, `/penerimaan/daftar` and a signed confirmation page showing the registration number.
  - Spam protection: throttling, a honeypot field and a minimum fill time.
  - Admin: a registrations inbox (status and notes), scoped to the user's sites, with CSV export guarded against formula injection.
  - Personal data is kept out of the audit log.
- Public routes: `/agenda`, `/agenda/{slug}`, `/galeri`, `/galeri/{slug}`, `/faq`, `/pimpinan`, `/penerimaan…`. Each returns 404 on a site without that module (`config/cms.php`); FAQ is now enabled for Ma'had too.
- Homepages: Pondok gets a TV section, an agenda sidebar and a PSB card driven by the open period. Ma'had gets Pimpinan & Masyayikh, Agenda & Halaqah, and PMB steps.
- Registration confirmation is on-screen and via a WhatsApp link only; email/WhatsApp notifications can be added later.

**Phase 2c — Site-specific modules:** Pondok (facilities, achievements, extracurriculars); Ma'had (lecturers, Takhassus programs, curriculum, publications/journals/repository/Bahtsul Masail, organizations); homepage sections for each.

Original Phase 2 scope: events calendar, galleries (incl. YouTube), FAQ, PMB, leadership, facilities, achievements, extracurriculars; Ma'had modules (lecturers, programs, curriculum, publications/journals/repository/Bahtsul Masail, organizations); the two public themes converted from `ide.html` / `pdf.html`, domain resolution, Hijri date and prayer-times widget (Ma'had), Pondok PSB online registration form, sitemap, and analytics injection.

**Phase 3 — SIAKAD and Tanya Hukum (CMS side only):** the CMS **defines the contract**, and SIAKAD is built against it later.
- Inbound webhooks (articles, answers) with HMAC auth; outbound `SiakadClient` behind an interface, with a `fake` driver for dev and tests.
- Tanya Hukum public form, moderation, forward/answer/publish, public repository; `integration_logs` UI with retry.
- **Deliverable:** `docs/siakad-api-spec.md` (auth/signing, endpoints, payloads, status codes, idempotency, retries, sample requests) plus an OpenAPI file, so the SIAKAD project can integrate.

**Phase 4 — Social media (ready, credentials later):** account manager, composer, per-target queue jobs, calendar with drag and drop, failure dashboard/retry.
- Drivers for Facebook Pages, Instagram (Graph API) and TikTok Content Posting API. They are enabled per platform once app keys go into `.env`; until then a `log`/fake driver is used.
- **Review-readiness**, so Meta App Review and the TikTok audit can pass:
  - public Privacy Policy and Terms pages
  - Meta data-deletion callback plus a deauthorize endpoint
  - only the minimal scopes requested
  - TikTok UX rules: creator-info query before posting, privacy-level picker with no default, interaction toggles, content-disclosure/branded toggles, explicit user consent, post-status polling
  - a written per-platform review checklist and screencast script in `docs/social-review.md`

**Phase 3/4 deliverable docs:** `docs/siakad-api-spec.md`, `docs/social-review.md`.

**Phase 5 — Hardening:** Pest coverage of policies, workflows, webhooks, jobs (HTTP faked); Larastan; rate limits; backups; Horizon; deployment docs.

## 6. Open questions / risks

**Decisions (from review, 2026-10-31):** PostgreSQL · VPS (Redis + Horizon) · two public domains, CMS on `admin.pondok` or `pondok/admin` · Indonesian only · mockups are references, inner pages derived from home style · SIAKAD is built later against our spec · social APIs are build-ready, credentials added later · no content migration needed.
- Git: new repo inside `cms-ready` (branch `main`); the drive-wide `D:\` repo is left untouched.
- Admin on the **`admin.pondok` subdomain** (`ADMIN_DOMAIN`).
- Arabic-calligraphy name = **uploaded image (SVG/PNG)** in site settings (`calligraphy` media collection), shown beside the logo.
- Keep: **Hijri date + prayer times** (Ma'had Aly top bar; site lat/lng + method settings) and an **in-CMS Pondok PSB online registration form** (submissions list, status, CSV export).
- Drop for now: site search; Tanya Hukum is **Ma'had Aly only**.

Original questions and answers:

1. **SIAKAD API contract** — endpoints, auth (HMAC? OAuth client credentials?), payload schema, whether SIAKAD is our system or third-party. *(Note: `.claude/launch.json` has a config named `siakad` on port 8010; is there a local SIAKAD project to integrate against?)* */ yes, but we will make it later from siakad site, now just build cms, and write api requirment and spec.md after finish, so siakad can integrate?*
2. **Social API approvals** — Meta app review (`instagram_content_publish`, `pages_manage_posts`) and **TikTok Content Posting API audit** are required before posting publicly; unaudited TikTok apps can only post private videos. This needs to start early since it takes weeks. */ just build first, api from 3 platform will set later, just make it ready and make it acceptable for meta and tiktok/*
3. **Database choice** — MySQL or PostgreSQL? (Immutable-audit trigger is cleaner on Postgres.) */ posgresql/*
4. **Domains** for each site, and whether the admin runs on its own subdomain. */ both website profile run on difference domain, but cms run on pondok/admin or admin.pondok/*
5. **Language** — the Ma'had mockup shows ID/AR/EN. Is everything translated or only key pages? Does Pondok need it too? */ no need multiple language for now, just ID/*
6. ~~Design~~ → resolved: `ide.html` (Ma'had) and `pdf.html` (Pondok). Do the inner pages (detail, listing, directory) need mockups, or should they be derived from the home page style? Real logos and photos are also needed. /* be derived from the home page style, like template, only differ in content/*
7. **Hosting** — VPS (Linux, so Horizon is fine) or shared hosting (no Redis/Horizon, needs cron-only queue)? */vps/*
8. Data migration from existing websites? */ if you mean template idea website, the answer is no, but if you mean the website can migrate from my website like siakad, the answer is yes, but no necessary/*

## Notes on current repo

- `git rev-parse --show-toplevel` is **`D:/`**, so the whole drive (including `$RECYCLE.BIN`) is one repo and branch `bayar` holds unrelated commits. Recommend `git init` inside `cms-ready` (or a fresh clone) before starting.
- `APP_NAME=Laravel`, `APP_URL=http://cms-ready.test:8000`; update both in Phase 0.
