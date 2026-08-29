<div align="center">

# Talvoro Design System

### Semantic design choices that stay safe, portable, and theme-aware.

**[Documentation](README.md)** · **[Development Guide](DEVELOPMENT.md)** · **[Contributing](../CONTRIBUTING.md)**

</div>

---

Talvoro `0.15` introduces a semantic design layer shared by Pages, Patterns, structured content presentations, themes, and the Visual Builder.

The core idea is simple:

> Editors choose **design intent**, while Talvoro and the active theme control the implementation details.

## Design principles

| Principle | Talvoro behavior |
| --- | --- |
| **Semantic over arbitrary** | Editors choose whitelisted design intent instead of injecting arbitrary CSS |
| **Theme-scoped** | Every theme keeps its own design-token values |
| **Shared rendering** | Public PHP rendering and Visual Builder preview normalize the same semantic values |
| **Stable theme contract** | Themes consume Talvoro CSS custom properties and semantic classes |
| **Privacy-friendly** | Design settings do not load arbitrary remote fonts/scripts |
| **Safe by default** | Unknown/malformed values normalize to supported defaults |
| **Accessible guidance** | Contrast problems remain visible without silently overriding editor choices |

## Redesign CSS architecture

During the complete redesign, Talvoro intentionally uses four CSS layers:

1. `talvoro-foundation.css` — product tokens, focus/motion, shared control/status primitives.
2. `app.css` — temporary legacy compatibility layer retained while screens are migrated.
3. `talvoro-public.css` or `talvoro-admin.css` — redesigned product-shell and context-specific rules.
4. `/theme.css` — active public-theme tokens/CSS; loaded only for public rendering and public previews.

`app.css` is not the future source of truth. Later redesign plans move verified rules into focused stylesheets and delete the corresponding legacy rules only after visual/regression checks pass.

The CMS product palette (`--tv-*`) is independent of the active public theme. Public content/theme components use `--talvoro-*` semantic tokens produced by `DesignSystem`.

### Approved product token roles

| Role | Purpose |
| --- | --- |
| **Ink** | Primary text, dark CMS navigation, strong neutral controls |
| **Parchment / Ivory** | Product canvas and warm working surfaces |
| **Coral / Terracotta** | Brand identity and primary actions; not error semantics |
| **Sea Glass** | Complementary brand accent and calm positive emphasis |
| **Indigo / Plum** | Depth, selected states, and secondary emphasis |
| **Success / Warning / Danger / Info** | Dedicated semantic states kept separate from brand colors |

## Theme-scoped tokens

Theme tokens are managed under:

```text
Design → Styles
```

Each active theme owns its own token set.

The token model covers areas such as:

| Category | Examples |
| --- | --- |
| Color | brand, accent, background, surface, text, border |
| Typography | privacy-friendly system font stacks, type scale |
| Layout | normal/wide content widths |
| Spacing | section spacing |
| Shape | surface and button radius |
| Depth | shadow strength |
| Links | link presentation/style |

Switching themes does not require one theme to overwrite another theme's values.

## Stable CSS variables

Themes can consume stable Talvoro variables such as:

```css
--talvoro-brand
--talvoro-text
--talvoro-content-width
--talvoro-radius
```

Themes should prefer these semantic values over copying site-specific settings into theme CSS.

## Portable token export

`DesignSystem::tokenExport()` exposes the Talvoro token model as a portable key/value map.

This keeps stored Page content independent from a specific theme implementation and creates a clean path for future import/export tooling to map Talvoro tokens to standardized design-token formats.

## Section styles

Page Builder sections store a deliberately small whitelist.

| Property | Supported intent |
| --- | --- |
| **Background** | Default · Soft · Accent · Dark |
| **Content width** | Normal · Wide · Full |
| **Spacing** | Compact · Normal · Spacious |
| **Alignment** | Left · Center |
| **Variant** | Only variants explicitly supported by that block type |

Unknown or malformed values normalize to safe defaults on the server.

> [!IMPORTANT]
> Page content stores semantic intent, not arbitrary styling code. This keeps content portable between compatible themes.

## Theme contract

`/theme.css` emits:

```text
active theme CSS
        ↓
Talvoro-generated semantic token layer
```

Imported themes can reference Talvoro variables and semantic section classes without becoming the owner of editor data.

A Talvoro theme should:

- prefer tokens to hard-coded site-specific colors;
- support semantic section classes;
- keep keyboard focus states readable;
- avoid relying on editor-only markup;
- avoid moving application data into theme CSS.

## Visual Builder behavior

The `0.15` Visual Builder keeps the preview iframe shell loaded and patches content as fields change.

This preserves:

- preview scroll position;
- selected device state;
- a faster editing loop.

Sections expose hover/selection outlines.

Editable preview text carries a safe field mapping, allowing interactions such as clicking a:

- heading;
- label;
- testimonial;
- FAQ answer;
- other mapped editable value.

That interaction selects the owning block and focuses the matching inspector control.

### Focus Preview

**Focus preview** temporarily gives the preview the full editing workspace.

Press:

```text
Esc
```

to return to the normal three-pane builder.

Device previews and standalone draft previews remain available.

## Pattern previews

The Pattern library uses lightweight schematic previews created from validated block types.

For multi-block Patterns, a compact flow indicator represents the first sections.

This avoids the cost and security complexity of rendering many live iframes or executing arbitrary Pattern content inside administration.

## Safety boundaries

Design settings cannot contain:

```text
arbitrary CSS
JavaScript
PHP
templates
remote font URLs
```

Color fields accept six-digit hex values, dimensions are bounded, and fonts/variants come from fixed choices.

The server validates the same design inputs that the UI exposes.

## Accessibility

The Styles screen provides live contrast feedback and the server repeats the accessibility review on save.

Contrast warnings are advisory guardrails: they keep potential problems visible while preserving intentional editor control.

Themes should also preserve:

- visible keyboard focus;
- readable foreground/background combinations;
- logical heading/content structure;
- usable interaction states.

## For theme authors

A compatible theme should treat Talvoro's semantic layer as a contract:

```text
content intent
   ↓
normalized Talvoro values
   ↓
CSS custom properties + semantic classes
   ↓
theme presentation
```

This separation lets themes become visually expressive without turning stored content into theme-specific markup.

---

[← Documentation home](README.md) · [Development guide →](DEVELOPMENT.md)
