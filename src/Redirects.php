<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/** Redirects written by MonoRanks (for example collapsing a redirect chain). Stored in one option, applied before WordPress routes. */
class Redirects {

	public static function all() {
		$r = get_option( 'monoranks_redirects', array() );
		return is_array( $r ) ? $r : array();
	}

	public static function get( $from ) {
		$all = self::all();
		return isset( $all[ self::key( $from ) ] ) ? $all[ self::key( $from ) ] : '';
	}

	public static function set( $from, $to ) {
		$all = self::all();
		if ( '' === $to ) {
			unset( $all[ self::key( $from ) ] );
		} else {
			$all[ self::key( $from ) ] = esc_url_raw( $to );
		}
		update_option( 'monoranks_redirects', $all, true );
		return true;
	}

	private static function key( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return untrailingslashit( $path ? $path : '/' );
	}

	public static function maybe_redirect() {
		$all = self::all();
		if ( ! $all || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$key = self::key( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $all[ $key ] ) ) {
			wp_redirect( $all[ $key ], 301, 'MonoRanks' ); // phpcs:ignore WordPress.Security.SafeRedirect -- target was approved by the site owner in MonoRanks
			exit;
		}
	}
}
