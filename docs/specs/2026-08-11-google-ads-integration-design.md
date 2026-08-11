# Google Ads integration — design

**Date:** 2026-08-11 · **Approved by:** Omer (brainstormed during the 1.16.0 walkthrough)

## What we're building

A fourth Integrations card: **Google Ads** — base tag plus per-page conversion
actions, with optional fixed value/currency per conversion.

### 1. Base tag (site-wide)

- Card follows the existing pattern: `AW-` prefix chip + ID field → Connect.
- Creates one head snippet (gtag loader + `gtag('config','AW-…')`), tagged
  `_wpci_integration = google_ads`, find-or-update on re-run, priority 0.
- Toggle + Remove work like the other cards (REST route key list gains
  `google_ads`).

### 2. Conversion actions (per page)

- Visible on the card only when the base tag is connected. Form fields:
  - **Conversion label** (required) — the part after the slash in
    `AW-123456789/AbCdEfG`. If the user pastes the whole `AW-…/label` string,
    the prefix is stripped. Validated `[A-Za-z0-9_-]+`.
  - **Value** (optional number) and **Currency** (3-letter code) — fixed value
    per conversion, matching Google Ads' "same value for each conversion"
    option. Value without a valid currency is an error (Google requires both).
- Add → creates a **draft** head snippet with
  `gtag('event','conversion',{send_to:'AW-…/label'[, value, currency]})`,
  conditions pre-set to `{type: specific, post_ids: []}` (matches nowhere until
  a page is chosen — fails safe), **priority 10** so it always prints after the
  base tag (priority 0). Tagged `google_ads_conversion` with
  `_wpci_integration_id = AW-…/label`.
- The user is redirected straight to the snippet edit screen; a one-time
  notice (transient) tells them: pick the page(s), then Publish.
- Re-adding the same label **updates code/title only** — it must NOT touch the
  user's chosen conditions, status, or priority.
- The card lists existing conversion actions (title, status, edit link) so the
  user can see and reach them.

### 3. Enhanced conversions (purchases and leads)

Supported via Google's **automatic** mode, which needs no plugin code — only
the base tag we install plus a switch inside Google Ads. The card and the Docs
page say exactly that. The **manual** mode (hashed customer data) is parked in
`docs/tasks/` as a future WooCommerce/forms project, explicitly sequenced
after Consent Mode V2.

### 4. Removal semantics

Removing the Google Ads integration trashes the base tag **and** all
`google_ads_conversion` snippets — conversion events are dead weight without
the base tag, and Remove already confirms with the user first.

## Touched surfaces

- `class-wpci-integrations.php` — card (via an optional `extra` render hook on
  `render_card()`), save/add/remove handlers, code builders, REST key list.
- `functions-helpers.php` — integration labels for `google_ads` /
  `google_ads_conversion` (list-table display).
- Dashboard (`class-wpci-admin-menu.php`) — Google Ads in the integrations
  status list; "One-click integrations" card copy mentions it.
- Docs page, readme (changelog + description), .pot.

## Out of scope

Dynamic per-order values, manual enhanced conversions, WooCommerce awareness —
parked task. No changes to output, conditions, save handler, or import/export
(conversion snippets are ordinary snippets).
