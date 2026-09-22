import { adminSettings } from '@/settings';

/**
 * A link that leaves the WordPress admin carries UTM tags, so the website can tell plugin traffic apart. Links into the
 * MonoRanks app itself are left alone: the app already knows where the person came from.
 */
export function out(url: string, content: string, campaign = 'plugin-admin'): string {
  try {
    const u = new URL(url);
    if (u.host === new URL(adminSettings().apiBase).host) return url;
    u.searchParams.set('utm_source', 'wordpress-plugin');
    u.searchParams.set('utm_medium', 'admin');
    u.searchParams.set('utm_campaign', campaign);
    u.searchParams.set('utm_content', content);
    return u.toString();
  } catch {
    return url;
  }
}
