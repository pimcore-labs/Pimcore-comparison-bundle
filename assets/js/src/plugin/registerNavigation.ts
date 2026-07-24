import { container, serviceIds, store } from '@pimcore/studio-ui-bundle/app';
import type { MainNavRegistry } from '@pimcore/studio-ui-bundle/modules/app';
import { openMainWidget } from '@pimcore/studio-ui-bundle/modules/widget-manager';
import { COMPARISON_WIDGET, NAV } from './definitions';
import type { NavItem } from './types';

function navWidgetConfig(item: NavItem) {
  return {
    name: item.name,
    id: item.id,
    component: item.component,
    config: {
      translationKey: item.label,
      icon: { type: 'name' as const, value: item.icon },
    },
  };
}

/**
 * Build the tab config that opens the comparison panel for a pair. `leftId`/`rightId` land in the
 * widget's `config` prop, which ComparisonView reads on mount.
 */
export function comparisonTabConfig(leftId: number, rightId: number) {
  return {
    name: 'Compare objects',
    id: `${COMPARISON_WIDGET}-${leftId}-${rightId}`,
    component: COMPARISON_WIDGET,
    config: {
      translationKey: 'Compare objects',
      leftId,
      rightId,
    },
  };
}

/**
 * Best-effort deep-link handler. The Studio router (a fixed @remix-run/router instance) exposes no
 * public API to register a custom `/studio/comparison` route, so instead we scan the current URL at
 * startup for `?left=&right=` (or a `#/studio/comparison?...` hash) and open the widget via a store
 * dispatch. See NOTES-frontend.md.
 */
function handleDeepLink(): void {
  try {
    const search = new URLSearchParams(window.location.search);
    let left = search.get('left');
    let right = search.get('right');

    if ((!left || !right) && window.location.hash.includes('comparison')) {
      const hashQuery = window.location.hash.split('?')[1] ?? '';
      const hp = new URLSearchParams(hashQuery);
      left = left ?? hp.get('left');
      right = right ?? hp.get('right');
    }

    const leftId = Number(left);
    const rightId = Number(right);
    const onComparisonPath =
      window.location.pathname.includes('/comparison') || window.location.hash.includes('comparison');

    if (onComparisonPath && Number.isFinite(leftId) && leftId > 0 && Number.isFinite(rightId) && rightId > 0) {
      store.dispatch(openMainWidget(comparisonTabConfig(leftId, rightId)));
      console.log('[Comparison] Opened comparison from deep link', { leftId, rightId });
    }
  } catch (e) {
    console.warn('[Comparison] deep-link handling skipped', e);
  }
}

export function registerMainNavigation(): void {
  console.log('[Comparison] Registering navigation...');
  try {
    const mainNavRegistry = container.get<MainNavRegistry>(serviceIds.mainNavRegistry);
    NAV.forEach((item) => {
      mainNavRegistry.registerMainNavItem({
        path: item.path,
        label: item.label,
        order: item.order,
        groupIcon: 'diff',
        widgetConfig: navWidgetConfig(item),
      });
    });
    console.log('[Comparison] Navigation registered');
  } catch (e) {
    console.warn('[Comparison] Navigation registration failed', e);
  }

  handleDeepLink();
}
