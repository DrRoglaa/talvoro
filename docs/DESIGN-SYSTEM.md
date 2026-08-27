# Talvoro Design System

Talvoro 0.15 introduces a semantic design layer shared by Pages, Patterns, structured content presentations and themes.

## Principles

- Editors choose design intent, not arbitrary CSS.
- Theme-scoped tokens live under **Design → Styles**. Every theme keeps its own values when you switch themes.
- Page Builder sections store a small whitelist of semantic choices: tone, width, spacing, alignment and supported block variants.
- Public PHP rendering and the Visual Builder preview use the same normalized values.
- Themes consume stable CSS custom properties such as `--talvoro-brand`, `--talvoro-text`, `--talvoro-content-width` and `--talvoro-radius`.
- Talvoro never accepts remote font URLs, JavaScript, templates or CSS through Design settings.
- Color contrast warnings are advisory guardrails; editors keep control while accessibility problems remain visible.

## Theme-scoped tokens

Each active theme owns a token set covering brand/accent/background/surface/text/border colors, privacy-friendly system font stacks, typography scale, normal/wide content widths, section spacing, surface/button radius, shadow strength and link style.

The internal model is intentionally small and semantic. `DesignSystem::tokenExport()` exposes a portable key/value map so future import/export tooling can map Talvoro tokens to standardized design-token formats without changing stored Page content.

## Section styles

Page Builder sections store only whitelisted values:

- Background: Default / Soft / Accent / Dark
- Content width: Normal / Wide / Full
- Spacing: Compact / Normal / Spacious
- Alignment: Left / Center
- Variant: only variants supported by that block type

Unknown or malformed values normalize to safe defaults on the server.

## Theme contract

`/theme.css` emits the active theme CSS first and Talvoro's generated semantic token layer second. Imported themes can reference Talvoro variables and semantic section classes without owning editor data.

A theme should prefer tokens instead of copying site-specific colors into its CSS. Themes may style the semantic classes more deeply, but should preserve readable focus states and avoid depending on editor-only markup.

## Visual editing

The 0.15 builder loads its iframe shell once and then patches preview content as fields change, preserving preview scroll and device state. Sections expose hover/selection outlines; editable preview text carries a safe field mapping, so clicking a heading, label, testimonial, FAQ answer or similar value selects the owning block and focuses the matching inspector control. **Focus preview** temporarily gives the preview the full workspace, and `Esc` returns to the normal three-pane builder. Device previews and standalone draft previews remain available.


## Pattern previews

The Pattern library uses lightweight schematic previews generated from validated block types. Multi-block Patterns expose a small flow indicator for their first sections, giving editors useful visual recognition without rendering dozens of live iframes or executing arbitrary Pattern content in administration.

## Accessibility and safety

Design inputs are whitelisted on the server. Color fields accept six-digit hex values, dimensions are bounded, and fonts/variants come from fixed choices. The Styles screen provides live contrast feedback while the server repeats the same accessibility review on save. Design settings cannot contain CSS, JavaScript, remote font URLs, templates or PHP.
