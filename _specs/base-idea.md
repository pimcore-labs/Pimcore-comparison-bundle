# PimcoreComparisonBundle — Technical Concept

**Status:** Draft v0.1
**Author:** Dietmar Rietsch
**Date:** 2026-07-24
**Target Platform:** Pimcore Platform ≥ 2026.1 (Studio UI), Symfony 7.4, PHP ≥ 8.3

> Note on naming: the working title "PimcoreComparrisonBundle" contains a typo. This concept uses the corrected name **PimcoreComparisonBundle** (Composer package `pimcore/comparison-bundle`, namespace `Pimcore\Bundle\ComparisonBundle`).

---

## 1. Problem Statement

Editors, data stewards, and PIM managers frequently need to understand how two data objects of the same class differ: duplicate candidates before a merge, a product and its variant, a migrated object versus its source, or two supplier records for the same article. Today this requires opening both objects in separate tabs and comparing field by field manually, which is slow and error-prone, especially for classes with hundreds of attributes, localized fields, and structured containers.

Pimcore Studio offers version comparison *within* one object, but there is no first-class capability to compare *two different objects* side by side. The PimcoreComparisonBundle closes this gap with a dedicated, type-aware comparison view rendered as a clean, filterable diff table inside Studio.

## 2. Goals

1. Compare any two data objects of the **same ClassDefinition** in a side-by-side diff table within Studio.
2. Provide **type-aware diffing** for every core fieldtype, including localized fields, relations, field collections, object bricks, and classification store.
3. Provide **inline text diffing** (character/word level) for textual fields including WYSIWYG content.
4. Respect all existing **permissions** (workspaces, class permissions, field-level layout permissions) with zero bypass paths.
5. Ship as a standard, standalone, installable bundle following Pimcore engineering standards, extensible via a Comparator SPI.

## 3. Non-Goals (v1)

- **Merging / copying values** between objects (P1 candidate, see roadmap; v1 is read-only).
- Comparing objects of **different classes** or class versions (semantically ambiguous; out of scope).
- Comparing **assets or documents** (separate concern; the architecture should not preclude it).
- Comparing **more than two** objects (n-way diff is a P2 consideration).
- Replacing the existing **version comparison** of a single object.

## 4. User Stories

- As a **data steward**, I want to open two products side by side and immediately see only the fields that differ, so that I can decide which one is the golden record.
- As an **editor**, I want inline highlighting of what changed inside a long description, so that I do not have to read both texts in full.
- As a **PIM manager**, I want localized fields compared per language, so that I can spot translation gaps between two records.
- As a **developer/integrator**, I want to register a custom comparator for my custom fieldtype, so that project-specific datatypes diff correctly.
- As an **admin**, I want the comparison to respect field-level permissions, so that users never see values they are not allowed to see on the regular edit layout.

## 5. UX Concept

### 5.1 Entry Points

1. **Grid/listing multi-select:** select exactly two objects of the same class in a Studio listing → context menu action "Compare objects".
2. **Object editor toolbar:** "Compare with…" opens an object search dialog pre-filtered to the same class.
3. **Deep link:** `/studio/comparison?left={id}&right={id}` for sharing and for programmatic integrations (e.g. dedup workflows).

### 5.2 Comparison View

A full-width Studio panel with:

| Zone | Content |
|---|---|
| Header | Both object headlines (key, path, ID, modification date, published state), swap-sides button, language selector for localized fields |
| Toolbar | Filter: *All fields / Differences only / Equal only*; free-text field filter; expand/collapse all containers; export |
| Diff table | One row per attribute: field label, left value, right value, status badge |
| Footer | Summary: "23 of 148 fields differ" plus per-category counts |

### 5.3 Diff Table Behavior

- **Status per row:** `equal`, `changed`, `only-left`, `only-right` (value set on one side only), `not-comparable` (e.g. calculated values that error out), `hidden` (permission-filtered, rendered as a locked placeholder without values).
- **Row coloring:** subtle background tint per status (green/amber/red tokens from the Studio design system), full WCAG AA contrast, status also encoded via icon so color is never the only signal.
- **Inline diff:** textual fields render word-level diffs (insertions underlined green, deletions struck-through red). WYSIWYG is diffed on sanitized HTML with a rendered and a source toggle.
- **Structured fields:** field collections, blocks, and bricks render as expandable nested sections; items are matched by index (v1) with a stable-key matching strategy hook for later.
- **Relations:** rendered as chips; classified into *added / removed / kept / reordered*. Reorder-only changes get their own status flag so pure ordering noise can be filtered out.
- **Localized fields:** grouped per field with one sub-row per enabled language; the language selector filters languages globally.
- **Images/assets in fields:** thumbnail preview side by side plus asset ID/path equality check (binary hash comparison is P2).

### 5.4 Export

- Export the current (filtered) diff as **XLSX** and **JSON** via a server-side export endpoint. Useful for audits and merge preparation offline.

## 6. Architecture

### 6.1 High-Level Flow

```
Studio UI (React plugin)
   │  GET /pimcore-studio/api/comparison/objects?leftId=…&rightId=…&locales=…
   ▼
ComparisonController (REST, OpenAPI-documented)
   ▼
ComparisonService
   ├─ ObjectResolver        → loads both objects, validates same class, checks permissions
   ├─ FieldWalker           → iterates ClassDefinition layout tree (incl. containers)
   ├─ ComparatorRegistry    → resolves comparator per fieldtype (tagged services)
   │     ├─ ScalarComparator, TextDiffComparator, RelationComparator,
   │     ├─ LocalizedFieldsComparator, FieldCollectionComparator,
   │     ├─ ObjectBrickComparator, ClassificationStoreComparator, …
   │     └─ FallbackComparator (getVersionPreview()-based)
   └─ DiffResultAssembler   → normalized DTO tree → JSON
```

### 6.2 Backend Components

**`ComparisonService`** (core orchestrator)
- Input: `leftId`, `rightId`, options (locales, includeEqual, fieldFilter).
- Guards: both objects exist, both are `Concrete`, identical `ClassDefinition` ID, user has view permission on both (workspace check via existing security services).
- Walks the **layout definition** (not the raw field list) so grouping, panels, and tabs from the class layout are preserved as the table's section structure. Fields hidden by layout permissions for the current user are emitted as `hidden` without values.

**`FieldComparatorInterface`** (the SPI)

```php
interface FieldComparatorInterface
{
    public function supports(Data $fieldDefinition): bool;

    public function compare(
        mixed $leftValue,
        mixed $rightValue,
        Data $fieldDefinition,
        ComparisonContext $context
    ): FieldDiff;
}
```

- Registered via service tag `pimcore.comparison.field_comparator` with a `priority` attribute; first supporting comparator wins. Projects override core behavior by registering a higher-priority comparator.
- `FieldDiff` DTO: `status`, `leftDisplay`, `rightDisplay`, `inlineDiff` (optional token list), `children` (for containers), `meta` (e.g. relation add/remove counts).

**Text diffing**
- Server-side word/character diff using a permissively licensed PHP diff library (MIT/BSD, per dependency policy) or an in-house Myers-diff implementation (small, well-understood, no dependency). Decision in §10 Open Questions.
- WYSIWYG: sanitize both sides with the platform's existing HTML sanitizer, then diff on a normalized token stream so attribute-order noise does not produce false positives.

**Normalization rules (false-positive avoidance)**
- Trim-insensitive comparison for strings (configurable).
- Numeric comparison with type coercion and configurable float epsilon.
- Date/DateTime compared in UTC at field-defined precision.
- Empty string vs. null treated as equal by default (configurable per project via bundle config).

### 6.3 REST API

Follows Studio API conventions (attribute routing, OpenAPI annotations, ProblemDetails errors):

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/pimcore-studio/api/comparison/objects` | Full diff result (paginated by section for very large classes) |
| `GET` | `/pimcore-studio/api/comparison/objects/summary` | Counts only (for badges, dedup tooling) |
| `POST` | `/pimcore-studio/api/comparison/objects/export` | XLSX/JSON export of a diff |

All endpoints require an authenticated Studio session or API token; standard rate limiting applies.

### 6.4 Frontend (Studio UI Plugin)

- Implemented as a **Studio UI SDK plugin** (React, TypeScript, strict mode), no ExtJS anywhere.
- Registers: grid context-menu action, object-editor toolbar button, the comparison panel route, and a deep-link handler.
- Components: `ComparisonView`, `DiffTable` (virtualized rows for classes with hundreds of fields), `InlineTextDiff`, `RelationChipDiff`, `ContainerSection`, `SummaryBar`.
- Uses Studio design tokens exclusively; dark mode supported out of the box.
- State: RTK Query against the comparison API; the diff is computed server-side, the client only renders (thin-client principle, keeps permission logic in one place).

### 6.5 Extensibility & Events

- **Comparator SPI** as above (primary extension point).
- **Events:** `PreComparisonEvent` (mutate options, veto), `PostComparisonEvent` (mutate result, e.g. inject computed score for dedup use cases).
- **Config** (`pimcore_comparison.yaml`): normalization toggles, excluded fields per class, default filter mode, export formats.

## 7. Security & Permissions

- Reuses existing element permission checks for both objects; no new permission model in v1 beyond one bundle-level permission `plugin_comparison` to enable/disable the feature per user role.
- Field-level layout permissions are enforced server-side during the walk; values never leave the backend for hidden fields.
- Export honors the exact same filtering as the view.
- No object data is persisted by the bundle; comparisons are computed on demand (stateless, cache-friendly via ETag on `modificationDate` pair).

## 8. Performance Considerations

- Target: full diff of a 300-field class with localized fields in **< 800 ms** server-side (P95).
- Lazy relation display resolution (batch-load related element metadata in one query per relation field).
- Section-level pagination/streaming for extreme classes; the client renders sections as they arrive.
- Virtualized table rendering client-side; inline text diff computed only for rows in `changed` state.

## 9. Packaging, Quality, Delivery

- **Package:** `pimcore/comparison-bundle`, installable via Composer + `PimcoreBundleManager`, standard `Installer` (adds the `plugin_comparison` permission).
- **Standards:** Symfony coding standards + Pimcore PHP guidelines; CI with PHPStan, Psalm, PHP CS Fixer, and the Claude-based code-review skill.
- **Tests:** unit tests per comparator (golden-file fixtures per fieldtype), API functional tests, Playwright E2E for the Studio panel.
- **Docs:** README, docs page with screenshots, comparator extension guide with a full custom-fieldtype example.
- **Dependency policy:** no copyleft; any diff library must be MIT/Apache-2/BSD, actively maintained, CVE-checked.

### Phasing

| Phase | Scope |
|---|---|
| **v1.0 (MVP)** | Read-only diff table, all core fieldtypes, localized fields, filters, deep links, permissions, XLSX/JSON export |
| **v1.1** | Merge assist: copy value left↔right with save (opens the door to a dedup/golden-record workflow), stable-key matching for collections |
| **v2.0** | N-way comparison, asset comparison, similarity scoring for duplicate detection, agentic merge proposals via Pimcore Agent |

## 10. Open Questions

1. **Diff library vs. in-house Myers implementation** for text diffing (engineering): dependency footprint vs. maintenance cost.
2. **Field collection matching:** is index-based matching acceptable for v1, or do key fields need to be configurable per collection from the start? (product)
3. **Should version comparison of a single object eventually be re-based on this engine** to avoid two diff implementations in the platform? (architecture)
4. **Placement:** core-adjacent official bundle vs. marketplace bundle first? Affects release cadence and support commitments. (product/business)

## 11. Success Metrics

- Adoption: ≥ 25% of active Studio editor accounts trigger at least one comparison within 60 days of release.
- Task efficiency: median time from "open comparison" to "filtered differences visible" < 5 seconds.
- Extensibility proof: at least one partner-built custom comparator within two release cycles.
- Quality: zero permission-bypass findings in security review; < 1% of comparisons ending in `not-comparable` errors on core fieldtypes.