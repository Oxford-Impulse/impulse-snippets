# Browse-all-pages picker + Add-link button (1.17.2)

**Status:** Done (2026-08-13)

## Problem (reported by Omer after testing 1.17.1)

1. "Still it does not show all the pages" — the 1.17.1 title-only search worked as designed, but that design was wrong for the real use case: most of his pages don't contain the brand name in their *title*, so a title search can never surface them. Users need to *browse* pages, not guess title words.
2. "It does not add the link, maybe we should place a add button there" — the Display Conditions box has a second link field (`wpci_condition_post_url`) below the picker that is only processed at save time via bare `url_to_postid()`, which silently fails for the static front page, percent-encoded (non-ASCII) slugs, and some permalink setups. The failure notice (admin transient) evidently wasn't seen.

## Fix

- **REST route** (`class-wpci-rest-controller.php`): a term shorter than 2 chars now returns *all published pages* (alphabetical, cap 100) instead of nothing — this powers browsing.
- **JS** (`admin-edit-screen.js`): focusing the empty search box fetches and shows the full page list; emptying the box does the same instead of hiding results. Placeholder text updated.
- **Shared resolver** `wpci_resolve_url_to_post_id()` (`functions-helpers.php`): `url_to_postid()` plus fallbacks — strips `#fragment`, `rawurldecode`s encoded slugs, handles subdirectory installs, resolves the bare home URL to the static front page, tries `get_page_by_path()`, then a slug lookup across public post types. Used by both the REST route and the save handler.
- **Add button** next to the paste-a-link field (`class-wpci-edit-screen.php` + JS): resolves the link immediately via the REST route and shows inline green/red feedback ("Added: …" / "No published page or post was found…"). Enter in that field triggers Add instead of submitting the whole form. The save-time path still exists as a safety net for text left in the field.

## Notes

- Not resubmitted to WordPress.org (1.17.0 stays queued — standing rule). GitHub/manual only.
- .pot not regenerated (two new short strings); fold into the next regeneration pass.
- Zip built with the .NET forward-slash method (see zip-forward-slashes memory), from a staged clean copy.
