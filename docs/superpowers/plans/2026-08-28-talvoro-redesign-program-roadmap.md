# Talvoro Complete Redesign Program Roadmap

> **For agentic workers:** This roadmap decomposes the approved redesign into independently testable implementation plans. Do not execute the whole roadmap as one change. Each numbered plan gets its own detailed plan and approval/checkpoint.

**Goal:** Deliver the approved Talvoro complete redesign without a big-bang rewrite, while preserving PHP/MySQL/HTML/CSS/vanilla-JavaScript runtime, existing content/data, Docker + traditional hosting, and the signed three-artifact release workflow.

**Architecture:** The program proceeds from shared visual foundations to public/theme work, CMS productivity surfaces, Page Builder/editor workflows, operational features, then targeted backend cleanup and final release hardening. Existing functional behavior remains in place unless a specific plan replaces it with a tested equivalent.

**Tech Stack:** PHP 8.5, MySQL, server-rendered HTML, CSS, vanilla JavaScript, SQL migrations, Docker, existing release shell/Python tooling. No Node/npm.

**Spec:** `docs/superpowers/specs/2026-08-28-talvoro-complete-redesign-design.md`

## Global Constraints

- Baseline source is Talvoro `0.15.1`; do not bump `VERSION` at the start of redesign work.
- No Node/npm runtime or build dependency.
- No React/Vue/Angular, Laravel/Symfony, Redis, mandatory queue workers, or proprietary SaaS dependency.
- Preserve PHP/MySQL/HTML/CSS/vanilla JavaScript.
- Preserve Docker and traditional PHP/MySQL web-hosting targets.
- Preserve existing authentication, MFA, users, CSRF/authorization, migrations, publishing lifecycle, revisions/autosave/trash, structured content, Page Builder data, media, menus, SEO, redirects, analytics, forms, backups, updates, installer, and release integrity unless a later plan explicitly replaces an implementation with a tested equivalent.
- Existing Talvoro 0.15.1 sites must remain upgradeable without losing content or runtime data.
- SQL migrations remain source-controlled under `database/migrations/` and must ship in packages.
- Do not include real `.env`, `storage/config.php`, sessions, logs, backups, runtime uploads, database dumps, private keys, tokens, or other secrets in development/release ZIPs.
- Do not commit, push, sign a tag, or publish a release automatically. The user approves code first; official workflow remains dev branch -> signed tag -> release workflow.
- Official release continues to produce source ZIP, Docker ZIP, and Web Hosting ZIP with checksums/signatures.
- Trenlume is a quality/completeness benchmark only; Talvoro must keep its own Concept B identity.
- Target WCAG 2.2 AA for redesigned components and workflows.
- Use semantic design tokens; brand colors and semantic status colors remain distinct.
- Keep public rendering server-side and CMS progressively enhanced rather than converted to an SPA.

---

## Plan 01 — Design Foundation and Product Shells

**Deliverable:** A production-ready Concept B design foundation, redesigned public header/footer shell, redesigned dark-sidebar/light-workspace CMS shell, canonical primitive styles, and a useful redesigned dashboard — while existing pages/features continue working.

**Primary files:**

- `public/assets/css/talvoro-foundation.css` (new)
- `public/assets/css/talvoro-public.css` (new)
- `public/assets/css/talvoro-admin.css` (new)
- `resources/views/layouts/app.php`
- `resources/views/admin/dashboard.php`
- `resources/views/admin/design/styles.php`
- `public/assets/js/admin-nav.js`
- `app/Core/DesignSystem.php`
- `bin/check-redesign-foundation.php` (new)

**No schema migration. No theme deletion. No version bump.**

Detailed plan: `docs/superpowers/plans/2026-08-28-talvoro-redesign-01-foundation-shells.md`

---

## Plan 02 — Talvoro Editorial Theme and Public Product Site

**Deliverable:** Introduce `Talvoro Editorial` as the canonical built-in theme and rebuild Talvoro's own public site around the approved story-driven Concept B direction while preserving legacy theme compatibility.

**Scope:**

- New built-in Talvoro Editorial theme identity and defaults.
- Compatibility path for existing `trenlume-light` installations; no abrupt deletion.
- Remove user-visible Trenlume/Spottina legacy naming from new/default output.
- Rebuild public homepage using real Talvoro Page Builder data/patterns.
- Product, Themes/Showcase, Resources, Self-hosting, Community/Open Source, Help/Support public architecture.
- New public header/footer behavior and responsive layouts.
- Dogfood Talvoro Page Builder, menus, media, forms, SEO, and structured content.
- Add migration only if required to introduce theme identity/default content safely.
- Preserve custom themes and active-site compatibility.

**Verification:** Fresh install, upgraded 0.15.1 install, old custom theme render, Talvoro Editorial render, public responsive/a11y checks, SEO metadata/sitemap checks.

---

## Plan 03 — CMS Information Architecture and Core Admin Surfaces

**Deliverable:** Complete the approved CMS organization so common tasks are obvious and advanced features remain easy to reach without sidebar overload.

**Scope:**

- Final Overview / Content / Design / Insights / System IA.
- Content landing page with Pages, Posts, structured content types, Media, Forms.
- Design landing page with Builder, Themes, Patterns, Components, Navigation.
- Insights landing page with Analytics, SEO, Redirects, Site Health.
- System landing page with Users/Security, Backups/Updates, Settings, Advanced.
- Permission-aware navigation and empty states.
- Command palette foundation (`Cmd+K` / `Ctrl+K`) for actions and content.
- Reusable admin partial/component renderer where useful.
- Mobile section navigation optimized for real administrative tasks.

**Verification:** Permission matrix, keyboard navigation, 390/768/1440 layouts, routes unchanged unless explicitly migrated with compatibility redirects.

---

## Plan 04 — Content Editing and Structured Content UX

**Deliverable:** A flagship content editor with clear Save/Publish state, contextual inspector, revisions/autosave/trash integration, and friendly structured-content editing.

**Scope:**

- Shared editor shell for pages/posts/structured entries where practical.
- Central content workspace + contextual right inspector.
- Explicit Saving / Saved / Unsaved / Save failed states.
- Save vs Publish distinction.
- Preview, scheduling, slug, template, featured media, taxonomy, SEO integration.
- Friendly content-type names; advanced schema settings progressively disclosed.
- Field-level validation + error summaries.
- Revision/history and Trash flows redesigned without changing data semantics.
- Mobile editing optimized for text/status/metadata tasks.

**Verification:** Autosave conflict tests, revision restore, trash/restore/permanent delete, scheduled publishing, validation/a11y checks.

---

## Plan 05 — Page Builder 3 Experience on Existing Page Builder Data

**Deliverable:** A structured visual composition experience that preserves compatible existing Page Builder data while making Patterns the recommended starting point.

**Scope:**

- Component registry boundary around existing block types.
- Blocks / Sections / Patterns / Dynamic Content / Media model.
- Real-theme canvas preview rather than approximate markup.
- Contextual selection toolbar and right inspector.
- Page Structure tree with keyboard-capable reorder.
- Pattern preview/library categories.
- Saved Pattern vs Global Section semantics.
- Dynamic content collections bound to structured content models.
- Forms and Media Library first-class builder integrations.
- Responsive defaults belong to components; no per-device pixel micromanagement.
- Compatibility alias/migration for legacy `spottina-*` presentation names without data loss.

**Verification:** Existing 0.15.1 builder page round-trip, pattern insertion, drag and keyboard reorder, public/preview equivalence, mobile output, component schema validation.

---

## Plan 06 — Media, Forms, Insights, Security Center, and System UX

**Deliverable:** Bring the operational CMS areas to the same visual/product quality as the editor and builder.

**Scope:**

- Media grid/list, metadata inspector, alt text, usage references, safer deletion.
- Forms + submissions unified around each form; delivery/privacy/retention controls.
- Analytics/SEO/Redirects/Site Health coherent Insights surfaces.
- Security Center status model with actionable findings.
- Updates/backups/system pages use intentionally calm operational UI.
- Destructive actions communicate consequences precisely.
- Public maintenance/error states use Talvoro Editorial.

**Verification:** Upload security regression, submission privacy tests, redirect/SEO checks, system re-authentication, backup/update safety checks.

---

## Plan 07 — Targeted Backend and HTTP Architecture Modernization

**Deliverable:** Reduce controller/core concentration only where redesigned flows now need clearer boundaries, without a framework migration.

**Scope:**

- Split `routes/web.php` into public/admin/install route modules while preserving route behavior.
- Gradually retire responsibilities from `app/Http/Controllers.php` into focused controllers.
- Introduce application services for publishing/media/forms/theme operations as needed.
- Introduce repository boundaries for persistence-heavy areas as needed; retain PDO/SQL.
- Formalize authenticated same-origin CMS JSON endpoints for command palette/autosave/media/search/builder operations.
- Centralize safe request guards, authorization, CSRF, and safe logging.
- Keep domain rules independent of HTTP.

**Verification:** Route inventory parity, permission/CSRF tests, controller behavior tests, public render parity, no schema or runtime dependency regression.

---

## Plan 08 — Upgrade, Accessibility, Regression, Packaging, and Release Hardening

**Deliverable:** Prove the redesigned Talvoro is safe to upgrade, accessible, package-clean, and compatible with the existing release process.

**Scope:**

- Fresh install -> latest schema test.
- 0.15.1 -> redesigned version migration/upgrade test.
- Legacy theme/custom theme compatibility tests.
- WCAG 2.2 AA regression pass across shared components.
- Keyboard/focus/dialog/reduced-motion checks.
- PHP syntax/static checks and existing `bin/check*.php` regression checks.
- Docker build/health validation.
- Web-hosting package smoke validation.
- Source/Docker/Web Hosting package manifest checks.
- Secret/runtime exclusion checks.
- Version consistency checks.
- Build a development source ZIP for server/Docker validation.
- User approval gate before version bump, dev push, signed tag, or official release workflow.

---

## Program Execution Order

1. Plan 01 — Design Foundation and Product Shells.
2. Review in local/server Docker at desktop/tablet/mobile.
3. Plan 02 — Talvoro Editorial + public product site.
4. Review public site and upgrade compatibility.
5. Plan 03 — CMS information architecture.
6. Plan 04 — Content editing.
7. Plan 05 — Page Builder.
8. Plan 06 — operational features.
9. Plan 07 — backend boundary cleanup driven by completed UI flows.
10. Plan 08 — final compatibility/release hardening.
11. Only after explicit approval: choose release version, update canonical version metadata, push to `dev`, create signed Git tag, and let the existing workflow create the official Source/Docker/Web Hosting release artifacts.

## Why This Decomposition

- Plan 01 creates the stable visual contract every later screen consumes.
- Plan 02 forces the theme/Page Builder foundation to prove it can build Talvoro itself.
- Plan 03 establishes navigation before redesigning individual CMS workflows.
- Plans 04-06 can then change focused product areas without inventing new layout patterns.
- Plan 07 refactors backend boundaries only after real interaction needs are known, preventing speculative architecture work.
- Plan 08 is verification and release hardening, not a place to discover foundational design problems.


---

## Spec Coverage Matrix

| Approved spec section | Implementation plan |
|---|---|
| 1. Purpose | Program-wide; Plans 01-08 |
| 2. Product Positioning | Plans 01-03 |
| 3. Technology and Runtime Constraints | Global constraints in every plan; verified again in Plan 08 |
| 4. Preserve vs Rebuild | Program sequencing; Plans 01-08 |
| 5. Brand and Visual Direction | Plan 01 foundation; Plan 02 public/theme implementation |
| 6. Shared Design System | Plan 01, then reused by Plans 02-06 |
| 7. Public Website Architecture | Plan 02 |
| 8. Talvoro Builds Talvoro | Plan 02 public site; Plan 05 builder proof |
| 9. Talvoro Editorial Theme | Plan 02 |
| 10. Page Builder Architecture | Plan 05 |
| 11. CMS/Admin Information Architecture | Plan 03, with shell groundwork in Plan 01 |
| 12. CMS Shell and Interaction Model | Plan 01 shell; Plan 03 command/navigation completion |
| 13. Content Editor | Plan 04 |
| 14. Structured Content UX | Plan 04 |
| 15. Design Area and Theme Customization | Plans 01-03 and Plan 05 |
| 16. Media UX | Plan 06 |
| 17. Forms and Submissions | Plan 06 |
| 18. Backend/Application Architecture | Plan 07; smaller boundary changes only when required in Plans 02-06 |
| 19. Security Architecture | Preserved throughout; UI/security integration in Plan 06; endpoint/refactor hardening in Plan 07; verification in Plan 08 |
| 20. Privacy Architecture | Plans 06 and 08 |
| 21. Accessibility Architecture | Component foundation in Plan 01; workflow-specific work in Plans 03-06; regression pass in Plan 08 |
| 22. Failure and Feedback States | Plans 03-06; final consistency pass in Plan 08 |
| 23. Security Center and System UX | Plan 06 |
| 24. Deployment and Release Workflow | Preserved globally; package/release validation in Plan 08 |
| 25. Upgrade Compatibility | Plans 02 and 05 for theme/builder compatibility; full proof in Plan 08 |
| 26. Database Migrations | Added only in the plan that needs each schema change; full fresh/upgrade migration verification in Plan 08 |
| 27. Testing and Verification Strategy | Every plan has a test gate; Plan 08 performs complete release-level verification |
| 28. Implementation Strategy | This roadmap itself |
| 29. Non-Goals for the Redesign | Global constraints in every plan |
| 30. Success Criteria | Measured across plan completion gates and final Plan 08 |
| 31. Post-Redesign Program | Begins only after Plans 01-08 and official redesign release approval |

