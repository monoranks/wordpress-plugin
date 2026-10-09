#!/usr/bin/env bash
#
# Builds the installable plugin: dist/monoranks/ (the folder exactly as it ships) and dist/monoranks-<version>.zip
# containing a monoranks/ folder. WordPress names the installed folder after the folder inside the zip, so it is always
# named for the plugin slug, never for this repository: a "wordpress-plugin" folder installs as a second plugin.
#
#   1. composer install (dev) so WP Scoper writes packages/autoload.php, then composer install --no-dev so only
#      production packages are left,
#   2. npm ci + npm run build, and a check that the build matches the committed build/ (the zip ships build/ as
#      committed, so a stale bundle fails here instead of shipping),
#   3. copies everything not listed in .distignore and checks the files the plugin cannot run without.
#
# Usage: bin/build-zip.sh [--skip-install]
#   --skip-install  skip steps 1 and 2 and package the working tree as it is.

set -euo pipefail

cd "$(dirname "$0")/.."

SLUG=monoranks
VERSION=$(grep -oE 'Version:[[:space:]]+[0-9.]+' "$SLUG.php" | awk '{print $2}')

if [ "${1:-}" != "--skip-install" ]; then
	composer install --no-interaction --prefer-dist --no-progress
	composer dist --no-progress
	npm ci --no-audit --no-fund
	npm run build
	git diff --exit-code --stat -- build || { echo "build/ is out of date: run npm run build and commit it" >&2; exit 1; }
fi

# The plugin loads packages/autoload.php. With no third-party runtime packages WP Scoper may not write one, and a
# PSR-4 loader for src/ is all the plugin needs; a file WP Scoper did write wins because it is already there.
mkdir -p packages
[ -f packages/autoload.php ] || cp bin/autoload-template.php packages/autoload.php

rm -rf "dist/$SLUG" "dist/$SLUG-$VERSION.zip"
mkdir -p "dist/$SLUG"
rsync -a --exclude-from=.distignore ./ "dist/$SLUG/"

for f in "$SLUG.php" uninstall.php readme.txt packages/autoload.php \
	build/main.js build/main.css build/column.css \
	resources/views/app.php resources/views/column-cell.php resources/views/partials/score-ring.php \
	resources/assets/mark.svg resources/assets/logo.svg resources/assets/veronalabs.svg \
	languages/$SLUG.pot; do
	test -s "dist/$SLUG/$f" || { echo "The zip would miss $f" >&2; exit 1; }
done

(cd dist && zip -rq "$SLUG-$VERSION.zip" "$SLUG")
echo "dist/$SLUG-$VERSION.zip"
