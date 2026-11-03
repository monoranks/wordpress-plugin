<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Calls from this site to MonoRanks, authenticated with the website's connector key (Authorization: Bearer).
 * The key reaches this one website only: it sends its content, change alerts and status, and reads back its own data.
 */
class Api {

	/** @return array{code:int, body:array|null, error:string|null} */
	public static function post( $path, array $body, $timeout = 15 ) {
		$c = Connection::get();
		if ( empty( $c['key'] ) || empty( $c['api_base'] ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => 'not_connected' );
		}
		$res = wp_remote_post(
			$c['api_base'] . '/api/connector' . $path,
			array(
				'timeout'   => $timeout,
				'headers'   => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $c['key'], 'User-Agent' => 'MonoRanks Connector/' . MONORANKS_CONNECTOR_VERSION ),
				'body'      => wp_json_encode( $body ),
				'sslverify' => ! Connection::local(),
			)
		);
		if ( is_wp_error( $res ) ) {
			Connection::update( array( 'last_error' => $res->get_error_message() ) );
			return array( 'code' => 0, 'body' => null, 'error' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 401 === $code ) {
			self::refused( $c['key'] );
		} elseif ( $code >= 200 && $code < 300 ) {
			self::accepted( $c['key'], array( 'last_sent_at' => gmdate( 'c' ) ) );
		} else {
			Connection::update( array( 'last_error' => is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'MonoRanks answered ' . $code ) );
		}
		return array( 'code' => $code, 'body' => is_array( $json ) ? $json : null, 'error' => $code >= 200 && $code < 300 ? null : ( is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'http_' . $code ) );
	}

	/** GET with the same key; MonoRanks answers 404 not_supported on routes it does not have yet. @return array{code:int, body:array|null, error:string|null} */
	public static function get( $path, $timeout = 15 ) {
		$c = Connection::get();
		if ( empty( $c['key'] ) || empty( $c['api_base'] ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => 'not_connected' );
		}
		$res = wp_remote_get(
			$c['api_base'] . '/api/connector' . $path,
			array(
				'timeout'   => $timeout,
				'headers'   => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $c['key'], 'User-Agent' => 'MonoRanks Connector/' . MONORANKS_CONNECTOR_VERSION ),
				'sslverify' => ! Connection::local(),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 401 === $code ) {
			self::refused( $c['key'] );
		} elseif ( $code >= 200 && $code < 300 ) {
			self::accepted( $c['key'] );
		}
		return array( 'code' => $code, 'body' => is_array( $json ) ? $json : null, 'error' => $code >= 200 && $code < 300 ? null : ( is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'http_' . $code ) );
	}

	/**
	 * A connector route took the key: it is alive, whatever an earlier answer said. Without this a read that works never
	 * cleared the mark, and the screen said "Key revoked" next to fresh data.
	 */
	private static function accepted( $key, array $patch = array() ) {
		$now = Connection::fresh();
		if ( empty( $now['key'] ) || $now['key'] !== $key ) {
			return;
		}
		if ( $patch || ! empty( $now['last_error'] ) || ( isset( $now['key_state'] ) && 'ok' !== $now['key_state'] ) ) {
			Connection::update( array_merge( array( 'key_state' => 'ok', 'last_error' => null ), $patch ) );
		}
	}

	/**
	 * A connector route answered 401. That alone does not prove the key is gone: the answer may be for an older key (the site
	 * was connected again while this request ran), and these routes also say 401 when a key lacks that one route's scope.
	 */
	private static function refused( $key ) {
		$now = Connection::fresh();
		if ( empty( $now['key'] ) || $now['key'] !== $key ) {
			return;
		}
		Connection::update( self::key_is_dead() ? array( 'key_state' => 'revoked', 'last_error' => 'MonoRanks did not accept the key' ) : array( 'last_error' => 'This key cannot do this: it lacks a permission' ) );
	}

	/**
	 * API v1 tells the two apart: 401 for a key that is unknown or revoked, 403 for a live key without the scope. Where that
	 * API is off or this site's id is unknown the 401 has to stand. When MonoRanks cannot be reached nothing is proven.
	 */
	private static function key_is_dead() {
		if ( Connection::is_revoked() ) {
			return true;
		}
		$res = self::v1_get( 'scores', 6 );
		return 401 === $res['code'] || 'off' === self::v1_state( $res );
	}

	/**
	 * This website's id in MonoRanks: stored at pairing, else the one the overview carries (a key pasted by hand).
	 */
	public static function site_id() {
		$c = Connection::get();
		if ( ! empty( $c['site_id'] ) ) {
			return (string) $c['site_id'];
		}
		$o = Insights::overview();
		return $o && ! empty( $o['site_id'] ) ? (string) $o['site_id'] : '';
	}

	/**
	 * GET a read of MonoRanks API v1 for this website (`/api/v1/sites/<id>/<path>`), with the same key. Used for the
	 * Grow cards and the post panel; docs/api.md lists the routes and the scope each needs. `error` is the API's error
	 * code (missing_scope, api_disabled, no_route, ga4_not_connected …) or http_<code>. Never marks the key revoked: the
	 * connector routes decide that.
	 *
	 * @param string $path    Path after the site, with its query string, e.g. "backlinks?view=new&per=3".
	 * @param int    $timeout Seconds.
	 * @return array{code:int, body:array|null, error:string|null}
	 */
	public static function v1_get( $path, $timeout = 8 ) {
		$c    = Connection::get();
		$site = self::site_id();
		if ( empty( $c['key'] ) || empty( $c['api_base'] ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => 'not_connected' );
		}
		if ( '' === $site ) {
			return array( 'code' => 0, 'body' => null, 'error' => 'no_site_id' );
		}
		$res = wp_remote_get(
			$c['api_base'] . '/api/v1/sites/' . rawurlencode( $site ) . '/' . ltrim( (string) $path, '/' ),
			array(
				'timeout'   => $timeout,
				'headers'   => array( 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $c['key'], 'User-Agent' => 'MonoRanks Connector/' . MONORANKS_CONNECTOR_VERSION ),
				'sslverify' => ! Connection::local(),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => 'unreachable' );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$err  = null;
		if ( $code < 200 || $code >= 300 ) {
			$err = is_array( $json ) && isset( $json['error']['code'] ) && is_string( $json['error']['code'] ) ? $json['error']['code'] : 'http_' . $code;
		}
		return array( 'code' => $code, 'body' => is_array( $json ) ? $json : null, 'error' => $err );
	}

	/**
	 * What a failed v1 read means for a card: no_access (the key lacks the read scope), off (this MonoRanks has the API
	 * or the route switched off, or does not have it yet), or error (try again later).
	 */
	public static function v1_state( array $res ) {
		if ( 403 === $res['code'] && in_array( $res['error'], array( 'missing_scope', 'forbidden', 'no_owner', 'owner_left' ), true ) ) {
			return 'no_access';
		}
		if ( 'no_site_id' === $res['error'] || ( 503 === $res['code'] && 'api_disabled' === $res['error'] ) || ( 404 === $res['code'] && 'no_route' === $res['error'] ) ) {
			return 'off';
		}
		return 'error';
	}

	/** The values of seo_plugin that MonoRanks accepted before 0.1.15 ('tsf' and 'other' came with it). */
	const KNOWN_SEO_PLUGINS = array( 'yoast', 'rankmath', 'aioseo', 'none' );

	public static function send_status( $status = null ) {
		$status = null === $status ? Rest::status_payload() : $status;
		$res    = self::post( '/status', $status );
		// A MonoRanks app that does not know the new seo_plugin values yet refuses the whole status with 400. Send it
		// again as 'none' (seo_plugin_name still says which plugin it is) so connecting keeps working until it does.
		if ( 400 === $res['code'] && isset( $status['seo_plugin'] ) && ! in_array( $status['seo_plugin'], self::KNOWN_SEO_PLUGINS, true ) ) {
			$status['seo_plugin'] = 'none';
			$res                  = self::post( '/status', $status );
		}
		return $res;
	}
}
