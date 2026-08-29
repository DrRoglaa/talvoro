# Talvoro Redesign 02 Public Product Site Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Talvoro Editorial as the canonical built-in frontend theme and turn the default public site into a complete story-driven Talvoro product website using Talvoro's own pages, Page Builder blocks, menus, SEO and forms.

**Architecture:** Preserve the existing PHP 8.5/MySQL/server-rendered architecture. Add a small `PublicSitePreset` service that safely seeds only missing/known-default public marketing content, keep `trenlume-light` installed for compatibility, and style the seeded Page Builder output through the existing semantic block variants and public stylesheet rather than introducing a SPA or a parallel template system.

**Tech Stack:** PHP 8.5, MySQL/MariaDB SQL migrations, HTML, CSS, vanilla JavaScript, existing Talvoro Page Builder/Menu/SEO/Theme services.

**Spec:** `docs/superpowers/specs/2026-08-28-talvoro-complete-redesign-design.md`

## Global Constraints

- Keep `VERSION` at `0.15.1` during this development ZIP.
- No Node/npm/Vite/Webpack or third-party frontend runtime.
- Preserve custom themes and existing user-authored pages; only known Talvoro demo defaults may be replaced.
- Keep `trenlume-light` available for backwards compatibility; do not delete it.
- Talvoro Editorial becomes the protected canonical built-in theme.
- Keep the approved 01e visual baseline: light Trenlume-inspired palette, system typography, refined terracotta action hierarchy.
- Public marketing content must use real Talvoro Pages/Page Builder/Menu/SEO data wherever practical.
- Do not commit, push or tag automatically.

---

### Task 1: Redesign 02 regression contract

**Files:**
- Create: `bin/check-redesign-02.php`

**Interfaces:**
- Consumes: existing 01e foundation and source layout.
- Produces: a source-level regression gate for theme identity, preset pages, menus, SEO, variants, and public CSS.

- [ ] Write checks that initially fail because `PublicSitePreset`, migration 025, Talvoro Editorial fallback, product-site variants, and Redesign 02 CSS do not exist.
- [ ] Run `php bin/check-redesign-02.php` and confirm failure.
- [ ] Keep this check green through all later tasks.

### Task 2: Canonical Talvoro Editorial theme and compatibility

**Files:**
- Create: `database/migrations/025_talvoro_editorial_public_site.sql`
- Modify: `app/Core/ThemeManager.php`
- Modify: `app/Core/Settings.php`
- Modify: `app/Core/Installer.php`
- Modify: `resources/views/admin/themes.php`

**Interfaces:**
- Produces built-in slug `talvoro-editorial` while preserving `trenlume-light`.
- Default fallback and deactivation target resolve to Talvoro Editorial.

- [ ] Insert Talvoro Editorial as a protected built-in theme; activate it only when the previous active theme is `trenlume-light` or no theme is active.
- [ ] Keep custom active themes untouched.
- [ ] Replace user-visible “Trenlume Light protected default” wording with Talvoro Editorial wording.
- [ ] Change installer default frontend theme setting to `talvoro-editorial`.
- [ ] Run source checks and PHP syntax validation.

### Task 3: Safe dogfooded public-site preset

**Files:**
- Create: `app/Core/PublicSitePreset.php`
- Create: `bin/apply-talvoro-product-site.php`

**Interfaces:**
- `PublicSitePreset::apply(?int $actorId = null, bool $force = false): array`
- `bin/apply-talvoro-product-site.php` is an explicit dogfooding command for Talvoro's own deployment; normal customer installs are never converted into the Talvoro marketing site by an update.
- Seeds `/`, `/product`, `/themes`, `/resources`, `/self-hosting`, `/open-source`, `/support`, `/demo` only when safe.
- Seeds primary/footer menus only if their locations are empty.
- Seeds SEO metadata for new marketing pages.

- [ ] Build canonical block arrays using existing `hero`, `values`, `custom`, `cards`, `stats`, `faq`, `contact`, and `cta` types.
- [ ] Replace homepage blocks only when they still match known Talvoro demo/legacy content; never overwrite a custom homepage.
- [ ] Create missing marketing pages as published Page Builder pages.
- [ ] Set branding to Talvoro only when site-name/tagline still use shipped defaults.
- [ ] Create primary/footer menus only when those locations do not already have a menu.
- [ ] Make the preset idempotent and refuse to overwrite a customized site unless explicitly forced.
- [ ] Expose the preset only through `bin/apply-talvoro-product-site.php`; do not run it from installer/updater/migrations.

### Task 4: Editorial product-site block variants and rendering

**Files:**
- Modify: `app/Core/PageBlocks.php`
- Modify: `public/assets/js/page-builder.js`
- Modify: `resources/views/page/blocks/custom.php`
- Modify: `resources/views/page/blocks/cards.php`

**Interfaces:**
- Adds semantic custom variants `product-ui`, `ownership`, `capabilities`, `install`.
- Adds cards variant `audiences`.
- Existing block JSON remains valid and old variants unchanged.

- [ ] Add variants to server validation and builder options.
- [ ] Render accessible product-preview markup only for the new variants.
- [ ] Keep normal custom/cards rendering unchanged.
- [ ] Add builder preview support through existing semantic variant attributes.

### Task 5: Public header, footer and complete product-site styling

**Files:**
- Modify: `resources/views/layouts/app.php`
- Modify: `public/assets/css/talvoro-public.css`
- Modify: `public/assets/css/talvoro-foundation.css` only if a shared semantic token is required.

**Interfaces:**
- Public header uses the menu system, a quiet GitHub link, Demo action and Get Talvoro CTA when the canonical Talvoro product menu is active.
- Footer remains restrained and light.

- [ ] Add a public product-site body marker when the active theme is `talvoro-editorial`.
- [ ] Implement story-driven section rhythm, product UI mock surfaces, ownership/install visuals, audience cards and responsive behavior.
- [ ] Ensure mobile navigation and footer remain usable without JavaScript dependency for core links.
- [ ] Preserve WCAG focus visibility and reduced-motion behavior.

### Task 6: Verification and development package

**Files:**
- Update: `bin/check-redesign-02.php`
- Package: `talvoro-redesign-02-v0.15.1.zip`

**Interfaces:**
- Produces a complete development source ZIP with migrations/release tooling and without `.git`, `.env`, uploads or runtime state.

- [ ] Run `php -l` on all changed PHP files.
- [ ] Run Redesign 02 plus 01e/01d/01c/foundation/v0.15/contact regression checks available without a database.
- [ ] Run release packaging tests.
- [ ] Verify ZIP integrity, migration presence, `VERSION=0.15.1`, and absence of secrets/runtime files.
- [ ] Deliver ZIP plus safe backup → stage → rsync → rebuild → migrate → verify → logs commands.
