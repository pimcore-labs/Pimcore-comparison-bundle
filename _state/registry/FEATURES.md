# Comparison features

<!-- SEED — hand-authored from _specs/base-idea.md + _specs/requirements.md. Not generated.
Will be REPLACED by `comparison:features:state` once the registry generator is ported (Phase 7).
Every feature is `planned` with no test evidence yet. -->

| Feature | Group | Status | PHPUnit | Playwright | Gaps |
|---|---|---|---|---|---|
| `api.rest` | api | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.asset-field` | comparators | planned | 0✓/0✗ | 0✓/0✗ | Binary hash comparison deferred to P2; v1 compares asset id/path |
| `comparators.classification-store` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.field-collection` | comparators | planned | 0✓/0✗ | 0✓/0✗ | Index-based item matching only in v1; stable-key matching deferred |
| `comparators.fallback` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.localized` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.object-brick` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.relation` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.scalar` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `comparators.text-diff` | comparators | planned | 0✓/0✗ | 0✓/0✗ | — |
| `core.comparator-registry` | core | planned | 0✓/0✗ | 0✓/0✗ | — |
| `core.comparison-service` | core | planned | 0✓/0✗ | 0✓/0✗ | — |
| `core.diff-result` | core | planned | 0✓/0✗ | 0✓/0✗ | — |
| `core.field-walker` | core | planned | 0✓/0✗ | 0✓/0✗ | — |
| `core.normalization` | core | planned | 0✓/0✗ | 0✓/0✗ | — |
| `export.xlsx-json` | export | planned | 0✓/0✗ | 0✓/0✗ | — |
| `extensibility.spi-events` | extensibility | planned | 0✓/0✗ | 0✓/0✗ | — |
| `platform.feature-registry` | platform | planned | 0✓/0✗ | 0✓/0✗ | Ported in Phase 7; until then _state is a hand-authored seed |
| `security.permissions` | security | planned | 0✓/0✗ | 0✓/0✗ | — |
| `ui.comparison-view` | ui | planned | 0✓/0✗ | 0✓/0✗ | — |
| `ui.diff-table` | ui | planned | 0✓/0✗ | 0✓/0✗ | — |

**21 features declared · all `planned` · 0 tests.** Spec coverage: 52 of 77 requirement ids claimed,
25 orphaned (23 `E2E-CMP-*` acceptance ids + `T-ARCH-001`, `T-ARCH-003`). See `spec-coverage.yaml`.
