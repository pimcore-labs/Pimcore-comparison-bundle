import 'reflect-metadata';

import { registerContextMenu } from './plugin/registerContextMenu';
import { registerMainNavigation } from './plugin/registerNavigation';
import { registerWidgets } from './plugin/registerWidgets';
import type { IAbstractPlugin } from './plugin/types';

export const PimcoreComparisonPlugin: IAbstractPlugin = {
  name: 'pimcore-comparison',
  priority: 100,

  onInit() {
    console.log('[Comparison] Plugin initialized');
    registerWidgets();
    registerMainNavigation();
    registerContextMenu();
  },

  onStartup() {
    console.log('[Comparison] Plugin starting up');
  },
};

export default PimcoreComparisonPlugin;

// Re-export the primary component for potential host consumption.
export { ComparisonView } from './modules/comparison/components/ComparisonView';
