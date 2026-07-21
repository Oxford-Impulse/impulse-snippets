# WP Code Injector

A WordPress plugin for adding unlimited named code snippets (JavaScript, CSS, HTML) to your site's head, body, or footer — manually, or with one-click integrations for Google Analytics 4, Google Tag Manager, and Meta Pixel.

By [Oxford Impulse](https://oxfordimpulse.com).

## Features

- **Unlimited snippets** — each with its own name, on/off toggle, and placement (Head / Body / Footer)
- **Paste code or link to an external file** — bare JS/CSS is auto-wrapped in the right tags
- **Display conditions** — target all pages, specific pages/posts, post types, or categories, with live search and paste-a-link shortcuts
- **One-click integrations** — paste a GA4 Measurement ID, GTM Container ID, or Meta Pixel ID and the correct snippet(s) are generated automatically, with instant pause/resume toggles
- **Instant on/off toggles** — flip any snippet from the list with no page reload (REST-backed)
- **Emergency kill switch** — pause all snippets site-wide from Settings
- **Safe uninstall** — deleting the plugin keeps your data unless you explicitly opt in to removal
- **No PHP execution** — by design; HTML/CSS/JS only

## Installation

1. Download the latest `wp-code-injector.zip` from [Releases](../../releases), or zip the `wp-code-injector/` folder yourself.
2. In WordPress admin: **Plugins → Add New Plugin → Upload Plugin** → choose the zip → **Install Now** → **Activate**.
3. Find **Code Injector** in your admin sidebar.

## Requirements

- WordPress 5.9+
- PHP 7.4+

## Structure

```
wp-code-injector/
├── wp-code-injector.php      Bootstrap: constants, includes, plugin header
├── uninstall.php             Opt-in data removal on plugin deletion
├── readme.txt                WordPress.org-format readme
├── includes/                 One class per concern (CPT, admin UI, output engine, integrations, REST, …)
└── assets/                   Admin CSS + vanilla JS (no build step)
```

## Security model

Snippet code is intentionally output as-is — that's the product. The compensating controls: only administrators (`manage_options`-level, via dedicated capabilities) can create or edit snippets, every save path is nonce- and capability-checked, and integration IDs are format-validated server-side before being templated into generated code.

## License

[GPL v2 or later](wp-code-injector/LICENSE.txt)

## Support

- [Report a bug or request a feature](https://github.com/Oxford-Impulse/wp-code-injector/issues)
- Email: info@oxfordimpulse.com
