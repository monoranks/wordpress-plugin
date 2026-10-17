<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The way back from the connect link when MonoRanks cannot reach this site (a local .test site, a firewall).
 *
 * The connect link carries a random state, kept per user for 30 minutes. When MonoRanks cannot approve the site itself, it
 * sends the person back to Settings with a one-time code and that state. The plugin checks the state (so nobody can link
 * this site to their own MonoRanks workspace by sending an admin a link), then trades the code for the website's
 * connector key with a direct request to MonoRanks. The key never travels in a URL.
 */
class Handoff {

	/** How long a connect link's state stays valid, in seconds. */
	const TTL = 1800;

	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_claim' ) );
	}

	private static function transient( $user_id ) {
		return 'monoranks_connect_' . (int) $user_id;
	}

	/**
	 * The state for the current user's connect link to $base, '' without a signed-in user. The same state is kept while it
	 * lives, so two open tabs show the same link; each call gives it another 30 minutes.
	 */
	public static function state( $base ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		$base  = untrailingslashit( (string) $base );
		$saved = get_transient( self::transient( $user_id ) );
		$state = is_array( $saved ) && ! empty( $saved['state'] ) && isset( $saved['base'] ) && $saved['base'] === $base ? (string) $saved['state'] : bin2hex( random_bytes( 20 ) );
		set_transient( self::transient( $user_id ), array( 'state' => $state, 'base' => $base ), self::TTL );
		return $state;
	}

	/** MonoRanks → Settings opened with ?monoranks_code=…&monoranks_state=…: claim the key, then show the clean page. */
	public static function maybe_claim() {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the state checked in redeem() plays the nonce's role
		if ( 'admin.php' !== $pagenow || ! isset( $_GET['page'], $_GET['monoranks_code'], $_GET['monoranks_state'] ) || Admin::SETTINGS !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$code  = sanitize_text_field( wp_unslash( $_GET['monoranks_code'] ) );
		$state = sanitize_text_field( wp_unslash( $_GET['monoranks_state'] ) );
		// phpcs:enable
		wp_safe_redirect( Admin::settings_url( self::claim( $code, $state ) ) );
		exit;
	}

	/** Redeem the code and connect with the key it brings. Returns a result code for Admin::notice_for(). */
	public static function claim( $code, $state ) {
		$got = self::redeem( $code, $state );
		if ( ! $got ) {
			return 'connect_failed';
		}
		return Actions::use_key( $got['key'], $got['base'], 'pairing', $got['site_id'] );
	}

	/**
	 * Check the state against the one stored for this user (then forget it) and trade the code for the key.
	 *
	 * @return array{key:string, site_id:string, base:string}|null
	 */
	public static function redeem( $code, $state ) {
		$user_id = get_current_user_id();
		$saved   = $user_id ? get_transient( self::transient( $user_id ) ) : false;
		if ( ! is_array( $saved ) || empty( $saved['state'] ) || ! is_string( $state ) || '' === $state || ! hash_equals( (string) $saved['state'], $state ) ) {
			return null;
		}
		delete_transient( self::transient( $user_id ) );
		$base = Connection::valid_base( isset( $saved['base'] ) ? $saved['base'] : MONORANKS_API_BASE );
		if ( ! $base || ! is_string( $code ) || ! preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $code ) ) {
			return null;
		}
		$res = wp_remote_post(
			$base . '/api/wordpress/claim',
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json', 'User-Agent' => 'MonoRanks Connector/' . MONORANKS_CONNECTOR_VERSION ),
				'body'        => wp_json_encode( array( 'code' => $code, 'site' => untrailingslashit( home_url( '/' ) ) ) ),
				'sslverify'   => ! Connection::local(),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$key  = Connection::valid_key( is_array( $body ) && isset( $body['key'] ) && is_string( $body['key'] ) ? $body['key'] : '' );
		if ( ! $key ) {
			return null;
		}
		$site_id = is_array( $body ) && isset( $body['site_id'] ) && is_string( $body['site_id'] ) ? sanitize_text_field( $body['site_id'] ) : '';
		return array( 'key' => $key, 'site_id' => $site_id, 'base' => $base );
	}
}
