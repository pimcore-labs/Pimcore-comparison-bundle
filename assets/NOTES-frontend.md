# Comparison Studio UI plugin — build notes & gaps

Scaffolded by mirroring the working `PimcoreDataSpineExplorerBundle/assets` plugin. Build tool is
rsbuild + module federation; the plugin is a module-federation remote (`pimcore_comparison`) exposed
to the host Studio shell via `src/Studio/WebpackEntryPointProvider.php`
(tag `pimcore_studio_ui.webpack_entry_point_provider`).

## Verified
- `npm run typecheck` — clean (TypeScript strict).
- `npm run build` — produces `public/build/<uuid>/entrypoints.json` + `exposeRemote.js` + `static/js/remoteEntry.js`.
- Served through nginx (after `bin/console assets:install`): exposeRemote.js / entrypoints.json /
  remoteEntry.js / main.js all return **200**.
- `bin/console cache:clear` — container compiles with the new tagged provider (DI intact).
- **Loads in the running Studio** — browser console shows, with no errors from our code:
  `[Comparison] Plugin initialized` → `1 widget(s) registered` → `Navigation registered` →
  `Context-menu action "Compare objects" registered on data-object.list-grid` → `Plugin starting up`.

## SDK APIs used (read from `@pimcore/studio-ui-bundle` 2026.1.4 type declarations)
- `container` + `serviceIds` from `.../app`; `serviceIds.widgetManager`, `serviceIds.mainNavRegistry`,
  and the string id `App/ContextMenuRegistry/ContextMenuRegistry`.
- `WidgetRegistry.registerWidget({ name, component, transformConfig })` (`.../modules/widget-manager`).
- `useWidgetManager().openMainWidget(tabConfig)` to open the panel from the context menu; the
  `openMainWidget` redux action (dispatched via the exported `store`) for the deep-link path.
- `ContextMenuRegistryInterface.registerToSlot('data-object.list-grid', { name, priority, useMenuItem })`
  (`.../modules/app`).
- `useRowSelectionOptional()` (`.../modules/element`) to read the live grid selection
  (`selectedRowsData` is keyed by element id; values carry `className`).
- `MainNavRegistry.registerMainNavItem(...)` (`.../modules/app`).

The component internals use the shared **antd 5.22** singleton + `@ant-design/icons` directly (Table,
Segmented, Input, Dropdown, Tag, Card, Flex, Empty, Spin, Alert, message, theme tokens) — per the
design-concept's SDK/antd mapping. No `--pc-*` variables, no Lucide, no Inter/JetBrains fonts.

## Context-menu "Compare objects" guard (design §5)
The `data-object.list-grid` slot passes only the single right-clicked `target` (SDK type
`DataObjectListGridContextMenuProps`), not the selection. The item therefore reads the live grid
selection via `useRowSelectionOptional()` and is shown **only when exactly two objects are selected
and they share a class** (className comparison across `selectedRowsData`). When class info is absent
from the selection payload the pair is still allowed and the server validates same-class. If the
selection context is unavailable the item hides (returns null) — graceful, matches the guard.

## Known gaps / decisions
1. **Deep-link route** — the Studio router is a fixed `@remix-run/router` instance with no public API
   to register a custom `/studio/comparison` route. Implemented as: (a) a copy-able deep-link **pill**
   in the panel header (fully working — copies `/studio/comparison?left=&right=` to the clipboard),
   and (b) a best-effort URL scan at startup (`registerNavigation.handleDeepLink`) that opens the
   widget via a `store.dispatch(openMainWidget(...))` when the current URL carries `?left=&right=` on a
   `/comparison` path/hash. A first-class route handler is not exposed by the SDK.
2. **Object header cards** — the `/objects` API returns `leftId/rightId/className/fields/summary` but
   no per-object key / path / published-state / modified-date / author. The two header cards therefore
   show id + class + an "Open in editor" affordance (LEFT · swap · RIGHT), not the full key/path/badge
   from the visual mock. Enriching them would need a second call to the Studio data-object API.
3. **Image cells** — asset diffs render icon + mono path + `Asset {id}` (from `meta.left`/`meta.right`).
   No live thumbnail: the comparison API returns id+path only, and no thumbnail-stream URL was assumed.
4. **`@ant-design/icons` singleton warning** — the host provides icons v6.1.0 while our manifest
   requests `^5.5.2`, so MF logs one benign version-mismatch warning; icons resolve to the host
   singleton and render fine (same shared config as DataSpine, which does not pin a requiredVersion).
5. **Filtering is server-side** — the toolbar Segmented (all/differences/equal), the field free-text
   filter, and the language chips all re-fetch `/objects` with `filter` / `query` / `locales` params
   (the server's `DiffFilter` is the single source of truth, so export matches the on-screen view).
