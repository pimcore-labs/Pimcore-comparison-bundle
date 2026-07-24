import type { ComponentType } from 'react';

export interface IAbstractPlugin {
  name: string;
  priority?: number;
  onInit?: (config: { container: unknown }) => void;
  onStartup?: (config: { moduleSystem: unknown }) => void;
}

export interface WidgetDef {
  /** snake_case registry name; must equal the nav widgetConfig.component and the openMainWidget component */
  name: string;
  component: ComponentType<any>;
  label: string;
}

export interface NavItem {
  path: string;
  label: string;
  order: number;
  component: string;
  name: string;
  id: string;
  icon: string;
}
