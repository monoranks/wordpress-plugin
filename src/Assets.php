<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The admin app (React, built by Vite into assets/build/) on the plugin's own screens, and the small stylesheet for the
 * Posts and Pages column everywhere else. The bundle never carries @wordpress/i18n or @wordpress/api-fetch: WordPress
 * provides both, and wp_set_script_translations() loads the catalogue into WordPress's own wp.i18n.
 */
class Assets {

	const HANDLE = 'monoranks-admin';

	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'module_tag' ), 10, 2 );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'translation_file' ), 10, 3 );
	}

	public static function enqueue( $hook ) {
		$dir = dirname( MONORANKS_CONNECTOR_FILE );
		if ( 'edit.php' === $hook ) {
			wp_enqueue_style( 'monoranks-column', plugins_url( 'assets/column.css', MONORANKS_CONNECTOR_FILE ), array(), MONORANKS_CONNECTOR_VERSION );
			return;
		}
		if ( ! in_array( $hook, array( 'toplevel_page_' . Admin::MENU, 'monoranks_page_' . Admin::SETTINGS ), true ) ) {
			return;
		}
		$js  = $dir . '/assets/build/main.js';
		$css = $dir . '/assets/build/main.css';
		if ( ! file_exists( $js ) ) {
			return; // Admin::mount() explains what to run.
		}
		wp_enqueue_style( self::HANDLE, plugins_url( 'assets/build/main.css', MONORANKS_CONNECTOR_FILE ), array(), self::version( $css ) );
		wp_enqueue_script( self::HANDLE, plugins_url( 'assets/build/main.js', MONORANKS_CONNECTOR_FILE ), array( 'wp-i18n', 'wp-api-fetch' ), self::version( $js ), true );
		wp_set_script_translations( self::HANDLE, 'monoranks', $dir . '/languages' );
		wp_add_inline_script( self::HANDLE, 'window.monoranksAdmin = ' . wp_json_encode( self::settings( 'monoranks_page_' . Admin::SETTINGS === $hook ? 'settings' : 'overview' ) ) . ';', 'before' );
	}

	/** Cache-busts on the built file's own mtime, so a rebuild gets a new URL without a version bump. */
	private static function version( $path ) {
		$m = file_exists( $path ) ? filemtime( $path ) : false;
		return $m ? (string) $m : MONORANKS_CONNECTOR_VERSION;
	}

	/** What the app needs before it loads: which screen, where things are, the brand SVGs, a notice carried in the URL. */
	public static function settings( $screen ) {
		$conn = Connection::get();
		return array(
			'screen'      => $screen,
			'theme'       => Admin::theme(),
			'version'     => MONORANKS_CONNECTOR_VERSION,
			'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'urls'        => array(
				'overview' => Admin::overview_url(),
				'settings' => Admin::settings_url(),
				'app'      => Connection::has_key() ? $conn['api_base'] : MONORANKS_API_BASE,
				'profile'  => admin_url( 'profile.php#application-passwords-section' ),
				'docs'     => 'https://monoranks.com/docs/wordpress-plugin/',
			),
			'titles'      => array( 'overview' => __( 'Overview', 'monoranks' ), 'settings' => __( 'MonoRanks settings', 'monoranks' ) ),
			'showAddress' => Admin::shows_address_field(),
			'apiBase'     => Connection::has_key() ? $conn['api_base'] : MONORANKS_API_BASE,
			'logos'       => array( 'wordmark' => Admin::asset( 'logo.svg' ), 'mark' => Admin::asset( 'mark.svg' ), 'veronalabs' => Admin::asset( 'veronalabs.svg' ) ),
			'notice'      => Admin::notice(),
		);
	}

	/** The bundle is an ES module. */
	public static function module_tag( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/** One JSON catalogue per locale for the app (languages/monoranks-<locale>-admin.json), whatever the built file is named. */
	public static function translation_file( $file, $handle, $domain ) {
		if ( self::HANDLE !== $handle || 'monoranks' !== $domain ) {
			return $file;
		}
		$path = dirname( MONORANKS_CONNECTOR_FILE ) . '/languages/monoranks-' . determine_locale() . '-admin.json';
		return file_exists( $path ) ? $path : false;
	}
}
