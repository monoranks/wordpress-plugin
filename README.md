# MonoRanks for WordPress

The WordPress plugin for [MonoRanks](https://monoranks.com): SEO, AEO and GEO audits every week, fixes explained in plain words, and the ones you approve written into WordPress with undo.

- Sends published content metadata so audits and rechecks stay current (never drafts, comments or credentials).
- Applies approved fixes: titles, meta descriptions, headings, image alt text, canonical, noindex, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt, llms.txt.
- Works next to Yoast SEO, Rank Math and All in One SEO.
- Every write is logged under Settings → MonoRanks and can be undone.

Full description, what is sent and the FAQ: `readme.txt` (the WordPress.org listing).

## Development

This repository is the plugin's home. The MonoRanks monorepo includes it as a git submodule (`packages/wp-plugin/monoranks`) for its own end-to-end tests and the in-app download.

- `ci.yml`, one pipeline: PHP syntax on 7.4, 8.1 and 8.3; unit tests (PHPUnit + Brain Monkey, no WordPress needed: `composer install && vendor/bin/phpunit`); the WordPress.org plugin checker; a version consistency check (header, constant, readme, changelog); Chromium tests (Playwright) against a real WordPress started with wp-env. A tag `v<version>` runs all of that first and deploys to WordPress.org only when every job passed.
- Classes: `src/` (namespace `MonoRanks`, Composer PSR-4). Third-party libraries go into `composer-deps.json`; WPify Scoper prefixes them into `deps/` on `composer install`, so nothing collides with other plugins.
- Locally: `npm install`, `npx wp-env start` (Docker), `npx playwright test`. The site runs at http://localhost:8889 (admin / password).
- `.wordpress-org/`: banner, icon and screenshots for the directory listing.

License: GPL-2.0-or-later.
