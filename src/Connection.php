<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The connection to MonoRanks, stored in one option (not autoloaded):
 * api_base, key (the website's connector key), site_id, via (pairing | manual), key_state (ok | revoked), paired_at, paired_by,
 * last_sent_at, last_error.
 */
class Connection {

	public static function get() {
		$c = get_option( 'monoranks_connection', array() );
		return is_array( $c ) ? $c : array();
	}

	public static function has_key() {
		$c = self::get();
		return ! empty( $c['key'] ) && ! empty( $c['api_base'] );
	}

	/**
	 * The connection as the database holds it now. A long request (a content sync in WP-Cron) keeps its first read in memory,
	 * and the site may have been connected again since.
	 */
	public static function fresh() {
		wp_cache_delete( 'monoranks_connection', 'options' );
		return self::get();
	}

	/** Saves on top of the stored connection, so a request that started earlier never brings an old key back or a removed one. */
	public static function update( array $patch ) {
		$now = self::fresh();
		if ( empty( $now['key'] ) && empty( $patch['key'] ) ) {
			return $now;
		}
		$c = array_merge( $now, $patch );
		update_option( 'monoranks_connection', $c, false );
		return $c;
	}

	/** MonoRanks no longer accepts the stored key. */
	public static function is_revoked() {
		$c = self::get();
		return self::has_key() && isset( $c['key_state'] ) && 'revoked' === $c['key_state'];
	}

	public static function clear() {
		delete_option( 'monoranks_connection' );
		delete_option( 'monoranks_sync' );
	}

	/** HTTPS only, except for sites marked as local development. */
	public static function valid_base( $url ) {
		$url = esc_url_raw( untrailingslashit( (string) $url ) );
		if ( ! $url ) {
			return '';
		}
		if ( 0 !== strpos( $url, 'https://' ) && ! self::local() ) {
			return '';
		}
		return $url;
	}

	public static function valid_key( $key ) {
		$key = trim( (string) $key );
		// A website key (mr_site_…) or a workspace key ticked for this one website (mr_ws_…, MonoRanks → Settings → API and MCP).
		return preg_match( '/^mr_(site|ws)_[A-Za-z0-9_-]{20,80}$/', $key ) ? $key : '';
	}

	public static function local() {
		return function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
	}
}
