<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/** What MonoRanks → Settings shows: connection state, the connector key, last sync, what is sent, recent changes. Answered as JSON. */
class Settings {

	/** Whether the plugin has anything of its own left in this WordPress (what the danger zone would erase). */
	public static function has_data() {
		foreach ( array( 'monoranks_connection', 'monoranks_insights', 'monoranks_change_log', 'monoranks_redirects', 'monoranks_ai_bots', 'monoranks_llms_txt' ) as $option ) {
			if ( false !== get_option( $option, false ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The one-link connect page in MonoRanks: it signs the person in, has them approve, and sends them back here.
	 * {base}/connect/wordpress?site=<home URL without the trailing slash>&return=<this settings page>&state=<per-user state>.
	 * The state lets MonoRanks hand the key back through Settings when it cannot reach this site (Handoff).
	 */
	public static function connect_url( $base = '' ) {
		$base  = untrailingslashit( $base ? (string) $base : MONORANKS_API_BASE );
		$state = Handoff::state( $base );
		return $base . '/connect/wordpress?site=' . rawurlencode( untrailingslashit( home_url( '/' ) ) ) . '&return=' . rawurlencode( Admin::settings_url() ) . ( $state ? '&state=' . $state : '' );
	}

	/**
	 * The Application Password MonoRanks writes fixes with, if one exists. MonoRanks' approve step creates it for the admin
	 * who approves, so every user who can manage options is checked, the current one first. A site connected with a one-time
	 * code or a pasted key has none until someone approves.
	 *
	 * @return array{exists:bool, revoke_url:string, available:bool}
	 */
	public static function app_password() {
		$available = function_exists( 'wp_is_application_passwords_available' ) ? (bool) wp_is_application_passwords_available() : false;
		$none      = array( 'exists' => false, 'revoke_url' => '', 'available' => $available );
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return $none;
		}
		$me    = get_current_user_id();
		$users = array_map( 'intval', (array) get_users( array( 'capability' => 'manage_options', 'fields' => 'ID', 'number' => 100 ) ) );
		usort( $users, static function ( $a, $b ) use ( $me ) { return (int) ( $b === $me ) - (int) ( $a === $me ); } );
		foreach ( $users as $user_id ) {
			foreach ( (array) \WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
				if ( self::is_monoranks_password( $item ) ) {
					$url = $user_id === $me ? admin_url( 'profile.php' ) : admin_url( 'user-edit.php?user_id=' . $user_id );
					return array( 'exists' => true, 'revoke_url' => $url . '#application-passwords-section', 'available' => $available );
				}
			}
		}
		return $none;
	}

	/** One Application Password is MonoRanks': made with MonoRanks' app id, or (older approvals) named MonoRanks. */
	public static function is_monoranks_password( $item ) {
		if ( ! is_array( $item ) ) {
			return false;
		}
		if ( ! empty( $item['app_id'] ) && is_string( $item['app_id'] ) ) {
			return 0 === strcasecmp( $item['app_id'], AuthorizeScreen::APP_ID );
		}
		return isset( $item['name'] ) && is_string( $item['name'] ) && 0 === strcasecmp( trim( $item['name'] ), 'MonoRanks' );
	}

	/**
	 * Where "Allow" goes when fixes are not set up: MonoRanks' approve step for this website ({base}/api/wordpress/start),
	 * which opens WordPress's own approve screen and comes back to Settings. A site connected with a one-time code or a key
	 * is already connected in MonoRanks, so the connect page would only say so. Without the website's id: the connect link.
	 */
	public static function allow_url( $base = '' ) {
		$conn    = Connection::get();
		$base    = untrailingslashit( $base ? (string) $base : ( ! empty( $conn['api_base'] ) ? $conn['api_base'] : MONORANKS_API_BASE ) );
		$site_id = isset( $conn['site_id'] ) ? (string) $conn['site_id'] : '';
		if ( '' === $site_id ) {
			return self::connect_url( $base );
		}
		$state = Handoff::state( $base );
		$query = array( 'site' => $site_id, 'wp' => untrailingslashit( home_url( '/' ) ), 'connect' => '1', 'return' => Admin::settings_url() );
		if ( $state ) {
			$query['state'] = $state;
		}
		return $base . '/api/wordpress/start?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	public static function data() {
		$conn      = Connection::get();
		$connected = Connection::has_key();
		$revoked   = $connected && isset( $conn['key_state'] ) && 'revoked' === $conn['key_state'];
		$sync      = get_option( 'monoranks_sync', array() );
		$overview  = Insights::overview();
		return array(
			'connected'    => $connected,
			'revoked'      => $revoked,
			'has_data'     => self::has_data(),
			'state'        => $revoked ? 'revoked' : ( $connected ? 'connected' : 'none' ),
			'key_hint'     => $connected ? substr( $conn['key'], 0, 12 ) . '…' : '',
			'key_via'      => $connected && 'pairing' === ( isset( $conn['via'] ) ? $conn['via'] : '' )
				/* translators: 1: user, 2: date */
				? Admin::digits( sprintf( __( 'set when %1$s connected MonoRanks on %2$s', 'monoranks' ), $conn['paired_by'], wp_date( get_option( 'date_format' ), strtotime( $conn['paired_at'] ) ) ) )
				: __( 'pasted by hand', 'monoranks' ),
			'api_base'     => $connected ? $conn['api_base'] : MONORANKS_API_BASE,
			'last_sent'    => Admin::ago( isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '', __( 'Never', 'monoranks' ) ),
			'last_sent_short' => Admin::ago_short( isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '', __( 'Never', 'monoranks' ) ),
			'last_sent_date'  => Admin::date_of( isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '' ),
			'last_error'   => isset( $conn['last_error'] ) ? (string) $conn['last_error'] : '',
			'sending'      => ! empty( $sync['run_id'] ) ? array( 'page' => (int) $sync['page'], 'pages' => (int) $sync['pages'] ) : null,
			'items'        => array_sum( Rest::status_payload()['post_types'] ),
			'next_audit'   => $overview && ! empty( $overview['next_audit_at'] ) ? Admin::ago( $overview['next_audit_at'] ) : '',
			'seo_plugin'   => SeoFields::plugin_name(),
			'seo_supported' => SeoFields::supported(),
			'profile_url'  => admin_url( 'profile.php#application-passwords-section' ),
			'app_password' => $connected ? self::app_password() : array( 'exists' => false, 'revoke_url' => '', 'available' => false ),
			'allow_url'    => $connected ? self::allow_url( $conn['api_base'] ) : '',
			'show_address' => Admin::shows_address_field(),
			'app_url'      => $connected ? $conn['api_base'] : MONORANKS_API_BASE,
			'connect_url'  => self::connect_url( $connected ? $conn['api_base'] : MONORANKS_API_BASE ),
			'log'          => Overview::log_rows( 50 ),
			'labels'       => Overview::field_labels(),
		);
	}
}
