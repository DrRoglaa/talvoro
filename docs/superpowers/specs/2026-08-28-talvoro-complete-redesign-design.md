# Talvoro Complete Redesign — Design Specification

**Date:** 2026-08-28  
**Current source baseline:** Talvoro 0.15.1  
**Status:** Approved design direction; implementation not started  
**Source of truth:** corrected Talvoro source ZIP uploaded for this redesign

## 1. Purpose

Talvoro will be redesigned as a **premium self-hosted publishing platform** for creators, indie developers, freelancers, and small businesses.

The redesign covers the **entire product experience**:

- public website;
- CMS/admin UI;
- shared Design System;
- Page Builder experience;
- built-in default theme;
- responsive behavior;
- accessibility and failure states;
- gradual backend/application restructuring where required by the redesigned experience.

The redesign does **not** replace Talvoro's proven technology stack or deployment/release model.

The product principle is:

> **Simple at first glance. Powerful when you need it.**

Trenlume is the benchmark for polish, coherence, completeness, responsiveness, security, privacy, and attention to detail. Talvoro must reach the same quality level without copying Trenlume's brand, layout, or product identity.

---

## 2. Product Positioning

Talvoro is a **premium self-hosted publishing platform**.

Primary audiences:

1. creators and writers;
2. indie developers;
3. freelancers and studios;
4. small businesses.

Talvoro should feel approachable to a non-developer while retaining powerful structured-content, design, operational, and self-hosting capabilities for advanced users.

The product should lead with the publishing experience, ownership, and design quality rather than with implementation technology.

Core positioning themes:

- beautiful publishing;
- complete ownership;
- self-hosted independence;
- privacy by default;
- powerful CMS capability revealed progressively;
- no forced vendor lock-in;
- professional design from first installation.

---

## 3. Technology and Runtime Constraints

Talvoro remains built with:

- PHP;
- MySQL;
- server-rendered HTML;
- CSS;
- vanilla JavaScript;
- SQL migrations;
- Docker as one supported deployment target;
- traditional PHP/MySQL web hosting as another supported deployment target.

The redesign must **not** introduce a Node/npm runtime or build requirement.

Talvoro currently has no `node_modules`, `package.json`, npm lockfile, Vite, Webpack, or equivalent Node build pipeline. The redesign should keep that property unless a later separately approved requirement genuinely demands otherwise.

The redesign must not introduce mandatory:

- React, Vue, Angular, or another frontend framework;
- Laravel, Symfony, or another backend framework migration;
- Redis;
- queues/workers for ordinary runtime;
- proprietary cloud infrastructure;
- third-party analytics;
- mandatory Talvoro cloud accounts;
- SaaS dependencies required to render or administer a normal Talvoro site.

Runtime should remain simple enough for both Docker and conventional PHP/MySQL hosting.

---

## 4. Preserve vs Rebuild

### 4.1 Preserve

Preserve the existing functional foundation unless an implementation is shown to be unsafe, broken, or incompatible with the redesign:

- authentication;
- MFA/security foundations;
- users;
- CSRF/authorization concepts;
- SQL migration model;
- pages and publishing lifecycle;
- revisions/autosave/trash;
- structured content/content models;
- Page Builder data and concepts;
- patterns/components where semantically sound;
- media;
- menus;
- themes/design tokens where architecturally useful;
- SEO;
- redirects;
- analytics;
- 404/site-health tooling;
- forms/submissions;
- backups;
- updates;
- installer;
- Docker and web-hosting support;
- release integrity/signing workflow.

### 4.2 Rebuild aggressively

Rebuild the user-facing experience:

- Talvoro brand identity;
- Design System;
- public site UI;
- CMS/admin shell;
- CMS navigation/information architecture;
- forms, buttons, tables, notices, dialogs, statuses;
- editor presentation;
- Page Builder interaction model;
- responsive behavior;
- empty/loading/error/success states;
- default site/theme presentation.

### 4.3 Refactor incrementally

Modernize backend/application architecture only as redesigned areas are implemented. Avoid a big-bang backend rewrite.

---

## 5. Brand and Visual Direction

The approved visual direction is **Concept B — Balanced premium publishing platform**.

It combines:

- warm editorial premium;
- modern creative studio energy;
- restrained visual expressiveness;
- sophisticated whitespace;
- calm, trustworthy product UI.

Approved brand direction includes the Concept B Talvoro logo/wordmark. Production implementation may normalize spacing, proportions, favicon/mark usage, monochrome treatment, and accessible variants without changing the approved identity.

### 5.1 Core palette roles

Use semantic roles rather than arbitrary colors:

- **Ink** — primary text, strong controls, CMS sidebar;
- **Parchment / Ivory** — page canvas and light surfaces;
- **Stone / Sand** — subtle sections and borders;
- **Terracotta / Coral** — primary Talvoro brand/action accent;
- **Sea Glass / Teal** — complementary accent and selected positive/system contexts;
- **Indigo / Plum** — depth, advanced/secondary emphasis;
- **Semantic red / amber / green / blue** — destructive, warning, success, and informational states.

Brand colors and semantic status colors must remain distinct.

### 5.2 Typography

Use two functional typography families:

- editorial/display face for major public headings and selected branded moments;
- high-legibility sans-serif for interface, body copy, forms, tables, and most CMS text.

Typography must use a canonical scale rather than page-specific sizes.

### 5.3 Spacing and surfaces

Whitespace establishes hierarchy before borders/cards.

Use a canonical spacing scale shared across public and admin surfaces, with denser application of the same scale in CMS contexts.

Surface hierarchy:

1. canvas;
2. section;
3. surface;
4. elevated surface.

Cards are not the default container for all content.

### 5.4 Corners and shadows

Public editorial imagery/panels may be somewhat softer and more rounded. CMS controls/tables should use tighter, more operational geometry.

Shadows are subtle and reserved for genuine elevation.

---

## 6. Shared Design System

Create one canonical Talvoro Design System with semantic tokens for:

- colors;
- typography;
- spacing;
- borders;
- radii;
- shadows;
- control sizing;
- container widths;
- breakpoints;
- motion;
- focus;
- semantic statuses.

The public site and CMS share the foundation but do not need to share identical components where interaction requirements differ.

Conceptual relationship:

```text
Talvoro Design Foundation
        /             \
   Public UI        Admin UI
```

Canonical component families include:

- buttons;
- form controls;
- field anatomy;
- tabs;
- notices;
- status badges;
- dialogs;
- tables;
- filters;
- page headers;
- toolbars;
- empty states;
- inspector panels;
- metadata rows;
- destructive-action panels.

There must be one canonical visual/semantic representation for recurring concepts such as `Published`, `Draft`, `Scheduled`, errors, warnings, and destructive actions.

---

## 7. Public Website Architecture

The public Talvoro website is story-driven and editorial, not a generic SaaS feature-card page.

### 7.1 Primary navigation

Initial navigation:

- Product;
- Themes;
- Resources;
- Self-hosting;
- GitHub as a quieter external action;
- Demo;
- Get Talvoro.

Avoid a mega-menu until content volume genuinely requires it.

### 7.2 Homepage narrative

The homepage follows this story:

1. **Hero** — `Write beautifully. Own completely.`
2. **Trust/ownership strip** — self-hosted, open, privacy-first, no lock-in.
3. **Publishing experience** — editor, content, media.
4. **Design freedom** — Page Builder, patterns, themes, navigation.
5. **Ownership/self-hosting** — domain, database, media, portability.
6. **Power when needed** — SEO, revisions, redirects, analytics, forms, backups, security, structured content.
7. **Audience stories** — creators, freelancers, indie developers, small businesses.
8. **Talvoro Editorial** — default theme and pattern showcase.
9. **Open and independent** — source, security, portability, releases.
10. **Final CTA** — simple, restrained, ownership-focused.

Use real Talvoro product UI/screens rather than generic pseudo-software illustrations where possible.

### 7.3 Product page

Organize capabilities by user workflow:

- Create;
- Design;
- Publish;
- Understand;
- Protect;
- Extend.

### 7.4 Themes and showcase

Talvoro Editorial is the initial flagship built-in theme.

Prioritize a small number of excellent themes over a large mediocre catalog.

Showcase pages can later feature real Talvoro sites and identify themes/patterns/content models used.

### 7.5 Resources

Resources should eventually include:

- Guides;
- Documentation;
- Changelog;
- Roadmap.

Documentation uses a purpose-built technical reading layout rather than marketing-page cards.

### 7.6 Self-hosting and installation

Self-hosting is a first-class public page.

Lead with understandable ownership benefits, then present supported install paths:

- Docker — recommended;
- standard/traditional PHP hosting;
- advanced deployment only where actually supported.

Do not make raw terminal commands the first thing a new visitor sees.

### 7.7 Community edition

Do not invent artificial pricing tiers before monetization is decided.

Talvoro Community remains clearly presented as free/self-hosted.

Future optional monetization may include themes/patterns, support, managed hosting, services, or separately justified Pro capabilities, but ending an entitlement must not remotely disable an existing self-hosted website.

### 7.8 Footer

Use a restrained editorial footer with compact groups for Product, Resources, Project, and Legal rather than a large sitemap dump.

---

## 8. “Talvoro Builds Talvoro”

Talvoro should dogfood its own CMS wherever practical.

The redesigned public website should be assembled using Talvoro's real capabilities:

- Page Builder;
- patterns;
- structured content;
- menus;
- media;
- SEO;
- forms;
- theme tokens.

Public CMS-managed content includes normal marketing/content pages, homepage sections, guides/resources, reusable patterns, and navigation/footer content where appropriate.

Security/system-controlled screens remain application-controlled:

- login;
- MFA;
- installer;
- updater;
- backup restore;
- system errors;
- maintenance/update states;
- other security-sensitive workflows.

The public preview and live page must render through the same theme/component implementation.

---

## 9. Talvoro Editorial Theme

Replace the legacy default visual identity with **Talvoro Editorial** while preserving safe upgrade compatibility.

Talvoro Editorial is not only CSS. It defines the canonical public presentation contract for:

- typography;
- spacing;
- surfaces;
- widths;
- imagery;
- buttons;
- forms;
- navigation;
- Page Builder components;
- responsive behavior.

A theme consumes semantic tokens rather than hardcoded page-specific colors.

Conceptual theme package:

```text
themes/
  talvoro-editorial/
    theme metadata
    templates
    components
    patterns
    assets
    styles
```

Exact file naming should adapt to the existing theme subsystem rather than forcing a speculative structure.

Theme metadata should eventually declare identity, version, compatibility, tokens, templates, components, patterns, and assets.

---

## 10. Page Builder Architecture

Talvoro's builder remains **structured, responsive, and design-safe**, not a free-position canvas.

### 10.1 Four composition levels

1. **Blocks** — small content primitives.
2. **Sections** — structural containers/layouts.
3. **Patterns** — professionally designed compositions.
4. **Dynamic Content** — bindings to structured content/models/collections.

Patterns are the recommended starting point for normal users.

### 10.2 Pattern library

Initial categories may include:

- Heroes;
- Content;
- Features;
- Media;
- Social Proof;
- Conversion;
- Collections;
- Contact.

Patterns require real visual previews.

### 10.3 Controlled customization

Users can change meaningful content/layout choices without being required to manipulate raw CSS measurements.

Normal controls may include:

- content slots;
- alignment;
- text/media ordering;
- background role;
- section spacing role;
- supported layout variants.

Arbitrary margins/padding/radii are not normal-user controls.

### 10.4 Responsive behavior

Components/patterns own sensible responsive defaults.

Users may make selected meaningful mobile choices, but should not need to design every breakpoint manually.

### 10.5 Real rendering

Builder canvas, preview URL, and published page should use the same real theme/component rendering wherever technically practical.

### 10.6 Selection and structure

Selected content receives contextual editing chrome without permanently cluttering the canvas.

Provide a Page Structure view for long/complex pages and keyboard-accessible reordering/navigation.

### 10.7 Dynamic content

A collection block can bind to structured content, e.g.:

```text
Content model: Projects
Filter: Featured = true
Layout: Editorial Grid
Sort: Newest first
Limit: 6
```

Patterns may expose dynamic content slots for models such as Projects, Team Members, Testimonials, or Blog Posts.

### 10.8 Media and forms

The builder integrates directly with Talvoro's Media Library and Forms system.

### 10.9 Reuse semantics

Differentiate clearly between:

- **Saved Pattern** — copied into a page; later edits are independent;
- **Global Section** — shared reference; edits affect all usages.

Global edits must clearly identify their impact before saving.

### 10.10 Extensibility

Long-term custom blocks should register through a component contract rather than requiring Page Builder core edits.

A component definition conceptually includes identity, category, schema, defaults, renderer, editor controls, layout capabilities, and accessibility requirements.

Do not build a marketplace as part of the redesign; preserve a clean extensibility boundary only.

---

## 11. CMS/Admin Information Architecture

The CMS uses a dark ink sidebar with a warm/light work surface.

Primary groups:

### Overview

- dashboard;
- recent work;
- site status;
- attention items;
- restrained traffic/health summary;
- quick actions.

### Content

- Pages;
- Content/structured types;
- Media;
- Forms/Submissions.

Users should see friendly content-type names such as Projects or Testimonials without needing to understand schema terminology first.

### Design

- Page Builder;
- Themes;
- Patterns;
- Components;
- Navigation.

### Insights

- Analytics;
- SEO;
- Redirects;
- 404/Site Health.

### System

- Users & Security;
- Backups;
- Updates;
- System Health;
- Settings;
- Advanced/developer details.

Common tasks remain visible; advanced functionality stays easy to reach without dominating first-use navigation.

---

## 12. CMS Shell and Interaction Model

### 12.1 Desktop shell

- dark ink sidebar;
- current site/workspace identity at top;
- global search/command access;
- grouped navigation;
- quiet active-state treatment;
- optional sidebar collapse;
- account/system area separated at bottom.

### 12.2 Mobile shell

Do not merely shrink the desktop sidebar.

Optimize mobile for realistic administrative tasks such as content editing, publishing, submissions, media, metadata, analytics, and settings.

Complex builder rearrangement remains best on desktop/tablet but must not become inaccessible on mobile.

### 12.3 Command palette

`Cmd+K` / `Ctrl+K` searches content and actions, e.g.:

- Homepage;
- specific content;
- Create new page;
- Upload media;
- Open analytics;
- Manage redirects;
- Check updates;
- Edit navigation.

### 12.4 Dashboard

The dashboard answers:

1. What needs attention?
2. What changed recently?
3. How is the site doing?
4. What can I do next?

Avoid a grid of decorative statistics cards.

---

## 13. Content Editor

The content editor is a flagship CMS experience.

Primary layout:

- central editing workspace;
- compact top bar for navigation, save state, preview, publish;
- contextual right inspector.

The inspector changes according to context rather than showing every property permanently.

Normal content metadata may include:

- status;
- scheduling;
- slug;
- template;
- featured image;
- SEO;
- taxonomy;
- visibility.

When a builder block is selected, the inspector switches to block/layout properties.

Autosave and published state remain visibly distinct.

---

## 14. Structured Content UX

Present structured content in two levels.

### Normal mode

Friendly `Content types` such as:

- Blog Posts;
- Projects;
- Team Members;
- Testimonials;
- Products.

### Advanced model settings

Expose:

- field definitions;
- validation;
- required/optional behavior;
- machine names;
- API/schema details;
- structural configuration.

Normal users should not need schema knowledge to manage ordinary content.

---

## 15. Design Area and Theme Customization

The Design landing page should summarize:

- active theme;
- Page Builder;
- Navigation;
- Patterns;
- Components.

Theme customization presents friendly groups such as:

### Brand

- logo;
- mark;
- primary accent;
- secondary accent.

### Typography

- display face;
- body/interface face;
- scale.

### Surfaces

- background;
- content width;
- corner character;
- density.

### Site

- header;
- footer;
- default layout.

Raw/advanced tokens remain behind an explicit Advanced boundary.

---

## 16. Media UX

Media should behave like a lightweight professional asset manager.

Capabilities include:

- grid/list modes;
- search;
- filters;
- upload;
- replacement;
- metadata;
- alt text;
- dimensions;
- usage references;
- copy URL;
- safe deletion behavior.

Selecting an asset should provide contextual details in an inspector.

Deletion should eventually warn when an asset is referenced by live content.

---

## 17. Forms and Submissions

Forms become a coherent CMS area instead of isolated configuration screens.

A form can expose:

- Overview;
- Fields;
- Submissions;
- Delivery;
- Settings.

Privacy controls belong with the form/submission lifecycle:

- retention;
- export;
- deletion;
- mail routing;
- spam/security settings.

Do not expand Talvoro into a full CRM.

---

## 18. Backend/Application Architecture

Use the existing lightweight PHP architecture and refactor incrementally.

Preferred request flow:

```text
Request
  -> Router
  -> HTTP/Security boundary
  -> Controller
  -> Application Service
  -> Domain/Repository
  -> Database/filesystem/mail/etc.
```

Rendering branches to either public theme rendering or admin rendering.

### 18.1 Routes

Gradually split route definitions by product area rather than maintaining one growing route inventory.

Possible organization:

```text
routes/
  public.php
  admin/
    auth.php
    content.php
    design.php
    insights.php
    system.php
  install.php
```

Exact implementation follows existing router conventions.

### 18.2 Controllers

Continue moving away from large aggregate controller files toward focused controllers.

Controllers should primarily:

- validate requests;
- authorize;
- call application services;
- select/render a response.

### 18.3 Application services

Introduce services as redesigned workflows require them, e.g. publishing, media, forms, themes, backups.

Services coordinate domain operations that should not live in HTTP controllers.

### 18.4 Persistence

Keep PDO/SQL. Do not introduce an ORM.

Gradually add explicit repository boundaries where useful for pages, posts/content, media, forms, users, and similar domains.

### 18.5 Public rendering boundary

Move toward an explicit flow:

```text
Page / Content
  -> Page Composition
  -> Theme Renderer
  -> Talvoro Editorial
  -> HTML
```

The renderer should not care whether content originated from pages, structured content, posts, builder composition, or dynamic collections.

### 18.6 Admin JSON endpoints

Remain server-rendered overall, but formalize small authenticated same-origin JSON endpoints where they materially improve UX:

- command palette;
- autosave;
- slug checks;
- media search;
- internal links;
- Page Builder operations;
- dynamic content preview;
- inline status changes.

State-changing requests remain CSRF-protected and authorized.

### 18.7 JavaScript

Remain modular vanilla JavaScript. No SPA rewrite.

Organize modules by area as the redesign grows instead of accumulating one global admin script.

---

## 19. Security Architecture

Security remains centralized and must not weaken during redesign.

Preserve and reinforce:

- authentication;
- MFA;
- CSRF;
- authorization;
- password policy;
- rate limiting;
- audit trail;
- upload validation/security;
- release/update integrity.

State-changing CMS requests use a consistent security boundary.

High-risk operations such as backup restore, update installation, MFA reset, user deletion, theme installation, and sensitive security configuration may require stronger confirmation or re-authentication where justified.

Do not create enterprise RBAC during the redesign, but establish capability-oriented authorization boundaries so future roles can be introduced without redesigning every controller.

Potential future capabilities include:

- content.view;
- content.create;
- content.publish;
- media.manage;
- design.manage;
- forms.view;
- analytics.view;
- users.manage;
- system.manage.

Navigation must respect user capabilities.

---

## 20. Privacy Architecture

Talvoro remains self-hosted and privacy-first by default.

Default installation requires no external analytics/tracking SDK, advertising service, CDN dependency, or mandatory Talvoro account.

If the administrator configures an external service, the CMS should identify it clearly.

Forms/submissions should eventually support explicit retention controls and auditable export/deletion behavior.

Application logs must avoid storing passwords, tokens, cookies, full sensitive request bodies, or unnecessary personal data.

Audit records record actions, not secrets.

---

## 21. Accessibility Architecture

Target **WCAG 2.2 AA** as the practical baseline.

Accessibility is implemented at the Design System/component level.

Requirements include:

- semantic HTML;
- accessible heading structure;
- visible, canonical focus state;
- keyboard navigation;
- correct dialog focus trapping/restoration;
- form labels and associated hints/errors;
- sufficient contrast;
- touch target quality;
- reduced-motion support;
- screen-reader announcements for asynchronous CMS operations;
- non-drag alternatives for builder reordering;
- semantic public output from Page Builder components.

Decorative brand colors must not be reused blindly as low-contrast functional text colors.

---

## 22. Failure and Feedback States

Design success, empty, loading, validation, authorization, save, conflict, and system-failure states intentionally.

### 22.1 Empty states

Use concise explanations and one clear next action where possible.

### 22.2 Loading states

Use loading/skeleton UI only for real asynchronous latency. Do not add decorative loading states to server-rendered navigation.

### 22.3 Save state

Use a consistent model such as:

- Saving...;
- Saved;
- Unsaved changes;
- Save failed;
- reconnecting/offline where relevant.

`Saved` and `Published` are always distinct.

### 22.4 Error locality

Show errors near the place where users can resolve them:

- field validation beside fields;
- upload errors on the affected upload;
- publishing conflicts near publish controls;
- system failures on dedicated system screens.

Production errors must not expose sensitive stack/database details.

### 22.5 Destructive actions

Do not use generic `Are you sure?` confirmations for significant actions.

Explain actual consequences and recovery behavior.

Use trash/recovery for normal content where practical; reserve permanent deletion for an explicit later action.

---

## 23. Security Center and System UX

Talvoro should eventually provide a calm Security Center/System Health experience showing actionable status such as:

- HTTPS;
- MFA;
- update status;
- backup freshness;
- sensitive path protection;
- debug state;
- runtime compatibility;
- database connectivity;
- login protection.

Warnings must explain both the issue and the remediation path.

System/destructive UI should be more restrained than public marketing surfaces.

---

## 24. Deployment and Release Workflow — Preserve Existing Model

The redesign must preserve the existing Talvoro development, deployment, and release workflow.

### 24.1 Development validation workflow

```text
Talvoro source
  -> implement milestone
  -> run checks/tests
  -> build source/standard ZIP
  -> upload ZIP to server home folder
  -> update/rebuild Dockerized website
  -> visually/functionally verify
  -> user approval
```

Development/source ZIPs used for validation are not official releases.

### 24.2 Git/release workflow

After code is approved:

```text
Approved code
  -> push to dev branch
  -> final checks
  -> create signed Git tag
  -> version/tag workflow detects release
  -> build official artifacts
  -> checksums/signatures
  -> publish release
```

Do not automatically commit, push, tag, or release during implementation sessions.

### 24.3 Official artifacts

The existing release must continue to produce:

1. **Source / standard ZIP**;
2. **Docker ZIP**;
3. **Web Hosting ZIP**.

All artifacts come from the same approved/tagged source version.

Do not manually modify a generated release artifact after the signed/tagged build.

### 24.4 Packaging rules

Official artifacts must exclude runtime/private data as appropriate, including:

- real `.env`/secret configuration;
- passwords/tokens;
- private keys;
- database runtime data/dumps unless explicitly part of a documented migration fixture;
- sessions;
- logs;
- backups;
- user uploads/runtime media;
- temporary/update state;
- Git working data.

Source SQL migrations are part of the application source and **must be included**.

Do not mention or exclude `node_modules` as a Talvoro-specific requirement because Talvoro does not use Node/npm.

### 24.5 Web Hosting package

The Web Hosting ZIP remains usable without Docker, Node/npm, Redis, queue workers, or a frontend build process.

### 24.6 Docker package

Preserve existing Docker deployment semantics, persistent database/runtime expectations, health checks, and update compatibility.

A UI redesign must not require wiping the existing database or persistent volume.

### 24.7 Version consistency

Strengthen release checks so all relevant version declarations/artifacts agree before release. A version mismatch should fail the release workflow.

---

## 25. Upgrade Compatibility

An existing Talvoro 0.15.1 installation must be upgradeable to the redesigned version without losing:

- pages;
- posts/content;
- structured/custom content;
- content models;
- Page Builder data;
- patterns;
- media records;
- menus;
- redirects;
- SEO;
- users;
- MFA/security configuration;
- forms/submissions;
- revisions;
- analytics;
- site configuration.

Use controlled migrations/adapters where schemas or visual structures change.

### 25.1 Theme transition

Do not delete the legacy default theme in the first redesign step.

Safe transition:

```text
0.15.1 legacy theme remains understood
  -> introduce Talvoro Editorial
  -> migrate Talvoro's own demo/default content
  -> verify old-content compatibility
  -> make Talvoro Editorial the new default for fresh sites
  -> retire legacy compatibility only in a later explicit release when safe
```

Existing upgraded sites must not unexpectedly switch themes unless a migration/upgrade policy explicitly and safely requires it.

---

## 26. Database Migrations

Keep ordered SQL migrations as the only schema authority.

New redesign features that require schema changes receive explicit migrations in `database/migrations/`.

Never create missing production schema ad hoc during ordinary requests.

Test at minimum:

- fresh installation to latest schema;
- Talvoro 0.15.1 supported upgrade path to redesigned schema.

---

## 27. Testing and Verification Strategy

Before a milestone is considered complete, run the relevant current and new tests/checks.

Target verification areas:

- PHP syntax/static checks;
- migration checks;
- automated regression tests;
- security regressions;
- public rendering;
- CMS behavior;
- Page Builder behavior;
- responsive public UI;
- responsive CMS UI;
- accessibility/keyboard behavior;
- upgrade compatibility;
- Docker build/health;
- Web Hosting package validation;
- release ZIP manifest validation;
- private/runtime data exclusion;
- version consistency;
- checksum/signature workflow.

For visual milestones, explicitly review representative public and CMS surfaces at desktop, tablet, and mobile widths.

Do not claim a redesign milestone is complete solely because code renders without syntax errors.

---

## 28. Implementation Strategy

Do **not** implement the redesign as one uncontrolled release.

Use milestone-driven replacement with regression verification after each stage.

The backend refactor is driven by redesigned product areas rather than by a speculative directory reorganization.

Example sequence:

1. Design foundation and new shells;
2. public theme foundation;
3. admin shell/navigation;
4. content management/editor;
5. Page Builder and component system;
6. design/theme management;
7. media/forms;
8. insights;
9. system/security;
10. public site dogfooding;
11. upgrade/release hardening;
12. completeness phase after redesign.

The detailed implementation plan will be produced separately after this design specification is explicitly approved.

---

## 29. Non-Goals for the Redesign

The redesign does not include, unless separately approved:

- migrating to a PHP framework;
- migrating to a frontend framework;
- introducing Node/npm;
- introducing an ORM;
- introducing Redis as a requirement;
- requiring a background worker for ordinary use;
- introducing SaaS analytics/tracking;
- introducing mandatory cloud accounts;
- building a plugin/theme marketplace;
- building full enterprise RBAC;
- building a CRM;
- inventing paid pricing tiers;
- replacing working database/content data simply to simplify implementation;
- changing the established signed-tag/release artifact workflow.

---

## 30. Success Criteria

The redesign is successful when:

1. Talvoro has its own unmistakable premium identity rather than legacy Trenlume/Spottina visual remnants.
2. Public site and CMS clearly belong to one product.
3. Talvoro Editorial is beautiful enough to publish from immediately on a fresh install.
4. The public Talvoro website is substantially built using Talvoro itself.
5. First-time users can understand the CMS without developer knowledge.
6. Advanced users can reach structured content, design, SEO, analytics, security, and system functionality without artificial limitations.
7. Page Builder remains structured, responsive, and difficult to accidentally break visually.
8. Existing 0.15.1 content/data can be upgraded safely.
9. Docker and traditional web-hosting deployment remain supported.
10. Release production still follows approval -> dev branch -> signed tag -> automated Source/Docker/Web Hosting artifacts.
11. No Node/npm runtime/build dependency is introduced.
12. Accessibility, privacy, security, and failure states are built into the system rather than patched after the visual redesign.
13. Talvoro reaches the visual coherence and product-quality benchmark represented by Trenlume while remaining unmistakably Talvoro.

---

## 31. Post-Redesign Program

After the complete visual/CMS redesign is stable and released, begin a separate **Talvoro Completeness Program**.

That program compares Talvoro functionality against the Trenlume quality/completeness benchmark and adds only features that provide clear value to Talvoro users, administrators, maintainability, privacy, security, SEO, accessibility, or operations.

The redesign should create the architecture and UX foundation that allows that completeness phase to grow without returning to inconsistent page-by-page additions.
