=== Impulse Snippets — Add Code to Header, Body & Footer ===
Contributors: (add your WordPress.org username here)
Tags: code snippets, header footer, google analytics, google tag manager, meta pixel
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.14.0
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
* **Display conditions** — target all pages, specific pages/posts, post types, or categories.
* **One-click integrations** — paste a Google Analytics 4, Google Tag Manager, or Meta Pixel ID and the correct snippet(s) are generated for you automatically, with instant pause/resume.
* **Instant on/off toggle** — enable or disable any snippet with a single click, no page reload.
* **Emergency kill switch** — pause every snippet site-wide from Settings if something ever goes wrong.

= A note on security =

Code you add is output exactly as written, with no modification. Only administrators can create or edit snippets, and you should only ever paste code from sources you trust.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-code-injector` directory, or install the plugin through the Plugins screen in WordPress directly.
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
3. One-click Google Analytics 4 / Google Tag Manager / Meta Pixel setup.

== Changelog ==

= 1.14.0 =
* Initial public release.

== Upgrade Notice ==

= 1.14.0 =
Initial public release.
