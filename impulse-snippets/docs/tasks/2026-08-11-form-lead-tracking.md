# Task: Form lead tracking — CF7 + WPForms conversions for Ads and GA4

Status: DONE (2026-08-11) — brainstormed (4 questions), spec'd
(docs/specs/2026-08-11-form-lead-tracking-design.md), built, and verified live
in Playground with Contact Form 7: the dataLayer after a successful submission
contained `user_data` with the correctly normalized SHA-256 email hash
(independently recomputed and matched exactly), `generate_lead`, and the
`conversion` event with `send_to AW-…/LeadTest`, in that order.

## What shipped

- The panel below the integration cards is now **Conversion tracking** (shows
  when Google Ads OR GA4 is connected) with a third collapsible section,
  **Form lead tracking** — pick a CF7/WPForms form from a dropdown, optional
  Ads conversion label (empty = GA4-only), optional value/currency, optional
  hashed-email checkbox. One `form_conversion` snippet per form, keyed by
  plugin+form_id (re-add updates), published immediately, per-row Remove.
- New meta on these snippets: `_wpci_form_plugin`, `_wpci_form_id`,
  `_wpci_ads_value`, `_wpci_ads_currency` (plus the usual integration tags and
  `_wpci_ads_enhanced`). Conditions match nothing — the footer listener
  printed by `Wpci_Ads_Dynamic` is the only outlet.
- Listener (wp_footer priority 20, printed only when a published tracking
  exists and the kill switch is off): fires `generate_lead` always (inert if
  nothing reads the dataLayer) and the Ads `conversion` when a label is set.
  CF7 via the `wpcf7mailsent` DOM event; WPForms via the
  `wpformsAjaxSubmitSuccess` jQuery event, with a `wpforms_process_complete`
  cookie fallback (`wpci_lead`, 10 min) for non-AJAX forms. The AJAX handler
  clears the cookie so a submission is never double-counted; if the JS event
  ever breaks, the worst case is a delayed count on the next page view.
- Enhanced conversions for **logged-out** visitors: the typed email is
  normalized (trim/lowercase/gmail-dot-strip, mirroring
  `wpci_hash_user_email()`) and SHA-256 hashed with SubtleCrypto in the
  visitor's browser (secure contexts only; plain HTTP still counts the
  conversion, just without the hash). The server-side fallback hashes with the
  PHP helper. The readable address never goes to Google either way.
- `wpci_sanitize_ads_label()` extracted to functions-helpers.php, shared by
  all three Ads label handlers, 6 new standalone tests (57 total).

## Gotchas recorded for next time

- Playground has no mail system, so CF7 submissions fail with "error trying
  to send your message" — add `demo_mode: on` under the form's Additional
  Settings tab to test success paths there. (Tracking correctly stays silent
  on failed sends.)
- Chrome DevTools: text typed in the console *Filter* box hides output, it
  doesn't run; and pasting into the prompt first needs typing "allow pasting".

## Possible follow-ups (not planned)

Elementor Forms, Gravity Forms, Fluent Forms adapters on the same
`form_conversion` pattern; per-form GA4 event names; phone-number hashing.
