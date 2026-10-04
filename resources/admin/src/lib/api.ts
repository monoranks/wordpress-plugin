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
    /** Fixes MonoRanks can write once approved there; null from MonoRanks before 1.0.41. */
    to_review?: null | { total: number; by_field: Record<string, number>; needs_value?: number | null; review_url: string; geo_url: string };
    attention: { post_id: number; url: string; title: string; health: Score; aeo: Score; issue: string; page_url: string }[];
    ready: { id: string; field: string; post_id: number; title: string; before: string | null; after: string; page_url: string; from: string; op: string }[];
  };
  host: string; audited: string; next_audit: string; fetched: string; last_sent: string; items: number;
  app_url: string; log: LogRow[]; labels: Record<string, string>;
};

export type SettingsData = {
  connected: boolean; revoked: boolean; state: 'connected' | 'revoked' | 'none'; has_data: boolean;
  key_hint: string; key_via: string; api_base: string; last_sent: string; last_error: string;
  sending: null | { page: number; pages: number }; items: number; next_audit: string; seo_plugin: string; seo_supported: boolean;
  profile_url: string; show_address: boolean; app_url: string; log: LogRow[]; labels: Record<string, string>;
};

/** One Grow section's state: ok, empty (no data yet), no_access (the key lacks the read scope), off, error, pending. */
export type GrowState = 'ok' | 'empty' | 'no_access' | 'off' | 'error' | 'pending';
type Section<T> = ({ state: 'ok' } & T) | { state: Exclude<GrowState, 'ok'>; link?: string; all_link?: string };

export type GrowData = {
  connected: boolean;
  fetched?: string;
  scope_url?: string;
  outreach?: Section<{ to_contact: number; added: number; all: number; link: string; top: { title: string; domain: string; url: string; source: string; why: string; score: number | null; link: string }[] }>;
  backlinks?: Section<{ domains: number; new: number; lost: number; review: number; rank: number | null; link: string; top: { domain: string; rank: number | null; link: string }[] }>;
  competitors?: Section<{ link: string; gaps: number | null; top: { domain: string; picked: boolean; shared: number; above_us: number; reason: string }[]; keywords: { keyword: string; volume: number | null }[] }>;
  keywords?: Section<{ tracked: number; link: string; up: { keyword: string; from: number; to: number; url: string }[]; down: { keyword: string; from: number; to: number; url: string }[] }>;
  report?: Section<{ title: string; scope: string; client: boolean; created_at: string; when?: string; link: string; all_link: string }>;
};

export type ActionResult<T> = { ok: boolean; notice: Notice; data: T };

export const api = {
  overview: () => apiFetch<OverviewData>({ path: `${NS}/overview` }),
  settings: () => apiFetch<SettingsData>({ path: `${NS}/settings` }),
  grow: () => apiFetch<GrowData>({ path: `${NS}/grow` }),
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
