# Task: Fix findings from full-project fault review (2026-08-10)

Source: full read-through of every plugin file by Claude. Nothing below has been fixed yet.

## P1 — Real bugs (fix before next release)

- [x] **Auto-detect wrap breaks on bare JS containing `<`** — FIXED in 1.14.1 (2026-08-11): "starts with a real tag" heuristic, 16/16 standalone tests pass. Spec: docs/specs/2026-08-10-auto-detect-wrap-fix-design.md — `wpci_maybe_wrap_code()` in `includes/functions-helpers.php` treats any code containing `<` as markup. Bare JS like `for (i = 0; i < 10; i++)` is printed as visible text instead of running. Fix: only treat as markup when the trimmed code *starts* with `<`.
- [x] **Re-save silently drops "specific pages" targeting** — FIXED in 1.15.0 (2026-08-11): currently-targeted posts are always rendered in the checkbox list (any status/post type), so they survive re-saves. AJAX search picker remains a future improvement.
- [x] **Switching Paste code ↔ External URL erases the other's content** — FIXED in 1.15.0 (2026-08-11): external URL now stored in `_wpci_external_url` meta, code stays in post_content; both saved on every submit. Legacy snippets (URL in post_content) still output via fallback and migrate on first re-save.
- [x] **Invalid conditions shown as "All pages" in list table** — FIXED in 1.15.0 (2026-08-11): list now shows "Invalid targeting — output disabled".

## P2 — Missing product pieces

- [ ] **No UI to reorder snippets** even though `menu_order` controls output order. Add a priority/order field (competitors: WPCode priority number).
- [ ] **Category targeting excludes category archive pages** — either support archives or relabel to "posts in these categories".
- [ ] **Add more display conditions**: front page, 404, search results, logged-in vs logged-out. Cheap wins with the existing tagged-union conditions format.
- [ ] **Import/export + duplicate snippet** — standard competitor features; needed for multi-site owners and migrations.
- [x] **Kill-switch visibility** — FIXED in 1.15.0 (2026-08-11): warning notice with a "resume" link on every plugin screen while paused.

## P3 — Release housekeeping

- [ ] readme.txt: replace Contributors placeholder (**needs Omer's WordPress.org username — cannot be guessed**); produce the 3 promised screenshots. Install path fixed and "Tested up to: 7.0" verified correct in 1.15.0.
- [x] `Update URI:` header — DONE in 1.15.0 (set to https://oxfordimpulse.com/impulse-snippets).
- [x] `load_plugin_textdomain()` + Domain Path header — DONE in 1.15.0. Generating the `.pot` file still open (needs wp-cli i18n tooling).
- [x] `index.php` guard files — DONE in 1.15.0 (includes/, assets/, assets/css/, assets/js/, languages/).
- [x] `uninstall.php` multisite-aware — DONE in 1.15.0.
- [x] Stale comment in `class-wpci-cpt.php` — DONE in 1.15.0 (comment now states there is deliberately no sanitize_callback).
