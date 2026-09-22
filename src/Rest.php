<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes under /wp-json/monoranks/v1, called by MonoRanks with the Application Password the owner approved.
 * Everything except ping needs an administrator. Data travels to MonoRanks separately, with the connector key.
 */
class Rest {

	const NS = 'monoranks/v1';

	public static function register() {
		register_rest_route( self::NS, '/ping', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'ping' ), 'permission_callback' => '__return_true' ) );
		$admin = array( __CLASS__, 'can_manage' );
		register_rest_route( self::NS, '/status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/pair', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'pair' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/unpair', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'unpair' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/sync', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'sync' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/apply', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'apply' ), 'permission_callback' => $admin ) );
		// The admin app's own routes (cookie + nonce through wp.apiFetch): what the screens read, and what their buttons do.
		register_rest_route( self::NS, '/admin/overview', array( 'methods' => 'GET', 'callback' => array( 'MonoRanks\\Overview', 'data' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/admin/settings', array( 'methods' => 'GET', 'callback' => array( 'MonoRanks\\Settings', 'data' ), 'permission_callback' => $admin ) );
		foreach ( array( 'apply', 'undo', 'sync', 'connect', 'disconnect' ) as $action ) {
			register_rest_route( self::NS, '/admin/' . $action, array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_' . $action ), 'permission_callback' => $admin ) );
		}
	}

	/** An action's outcome plus the refreshed screen data, so the app repaints without a second request. */
	private static function admin_result( $code, $screen ) {
		$notice = Admin::notice_for( $code );
		return array( 'ok' => $notice && 'success' === $notice['type'], 'code' => $code, 'notice' => $notice, 'data' => 'settings' === $screen ? Settings::data() : Overview::data() );
	}

	private static function screen( \WP_REST_Request $req ) {
		return 'settings' === $req->get_param( 'screen' ) ? 'settings' : 'overview';
	}

	public static function admin_apply( \WP_REST_Request $req ) {
		return self::admin_result( Actions::apply( sanitize_text_field( (string) $req->get_param( 'fix' ) ) ), 'overview' );
	}

	public static function admin_undo( \WP_REST_Request $req ) {
		$entry = $req->get_param( 'entry' );
		if ( null === $entry || '' === $entry ) {
			return new \WP_Error( 'monoranks_no_entry', 'entry is required', array( 'status' => 400 ) );
		}
		return self::admin_result( Actions::undo( (int) $entry, sanitize_text_field( (string) $req->get_param( 'at' ) ) ), self::screen( $req ) );
	}

	public static function admin_sync( \WP_REST_Request $req ) {
		return self::admin_result( Actions::send_now(), self::screen( $req ) );
	}

	public static function admin_connect( \WP_REST_Request $req ) {
		return self::admin_result( Actions::connect_key( sanitize_text_field( (string) $req->get_param( 'key' ) ), sanitize_text_field( (string) $req->get_param( 'api_base' ) ) ), 'settings' );
	}

	public static function admin_disconnect() {
		return self::admin_result( Actions::disconnect(), 'settings' );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function ping() {
		return array(
			'plugin'        => 'monoranks',
			'version'       => MONORANKS_CONNECTOR_VERSION,
			'app_passwords' => function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available(),
			'https'         => is_ssl() || 0 === strpos( home_url(), 'https://' ),
		);
	}

	/** What the plugin reports about itself, both here and when it sends its status with the key. No user data. */
	public static function status_payload() {
		$counts = array();
		foreach ( Content::post_types() as $type ) {
			$c               = wp_count_posts( $type );
			$counts[ $type ] = isset( $c->publish ) ? (int) $c->publish : 0;
		}
		return array(
			'site_url'   => home_url(),
			'name'       => get_bloginfo( 'name' ),
			'version'    => MONORANKS_CONNECTOR_VERSION,
			'wordpress'  => get_bloginfo( 'version' ),
			'seo_plugin' => SeoFields::plugin(),
			'post_types' => $counts,
			'can_write'  => Writer::FIELDS,
		);
	}

	public static function status() {
		$conn = Connection::get();
		$user = wp_get_current_user();
		return array_merge(
			self::status_payload(),
			array(
				'user'         => $user ? $user->user_login : null,
				'paired'       => Connection::has_key(),
				'key_state'    => Connection::has_key() ? ( isset( $conn['key_state'] ) ? $conn['key_state'] : 'ok' ) : 'none',
				'site_id'      => isset( $conn['site_id'] ) ? $conn['site_id'] : null,
				'last_sent_at' => isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : null,
			)
		);
	}

	/** MonoRanks hands over the website's connector key right after the owner approved the Application Password. */
	public static function pair( \WP_REST_Request $req ) {
		$base    = Connection::valid_base( $req->get_param( 'api_base' ) );
		$key     = Connection::valid_key( $req->get_param( 'key' ) );
		$site_id = sanitize_text_field( (string) $req->get_param( 'site_id' ) );
		if ( ! $base || ! $key ) {
			return new \WP_Error( 'monoranks_bad_pair', 'api_base (https) and a connector key are required', array( 'status' => 400 ) );
		}
		Connection::update( array( 'api_base' => $base, 'key' => $key, 'site_id' => $site_id, 'via' => 'pairing', 'key_state' => 'ok', 'paired_at' => gmdate( 'c' ), 'paired_by' => wp_get_current_user()->user_login, 'last_error' => null ) );
		Sync::schedule_daily();
		return self::status();
	}

	public static function unpair() {
		Sync::unschedule();
		Connection::clear();
		return array( 'paired' => false );
	}

	public static function sync() {
		return Sync::start();
	}

	public static function apply( \WP_REST_Request $req ) {
		$changes = $req->get_param( 'changes' );
		if ( ! is_array( $changes ) || count( $changes ) > 500 ) {
			return new \WP_Error( 'monoranks_bad_changes', 'changes must be a list of at most 500 items', array( 'status' => 400 ) );
		}
		$actor = sanitize_text_field( (string) $req->get_param( 'actor' ) );
		return array( 'results' => Writer::apply( $changes, $actor ? $actor : 'MonoRanks' ) );
	}
}
