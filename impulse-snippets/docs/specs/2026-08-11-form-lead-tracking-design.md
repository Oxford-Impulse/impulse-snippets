# Form lead tracking — design (1.17.0 Phase 2)

Approved by Omer 2026-08-11. Decisions from brainstorming: per-form tracking
entries (multiple allowed, each tied to one specific form); each tracked form
sends BOTH the Google Ads conversion (label optional) and a GA4
`generate_lead` event automatically; hashed-email enhanced conversions as an
optional per-form checkbox (default off); lives in the shared panel below the
integration cards, which is renamed "Conversion tracking" and now appears when
Google Ads OR GA4 is connected.

## What it does, in plain language

Site owners running lead-gen (contact forms instead of checkouts) currently
have to guess with page-based conversions. This tracks the *submission
itself*: when a visitor successfully submits a tracked Contact Form 7 or
WPForms form, the browser tells Google right then — no thank-you page needed,
no double counting (failed validations don't fire).

## Data model

Each tracked form is one `wpci_snippet` post, integration key
`form_conversion` (multiple allowed, like `google_ads_conversion`):

- `post_status` publish immediately (nothing to pick afterwards — the form
  event IS the targeting); the list-table toggle switches it off.
- `post_content`: explanatory preview comment (never printed).
- `_wpci_conditions`: `{type: specific, post_ids: []}` — matches nothing on
  purpose; the normal output loop must never print it.
- `_wpci_integration_id`: full `AW-XXXX/label` send_to, or `''` for
  GA4-only tracking.
- `_wpci_form_plugin`: `cf7` | `wpforms`; `_wpci_form_id`: the form's post ID.
- `_wpci_ads_enhanced`: hashed-email checkbox; `_wpci_ads_value` +
  `_wpci_ads_currency`: optional fixed value pair.

Find-or-update key is plugin+form_id: re-adding the same form updates its
entry, never duplicates. Removing the Google Ads integration does NOT trash
form trackings (they may be GA4-only; an orphaned send_to just queues into a
dataLayer nothing reads — harmless).

## Admin UI (class-wpci-integrations.php)

- Panel renamed **Conversion tracking**; shown when `google_ads` OR `ga4` is
  connected. The two Ads-only sections (Conversion actions, WooCommerce
  purchase tracking) still render only with Google Ads connected.
- New third collapsible section **Form lead tracking** — summary shows
  `(N forms tracked)`, or `(requires Contact Form 7 or WPForms)` when neither
  plugin is active (body then explains it activates automatically).
- Body: list of existing trackings (form name → snippet edit link, status,
  per-row Remove with confirm) + the add form:
  - **Form**: `<select>` with optgroups per plugin, listing
    `wpcf7_contact_form` / `wpforms` posts. Value format `cf7:123`.
  - **Google Ads conversion label** — optional; requires Ads connected when
    filled. Same accept-full-paste normalization as the other label fields.
  - **Value + Currency** — optional pair, currency pre-filled from the store.
  - **Send hashed email from the form** checkbox + Learn more popover.
- Handlers: `wpci_add_form_conversion` (validates the form post really exists
  and is the right post type; currency required whenever value set) and
  `wpci_remove_form_conversion` (id + nonce, verifies the integration tag
  before trashing). All the usual nonce + capability re-checks.
- `wpci_sanitize_ads_label()` extracted into functions-helpers.php (shared by
  the three label handlers) so the standalone suite can test it.

## Front-end (class-wpci-ads-dynamic.php)

One inline listener `<script>` on `wp_footer` (priority 20), printed only when
at least one published `form_conversion` exists and the kill switch is off.
Config is a `wp_json_encode`'d array of `{p, f, s, v, c, h}` per form.

Firing (shared `fire()` in the listener):
1. If a hash is available: `gtag('set', 'user_data', {sha256_email_address})`.
2. Always `gtag('event', 'generate_lead', {value?, currency?})` — fires into
   the dataLayer regardless of how GA4 got onto the page (our card, GT- tag,
   GTM, or manually pasted); if nothing reads it, it's one inert array entry.
3. If a send_to label is set: `gtag('event', 'conversion', {send_to, ...})`.

Per plugin:
- **CF7**: native DOM event `wpcf7mailsent` (always fires — CF7 is
  AJAX-only), form matched via `event.detail.contactFormId`, email read from
  the form's `input[type=email]`.
- **WPForms AJAX mode**: jQuery event `wpformsAjaxSubmitSuccess` on the form
  element (guarded on `window.jQuery`, which WPForms ships with); form ID from
  the `wpforms[id]` hidden input. The handler also clears the fallback cookie
  (below) so the same submission is never counted twice.
- **WPForms non-AJAX mode** (page reload): server hook
  `wpforms_process_complete` sets a short-lived cookie `wpci_lead`
  (`form_id|serverhash`, 10 min, path /, secure on HTTPS, NOT httponly — the
  listener must clear it). On the next page view the listener config carries a
  `pending` entry built from that cookie (validated server-side: integer ID
  must match a tracked WPForms entry, hash must be 64 hex chars) and fires it,
  then clears the cookie. If our JS never runs (event name breakage), worst
  case is a delayed count on the next page — never a double count.

Email hashing (client side, enhanced checkbox only): normalize exactly like
`wpci_hash_user_email()` (trim, lowercase, strip dots in the local part for
gmail.com/googlemail.com), then `crypto.subtle.digest('SHA-256')`. Requires a
secure context — on plain HTTP the hash is silently skipped and the conversion
still counts. The raw address never leaves the browser. The server-side
fallback hash uses `wpci_hash_user_email()` on the submitted email field.

## Testing

- `wpci_sanitize_ads_label()` joins the standalone suite (bare label, full
  AW-…/label paste, invalid characters, whitespace, empty).
- Live Playground pass with Contact Form 7 installed: submit a test form,
  confirm `generate_lead` + `conversion` + `user_data` hash in the console/
  network panel; toggle off, confirm silence.

## Out of scope

- Elementor Forms, Gravity Forms, Fluent Forms — add-plugin follow-ups once
  the CF7/WPForms pattern is proven.
- Per-field mapping (phone, name enhanced-conversion fields) — email only.
