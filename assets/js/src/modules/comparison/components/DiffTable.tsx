import React from 'react';
import { Space, Table, Tag, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import {
  ArrowLeftOutlined,
  ArrowRightOutlined,
  CheckOutlined,
  EditOutlined,
  ExclamationCircleOutlined,
  FileImageOutlined,
  LockOutlined,
  SwapOutlined,
} from '@ant-design/icons';
import type { AssetEntry, DiffStatus, FieldDiff } from '../../../api/types';
import { InlineTextDiff } from './InlineTextDiff';
import { RelationChipDiff } from './RelationChipDiff';

const { Text } = Typography;

/** Status taxonomy (design §3): icon + label + antd semantic colour. Colour is never the only signal. */
export const STATUS_META: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  equal: { label: 'Equal', color: 'default', icon: <CheckOutlined /> },
  changed: { label: 'Changed', color: 'warning', icon: <EditOutlined /> },
  'only-left': { label: 'Only left', color: 'error', icon: <ArrowLeftOutlined /> },
  'only-right': { label: 'Only right', color: 'success', icon: <ArrowRightOutlined /> },
  reordered: { label: 'Reordered', color: 'processing', icon: <SwapOutlined /> },
  'not-comparable': { label: 'Not comparable', color: 'error', icon: <ExclamationCircleOutlined /> },
  hidden: { label: 'Hidden', color: 'default', icon: <LockOutlined /> },
};

/** A container fieldtype carries child rows and its own type badge. */
const CONTAINER_TYPE_LABEL: Record<string, string> = {
  localizedfields: 'Localized',
  objectbricks: 'Object bricks',
  objectbrick: 'Object brick',
  fieldcollections: 'Field collection',
  fieldcollectionItem: 'Item',
  classificationstore: 'Classification store',
};

export interface DiffRow extends FieldDiff {
  _key: string;
  children?: DiffRow[];
}

/** Assign a stable, unique key to every node (names can repeat across nesting levels). */
export function buildRows(fields: FieldDiff[], parent = ''): DiffRow[] {
  return fields.map((f, i) => {
    const key = `${parent}${i}:${f.name}`;
    const children =
      f.children && f.children.length > 0 ? buildRows(f.children, `${key}/`) : undefined;
    return { ...f, _key: key, children };
  });
}

/** Every row key that has children — used for expand-all. */
export function expandableKeys(rows: DiffRow[]): string[] {
  const out: string[] = [];
  const walk = (list: DiffRow[]): void => {
    for (const r of list) {
      if (r.children && r.children.length > 0) {
        out.push(r._key);
        walk(r.children);
      }
    }
  };
  walk(rows);
  return out;
}

const Muted: React.FC = () => <Text type="secondary">—</Text>;

const PlainValue: React.FC<{ value: unknown }> = ({ value }) => {
  if (value === null || value === undefined || value === '') {
    return <Muted />;
  }
  if (typeof value === 'object') {
    return (
      <Text code style={{ fontSize: 12 }}>
        {JSON.stringify(value)}
      </Text>
    );
  }
  return <span style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{String(value)}</span>;
};

const AssetCell: React.FC<{ entries: AssetEntry[] }> = ({ entries }) => {
  if (!entries || entries.length === 0) {
    return <Muted />;
  }
  return (
    <Space direction="vertical" size={2}>
      {entries.map((e, i) => (
        <Space key={i} size={6} align="center">
          <FileImageOutlined />
          <Text code style={{ fontSize: 12 }}>
            {e.path ?? '(no path)'}
          </Text>
          {e.id !== null && e.id !== undefined && (
            <Text type="secondary" style={{ fontSize: 11 }}>
              Asset {String(e.id)}
            </Text>
          )}
        </Space>
      ))}
    </Space>
  );
};

/** Render one side's cell for a field row, choosing the renderer by kind. */
function renderSide(record: DiffRow, side: 'left' | 'right'): React.ReactNode {
  if (record.status === 'hidden') {
    return (
      <Space size={6}>
        <LockOutlined />
        <Text type="secondary">Value withheld</Text>
      </Space>
    );
  }
  if (record.status === 'not-comparable') {
    return (
      <Space size={6}>
        <ExclamationCircleOutlined style={{ color: 'var(--ant-color-error, #ff4d4f)' }} />
        <Text type="danger">Not comparable</Text>
      </Space>
    );
  }
  // Container rows own no scalar value.
  if (record.children && record.children.length > 0) {
    return null;
  }
  if (record.inlineDiff && record.inlineDiff.length > 0) {
    return <InlineTextDiff tokens={record.inlineDiff} side={side} />;
  }
  if (record.meta?.chips && record.meta.chips.length > 0) {
    return <RelationChipDiff chips={record.meta.chips} side={side} />;
  }
  const assetEntries = side === 'left' ? record.meta?.left : record.meta?.right;
  if (Array.isArray(record.meta?.left) && Array.isArray(record.meta?.right)) {
    return <AssetCell entries={(assetEntries as AssetEntry[]) ?? []} />;
  }
  return <PlainValue value={side === 'left' ? record.leftDisplay : record.rightDisplay} />;
}

const StatusTag: React.FC<{ status: DiffStatus }> = ({ status }) => {
  const meta = STATUS_META[status] ?? STATUS_META.equal;
  return (
    <Tag color={meta.color} icon={meta.icon}>
      {meta.label}
    </Tag>
  );
};

const FieldLabel: React.FC<{ record: DiffRow }> = ({ record }) => {
  const isContainer = !!record.children && record.children.length > 0;
  const typeLabel = CONTAINER_TYPE_LABEL[record.fieldtype];
  const language = record.meta?.language;
  const note = record.meta?.note;
  return (
    <Space size={6} wrap align="center">
      <Text strong={isContainer}>{record.label || record.name}</Text>
      {language && (
        <Tag style={{ fontFamily: 'monospace', textTransform: 'uppercase' }}>{String(language)}</Tag>
      )}
      {isContainer && typeLabel && <Tag color="blue">{typeLabel}</Tag>}
      {isContainer && record.children && (
        <Text type="secondary" style={{ fontSize: 12 }}>
          · {record.children.length}
        </Text>
      )}
      {note && (
        <Text type="secondary" style={{ fontSize: 11 }}>
          {String(note)}
        </Text>
      )}
    </Space>
  );
};

export interface DiffTableProps {
  rows: DiffRow[];
  leftKey: string;
  rightKey: string;
  expandedRowKeys: string[];
  onExpandedRowsChange: (keys: string[]) => void;
  density?: 'comfortable' | 'compact';
}

export const DiffTable: React.FC<DiffTableProps> = ({
  rows,
  leftKey,
  rightKey,
  expandedRowKeys,
  onExpandedRowsChange,
  density = 'comfortable',
}) => {
  const columns: ColumnsType<DiffRow> = [
    {
      title: 'Field',
      dataIndex: 'label',
      key: 'field',
      width: '28%',
      render: (_: unknown, record: DiffRow) => <FieldLabel record={record} />,
    },
    {
      title: <Text type="secondary">Left · {leftKey}</Text>,
      key: 'left',
      width: '30%',
      render: (_: unknown, record: DiffRow) => renderSide(record, 'left'),
    },
    {
      title: <Text type="secondary">Right · {rightKey}</Text>,
      key: 'right',
      width: '30%',
      render: (_: unknown, record: DiffRow) => renderSide(record, 'right'),
    },
    {
      title: 'Status',
      key: 'status',
      width: 150,
      render: (_: unknown, record: DiffRow) => <StatusTag status={record.status} />,
    },
  ];

  return (
    <Table<DiffRow>
      rowKey="_key"
      columns={columns}
      dataSource={rows}
      pagination={false}
      size={density === 'compact' ? 'small' : 'middle'}
      sticky
      scroll={{ x: 'max-content' }}
      expandable={{
        childrenColumnName: 'children',
        expandedRowKeys,
        onExpandedRowsChange: (keys) => onExpandedRowsChange(keys as string[]),
      }}
    />
  );
};
