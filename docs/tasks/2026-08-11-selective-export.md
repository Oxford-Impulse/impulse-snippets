# Selective export — choose which snippets to download

**Status:** DONE (2026-08-11) — brainstormed, spec'd (docs/specs/2026-08-11-selective-export-design.md), built as both a list bulk action and a filterable pre-ticked page picker, verified live in Playground.
**Source:** Omer, 2026-08-11, during the 1.16.0 Playground walkthrough.

## The idea

Today the Export button on Import/Export downloads *every* snippet. Omer suggested
letting the user choose: all snippets, or only specific ones.

## Why it's worth doing

- Real use case: moving one working snippet (e.g. a tuned cookie banner) to another
  site without dragging test/draft snippets along.
- Competitor precedent: **Code Snippets** and **WPCode** both offer per-snippet
  export via checkboxes + a bulk action on the snippets list table, not via their
  import/export page.

## Design questions for the brainstorming session

1. Where does selection live — checkboxes on the Import/Export page, or a native
   **bulk action ("Export selected") on the All Snippets list table** (the
   competitor pattern, and likely the WordPress-native answer)?
2. Do both entry points coexist (list bulk action for "some", Import/Export page
   button for "all")?
3. Does the export filename reflect the selection (e.g. `impulse-snippets-2-of-6.json`)?
4. No format change expected — the JSON export format (EXPORT_VERSION 1) already
   supports any subset; import side needs no changes.

## Scope guess

Small: one bulk action handler + reusing the existing export builder with an ID
filter. But confirm the design first — don't build from this file alone.
