<?php
defined( 'ABSPATH' ) || exit;

/**
 * The connection to MonoRanks, stored in one option (not autoloaded):
 * api_base, key (the website's connector key), site_id, via (pairing | manual), key_state (ok | revoked), paired_at, paired_by,
 * last_sent_at, last_error.
 */
class MonoRanks_Connection {

	public static function get() {
		$c = get_option( 'monoranks_connection', array() );
		return is_array( $c ) ? $c : array();
	}

	public static function has_key() {
		$c = self::get();
		return ! empty( $c['key'] ) && ! empty( $c['api_base'] );
	}

	public static function update( array $patch ) {
		$c = array_merge( self::get(), $patch );
		update_option( 'monoranks_connection', $c, false );
		return $c;
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
		return preg_match( '/^mr_site_[A-Za-z0-9_-]{20,80}$/', $key ) ? $key : '';
	}

	public static function local() {
		return function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
	}
}
