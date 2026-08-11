# Task: Add Google Consent Mode V2 support (Google Ads, GA4, GTM)

Status: DONE (2026-08-11) — brainstormed (all 4 open questions answered), spec'd
(docs/specs/2026-08-11-consent-mode-v2-design.md), built, and verified live in
Playground (including the print-before-Google-tags ordering via priority −10).
Decisions: own card + nudges on Google cards; EU/UK-scoped or global denied
presets; ads_data_redaction always on, url_passthrough omitted; Advanced mode
only (Basic is the CMP's job, documented honestly). A no-Google-tag-connected
notice covers the empty case. Meta Pixel consent remains a possible follow-up.

Reference: https://developers.google.com/tag-platform/security/guides/consent?consentmode=advanced

## What this is, in plain language

Since March 2024, Google requires "Consent Mode V2" for any site using Google Ads,
Google Analytics 4, or Google Tag Manager that has visitors from Europe (EEA/UK).
It is a small piece of JavaScript that tells Google's tags, **before they load**,
what the visitor has (or hasn't) consented to. Without it, Google drops ad
measurement, remarketing audiences stop filling, and conversion tracking degrades.

The signal is a call like this, placed *above* the GA4/GTM snippet in the `<head>`:

```js
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',        // new in V2
  'ad_personalization': 'denied',  // new in V2
  'analytics_storage': 'denied'
});
```

Later, when the visitor accepts cookies (usually via a consent banner / CMP plugin
like Complianz, Cookiebot, CookieYes), a matching `gtag('consent', 'update', {...})`
call flips the values to `granted`.

Two flavors:
- **Basic**: Google tags don't load at all until consent is granted.
- **Advanced** (what Google's linked guide describes): tags load immediately but send
  anonymous, cookieless "pings" until consent is granted — Google then models the
  missing data. Better measurement, slightly more legal gray area; the site owner chooses.

## Why it fits Impulse Snippets

Our one-click integrations (GA4, GTM) generate exactly the tags Consent Mode governs.
A site owner who sets up GA4 through our wizard and has EU traffic is currently
non-compliant with Google's requirement out of the box. Competitors (WPCode etc.)
ship consent handling or CMP compatibility; we should too.

## Proposed scope (to be validated in brainstorming)

- [ ] Extend the Integrations wizard with a **Consent Mode V2** option: generates a
      tagged snippet containing the `consent default` call, using the same
      find-or-update logic as GA4/GTM (re-running updates, never duplicates).
- [ ] **Ordering guarantee**: the consent default snippet must print in `<head>`
      *before* the GA4/GTM snippets. `menu_order` controls output order — the wizard
      must set it so consent always comes first (this also overlaps with the P2
      "no UI to reorder snippets" finding in the 2026-08-10 fault review).
- [ ] Let the owner choose **default granted vs denied** per signal, or at least a
      simple "EU-safe defaults (all denied)" preset. Consider `region` support
      (denied for EEA only, granted elsewhere) — Google's API supports it.
- [ ] Choose **Basic vs Advanced** mode wording carefully — explain in plain language
      on the wizard screen, since this is a legal/measurement trade-off the owner makes.
- [ ] **Docs page section**: what Consent Mode is, why Google requires it, and that it
      is *not* a cookie banner (see out-of-scope below).

## Explicitly out of scope (for now)

- **Building our own cookie banner / CMP.** That is a whole product in itself
  (geolocation, consent logging, per-category cookies, legal text). The realistic
  play: emit the `consent default` snippet and document how popular CMP plugins
  (Complianz, Cookiebot, CookieYes) send the `consent update` — most Google-certified
  CMPs handle the update side automatically.
- **Meta Pixel consent** — Consent Mode is Google-only. Meta has its own
  `fbq('consent', ...)` API; note it as a possible follow-up task.

## Open questions for brainstorming

1. Wizard placement: a fourth card next to GA4/GTM/Meta Pixel, or a checkbox *inside*
   the GA4 and GTM cards ("also add Consent Mode V2 — recommended for EU traffic")?
2. If the owner has no CMP at all, defaults of "denied" mean GA4 collects almost
   nothing. Do we warn about that, or default differently?
3. `url_passthrough` and `ads_data_redaction` flags — expose them or keep hidden?
4. Should re-running the GA4/GTM wizard auto-offer Consent Mode if it's missing?
