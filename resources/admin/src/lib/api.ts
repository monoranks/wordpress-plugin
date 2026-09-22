import apiFetch from '@wordpress/api-fetch';
import type { Notice } from '@/settings';

/** The REST routes under monoranks/v1/admin that the screens read and act on (src/Rest.php). */
const NS = '/monoranks/v1/admin';

export type Score = number | null;

export type LogRow = { index: number; at: string; when: string; actor: string; field: string; fieldLabel: string; where: string; previous: string; value: string };

export type OverviewData = {
  connected: boolean;
  revoked: boolean;
  status: 'ok' | 'none' | 'unsupported' | 'error' | 'revoked' | 'never';
  overview: null | {
    site_url: string; audited_at: string; next_audit_at: string;
    health: Score; aeo: Score; pages_scored: number; pages_total: number;
    deltas: { health: number | null; aeo: number | null };
    traffic: null | { clicks_28d: number; delta_pct: number | null; series: number[] };
    fixes: { ready: number; applied_30d: number };
    ai: { bots_rules: boolean; llms_txt: boolean };
    attention: { post_id: number; url: string; title: string; health: Score; aeo: Score; issue: string; page_url: string }[];
    ready: { id: string; field: string; post_id: number; title: string; before: string | null; after: string; page_url: string; from: string; op: string }[];
  };
  host: string; audited: string; next_audit: string; fetched: string; last_sent: string; items: number;
  app_url: string; log: LogRow[]; labels: Record<string, string>;
};

export type SettingsData = {
  connected: boolean; revoked: boolean; state: 'connected' | 'revoked' | 'none'; has_data: boolean;
  key_hint: string; key_via: string; api_base: string; last_sent: string; last_error: string;
  sending: null | { page: number; pages: number }; items: number; next_audit: string; seo_plugin: string;
  profile_url: string; show_address: boolean; app_url: string; log: LogRow[]; labels: Record<string, string>;
};

export type ActionResult<T> = { ok: boolean; notice: Notice; data: T };

export const api = {
  overview: () => apiFetch<OverviewData>({ path: `${NS}/overview` }),
  settings: () => apiFetch<SettingsData>({ path: `${NS}/settings` }),
  apply: (fix: string) => apiFetch<ActionResult<OverviewData>>({ path: `${NS}/apply`, method: 'POST', data: { fix } }),
  undo: (row: LogRow, screen: 'overview' | 'settings') => apiFetch<ActionResult<OverviewData | SettingsData>>({ path: `${NS}/undo`, method: 'POST', data: { entry: row.index, at: row.at, screen } }),
  sync: (screen: 'overview' | 'settings') => apiFetch<ActionResult<OverviewData | SettingsData>>({ path: `${NS}/sync`, method: 'POST', data: { screen } }),
  connect: (key: string, api_base?: string) => apiFetch<ActionResult<SettingsData>>({ path: `${NS}/connect`, method: 'POST', data: { key, api_base } }),
  disconnect: () => apiFetch<ActionResult<SettingsData>>({ path: `${NS}/disconnect`, method: 'POST' }),
  deleteEverything: () => apiFetch<ActionResult<SettingsData>>({ path: `${NS}/delete`, method: 'POST' }),
};

/** apiFetch rejects with the REST error body; show its message, or a plain one. */
export function errorText(e: unknown, fallback: string): string {
  const m = (e as { message?: string } | undefined)?.message;
  return typeof m === 'string' && m ? m : fallback;
}
