# Selective export — design

**Date:** 2026-08-11 · **Approved by:** Omer (during 1.16.0 Playground walkthrough)
**Replaces:** the "export everything only" behavior of the Import/Export page.

## What we're building

Let the user choose which snippets an export contains, from two entry points that
share one backend:

1. **Bulk action on All Snippets** (`edit.php?post_type=wpci_snippet`): a new
   "Export" entry in the Bulk actions dropdown. Tick rows → Apply → the browser
   downloads a JSON file containing only those snippets. This is the
   WPCode / Code Snippets pattern and reuses the native list UI.
2. **Checkbox picker on the Import/Export page**: the Export card lists every
   snippet (title — location, Published/Draft) with a checkbox, **all ticked by
   default**, plus a "Select all" master checkbox. All-ticked = today's
   one-click full export, so existing behavior is preserved by default.

## Decisions

- **File format unchanged** (`EXPORT_VERSION 1`); a partial export is just a
  shorter `snippets` array. Import side needs zero changes.
- **Empty selection fails friendly**: unticking everything and submitting shows
  "Select at least one snippet to export." via the existing notice mechanism —
  never an empty file.
- **Zero snippets on the site**: the Export card shows "No snippets to export
  yet." instead of a form.
- **Where the code lives**: all of it in `Wpci_Import_Export` (which already
  owns the Duplicate row action, so list-table-adjacent actions have precedent
  there). The export query/payload builder is refactored to accept an optional
  ID list; both entry points call it.
- **Bulk action nonce**: WordPress core verifies the bulk-actions nonce
  (`bulk-posts`) before the `handle_bulk_actions-*` filter fires; the handler
  re-checks capability and sanitizes IDs but does not re-verify the nonce
  (documented in code).
- **Select-all JS**: new `assets/js/admin-import-export.js` (vanilla, no
  dependencies), enqueued only on the Import/Export page. Master checkbox
  toggles all; unticking any row unticks the master.
- **Long lists**: the picker gets a max-height with scroll so a site with many
  snippets doesn't stretch the card.

## Out of scope

- Changing the export filename per selection.
- Any import-side changes.
- Per-snippet export row action (the bulk action covers single-snippet export:
  tick one row).

## Testing

- PHPCS stays at zero findings; the 44 standalone tests still pass (this
  feature is WordPress-query-bound, so its verification is manual).
- Live Playground checks: bulk-export a subset from the list; partial export
  from the page picker; empty-selection error; re-import the partial file
  (arrives as drafts).
