/**
 * Wire types for the Comparison REST API. These mirror the PHP DTOs exactly
 * (src/Diff/FieldDiff.php, src/Diff/DiffStatus.php, ComparisonController responses),
 * so the React client renders them directly (thin client — nothing is recomputed here).
 */

export type DiffStatus =
  | 'equal'
  | 'changed'
  | 'only-left'
  | 'only-right'
  | 'reordered'
  | 'not-comparable'
  | 'hidden';

/** One token of an inline text diff (TextDiffComparator). */
export interface InlineToken {
  op: 'equal' | 'insert' | 'delete';
  text: string;
}

/** A relation chip (RelationComparator → meta.chips). */
export interface RelationChip {
  label: string;
  state: 'kept' | 'added' | 'removed' | 'moved' | 'reordered';
}

/** A single asset entry (AssetFieldComparator → meta.left / meta.right). */
export interface AssetEntry {
  id: number | string | null;
  path: string | null;
}

/** Free-form per-row metadata. Only the keys the UI reads are enumerated. */
export interface FieldMeta {
  chips?: RelationChip[];
  counts?: { added: number; removed: number; kept: number };
  reordered?: boolean;
  left?: AssetEntry[];
  right?: AssetEntry[];
  locale?: string;
  language?: string;
  note?: string;
  [key: string]: unknown;
}

/** The normalized diff of a single attribute (PHP FieldDiff::jsonSerialize). */
export interface FieldDiff {
  name: string;
  label: string;
  fieldtype: string;
  status: DiffStatus;
  leftDisplay?: unknown;
  rightDisplay?: unknown;
  inlineDiff?: InlineToken[];
  children?: FieldDiff[];
  meta?: FieldMeta;
}

export interface DiffCounts {
  [status: string]: number;
}

export interface DiffSummary {
  total: number;
  differing: number;
  counts: DiffCounts;
}

/** GET /objects response. */
export interface ComparisonResult {
  leftId: number;
  rightId: number;
  className: string;
  fields: FieldDiff[];
  summary: DiffSummary;
}

/** GET /objects/summary response. */
export interface ComparisonSummary {
  leftId: number;
  rightId: number;
  className: string;
  total: number;
  differing: number;
  counts: DiffCounts;
}

/** GET /status response. */
export interface ComparisonStatus {
  bundle: string;
  ok: boolean;
  version: string;
  readOnly: boolean;
}

export type FilterMode = 'all' | 'differences' | 'equal';
export type ExportFormat = 'xlsx' | 'json';

export interface ComparisonQuery {
  leftId: number;
  rightId: number;
  filter?: FilterMode;
  query?: string;
  locales?: string[];
}
