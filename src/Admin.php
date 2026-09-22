<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The MonoRanks admin menu (Overview, Settings). Both screens are the React app in resources/admin (built into
 * build/, enqueued by Assets); PHP only prints the mount and the settings the app reads, and answers its REST
 * calls (Rest, Actions). The Posts and Pages column stays server-rendered (Column, resources/views/).
 */
class Admin {

	const MENU     = 'monoranks';
	const SETTINGS = 'monoranks-settings';

	/**
	 * The screen hooks WordPress gave the two pages, kept because they are not guessable: a submenu's hook is built from
	 * the parent's menu title, which is translated, so "monoranks_page_…" is only right in English.
	 *
	 * @var array<string,string>
	 */
	private static $hooks = array();

	public static function register() {
		add_filter( 'load_textdomain_mofile', array( __CLASS__, 'bundled_mofile' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'legacy_url' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 100 );
		add_filter( 'update_footer', array( __CLASS__, 'footer_text' ), 100 );
	}

	/**
	 * WordPress loads this plugin's translations on its own (from wp-content/languages/plugins, where language packs from
	 * translate.wordpress.org land). Until a language pack exists, the copy bundled under languages/ stands in.
	 */
	public static function bundled_mofile( $mofile, $domain ) {
		if ( 'monoranks' !== $domain || file_exists( $mofile ) ) {
			return $mofile;
		}
		$bundled = dirname( MONORANKS_CONNECTOR_FILE ) . '/languages/' . basename( $mofile );
		return file_exists( $bundled ) ? $bundled : $mofile;
	}

	public static function menu() {
		self::$hooks['overview'] = (string) add_menu_page( __( 'MonoRanks', 'monoranks' ), __( 'MonoRanks', 'monoranks' ), 'manage_options', self::MENU, array( __CLASS__, 'mount' ), self::menu_icon(), 58 );
		add_submenu_page( self::MENU, __( 'Overview', 'monoranks' ), __( 'Overview', 'monoranks' ), 'manage_options', self::MENU, array( __CLASS__, 'mount' ) );
		self::$hooks['settings'] = (string) add_submenu_page( self::MENU, __( 'MonoRanks settings', 'monoranks' ), __( 'Settings', 'monoranks' ), 'manage_options', self::SETTINGS, array( __CLASS__, 'mount' ) );
	}

	/** The screen hook of one of the plugin's pages, or '' before admin_menu has run. */
	public static function hook( $page ) {
		return isset( self::$hooks[ $page ] ) ? self::$hooks[ $page ] : '';
	}

	/** overview | settings for one of the plugin's screen hooks, or '' for anything else. */
	public static function screen_for_hook( $hook ) {
		foreach ( self::$hooks as $page => $known ) {
			if ( $known && $known === $hook ) {
				return $page;
			}
		}
		return '';
	}

	/** Where the app renders. Without a build (a checkout that skipped `npm run build`) it says what to run. */
	public static function mount() {
		echo self::view( 'app', array( 'theme' => self::theme(), 'built' => file_exists( dirname( MONORANKS_CONNECTOR_FILE ) . '/build/main.js' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes
	}

	/** The MonoRanks mark (resources/assets/mark.svg) as a data URI; WordPress repaints its fill in the admin colour scheme. */
	private static function menu_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode( self::asset( 'mark.svg' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon
	}

	/** The contents of one of the plugin's own SVG files under resources/assets/, for inlining (so currentColor applies). */
	public static function asset( $file ) {
		$path = dirname( MONORANKS_CONNECTOR_FILE ) . '/resources/assets/' . $file;
		return file_exists( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plugin's own file
	}

	/** The plugin's screens carry one class of their own, so the stylesheet does not depend on a translated hook name. */
	public static function body_class( $classes ) {
		return self::is_own_screen() ? $classes . ' monoranks-screen' : $classes;
	}

	/** True on the plugin's own screens (Overview, Settings). */
	public static function is_own_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && '' !== self::screen_for_hook( $screen->id );
	}

	/** The plugin's screens carry their own footer, so WordPress's "Thank you for creating with WordPress" line steps aside. */
	public static function footer_text( $text ) {
		return self::is_own_screen() ? '' : $text;
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

	/** light or dark. WordPress admin is light; a theme or plugin can switch the MonoRanks screens with the filter. */
	public static function theme() {
		$theme = apply_filters( 'monoranks_admin_theme', 'light' );
		return 'dark' === $theme ? 'dark' : 'light';
	}

	/** Renders resources/views/<name>.php with $data extracted into scope. Returns the HTML. */
	public static function view( $name, array $data = array() ) {
		$file = dirname( MONORANKS_CONNECTOR_FILE ) . '/resources/views/' . $name . '.php';
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

	/** A score ring for the Posts column (the app's ScoreBox): 'sm' 30px in the cell, 'md' 40px in its tooltip. */
	public static function ring( $score, $size = 'sm', $label = '' ) {
		return self::view( 'partials/score-ring', array( 'score' => $score, 'size' => in_array( $size, array( 'md', 'lg' ), true ) ? $size : 'sm', 'label' => $label, 'tone' => Insights::tone( $score ) ) );
	}

	/** One word for a score, next to the ring in the column's tooltip. */
	public static function tone_word( $score ) {
		switch ( Insights::tone( $score ) ) {
			case 'good':
				return __( 'good', 'monoranks' );
			case 'warn':
				return __( 'needs work', 'monoranks' );
			case 'critical':
				return __( 'low', 'monoranks' );
		}
		return __( 'not scored', 'monoranks' );
	}

	/**
	 * Digits in the admin's own script: Persian for fa_*, Arabic-Indic for ar_*, Latin elsewhere. WordPress formats
	 * numbers and dates with Latin digits; the screens pass everything they print through here.
	 */
	public static function digits( $text ) {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$sets   = array( 'fa' => '۰۱۲۳۴۵۶۷۸۹', 'ar' => '٠١٢٣٤٥٦٧٨٩' );
		$lang   = substr( (string) $locale, 0, 2 );
		if ( ! isset( $sets[ $lang ] ) ) {
			return (string) $text;
		}
		return strtr( (string) $text, array_combine( str_split( '0123456789' ), preg_split( '//u', $sets[ $lang ], -1, PREG_SPLIT_NO_EMPTY ) ) );
	}

	/** A number for the screen: thousands separator from the locale, local digits. */
	public static function n( $number ) {
		return self::digits( number_format_i18n( $number ) );
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
			return self::digits( sprintf( __( 'in %1$s (%2$s)', 'monoranks' ), human_time_diff( time(), $ts ), $when ) );
		}
		/* translators: 1: relative time, 2: date */
		return self::digits( sprintf( __( '%1$s ago (%2$s)', 'monoranks' ), human_time_diff( $ts, time() ), $when ) );
	}

	/** The notice for a result code (the app shows it after an action; the legacy URL carries one as ?monoranks=<code>). */
	public static function notice_for( $code ) {
		$map = array(
			'connected'    => array( 'success', __( 'Connected. Your published content is on its way to MonoRanks.', 'monoranks' ) ),
			'sent'         => array( 'success', __( 'Content sent to MonoRanks.', 'monoranks' ) ),
			'disconnected' => array( 'success', __( 'Disconnected. Nothing is sent to MonoRanks any more.', 'monoranks' ) ),
			'applied'      => array( 'success', __( 'Applied. The change is listed under Recent changes and can be undone.', 'monoranks' ) ),
			'undone'       => array( 'success', __( 'Undone. The previous value is back.', 'monoranks' ) ),
			'apply_failed' => array( 'error', __( 'MonoRanks could not apply that change: the page changed since it was reviewed. Open it in MonoRanks to review again.', 'monoranks' ) ),
			'bad_key'      => array( 'error', __( 'That does not look like a MonoRanks connector key. Copy it again from MonoRanks → Settings → API and MCP.', 'monoranks' ) ),
			'bad_address'  => array( 'error', __( 'The MonoRanks address must start with https://.', 'monoranks' ) ),
			'rejected'     => array( 'error', __( 'MonoRanks did not accept this key. It may have been revoked or replaced; create a new one in MonoRanks.', 'monoranks' ) ),
			'other_site'   => array( 'error', __( 'This key belongs to a different website in MonoRanks. Create the key on the website with this address.', 'monoranks' ) ),
			'unreachable'  => array( 'error', __( 'MonoRanks could not be reached. Check the address and try again.', 'monoranks' ) ),
		);
		return isset( $map[ $code ] ) ? array( 'type' => $map[ $code ][0], 'text' => $map[ $code ][1] ) : null;
	}

	public static function notice() {
		$code = isset( $_GET['monoranks'] ) ? sanitize_key( wp_unslash( $_GET['monoranks'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		return $code ? self::notice_for( $code ) : null;
	}

	/** A link that leaves the admin, tagged so MonoRanks can tell plugin traffic apart. */
	public static function out( $url, $content, $campaign = 'plugin-admin' ) {
		return $url ? add_query_arg( array( 'utm_source' => 'wordpress-plugin', 'utm_medium' => 'admin', 'utm_campaign' => $campaign, 'utm_content' => $content ), $url ) : '';
	}

	/** The address field is only for development sites pointing the plugin at a local MonoRanks. */
	public static function shows_address_field() {
		return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}
}
