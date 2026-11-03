# v2 plan

Source: [req/v2.md](req/v2.md). This builds on the finished v1 (Phases 0–5, see [plan.md](plan.md)).

## Requirements as understood

| # | Requirement | Decision |
|---|---|---|
| 1 | Admission: a registration step to upload documents, tied to the requirements | Structured requirements per admission period. Applicants fill in their biodata, then upload one file per requirement, now **or later** with their registration number + birth date. Admins verify each document. |
| 2 | Public website: dark mode | Both sites. Follows the visitor's system setting by default, with a toggle in the header. |
| 3 | Public website: hero picture slider, managed in the admin | **Ma'had Aly only.** Slides become the background of the existing hero; the text and calligraphy panel stay on top. Each slide: image, optional title, text and button link; order, schedule and hide are set in the admin. |
| 4 | Admin dashboard in Indonesian | Every admin screen, message, email and validation error is in Indonesian, except terms that are clearer in English (glossary below). |
| 5 | Hide action on all content | A separate **visibility switch** (eye icon) on each list row. It hides an item from the public site without changing its workflow status, and showing it again restores it exactly as it was. |

## Current state (what v2 changes)

- Admission periods have a rich-text `requirements` field, but registrations have no files.
- The Ma'had home hero is static text + calligraphy. The Pondok home opens with the featured article; it gets no slider.
- The public themes are colour-token based (`resources/css/sites/*.css`, overridden by site-settings colours), so dark mode means a second token set. The admin panel already has dark mode (Flux appearance setting).
- The admin is English. All ~810 admin strings already go through `__()`, so translation is done with language files, not view edits. There is no `lang/` folder yet, so validation, auth and pagination messages are English too.
- FAQ, leaders, admission periods and the generic modules already have a publish switch. Posts, pages, events, galleries, publications, categories and menu items have no hide.

---

## Phase 6 — Hide switch ✅ done 2026-11-03

**Notes from building it:**
- `published()` on workflow models now excludes hidden items, which covers every public query and the sitemap at once.
- The public menu also drops links to *unpublished* pages (they would 404 anyway).
- `is_hidden` is not mass-assignable: it changes only through `App\Support\Visibility::toggle()`, which writes the audit entry.
- Content lists share the `App\Livewire\ContentList` base (pagination, toggle, filter). The editor switch lives in the shared workflow panel.
- The Unpublish button's icon changed from eye-slash to a download arrow, so it isn't confused with Hide.

**Plan:**

**Data:** an `is_hidden` boolean (default false) on `posts`, `pages`, `events`, `galleries`, `publications`, `categories` and `menu_items`, and later on `hero_slides` (Phase 8).

**Behaviour:**
- Hidden items disappear from the public site everywhere: lists, detail pages (404), the home page, related items, the sitemap, menus and RSS-like listings. This goes through the public "visible" query scopes, so it can't be forgotten per page.
  - A hidden **category** hides its archive page and its menu links. Its posts stay visible in other lists.
  - A hidden **menu item** hides its sub-links too.
- The workflow status is untouched: a published post that is hidden stays "Published", and unhiding shows it again.
- **Who can toggle:** anyone with the module's edit permission on that site, including site admins on content that is already published (they still can't publish). Superadmins and editors can always.
- Every toggle is written to the audit log (`content.hidden` / `content.shown`).

**UI:**
- An eye / eye-slash button on every list row, plus a "Disembunyikan" badge and a "Tampilkan yang disembunyikan" filter.
- The same switch in each editor's sidebar.
- The existing "Published" switches on FAQ, leaders, admissions and the generic modules are relabelled to the same show/hide wording, so the whole admin is consistent. Their behaviour doesn't change.

**Tests:** hidden items are 404 / absent in lists, the sitemap and menus; status is unchanged; toggling is allowed per role and site; audit entries are written.

## Phase 7 — Admission documents ✅ done 2026-11-03

**Notes from building it:**
- The relation is `AdmissionPeriod::documentRequirements()`, because `requirements` is already the rich-text column.
- Uploads work without JavaScript (plain form posts). With JavaScript, the same form uploads with a progress bar and shows errors inline.
- Laravel's fake uploads report a MIME type from the file *name*; the content check is tested with a real temporary file. A test also confirms EXIF data is removed from photos.
- Site admins cannot edit a *published* admission period (existing rule), so editors manage the requirements of a live period.
- Public validation messages are still English until Phase 10 adds the Indonesian language files.
- Demo data: the seeder adds four requirements to the demo period.

**Plan:**

**Data:**
- `admission_requirements`, one per period: name (e.g. "Kartu Keluarga"), description, required yes/no, accepted types (PDF / JPG / PNG / WEBP), max size (default 5 MB), and order. They are edited in the admission period editor as a sortable list. The existing rich-text requirements stay as general information.
- `admission_documents`, one per registration and requirement:
  - the file (spatie medialibrary on the **private** disk, never a public URL)
  - status: *menunggu* / *diterima* / *perlu perbaikan*
  - the admin's note, the reviewer and the review time
- Registrations get a completeness summary (e.g. "3/5 berkas, 1 perlu perbaikan") for lists, filters and the CSV export.

**Public flow:**
1. **Step 1, biodata:** the existing form, now with a step indicator. Submitting creates the registration.
2. **Step 2, berkas:** one card per requirement, showing its status, accepted types and size limit. Each file uploads on its own with a progress bar, and can be replaced until it is accepted. "Selesai" leads to the confirmation page with the registration number and instructions to come back.
3. **Come back later:** a "Lengkapi berkas" page on the admissions page asks for the registration number + birth date (throttled) and opens step 2 for that registration for a limited time (session-bound). Uploads are allowed while the period is open, and documents that need fixing can always be replaced.

**Admin:**
- The registration detail shows each document with a preview (images inline, PDF download) and buttons for *Terima* and *Perlu perbaikan* (with a note).
- Filters: berkas lengkap / belum lengkap / perlu perbaikan.
- When a document needs fixing and the applicant gave an email, they are emailed with the note and the "Lengkapi berkas" link.

**Security & privacy:**
- Files are stored on the private disk with random names. Allowed types are checked by content (not only the extension), and images are re-encoded to strip EXIF.
- Files are served only through an authorized admin route (`Content-Disposition: attachment` for non-images, `nosniff`). Applicants never see other registrations: access is tied to the session grant.
- Uploads are throttled. The audit log records actions without personal data or file names.
- Backups already include `storage/app/private`.
- Optional: `CMS_ADMISSION_DOCUMENTS_RETENTION_DAYS` deletes documents a set number of days after the period closes (off by default).

**Tests:**
- the two-step flow, and coming back with the right and wrong number or birth date
- type and size checks, replacing a file, and being locked after acceptance
- private storage (no public URL) and admin download permissions per site
- verification, notifications and the export columns

## Phase 8 — Ma'had hero slider ✅ done 2026-11-04

**Notes from building it:**
- Slides use the existing `large` image conversion (WEBP, max 1600 px) instead of a new `hero` conversion. A new conversion would be generated for every image upload site-wide, and existing images wouldn't have it.
- The admin screen is "Homepage slider" in the Website group, shown only when the selected site uses the module. Slides with a deleted image are skipped on the website and flagged in the admin.
- Accessibility: a pause/play button (required for content that moves automatically), labelled dots, keyboard arrows, swipe, pause on hover/focus or a hidden tab, and no autoplay with reduced motion. The site name stays the page's `<h1>` (visually hidden while slides show their own title).
- In the built-in browser pane, images from the admin host don't load on the public hosts (the pane blocks the request; curl serves them). This affects all local media on the public sites, not just slides. Real browsers and S3 media in production are unaffected.

**Plan:**

**Data:** `hero_slides` (site_id, image from the media library, optional title, text, button label + URL, sort order, optional `starts_at` / `ends_at`, `is_hidden`). It is a Ma'had module (enabled in `config/cms.php` modules), with permission `hero_slides.*` for the Ma'had admin, editors and superadmins.

**Admin:**
- A "Slider beranda" screen with a thumbnail list.
- Each slide has a dialog with the image picker (images only), the texts, the link, the schedule and the hide switch.
- Slides are ordered with up/down buttons.
- An image conversion `hero` (1920 px wide, WEBP) is generated.

**Public (Ma'had home):**
- The active slides (visible and within their schedule) form the hero background, behind a dark gradient that keeps the text readable.
- The text column shows the current slide's title, text and button when set, and otherwise the site name, description and CTA as today. The calligraphy panel stays.
- The slider (Alpine, no extra library):
  - autoplays every 6 s, pausing on hover, focus or a hidden tab
  - prev / next buttons and dots, keyboard and swipe support
  - no autoplay with `prefers-reduced-motion`
  - the first image is preloaded and the rest lazy-loaded
- With no slides, the hero looks exactly like today.

**Tests:** schedule and hide filtering, order, Ma'had-only (403 / absent on Pondok), and the fallback hero.

## Phase 9 — Public dark mode (both sites) ✅ done 2026-11-04

**Notes from building it:**
- **The `brand` token.** A new `brand` colour token is used for text, borders, focus rings and form accents. It equals the site's primary colour in light mode and a light tint of it in dark mode (`color-mix`, so it follows the colours set in site settings). The ~160 public uses of `text-/border-/ring-/accent-primary` were switched to it; `bg-primary` (hero, buttons, header bar) stays dark green in both modes. A test fails if a public view uses `*-primary` for text, borders or rings again.
- **Dark palettes.** Each theme file defines its dark palette under `:root.dark`. The `dark:` variant is class-based, and only the status colours (red errors, amber notices) needed explicit dark variants.
- **Toggle icons.** The Google icon font sets `display` on icons, which beats Tailwind's `hidden`, so the toggle hides wrapper spans instead.
- **Optional dark logo.** "Logo for dark mode" in site settings swaps in on dark headers (and on the Pondok footer). The Ma'had footer is always dark and keeps the normal logo.
- **"Warna gelap" overrides.** The plan's optional dark-colour fields in site settings were not added: the derived tints read well with both default palettes. They are easy to add later if a custom brand colour needs it.

**Plan:**

**Theme tokens:**
- A dark token set per theme in `resources/css/sites/{pondok,mahad}.css` (surfaces, text, borders, cards, footer).
- The brand colours from site settings stay; their dark-mode variants are derived with `color-mix()` so text and buttons keep WCAG AA contrast.
- Optional site-settings fields for "warna gelap" overrides and a **logo for dark mode** (a dark logo on a dark header would disappear).

**Switching:**
- The default follows `prefers-color-scheme`.
- A sun/moon toggle in the header and mobile menu stores the choice in `localStorage`.
- A tiny inline script in `<head>` applies it before the first paint (no white flash).
- `theme-color` and `color-scheme` meta tags follow the mode.

**Coverage:**
- every public page: home, lists, detail, prose content, cards
- forms (PSB with the new document step, Tanya Hukum)
- galleries, the 404 page, embeds and the Ma'had top bar (Hijri date and prayer times)
- the ~15 hard-coded colours in the views are replaced with tokens where they are not intentional (e.g. white text over photos stays)

**Checks:** a browser pass of every page type in light and dark at phone and desktop widths, plus a test that the toggle markup and head script render.

## Phase 10 — Indonesian admin

**Translations:**
- `lang/id.json` for all admin strings, including the enum labels (statuses, roles, types), notifications and emails, toasts, confirmation dialogs, the Fortify auth pages, settings, the social composer, and the strings from Phases 6–9.
- `lang/id/{validation,auth,passwords,pagination}.php` (published, then translated), with Indonesian attribute names for the admin forms.
- English strings in Blade or PHP that don't go through `__()` are found and wrapped.
- Dates already use `translatedFormat` with `APP_LOCALE=id`.

**Glossary:** the terms that stay in English. Adjust before starting if you prefer otherwise.

| Stays English | Translated (examples) |
|---|---|
| Dashboard, Login, Logout, Audit log, Email, Password, Passkey, Two-factor (2FA), Superadmin | Posts → **Berita & Artikel**, Pages → **Halaman**, Media library → **Pustaka Media**, Settings → **Pengaturan** |
| Slug, SEO, URL, Link, Upload, Draft, Preview | Review queue → **Antrean Tinjauan**, Approve → **Setujui**, Reject → **Tolak**, Publish → **Terbitkan** |
| Caption, Hashtag, Webhook, Token, Horizon | Schedule → **Jadwalkan**, Save → **Simpan**, Delete → **Hapus**, Hide/Show → **Sembunyikan/Tampilkan** |

**Guard:** a test fails when an admin `__()` string has no Indonesian entry, so later features can't reintroduce English.

The public sites are already in Indonesian and stay unchanged.

---

## Order and delivery

| Phase | Scope | Size |
|---|---|---|
| 6 | Hide switch (foundation for the slider's hide too) | M |
| 7 | Admission documents | L |
| 8 | Ma'had hero slider | M |
| 9 | Public dark mode | M |
| 10 | Indonesian admin (last, so it covers every new string) | M, mostly mechanical |

Each phase works like v1: build → tests, Pint and Larastan → browser check → update this file → commit when you say so.

## Open points (defaults chosen; tell me to change any)

1. **Glossary** above.
2. **Categories** get the hide switch too; their posts stay visible elsewhere.
3. **Dark-mode logo** upload is optional; without it, the normal logo is used.
4. **Document retention** is off by default.
5. **The document size limit** defaults to 5 MB per file (adjustable per requirement, up to the 12 MB upload limit).
