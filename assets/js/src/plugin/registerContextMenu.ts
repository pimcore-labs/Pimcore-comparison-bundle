import React from 'react';
import { container, serviceIds } from '@pimcore/studio-ui-bundle/app';
import type { ContextMenuRegistryInterface } from '@pimcore/studio-ui-bundle/modules/app';
import { useWidgetManager } from '@pimcore/studio-ui-bundle/modules/widget-manager';
import { useRowSelectionOptional } from '@pimcore/studio-ui-bundle/modules/element';
import { DiffOutlined } from '@ant-design/icons';
import { comparisonTabConfig } from './registerNavigation';

type MenuItem = { key: string; label: string; icon?: React.ReactNode; onClick?: () => void } | null;

interface GridContext {
  target?: { id?: unknown; className?: unknown } | null;
}

function getContextMenuRegistry(): ContextMenuRegistryInterface | null {
  try {
    const ids = serviceIds as unknown as Record<string, string>;
    const serviceId = ids['App/ContextMenuRegistry/ContextMenuRegistry'];
    if (!serviceId) {
      return null;
    }
    const registry = container.get<ContextMenuRegistryInterface>(serviceId);
    return typeof registry?.registerToSlot === 'function' ? registry : null;
  } catch {
    return null;
  }
}

/**
 * Read the current data-object grid selection (best-effort). The grid stores selected rows in the
 * RowSelectionProvider context; `selectedRowsData` is keyed by element id and its values carry the
 * row payload (incl. `className`). Returns the selected ids + the set of distinct classNames.
 */
function readSelection(selection: { selectedRowsData?: Record<number, unknown> } | undefined): {
  ids: number[];
  classes: Set<string>;
} {
  const data = selection?.selectedRowsData ?? {};
  const ids: number[] = [];
  const classes = new Set<string>();
  Object.entries(data).forEach(([key, value]) => {
    const id = Number(key);
    if (Number.isFinite(id) && id > 0) {
      ids.push(id);
    }
    const cls = (value as { className?: unknown } | null)?.className;
    if (typeof cls === 'string' && cls !== '') {
      classes.add(cls);
    }
  });
  return { ids: ids.sort((a, b) => a - b), classes };
}

export function registerContextMenu(): void {
  const ctxRegistry = getContextMenuRegistry();
  if (!ctxRegistry) {
    console.warn('[Comparison] ContextMenuRegistry unavailable; skipping "Compare objects" action');
    return;
  }

  try {
    ctxRegistry.registerToSlot('data-object.list-grid', {
      name: 'comparisonCompareObjects',
      priority: 940,
      // `useMenuItem` is invoked as a hook inside the grid's context-menu React tree, so hooks
      // (selection context + widget manager) are valid here.
      useMenuItem: (context: GridContext): MenuItem => {
        let selection: { selectedRowsData?: Record<number, unknown> } | undefined;
        let openMainWidget: ((cfg: ReturnType<typeof comparisonTabConfig>) => void) | null = null;
        try {
          selection = useRowSelectionOptional() as { selectedRowsData?: Record<number, unknown> } | undefined;
        } catch {
          selection = undefined;
        }
        try {
          openMainWidget = useWidgetManager().openMainWidget;
        } catch {
          openMainWidget = null;
        }

        const { ids, classes } = readSelection(selection);
        // Guard (design §5): exactly two objects of the SAME class. When class info is present it
        // must be a single class; when absent we still allow the pair and the server validates it.
        const sameClass = classes.size <= 1;
        if (ids.length !== 2 || !sameClass) {
          return null;
        }

        const [leftId, rightId] = ids;
        return {
          key: 'comparison-compare-objects',
          label: 'Compare objects',
          icon: React.createElement(DiffOutlined),
          onClick: () => {
            try {
              if (openMainWidget) {
                openMainWidget(comparisonTabConfig(leftId, rightId));
              } else {
                console.warn('[Comparison] widget manager unavailable; cannot open comparison');
              }
            } catch (e) {
              console.warn('[Comparison] failed to open comparison', e);
            }
          },
        };
      },
    });

    console.log('[Comparison] Context-menu action "Compare objects" registered on data-object.list-grid');
  } catch (slotErr) {
    console.warn('[Comparison] could not register context menu on data-object.list-grid', slotErr);
  }
}
