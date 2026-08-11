# WooCommerce dynamic conversion values + manual enhanced conversions

**Status:** parked — future project. Must come AFTER Consent Mode V2
(docs/tasks/2026-08-11-google-consent-mode-v2.md); needs its own brainstorming
session before any build.
**Source:** Omer, 2026-08-11, while designing the Google Ads integration
(docs/specs/2026-08-11-google-ads-integration-design.md).

## What shipped instead (baseline)

The Google Ads integration supports **fixed** value/currency per conversion
action, and **automatic** enhanced conversions (a Google Ads-side switch that
works with our base tag — documented on the card and Docs page).

## What this task would add

1. **Dynamic per-order conversion values** — send the real order total
   (e.g. €137.90) instead of a fixed value. Requires reading WooCommerce order
   data on the order-received page and injecting `value`, `currency`, and
   `transaction_id` into the conversion event.
2. **Manual enhanced conversions** — send hashed (SHA-256) customer email/phone
   with the conversion via `gtag('set','user_data',…)`. Requires a data source
   (WooCommerce order, or form plugin) and consent gating.

## Why parked

- Both need customer/order data the core plugin deliberately can't see.
- Manual enhanced conversions transmit personal data → GDPR/consent must be
  solved first (Consent Mode V2), or this feature would be legally reckless
  for EU users.
- WooCommerce detection/integration is a meaningful scope expansion for a
  plugin whose value is simplicity — worth doing deliberately, not as a rider.

## Notes for the future brainstorming

- Competitor check: WPCode has a "conversion pixels" addon (paid) doing
  exactly this; Site Kit covers GA but not per-order Ads values well.
- "WooCommerce conversion tracking" has real search volume — good SEO angle.
- Detection: `class_exists( 'WooCommerce' )`; hook the order-received endpoint.
- Keep the no-PHP-execution product promise intact: this would be first-party
  plugin code, not user-supplied PHP.
