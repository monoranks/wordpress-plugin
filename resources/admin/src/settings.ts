/** What src/Assets.php prints before the bundle: where things are, which screen this is, the brand SVGs. */
export type AdminSettings = {
  screen: 'overview' | 'settings';
  locale: string;
  theme: 'light' | 'dark';
  version: string;
  host: string;
  urls: { overview: string; settings: string; app: string; profile: string; docs: string };
  titles: { overview: string; settings: string };
  showAddress: boolean;
  apiBase: string;
  logos: { wordmark: string; mark: string; veronalabs: string };
  notice: Notice | null;
};

export type Notice = { type: 'success' | 'error' | 'warning'; text: string };

declare global {
  interface Window { monoranksAdmin?: AdminSettings }
}

export function adminSettings(): AdminSettings {
  const s = window.monoranksAdmin;
  if (!s) throw new Error('monoranksAdmin settings missing');
  return s;
}
