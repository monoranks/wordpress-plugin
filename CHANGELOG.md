# Changelog

All notable changes to the MonoRanks WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/); versions follow [Semantic Versioning](https://semver.org/).

## [0.1.7] — 2026-09-23

- Scores for every post, not just the first few hundred. A pull that ran out of time started again from the beginning each time, so on a site with many posts everything after the first batch stayed without a score; it now carries on where it stopped and finishes in the background.

## [0.1.6] — 2026-09-23

- Scores now appear as soon as you open the MonoRanks screen. They used to arrive only through WP-Cron, so on a site where WP-Cron is switched off, or where the host blocks the request that starts it, the screen said "Waiting for the first audit" even though MonoRanks had already audited the site.
- "Sync content now" brings the scores back with it instead of leaving them for a later background run.
- The rest of the pages still come down in the background, so a site with thousands of posts does not hold up the screen.

## [0.1.5] — 2026-09-23

- Disconnecting in WordPress now tells MonoRanks, so it revokes this website's key and stops showing the site as connected.
- "Open MonoRanks" and "Open the audit" go to this website in MonoRanks, not to its home page, even before the first audit results arrive.
- A MonoRanks that could not answer for audit results yet is asked again the next hour instead of the next day, and "Sync content now" asks straight away.

## [0.1.4] — 2026-09-23

- The Plugin URI and Author URI headers point at plain addresses, without tracking parameters.
- The contributor list uses the right WordPress.org username.
- The public ping route says in the code why it is public: MonoRanks calls it before the site is paired, it reads nothing, and every other route needs an administrator.

## [0.1.3] — 2026-09-23

- On a site with many plugins the admin menu is taller than the page; the plugin's screens now stretch to the bottom of it, so the footer is the last thing on the page with nothing left over under it.

## [0.1.2] — 2026-09-22

- On a site with a long admin menu the plugin's screens now reach the bottom of the page, so the footer is the last thing on it: no strip and no empty space under it.

## [0.1.1] — 2026-09-22

- On a site whose admin menu is taller than the page, the plugin's screens ended in a grey strip under the footer. The dark band now runs to the bottom of the page.

## [0.1.0] — 2026-09-22

Beta, submitted to WordPress.org for review. The version stays below 1.0.0 until the plugin is published there.

- Connects a WordPress site to MonoRanks with a connector key or by approving MonoRanks in WordPress (Application Password).
- Sends published content metadata (never drafts, comments or credentials) on connect, on every publish, update or unpublish, and once a day.
- Applies the fixes approved in MonoRanks: SEO title, meta description, canonical, noindex, image alt text, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt and an llms.txt file, each with Undo.
- Works next to Yoast SEO, Rank Math and All in One SEO; prints the approved tags itself when no SEO plugin is active.
- A MonoRanks menu in the WordPress admin. Overview: the site's health and AEO scores with their weekly change, search clicks over 28 days, pages needing attention, the fixes approved in MonoRanks with Apply and Apply all, and the recent changes with Undo. Settings: connection state, connector key, last sync, what is sent and read back, recent changes, disconnect.
- A "MonoRanks" column at the end of Posts, Pages and every public post type: each page's health and AEO score rings; hovering shows fixes waiting, open issues and when it was audited. Sortable by health; a "Needs attention" view lists published pages under 60.
- Reads this website's audit results from MonoRanks with the connector key (`docs/api.md`), cached and refreshed in the background so admin screens never wait on MonoRanks. Until MonoRanks answers these routes the screens say so and show nothing invented.
- Overview and Settings are one app: switching between them (top band, footer, "All changes", "Open settings") changes the URL and the admin menu's highlight without a page load; back and forward work. Every link that leaves the admin carries UTM tags (utm_source=wordpress-plugin).
- The Overview and Settings screens are a React app (Vite, Tailwind 4, shadcn-style components, the stack shared with WConvert): actions such as Apply, Undo, Sync and Connect happen in place through `monoranks/v1/admin/*` REST routes, with no page reload.
- The plugin's screens sit in their own frame: a dark brand band with the MonoRanks logo, the section links and a way into MonoRanks, a light title area, and a service footer with a link to what is sent, help, and the VeronaLabs credit. The menu icon is the MonoRanks mark.
- Admin screens use the MonoRanks design tokens (colours, radii, spacing; light and dark palettes; right-to-left admins mirror, with that script's digits). PHP templates live under `resources/views/`, the app's sources under `resources/admin/`, and `npm run build` writes `build/`.
- The MonoRanks address is fixed (`MONORANKS_API_BASE`, overridable in `wp-config.php`); the address field only shows on local and development sites. The old Settings → MonoRanks link redirects to the new screen.
- A danger zone in Settings, shown when the site is not connected: erases everything the plugin stored (connection, cached audit results, every page's scores, redirects, AI access files, change log). Pages keep what was written.
- Translations: `languages/monoranks.pot` and a Persian translation, loaded through WordPress's textdomain registry so a language pack always wins.
