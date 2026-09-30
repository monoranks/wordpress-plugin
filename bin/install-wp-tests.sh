#!/usr/bin/env bash
#
# Installs a throwaway WordPress site for the integration tests (tests/integration), which load a real WordPress
# through wp-load.php rather than the WordPress test library. Needs WP-CLI (`wp`) and a reachable MySQL or MariaDB.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
#   db-host     host or host:port (default: 127.0.0.1)
#   wp-version  X.Y, X.Y.Z or "latest" (default: latest)
#
# The site goes to WP_CORE_DIR (default /tmp/wordpress); an existing folder is reused, so delete it to reinstall
# another version. The database is emptied and installed again on every run. Then run:
#
#   MONORANKS_WP_PATH=/tmp/wordpress composer test:integration

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]" >&2
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-127.0.0.1}
WP_VERSION=${5:-latest}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}
WP_CORE_DIR=${WP_CORE_DIR%/}

wp() {
	command wp --path="$WP_CORE_DIR" --allow-root "$@"
}

if [ -f "$WP_CORE_DIR/wp-load.php" ]; then
	echo "Reusing WordPress in $WP_CORE_DIR"
else
	echo "Downloading WordPress $WP_VERSION into $WP_CORE_DIR"
	mkdir -p "$WP_CORE_DIR"
	wp core download --version="$WP_VERSION" --force
fi

rm -f "$WP_CORE_DIR/wp-config.php"
wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --skip-check

echo "Waiting for the database at $DB_HOST"
for _ in $(seq 1 30); do
	if wp db check > /dev/null 2>&1 || wp db create > /dev/null 2>&1; then
		break
	fi
	sleep 1
done
wp db reset --yes
wp core install --url=http://localhost --title="MonoRanks tests" --admin_user=admin --admin_password=admin \
	--admin_email=admin@example.com --skip-email

echo "WordPress $(wp core version) is ready. Run: MONORANKS_WP_PATH=$WP_CORE_DIR composer test:integration"
