# MonoRanks for WordPress

The WordPress plugin for [MonoRanks](https://monoranks.com): SEO, AEO and GEO audits every week, fixes explained in plain words, and the ones you approve written into WordPress with undo.

- Sends published content metadata so audits and rechecks stay current (never drafts, comments or credentials).
- Applies approved fixes: titles, meta descriptions, headings, image alt text, canonical, noindex, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt, llms.txt.
- Works next to Yoast SEO, Rank Math and All in One SEO.
- Every write is logged under Settings → MonoRanks and can be undone.

Full description, what is sent and the FAQ: `readme.txt` (the WordPress.org listing).

## Development

This repository is the plugin's home. The MonoRanks monorepo includes it as a git submodule (`packages/wp-plugin/monoranks`) for its own end-to-end tests and the in-app download.

- `ci.yml`: PHP syntax on 7.4, 8.1 and 8.3, the WordPress.org plugin checker, a version consistency check, and Chromium tests (Playwright) against a real WordPress started with wp-env: settings page, key validation, REST permissions, nothing published before an approval.
- Locally: `npm install`, `npx wp-env start` (Docker), `npx playwright test`. The site runs at http://localhost:8889 (admin / password).
- `deploy.yml`: pushing a tag `v<version>` publishes that version to WordPress.org (SVN credentials in the repository secrets) and attaches the zip to the GitHub release.
- `.wordpress-org/`: banner, icon and screenshots for the directory listing.

License: GPL-2.0-or-later.
