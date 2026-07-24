import React from 'react';
import { Flex, Space, Tag, Typography } from 'antd';
import { PieChartOutlined } from '@ant-design/icons';
import type { DiffSummary } from '../../../api/types';
import { STATUS_META } from './DiffTable';

const { Text } = Typography;

export interface SummaryBarProps {
  summary: DiffSummary;
}

/**
 * "N of M fields differ" + per-category counts. The counts come straight from the server summary
 * payload (nothing recomputed client-side). Colour is paired with the status label, never alone.
 */
export const SummaryBar: React.FC<SummaryBarProps> = ({ summary }) => {
  const { total, differing, counts } = summary;

  const orderedStatuses = Object.keys(STATUS_META).filter((s) => (counts[s] ?? 0) > 0);

  return (
    <Flex
      align="center"
      justify="space-between"
      wrap="wrap"
      gap={12}
      style={{
        padding: '8px 12px',
        borderTop: '1px solid var(--ant-color-border-secondary, #f0f0f0)',
      }}
    >
      <Space size={12} wrap align="center">
        <PieChartOutlined />
        <Text strong>
          {differing} of {total} field{total === 1 ? '' : 's'} differ
        </Text>
        <Space size={[6, 6]} wrap>
          {orderedStatuses.map((status) => {
            const meta = STATUS_META[status];
            return (
              <Tag key={status} color={meta.color} icon={meta.icon}>
                {counts[status]} {meta.label.toLowerCase()}
              </Tag>
            );
          })}
        </Space>
      </Space>
      <Text type="secondary" style={{ fontSize: 12 }}>
        Computed server-side · permissions enforced · nothing persisted
      </Text>
    </Flex>
  );
};
