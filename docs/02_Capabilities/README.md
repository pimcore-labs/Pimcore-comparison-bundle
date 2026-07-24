# Capabilities

A type-aware, side-by-side diff of two data objects of the same class, inside Pimcore Studio.

## Entry points

1. **Grid multi-select** — select exactly two objects of the same class in a listing → context-menu
   action **"Compare objects"** (disabled otherwise).
2. **Object editor** — **"Compare with…"** opens a same-class object search.
3. **Deep link** — `/studio/comparison?left={id}&right={id}` for sharing and dedup integrations.

## The comparison view

- **Two object header cards** (LEFT · swap · RIGHT): key, published/draft state, path, id, modified, author.
  A swap button flips the sides.
- **Toolbar** — filter *All / Differences only / Equal only*, a free-text field filter, per-language chips,
  expand/collapse all, and an **Export** dropdown (XLSX / JSON).
- **Diff table** — one row per attribute grouped by the class layout's sections, with the left value, the
  right value, and a status badge.
- **Summary bar** — "N of M fields differ" plus per-category counts.

## Type-aware diffing

| Fieldtype | Behaviour |
|---|---|
| Scalars (input, number, select, date, …) | normalized equality (trim, numeric epsilon, UTC dates, ""↔null) |
| Text / WYSIWYG | word-level **inline diff**; WYSIWYG diffed on sanitized HTML |
| Relations | classified **added / removed / kept / reordered**; pure reorder noise is filterable |
| Localized fields | one sub-row per language; translation gaps surface as only-left / only-right |
| Field collections | items matched by index (v1), expandable nested rows |
| Object bricks | matched by brick type, expandable nested rows |
| Classification store | walked by group / key / language |
| Image / asset fields | side-by-side, compared by asset id / path |
| Unknown / custom | falls back to the fielddetype's own version preview |

## Status taxonomy

`equal`, `changed`, `only-left`, `only-right`, `reordered`, `not-comparable` (a calculated value that
errored), `hidden` (a field the user may not see — shown as a locked placeholder, never with a value).
Status is encoded by both colour and icon, so colour is never the only signal.

## Guarantees

- **Read-only** — v1 never writes; there is no merge/copy route.
- **Stateless** — nothing is persisted; results are cache-friendly (ETag on the modification-date pair).
- **Permission-safe** — element view-permission on both objects is required, and field-level layout
  permissions are enforced server-side; export honours the exact same filtering and masking.

## Narrated capability walkthrough

📹 **[`comparison-capability.mp4`](comparison-capability.mp4)** — a narrated walkthrough of the full
capability: the two object headers, the type-aware diff table (scalars, inline text diff, per-language
localized rows, relation chips added/removed/kept/reordered, field-collection / object-brick /
classification-store sections, permission-hidden and not-comparable rows), the filters, export, and the
summary bar.

The video is produced by a re-runnable pipeline (Playwright records the walkthrough → macOS `say`
voices the script → `ffmpeg` muxes):

```bash
cd tests/e2e
npx playwright test --project=tour --grep 'capability-walkthrough render'   # → tour-recordings/**/video.webm
scripts/build-capability-video.sh comparison-capability scripts/narration/comparison-capability.txt
# → docs/02_Capabilities/comparison-capability.mp4
```

The narration script lives in `tests/e2e/scripts/narration/`; edit it and re-run to revoice. Additional
per-flow videos (entry points, permissions, export) reuse the same pipeline with their own tour spec +
narration script.
