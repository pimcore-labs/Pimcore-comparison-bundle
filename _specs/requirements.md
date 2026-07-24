# PimcoreComparisonBundle — Requirements & Requirement IDs

**Status:** Draft v0.1 · **Date:** 2026-07-24 · Companion to `base-idea.md` and `design-concept/`.

This file is the **authored source of requirement IDs**. The `_state/registry` and `_state/parity` seeds
reference these IDs; once the `comparison:features:state` / `comparison:parity:state` generators are ported
(implementation plan Phase 7) they scan `_specs/**/*.md` for these ID shapes and reproduce
`spec-coverage.yaml` automatically. ID families are fixed by the scanner regex:

| Family | Shape | Meaning |
|---|---|---|
| `C-n` | `C-\d+` | Cross-cutting invariants / core constraints |
| `FR-CMP-nnn` | `FR-[A-Z]+-\d+` | Functional requirements |
| `E2E-CMP-nnn` | `E2E-[A-Z]+-\d+` | End-to-end acceptance scenarios (Playwright) |
| `T-SEC-nnn` | `T-SEC-\d+[a-z]?` | Security gates |
| `T-ARCH-nnn` | `T-ARCH-\d+` | Architecture gates |
| `T-FEAT-nnn` | `T-FEAT-\d+` | Feature-registry discipline gates |

> The illustrative `T-SEC-CMP-*` shape is intentionally NOT used — the scanner's `T-SEC-\d+[a-z]?` prefix is
> fixed, so security IDs carry no `CMP` infix. Functional/e2e IDs use the `CMP` family segment.

---

## Core invariants (`C-*`) — Goals, Non-goals, §7

- **C-1** — Same class only: both objects exist, are `Concrete`, and share an identical `ClassDefinition`. (Goal 1, §6.2)
- **C-2** — Permission parity: field-level layout permissions are enforced server-side; fields the user may not see are emitted as `hidden` and carry no value. (Goal 4, §7)
- **C-3** — Read-only in v1: no merge, copy, or write of object values; no write route exists. (§3)
- **C-4** — Stateless: no object data is persisted; comparisons are computed on demand; ETag keyed on the pair of `modificationDate`s. (§7)
- **C-5** — Thin client: the diff is computed server-side so permission logic lives in one place; the client only renders. (§6.4)
- **C-6** — Exactly two objects in v1 (n-way comparison is out of scope). (§3)

## Functional requirements (`FR-CMP-*`) — §5 UX + §6 architecture

- **FR-CMP-001** — `ComparisonService` orchestration: load left/right, run guards, assemble the result. (§6.1/6.2)
- **FR-CMP-002** — `FieldWalker` walks the class **layout** definition (not the flat field list), preserving panels/tabs/regions as sections. (§6.2)
- **FR-CMP-003** — `ComparatorRegistry`: tagged comparators resolved by priority, first-supporting-wins. (§6.2)
- **FR-CMP-004** — `ScalarComparator` (input/number/select/checkbox/date scalars). (§6.1)
- **FR-CMP-005** — `TextDiffComparator`: word/character-level inline diff for textual fields. (§5.3/6.2)
- **FR-CMP-006** — WYSIWYG diff on sanitized HTML, with rendered + source toggle. (§5.3/6.2)
- **FR-CMP-007** — `RelationComparator`: classify related elements as added / removed / kept / reordered. (§5.3)
- **FR-CMP-008** — `LocalizedFieldsComparator`: one sub-row per enabled language; global language filter. (§5.3)
- **FR-CMP-009** — `FieldCollectionComparator`: items matched by index (v1), expandable nested sections. (§5.3)
- **FR-CMP-010** — `ObjectBrickComparator`: expandable brick sections. (§6.1)
- **FR-CMP-011** — `ClassificationStoreComparator`: walk active groups / items. (§6.1)
- **FR-CMP-012** — `FallbackComparator`: `getVersionPreview()`-based rendering for unknown/custom fieldtypes. (§6.2)
- **FR-CMP-013** — Image/asset field: thumbnail preview side-by-side + asset id/path equality (binary hash is P2). (§5.3)
- **FR-CMP-014** — Normalization: trim-insensitive strings, numeric epsilon, date UTC/precision, `""` vs `null`. (§6.2)
- **FR-CMP-015** — Status taxonomy: `equal` / `changed` / `only-left` / `only-right` / `reordered` / `not-comparable` / `hidden`. (§5.3)
- **FR-CMP-016** — `DiffResultAssembler` produces a normalized `FieldDiff` DTO tree (`status`, `leftDisplay`, `rightDisplay`, `inlineDiff`, `children`, `meta`). (§6.2)
- **FR-CMP-017** — Filters: *all / differences-only / equal-only* + a free-text field-name filter. (§5.2/5.3)
- **FR-CMP-018** — Summary counts: "N of M fields differ" + per-category counts. (§5.2)
- **FR-CMP-019** — Swap sides + a global language selector for localized fields. (§5.2)
- **FR-CMP-020** — Section-level pagination/streaming for very large classes; client renders sections as they arrive. (§6.3/8)
- **FR-CMP-021** — XLSX export of the current (filtered) diff. (§5.4)
- **FR-CMP-022** — JSON export of the current (filtered) diff. (§5.4)
- **FR-CMP-023** — Comparator SPI: projects register a higher-priority comparator to override core behaviour. (§6.2/6.5)
- **FR-CMP-024** — `PreComparisonEvent` (mutate options / veto) and `PostComparisonEvent` (mutate result). (§6.5)
- **FR-CMP-025** — Bundle config: normalization toggles, per-class excluded fields, default filter mode, export formats. (§6.5)
- **FR-CMP-026** — REST `GET /pimcore-studio/api/comparison/objects` — full diff result (section-paginated). (§6.3)
- **FR-CMP-027** — REST `GET /pimcore-studio/api/comparison/objects/summary` — counts only. (§6.3)
- **FR-CMP-028** — REST `POST /pimcore-studio/api/comparison/objects/export` — XLSX/JSON export. (§6.3)
- **FR-CMP-029** — Entry point: grid multi-select context action "Compare objects" (exactly two same-class objects). (§5.1)
- **FR-CMP-030** — Entry point: object-editor "Compare with…" dialog with a same-class object search. (§5.1)
- **FR-CMP-031** — Deep link `/studio/comparison?left={id}&right={id}` + copy-to-clipboard. (§5.1)
- **FR-CMP-032** — Studio SDK plugin: `ComparisonView` / `DiffTable` (virtualized) / `InlineTextDiff` / `RelationChipDiff` / `ContainerSection` / `SummaryBar`. (§6.4)

## Acceptance scenarios (`E2E-CMP-*`) — Playwright, from §5

- **E2E-CMP-001** — "Compare objects" is enabled only when exactly two objects of the same class are selected.
- **E2E-CMP-002** — The "Compare with…" dialog is pre-filtered to the same class and launches the comparison.
- **E2E-CMP-003** — The deep link `/studio/comparison?left&right` renders the comparison view.
- **E2E-CMP-004** — The *Differences only* filter narrows the table to changed/only-* rows.
- **E2E-CMP-005** — The free-text field filter narrows rows by field label.
- **E2E-CMP-006** — A changed text field renders an inline token diff (insertions/deletions).
- **E2E-CMP-007** — A WYSIWYG field is diffed on sanitized HTML.
- **E2E-CMP-008** — Localized fields render one sub-row per language; a translation gap surfaces as `only-left`/`only-right`.
- **E2E-CMP-009** — Relations render chips classified added / removed / kept.
- **E2E-CMP-010** — A reorder-only relation change is flagged `reordered` and is filterable as noise.
- **E2E-CMP-011** — A field collection matches items by index and shows an only-right item.
- **E2E-CMP-012** — An object-brick section renders as an expandable nested section.
- **E2E-CMP-013** — A classification-store section renders its groups/items.
- **E2E-CMP-014** — An image/asset field renders thumbnails + asset id/path on both sides.
- **E2E-CMP-015** — A permission-hidden field renders a locked placeholder with no value.
- **E2E-CMP-016** — A calculated field that errors renders `not-comparable`.
- **E2E-CMP-017** — Swap flips the sides (and `only-left` ↔ `only-right`).
- **E2E-CMP-018** — Expand-all / collapse-all toggles all container sections.
- **E2E-CMP-019** — XLSX export downloads the current filtered view.
- **E2E-CMP-020** — JSON export downloads the current filtered view.
- **E2E-CMP-021** — The summary bar reports correct differ/equal/hidden/not-comparable counts.
- **E2E-CMP-022** — The empty state ("No fields match the current filter") shows when a filter matches nothing.
- **E2E-CMP-023** — The deep-link copy button copies the shareable URL.

## Security gates (`T-SEC-*`) — §7

- **T-SEC-001** — Both objects require `view` permission for the current user; denial ⇒ 403 / no data.
- **T-SEC-002** — Field-level layout permissions are enforced server-side; hidden fields never leave the backend (C-2).
- **T-SEC-003** — The `plugin_comparison` permission gates the feature per user role.
- **T-SEC-004** — No object data is persisted by the bundle (stateless, C-4).
- **T-SEC-005** — No write/merge route exists in v1 (read-only, C-3).
- **T-SEC-006** — Export honours the exact same filtering and permission masking as the view.
- **T-SEC-007** — The same-class + `Concrete` guard cannot be bypassed (C-1).

## Architecture gates (`T-ARCH-*`) — §9

- **T-ARCH-001** — Import boundaries: comparators depend only on the SPI and the core `Data` diff API, not on controllers.
- **T-ARCH-002** — `@internal` contract audit: every used vendor `@internal` symbol is tracked.
- **T-ARCH-003** — Frontend purity: the client is a thin renderer (no business/permission logic); Studio SDK components only.

## Feature-registry discipline gates (`T-FEAT-*`) — ported in Phase 7

- **T-FEAT-001** — No feature claims a status the test evidence does not support (overclaim gate).
- **T-FEAT-002** — No test references a feature id that no `#[AsFeature]` declares.
- **T-FEAT-003** — No feature is more mature than its dependencies.
- **T-FEAT-004** — The untagged-test ratio does not grow.
- **T-FEAT-005** — The committed `_state` matches the generated one (`git diff --exit-code`).
- **T-FEAT-006** — Every spec requirement id is claimed by some feature.
