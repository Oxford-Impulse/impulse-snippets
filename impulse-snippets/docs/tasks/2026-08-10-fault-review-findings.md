# Task: Fix findings from full-project fault review (2026-08-10)

Source: full read-through of every plugin file by Claude. Nothing below has been fixed yet.

## P1 — Real bugs (fix before next release)

- [x] **Auto-detect wrap breaks on bare JS containing `<`** — FIXED in 1.14.1 (2026-08-11): "starts with a real tag" heuristic, 16/16 standalone tests pass. Spec: docs/specs/2026-08-10-auto-detect-wrap-fix-design.md — `wpci_maybe_wrap_code()` in `includes/functions-helpers.php` treats any code containing `<` as markup. Bare JS like `for (i = 0; i < 10; i++)` is printed as visible text instead of running. Fix: only treat as markup when the trimmed code *starts* with `<`.
- [x] **Re-save silently drops "specific pages" targeting** — FIXED in 1.15.0 (2026-08-11): currently-targeted posts are always rendered in the checkbox list (any status/post type), so they survive re-saves. AJAX search picker remains a future improvement.
- [x] **Switching Paste code ↔ External URL erases the other's content** — FIXED in 1.15.0 (2026-08-11): external URL now stored in `_wpci_external_url` meta, code stays in post_content; both saved on every submit. Legacy snippets (URL in post_content) still output via fallback and migrate on first re-save.
- [x] **Invalid conditions shown as "All pages" in list table** — FIXED in 1.15.0 (2026-08-11): list now shows "Invalid targeting — output disabled".

## P2 — Missing product pieces

- [x] **No UI to reorder snippets** — DONE in 1.16.0 (2026-08-11): Priority number field on the edit screen (stored as menu_order), sortable Priority column in the list. Prerequisite for the Consent Mode V2 ordering guarantee.
- [x] **Category targeting excludes category archive pages** — DONE in 1.16.0: relabeled "Categories (single blog posts)" with clarified description. Archive support remains a possible future condition.
- [x] **Add more display conditions** — DONE in 1.16.0: "Special pages" type (front page, 404, search results) + independent visitor filter (everyone / logged-in only / logged-out only). Covered by 15 new matches() tests.
- [x] **Import/export + duplicate snippet** — DONE in 1.16.0: Import/Export admin page (JSON export of all snippets; imports arrive as drafts, all fields re-whitelisted) + Duplicate row action (copy arrives as draft, integration tags deliberately not copied).
- [x] **Kill-switch visibility** — FIXED in 1.15.0 (2026-08-11): warning notice with a "resume" link on every plugin screen while paused.

## P3 — Release housekeeping

- [ ] readme.txt: replace Contributors placeholder (**needs Omer's WordPress.org username — cannot be guessed**); produce the 3 promised screenshots. Install path fixed and "Tested up to: 7.0" verified correct in 1.15.0.
- [x] `Update URI:` header — DONE in 1.15.0 (set to https://oxfordimpulse.com/impulse-snippets).
- [x] `load_plugin_textdomain()` + Domain Path header — DONE in 1.15.0. `.pot` file generated in 1.16.0 (languages/impulse-snippets.pot via wp-cli i18n make-pot).
- [x] `index.php` guard files — DONE in 1.15.0 (includes/, assets/, assets/css/, assets/js/, languages/).
- [x] `uninstall.php` multisite-aware — DONE in 1.15.0.
- [x] Stale comment in `class-wpci-cpt.php` — DONE in 1.15.0 (comment now states there is deliberately no sanitize_callback).
