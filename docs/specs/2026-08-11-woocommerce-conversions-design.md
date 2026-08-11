# WooCommerce purchase tracking + enhanced conversions — design (Phase 1)

**Date:** 2026-08-11 · **Approved by:** Omer ("both" lead scopes, split into
phases for pace; this is Phase 1). Ships as **1.17.0** — NOT re-uploaded to the
pending 1.16.0 review; first SVN release after approval carries it.

## What Phase 1 delivers

1. **WooCommerce purchase conversions with real order data** — on the order
   "thank-you" page, send `value` (order total), `currency`, and
   `transaction_id` (order number; Google dedupes revisits by it).
2. **Enhanced conversions for purchases** — optional SHA-256 hashed billing
   email via `gtag('set','user_data',{sha256_email_address})`. Never the raw
   address. Respects Consent Mode automatically (gtag gates transmission).
3. **Enhanced conversions for logged-in leads** — existing page-based
   conversion actions get an optional flag; when the visitor is logged in,
   their hashed account email prints before the event.

**Phase 2 (separate project):** form-plugin email capture (CF7 / WPForms /
Elementor) for logged-out lead enhanced conversions.

## Architecture

- **New `class-wpci-ads-dynamic.php` (`Wpci_Ads_Dynamic`)** — owns all
  dynamic-per-request Google Ads output:
  - Hooks `woocommerce_thankyou` (fires only if WooCommerce exists — no
    detection needed for output; `class_exists('WooCommerce')` gates only the
    admin UI). Reads the published `google_ads_purchase` snippet's
    `_wpci_integration_id` (send_to) + `_wpci_ads_enhanced` meta, builds the
    event from `wc_get_order()`, prints with a self-contained gtag stub.
  - Hooks `wp_head` **priority 0** (before Wpci_Output's 1): if any published,
    condition-matching `google_ads_conversion` snippet has
    `_wpci_ads_enhanced` and the visitor is logged in, prints one hashed
    `user_data` set.
- **The purchase snippet is a real snippet** (toggle, kill switch, list
  visibility) but its conditions are `{specific, []}` so the normal output
  loop never prints it; its `post_content` is an explanatory preview (edits
  to it have no effect — the comment says so). The Woo hook is the targeting.
- **`wpci_hash_user_email()` helper** — Google's normalization: trim,
  lowercase, and for gmail.com/googlemail.com strip dots in the local part;
  then SHA-256. Pure function → joins the standalone test suite.

## UI (Google Ads card)

- Conversion-action form gains: ☐ "Also send the hashed email of logged-in
  visitors (enhanced conversions for leads)".
- New **WooCommerce purchase tracking** block below it:
  - WooCommerce active → label field + ☐ enhanced conversions → creates or
    updates the purchase snippet (published immediately — it can only fire on
    a real completed order). Shows current status + edit link when set up.
  - WooCommerce absent → one grey line: activates automatically when
    WooCommerce is installed (feature discoverability).
- Removing the Google Ads integration also trashes `google_ads_purchase`.

## Safety notes

- No raw PII ever in page source — hash computed server-side.
- Enhanced output respects the kill switch and post_status like everything.
- Old plugin versions fail closed on the purchase snippet (unknown-shaped
  conditions match nothing).

## Out of scope (Phase 2+)

Form-plugin integrations, phone-number user_data, Meta Advanced Matching,
Limited Data Use flags.
