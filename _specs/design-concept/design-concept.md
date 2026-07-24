# PimcoreComparisonBundle — Design Concept

**Status:** concept prototype · draft v0.1
**Source of truth (visual):** `Comparison View.dc.html` in this folder (a Claude Design prototype —
`<x-dc>` component format; needs the DC runtime to render live, kept here as the authoritative visual).
**Origin:** Claude Design project `8b608c34-3ba3-4681-bafe-f7dbd1e8c5d3`, file `Comparison View.dc.html`.

> ⚠️ **The bundled `_ds/pimcore-design-system-…` is a marketing-mock approximation** built from public
> screenshots — Inter font, Lucide icons, `--pc-*` CSS variables. It is **NOT** the Pimcore Studio UI SDK.
> The implementation MUST reproduce the *layout and behaviour* below using **only real Studio SDK components**
> (`@pimcore/studio-ui-bundle` over the shared Ant Design 5.22 singleton) with SDK theme tokens and the SDK
> `Icon` component. Drop the `--pc-*` tokens and Lucide entirely. This is a hard constraint from the product owner.

---

## 1. The three screens (the flow)

The prototype models a 3-step flow: **1 · Entry (listing)** → **2 · Compare with…** → **3 · Comparison view**.

### Screen 1 — Entry point: grid multi-select → context menu
A Studio Data Objects listing (`/Products`) grid: columns *checkbox, ID, Key, Name, SKU, Published (badge),
Modified*. A selection bar shows **"2 selected"** + a **"class: Product"** pill. Right-clicking the selection
opens a context menu whose relevant item is **"Compare objects"**. Guard note, verbatim:
> "Compare objects" is enabled only when exactly two objects of the same class are selected.

### Screen 2 — Entry point: "Compare with…" dialog
From the object-editor toolbar (breadcrumb + Published badge + Preview / **Compare with…** / Save & publish),
a modal titled **"Compare with…"** opens over the editor:
- Subtitle: *"Pick a second object to compare with `TRAIL-PRO-29-XL` — results are pre-filtered to class Product."*
- A search input with a live result count (e.g. "4 results").
- A result list — each row: object icon, mono **key**, `name · path`, `ID`, a select check.
- Footer: *"Same class only · opens a read-only comparison"* + **Cancel** / **Compare**.

### Screen 3 — Comparison view (the primary deliverable)
Full-width panel, top → bottom:

1. **Header** — compare icon + **"Compare objects"**; a mono **deep-link pill**
   `/studio/comparison?left={id}&right={id}` with a copy button; right-aligned *"Class: Product · read-only (v1)"*.
2. **Two object cards** — `LEFT · (swap) · RIGHT`. Each card: object icon, mono **key**, **published/draft badge**,
   `path`, `ID {id} · modified {date} · {by}`, and a `LEFT`/`RIGHT` corner label. A circular **swap** button sits
   between them (swapping also flips `only-left` ↔ `only-right`).
3. **Toolbar** — a segmented **filter** (`All fields` / `Differences only` / `Equal only`); a free-text
   **"Filter fields…"** input; **Languages** toggle chips (EN / DE / FR); **expand-all** / **collapse-all** icon
   buttons; an **Export ▾** dropdown (*Export as XLSX* / *Export as JSON*, note *"Exports the current filtered
   view · honors permissions"*).
4. **Diff table** — see §2.
5. **Summary bar** — pie icon + **"N of M fields differ"** + per-category detail (`… equal · … hidden by
   permissions · … not comparable`); right note: *"Computed server-side · permissions enforced · nothing persisted"*.
6. **Toasts** — transient confirmations (link copied, export ready).

Prototype props: `startView` (comparison/listing/dialog), `density` (comfortable/compact), `rowTint` (bool).

---

## 2. Diff-table anatomy

Grid columns: **`Field | Left {leftKey} | Right {rightKey} | Status`** (sticky header). Three row kinds:

- **Section row** (collapsible) — a layout group from the class layout tree: *General, Descriptions, Pricing,
  Relations, Media,* plus **tagged** containers: *Technical specs* `Field collection`, *SEO* `Object brick`,
  *Drivetrain* `Classification store`. Shows a chevron, the uppercase section label, an optional type tag, and a
  right-aligned count.
- **Item row** (nested, e.g. field-collection items) — *"Item #0 · Frame"* with a layers icon, an optional status
  badge, and a note like *"matched by index (v1)"* / *"item exists on right only"*.
- **Field row** — `Field label (+ optional language badge, note, meta) | Left cell | Right cell | Status`.

**Cell render kinds** (each side independently):
- **plain** — text; `—` (muted) for null. Mono variant for codes/dates.
- **inline** — word/char **token diff**: deletions struck-through (danger), insertions underlined (success);
  unchanged tokens neutral. Used for text + WYSIWYG (diffed on sanitized HTML).
- **chips** — relations: `kept` (neutral), `added` (success, ＋), `removed` (danger, −, struck), `reordered`
  (info, ↕). Meta line e.g. *"2 added · 1 removed · 2 kept"*; reorder-only is flagged as filterable noise.
- **image** — thumbnail + mono asset path + `Asset ID`.
- **hidden** — *"Value withheld"* with a lock icon (hatched row background); field masked by permissions.
- **not-comparable** — *"Calculated value failed on this object"* (danger, alert icon).

**Localized fields** render one sub-row per language with a mono language badge (EN/DE/FR); the toolbar language
chips filter which languages show globally. A missing translation on one side surfaces as `only-left`/`only-right`.

---

## 3. Status taxonomy (icon + colour — colour is never the only signal)

| Status | Meaning | Icon (design) | Semantic colour | SDK token |
|---|---|---|---|---|
| `equal` | identical after normalization | equals | neutral / grey | neutral text |
| `changed` | both set, differ | pencil | amber / warning | `warning` |
| `only-left` | value on left only | arrow-left | red / danger | `danger` |
| `only-right` | value on right only | arrow-right | green / success | `success` |
| `reordered` | relations same set, order differs | up-down | blue / info | `info` |
| `not-comparable` | calculated/errored value | alert-circle | red / danger | `danger` |
| `hidden` | masked by field permissions | lock | neutral (hatched) | neutral, no value |

Normalization that produces `equal` despite raw differences: numeric (`13.4` == `13.40`), trim, date UTC/precision,
`""` vs `null`. Rows may carry a subtle status tint (toggle `rowTint`).

---

## 4. Design → Studio SDK component mapping (SDK-only, per the hard constraint)

Rebuild with `@pimcore/studio-ui-bundle` components over the shared Ant Design 5.22 singleton + SDK theme tokens +
the SDK `Icon` component. Exact SDK export names to be read from
`vendor/pimcore/studio-ui-bundle/assets/js/src/components` at build time.

| Design element | SDK / antd component |
|---|---|
| Grid + "Compare objects" context action (Screen 1) | Studio grid + `ContextMenuRegistry.registerToSlot('data-object.list-grid', …)`; item hidden unless 2 same-class rows selected |
| "Compare with…" modal (Screen 2) | SDK `Modal` + `SearchInput`/`Input` + result `List`; Cancel/Compare `Button`s |
| Deep-link pill + copy | mono text + SDK `IconButton` (`Icon` copy) → clipboard |
| Two object header cards, LEFT · swap · RIGHT | SDK `Flex`/layout + `Card`; published/draft = antd `Tag`; swap = SDK `IconButton` |
| Filter All / Differences / Equal | antd `Segmented` |
| Field free-text filter | SDK `SearchInput` / antd `Input` |
| Language toggle chips | antd `Segmented` (multiple) or `Tag.CheckableTag` |
| Expand-all / collapse-all | SDK `IconButton` + `Icon` |
| Export ▾ (XLSX / JSON) | antd `Dropdown` + `Button` |
| Diff table (virtualized, sticky header, sections) | SDK `Grid`/table primitive or antd `Table` (virtual rows); collapsible sections = antd `Collapse` semantics |
| Inline token diff (ins underline / del strike) | plain spans styled from SDK semantic tokens (success / danger) |
| Relation chips (kept/added/removed/reordered) | antd `Tag` in the four semantic colours + `Icon` |
| Image cell | SDK asset thumbnail / antd `Image` + mono path |
| hidden / not-comparable cells | `Icon` (lock / alert) + muted / danger token text |
| Status column (icon + label, never colour-only) | `Icon` + `Tag`, semantic tokens |
| Summary bar / toast | SDK layout + notification API |

**Do NOT** ship: Lucide icon font, Inter/JetBrains web fonts, the `--pc-*` CSS variables, or the `<x-dc>` runtime.
Those belong to the mock only; the SDK provides the equivalents.

---

## 5. Behavioural contract (drives the E2E specs)

- "Compare objects" appears only for exactly two same-class selections; disabled/hidden otherwise.
- Filter narrows rows to All / Differences-only / Equal-only; field free-text filter narrows by label.
- Language chips add/remove per-language sub-rows.
- Swap flips sides and `only-left` ↔ `only-right`.
- Export XLSX / JSON downloads the **current filtered** view and honours permissions.
- `hidden` rows show no value to a user lacking field view permission.
- Everything is computed server-side; nothing is persisted; read-only in v1.
