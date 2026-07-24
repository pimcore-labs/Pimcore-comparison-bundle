import { container, serviceIds } from '@pimcore/studio-ui-bundle/app';
import type { WidgetRegistry } from '@pimcore/studio-ui-bundle/modules/widget-manager';
import { WIDGETS } from './definitions';

export function registerWidgets(): void {
  console.log('[Comparison] Registering widgets...');
  const widgetRegistry = container.get<WidgetRegistry>(serviceIds.widgetManager);

  WIDGETS.forEach((w) => {
    widgetRegistry.registerWidget({
      name: w.name,
      component: w.component,
      transformConfig: (config: Record<string, unknown> | null | undefined) => ({
        ...config,
        translationKey:
          (config?.label as string | undefined) ??
          (config?.translationKey as string | undefined) ??
          w.label,
      }),
    });
  });

  console.log(`[Comparison] ${WIDGETS.length} widget(s) registered`);
}
