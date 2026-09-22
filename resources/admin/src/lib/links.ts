/** Every link that leaves the WordPress admin carries UTM tags, so MonoRanks and the website can tell plugin traffic apart. */
export function out(url: string, content: string, campaign = 'plugin-admin'): string {
  try {
    const u = new URL(url);
    u.searchParams.set('utm_source', 'wordpress-plugin');
    u.searchParams.set('utm_medium', 'admin');
    u.searchParams.set('utm_campaign', campaign);
    u.searchParams.set('utm_content', content);
    return u.toString();
  } catch {
    return url;
  }
}
