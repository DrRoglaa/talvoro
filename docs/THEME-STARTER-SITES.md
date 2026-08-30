<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Theme Starter Sites

### Declarative demo content for safe, portable Talvoro themes.

Talvoro 0.17.0 introduces **Theme Starter Sites**: an opt-in way for an imported theme to describe a complete starter/demo website without giving that theme executable PHP, shell, SQL, or unrestricted filesystem access.

A Starter Site belongs to the theme package, but **Talvoro core remains the installer**. The theme supplies validated data; Talvoro creates ordinary Pages, Page Builder blocks, Media Library assets, Structured Content, Journal content, menus, SEO records, approved settings, and theme design values through the same CMS services used by the administration interface.

## User workflow

Starter content is never installed implicitly.

```text
Import Theme
      ↓
Activate Theme
      ↓
Starter Site Available
      ↓
Review Starter Site
      ↓
Install Starter Site
```

Importing or activating a theme does **not** create pages, posts, models, menus, media, or settings. A privileged administrator must review the plan and explicitly confirm installation.

After installation Talvoro can also offer:

- **Repair Starter Site** when an owned starter resource is missing;
- **Delete Demo Data** to remove only demo resources Talvoro can still prove are safely starter-owned;
- a non-destructive **Starter Update Available** state when the imported starter definition changes. Talvoro 0.17.0 records update metadata but does not run theme-supplied migrations or overwrite existing starter content automatically.

## Theme package structure

A theme without starter content remains unchanged:

```text
theme.zip
├── theme.json
├── style.css
└── assets/
```

A theme with starter content may additionally contain exactly:

```text
theme.zip
├── theme.json
├── style.css
├── assets/
│   ├── hero.webp
│   └── portrait.webp
└── starter/
    └── starter.json
```

`starter/starter.json` is optional. No other executable or arbitrary files are permitted under `starter/`.

## Starter manifest

The manifest is strict JSON. The current schema is version `1`.

```json
{
  "schema_version": 1,
  "starter_version": "1.0.0",
  "name": "Example Starter Site",
  "description": "An optional complete website starter.",
  "resources": [
    {
      "key": "page.about",
      "type": "page",
      "data": {
        "title": "About",
        "path": "/about",
        "status": "published",
        "blocks": [
          {
            "id": "about-intro",
            "type": "custom",
            "eyebrow": "Our story",
            "heading": "Built with care",
            "body": "<p>Starter content remains normal editable Talvoro content.</p>"
          }
        ]
      }
    },
    {
      "key": "menu.primary",
      "type": "menu",
      "data": {
        "name": "Primary navigation",
        "menu_key": "example_primary",
        "location": "primary"
      }
    },
    {
      "key": "menu_item.primary.about",
      "type": "menu_item",
      "data": {
        "menu": {"$ref": "menu.primary"},
        "label": "About",
        "target": {"$ref": "page.about"},
        "sort_order": 10
      }
    }
  ]
}
```

### Logical resource keys

Every resource has a stable logical key such as:

```text
media.hero
page.home
page.about
model.projects
field.projects.summary
entry.project.house-a
category.news
post.welcome
menu.primary
menu_item.primary.home
seo.home
setting.site-name
design.brand
```

Database IDs must never be embedded in a theme package.

Structured Content `model_key` and `field_key` values must also respect Talvoro's reserved framework keys. Names used by core entry metadata, routing, lifecycle, SEO, or system tables—such as `title`, `slug`, `status`, and `published_at`—cannot be reused as custom model or field keys. Talvoro rejects those manifests during theme import, before any starter definition is stored or site content can be changed.

### References

Starter resources refer to other starter resources with:

```json
{"$ref": "resource.key"}
```

Talvoro validates every reference and resolves it during installation. References are accepted only in fields whose resource adapter supports that target type. Missing, incompatible, duplicate, or cyclic references are rejected before content is written.

## Supported resource types in 0.17.0

| Type | Talvoro system reused |
| --- | --- |
| `media` | Media Library and responsive image variants |
| `content_component` | Structured Content reusable components |
| `component_field` | Structured Content component fields |
| `content_model` | Structured Content models |
| `content_field` | Structured Content model fields |
| `content_entry` | Structured Content entries, relations, galleries, featured media |
| `blog_category` | Journal categories |
| `post` | Journal posts and category assignments |
| `page` | Pages and Page Builder blocks |
| `menu` | Navigation menus |
| `menu_item` | Menu items and typed content targets |
| `seo` | Talvoro SEO records |
| `setting` | Explicitly allowlisted site settings only |
| `theme_design` | Talvoro Design System values scoped to the imported theme |

Galleries, featured images, contact pages, homepage content, and Page Builder layouts are represented through their native Talvoro resource fields rather than through parallel starter-only CMS systems.

## Approved starter settings

Themes do not receive unrestricted access to `cms_settings`.

Talvoro 0.17.0 permits only these setting keys through a starter manifest:

```text
branding.site_name
branding.tagline
branding.footer_text
branding.footer_note
blog.enabled
blog.archive_title
blog.archive_intro
```

Mail, SMTP, authentication, security, admin-path, privacy-retention, database, update, backup, and other operational settings cannot be set by a theme Starter Site.

## Media

Starter media must be local to the imported theme package and referenced beneath `assets/`.

Supported Starter Site media formats in 0.17.0:

- JPEG;
- PNG;
- WebP.

Talvoro validates the packaged file during theme import, records its SHA-256 digest, and re-verifies it before copying it into the Media Library. The original theme asset is not moved. Normal Talvoro media metadata and responsive variants are then created.

Remote starter images, absolute paths, `../` traversal, symlinks, arbitrary local files, executable files, and unsupported MIME types are rejected.

## Validation and limits

The existing imported-theme limits remain in force. Starter Site-specific limits include:

| Limit | 0.17.0 |
| --- | ---: |
| `starter/starter.json` size | 512 KiB |
| Resources per starter | 500 |
| Logical references | 2,000 |
| Logical key length | 160 characters |
| JSON nesting depth | 32 |
| Starter media file | existing theme 12 MB / 60 MP / 16,000 px limits |

The parser rejects malformed JSON, unsupported schema versions, unknown manifest/resource fields, duplicate keys, invalid resource types, broken references, cycles, unsafe asset paths, remote assets, and exceeded limits before installation begins.

## Ownership and idempotency

Talvoro stores starter definitions, installations, and per-resource ownership in dedicated tables. Each installed resource records its logical key, resource type, record locator, ownership mode, definition hash, baseline fingerprint, and—where Talvoro intentionally changed a pre-existing value—the previous state required for safe restoration.

This prevents a second installation from creating duplicate Home pages, posts, entries, menus, or media for the same logical starter resource.

Talvoro distinguishes approximately:

- **Not installed**;
- **Installed**;
- **Modified**;
- **Repair available**;
- **Needs attention**;
- **Starter update available**.

A starter definition changing does not grant permission to overwrite content a user has edited.

## Existing-content conflicts

Talvoro preflights the complete Starter Site immediately before installation.

If an unrelated site record already occupies a natural identity such as a page path, post slug, category slug, model key, entry slug, or menu key/location, Talvoro reports a conflict and does not silently adopt, overwrite, or rename that user content.

The administrator must resolve blocking conflicts before installation.

Some collisions can be declared as **controlled, reversible takeovers** instead of destructive conflicts:

- a `page` resource may set `"replace_existing": true`; Talvoro then snapshots the existing page, shows the change in preflight, requires explicit confirmation, and restores the previous page during **Delete Demo Data** when the starter version remains untouched;
- existing SEO at a starter-managed path is treated as a controlled mutation, not silently overwritten; its previous metadata is preserved for restoration;
- a starter menu may temporarily occupy `primary`, `footer`, or `mobile`. If that location already has a menu, Talvoro moves the existing menu to `unassigned` without deleting or rewriting its items, creates the starter menu in the requested location, and restores the previous location during safe Demo Data removal when possible;
- an existing **menu key** still remains a hard conflict. Theme authors should therefore use theme-specific keys such as `example_primary` rather than generic keys such as `primary`.

These controlled mutations appear as human-readable before/after cards on the Starter Site review screen. The technical logical key remains available under details for diagnostics.

### Home page

A fresh Talvoro installation already owns `/`. Therefore a starter Home page is always a controlled mutation rather than an ordinary created record. Other pages remain blocking conflicts unless their manifest resource explicitly opts into `replace_existing: true`.

Talvoro stores the previous Home state before applying starter Home content. If the starter Home remains unchanged, **Delete Demo Data** can restore the previous Home. If the user later edits that starter Home, Talvoro preserves the edited page and detaches it from starter ownership instead of destroying the user's work.

## Transactions and failure recovery

Installation, repair, and Delete Demo Data use an outer database transaction.

Media files cannot be rolled back by InnoDB, so Talvoro also maintains an installation-scoped filesystem journal for files it creates. If the database operation fails, database changes are rolled back and newly created starter files are cleaned up. Existing theme source assets are never modified by the installer.

CMS services that participate in Starter Site operations are transaction-aware so the starter engine can keep the multi-resource operation atomic.

## Repair Starter Site

Repair is deliberately conservative.

It can recreate missing resources that Talvoro still owns and whose dependencies are safe. It does **not** reset user-edited starter records back to demo defaults.

Examples:

- missing owned menu item → repair may recreate it;
- deleted owned media asset → repair may re-import it from the verified theme asset;
- existing starter page edited by the user → repair leaves it untouched.

## Delete Demo Data

**Delete Demo Data** is intentionally safer than a conventional “reset demo” feature.

Talvoro processes owned resources in reverse dependency order and follows these rules:

- untouched starter-created resource → remove when Talvoro can do so safely;
- starter-controlled mutation still matching its baseline → restore the previous state;
- user-modified starter resource → keep it and detach starter ownership;
- resource containing or referenced by unrelated user data → keep it and detach when deletion would be destructive;
- unrelated user-created content → never considered for starter deletion.

The completion summary reports resources removed, restored, retained, or detached. When in doubt, Talvoro preserves content rather than deleting it.

## Permissions and request security

Starter Site management uses the dedicated permission:

```text
starter_sites.manage
```

Talvoro seeds it for the **Super Administrator** role only. Other roles may be granted it deliberately later, but theme-management permission alone does not imply permission to publish a complete starter website.

Install, repair, and Delete Demo Data are POST actions protected by Talvoro authentication, authorization, CSRF validation, and same-origin request protections. Theme import and activation never trigger these actions automatically.

## Theme deletion

Talvoro refuses to delete an imported theme while it still has an active Starter Site installation. Delete the demo/starter ownership safely first, or retain the theme package while owned resources still require it for repair.

## Starter versions and updates

`starter_version` and manifest/resource hashes are stored from 0.17.0 onward.

Talvoro 0.17.0 does not provide theme-supplied migration scripts and does not automatically update existing starter content. If a future custom-theme update imports a changed starter definition, Talvoro can report that an update exists without overwriting user changes.

A later update workflow may add genuinely new logical resources, but executable starter migrations remain outside the theme contract.

## Theme-author checklist

Before distributing a theme with a Starter Site:

1. keep the package declarative and self-contained;
2. use stable logical keys, never database IDs;
3. reuse native Talvoro Pages, Page Builder, Media, Structured Content, Journal, Menus, SEO, Settings, and Design System resources;
4. include meaningful alt text for starter media;
5. avoid remote dependencies and hotlinked demo images;
6. choose page/model slugs that do not collide with one another;
7. make all demo text clearly replaceable and avoid presenting fictional/demo claims as real facts;
8. test first install, repeated install/idempotency, repair, modified content, conflicts, and Delete Demo Data;
9. test keyboard/focus behavior and responsive rendering with the installed starter content;
10. keep theme CSS within the normal safe imported-theme contract.

A Starter Site should leave the administrator with an intentionally complete website, but every created record remains ordinary Talvoro content that can be edited after installation.
