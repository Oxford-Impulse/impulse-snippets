# Impulse Snippets

A WordPress plugin for adding unlimited named code snippets (JavaScript, CSS, HTML) to your site's head, body, or footer — manually, or with one-click integrations for Google Analytics 4, Google Tag Manager, Meta Pixel, Google Ads (including per-page conversion tracking), the unified Google tag, and Google Consent Mode V2.

By [Oxford Impulse](https://oxfordimpulse.com). Submitted to the [WordPress.org Plugin Directory](https://wordpress.org/plugins/) (slug `impulse-snippets`, awaiting review).

## Features

- **Unlimited snippets** — each with its own name, on/off toggle, placement (Head / Body / Footer), and a sortable Priority controlling output order
- **Paste code or link to an external file** — bare JS/CSS is auto-wrapped in the right tags
- **Display conditions** — target all pages, specific pages/posts (type-to-search picker or paste-a-link), post types, categories, or special pages (front page / 404 / search), each optionally limited to logged-in or logged-out visitors
- **One-click integrations** — paste an ID and the correct snippet(s) are generated automatically, with instant pause/resume toggles:
  - Google Analytics 4, Google Tag Manager, Meta Pixel, unified Google tag (GT-)
  - **Google Ads** — base tag plus conversion actions (optional fixed value/currency) that fire only on the page you choose, e.g. a thank-you page
  - **Consent Mode V2** — the consent-default signal Google requires for EU/UK traffic, auto-prioritized to print before every Google tag; pairs with any certified consent banner plugin
- **Import / Export** — selective JSON export (filterable picker or list bulk action); imports always arrive as drafts so nothing runs unreviewed
- **Duplicate** — one-click copy of any snippet (as a draft)
- **Instant on/off toggles** — flip any snippet from the list with no page reload (REST-backed)
- **Emergency kill switch** — pause all snippets site-wide from Settings
- **Safe uninstall** — deleting the plugin keeps your data unless you explicitly opt in to removal (multisite-aware)
- **No PHP execution** — by design; HTML/CSS/JS only

## Installation

1. Download the latest `impulse-snippets.zip` from [Releases](../../releases), or zip the `impulse-snippets/` folder yourself.
2. In WordPress admin: **Plugins → Add New Plugin → Upload Plugin** → choose the zip → **Install Now** → **Activate**.
3. Find **Impulse Snippets** in your admin sidebar.

## Requirements

- WordPress 5.9+
- PHP 7.4+

## Structure

```
impulse-snippets/
├── impulse-snippets.php      Bootstrap: constants, includes, plugin header
├── uninstall.php             Opt-in data removal on plugin deletion
├── readme.txt                WordPress.org-format readme
├── includes/                 One class per concern (CPT, admin UI, output engine, integrations, REST, …)
├── languages/                Translation template (.pot)
└── assets/                   Admin CSS + vanilla JS (no build step)
```

## Development

- **Tests**: `php tests/run-tests.php` — standalone logic tests (code wrapping, condition matching), no WordPress install needed; exit 0 = pass.
- **Coding standards**: PHP_CodeSniffer with the WordPress-Extra ruleset (`phpcs.xml.dist`); the scan is kept at zero findings. Install via `composer install`.

## Security model

Snippet code is intentionally output as-is — that's the product. The compensating controls: only administrators (`manage_options`-level, via dedicated capabilities) can create or edit snippets, every save path is nonce- and capability-checked, and integration IDs are format-validated server-side before being templated into generated code.

## License

[GPL v2 or later](impulse-snippets/LICENSE.txt)

## Support

- [Report a bug or request a feature](https://github.com/Oxford-Impulse/impulse-snippets/issues)
- Email: info@oxfordimpulse.com
