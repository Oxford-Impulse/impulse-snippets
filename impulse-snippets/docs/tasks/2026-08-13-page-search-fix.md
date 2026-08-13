# Fix: specific-pages search hides pages; pasted links not usable (1.17.1)

**Status:** Done (2026-08-13)

## Problem (reported by Omer, on his live site)

1. The "Specific pages or posts" search on the Display Conditions box showed only blog posts, never pages. Cause: WordPress's `s` search matches body text too, so on a content-heavy site every post mentioning the brand name matched, and the single shared 20-result cap left no room for pages.
2. Pasting a page link into the search box found nothing. The old dedicated "paste a URL" field was dropped when the type-to-search picker replaced the checkbox list, and the picker only understood text.

## Fix (all in `includes/class-wpci-rest-controller.php`, `search_posts()`)

- Terms starting with `http://`/`https://` are resolved via `url_to_postid()` and returned as a single clickable result (published posts only).
- Text searches now use `search_columns => ['post_title']` (titles only; WP 6.2+ — silently ignored on older versions, which then fall back to the old broader matching).
- Pages are queried separately from all other post types (10 results each, pages listed first) so posts can never crowd them out.

## Also in this release

- Version bump 1.17.0 → 1.17.1, readme changelog entry.
- NOT resubmitted to WordPress.org — the queued review copy stays at 1.17.0 per the standing rule (never resubmit while queued). 1.17.1 ships via GitHub/manual install only; WP.org gets it as an update after approval.

## Recurring build gotcha (second occurrence!)

The distributed 1.17.0 zip had backslash entry paths (built with a default Windows zip tool), which WordPress cannot extract — "Eklenti dosyası bulunamadı". Zips must be built with the .NET `CreateEntryFromFile` method using `.Replace('\', '/')` on entry paths, from a staged clean copy (excludes CLAUDE.md, AGENTS.md, skills-lock.json, docs/, .claude/, .agents/ — 33 files). Verify entries after every build.
