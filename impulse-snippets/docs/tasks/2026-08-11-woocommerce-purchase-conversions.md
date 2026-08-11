# Task: WooCommerce purchase conversions + enhanced conversions (1.17.0 Phase 1)

Status: DONE (2026-08-11) — brainstormed, spec'd
(docs/specs/2026-08-11-woocommerce-conversions-design.md), built, and verified
live in Playground: view-source on the Order received page printed the real
order data (`'value': 25, 'currency': 'GBP', 'transaction_id': '29'`) plus the
SHA-256 hashed billing email `user_data` line above it. Phase 2 (form-plugin
lead capture) is parked below.

## What shipped

- **New `Wpci_Ads_Dynamic` class** (`includes/class-wpci-ads-dynamic.php`): the
  dynamic-per-request Google Ads output that a static snippet can't do, because
  the values only exist at display time.
- **Purchase conversions**: printed on WooCommerce's Order received page with
  the real order total, currency, and order number (`transaction_id` lets
  Google deduplicate page refreshes). Detection uses the order-received
  *endpoint* (`is_order_received_page()` + order-key check), NOT the
  `woocommerce_thankyou` hook — the block-based checkout (Woo's default since
  8.3) never fires that hook, which was the original "no conversion appears"
  bug during live testing.
- **Enhanced conversions (purchase)**: optional checkbox sends
  `gtag('set', 'user_data', {'sha256_email_address': ...})` — the billing
  email is hashed server-side with SHA-256 (Google's normalization: trim,
  lowercase, gmail dots stripped); the raw address never reaches the page.
- **Enhanced conversions (leads)**: page-based conversion actions can send the
  same hashed `user_data` for logged-in visitors (`_wpci_ads_enhanced` meta,
  printed on `wp_head` priority 0 so it precedes every conversion event).
- The purchase setup lives in the Google Ads panel on the Integrations page as
  a real tagged snippet (`google_ads_purchase`) — list visibility, on/off
  toggle, kill switch all apply. Its conditions deliberately match nothing;
  the endpoint detection IS its targeting.
- `wpci_hash_user_email()` helper joined the standalone test suite (51 tests).

## Phase 2 — DONE same day (2026-08-11)

Form lead tracking for Contact Form 7 + WPForms shipped and was verified live
in Playground — see docs/specs/2026-08-11-form-lead-tracking-design.md and
docs/tasks/2026-08-11-form-lead-tracking.md. Elementor Forms / Gravity Forms /
Fluent Forms remain possible follow-ups.
