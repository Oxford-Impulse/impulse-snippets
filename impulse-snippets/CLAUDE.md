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

A WordPress plugin: **Impulse Snippets** (formerly "WP Code Injector", v1.14.0, GPL v2). It lets site owners add unlimited named code snippets (JavaScript, CSS, HTML) to their site's `<head>`, after the opening `<body>` tag, or in the footer — without editing theme files. Sold/published by Oxford Impulse (oxfordimpulse.com, contact: info@oxfordimpulse.com).

Key user-facing features:
- Unlimited named snippets, each with its own on/off toggle (no page reload).
- Three placements: head, body, footer.
- Paste code inline **or** link to an external file (URL).
- Auto-detect wrapping: bare JS/CSS gets `<script>`/`<style>` tags added at output time.
- Display conditions: all pages, specific pages/posts (incl. paste-a-URL), post types, or categories.
- One-click integrations: Google Analytics 4, Google Tag Manager, Meta Pixel — paste an ID, snippets are generated and tagged so re-running updates instead of duplicating.
- Emergency kill switch: pause every snippet site-wide from Settings.
- Deliberately **no PHP execution** (safety decision) and data is **kept on uninstall by default** (opt-in deletion).

## Architecture (how the code is organized)

Everything lives in a single plugin folder. `impulse-snippets.php` is the entry point: it defines `WPCI_*` constants, requires every file in `includes/`, then boots `Wpci_Plugin::instance()` — a singleton that just instantiates all the other classes (no business logic in it).

Core storage idea: **each snippet is a WordPress post** of custom type `wpci_snippet`.
- `post_status` publish/draft **is** the on/off toggle.
- `menu_order` **is** the display order.
- `post_content` holds the raw code (or the external URL).
- Post meta holds the rest: `_wpci_location` (head/body/footer), `_wpci_code_type` (auto/script/style/html), `_wpci_source` (inline/external), `_wpci_conditions` (one JSON blob — a tagged union `{type: all|specific|post_types|categories, ...}`), `_wpci_integration` + `_wpci_integration_id` (wizard-managed tagging).

Files in `includes/` (all classes prefixed `Wpci_`, constants prefixed `WPCI_`):

| File | Role |
|---|---|
| `class-wpci-plugin.php` | Singleton bootstrapper; instantiates everything |
| `class-wpci-cpt.php` | Registers the `wpci_snippet` post type + meta; dedicated capabilities granted to administrator only (self-healing on `admin_init`) |
| `class-wpci-admin-menu.php` | Top-level "Impulse Snippets" admin menu (Dashboard + submenus); enqueues admin assets only on plugin screens |
| `class-wpci-list-table.php` | Adds Location/Type/Conditions columns to the native CPT list table |
| `class-wpci-edit-screen.php` | Custom meta boxes on the snippet edit screen (code textarea, location, type, source, conditions) |
| `class-wpci-save-handler.php` | The save path: nonce + capability re-checks, whitelist validation of every field, rebuilds conditions from scratch (never trusts posted JSON) |
| `class-wpci-conditions.php` | Decodes + evaluates the JSON targeting rule per request; malformed data fails **closed** (snippet suppressed) |
| `class-wpci-output.php` | Front-end printing on `wp_head` / `wp_body_open` / `wp_footer`; `wp_footer` doubles as body fallback for themes missing `wp_body_open` (guarded against double-print) |
| `class-wpci-integrations.php` | GA4 / GTM / Meta Pixel wizard; generates ordinary tagged snippets, find-or-update on re-run; REST toggle route |
| `class-wpci-rest-controller.php` | One REST route (`wpci/v1/snippets/{id}/toggle`) powering the list-table on/off switch; everything else is plain form posts on purpose |
| `class-wpci-settings.php` | Settings page: kill switch (`wpci_disable_all` option) + uninstall-data opt-in (`wpci_remove_data_on_uninstall`) |
| `class-wpci-contact.php` | Contact page: plain `mailto:` links (deliberately not `wp_mail()` — unreliable on cheap hosting) |
| `class-wpci-docs.php` | In-plugin documentation page (plain-language walkthrough) |
| `class-wpci-plugin-links.php` | Settings/Docs links on the Plugins-screen row |
| `functions-helpers.php` | Stateless helpers: location/type labels, code wrapping, external tag rendering, conditions summary, integration lookups |

Other files: `assets/css/admin.css` + three small vanilla-JS files in `assets/js/` (edit screen, list toggle, integrations toggle), `uninstall.php` (standalone; only deletes data if opted in), `readme.txt` (WordPress.org format), `languages/` (empty, text domain `impulse-snippets`).

## Conventions and gotchas

- **Naming**: the code prefix is still `wpci`/`WPCI_`/`Wpci_` from the old "WP Code Injector" name, even though the plugin is now "Impulse Snippets". Text domain is `impulse-snippets`. Keep the prefix — renaming it would break existing installs' stored data.
- **Security model**: snippet code is intentionally output raw, unescaped — that's the product. The safeguard is *access control*, not escaping: only administrators (dedicated `wpci_snippet` capabilities / `manage_options`) can create or edit snippets. Every save path re-verifies nonce + capability. Never "fix" the unescaped output; do keep every input that *isn't* the code itself strictly whitelisted/sanitized (the save handler is the reference example).
- **No build step, no Composer, no npm.** Plain PHP 7.4+, WordPress 5.9+. Follow WordPress coding standards (tabs, Yoda conditions, `esc_html__()` etc. for all UI strings).
- **Plain form posts over AJAX/REST** everywhere except the two toggle switches — deliberate simplicity, keep it that way unless a feature genuinely needs otherwise.
- **Data safety is a product value**: deactivation never touches data; uninstall keeps data unless explicitly opted in. Preserve this in anything new.
- Comments in the code explain *why* decisions were made (e.g. the `wp_body_open` fallback, the capability self-heal) — read them before changing behavior, and keep that comment style.

## Docs & tasks

- `docs/` is the project documentation folder; `docs/tasks/` holds one markdown file per planned/ongoing task (create it if missing).
- `AGENTS.md` is a link to this file — edit `CLAUDE.md` only.
