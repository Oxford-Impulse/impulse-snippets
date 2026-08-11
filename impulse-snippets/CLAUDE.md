# Impulse Snippets — Project Instructions

## How to work with Omer (READ FIRST)

1. **One step at a time.** Never dump large multi-step actions at once. Explain each concept in plain language before/while doing it. Omer types commands himself — give him the command and wait, don't run ahead.
2. **Always critique and suggest.** Omer explicitly asked for honest criticism. Don't just agree or flatter — point out problems, risks, and better alternatives every time.
3. **Always use the brainstorming skill** (`.claude/skills/brainstorming`) before any creative work — new features, components, functionality, or behavior changes. Explore intent and design before implementation.
4. **Look at competitors for inspiration.** When designing features or UI, check how competing plugins solve it (WPCode, Code Snippets, Insert Headers and Footers / WPCode Lite, Woody Code Snippets, Head & Footer Code).
5. **Omer does not have a coding background.** Always explain in the simplest terms — plain language, no unexplained jargon, use analogies where they help.
6. **Pace beats intensity.** The full MVP is realistically months of effort, not weeks. Plan and communicate accordingly; don't compress roadmaps to look impressive.
7. **Create task files in `docs/tasks/`** whenever a piece of work is identified, planned, or handed off. One markdown file per task.
8. **Always offer new ideas.** Proactively bring fresh feature ideas, UX improvements, and growth suggestions — don't wait to be asked.

## What this project is

A WordPress plugin: **Impulse Snippets** (formerly "WP Code Injector", v1.17.0 in development, GPL v2). It lets site owners add unlimited named code snippets (JavaScript, CSS, HTML) to their site's `<head>`, after the opening `<body>` tag, or in the footer — without editing theme files. Sold/published by Oxford Impulse (oxfordimpulse.com, contact: info@oxfordimpulse.com).

**WordPress.org status (as of 2026-08-11):** v1.16.0 submitted to the Plugin Directory, slug `impulse-snippets`, **awaiting first review** (queue was ~463 plugins; expect weeks). WP.org username: `omersekinci`; review emails go to info@oxfordimpulse.com from plugins@wordpress.org — forward them to Claude before replying. While queued: never resubmit and never submit a second plugin. The submission zip is built by staging a clean copy (excludes `.claude/`, `.agents/`, `docs/`, `CLAUDE.md`, `AGENTS.md`, `skills-lock.json`) and zipping it as top-level `impulse-snippets/`; the zip itself is git-ignored. Directory screenshots live in `wporg-assets/` at the repo root (screenshot-1/2/3.png); an icon (256×256) and banner (772×250) still need designing before approval day.

Key user-facing features:
- Unlimited named snippets, each with its own on/off toggle (no page reload), plus a sortable Priority (output order) per snippet.
- Three placements: head, body, footer.
- Paste code inline **or** link to an external file (URL, stored in its own meta field).
- Auto-detect wrapping: bare JS/CSS gets `<script>`/`<style>` tags added at output time (code *starting* with a real tag is left alone — a `<` elsewhere is treated as a JS less-than).
- Display conditions: all pages, specific pages/posts (type-to-search picker), post types, categories, or special pages (front page / 404 / search) — each optionally limited to logged-in or logged-out visitors.
- One-click integrations: Google Analytics 4, Google Tag Manager, Meta Pixel (with optional wait-for-consent mode), Google Ads (base tag + per-page conversion actions with optional fixed value/currency), unified Google tag (GT-), and Consent Mode V2 (EU/UK-scoped or global denied defaults, auto-priority −10 so it prints before every Google tag) — paste an ID, snippets are generated and tagged so re-running updates instead of duplicating.
- Conversion tracking panel (1.17.0, below the integration cards, shown when Google Ads or GA4 is connected; independently collapsible sections): page-based conversion actions; WooCommerce purchase tracking (orders reported on the order-received *endpoint* — not `woocommerce_thankyou`, which block checkout never fires — with real total/currency/order number); and form lead tracking (pick a Contact Form 7 or WPForms form, a lead fires on successful submit — GA4 `generate_lead` always, Ads conversion if labeled). Optional enhanced conversions everywhere send a SHA-256 hashed email, never the raw address — server-side for purchases/logged-in leads, in-browser (SubtleCrypto) for form submitters, so it works logged-out too.
- Import/export snippets as JSON — selective export via a filterable pre-ticked picker on the Import/Export page or an Export bulk action on the list table (imports always land as drafts) — and one-click Duplicate on the list table.
- Emergency kill switch: pause every snippet site-wide from Settings (warning banner on all plugin screens while active).
- Deliberately **no PHP execution** (safety decision) and data is **kept on uninstall by default** (opt-in deletion; uninstall is multisite-aware).

## Architecture (how the code is organized)

Everything lives in a single plugin folder. `impulse-snippets.php` is the entry point: it defines `WPCI_*` constants, requires every file in `includes/`, then boots `Wpci_Plugin::instance()` — a singleton that just instantiates all the other classes (no business logic in it).

Core storage idea: **each snippet is a WordPress post** of custom type `wpci_snippet`.
- `post_status` publish/draft **is** the on/off toggle.
- `menu_order` **is** the display order (the "Priority" field).
- `post_content` holds the raw code (legacy installs may still hold the external URL here — output has a guarded fallback for that).
- Post meta holds the rest: `_wpci_location` (head/body/footer), `_wpci_code_type` (auto/script/style/html), `_wpci_source` (inline/external), `_wpci_external_url` (the external file URL since 1.15.0), `_wpci_conditions` (one JSON blob — a tagged union `{type: all|specific|post_types|categories|special, ...}` plus an optional `visitor` key: logged_in/logged_out), `_wpci_integration` + `_wpci_integration_id` (wizard-managed tagging), and on conversion-type snippets `_wpci_ads_enhanced` plus (form trackings only) `_wpci_form_plugin`/`_wpci_form_id`/`_wpci_ads_value`/`_wpci_ads_currency`.

Files in `includes/` (all classes prefixed `Wpci_`, constants prefixed `WPCI_`):

| File | Role |
|---|---|
| `class-wpci-plugin.php` | Singleton bootstrapper; instantiates everything |
| `class-wpci-cpt.php` | Registers the `wpci_snippet` post type + meta; dedicated capabilities granted to administrator only (self-healing on `admin_init`) |
| `class-wpci-admin-menu.php` | Top-level "Impulse Snippets" admin menu (Dashboard + submenus); enqueues admin assets only on plugin screens |
| `class-wpci-list-table.php` | Adds Location/Type/Conditions/Priority columns to the native CPT list table (Priority sortable via `menu_order`) |
| `class-wpci-edit-screen.php` | Custom meta boxes on the snippet edit screen (code textarea, location + priority, type, source, conditions incl. the AJAX post-search picker) |
| `class-wpci-save-handler.php` | The save path: nonce + capability re-checks, whitelist validation of every field, rebuilds conditions from scratch (never trusts posted JSON) |
| `class-wpci-conditions.php` | Decodes + evaluates the JSON targeting rule per request; malformed data fails **closed** (snippet suppressed) |
| `class-wpci-output.php` | Front-end printing on `wp_head` / `wp_body_open` / `wp_footer`; `wp_footer` doubles as body fallback for themes missing `wp_body_open` (guarded against double-print) |
| `class-wpci-ads-dynamic.php` | Dynamic conversion output that static snippets can't do: WooCommerce purchase conversions detected via the order-received *endpoint* (NOT `woocommerce_thankyou` — block checkout never fires it) on `wp_head` 5; hashed `user_data` on `wp_head` 0 for enhanced-flagged page conversions; form-lead listener on `wp_footer` 20 (CF7 `wpcf7mailsent` DOM event, WPForms `wpformsAjaxSubmitSuccess` jQuery event + `wpforms_process_complete` cookie fallback for non-AJAX forms, in-browser SubtleCrypto email hashing). All these snippets' conditions match nothing — their hook/event is the targeting |
| `class-wpci-integrations.php` | Integrations wizard (GA4, GTM, Meta Pixel, Google Ads, Google tag GT-, Consent Mode V2) + the "Conversion tracking" panel below the cards (conversion actions, WooCommerce purchase tracking, per-form lead tracking); generates ordinary tagged snippets, find-or-update on re-run; REST toggle route |
| `class-wpci-rest-controller.php` | Two REST routes: `wpci/v1/snippets/{id}/toggle` (list-table on/off switch) and `wpci/v1/posts/search` (edit-screen picker); everything else is plain form posts on purpose |
| `class-wpci-import-export.php` | Import/Export admin page (selective JSON export via filterable pre-ticked picker; import always as drafts with full re-whitelisting) + Export bulk action and Duplicate row action on the list (integration tags deliberately not copied on duplicate) |
| `class-wpci-settings.php` | Settings page: kill switch (`wpci_disable_all` option, with `admin_notices` banner on plugin screens) + uninstall-data opt-in (`wpci_remove_data_on_uninstall`) |
| `class-wpci-contact.php` | Contact page: plain `mailto:` links (deliberately not `wp_mail()` — unreliable on cheap hosting) |
| `class-wpci-docs.php` | In-plugin documentation page (plain-language walkthrough) |
| `class-wpci-plugin-links.php` | Settings/Docs links on the Plugins-screen row |
| `functions-helpers.php` | Stateless helpers: location/type labels, code wrapping, external tag rendering, conditions summary, integration lookups |

Other files: `assets/css/admin.css` + four small vanilla-JS files in `assets/js/` (edit screen incl. the search picker, list toggle, integrations toggle, import/export picker), `uninstall.php` (standalone, multisite-aware; only deletes data if opted in), `readme.txt` (WordPress.org format), `languages/impulse-snippets.pot` (generated with wp-cli i18n make-pot; text domain `impulse-snippets`), `index.php` guard files in every asset folder.

## Conventions and gotchas

- **Naming**: the code prefix is still `wpci`/`WPCI_`/`Wpci_` from the old "WP Code Injector" name, even though the plugin is now "Impulse Snippets". Text domain is `impulse-snippets`. Keep the prefix — renaming it would break existing installs' stored data.
- **Security model**: snippet code is intentionally output raw, unescaped — that's the product. The safeguard is *access control*, not escaping: only administrators (dedicated `wpci_snippet` capabilities / `manage_options`) can create or edit snippets. Every save path re-verifies nonce + capability. Never "fix" the unescaped output; do keep every input that *isn't* the code itself strictly whitelisted/sanitized (the save handler is the reference example).
- **No build step, no Composer, no npm.** Plain PHP 7.4+, WordPress 5.9+. Follow WordPress coding standards (tabs, Yoda conditions, `esc_html__()` etc. for all UI strings).
- **Plain form posts over AJAX/REST** everywhere except the two toggle switches and the post-search picker — deliberate simplicity, keep it that way unless a feature genuinely needs otherwise.
- **Data safety is a product value**: deactivation never touches data; uninstall keeps data unless explicitly opted in. Preserve this in anything new.
- **Snippet output order is a contract**: `wp_head`/`wp_footer` print snippets by `menu_order` ASC. The integrations wizard relies on it — Consent Mode V2 is created at priority **−10** (must print before every Google tag), base tags at **0**, Google Ads conversion events at **10** (gtag() must already exist). Wizard re-runs never overwrite `menu_order` — a user-customized priority must survive.
- **WordPress.org gotchas (learned during submission)**: no `Update URI:` header (it blocks directory-hosted updates); the readme.txt title must exactly match the plugin header `Plugin Name:` (the automated scan flags any suffix); readme needs an "External services" section covering the Google/Meta scripts wizard snippets load; max 5 readme tags.
- Comments in the code explain *why* decisions were made (e.g. the `wp_body_open` fallback, the capability self-heal) — read them before changing behavior, and keep that comment style.

## Dev tooling (repo root, one level up from the plugin)

- **Tests**: `tests/run-tests.php` — 44 standalone logic tests (code wrapping, conditions decode/matching, summaries), no WordPress needed (WP functions are stubbed). Run with any PHP 7.4+ CLI: `php tests/run-tests.php` (exit 0 = pass). Note: no PHP is installed system-wide on this machine — download a portable PHP zip from windows.php.net when needed.
- **PHPCS**: `composer.json` + `phpcs.xml.dist` at repo root configure PHP_CodeSniffer with the WordPress-Extra standard (deliberately not full "WordPress" — the Docs docblock sniffs are omitted, this codebase documents *why* in targeted comments instead). Install with `php composer.phar install`, run with `php vendor/squizlabs/php_codesniffer/bin/phpcs`. Keep the scan at zero findings; when a rule is knowingly overridden, use `phpcs:ignore` with a written justification, never a bare ignore.
- `vendor/` is git-ignored; `composer.lock` is committed.

## Docs & tasks

- `docs/` is the project documentation folder; `docs/tasks/` holds one markdown file per planned/ongoing task (create it if missing).
- `AGENTS.md` is a link to this file — edit `CLAUDE.md` only.
