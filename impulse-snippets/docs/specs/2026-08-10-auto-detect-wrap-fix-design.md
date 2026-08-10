# Design: Fix Auto-detect code wrapping for bare JavaScript

Date: 2026-08-10
Status: Approved approach — pending spec review
Related task: docs/tasks/2026-08-10-fault-review-findings.md (P1, item 1)

## Problem

Snippets with Code type **Auto-detect** (the default) use this rule in
`wpci_maybe_wrap_code()` (`includes/functions-helpers.php`): *if the code
contains a `<` character anywhere, treat it as HTML and output it unchanged;
otherwise wrap it in `<script>` tags.*

Plain JavaScript legitimately contains `<` as the less-than operator
(e.g. `for (i = 0; i < 10; i++)`). Such code is misclassified as HTML and
printed on the live site as visible text instead of executing.

## Decisions made (with Omer, 2026-08-10)

1. **Auto-heal on update.** The wrapping decision is made at page-load time,
   not save time, so the fix applies to already-saved snippets immediately
   after the plugin updates. Snippets currently showing code as text will
   start executing. Accepted: the code was pasted into a code plugin
   intentionally and was visibly broken before.
2. **Heuristic: "starts with a real tag".** Code is treated as HTML only if,
   after trimming whitespace, it begins with `<` followed by a letter
   (`<div`, `<script`, `<META`), `!` (`<!--`, `<!DOCTYPE`), or `/` (stray
   closing tag). Everything else is wrapped as JavaScript.
   Rejected alternatives: bare "first char is `<`" (treats non-code like
   `< 5 items` as HTML); "ask user at save time" (bigger UI change, no
   auto-heal).

## Change

One condition in the `'auto'` branch of `wpci_maybe_wrap_code()`:

- Before: `if ( false !== strpos( $code, '<' ) ) { return $code; }`
- After: treat as HTML only when the trimmed code matches `/^<[a-z!\/]/i`;
  otherwise `return '<script>' . $code . '</script>';`

The function already trims `$code` at entry, so the regex anchors to the
real first character. No other branch (`script`, `style`, `html`), no other
file, and no stored data changes.

## Out of scope

- Explicit code types and external-file snippets (unchanged).
- Save-time validation or UI changes.
- Permanent test infrastructure (separate decision).

## Accepted edge case

HTML that starts with loose text (e.g. `Hello <b>world</b>`) under
Auto-detect gets wrapped as JavaScript and breaks. Never valid usage; the
explicit "HTML / mixed" type handles it correctly. The edit screen's
auto-detect description already tells users tags are added when their code
lacks them.

## Verification

1. **Standalone test script** (temporary, in scratchpad — not committed):
   loads `functions-helpers.php` standalone (define `ABSPATH`, stub `__()`
   if needed) and asserts `wpci_maybe_wrap_code()` output for:
   - bare JS containing `<` → wrapped in `<script>` (the bug fix)
   - bare JS without `<` → wrapped (unchanged behavior)
   - full `<script>…</script>` block → unchanged
   - `<div>…</div>` → unchanged
   - `<!-- comment -->` → unchanged
   - `<meta …>` → unchanged
   - empty string → empty
   - `Hello <b>world</b>` → wrapped (accepted edge case, documented)
   - types `script` / `style` / `html` → behavior identical to before
2. **Live check in WordPress Playground**: create an Auto-detect snippet
   with `for (i = 0; i < 3; i++) { console.log(i); }`, confirm it executes
   in the browser console and is not visible on the page.

## Release

- Bump version to 1.14.1 in `impulse-snippets.php` (header + `WPCI_VERSION`).
- Add readme.txt changelog entry: fix Auto-detect misclassifying JavaScript
  containing `<` as HTML.
