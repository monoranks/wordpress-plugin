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
  "to_review": { "total": 58, "by_field": { "seo_title": 12, "alt": 41, "content": 4, "llms_txt": 1 }, "review_url": "https://app.monoranks.com/sites/site_abc/actions?view=wordpress", "geo_url": "https://app.monoranks.com/sites/site_abc/geo" },
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
- `to_review` (MonoRanks 1.0.41+, absent before): fixes MonoRanks can write once someone approves them there. `by_field` counts the pages of open issues per writable field, plus 1 for a missing `llms_txt` and 1 for missing `ai_bots` rules. `review_url` opens the Fix in WordPress view of Actions; `geo_url` the GEO screen for llms.txt and AI crawler rules.
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

## Storage on the WordPress side

- Option `monoranks_insights` (not autoloaded): `status` (ok | none | unsupported | error | revoked), `fetched_at`, `overview`, `pages_synced_at`.
- Post meta `_monoranks_score` (the page row above) and `_monoranks_health` (integer, for sorting the column).
- Disconnecting or deleting the plugin removes all of it.
