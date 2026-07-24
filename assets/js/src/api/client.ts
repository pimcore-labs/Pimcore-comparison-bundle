/**
 * Plain fetch() client for the Comparison REST API (mirrors the DataSpine `spineApi` pattern:
 * a thin wrapper with credentials:'include', JSON headers, throws on non-OK responses). No RTK
 * Query — the comparison surface is read-only and stateless, so a simple client is more robust.
 */
import type {
  ComparisonResult,
  ComparisonSummary,
  ComparisonStatus,
  ComparisonQuery,
  ExportFormat,
  FilterMode,
} from './types';

const API = '/pimcore-studio/api/comparison';

function qs(params: Record<string, string | number | boolean | undefined | null>): string {
  const sp = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') {
      sp.set(k, String(v));
    }
  });
  const s = sp.toString();
  return s ? `?${s}` : '';
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    credentials: 'include',
    ...options,
  });

  if (response.status === 204) {
    return undefined as T;
  }

  if (!response.ok) {
    const text = await response.text().catch(() => '');
    throw new Error(
      `Comparison API error: ${response.status} ${response.statusText}${text ? `: ${text}` : ''}`,
    );
  }

  return (await response.json()) as T;
}

/** Build the query string shared by objects()/summary(). */
function objectsQs(q: ComparisonQuery): string {
  return qs({
    leftId: q.leftId,
    rightId: q.rightId,
    filter: q.filter,
    query: q.query,
    locales: q.locales && q.locales.length > 0 ? q.locales.join(',') : undefined,
  });
}

export const comparisonApi = {
  /** Liveness / permission probe. */
  status: () => request<ComparisonStatus>('/status'),

  /** The filtered diff tree + summary for a pair. */
  objects: (q: ComparisonQuery) => request<ComparisonResult>(`/objects${objectsQs(q)}`),

  /** The summary counts only (cheaper than /objects). */
  summary: (q: ComparisonQuery) => request<ComparisonSummary>(`/objects/summary${objectsQs(q)}`),

  /**
   * URL for a GET-style export. The export endpoint is a POST, so this is only useful when we
   * want the browser to navigate; prefer exportDownload() for the real (filtered) export.
   */
  exportUrl: (q: ComparisonQuery, format: ExportFormat): string =>
    `${API}/objects/export${qs({ leftId: q.leftId, rightId: q.rightId, format, filter: q.filter, query: q.query })}`,

  /**
   * POST the export request and trigger a browser download of the returned blob. Honors the
   * current filtered view + server-side permissions (T-SEC-006).
   */
  exportDownload: async (
    q: ComparisonQuery,
    format: ExportFormat,
  ): Promise<void> => {
    const response = await fetch(`${API}/objects/export`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        leftId: q.leftId,
        rightId: q.rightId,
        format,
        filter: q.filter,
        query: q.query,
      }),
    });
    if (!response.ok) {
      const text = await response.text().catch(() => '');
      throw new Error(`Comparison export failed: ${response.status} ${response.statusText}${text ? `: ${text}` : ''}`);
    }
    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const match = /filename="?([^"]+)"?/.exec(disposition);
    const filename = match?.[1] ?? `comparison-${q.leftId}-vs-${q.rightId}.${format}`;
    const url = URL.createObjectURL(blob);
    try {
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
    } finally {
      URL.revokeObjectURL(url);
    }
  },
};

export type ComparisonApi = typeof comparisonApi;
export type { FilterMode };
