=== Impulse Snippets ===
Contributors: omersekinci
Tags: code snippets, header footer, google analytics, google tag manager, meta pixel
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.16.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add unlimited named code snippets (scripts, styles, HTML) to your site's head, body, or footer — manually, or with one-click integrations.

== Description ==

Impulse Snippets lets you add JavaScript, CSS, or HTML snippets anywhere on your WordPress site — in the `<head>`, right after the opening `<body>` tag, or in the footer — without editing your theme's files.

= Key features =

* **Unlimited snippets** — create as many named snippets as you need, each with its own independent on/off switch.
* **Three placements** — Head, Body (after the opening `<body>` tag), or Footer.
* **Paste code or link to a file** — paste JavaScript/CSS/HTML directly, or link to an externally-hosted file instead.
* **Auto-detect formatting** — bare code without `<script>`/`<style>` tags gets wrapped automatically.
* **Display conditions** — target all pages, specific pages/posts, post types, categories, or special pages (front page, 404, search results), plus an optional logged-in/logged-out visitor filter.
* **Priority control** — decide the exact order snippets print in when several share a location (e.g. a consent script before analytics).
* **Import / Export & Duplicate** — back up all snippets to a JSON file, move them between sites (imports arrive switched off for safety), and duplicate any snippet with one click.
* **One-click integrations** — paste a Google Analytics 4, Google Tag Manager, Meta Pixel, or Google Ads ID and the correct snippet(s) are generated for you automatically, with instant pause/resume.
* **Google Ads conversion tracking** — add conversion actions (with optional fixed value/currency) that fire only on the page you choose, like your thank-you page. Enhanced conversions work out of the box via Google's automatic mode.
* **Consent Mode V2** — one click creates the consent signal Google requires for sites with EU/UK visitors, printed before every Google tag. Choose EU-only or global "denied by default"; pairs with any certified consent banner plugin (Complianz, Cookiebot, CookieYes …).
* **Google tag (GT-) support** — if your Google account uses the newer unified GT- tag, connect it directly.
* **Instant on/off toggle** — enable or disable any snippet with a single click, no page reload.
* **Emergency kill switch** — pause every snippet site-wide from Settings if something ever goes wrong.

= A note on security =

Code you add is output exactly as written, with no modification. Only administrators can create or edit snippets, and you should only ever paste code from sources you trust.

= External services =

The plugin itself never contacts any external service and collects no data. However, snippets you create — including the ones the one-click Integrations wizard generates at your request — run on your site's public pages and may load third-party scripts there:

* Google Analytics 4 / Google Tag Manager / Google Ads snippets load scripts from googletagmanager.com (Google's [terms](https://marketingplatform.google.com/about/analytics/terms/us/) and [privacy policy](https://policies.google.com/privacy)).
* Meta Pixel snippets load scripts from connect.facebook.net ([Meta's terms](https://www.facebook.com/legal/terms) and [privacy policy](https://www.facebook.com/privacy/policy)).

These snippets are only created when you explicitly connect an integration (or paste such code yourself), and you can pause or delete them at any time. Depending on your visitors' location, using these services may require a consent banner / cookie notice on your site.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/impulse-snippets` directory, or install the plugin through the Plugins screen in WordPress directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to "Impulse Snippets" in your admin sidebar to get started.

== Frequently Asked Questions ==

= Will this slow down my site? =

Snippets are only queried for the location they're assigned to, and only output on pages that match their display conditions. Keep the number of snippets reasonable, as with any script or analytics tooling.

= What happens to my snippets if I deactivate or delete the plugin? =

Deactivating never touches your data. Deleting the plugin also keeps your snippets by default — you must explicitly opt in under Settings if you want a full data removal on uninstall.

= Can I run PHP code with this plugin? =

No. This is a deliberate safety decision — only HTML, CSS, and JavaScript are supported.

== Screenshots ==

1. The Dashboard, showing snippet counts and quick links.
2. Creating a new snippet with the code editor and display conditions.
3. One-click Google Analytics 4 / Google Tag Manager / Meta Pixel / Google Ads setup.

== Changelog ==

= 1.16.0 =
* New: Priority field — control the exact output order of snippets sharing a location (lowest number prints first). Sortable Priority column in the snippets list.
* New: "Special pages" display condition — target the front page, the 404 page, and/or search results.
* New: visitor filter — show any snippet to everyone, logged-in users only, or logged-out visitors only, on top of the page rule.
* New: Import / Export page — download snippets as JSON, import them on another site (imported snippets arrive as drafts so nothing runs unreviewed).
* New: choose exactly which snippets to export — a filterable pre-ticked picker on the Import / Export page, or tick rows on the snippets list and use the Export bulk action.
* New: Google Ads integration — connect your AW- ID for the site-wide tag, then add conversion actions (optional fixed value/currency) that fire only on the page you pick, e.g. your thank-you page. Enhanced conversions supported via Google's automatic mode.
* New: Consent Mode V2 integration — one click creates Google's required consent-default signal (EU/UK-scoped or global), auto-prioritized to print before every Google tag, with privacy-safe ads_data_redaction enabled.
* New: Google tag (GT-) card for the unified Google tag used by newer Google accounts.
* New: Duplicate row action on the snippets list.
* Added a .pot translation template in languages/.
* Clarified that the Categories condition applies to single blog posts, not category archive pages.

= 1.15.0 =
* Fixed: re-saving a snippet no longer silently removes "specific pages" targeting for pages outside the visible selection list (sites with 200+ pages, or custom post types added via the paste-a-URL field).
* Fixed: switching between "Paste code" and "External URL" no longer erases the other field's content — both are now stored independently.
* Fixed: snippets with corrupted targeting data are now labeled "Invalid targeting — output disabled" in the snippets list instead of misleadingly showing "All pages".
* New: while the emergency kill switch is active, a warning notice now appears on every plugin screen, not just Settings.
* New: uninstall cleanup is now multisite-aware (each site's own opt-in is honored).
* Added the Update URI plugin header and translation loading from the languages folder.

= 1.14.1 =
* Fixed: "Auto-detect" no longer mistakes JavaScript containing a `<` (less-than) sign for HTML. Such code is now correctly wrapped in script tags and executes instead of being printed on the page.

= 1.14.0 =
* Initial public release.

== Upgrade Notice ==

= 1.14.0 =
Initial public release.
