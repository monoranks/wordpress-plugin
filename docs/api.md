# What the plugin reads from MonoRanks

The plugin sends content with the website's connector key (`POST /api/connector/status`, `/content`, `/changes`). Since 1.0.0 it also **reads** this website's audit results with the same key, so the Overview screen and the MonoRanks column in Posts and Pages can show scores without MonoRanks having to push anything. The key still only reaches this one website's data.

All reads are `GET {api_base}/api/connector/<route>` with `Authorization: Bearer <connector key>` and `Accept: application/json`. `api_base` is `https://app.monoranks.com` unless `MONORANKS_API_BASE` is overridden in `wp-config.php`.

Answers the plugin understands on every route:

- `200` with the JSON below.
- `401`: the key was revoked. The plugin marks the connection "Key revoked" and stops until a new key is set.
- `404` with `{ "error": "not_supported" }`: this MonoRanks does not have the route yet. The plugin shows "not scored yet" states and asks again after a day.

The plugin refreshes in the background (WP-Cron) when an admin opens a MonoRanks screen or a Posts list and the cache is older than an hour, right after "Send content now", and after a key is pasted. Nothing is fetched on the public site.

## `GET /api/connector/overview`

One object for the site. Every field is optional; unknown fields are ignored. Scores are integers 0–100 or `null`.

```json
{
  "site_id": "site_abc",
  "site_url": "https://app.monoranks.com/sites/site_abc",
  "audited_at": "2026-09-20T04:10:00Z",
  "next_audit_at": "2026-09-27T04:00:00Z",
  "health": 74,
  "aeo": 61,
  "pages_scored": 128,
  "pages_total": 140,
  "deltas": { "health": 3, "aeo": -2 },
  "traffic": { "clicks_28d": 12480, "delta_pct": 8, "series": [380, 402, 395, "... 28 daily values"] },
  "fixes": { "ready": 6, "applied_30d": 14 },
  "to_review": { "total": 58, "by_field": { "seo_title": 12, "alt": 41, "content": 4, "llms_txt": 1 }, "needs_value": 101, "needs_value_by_field": { "seo_title": 98, "alt": 3 }, "review_url": "https://app.monoranks.com/sites/site_abc/actions?view=wordpress", "geo_url": "https://app.monoranks.com/sites/site_abc/geo" },
  "ai": { "bots_rules": true, "llms_txt": true },
  "attention": [
    { "post_id": 12, "url": "https://example.com/pricing/", "title": "Pricing", "health": 38, "aeo": 44, "issue": "Meta description missing", "page_url": "https://app.monoranks.com/sites/site_abc/pages/p_1" }
  ],
  "ready": [
    { "id": "chg_1", "field": "seo_description", "post_id": 12, "title": "Pricing", "before": "", "after": "Plans from $19…", "page_url": "https://app.monoranks.com/sites/site_abc/changes/chg_1" },
    { "id": "chg_2", "field": "redirect", "from": "/old-pricing/", "before": "", "after": "https://example.com/pricing/" },
    { "id": "chg_3", "field": "alt", "attachment_id": 88, "post_id": 12, "before": "", "after": "Pricing table, three plans" },
    { "id": "chg_4", "field": "content", "post_id": 30, "op": "insert_top", "before": "", "after": "<p>Answer-first paragraph…</p>", "page_url": "…" }
  ]
}
```

- `deltas`: the change against the newest score at least six and a half days older than the latest one. Each value is `null` when there is no such score yet (a site's first week) or one of the two runs has no score; the plugin then shows no change at all rather than "0". Every plugin release since 0.1.2 accepts `null` here.
- `pages_scored` / `pages_total`: pages with a score and all pages MonoRanks knows for the site, counted separately.
- `attention`: at most 10 rows, lowest health first; `page_url` opens the page's report in MonoRanks.
- `to_review` (MonoRanks 1.0.41+, absent before): fixes MonoRanks can write once someone approves them there. `by_field` counts the pages of open issues per writable field, plus 1 for a missing `llms_txt` and 1 for missing `ai_bots` rules. `review_url` opens the Fix in WordPress view of Actions; `geo_url` the GEO screen for llms.txt and AI crawler rules. `total` and `by_field` count only fixes that already have a value ready to apply (MonoRanks 1.0.42+). `needs_value` and `needs_value_by_field` (MonoRanks 1.0.68+) count the pages whose fix still needs a value typed or drafted with AI in MonoRanks; older MonoRanks sent the same numbers as `needs_draft` and `draft_by_field`, which it still sends. From 0.1.16 the plugin shows "3 ready to apply · 101 need a value in MonoRanks" when either count is there, and the older "58 fixes are ready for your approval" when neither is.
- `ready`: fixes the owner approved in MonoRanks that are not written yet, at most 50. `field` is one of the fields the plugin can write (`seo_title`, `seo_description`, `canonical`, `noindex`, `alt`, `redirect`, `content`, `ai_bots`, `llms_txt`); `before` is the value MonoRanks saw (the plugin refuses the change when the site differs) and `after` the value to write. The plugin applies them through the same code path as `POST /wp-json/monoranks/v1/apply`, then reports back (below). `content` changes are only reviewed in MonoRanks (the plugin links to `page_url`), not applied from WordPress.
- `series` holds up to 28 daily click counts, oldest first.

## `GET /api/connector/pages?since=<iso>&cursor=<cursor>`

Per-page scores for the Posts and Pages column. `since` is the newest `audited_at` the plugin has stored (omitted on the first pull); MonoRanks answers with every page audited after it. Pages are matched by `post_id` (the WordPress ID the plugin sent with the content) or, failing that, by `url`.

```json
{
  "pages": [
    { "post_id": 12, "url": "https://example.com/pricing/", "health": 38, "aeo": 44, "open_issues": 3, "fixes_ready": 2, "audited_at": "2026-09-20T04:10:00Z", "page_url": "https://app.monoranks.com/sites/site_abc/pages/p_1" }
  ],
  "next": null
}
```

`next` is an opaque cursor for the next batch (`null` when done); the plugin follows at most 25 batches per refresh.

## `POST /api/connector/disconnect`

Sent when someone disconnects the plugin in WordPress, so MonoRanks revokes this website's key and stops showing the site as connected. Body: `{ "reason": "disconnected in WordPress" }` (optional). The plugin does not wait for the answer: it disconnects either way.

## `POST /api/connector/fixes`

After the plugin applied fixes from the Overview it tells MonoRanks what happened, so the change record moves on (applied, or refused because the page changed since the preview):

```json
{ "results": [ { "id": "chg_1", "ok": true, "previous": "", "post_id": 12 }, { "id": "chg_2", "ok": false, "error": "changed_since_preview", "current": "…" } ] }
```

Same result shape as `POST /wp-json/monoranks/v1/apply`. Best-effort: the plugin does not retry it.

Since 0.1.14 a `content` change (or its undo) whose saved result would break a block is rolled back and comes back as `{ "ok": false, "error": "block_check_failed", "reason": "The new paragraph block does not match what the block editor would save. The post was put back as it was." }`. `reason` is a plain-language sentence meant to be shown as is; `update_failed` now carries a `reason` too.

### `cache` (since 0.1.18)

Every successful write or undo (`ok: true`) also says what the site's page caches did with the page that changed, so MonoRanks can explain a page that still shows the old version:

```json
{ "id": "chg_1", "ok": true, "previous": "", "post_id": 12, "cache": { "purged": ["wp-optimize"], "failed": [], "none": false } }
```

- `purged`: caches that were asked to drop the page. Names: `wp-rocket`, `litespeed`, `w3-total-cache`, `wp-super-cache`, `siteground`, `wp-optimize`, `nginx-helper`, `breeze`, `kinsta`, `wp-engine`, `pantheon`.
- `failed`: caches that reported a failure or threw. The write itself still succeeded.
- `none`: `true` when no supported cache was active for this change, so nothing was purged (a cache the plugin cannot reach, such as a Cloudflare cache rule, may still hold the page).
- What is purged: the post's URL for SEO fields and the opening paragraph (plus the home page when the post is the static front page); the post the image belongs to for `alt` (the change's `post_id`, else the post it was uploaded to; no `cache` field when neither is known); the source URL for `redirect`; `/llms.txt` or `/robots.txt` for `llms_txt` and `ai_bots`.
- Absent on refused changes (`ok: false`) and from plugins before 0.1.18; treat a missing field as "unknown".

Since 0.1.15 an SEO field (`seo_title`, `seo_description`, `canonical`, `noindex`) can also be refused before anything is written:

- `seo_plugin_unsupported`: an SEO plugin MonoRanks cannot write into is active (status `seo_plugin: "other"`), for example `"reason": "SEOPress is active on this site and prints its own SEO tags. MonoRanks cannot write into SEOPress yet, so nothing was changed. Make this change in SEOPress instead."`
- `seo_plugin_homepage_setting`: with The SEO Framework, the post is the static front page and that field is set in its Homepage Settings, which win over the page's own field.

## `POST /api/connector/status`

The plugin's status, sent after connecting, on "Send content now" and daily (the same object as `GET /wp-json/monoranks/v1/status` without the user fields). `seo_plugin` is `yoast`, `rankmath`, `aioseo`, `tsf` (The SEO Framework, since 0.1.15), `other` (an SEO plugin MonoRanks cannot write into, since 0.1.15) or `none`; `seo_plugin_name` (since 0.1.15) is its name, or `""`. If MonoRanks answers 400 to a status whose `seo_plugin` is `tsf` or `other`, the plugin sends it once more with `seo_plugin: "none"`.

## What to do next and per-post data: API v1 reads

The Overview's **What to do next** cards and the **MonoRanks** box in the post editor read MonoRanks API v1 with the same key: `GET {api_base}/api/v1/sites/{site_id}/<route>`, `Authorization: Bearer <connector key>`. `site_id` is the one stored at pairing (or the overview's `site_id` for a key pasted by hand). The full shapes are in MonoRanks' `/api/v1/openapi.json`; the plugin keeps only the fields below and ignores the rest.

| Shown in WordPress | Route | Scope |
| --- | --- | --- |
| Outreach card: targets to contact, the top 3 with why | `outreach?status=to_contact&per=3` | `search:read` |
| Backlinks card: websites won and lost in 28 days, to review for spam, newest strong ones | `backlinks?view=new&per=3&sort=strength` | `search:read` |
| Competitors card: top 3 (the ones that drive gaps first), gap count, top 3 gap keywords | `competitors`, `competitors/gaps?view=gaps&per=3` | `search:read` |
| Keyword movers card: tracked keywords up and down since a week ago | `keywords?view=tracked&sort=change&dir=desc&per=10` and `dir=asc` | `search:read` |
| Latest report line | `reports?limit=1` (monoranks/monoranks#199) | `sites:read` |
| Post box and column card: visits, key events and revenue per page (28 days) | `analytics/pages?limit=1000&sort=-revenue` | `analytics:read` |
| Same, clicks only, when Google Analytics is not connected (409) | `search/rows?dimension=page&limit=1000&from=…&to=…` | `search:read` |
| Post box and column card: lost links to win back for the page | `outreach?source=lost_link&per=200` (grouped by `reason.lostTo`) | `search:read` |
| Post box: last change MonoRanks saw | `pages/history?limit=10&url=<permalink>` | `pages:read` |
| Post box: top searches for the page | `search/rows?dimension=query_page&limit=5&from=…&to=…&page=<permalink>` | `search:read` |

The 28 days end three days ago, the last day of final Search Console data, as MonoRanks uses by default.

Keys made by a WordPress pairing once monoranks/monoranks#199 is released carry `search:read` and `analytics:read` next to the three locked plugin scopes. Older keys have only `content:write`, `sites:read` and `pages:read`: the cards then say the key cannot read this yet and link to the website's Integrations screen in MonoRanks, where the owner can tick the scopes, or the site can be connected again.

How each answer is used:

- `200`: shown. An empty list is shown as a plain "nothing yet" line, never as an error.
- `403 missing_scope` (or the key owner left the workspace): the card says the key cannot read it yet. The key is **not** marked revoked.
- `503 api_disabled` or `404 no_route` (an older MonoRanks): "Not available in your MonoRanks yet".
- `409 ga4_not_connected` / `ga4_no_access` on `analytics/pages`: the post box offers to connect Google Analytics and shows clicks from `search/rows` instead.
- Anything else (timeouts, `429`, `5xx`): "could not be reached", asked again within the hour.

How often:

- The cards: one pull of at most 7 calls when the Overview opens and the cache is older than six hours (one hour after a failed section), within a 12-second budget; sections it had no time for are finished by WP-Cron.
- Page values (analytics or search rows, plus lost links): at most 3 calls a day, in the background (WP-Cron) when a Posts list or the post editor opens, never while the list renders.
- The post box: 2 calls per post, when the editor opens and that post's reads are older than twelve hours (one hour after a failure). The box loads after the editor, so opening a post never waits on MonoRanks.

All of it is read in the admin only, by administrators (`manage_options`); nothing is fetched on the public site.

## Storage on the WordPress side

- Option `monoranks_insights` (not autoloaded): `status` (ok | none | unsupported | error | revoked), `fetched_at`, `overview`, `pages_synced_at`.
- Post meta `_monoranks_score` (the page row above) and `_monoranks_health` (integer, for sorting the column).
- Transient `monoranks_grow` (kept 3 days, fresh for 6 hours): the What to do next cards, one entry per section with its `state` (ok | empty | no_access | off | error | pending).
- Transient `monoranks_page_values` (kept 3 days, fresh for a day): per page path, clicks, impressions and position, and with Google Analytics sessions, key events and revenue; lost links per page; the currency and period.
- Transients `monoranks_post_<id>` (12 hours): the post box's last change and top searches for that post.
- Disconnecting, connecting a new key, or deleting the plugin removes all of it.
