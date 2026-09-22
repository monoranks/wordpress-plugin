#!/usr/bin/env bash
# Rebuilds the translation catalogue: languages/monoranks.pot from PHP, views and the admin app's sources, and one JSON
# per translated locale for the app (languages/monoranks-<locale>-admin.json, loaded by Assets::translation_file()).
# WP-CLI's extractor reads JavaScript but not TSX, so the app's sources are transpiled to a scratch folder first.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf tmp/i18n && mkdir -p tmp/i18n
find resources/admin/src -name '*.tsx' -o -name '*.ts' | xargs npx esbuild --outdir=tmp/i18n --format=esm --jsx=preserve --log-level=warning
wp i18n make-pot . languages/monoranks.pot --include=monoranks.php,uninstall.php,src,resources/views,tmp/i18n \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/monoranks/wordpress-plugin/issues"}'
python3 bin/po-sync.py
for po in languages/monoranks-*.po; do
  locale=$(basename "$po" .po | sed 's/^monoranks-//')
  wp i18n make-mo "$po" languages/
  rm -rf tmp/i18n-json && mkdir -p tmp/i18n-json
  wp i18n make-json "$po" tmp/i18n-json --no-purge --pretty-print >/dev/null
  php -r '
    $out = null;
    foreach (glob($argv[1] . "/*.json") as $f) {
      $j = json_decode(file_get_contents($f), true);
      if (!$out) { $out = $j; $out["source"] = "resources/admin"; continue; }
      $out["locale_data"]["messages"] += $j["locale_data"]["messages"];
    }
    if ($out) { file_put_contents($argv[2], json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"); }
  ' tmp/i18n-json "languages/monoranks-$locale-admin.json"
done
rm -rf tmp/i18n tmp/i18n-json
echo "languages/ updated"
