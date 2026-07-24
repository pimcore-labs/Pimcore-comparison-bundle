import React from 'react';
import { theme } from 'antd';
import type { InlineToken } from '../../../api/types';

export interface InlineTextDiffProps {
  tokens: InlineToken[];
  /**
   * Which side this cell represents. The single token list encodes the left→right transform, so
   * the LEFT cell hides insertions and strikes deletions; the RIGHT cell hides deletions and
   * underlines insertions. 'both' renders the merged view.
   */
  side?: 'left' | 'right' | 'both';
}

/**
 * Renders an inline word/char diff token list. Insertions are underlined and coloured success;
 * deletions are struck through and coloured danger; unchanged tokens are neutral. Colour is never
 * the only signal — the text decoration carries the same meaning.
 */
export const InlineTextDiff: React.FC<InlineTextDiffProps> = ({ tokens, side = 'both' }) => {
  const { token } = theme.useToken();

  const visible = tokens.filter((t) => {
    if (t.op === 'equal') return true;
    if (t.op === 'insert') return side !== 'left';
    if (t.op === 'delete') return side !== 'right';
    return true;
  });

  if (visible.length === 0) {
    return <span style={{ color: token.colorTextTertiary }}>—</span>;
  }

  return (
    <span style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
      {visible.map((t, i) => {
        if (t.op === 'insert') {
          return (
            <span
              key={i}
              style={{ textDecoration: 'underline', color: token.colorSuccess, background: token.colorSuccessBg }}
            >
              {t.text}
            </span>
          );
        }
        if (t.op === 'delete') {
          return (
            <span
              key={i}
              style={{ textDecoration: 'line-through', color: token.colorError, background: token.colorErrorBg }}
            >
              {t.text}
            </span>
          );
        }
        return <span key={i}>{t.text}</span>;
      })}
    </span>
  );
};
