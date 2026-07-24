# Development documentation

- [Comparator SPI — extend the diff engine](comparator-spi.md) — the primary extension point, with a
  complete custom-fieldtype example.
- [REST API reference](rest-api.md) — the three endpoints, payload shapes, and errors.

## Architecture at a glance

```
ComparisonController (REST, /pimcore-studio/api/comparison/*)
   │  plugin_comparison gate + element view-permission on both objects + ETag
   ▼
ComparisonService                       guards: same class, both Concrete (C-1/C-6)
   ├─ HiddenFieldResolver               per-user field masking → hidden (C-2)
   ├─ FieldWalker                       walks the class LAYOUT tree, preserving sections
   ├─ ComparatorRegistry                resolves a comparator per field (tagged, priority)
   │     └─ Scalar / TextDiff / Relation / Localized / FieldCollection /
   │        ObjectBrick / ClassificationStore / AssetField / Fallback
   ├─ Normalizer                        trim / numeric epsilon / UTC dates / "" vs null
   └─ DiffResult (FieldDiff tree + summary counts) → JSON
                                        └─ DiffFilter + DiffExporter (XLSX / JSON)
```

Design principles: **read-only** (C-3, no write route), **stateless** (C-4, nothing persisted, ETag on the
modificationDate pair), **thin client** (C-5, the diff is computed server-side; the Studio plugin only
renders), and **permission parity** (C-2, reuses Pimcore's own element and layout permissions — no new
model beyond the `plugin_comparison` gate).

## Source layout

| Path | What |
|---|---|
| `src/Comparison/` | `ComparisonService`, `FieldWalker`, `Normalizer`, `DiffResult` |
| `src/Comparator/` | the SPI (`FieldComparatorInterface`, `AbstractFieldComparator`), `ComparatorRegistry`, the 9 comparators |
| `src/Diff/` | `FieldDiff`, `DiffStatus` |
| `src/Controller/` | `ComparisonController` + the permission base |
| `src/Export/` | `DiffFilter`, `DiffExporter` |
| `src/Studio/` | `HiddenFieldResolver`, `WebpackEntryPointProvider` |
| `src/Command/` | `comparison:diff` |
| `src/Security/` | `ComparisonPermissions` (the `plugin_comparison` catalogue) |
| `assets/` | the Studio UI React plugin (Rsbuild + Module Federation) |
| `tests/phpunit/` | unit tests (per comparator + engine + export + registry) |

## Running the tests

```bash
# PHPUnit (unit) — from the skeleton root, foreground
docker compose exec php vendor/bin/phpunit -c bundles/PimcoreComparisonBundle/phpunit.xml.dist
```
