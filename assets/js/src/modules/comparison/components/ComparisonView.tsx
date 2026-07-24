import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Dropdown,
  Empty,
  Flex,
  Input,
  Segmented,
  Space,
  Spin,
  Tag,
  Tooltip,
  Typography,
  message,
} from 'antd';
import type { MenuProps } from 'antd';
import {
  CopyOutlined,
  DiffOutlined,
  DownOutlined,
  ExpandAltOutlined,
  ExportOutlined,
  ShrinkOutlined,
  SwapOutlined,
} from '@ant-design/icons';
import { comparisonApi } from '../../../api/client';
import type {
  ComparisonResult,
  ExportFormat,
  FieldDiff,
  FilterMode,
} from '../../../api/types';
import { DiffTable, buildRows, expandableKeys } from './DiffTable';
import { SummaryBar } from './SummaryBar';

const { Title, Text } = Typography;

interface ComparisonViewConfig {
  leftId?: number | string;
  rightId?: number | string;
}

export interface ComparisonViewProps {
  config?: ComparisonViewConfig;
}

const FILTER_OPTIONS: { label: string; value: FilterMode }[] = [
  { label: 'All fields', value: 'all' },
  { label: 'Differences only', value: 'differences' },
  { label: 'Equal only', value: 'equal' },
];

function toId(value: number | string | undefined): number {
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : 0;
}

/** Collect every distinct locale used by localized sub-rows, for the language toggle chips. */
function collectLocales(fields: FieldDiff[]): string[] {
  const set = new Set<string>();
  const walk = (list: FieldDiff[]): void => {
    for (const f of list) {
      const loc = f.meta?.language;
      if (typeof loc === 'string' && loc !== '') set.add(loc);
      if (f.children && f.children.length > 0) walk(f.children);
    }
  };
  walk(fields);
  return Array.from(set).sort();
}

/** Best-effort open of a data object in the Studio editor (host wiring degrades to a window event). */
function openDataObject(id: number): void {
  try {
    const w = window as unknown as {
      PimcoreStudio?: { element?: { openDataObject?: (id: number) => void; openElement?: (id: number, type: string) => void } };
      parent?: Window & { PimcoreStudio?: { element?: { openDataObject?: (id: number) => void; openElement?: (id: number, type: string) => void } } };
    };
    const api = w.parent?.PimcoreStudio ?? w.PimcoreStudio;
    if (api?.element?.openDataObject) {
      api.element.openDataObject(id);
      return;
    }
    if (api?.element?.openElement) {
      api.element.openElement(id, 'data-object');
      return;
    }
  } catch {
    /* ignore */
  }
  window.dispatchEvent(new CustomEvent('pimcore:open-element', { detail: { type: 'data-object', id } }));
}

const ObjectCard: React.FC<{ id: number; className: string; corner: 'LEFT' | 'RIGHT' }> = ({
  id,
  className,
  corner,
}) => (
  <Card size="small" style={{ flex: 1, minWidth: 200, position: 'relative' }}>
    <div style={{ position: 'absolute', top: 8, right: 12 }}>
      <Text type="secondary" style={{ fontSize: 11, letterSpacing: 1 }}>
        {corner}
      </Text>
    </div>
    <Flex vertical gap={4}>
      <Space size={8} align="center">
        <DiffOutlined />
        <Text strong>ID {id}</Text>
      </Space>
      <Space size={6} wrap>
        <Tag>{className}</Tag>
        <Button type="link" size="small" style={{ padding: 0 }} onClick={() => openDataObject(id)}>
          Open in editor
        </Button>
      </Space>
    </Flex>
  </Card>
);

const ComparisonPanel: React.FC<ComparisonViewProps> = ({ config }) => {
  const [leftId, setLeftId] = useState<number>(toId(config?.leftId));
  const [rightId, setRightId] = useState<number>(toId(config?.rightId));
  const [filter, setFilter] = useState<FilterMode>('differences');
  const [query, setQuery] = useState<string>('');
  const [debouncedQuery, setDebouncedQuery] = useState<string>('');
  const [localeSet, setLocaleSet] = useState<Set<string> | null>(null);
  const [expandedKeys, setExpandedKeys] = useState<string[]>([]);
  const [data, setData] = useState<ComparisonResult | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const requestSeq = useRef(0);

  const availableLocales = useMemo(() => (data ? collectLocales(data.fields) : []), [data]);

  // Debounce the free-text field filter.
  useEffect(() => {
    const t = window.setTimeout(() => setDebouncedQuery(query), 300);
    return () => window.clearTimeout(t);
  }, [query]);

  const localesParam = useMemo<string[] | undefined>(() => {
    if (!localeSet || availableLocales.length === 0) return undefined;
    if (localeSet.size >= availableLocales.length) return undefined;
    if (localeSet.size === 0) return undefined;
    return Array.from(localeSet);
  }, [localeSet, availableLocales]);

  const hasPair = leftId > 0 && rightId > 0;

  const fetchDiff = useCallback(() => {
    if (!hasPair) return;
    const seq = ++requestSeq.current;
    setLoading(true);
    setError(null);
    comparisonApi
      .objects({ leftId, rightId, filter, query: debouncedQuery, locales: localesParam })
      .then((result) => {
        if (seq !== requestSeq.current) return;
        setData(result);
        setExpandedKeys(expandableKeys(buildRows(result.fields)));
      })
      .catch((e: unknown) => {
        if (seq !== requestSeq.current) return;
        setError(e instanceof Error ? e.message : 'Failed to load comparison.');
        setData(null);
      })
      .finally(() => {
        if (seq === requestSeq.current) setLoading(false);
      });
  }, [hasPair, leftId, rightId, filter, debouncedQuery, localesParam]);

  useEffect(() => {
    fetchDiff();
  }, [fetchDiff]);

  const rows = useMemo(() => (data ? buildRows(data.fields) : []), [data]);
  const allExpandable = useMemo(() => expandableKeys(rows), [rows]);

  const swap = useCallback(() => {
    setLeftId(rightId);
    setRightId(leftId);
  }, [leftId, rightId]);

  const deepLink = `/studio/comparison?left=${leftId}&right=${rightId}`;

  const copyDeepLink = useCallback(() => {
    const done = (): void => void message.success('Deep link copied');
    try {
      if (navigator.clipboard?.writeText) {
        void navigator.clipboard.writeText(deepLink).then(done).catch(() => message.error('Copy failed'));
      } else {
        message.warning('Clipboard unavailable — copy manually');
      }
    } catch {
      message.error('Copy failed');
    }
  }, [deepLink]);

  const toggleLocale = useCallback(
    (locale: string, checked: boolean) => {
      setLocaleSet((prev) => {
        const base = prev ?? new Set(availableLocales);
        const next = new Set(base);
        if (checked) next.add(locale);
        else next.delete(locale);
        return next;
      });
    },
    [availableLocales],
  );

  const exportItems: MenuProps['items'] = [
    { key: 'xlsx', label: 'Export as XLSX' },
    { key: 'json', label: 'Export as JSON' },
  ];

  const doExport = useCallback(
    (format: ExportFormat) => {
      if (!hasPair) return;
      message.loading({ content: 'Preparing export…', key: 'cmp-export' });
      comparisonApi
        .exportDownload({ leftId, rightId, filter, query: debouncedQuery, locales: localesParam }, format)
        .then(() => message.success({ content: 'Export ready', key: 'cmp-export' }))
        .catch((e: unknown) =>
          message.error({ content: e instanceof Error ? e.message : 'Export failed', key: 'cmp-export' }),
        );
    },
    [hasPair, leftId, rightId, filter, debouncedQuery, localesParam],
  );

  const localeChecked = useCallback(
    (locale: string): boolean => (localeSet ? localeSet.has(locale) : true),
    [localeSet],
  );

  if (!hasPair) {
    return (
      <Flex align="center" justify="center" style={{ height: '100%', padding: 48 }}>
        <Empty
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          description={
            <Space direction="vertical" size={4}>
              <Text strong>Select two objects to compare</Text>
              <Text type="secondary">
                In a Data Objects listing, select exactly two objects of the same class, right-click, and
                choose “Compare objects”.
              </Text>
            </Space>
          }
        />
      </Flex>
    );
  }

  const className = data?.className ?? '';

  return (
    <Flex vertical style={{ height: '100%', overflow: 'auto' }}>
      <Flex vertical gap={12} style={{ padding: 16, flex: 1 }}>
        {/* Header */}
        <Flex align="center" justify="space-between" wrap="wrap" gap={12}>
          <Space size={10} align="center">
            <DiffOutlined style={{ fontSize: 18 }} />
            <Title level={4} style={{ margin: 0 }}>
              Compare objects
            </Title>
            <Tag
              style={{ fontFamily: 'monospace', margin: 0 }}
              icon={
                <Tooltip title="Copy deep link">
                  <CopyOutlined onClick={copyDeepLink} style={{ cursor: 'pointer' }} />
                </Tooltip>
              }
            >
              {deepLink}
            </Tag>
          </Space>
          <Text type="secondary">
            {className ? `Class: ${className} · ` : ''}read-only (v1)
          </Text>
        </Flex>

        {/* Object header cards: LEFT · swap · RIGHT */}
        <Flex align="center" gap={12} wrap="wrap">
          <ObjectCard id={leftId} className={className} corner="LEFT" />
          <Tooltip title="Swap sides">
            <Button shape="circle" icon={<SwapOutlined />} onClick={swap} />
          </Tooltip>
          <ObjectCard id={rightId} className={className} corner="RIGHT" />
        </Flex>

        {/* Toolbar */}
        <Flex align="center" justify="space-between" wrap="wrap" gap={12}>
          <Space size={10} wrap align="center">
            <Segmented
              value={filter}
              onChange={(v) => setFilter(v as FilterMode)}
              options={FILTER_OPTIONS}
            />
            <Input
              allowClear
              placeholder="Filter fields…"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              style={{ width: 200 }}
            />
            {availableLocales.length > 0 && (
              <Space size={4} align="center">
                <Text type="secondary" style={{ fontSize: 12 }}>
                  Languages
                </Text>
                {availableLocales.map((loc) => (
                  <Tag.CheckableTag
                    key={loc}
                    checked={localeChecked(loc)}
                    onChange={(checked) => toggleLocale(loc, checked)}
                    style={{ fontFamily: 'monospace', textTransform: 'uppercase' }}
                  >
                    {loc}
                  </Tag.CheckableTag>
                ))}
              </Space>
            )}
          </Space>
          <Space size={8} align="center">
            <Tooltip title="Expand all">
              <Button icon={<ExpandAltOutlined />} onClick={() => setExpandedKeys(allExpandable)} />
            </Tooltip>
            <Tooltip title="Collapse all">
              <Button icon={<ShrinkOutlined />} onClick={() => setExpandedKeys([])} />
            </Tooltip>
            <Dropdown
              menu={{
                items: exportItems,
                onClick: ({ key }) => doExport(key as ExportFormat),
              }}
            >
              <Button icon={<ExportOutlined />}>
                <Space size={4}>
                  Export
                  <DownOutlined />
                </Space>
              </Button>
            </Dropdown>
          </Space>
        </Flex>

        {/* Body */}
        {error && (
          <Alert
            type="error"
            showIcon
            message="Could not load comparison"
            description={error}
            action={
              <Button size="small" onClick={fetchDiff}>
                Retry
              </Button>
            }
          />
        )}

        {loading && !data && (
          <Flex align="center" justify="center" style={{ padding: 48 }}>
            <Spin />
          </Flex>
        )}

        {!error && data && (
          <Spin spinning={loading}>
            {rows.length === 0 ? (
              <Empty
                image={Empty.PRESENTED_IMAGE_SIMPLE}
                description={
                  filter === 'differences'
                    ? 'No differences for the current filter — the two objects match.'
                    : 'No fields match the current filter.'
                }
              />
            ) : (
              <DiffTable
                rows={rows}
                leftKey={String(leftId)}
                rightKey={String(rightId)}
                expandedRowKeys={expandedKeys}
                onExpandedRowsChange={setExpandedKeys}
              />
            )}
          </Spin>
        )}
      </Flex>

      {/* Summary bar */}
      {data && <SummaryBar summary={data.summary} />}
    </Flex>
  );
};

export const ComparisonView: React.FC<ComparisonViewProps> = (props) => <ComparisonPanel {...props} />;

export default ComparisonView;
