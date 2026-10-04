# Changelog

All notable changes to the MonoRanks WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/); versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

- **What to do next** on the Overview: four cards and a line under the scores. Outreach (targets to contact, the top three with why), Backlinks (websites won and lost in 28 days, how many to review for spam, the newest strong ones), Competitors (the top three, how many keywords they rank for and this site does not, the top three of those), Keyword movers (tracked keywords up and down since a week ago) and the latest website or client report. Each card says plainly when the key cannot read it yet, when MonoRanks has it switched off, or when there is no data. Read from MonoRanks API v1, cached for six hours.
- A **MonoRanks box in the post editor** (block and classic editor), for administrators: the page's scores, Google search clicks, impressions, average position and top searches over 28 days, visits from search, key events and revenue when Google Analytics is connected in MonoRanks, lost links to win back, and the last change MonoRanks saw on the page. It loads after the editor, so opening a post never waits.
- The card in the Posts and Pages column shows administrators the page's clicks and revenue over 28 days and its lost links.
- Needs the `search:read` and `analytics:read` scopes on the site's key, which a WordPress pairing gives once monoranks/monoranks#199 is released. Older keys keep working; those cards point to the website's Integrations screen to allow them.
- The Persian catalogue's header had Persian digits in its charset and plural rule (`UTF-۸`, `n > ۱`); they are Latin again.
- `docs/api.md` lists every route, scope, cache time and how each answer is shown.

## [0.1.18] — unreleased

- After every write and undo, the plugin asks the active page caches to drop the page that changed, so the new title, description, canonical, noindex, alt text, opening paragraph or redirect shows right away instead of when the cache expires (#11). SEO fields and the opening paragraph purge the post's URL, plus the home page when the post is the static front page; alt text purges the post the image belongs to; a redirect purges its source URL; llms.txt and the AI crawler rules purge `/llms.txt` or `/robots.txt` as before.
- New caches, each through its public API and only when active: WP-Optimize (`WPO_Page_Cache::delete_cache_by_url()`, only when its page cache is on), Nginx Helper (the `$nginx_purger->purge_url()` it exposes as a global, only when purging is on), Breeze (`do_action( 'purge_post_cache', $post_id )`, posts only), Kinsta (`$kinsta_cache->kinsta_cache_purge->initiate_purge( $post_id, 'post' )`), WP Engine (`WpeCommon::purge_varnish_cache( $post_id )`) and Pantheon Advanced Page Cache (`pantheon_wp_clear_edge_paths()`). Kinsta and WP Engine have no one-URL purge, so for `/llms.txt` and `/robots.txt` they purge everything (`purge_complete_caches()`, `purge_varnish_cache()`); a redirect source that is not a post is left alone there. WP Rocket purges the home page with `rocket_clean_home()`.
- The official Cloudflare plugin still has no public function or action to purge one URL, so it is not called. Its own hook already purges a post when the opening paragraph is written (that write saves the post).
- Each successful write result has a new `cache` field: `{ "purged": ["wp-optimize"], "failed": [], "none": false }`. `none` is true when no supported cache was active. A cache that reports a failure or throws is listed in `failed`; the write itself still succeeds. Older fields are unchanged.
- New filter `monoranks_purge_urls` and action `monoranks_purged_cache` for page purges, next to the existing `monoranks_purge_file_urls` and `monoranks_purged_file_cache`.

## [0.1.17] — unreleased

- After MonoRanks writes llms.txt or the AI crawler rules, the plugin asks the active page cache to drop `/llms.txt` or `/robots.txt`, so a copy cached before the change (often a 404 from before the file existed) does not hide it from AI crawlers or from MonoRanks' own check (monoranks/monoranks#87). Supported through each plugin's public API: WP Rocket (`rocket_clean_files`), LiteSpeed Cache (`litespeed_purge_url`), W3 Total Cache (`w3tc_flush_url`), WP Super Cache (`wpsc_delete_url_cache`) and SiteGround Optimizer (`sg_cachepress_purge_cache`). The official Cloudflare plugin has no public way to purge one URL and is left alone; a CDN cache rule outside WordPress still clears only when it expires or is purged there.
- New filter `monoranks_purge_file_urls` to add URLs to that purge, and action `monoranks_purged_file_cache` for other caches.
- `/llms.txt` served by the plugin is marked as not cacheable: `Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private`, `DONOTCACHEPAGE`, and LiteSpeed's no-cache control.

## [0.1.16] — unreleased

- The Overview's "Ready to apply" card splits the count: "3 ready to apply · 101 need a value in MonoRanks". It reads `to_review.needs_value` (MonoRanks 1.0.68+, or `needs_draft` from 1.0.42) and keeps the older "N fixes are ready for your approval" when MonoRanks sends neither (monoranks/monoranks#73).
- The card also shows when nothing is ready yet but some fixes need a value, and its text says to type or draft the value in MonoRanks.
- `docs/api.md` documents `needs_value` and `needs_value_by_field`.

## [0.1.15] — unreleased

- The SEO Framework is detected (`THE_SEO_FRAMEWORK_VERSION` or `The_SEO_Framework\Load`). Approved titles, descriptions, canonicals and noindex are written into its post meta: `_genesis_title`, `_genesis_description`, `_genesis_canonical_uri` and `_genesis_noindex` (`1` for noindex; turning it off goes back to its "Default", a forced index `-1` stays). Before, it was treated as "no SEO plugin", so an applied title never reached the page and a description or canonical would have been printed twice (#2).
- A title MonoRanks wrote is the whole title: The SEO Framework's site name (or homepage tagline) addition is left off it through `the_seo_framework_use_title_branding`, for as long as the field still holds that title.
- On a static front page, The SEO Framework's Homepage Settings (Meta Title, Meta Description, Canonical URL) win over the page's own fields. When one is set, that write is refused with `error: "seo_plugin_homepage_setting"` and a `reason`; reads return the Homepage Settings value.
- SEOPress, Slim SEO, Squirrly SEO, SmartCrawl, WP Meta SEO and SEO SIMPLE PACK are detected as `seo_plugin: "other"`. Title, description, canonical and noindex writes are refused with `error: "seo_plugin_unsupported"` and a `reason` that names the plugin, and the connector prints none of its own tags next to theirs.
- The status sent to MonoRanks has `seo_plugin` `tsf` or `other` where it applies, and a new `seo_plugin_name`. A MonoRanks that does not accept the new values yet answers 400; the plugin then sends the status again with `seo_plugin: "none"` so connecting keeps working.
- Settings shows a warning when an SEO plugin MonoRanks cannot write into is active.
- Integration tests run with The SEO Framework loaded (`MONORANKS_WP_PLUGINS=autodescription/autodescription.php`, group `tsf`).

## [0.1.14] — unreleased

- In block editor posts the approved opening paragraph is written as its own `core/paragraph` block (with `<!-- wp:paragraph -->` delimiters, named "MonoRanks opening" in the List View) in front of the first block, instead of a marker-wrapped paragraph that the editor showed as a Classic block. Classic posts keep the marker-wrapped paragraph. An opening written by an earlier version is still found, replaced and removed (monoranks/monoranks#48).
- Blocks from other plugins (Rank Math, Yoast and others) are never rewritten: only our own paragraph is added or removed, and kses is skipped for this one save so it cannot re-filter markup that is already stored.
- After every body edit and every undo the post is read back: the stored content must be exactly what was meant, the new block must be a registered `core/paragraph` whose `serialize_block()` round trip equals its stored markup, and every other block must still be there in order. Otherwise the previous content is put back byte for byte and the change fails with `error: "block_check_failed"` and a plain-language `reason`.
- Integration tests run against a local WordPress (`MONORANKS_WP_PATH=… composer test:integration`).

## [0.1.13] — 2026-09-26

- The plugin description lists only the fixes it can write: titles, meta descriptions, image alt text, canonical URLs, noindex, redirects, one opening paragraph, AI crawler rules and llms.txt.
- With All in One SEO active, approved titles, descriptions, canonicals and noindex are passed to it through its filters (`aioseo_title`, `aioseo_description`, `aioseo_canonical_url`, `aioseo_robots_meta`) instead of printing a second set of tags.
- Without an SEO plugin, an approved noindex is added to WordPress's own robots tag through `wp_robots` instead of a second robots tag.
- The readme links to the admin app's source code and build steps.

## [0.1.11] — 2026-09-25

- "Ready to apply" no longer ends at "No fixes waiting" when MonoRanks has fixes it could write. It says how many are ready for approval, split by type (page titles, image alt text, llms.txt and so on), with a Review in MonoRanks button; the Fixes card links to the same place. Needs MonoRanks 1.0.41 or later; with an older MonoRanks the screen looks as before.

## [0.1.10] — 2026-09-24

- The change beside search clicks on the MonoRanks screen reads "+8% vs previous 28 days" instead of "+8% this week". The figure has always compared the last 28 days with the 28 days before; only the label was wrong.
- Persian: three messages added in recent versions were still in English and are now translated.
- A site that was never connected no longer shows the "Danger zone" erase card in Settings. Since 0.1.8 the update step saved an empty record on a fresh install, so the plugin thought it had data to erase.

## [0.1.9] — 2026-09-23

- An empty ring in the Posts list says what it means. MonoRanks reads AEO on a sample of pages, so a post without an AEO score has not been checked rather than scored badly, and the ring now says so.

## [0.1.8] — 2026-09-23

- Reads every score again after this update. Earlier versions could mark an audit as stored while MonoRanks had only sent its first batch of pages, which left most posts without a score in the Posts list; the update clears that mark once and the scores come back in full.
- Deleting the plugin also clears the lock it uses while reading from MonoRanks.

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
