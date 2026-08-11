# Meta Pixel consent — design

**Date:** 2026-08-11 · **Approved by:** Omer

## What

A **"Wait for cookie consent before tracking"** checkbox on the Meta Pixel
integration card (default **off**). When ticked, the generated pixel snippet
includes `fbq('consent', 'revoke');` before `fbq('init', …)` — the pixel stays
silent until a consent banner calls `fbq('consent', 'grant')`.

## Why this shape

- Meta's consent API is binary and global: **no region scoping** (unlike
  Google's Consent Mode) and **no modeling** — revoked means zero data until
  granted. Therefore default off, with a plain-language warning that ticking
  it without a consent banner plugin silences the pixel entirely.
- Lives inside the generated snippet itself (not a separate snippet like
  Google's): the revoke must precede init in the same fbq queue, so one
  snippet guarantees ordering with no priority juggling.
- Not merged into the Consent Mode V2 card — the two systems behave too
  differently; implying equivalence would mislead.

## Implementation details

- `render_card()` gains an optional `form_extra` callable rendered inside the
  save form (before the submit button) — used by the Meta Pixel card for the
  checkbox.
- **No new stored state**: the checkbox's checked state is derived from
  whether the existing snippet's content contains the revoke call. Single
  source of truth, survives export/import.
- `meta_pixel_code( $id, $consent )`: when `$consent`, adds the revoke line
  after the fbq stub, and **omits the `<noscript>` image fallback** — that
  image would otherwise track no-JS visitors unconditionally, defeating the
  point.
- Re-running the wizard with the box toggled updates the same snippet
  (existing find-or-update).
- Docs section 6 + readme note; mention that many banner plugins can also
  script-block the pixel outright ("Basic"-style), which composes fine.

## Out of scope

Limited Data Use (California LDU flags), server-side/Conversions API.
