import React from 'react';
import { Space, Tag } from 'antd';
import type { RelationChip } from '../../../api/types';

export interface RelationChipDiffProps {
  chips: RelationChip[];
  /**
   * Which side this cell represents. LEFT shows the elements present on the left (kept, removed,
   * moved); RIGHT shows those present on the right (kept, added, moved). 'both' shows everything.
   */
  side?: 'left' | 'right' | 'both';
}

const STATE_COLOR: Record<RelationChip['state'], string> = {
  kept: 'default',
  added: 'success',
  removed: 'error',
  moved: 'processing',
  reordered: 'processing',
};

const STATE_MARK: Record<RelationChip['state'], string> = {
  kept: '',
  added: '＋ ',
  removed: '− ',
  moved: '↕ ',
  reordered: '↕ ',
};

function visibleForSide(chip: RelationChip, side: 'left' | 'right' | 'both'): boolean {
  if (side === 'both') return true;
  if (chip.state === 'added') return side === 'right';
  if (chip.state === 'removed') return side === 'left';
  return true; // kept / moved / reordered are present on both sides
}

/** Renders relation elements as antd Tags coloured by state (kept / added / removed / moved). */
export const RelationChipDiff: React.FC<RelationChipDiffProps> = ({ chips, side = 'both' }) => {
  const visible = chips.filter((c) => visibleForSide(c, side));

  if (visible.length === 0) {
    return <span style={{ color: 'var(--ant-color-text-tertiary, #bfbfbf)' }}>—</span>;
  }

  return (
    <Space size={[4, 4]} wrap>
      {visible.map((chip, i) => (
        <Tag
          key={`${chip.label}-${i}`}
          color={STATE_COLOR[chip.state]}
          style={chip.state === 'removed' ? { textDecoration: 'line-through' } : undefined}
        >
          {STATE_MARK[chip.state]}
          {chip.label}
        </Tag>
      ))}
    </Space>
  );
};
