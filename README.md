# PimcoreComparisonBundle

Compare **two data objects of the same class** side by side in Pimcore Studio as a type-aware,
filterable **diff table**. Pimcore ships version comparison *within* one object; this bundle closes the
gap for comparing *two different objects* — duplicate candidates before a merge, a product and its
variant, a migrated object vs. its source, or two supplier records for the same article.

- **Package:** `pimcore/comparison-bundle`
- **Namespace:** `Pimcore\Bundle\ComparisonBundle`
- **Bundle class:** `Pimcore\Bundle\ComparisonBundle\PimcoreComparisonBundle`
- **Target platform:** Pimcore ≥ 2026.1 (Studio UI), Symfony 7, PHP ≥ 8.4
- **v1 scope:** read-only, stateless, permission-safe. No merging/copying values (roadmap), no
  cross-class or n-way comparison.

---

## Highlights

- **Type-aware diffing** for every core fieldtype: scalars, localized fields, relations, field
  collections, object bricks, classification store — with a `getVersionPreview()`-based fallback for
  unknown/custom types.
- **Inline text diff** (word level) for textual fields; WYSIWYG diffed on sanitized HTML.
- **Relations** classified as *added / removed / kept / reordered* — pure reorder noise is filterable.
- **Localized fields** compared per language.
- **Permission-safe:** reuses Pimcore's existing element and field-level layout permissions; hidden
  fields are rendered as a locked placeholder and never leave the backend.
- **Stateless:** nothing is persisted; comparisons are computed on demand (cache-friendly via ETag on
  the pair of modification dates).
- **Extensible** via a Comparator SPI and `Pre`/`PostComparisonEvent` events.

## Status

This bundle is under active construction against a phased plan (`_specs/plans/`). What is present today:

| Area | Status |
|---|---|
| Bundle skeleton, Installer, `plugin_comparison` permission, `/comparison/status` route | ✅ built + installed |
| Comparison engine: SPI, comparator registry, normalizer, field walker, `ComparisonService`, `FieldDiff`/`DiffResult` | ✅ built, unit-tested |
| Comparators: scalar, text/WYSIWYG, relation, localized | ✅ built, unit-tested |
| Comparators: field-collection, object-brick, classification-store, asset-field, fallback | 🔜 in progress |
| REST API (`objects` / `summary` / `export`), XLSX/JSON export | 🔜 planned |
| Studio UI plugin (grid action, compare-with dialog, deep link, diff table) | 🔜 planned |
| Feature-state registry generators, docs + narrated videos | 🔜 planned |

The machine-readable feature/spec state lives in [`_state/`](_state/) (see below).

---

## Installation

The bundle is wired into this skeleton via `config/bundles.php` and the root `composer.json` PSR-4
autoload map. To install (registers the `plugin_comparison` permission):

```bash
docker compose exec php bin/console pimcore:bundle:install PimcoreComparisonBundle
docker compose exec php php -d memory_limit=1024M bin/console cache:clear
```

As a standalone Composer package in another project:

```bash
composer require pimcore/comparison-bundle
bin/console pimcore:bundle:install PimcoreComparisonBundle
```

---

## Usage

### Entry points (Studio UI — planned)

1. **Grid multi-select:** select exactly two objects of the same class in a listing → context-menu
   action **“Compare objects”** (enabled only for two same-class objects).
2. **Object editor:** **“Compare with…”** opens a same-class object search.
3. **Deep link:** `/studio/comparison?left={id}&right={id}` for sharing and dedup integrations.

### REST API (planned)

Under the Studio API prefix `/pimcore-studio/api/comparison` (inside the Studio auth firewall):

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/comparison/objects?leftId=…&rightId=…&locales=…` | Full diff result |
| `GET` | `/comparison/objects/summary` | Counts only (badges, dedup tooling) |
| `POST` | `/comparison/objects/export` | XLSX / JSON export of the current filtered diff |
| `GET` | `/comparison/status` | Health / capability probe (available today) |

### Programmatic

```php
use Pimcore\Bundle\ComparisonBundle\Comparison\ComparisonService;

$result = $comparisonService->compareById($leftId, $rightId, ['locales' => ['en', 'de']]);
$result->differing();   // e.g. 23
$result->counts();      // ['equal' => 125, 'changed' => 18, 'only-left' => 3, ...]
$json = json_encode($result);
```

`compareById()` guards that both objects exist, are `Concrete`, and share an identical
`ClassDefinition` (same-class only). Field values the current user may not see are passed in via
`options['hiddenFields']` and emitted as `hidden` without values.

---

## Architecture

```
Studio UI (React plugin, SDK components only)
   │  GET /pimcore-studio/api/comparison/objects
   ▼
ComparisonController (REST)
   ▼
ComparisonService
   ├─ FieldWalker          → walks the class LAYOUT tree, preserving sections
   ├─ ComparatorRegistry   → resolves a comparator per fieldtype (tagged, priority)
   │     ├─ ScalarComparator, TextDiffComparator, RelationComparator,
   │     ├─ LocalizedFieldsComparator, FieldCollectionComparator,
   │     ├─ ObjectBrickComparator, ClassificationStoreComparator, AssetFieldComparator
   │     └─ FallbackComparator (getVersionPreview based)
   ├─ Normalizer           → trim / numeric epsilon / UTC dates / ""-vs-null
   └─ DiffResultAssembler  → normalized FieldDiff DTO tree → JSON
```

The engine **reuses Pimcore's own per-fieldtype diff API** (`getVersionPreview()`,
`getDiffDataForEditMode()`, `EqualComparisonInterface::isEqual()`) rather than reimplementing value
rendering, and it resolves per-user field visibility through Studio's existing layout-permission
services — so there is no new permission model and no persisted state.

### Extending — the Comparator SPI

Register a service implementing `FieldComparatorInterface` (extend `AbstractFieldComparator` for the
helpers) and tag it. A higher `priority` overrides a core comparator:

```php
use Pimcore\Bundle\ComparisonBundle\Comparator\AbstractFieldComparator;
use Pimcore\Bundle\ComparisonBundle\Comparator\ComparisonContext;
use Pimcore\Bundle\ComparisonBundle\Diff\FieldDiff;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pimcore.comparison.field_comparator', ['priority' => 50])]
final class MyFieldtypeComparator extends AbstractFieldComparator
{
    public function supports(Data $fieldDefinition): bool
    {
        return $fieldDefinition->getFieldtype() === 'myCustomType';
    }

    public function compare(mixed $left, mixed $right, Data $fd, ComparisonContext $ctx): FieldDiff
    {
        // ... return $this->diff($fd, $status, $leftDisplay, $rightDisplay);
    }
}
```

### Configuration

```yaml
# config/packages/pimcore_comparison.yaml
pimcore_comparison:
    normalization:
        trim: true
        numeric_epsilon: 0.0
        empty_string_equals_null: true
    default_filter: differences      # all | differences | equal
    export:
        formats: [xlsx, json]
    excluded_fields:
        Product: [internalNotes]
```

---

## Security & permissions

- One bundle permission, `plugin_comparison`, gates the feature per role (registered by the Installer).
- Element **view** permission is required on **both** compared objects.
- Field-level layout permissions are enforced server-side; hidden fields carry no value.
- No object data is persisted; export honours the exact same filtering and masking as the view.

---

## Testing

```bash
# PHPUnit (unit) — run from the skeleton root, foreground
docker compose exec php vendor/bin/phpunit -c bundles/PimcoreComparisonBundle/phpunit.xml.dist
```

Playwright end-to-end specs (Studio UI) are planned under `tests/e2e/`.

---

## `_state/` — machine-generated feature state

The bundle carries a **declaration-is-authored / state-is-computed** registry under [`_state/`](_state/):
`registry/` (features + coverage + spec-coverage), `parity/` (a capability/acceptance matrix), and
`contracts/` (the `@internal` vendor-symbol ledger). Requirement IDs are authored in
[`_specs/requirements.md`](_specs/requirements.md). Once the generators are ported, `_state/` is
regenerated from `#[AsFeature]` declarations ⋈ test evidence and guarded by `git diff --exit-code`;
until then it is a hand-authored seed (each file says so in its header).

## Documentation

- [`_specs/base-idea.md`](_specs/base-idea.md) — the technical concept
- [`_specs/requirements.md`](_specs/requirements.md) — requirement IDs
- [`_specs/design-concept/`](_specs/design-concept/) — the Studio view design (SDK-component mapping)
- [`_specs/plans/`](_specs/plans/) — the implementation plan

## License

Proprietary — © Pimcore.
