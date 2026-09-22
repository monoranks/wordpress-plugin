# MonoRanks for WordPress

The WordPress plugin for [MonoRanks](https://monoranks.com): SEO, AEO and GEO audits every week, fixes explained in plain words, and the ones you approve written into WordPress with undo.

- Sends published content metadata so audits and rechecks stay current (never drafts, comments or credentials).
- Applies approved fixes: titles, meta descriptions, headings, image alt text, canonical, noindex, redirects, an answer-first opening paragraph, AI crawler rules in robots.txt, llms.txt.
- Works next to Yoast SEO, Rank Math and All in One SEO.
- Shows the audit inside WordPress: a MonoRanks menu with an Overview (site health and AEO scores, search clicks, pages needing attention, fixes ready to apply, recent changes with Undo) and Settings, plus a MonoRanks column with each page's scores in Posts and Pages.
- Every write is logged under MonoRanks → Settings and can be undone.

Full description, what is sent and the FAQ: `readme.txt` (the WordPress.org listing).

## Development

This repository is the plugin's home. The MonoRanks monorepo includes it as a git submodule (`packages/wp-plugin/monoranks`) for its own end-to-end tests and the in-app download.

- `tests.yml`: the suite, on every pull request — PHP syntax on 7.4, 8.1 and 8.3; the admin app (`npm run typecheck`, `npm run build`, and the committed `assets/build/` must match); unit tests (PHPUnit + Brain Monkey, no WordPress needed: `composer install && vendor/bin/phpunit`); the WordPress.org plugin checker; a version consistency check (header, constant, readme, changelog); Chromium tests (Playwright) against a real WordPress started with wp-env.
- `deploy.yml`: publishing a GitHub release `v<version>` runs `tests.yml` on that commit and, only when green, deploys to WordPress.org and attaches the zip to the release.
- Classes: `src/` (namespace `MonoRanks`, Composer PSR-4). The Overview and Settings screens are a React app in `resources/admin/` (Vite, Tailwind 4, shadcn-style components, the same stack as WConvert; `@wordpress/i18n` and `@wordpress/api-fetch` come from WordPress) that reads and acts through `monoranks/v1/admin/*` REST routes; `npm run build` writes `assets/build/`, which is committed so every checkout works without Node. The Posts and Pages column stays server-rendered (`resources/views/`, `assets/column.css`).
- Translations: `bin/i18n.sh` rebuilds `languages/monoranks.pot` from PHP and the app's sources, syncs the `.po` files (`bin/po-sync.py` keeps translations, prints what is missing), and writes `.mo` plus the JSON catalogue the app loads (`monoranks-<locale>-admin.json`).
- `docs/api.md`: what the plugin reads from MonoRanks with the connector key (overview, per-page scores, applied-fix reports) and how it caches it. Third-party libraries are listed under `extra.wp-scoper.packages`; [WP Scoper](https://github.com/veronalabs/wp-scoper) prefixes them into `packages/` under `MonoRanks\Deps` on `composer install`, so nothing collides with other plugins.
- Locally: `npm install`, `npx wp-env start` (Docker), `npx playwright test`. The site runs at http://localhost:8889 (admin / password).
- `.wordpress-org/`: banner, icon and screenshots for the directory listing.
- What ships in the zip (`.distignore` lists what does not): `monoranks.php`, `uninstall.php`, `readme.txt`, `src/`, `resources/views/`, `assets/` (the column stylesheet, the SVGs and the built app in `assets/build/`), `languages/` (`.mo`, `.po`, `.pot` and the app's JSON catalogues) and `vendor/` with only the autoloader (`composer install --no-dev`). The app's sources under `resources/admin/`, tests, docs and tooling stay out.

License: GPL-2.0-or-later.
