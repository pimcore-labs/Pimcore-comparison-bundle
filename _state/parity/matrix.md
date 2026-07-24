# Comparison parity matrix (SEED — hand-authored, not generated)

Greenfield bundle: there is **no legacy surface** to port, so this is a capability / acceptance matrix
seeded from `_specs/base-idea.md` §5–§6 and the design concept. **Status is `planned` for every row and
every test id is `unclaimed`** — nothing is built or tested yet. Once the `comparison:parity:state`
generator is ported (implementation plan Phase 7), status becomes *derived* from passing tests
(verified / partial / failing / unclaimed) and this file is regenerated.

| Area | Capability | Surface | Target (v1) | Test ids | Status |
| --- | --- | --- | --- | --- | --- |
| Entry | Grid multi-select → Compare objects | frontend | Studio grid context-menu action (2 same-class) | `E2E-CMP-001` (planned) | planned |
| Entry | "Compare with…" dialog (same-class search) | frontend | SDK Modal + object search | `E2E-CMP-002` (planned) | planned |
| Entry | Deep link /studio/comparison?left&right (+ copy) | frontend | SDK route + clipboard copy | `E2E-CMP-003` (planned), `E2E-CMP-023` (planned) | planned |
| Diff table | Filters: all / differences-only / equal-only + field filter | frontend | antd Segmented + Input | `E2E-CMP-004` (planned), `E2E-CMP-005` (planned) | planned |
| Diff table | Inline text diff (+ WYSIWYG sanitized) | frontend | Server-side token diff + sanitized-HTML diff | `E2E-CMP-006` (planned), `E2E-CMP-007` (planned) | planned |
| Diff table | Localized per-language sub-rows | frontend | Per-language sub-rows | `E2E-CMP-008` (planned) | planned |
| Diff table | Relations: added / removed / kept / reordered | frontend | antd Tag chips (4 semantic colours) | `E2E-CMP-009` (planned), `E2E-CMP-010` (planned) | planned |
| Diff table | Containers: field-collection / object-brick / classification-store | frontend | Collapsible layout-tree sections | `E2E-CMP-011` (planned), `E2E-CMP-012` (planned), `E2E-CMP-013` (planned) | planned |
| Diff table | Image / asset field (thumbnail + id/path) | frontend | Asset thumbnail + id/path check | `E2E-CMP-014` (planned) | planned |
| Diff table | Swap + expand/collapse + summary + empty state | frontend | Toolbar controls + summary bar | `E2E-CMP-017` (planned), `E2E-CMP-018` (planned), `E2E-CMP-021` (planned), `E2E-CMP-022` (planned) | planned |
| Export | XLSX / JSON of the filtered, permissioned view | backend | POST /comparison/objects/export | `E2E-CMP-019` (planned), `E2E-CMP-020` (planned), `T-SEC-006` (planned) | planned |
| Security | View permission required on both objects | backend | hasElementPermission on both | `T-SEC-001` (planned) | planned |
| Security | Field-level permission masking (hidden rows) | backend | plugin_comparison permission | `T-SEC-002` (planned), `E2E-CMP-015` (planned) | planned |
| Security | plugin_comparison gate | backend | Route absence + no persistence | `T-SEC-003` (planned) | planned |
| Security | Stateless + no write route (read-only v1) | backend | ComparisonService guard | `T-SEC-004` (planned), `T-SEC-005` (planned) | planned |
| Security | Same-class + Concrete guard | backend | not-comparable status + error cell | `T-SEC-007` (planned) | planned |
| Core | Not-comparable handling (calculated errors) | backend | status taxonomy | `E2E-CMP-016` (planned) | planned |
| Architecture | Import boundaries + @internal audit + frontend purity | backend | ArchitectureRules + InternalContractAudit | `T-ARCH-001` (planned), `T-ARCH-002` (planned), `T-ARCH-003` (planned) | planned |
