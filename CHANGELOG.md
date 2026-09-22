# Changelog

All notable changes to the MonoRanks WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/); versions follow [Semantic Versioning](https://semver.org/).

## [1.1.0] — unreleased

First release on WordPress.org.

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
- Translations: `languages/monoranks.pot` and a Persian translation.
