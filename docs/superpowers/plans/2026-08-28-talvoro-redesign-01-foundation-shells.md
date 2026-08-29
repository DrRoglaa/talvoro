# Talvoro Redesign 01 — Design Foundation and Product Shells Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the approved Concept B Talvoro Design System foundation and redesign the shared public/CMS shells without changing Talvoro's data model, routes, deployment model, or current feature behavior.

**Architecture:** Keep `public/assets/css/app.css` as a temporary compatibility layer, then load new focused CSS files after it so redesigned shells can be introduced safely without a big-bang stylesheet rewrite. `DesignSystem.php` remains the public-theme token authority; the CMS receives a stable Talvoro product palette independent of whichever public theme is active. This milestone changes shared presentation and navigation only; it does not introduce Talvoro Editorial as the built-in theme yet and does not alter the database schema.

**Tech Stack:** PHP 8.5, MySQL, server-rendered HTML, CSS, vanilla JavaScript, existing SQL/release tooling. No Node/npm.

**Spec:** `docs/superpowers/specs/2026-08-28-talvoro-complete-redesign-design.md`

## Global Constraints

- Baseline source is Talvoro `0.15.1`; do not change `VERSION` in this plan.
- No Node/npm runtime or build dependency.
- No frontend/backend framework migration.
- Preserve all existing routes and functional behavior.
- No database migration in this plan.
- Do not delete or rename the existing `trenlume-light` built-in theme in this plan; legacy theme compatibility is handled in Plan 02.
- Do not remove `public/assets/css/app.css` in this plan. It remains a compatibility layer until later page-by-page replacement has been verified.
- New shared CSS must be scoped so public and CMS rules cannot accidentally style each other.
- Use local/system font stacks only; no external font/CDN request.
- Target WCAG 2.2 AA for new tokens/components.
- Brand colors and semantic status colors must remain separate.
- Do not inspect, copy, or package real `.env`, `storage/config.php`, sessions, logs, backups, uploads, database dumps, private keys, tokens, or passwords.
- Do not commit, push, sign a tag, publish a release, or bump the version automatically. At the end, produce only the development/source ZIP and verification evidence for user approval.

---

## File Structure for This Milestone

### New files

- `public/assets/css/talvoro-foundation.css` — product-wide tokens, reset additions, focus/motion primitives, shared button/form/status vocabulary.
- `public/assets/css/talvoro-public.css` — public shell/header/footer and public-only layout refinements.
- `public/assets/css/talvoro-admin.css` — CMS shell/sidebar/topbar/dashboard and admin-only component refinements.
- `bin/check-redesign-foundation.php` — database-independent source regression checks for this milestone.

### Modified files

- `resources/views/layouts/app.php` — CSS load order, public shell classes, CMS navigation grouping, accessible shell markup.
- `public/assets/js/admin-nav.js` — mobile drawer focus handling and state semantics.
- `app/Core/DesignSystem.php` — Concept B public-theme defaults plus depth token and stronger contrast warnings.
- `resources/views/admin/design/styles.php` — expose the new public-theme depth token and explain brand/action semantics.
- `resources/views/admin/dashboard.php` — replace metric-card wall with the approved useful Overview hierarchy.
- `docs/DESIGN-SYSTEM.md` — document new CSS responsibilities and temporary legacy layer.
- `bin/check.php` — register lightweight source assertions that should remain after the redesign foundation lands.

### Explicitly untouched

- `database/migrations/*`
- `VERSION`
- `packaging/MINIMUM_UPDATE_VERSION`
- release/signing workflow semantics
- authentication/MFA/session logic
- Page Builder storage/data schema
- public content data

---

### Task 1: Add a Failing Foundation Regression Check

**Files:**
- Create: `bin/check-redesign-foundation.php`
- Modify later in task: none

**Interfaces:**
- Consumes: repository paths via `dirname(__DIR__)` only; no database connection.
- Produces: CLI exit code `0` when the redesign foundation contract is present, `1` when any check fails.

- [ ] **Step 1: Create the source-level checker with the final expected contract**

Create `bin/check-redesign-foundation.php` with this exact structure:

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string)file_get_contents($path) : '';
};

$layout = $read('resources/views/layouts/app.php');
$foundation = $read('public/assets/css/talvoro-foundation.css');
$publicCss = $read('public/assets/css/talvoro-public.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');
$design = $read('app/Core/DesignSystem.php');
$dashboard = $read('resources/views/admin/dashboard.php');
$adminNav = $read('public/assets/js/admin-nav.js');

$checks = [
    'Foundation stylesheet exists' => $foundation !== '',
    'Public stylesheet exists' => $publicCss !== '',
    'Admin stylesheet exists' => $adminCss !== '',
    'Layout loads foundation before legacy CSS' =>
        strpos($layout, '/assets/css/talvoro-foundation.css') !== false
        && strpos($layout, '/assets/css/talvoro-foundation.css') < strpos($layout, '/assets/css/app.css'),
    'Layout loads public redesign stylesheet' => str_contains($layout, '/assets/css/talvoro-public.css'),
    'Layout loads admin redesign stylesheet' => str_contains($layout, '/assets/css/talvoro-admin.css'),
    'Concept B product tokens exist' =>
        str_contains($foundation, '--tv-ink:')
        && str_contains($foundation, '--tv-parchment:')
        && str_contains($foundation, '--tv-coral:')
        && str_contains($foundation, '--tv-sea-glass:')
        && str_contains($foundation, '--tv-indigo:'),
    'Accessible primary action token exists' => str_contains($foundation, '--tv-action-primary: #b75544'),
    'Semantic danger is separate from coral brand' =>
        str_contains($foundation, '--tv-danger:')
        && !str_contains($foundation, '--tv-danger:var(--tv-coral)'),
    'Reduced-motion contract present' => str_contains($foundation, '@media (prefers-reduced-motion: reduce)'),
    'Visible focus contract present' => str_contains($foundation, ':focus-visible'),
    'Admin shell uses final navigation groups' =>
        str_contains($layout, '>Overview<')
        && str_contains($layout, '>Content<')
        && str_contains($layout, '>Design<')
        && str_contains($layout, '>Insights<')
        && str_contains($layout, '>System<'),
    'Duplicate System navigation removed' => substr_count($layout, '<span>System</span>') === 1,
    'Admin navigation restores opener focus' => str_contains($adminNav, 'lastFocusedBeforeOpen'),
    'Public design system exposes depth token' => str_contains($design, "'depth' =>"),
    'Dashboard asks attention/change/performance/next-action questions' =>
        str_contains($dashboard, 'Needs attention')
        && str_contains($dashboard, 'Recently edited')
        && str_contains($dashboard, 'Site snapshot')
        && str_contains($dashboard, 'Quick actions'),
    'Node package metadata not introduced' => !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-foundation checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
```

- [ ] **Step 2: Run the checker and verify it fails before implementation**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: non-zero exit status with failures for missing `talvoro-foundation.css`, `talvoro-public.css`, `talvoro-admin.css`, new load-order assertions, new navigation grouping, and new Design System token.

- [ ] **Step 3: Syntax-check the checker itself**

Run:

```bash
php -l bin/check-redesign-foundation.php
```

Expected:

```text
No syntax errors detected in bin/check-redesign-foundation.php
```

- [ ] **Step 4: Review checkpoint — no commit**

Record that only `bin/check-redesign-foundation.php` was added. Do not commit or push.

---

### Task 2: Create the Canonical Talvoro Product Foundation CSS

**Files:**
- Create: `public/assets/css/talvoro-foundation.css`
- Test: `bin/check-redesign-foundation.php`

**Interfaces:**
- Produces stable `--tv-*` product tokens consumed by `talvoro-admin.css` and non-theme product chrome.
- Does not replace `--talvoro-*` public-theme tokens produced by `DesignSystem::css()`.

- [ ] **Step 1: Add Concept B product tokens with accessible action semantics**

Create `public/assets/css/talvoro-foundation.css` beginning with:

```css
/* Talvoro product foundation — Concept B. Product UI only; public theme tokens remain --talvoro-*. */
:root {
  --tv-ink: #24201e;
  --tv-ink-soft: #39322f;
  --tv-parchment: #f7f2ea;
  --tv-ivory: #fffdfa;
  --tv-sand: #e9ded1;
  --tv-stone: #d8ccc0;
  --tv-muted: #6f655f;

  --tv-coral: #d66f5b;
  --tv-action-primary: #b75544;
  --tv-action-primary-hover: #9f4335;
  --tv-sea-glass: #5c8f86;
  --tv-sea-glass-strong: #4c756e;
  --tv-indigo: #5c4c7c;

  --tv-success: #2f6f57;
  --tv-warning: #8a5c13;
  --tv-danger: #a43c3c;
  --tv-info: #355f8a;

  --tv-text: var(--tv-ink);
  --tv-text-secondary: #5f5651;
  --tv-text-tertiary: var(--tv-muted);
  --tv-border: #e4d9ce;
  --tv-border-strong: #cfc0b3;
  --tv-focus: #315f78;

  --tv-font-display: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif;
  --tv-font-ui: "Avenir Next", Avenir, "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
  --tv-font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  --tv-space-1: .25rem;
  --tv-space-2: .5rem;
  --tv-space-3: .75rem;
  --tv-space-4: 1rem;
  --tv-space-5: 1.25rem;
  --tv-space-6: 1.5rem;
  --tv-space-8: 2rem;
  --tv-space-10: 2.5rem;
  --tv-space-12: 3rem;
  --tv-space-16: 4rem;
  --tv-space-20: 5rem;

  --tv-radius-control: 10px;
  --tv-radius-panel: 18px;
  --tv-radius-editorial: 28px;
  --tv-shadow-raised: 0 18px 45px rgba(36, 32, 30, .09);
  --tv-shadow-dialog: 0 28px 80px rgba(36, 32, 30, .18);

  --tv-control-height-compact: 36px;
  --tv-control-height: 44px;
  --tv-control-height-large: 50px;
  --tv-content: 760px;
  --tv-wide: 1240px;
  --tv-shell: 1480px;
}
```

- [ ] **Step 2: Add shared focus, button, form, notice, badge, and motion primitives**

Append these canonical rules; retain the class names already used by existing views so the new layer upgrades them instead of requiring a mass markup rewrite:

```css
:where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
  outline: 3px solid color-mix(in srgb, var(--tv-focus) 72%, white);
  outline-offset: 3px;
}

.button {
  min-height: var(--tv-control-height);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  border: 1px solid transparent;
  border-radius: var(--tv-radius-control);
  padding: .68rem 1rem;
  font: 700 .92rem/1 var(--tv-font-ui);
  text-decoration: none;
  cursor: pointer;
}
.button:not(.secondary):not(.danger):not(.ghost) {
  background: var(--tv-action-primary);
  color: #fff;
}
.button:not(.secondary):not(.danger):not(.ghost):hover { background: var(--tv-action-primary-hover); }
.button.secondary { background: transparent; border-color: var(--tv-border-strong); color: var(--tv-text); }
.button.ghost { background: transparent; color: var(--tv-text); }
.button.danger { background: var(--tv-danger); color: #fff; }
.button.compact { min-height: var(--tv-control-height-compact); padding: .52rem .8rem; font-size: .84rem; }

:where(input:not([type="checkbox"]):not([type="radio"]):not([type="color"]), select, textarea) {
  min-height: var(--tv-control-height);
  border: 1px solid var(--tv-border-strong);
  border-radius: var(--tv-radius-control);
  background: var(--tv-ivory);
  color: var(--tv-text);
  font: 500 .95rem/1.45 var(--tv-font-ui);
}
textarea { min-height: 7rem; }

.notice {
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-control);
  padding: .9rem 1rem;
  background: var(--tv-ivory);
  color: var(--tv-text);
}
.notice.success { border-color: color-mix(in srgb, var(--tv-success) 36%, white); background: color-mix(in srgb, var(--tv-success) 8%, white); }
.notice.warning { border-color: color-mix(in srgb, var(--tv-warning) 38%, white); background: color-mix(in srgb, var(--tv-warning) 8%, white); }
.notice.error { border-color: color-mix(in srgb, var(--tv-danger) 38%, white); background: color-mix(in srgb, var(--tv-danger) 7%, white); }

.status-badge {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: .2rem .55rem;
  border-radius: 999px;
  font: 750 .75rem/1 var(--tv-font-ui);
}
.status-badge.published { background: color-mix(in srgb, var(--tv-success) 13%, white); color: var(--tv-success); }
.status-badge.draft { background: #eee9e4; color: #594f49; }
.status-badge.scheduled { background: color-mix(in srgb, var(--tv-indigo) 12%, white); color: var(--tv-indigo); }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    scroll-behavior: auto !important;
    animation-duration: .001ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: .001ms !important;
  }
}
```

- [ ] **Step 3: Verify the foundation file independently**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: token, focus, reduced-motion, action/danger separation checks now pass; shell/load/navigation checks still fail.

- [ ] **Step 4: Check that no Node tooling appeared**

Run:

```bash
test ! -e package.json && test ! -d node_modules && echo "PASS: no Node/npm introduced"
```

Expected:

```text
PASS: no Node/npm introduced
```

- [ ] **Step 5: Review checkpoint — no commit**

Inspect only the new foundation stylesheet and checker output. Do not commit or push.

---

### Task 3: Load the New CSS Layers and Redesign the Public Shell

**Files:**
- Create: `public/assets/css/talvoro-public.css`
- Modify: `resources/views/layouts/app.php:197-206, 300-end public shell section`
- Test: `bin/check-redesign-foundation.php`

**Interfaces:**
- CSS order: `talvoro-foundation.css` -> legacy `app.css` -> `talvoro-public.css` or `talvoro-admin.css` -> `/theme.css` for public/theme-specific overrides.
- Existing `/theme.css` route remains unchanged.
- Existing public menu data from `Menus::publicTree()` and `Pages::navigation()` remains unchanged.

- [ ] **Step 1: Change the stylesheet loading contract**

Replace the current single-app-css block around lines 197-200 with this ordering:

```php
<link rel="stylesheet" href="/assets/css/talvoro-foundation.css?v=<?= e(app_version()) ?>">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= e(app_version()) ?>">
<?php if ($isAdminArea): ?>
    <link rel="stylesheet" href="/assets/css/talvoro-admin.css?v=<?= e(app_version()) ?>">
<?php elseif (!$isPrivateAuth): ?>
    <link rel="stylesheet" href="/assets/css/talvoro-public.css?v=<?= e(app_version()) ?>">
<?php endif; ?>
<?php if (!$isAdminRequest || $publicPreview): ?>
    <link rel="stylesheet" href="/theme.css?v=<?= e(app_version()) ?>">
<?php endif; ?>
```

Do not remove the legacy `app.css` link yet.

- [ ] **Step 2: Add explicit shell scope classes without changing route/data behavior**

Change the public wrapper from:

```php
<div class="public-shell">
```

to:

```php
<div class="public-shell talvoro-public-shell" data-public-shell>
```

Change the public header from:

```php
<header class="public-nav">
```

to:

```php
<header class="public-nav talvoro-public-header">
```

Change the footer from:

```php
<footer class="public-footer rich-public-footer">
```

to:

```php
<footer class="public-footer rich-public-footer talvoro-public-footer">
```

Keep the existing dynamic site name/logo/menu/footer content intact in this task.

- [ ] **Step 3: Create the public shell stylesheet**

Create `public/assets/css/talvoro-public.css` with these core rules:

```css
body.public-body {
  margin: 0;
  color: var(--talvoro-text, var(--tv-text));
  background: var(--talvoro-bg, var(--tv-parchment));
}

.talvoro-public-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.talvoro-public-header {
  width: min(calc(100% - 2rem), var(--tv-shell));
  margin: 1rem auto 0;
  min-height: 72px;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: .75rem 1rem;
  border: 1px solid color-mix(in srgb, var(--talvoro-border, var(--tv-border)) 82%, transparent);
  border-radius: 18px;
  background: color-mix(in srgb, var(--talvoro-surface, var(--tv-ivory)) 92%, transparent);
  backdrop-filter: blur(16px);
}

.talvoro-public-header .public-brand { margin-right: auto; }
.talvoro-public-header > nav { display: flex; align-items: center; gap: .3rem; }
.talvoro-public-header > nav a,
.talvoro-public-header .public-nav-group > a {
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  padding: .45rem .72rem;
  border-radius: 9px;
  color: var(--talvoro-text, var(--tv-text));
  font-weight: 650;
  text-decoration: none;
}
.talvoro-public-header > nav a:hover,
.talvoro-public-header > nav a.is-active,
.talvoro-public-header .public-nav-group.is-active > a {
  background: color-mix(in srgb, var(--talvoro-brand, var(--tv-coral)) 9%, transparent);
}

.public-main { flex: 1; }

.talvoro-public-footer {
  width: min(calc(100% - 2rem), var(--tv-shell));
  margin: clamp(4rem, 8vw, 8rem) auto 1rem;
  border-radius: var(--tv-radius-editorial);
  background: var(--tv-ink);
  color: #f9f4ee;
}
.talvoro-public-footer :where(a, p, small, span) { color: inherit; }
.talvoro-public-footer a:hover { color: #fff; }

@media (max-width: 900px) {
  .talvoro-public-header { margin-top: .5rem; width: min(calc(100% - 1rem), var(--tv-shell)); min-height: 62px; }
  .talvoro-public-header > nav { display: none; }
  .talvoro-public-footer { width: min(calc(100% - 1rem), var(--tv-shell)); border-radius: 20px; }
}
```

Do not redesign page-builder/home content in this file; that belongs to Plan 02.

- [ ] **Step 4: PHP-syntax check the shared layout**

Run:

```bash
php -l resources/views/layouts/app.php
```

Expected: no syntax errors.

- [ ] **Step 5: Re-run the foundation checker**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: CSS existence/load checks pass; admin shell/navigation, Design System depth, dashboard, and opener-focus checks still fail.

- [ ] **Step 6: Review checkpoint — no commit**

Open a public page in the current local/Docker environment and verify only shell-level change: header/footer adopt new geometry while page content still renders with legacy-compatible styling. Do not change content or theme data.

---

### Task 4: Rebuild the CMS Shell and Navigation Grouping

**Files:**
- Create: `public/assets/css/talvoro-admin.css`
- Modify: `resources/views/layouts/app.php:210-303`
- Test: `bin/check-redesign-foundation.php`

**Interfaces:**
- Existing route URLs and Gate permissions remain unchanged.
- Sidebar groups become Overview / Content / Design / Insights / System.
- No current admin destination becomes unreachable.

- [ ] **Step 1: Replace sidebar group labels/order without changing route targets**

Use this group order in `resources/views/layouts/app.php`:

```text
Overview
  Overview

Content
  Pages
  Posts
  Categories
  [dynamic custom content types]
  Media
  Contact submissions
  Blog settings

Design
  Styles
  Patterns
  Themes
  Menus
  Content models

Insights
  Analytics
  SEO
  Redirects
  Site health

System
  Site mode
  Email
  Security
  Users
  System
```

Keep every existing `Gate::allows(...)` guard. Remove the current duplicated System link so `<span>System</span>` appears exactly once as a navigation destination.

Use `<span class="cms-nav-label">Overview</span>`, `Content`, `Design`, `Insights`, and `System` as the only sidebar group labels.

- [ ] **Step 2: Add shell scope/state attributes**

Change:

```php
<div class="shell admin-shell" data-admin-shell>
```

to:

```php
<div class="shell admin-shell talvoro-admin-shell" data-admin-shell data-nav-state="closed">
```

Add the admin main content target:

```php
<main class="main admin-main" id="cms-main-content" tabindex="-1"><?= $content ?></main>
```

Add a skip link immediately inside the admin shell:

```php
<a class="cms-skip-link" href="#cms-main-content">Skip to content</a>
```

- [ ] **Step 3: Create the dark-sidebar/light-workspace stylesheet**

Create `public/assets/css/talvoro-admin.css` with the initial shell rules:

```css
body.admin-body {
  margin: 0;
  background: var(--tv-parchment);
  color: var(--tv-text);
  font-family: var(--tv-font-ui);
}

.talvoro-admin-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 264px minmax(0, 1fr);
  background: var(--tv-parchment);
}

.cms-sidebar {
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--tv-ink);
  color: #f7f1eb;
  border-right: 1px solid rgba(255,255,255,.08);
}
.cms-sidebar :where(a, button) { color: inherit; }
.cms-sidebar-head { padding: 1.1rem 1rem .8rem; }
.cms-sidebar-nav { padding: .35rem .65rem 1rem; overflow-y: auto; }
.cms-nav-group + .cms-nav-group { margin-top: 1.1rem; }
.cms-nav-label {
  display: block;
  padding: 0 .7rem .4rem;
  color: #b9ada5;
  font-size: .69rem;
  font-weight: 800;
  letter-spacing: .09em;
  text-transform: uppercase;
}
.cms-nav-link {
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: .5rem .68rem;
  border-radius: 9px;
  text-decoration: none;
  color: #eee6df;
}
.cms-nav-link:hover { background: rgba(255,255,255,.07); }
.cms-nav-link.is-active {
  background: color-mix(in srgb, var(--tv-coral) 22%, transparent);
  color: #fff;
}
.cms-nav-icon { color: #cbbeb5; }
.cms-nav-link.is-active .cms-nav-icon { color: #fff; }

.cms-workspace { min-width: 0; background: var(--tv-parchment); }
.cms-topbar {
  min-height: 72px;
  position: sticky;
  top: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .8rem clamp(1rem, 2.5vw, 2.25rem);
  border-bottom: 1px solid var(--tv-border);
  background: color-mix(in srgb, var(--tv-ivory) 94%, transparent);
  backdrop-filter: blur(14px);
}
.admin-main {
  width: min(calc(100% - 2rem), 1320px);
  margin-inline: auto;
  padding: clamp(1.3rem, 2.5vw, 2.4rem) 0 4rem;
}

.cms-skip-link {
  position: fixed;
  left: 1rem;
  top: .5rem;
  z-index: 1000;
  transform: translateY(-180%);
  padding: .65rem .85rem;
  border-radius: var(--tv-radius-control);
  background: var(--tv-ivory);
  color: var(--tv-ink);
}
.cms-skip-link:focus { transform: translateY(0); }

@media (max-width: 980px) {
  .talvoro-admin-shell { display: block; }
  .cms-sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: min(86vw, 320px);
    z-index: 60;
    transform: translateX(-105%);
    transition: transform 180ms ease;
  }
  .talvoro-admin-shell.nav-open .cms-sidebar { transform: translateX(0); }
  .admin-main { width: min(calc(100% - 1.25rem), 1320px); }
}
```

Use new CSS only to override shell primitives; do not delete the old sidebar rules from `app.css` yet.

- [ ] **Step 4: PHP-syntax check the layout**

Run:

```bash
php -l resources/views/layouts/app.php
```

Expected: no syntax errors.

- [ ] **Step 5: Run the regression checker**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: new admin CSS and final navigation-group checks pass. Design token/dashboard/focus checks may still fail.

- [ ] **Step 6: Review checkpoint — no commit**

At 1440px verify dark ink sidebar + warm workspace. At 390px verify sidebar remains off-canvas until opened and main content remains full width. Confirm every old admin link is still reachable.

---

### Task 5: Make the Mobile CMS Drawer Keyboard-Safe

**Files:**
- Modify: `public/assets/js/admin-nav.js`
- Test: `bin/check-redesign-foundation.php`

**Interfaces:**
- Uses existing `[data-admin-shell]`, `[data-admin-nav]`, `[data-admin-nav-toggle]`, `[data-admin-nav-close]` hooks.
- Produces opener-focus restoration and mobile-only `aria-hidden` state without changing desktop navigation behavior.

- [ ] **Step 1: Add opener tracking and a mobile media query**

Near the existing declarations add:

```js
const mobileNav = matchMedia('(max-width: 980px)');
let lastFocusedBeforeOpen = null;
```

- [ ] **Step 2: Replace `setOpen` with an accessible state transition**

Use this implementation:

```js
const setOpen = (open) => {
  if (open) lastFocusedBeforeOpen = document.activeElement;

  shell.classList.toggle('nav-open', open);
  shell.dataset.navState = open ? 'open' : 'closed';
  document.body.classList.toggle('admin-nav-open', open);
  toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

  if (mobileNav.matches) {
    nav.setAttribute('aria-hidden', open ? 'false' : 'true');
  } else {
    nav.removeAttribute('aria-hidden');
  }

  if (open) {
    requestAnimationFrame(() => {
      revealActiveInsidePane();
      const firstTarget = nav.querySelector('.cms-nav-link.is-active, a, button');
      firstTarget?.focus();
    });
  } else if (lastFocusedBeforeOpen instanceof HTMLElement) {
    lastFocusedBeforeOpen.focus();
  }
};
```

- [ ] **Step 3: Add Tab containment while the mobile drawer is open**

Inside the existing `document.addEventListener('keydown', ...)`, handle Tab before Escape:

```js
if (event.key === 'Tab' && shell.classList.contains('nav-open') && mobileNav.matches) {
  const focusable = [...nav.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')]
    .filter((element) => !element.hasAttribute('hidden'));
  if (focusable.length) {
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }
}
```

Retain Escape handling but let `setOpen(false)` perform focus restoration; remove the separate unconditional `toggle.focus()` call if present.

- [ ] **Step 4: Initialize `aria-hidden` from viewport state and keep it updated**

Add:

```js
const syncViewportState = () => {
  if (mobileNav.matches) nav.setAttribute('aria-hidden', shell.classList.contains('nav-open') ? 'false' : 'true');
  else nav.removeAttribute('aria-hidden');
};

syncViewportState();
mobileNav.addEventListener?.('change', (event) => {
  if (!event.matches) setOpen(false);
  syncViewportState();
});
```

Remove the old independent `matchMedia('(min-width: 981px)')...` listener so viewport state has one owner.

- [ ] **Step 5: Run the foundation checker**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: `Admin navigation restores opener focus` passes.

- [ ] **Step 6: Manually keyboard-test the drawer**

At a viewport under 981px:

1. Tab to the menu button.
2. Press Enter.
3. Verify focus moves into the sidebar.
4. Press Shift+Tab on the first focusable item and verify focus stays in the drawer.
5. Press Tab on the last focusable item and verify focus wraps.
6. Press Escape and verify the drawer closes and focus returns to the menu button.

- [ ] **Step 7: Review checkpoint — no commit**

Do not change Page Builder/editor keyboard behavior in this task; that belongs to later plans.

---

### Task 6: Evolve Public Theme Tokens to the Approved Concept B Palette

**Files:**
- Modify: `app/Core/DesignSystem.php`
- Modify: `resources/views/admin/design/styles.php`
- Test: `bin/check-redesign-foundation.php`

**Interfaces:**
- Existing keys remain valid.
- Adds one new optional setting key: `depth`.
- Missing `depth` setting automatically falls back to the default; no SQL migration is required because theme token settings are already key/value settings.
- Existing custom themes continue to receive their current saved token values.

- [ ] **Step 1: Change public-theme defaults and add `depth`**

In `DesignSystem::definitions()` use:

```php
'brand' => ['label' => 'Primary accent', 'type' => 'color', 'default' => '#b75544'],
'accent' => ['label' => 'Sea glass accent', 'type' => 'color', 'default' => '#5c8f86'],
'depth' => ['label' => 'Depth accent', 'type' => 'color', 'default' => '#5c4c7c'],
'background' => ['label' => 'Page background', 'type' => 'color', 'default' => '#f7f2ea'],
'surface' => ['label' => 'Surface', 'type' => 'color', 'default' => '#fffdfa'],
'text' => ['label' => 'Text', 'type' => 'color', 'default' => '#24201e'],
'muted' => ['label' => 'Muted text', 'type' => 'color', 'default' => '#6f655f'],
'border' => ['label' => 'Border', 'type' => 'color', 'default' => '#e4d9ce'],
```

Keep all existing typography/layout controls and validation mechanics.

- [ ] **Step 2: Emit the depth token from `DesignSystem::css()`**

In the generated `:root` block add:

```php
--talvoro-depth:{$v['depth']};
```

Do not remove existing `--talvoro-brand`, `--talvoro-accent`, or compatibility tokens.

- [ ] **Step 3: Strengthen contrast warnings**

Replace the current `$pairs` array with:

```php
$pairs = [
    ['text', 'background', 'Main text against page background'],
    ['text', 'surface', 'Main text against cards and surfaces'],
    ['muted', 'background', 'Muted text against page background'],
    ['muted', 'surface', 'Muted text against cards and surfaces'],
];
```

Then add a brand-action warning after the loop:

```php
$brandWhite = self::contrastRatio('#ffffff', $values['brand']);
if ($brandWhite < 4.5) {
    $warnings[] = 'Primary accent with white action text has low contrast (' . number_format($brandWhite, 2) . ':1). Choose a darker primary accent or use a different action treatment.';
}
```

- [ ] **Step 4: Export the new token**

Add this entry to `tokenExport()`:

```php
'color.depth' => $v['depth'],
```

- [ ] **Step 5: Expose `depth` in the Styles UI**

Change the color loop in `resources/views/admin/design/styles.php` to:

```php
<?php foreach (['brand','accent','depth','background','surface','text','muted','border'] as $key): $def=$definitions[$key]; ?>
```

Change the Colors explanatory copy to:

```html
<p class="muted">Set the theme's editorial accents and neutral foundation. Brand accents are visual identity; success, warning and destructive states remain separate Talvoro system colors.</p>
```

- [ ] **Step 6: Syntax-check both PHP files**

Run:

```bash
php -l app/Core/DesignSystem.php
php -l resources/views/admin/design/styles.php
```

Expected: no syntax errors.

- [ ] **Step 7: Run the foundation checker**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: `Public design system exposes depth token` passes.

- [ ] **Step 8: Review checkpoint — no commit**

Verify no database migration was added and `VERSION` still contains `0.15.1`.

---

### Task 7: Redesign the Overview Dashboard Around User Questions

**Files:**
- Modify: `resources/views/admin/dashboard.php`
- No controller changes in this milestone.
- Test: `bin/check-redesign-foundation.php`

**Interfaces:**
- Consumes existing `$stats`, `$postCounts`, `$recentPosts`, `Settings::siteMode()`, and Gate permissions.
- Does not add queries or change dashboard permissions.

- [ ] **Step 1: Replace the current page-header/metric-card wall with a concise hero and quick actions**

Use this top structure:

```php
<header class="page-header overview-header">
    <div>
        <p class="eyebrow">Overview</p>
        <h1>Your site at a glance</h1>
        <p class="muted">Publish, review what changed, and see what needs attention without digging through the CMS.</p>
    </div>
    <div class="overview-quick-actions" aria-label="Quick actions">
        <?php if (Gate::allows('content.edit')): ?><a class="button" href="<?= e(admin_url('/posts/new')) ?>">New post</a><?php endif; ?>
        <?php if (Gate::allows('pages.edit')): ?><a class="button secondary" href="<?= e(admin_url('/pages/new')) ?>">New page</a><?php endif; ?>
        <a class="button ghost" href="/" target="_blank" rel="noopener">View site ↗</a>
    </div>
</header>
```

Use `admin_url('/...')` consistently rather than concatenating the admin base manually in new markup.

- [ ] **Step 2: Add a two-column Overview layout with the four approved questions**

Use these section headings exactly so the contract is clear:

```html
<div class="overview-layout">
  <div class="overview-primary stack">
    <section class="admin-surface overview-attention">
      <div class="section-heading"><div><p class="eyebrow">What needs attention?</p><h2>Needs attention</h2></div></div>
      <!-- derived site-mode/draft/scheduled messages -->
    </section>

    <section class="admin-surface">
      <div class="section-heading"><div><p class="eyebrow">What changed?</p><h2>Recently edited</h2></div></div>
      <!-- existing recentPosts content list -->
    </section>
  </div>

  <aside class="overview-secondary stack">
    <section class="admin-surface">
      <div class="section-heading"><div><p class="eyebrow">How is the site doing?</p><h2>Site snapshot</h2></div></div>
      <!-- compact stats, not four standalone metric cards -->
    </section>

    <section class="admin-surface">
      <div class="section-heading"><div><p class="eyebrow">What can I do next?</p><h2>Quick actions</h2></div></div>
      <!-- permission-aware text links -->
    </section>
  </aside>
</div>
```

- [ ] **Step 3: Derive attention items only from existing data**

Inside `Needs attention`, render:

```php
<ul class="attention-list">
    <?php if ($siteMode !== 'live'): ?>
        <li><span class="attention-dot warning" aria-hidden="true"></span><div><strong>Public site is in development mode.</strong><?php if (Gate::allows('site.manage')): ?> <a href="<?= e(admin_url('/site-mode')) ?>">Review site mode</a><?php endif; ?></div></li>
    <?php endif; ?>
    <?php if ((int)$postCounts['draft'] > 0): ?>
        <li><span class="attention-dot neutral" aria-hidden="true"></span><div><strong><?= (int)$postCounts['draft'] ?> draft post<?= (int)$postCounts['draft'] === 1 ? '' : 's' ?></strong> waiting for editorial review.</div></li>
    <?php endif; ?>
    <?php if ((int)$postCounts['scheduled'] > 0): ?>
        <li><span class="attention-dot info" aria-hidden="true"></span><div><strong><?= (int)$postCounts['scheduled'] ?> scheduled post<?= (int)$postCounts['scheduled'] === 1 ? '' : 's' ?></strong> queued for publishing.</div></li>
    <?php endif; ?>
    <?php if ($siteMode === 'live' && (int)$postCounts['draft'] === 0 && (int)$postCounts['scheduled'] === 0): ?>
        <li class="attention-clear"><span class="attention-dot success" aria-hidden="true"></span><div><strong>Nothing urgent.</strong> Your public site is live and there are no draft or scheduled posts needing attention.</div></li>
    <?php endif; ?>
</ul>
```

Do not invent update/security alerts until Plan 06 provides the proper data contract.

- [ ] **Step 4: Render the Site snapshot as one compact definition list**

Use existing values only:

```php
<dl class="snapshot-list">
    <div><dt>Posts</dt><dd><?= (int)$stats['posts'] ?></dd></div>
    <div><dt>Published</dt><dd><?= (int)$stats['published'] ?></dd></div>
    <div><dt>Page views · 24h</dt><dd><?= (int)$stats['events'] ?></dd></div>
    <div><dt>CMS users</dt><dd><?= (int)$stats['users'] ?></dd></div>
</dl>
```

- [ ] **Step 5: Add dashboard-specific CSS to `talvoro-admin.css`**

Append:

```css
.overview-header { align-items: flex-end; }
.overview-quick-actions { display: flex; flex-wrap: wrap; gap: .55rem; }
.overview-layout { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(280px, .85fr); gap: 1rem; }
.admin-surface {
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-panel);
  background: var(--tv-ivory);
  padding: clamp(1rem, 2vw, 1.4rem);
}
.attention-list, .snapshot-list { margin: 0; padding: 0; list-style: none; }
.attention-list li { display: flex; gap: .7rem; padding: .72rem 0; border-top: 1px solid var(--tv-border); }
.attention-list li:first-child { border-top: 0; }
.attention-dot { width: 9px; height: 9px; margin-top: .42rem; flex: 0 0 auto; border-radius: 50%; background: var(--tv-muted); }
.attention-dot.warning { background: var(--tv-warning); }
.attention-dot.info { background: var(--tv-info); }
.attention-dot.success { background: var(--tv-success); }
.snapshot-list > div { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; padding: .7rem 0; border-top: 1px solid var(--tv-border); }
.snapshot-list > div:first-child { border-top: 0; }
.snapshot-list dt { color: var(--tv-text-secondary); }
.snapshot-list dd { margin: 0; font-size: 1.35rem; font-weight: 800; }
@media (max-width: 900px) { .overview-layout { grid-template-columns: 1fr; } }
```

- [ ] **Step 6: Syntax-check and regression-check**

Run:

```bash
php -l resources/views/admin/dashboard.php
php bin/check-redesign-foundation.php
```

Expected: dashboard contract now passes; all foundation checks should pass after prior tasks.

- [ ] **Step 7: Review the dashboard at 1440px and 390px**

Verify:

- desktop shows a clear primary column plus restrained secondary summary;
- mobile becomes one readable column;
- no horizontal overflow;
- empty recent-post state remains useful;
- no fake analytics/security information is shown.

- [ ] **Step 8: Review checkpoint — no commit**

Do not add new dashboard database queries in this milestone.

---

### Task 8: Wire the Foundation into Existing Checks and Document the CSS Contract

**Files:**
- Modify: `bin/check.php`
- Modify: `docs/DESIGN-SYSTEM.md`
- Test: `bin/check-redesign-foundation.php`, `bin/check.php`

**Interfaces:**
- `bin/check.php` gains only source/file assertions that are valid in every installed environment.
- No existing check is removed or weakened.

- [ ] **Step 1: Add durable source assertions to `bin/check.php`**

Near the existing source-file checks add:

```php
$foundationCss = (string)@file_get_contents(base_path('public/assets/css/talvoro-foundation.css'));
$publicRedesignCss = (string)@file_get_contents(base_path('public/assets/css/talvoro-public.css'));
$adminRedesignCss = (string)@file_get_contents(base_path('public/assets/css/talvoro-admin.css'));
$checks['Talvoro redesign foundation stylesheet present'] = str_contains($foundationCss, '--tv-action-primary: #b75544');
$checks['Talvoro public shell stylesheet present'] = str_contains($publicRedesignCss, '.talvoro-public-shell');
$checks['Talvoro admin shell stylesheet present'] = str_contains($adminRedesignCss, '.talvoro-admin-shell');
$checks['Talvoro reduced-motion foundation present'] = str_contains($foundationCss, '@media (prefers-reduced-motion: reduce)');
```

Do not make `bin/check.php` depend on a browser or Node tool.

- [ ] **Step 2: Document the temporary four-layer CSS model**

Add a section to `docs/DESIGN-SYSTEM.md` containing:

```markdown
## Redesign CSS architecture

During the complete redesign, Talvoro intentionally uses four CSS layers:

1. `talvoro-foundation.css` — product tokens, focus/motion, shared control/status primitives.
2. `app.css` — temporary legacy compatibility layer retained while screens are migrated.
3. `talvoro-public.css` or `talvoro-admin.css` — redesigned product-shell and context-specific rules.
4. `/theme.css` — active public-theme tokens/CSS; loaded only for public rendering and public previews.

`app.css` is not the future source of truth. Later redesign plans move verified rules into focused stylesheets and delete the corresponding legacy rules only after visual/regression checks pass.

The CMS product palette (`--tv-*`) is independent of the active public theme. Public content/theme components use `--talvoro-*` semantic tokens produced by `DesignSystem`.
```

Also document the approved token roles: Ink, Parchment/Ivory, Coral/Terracotta, Sea Glass, Indigo/Plum, and separate semantic success/warning/danger/info.

- [ ] **Step 3: Syntax-check all changed PHP files**

Run:

```bash
php -l bin/check-redesign-foundation.php
php -l bin/check.php
php -l app/Core/DesignSystem.php
php -l resources/views/layouts/app.php
php -l resources/views/admin/design/styles.php
php -l resources/views/admin/dashboard.php
```

Expected: every command reports no syntax errors.

- [ ] **Step 4: Run the standalone redesign checker**

Run:

```bash
php bin/check-redesign-foundation.php
```

Expected: all redesign-foundation checks pass.

- [ ] **Step 5: Run the existing version-specific regression check**

Run:

```bash
php bin/check-v0150.php
```

Expected: all existing v0.15 checks pass. If this checker requires an installed/database-backed environment, run it inside the existing Talvoro Docker container rather than weakening/skipping assertions.

- [ ] **Step 6: Run the full existing check in the installed Docker environment**

Run inside the existing application container using its current established command/path:

```bash
php bin/check.php
```

Expected: all existing checks plus the new redesign source checks pass. Do not modify the user's runtime database to make a visual check pass.

- [ ] **Step 7: Run release-packaging regression tests without changing the version**

Run:

```bash
./scripts/release/test-release.sh
```

Expected final line:

```text
All Talvoro release-packaging tests passed.
```

- [ ] **Step 8: Build only the development/source artifact for server review**

Run:

```bash
./scripts/release/build-source.sh
```

Expected artifact:

```text
dist/talvoro-v0.15.1.zip
```

This is a development review package, not an official release. Do not build/sign/publish the official three-artifact release yet.

- [ ] **Step 9: Verify the development ZIP contains migrations and excludes private/runtime data**

Run:

```bash
unzip -l dist/talvoro-v0.15.1.zip | grep 'database/migrations/022_contact_forms.sql'
unzip -l dist/talvoro-v0.15.1.zip | grep -E 'talvoro/(\.env$|storage/config\.php$|storage/sessions/|storage/logs/|storage/backups/|public/uploads/)' && exit 1 || true
```

Expected:

- first command finds `database/migrations/022_contact_forms.sql`;
- second command produces no matches.

- [ ] **Step 10: Confirm technology/release constraints did not drift**

Run:

```bash
test "$(cat VERSION)" = "0.15.1"
test ! -e package.json
test ! -d node_modules
grep -q '"php": "\^8.5"' composer.json
```

Expected: exit status `0`.

- [ ] **Step 11: Visual acceptance matrix**

In the user's local/server Docker environment, capture/review these surfaces at `1440px`, `768px`, and `390px` widths:

```text
Public homepage shell
Public normal page shell
CMS Overview
CMS Design > Styles
CMS Security (regression surface)
CMS Media (regression surface)
Private login screen (legacy-compatible; must not be broken)
```

For each surface verify:

```text
No horizontal overflow
No clipped navigation
Visible keyboard focus
Readable contrast
Public and CMS remain visually related but not identical
Dark sidebar only appears in CMS
Public theme.css still applies to public rendering/preview
Existing actions and routes still work
Reduced-motion preference does not remove required state changes
```

- [ ] **Step 12: Stop for user review — no commit/push/tag/version bump**

Provide:

```text
Changed-file list
Standalone redesign check result
Existing regression check result
Release-packaging test result
Development source ZIP path
Desktop/tablet/mobile visual review notes
Known remaining legacy CSS areas intentionally deferred to later plans
```

Do not proceed to Plan 02 until the user approves this milestone.

---

## Plan 01 Completion Criteria

Plan 01 is complete only when all of the following are true:

- Concept B product tokens exist in a dedicated foundation stylesheet.
- Public and CMS shells use separate focused stylesheets while legacy `app.css` remains a compatibility layer.
- CMS has dark ink sidebar + warm workspace at desktop and an accessible mobile drawer.
- CMS navigation visibly follows the approved five-group information architecture without dropping existing destinations.
- Duplicate System navigation is removed.
- Public theme tokens include the approved depth accent and stronger contrast checks.
- Dashboard answers Needs attention / Recently edited / Site snapshot / Quick actions using existing data only.
- No schema migration was added.
- No Node/npm tooling was introduced.
- `VERSION` is still `0.15.1`.
- Existing regression/release packaging checks pass.
- Development source ZIP includes SQL migrations and excludes private/runtime data.
- User has visually reviewed the milestone before any commit/push/tag/release action.

