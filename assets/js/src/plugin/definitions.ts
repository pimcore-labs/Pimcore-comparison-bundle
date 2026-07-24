import { ComparisonView } from '../modules/comparison/components/ComparisonView';
import type { NavItem, WidgetDef } from './types';

/** The registry name of the comparison widget. Must match the nav widgetConfig.component and
 * every openMainWidget({ component }) call that opens the panel. */
export const COMPARISON_WIDGET = 'comparison-view';

export const WIDGETS: WidgetDef[] = [
  { name: COMPARISON_WIDGET, component: ComparisonView, label: 'Compare objects' },
];

/**
 * A single left-nav affordance. Opening it with no leftId/rightId renders the panel's empty
 * state (which explains the "select two objects → Compare" flow). The primary entry point is the
 * grid context-menu action registered in registerContextMenu.ts.
 */
export const NAV: NavItem[] = [
  {
    path: 'Comparison/Compare',
    label: 'Compare objects',
    order: 200,
    component: COMPARISON_WIDGET,
    name: 'ComparisonCompare',
    id: 'comparison-compare',
    icon: 'diff',
  },
];
