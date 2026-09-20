# MonoRanks for WordPress

The WordPress plugin for [MonoRanks](https://monoranks.com): SEO, AEO and GEO audits every week, fixes explained in plain words, and the ones you approve written into WordPress with undo.

- Sends published content metadata so audits and rechecks stay current (never drafts, comments or credentials).
- Applies approved fixes: titles, meta descriptions, headings, image alt text, canonical, noindex, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt, llms.txt.
- Works next to Yoast SEO, Rank Math and All in One SEO.
- Every write is logged under Settings → MonoRanks and can be undone.

Full description, what is sent and the FAQ: `readme.txt` (the WordPress.org listing).

## Development

This repository mirrors `packages/wp-plugin/monoranks` of the MonoRanks monorepo, which is the source of truth; changes land here through `pnpm --filter @monoranks/wp-plugin publish:github`. Pull requests here are welcome and are applied upstream.

- `ci.yml`: PHP syntax on 7.4, 8.1 and 8.3, the WordPress.org plugin checker, and a version consistency check.
- `deploy.yml`: pushing a tag `v<version>` publishes that version to WordPress.org (SVN credentials in the repository secrets) and attaches the zip to the GitHub release.
- `.wordpress-org/`: banner, icon and screenshots for the directory listing.

License: GPL-2.0-or-later.
