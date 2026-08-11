# Idea (parked): visual glyphs on the Integrations cards

Status: PARKED (2026-08-11) — Omer asked "why don't we put logos of each
platform in integrations?" during 1.17.0 work. Advice given and accepted:
**do not use the official Google / Meta / WooCommerce logos.**

## Why not the real logos

- Trademark risk: Google's and Meta's brand guidelines only permit their logos
  in approved contexts; a paid plugin's admin UI is not one. WordPress.org
  reviewers also flag third-party trademarks in plugin assets (Guideline 17
  covers naming, but reviewers extend the caution to bundled brand imagery).
- The bundled images would need a GPL-compatible license — official brand
  logos are explicitly *not* freely licensed.

## What we could do instead (when picked up)

- Neutral **custom glyphs** drawn in our own black-and-white brand style
  (matching the pulse-in-brackets icon): e.g. a bar-chart glyph for analytics,
  a tag glyph for GTM, a megaphone for ads, a shield for Consent Mode.
- Or Dashicons (already shipped with WordPress, GPL, zero bytes added):
  `chart-bar`, `tag`, `megaphone`, `shield`, `cart`.
- Keep cards text-first either way — the glyph is decoration, not navigation.

## Effort when resumed

Small: one `<span class="dashicons ...">` (or inline SVG) per card heading in
`class-wpci-integrations.php` + a few CSS rules. No data or behavior changes.
