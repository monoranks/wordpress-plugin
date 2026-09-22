# Changelog

All notable changes to the MonoRanks WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/); versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] — unreleased

Initial release.

- Connects a WordPress site to MonoRanks with a connector key or by approving MonoRanks in WordPress (Application Password).
- Sends published content metadata (never drafts, comments or credentials) on connect, on every publish, update or unpublish, and once a day.
- Applies the fixes approved in MonoRanks: SEO title, meta description, canonical, noindex, image alt text, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt and an llms.txt file, each with Undo.
- Works next to Yoast SEO, Rank Math and All in One SEO; prints the approved tags itself when no SEO plugin is active.
- A MonoRanks menu in the WordPress admin. Overview: the site's health and AEO scores with their weekly change, search clicks over 28 days, pages needing attention, the fixes approved in MonoRanks with Apply and Apply all, and the recent changes with Undo. Settings: connection state, connector key, last sync, what is sent and read back, recent changes, disconnect.
- A "MonoRanks" column in Posts, Pages and every public post type: each page's health and AEO scores, fixes waiting for it and when it was audited. Sortable by health; a "Needs attention" view lists published pages under 60.
- Reads this website's audit results from MonoRanks with the connector key (`docs/api.md`), cached and refreshed in the background so admin screens never wait on MonoRanks. Until MonoRanks answers these routes the screens say so and show nothing invented.
- Admin screens use the MonoRanks design tokens (colours, radii, spacing; light and dark palettes; right-to-left admins mirror). Templates live under `views/`.
- The MonoRanks address is fixed (`MONORANKS_API_BASE`, overridable in `wp-config.php`); the address field only shows on local and development sites. The old Settings → MonoRanks link redirects to the new screen.
- Translations: `languages/monoranks.pot` and a Persian translation.
