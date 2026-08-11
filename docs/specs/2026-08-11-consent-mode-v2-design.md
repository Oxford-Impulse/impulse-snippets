# Consent Mode V2 + Google tag (GT-) card — design

**Date:** 2026-08-11 · **Approved by:** Omer (brainstormed per the task file's
four open questions; see docs/tasks/2026-08-11-google-consent-mode-v2.md)

## Consent Mode V2 card

- Fifth Integrations card. No ID field — a radio choice instead:
  **"Denied by default for: ( • ) EU/UK visitors only  ( ) Everyone"**, then
  Set up / Update.
- Generates ONE head snippet, integration key `consent_mode`,
  `_wpci_integration_id` = the chosen preset (`eu` | `all`) so the card can
  show the current preset and find-or-update works.
- **Priority −10** (menu_order), guaranteeing it prints before every Google
  base tag (0) and conversion event (10). Verified: the edit screen priority
  input has no min attribute and the save path uses intval(), so −10 is
  user-editable and survives.
- Snippet content (self-contained gtag stub, Google's Advanced mode):
  - `gtag('consent','default', {ad_storage/ad_user_data/ad_personalization/
    analytics_storage: 'denied', wait_for_update: 500 [, region: EEA+UK+CH]})`
  - `gtag('set','ads_data_redaction', true)` — privacy-safe pairing, always on.
  - `url_passthrough` deliberately NOT emitted (gray-area, mutates URLs).
- Region preset list: EEA members + GB + CH.
- Card copy warns in plain language: this is the signal, not the banner; a
  consent banner plugin (Complianz / Cookiebot / CookieYes — any
  Google-certified CMP) sends the matching `consent update`. Without one,
  denied visitors stay denied.
- Toggle + Remove behave like other cards (REST key list gains `consent_mode`).

## Nudges

`render_card()` shows one description line on connected Google cards
(`ga4`, `gtm`, `google_ads`, `google_tag`) when `consent_mode` is not
connected: "Recommended for EU visitors: set up Consent Mode V2 (card below)."

## Google tag (GT-) card

- Standard card via `render_card()`: key `google_tag`, prefix chip `GT-`,
  validation `/^GT-[A-Z0-9]+$/i`, same gtag loader code as GA4/Ads with the
  unified ID, head, priority 0.
- Help text warns not to connect GT- alongside the equivalent G-/AW- IDs
  (the tag would load twice).

## Shared changes

- `upsert_snippet()` gains an optional `$menu_order` parameter (default 0).
- Integration labels for `google_tag` and `consent_mode` in helpers.
- Dashboard status list + Docs section 6 + readme (features, changelog,
  External services already covers googletagmanager.com).

## Explicitly out of scope

Cookie banner/CMP UI, Meta Pixel consent (`fbq('consent')` — future task),
Basic-mode tag blocking (only a CMP can do that; docs say so honestly).
