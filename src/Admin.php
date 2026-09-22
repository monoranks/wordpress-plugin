<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The MonoRanks admin menu (Overview, Settings), the stylesheet, the view loader and the form handlers behind the buttons.
 * Screens gather their data in Overview and Settings and hand it to a template under views/; no HTML lives in classes.
 */
class Admin {

	const MENU     = 'monoranks';
	const SETTINGS = 'monoranks-settings';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'textdomain' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'legacy_url' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_monoranks_connect_key', array( __CLASS__, 'connect_key' ) );
		add_action( 'admin_post_monoranks_send_now', array( __CLASS__, 'send_now' ) );
		add_action( 'admin_post_monoranks_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_monoranks_apply', array( 'MonoRanks\\Overview', 'apply' ) );
		add_action( 'admin_post_monoranks_undo', array( 'MonoRanks\\Overview', 'undo' ) );
	}

	public static function textdomain() {
		load_plugin_textdomain( 'monoranks', false, dirname( plugin_basename( MONORANKS_CONNECTOR_FILE ) ) . '/languages' );
	}

	public static function menu() {
		add_menu_page( 'MonoRanks', 'MonoRanks', 'manage_options', self::MENU, array( 'MonoRanks\\Overview', 'render' ), self::menu_icon(), 58 );
		add_submenu_page( self::MENU, __( 'Overview', 'monoranks' ), __( 'Overview', 'monoranks' ), 'manage_options', self::MENU, array( 'MonoRanks\\Overview', 'render' ) );
		add_submenu_page( self::MENU, __( 'MonoRanks settings', 'monoranks' ), __( 'Settings', 'monoranks' ), 'manage_options', self::SETTINGS, array( 'MonoRanks\\Settings', 'render' ) );
	}

	/** The MonoRanks mark, as a data URI so the menu shows it in the admin colour scheme. */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 19V7l6 6 6-6v12"/><path d="M18 9h3M18 13h3"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon
	}

	/** Settings → MonoRanks used to live under options-general.php; old links and the browser tests still open it. */
	public static function legacy_url() {
		global $pagenow;
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && self::MENU === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect only
			$notice = isset( $_GET['monoranks'] ) ? sanitize_key( wp_unslash( $_GET['monoranks'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( self::settings_url( $notice ) );
			exit;
		}
	}

	public static function settings_url( $notice = '' ) {
		$url = admin_url( 'admin.php?page=' . self::SETTINGS );
		return $notice ? add_query_arg( 'monoranks', rawurlencode( $notice ), $url ) : $url;
	}

	public static function overview_url() {
		return admin_url( 'admin.php?page=' . self::MENU );
	}

	public static function assets( $hook ) {
		$ours = in_array( $hook, array( 'toplevel_page_' . self::MENU, 'monoranks_page_' . self::SETTINGS, 'edit.php' ), true );
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_style( 'monoranks-admin', plugins_url( 'assets/admin.css', MONORANKS_CONNECTOR_FILE ), array(), MONORANKS_CONNECTOR_VERSION );
	}

	/** light or dark. WordPress admin is light; a theme or plugin can switch the MonoRanks screens with the filter. */
	public static function theme() {
		$theme = apply_filters( 'monoranks_admin_theme', 'light' );
		return 'dark' === $theme ? 'dark' : 'light';
	}

	/** Renders views/<name>.php with $data extracted into scope. Returns the HTML. */
	public static function view( $name, array $data = array() ) {
		$file = dirname( MONORANKS_CONNECTOR_FILE ) . '/views/' . $name . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		ob_start();
		( static function ( $monoranks_view_file, $monoranks_view_data ) {
			extract( $monoranks_view_data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables
			include $monoranks_view_file;
		} )( $file, $data );
		return ob_get_clean();
	}

	/** A score ring (the app's ScoreBox): 'sm' 30px in lists, 'lg' 64px on tiles. */
	public static function ring( $score, $size = 'sm', $label = '' ) {
		return self::view( 'partials/score-ring', array( 'score' => $score, 'size' => 'lg' === $size ? 'lg' : 'sm', 'label' => $label, 'tone' => Insights::tone( $score ) ) );
	}

	/** "3 minutes ago (22 Sep 2026, 14:03)" from an ISO date, or the fallback. */
	public static function ago( $iso, $fallback = '' ) {
		$ts = $iso ? strtotime( (string) $iso ) : 0;
		if ( ! $ts ) {
			return $fallback;
		}
		$when = wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $ts );
		if ( $ts > time() ) {
			/* translators: 1: relative time, 2: date */
			return sprintf( __( 'in %1$s (%2$s)', 'monoranks' ), human_time_diff( time(), $ts ), $when );
		}
		/* translators: 1: relative time, 2: date */
		return sprintf( __( '%1$s ago (%2$s)', 'monoranks' ), human_time_diff( $ts, time() ), $when );
	}

	/** The notice for the ?monoranks=<code> query argument set by the handlers below. */
	public static function notice() {
		$msg = isset( $_GET['monoranks'] ) ? sanitize_key( wp_unslash( $_GET['monoranks'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		$map = array(
			'connected'   => array( 'success', __( 'Connected. Your published content is on its way to MonoRanks.', 'monoranks' ) ),
			'sent'        => array( 'success', __( 'Content sent to MonoRanks.', 'monoranks' ) ),
			'applied'     => array( 'success', __( 'Applied. The change is listed under Recent changes and can be undone.', 'monoranks' ) ),
			'undone'      => array( 'success', __( 'Undone. The previous value is back.', 'monoranks' ) ),
			'apply_failed' => array( 'error', __( 'MonoRanks could not apply that change: the page changed since it was reviewed. Open it in MonoRanks to review again.', 'monoranks' ) ),
			'bad_key'     => array( 'error', __( 'That does not look like a MonoRanks connector key. Copy it again from MonoRanks → Settings → API and MCP.', 'monoranks' ) ),
			'bad_address' => array( 'error', __( 'The MonoRanks address must start with https://.', 'monoranks' ) ),
			'rejected'    => array( 'error', __( 'MonoRanks did not accept this key. It may have been revoked or replaced; create a new one in MonoRanks.', 'monoranks' ) ),
			'other_site'  => array( 'error', __( 'This key belongs to a different website in MonoRanks. Create the key on the website with this address.', 'monoranks' ) ),
			'unreachable' => array( 'error', __( 'MonoRanks could not be reached. Check the address and try again.', 'monoranks' ) ),
		);
		return isset( $map[ $msg ] ) ? array( 'type' => $map[ $msg ][0], 'text' => $map[ $msg ][1] ) : null;
	}

	/** The address field is only for development sites pointing the plugin at a local MonoRanks. */
	public static function shows_address_field() {
		return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	public static function back( $code, $to = 'settings' ) {
		wp_safe_redirect( 'overview' === $to ? add_query_arg( 'monoranks', rawurlencode( $code ), self::overview_url() ) : self::settings_url( $code ) );
		exit;
	}

	public static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed', 'monoranks' ) );
		}
		check_admin_referer( $nonce );
	}

	public static function connect_key() {
		self::guard( 'monoranks_connect_key' ); // Capability and nonce checked here.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard() above.
		$key  = Connection::valid_key( isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '' );
		$base = isset( $_POST['api_base'] ) && self::shows_address_field() ? sanitize_text_field( wp_unslash( $_POST['api_base'] ) ) : MONORANKS_API_BASE;
		// phpcs:enable
		$base = Connection::valid_base( $base );
		if ( ! $key ) {
			self::back( 'bad_key' );
		}
		if ( ! $base ) {
			self::back( 'bad_address' );
		}
		$previous = Connection::get();
		Connection::update( array( 'api_base' => $base, 'key' => $key, 'via' => 'manual', 'key_state' => 'ok', 'paired_at' => gmdate( 'c' ), 'paired_by' => wp_get_current_user()->user_login ) );
		$res = Api::send_status();
		if ( 200 !== $res['code'] ) {
			$previous ? update_option( 'monoranks_connection', $previous, false ) : Connection::clear();
			self::back( 401 === $res['code'] ? 'rejected' : ( 403 === $res['code'] ? 'other_site' : 'unreachable' ) );
		}
		if ( isset( $res['body']['site_id'] ) ) {
			Connection::update( array( 'site_id' => sanitize_text_field( (string) $res['body']['site_id'] ) ) );
		}
		Sync::start();
		Insights::refresh();
		self::back( 'connected' );
	}

	public static function send_now() {
		self::guard( 'monoranks_send_now' );
		Api::send_status();
		Sync::start();
		Insights::refresh();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard() above.
		self::back( 'sent', isset( $_POST['back'] ) && 'overview' === $_POST['back'] ? 'overview' : 'settings' );
	}

	public static function disconnect() {
		self::guard( 'monoranks_disconnect' );
		Sync::unschedule();
		Connection::clear();
		Insights::clear();
		wp_safe_redirect( self::settings_url() );
		exit;
	}
}
