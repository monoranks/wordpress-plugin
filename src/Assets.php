<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The admin app (React, built by Vite into build/) on the plugin's own screens, and the small stylesheet for the Posts
 * and Pages column (also from build/, source in resources/assets/) on list screens. The bundle never carries @wordpress/i18n or @wordpress/api-fetch: WordPress
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
			wp_enqueue_style( 'monoranks-column', plugins_url( 'build/column.css', MONORANKS_CONNECTOR_FILE ), array(), self::version( $dir . '/build/column.css' ) );
			return;
		}
		$screen = Admin::screen_for_hook( $hook );
		if ( '' === $screen ) {
			return;
		}
		$js  = $dir . '/build/main.js';
		$css = $dir . '/build/main.css';
		if ( ! file_exists( $js ) ) {
			return; // Admin::mount() explains what to run.
		}
		wp_enqueue_style( self::HANDLE, plugins_url( 'build/main.css', MONORANKS_CONNECTOR_FILE ), array(), self::version( $css ) );
		wp_enqueue_script( self::HANDLE, plugins_url( 'build/main.js', MONORANKS_CONNECTOR_FILE ), array( 'wp-i18n', 'wp-api-fetch' ), self::version( $js ), true );
		wp_set_script_translations( self::HANDLE, 'monoranks', $dir . '/languages' );
		wp_add_inline_script( self::HANDLE, 'window.monoranksAdmin = ' . wp_json_encode( self::settings( $screen ) ) . ';', 'before' );
	}

	/** Cache-busts on the built file's own mtime, so a rebuild gets a new URL without a version bump. */
	private static function version( $path ) {
		$m = file_exists( $path ) ? filemtime( $path ) : false;
		return $m ? (string) $m : MONORANKS_CONNECTOR_VERSION;
	}

	/** What the app needs before it loads: which screen, where things are, the brand SVGs, a notice carried in the URL. */
	public static function settings( $screen ) {
		$conn     = Connection::get();
		$overview = Connection::has_key() ? Insights::overview() : null;
		$app      = Connection::has_key() ? $conn['api_base'] : MONORANKS_API_BASE;
		return array(
			'locale'      => str_replace( '_', '-', determine_locale() ),
			'screen'      => $screen,
			'theme'       => Admin::theme(),
			'version'     => MONORANKS_CONNECTOR_VERSION,
			'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'urls'        => array(
				'overview' => Admin::overview_url(),
				'settings' => Admin::settings_url(),
				'app'      => $overview && ! empty( $overview['site_url'] ) ? $overview['site_url'] : $app,
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

	/** The bundle is an ES module. Only the tag with the src: the inline settings and translations stay classic scripts. */
	public static function module_tag( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		return (string) preg_replace( '/<script(?=[^>]*\ssrc=)/', '<script type="module"', $tag, 1 );
	}

	/**
	 * One JSON catalogue per locale for the app (languages/monoranks-<locale>-admin.json), whatever the built file is
	 * named. A locale the plugin does not bundle keeps whatever WordPress found, so a language pack still applies.
	 */
	public static function translation_file( $file, $handle, $domain ) {
		if ( self::HANDLE !== $handle || 'monoranks' !== $domain ) {
			return $file;
		}
		$path = dirname( MONORANKS_CONNECTOR_FILE ) . '/languages/monoranks-' . determine_locale() . '-admin.json';
		return file_exists( $path ) ? $path : $file;
	}
}
