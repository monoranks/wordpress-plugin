# MonoRanks for WordPress

The WordPress plugin for [MonoRanks](https://monoranks.com): SEO, AEO and GEO audits every week, fixes explained in plain words, and the ones you approve written into WordPress with undo.

- Sends published content metadata so audits and rechecks stay current (never drafts, comments or credentials).
- Applies approved fixes: titles, meta descriptions, headings, image alt text, canonical, noindex, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt, llms.txt.
- Works next to Yoast SEO, Rank Math and All in One SEO.
- Every write is logged under Settings → MonoRanks and can be undone.

Full description, what is sent and the FAQ: `readme.txt` (the WordPress.org listing).

## Development

This repository is the plugin's home. The MonoRanks monorepo includes it as a git submodule (`packages/wp-plugin/monoranks`) for its own end-to-end tests and the in-app download.

- `tests.yml`: the suite, on every pull request — PHP syntax on 7.4, 8.1 and 8.3; unit tests (PHPUnit + Brain Monkey, no WordPress needed: `composer install && vendor/bin/phpunit`); the WordPress.org plugin checker; a version consistency check (header, constant, readme, changelog); Chromium tests (Playwright) against a real WordPress started with wp-env.
- `deploy.yml`: publishing a GitHub release `v<version>` runs `tests.yml` on that commit and, only when green, deploys to WordPress.org and attaches the zip to the release.
- Classes: `src/` (namespace `MonoRanks`, Composer PSR-4). Third-party libraries are listed under `extra.wp-scoper.packages`; [WP Scoper](https://github.com/veronalabs/wp-scoper) prefixes them into `packages/` under `MonoRanks\Deps` on `composer install`, so nothing collides with other plugins.
- Locally: `npm install`, `npx wp-env start` (Docker), `npx playwright test`. The site runs at http://localhost:8889 (admin / password).
- `.wordpress-org/`: banner, icon and screenshots for the directory listing.

License: GPL-2.0-or-later.
