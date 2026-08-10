# Task: Fix findings from full-project fault review (2026-08-10)

Source: full read-through of every plugin file by Claude. Nothing below has been fixed yet.

## P1 — Real bugs (fix before next release)

- [x] **Auto-detect wrap breaks on bare JS containing `<`** — FIXED in 1.14.1 (2026-08-11): "starts with a real tag" heuristic, 16/16 standalone tests pass. Spec: docs/specs/2026-08-10-auto-detect-wrap-fix-design.md — `wpci_maybe_wrap_code()` in `includes/functions-helpers.php` treats any code containing `<` as markup. Bare JS like `for (i = 0; i < 10; i++)` is printed as visible text instead of running. Fix: only treat as markup when the trimmed code *starts* with `<`.
- [ ] **Re-save silently drops "specific pages" targeting** — the edit screen lists only the first 200 published posts/pages; saving rebuilds targeting from visible checkboxes only, so targets outside the list (200+ item sites, URL-pasted custom post types) are silently removed on Update. Fix: always render checked entries for currently-targeted IDs even if outside the 200, and/or switch to an AJAX search picker.
- [ ] **Switching Paste code ↔ External URL erases the other's content** — both share `post_content`. Fix: separate storage (e.g. meta for URL) or a confirm-before-save warning.
- [ ] **Invalid conditions shown as "All pages" in list table** — `wpci_get_conditions_summary()` default case says "All pages" while output fails closed (snippet prints nowhere). Fix: show "Invalid targeting — output disabled".

## P2 — Missing product pieces

- [ ] **No UI to reorder snippets** even though `menu_order` controls output order. Add a priority/order field (competitors: WPCode priority number).
- [ ] **Category targeting excludes category archive pages** — either support archives or relabel to "posts in these categories".
- [ ] **Add more display conditions**: front page, 404, search results, logged-in vs logged-out. Cheap wins with the existing tagged-union conditions format.
- [ ] **Import/export + duplicate snippet** — standard competitor features; needed for multi-site owners and migrations.
- [ ] **Kill-switch visibility**: when "pause all" is on, show a site-wide admin notice on every plugin screen (currently only visible on Settings).

## P3 — Release housekeeping

- [ ] readme.txt: replace Contributors placeholder; fix install path (`wp-code-injector` → `impulse-snippets`); verify "Tested up to" against current WP version; produce the 3 promised screenshots.
- [ ] Add `Update URI:` header to `impulse-snippets.php` (protects non-.org installs from slug collisions).
- [ ] Add `load_plugin_textdomain()` + generate a `.pot` file into `languages/`.
- [ ] Add empty `index.php` guard files to `includes/` and `assets/`.
- [ ] Make `uninstall.php` multisite-aware.
- [ ] Fix stale comment in `class-wpci-cpt.php` (`_wpci_conditions` registration claims a baseline sanitize_callback that isn't registered).
