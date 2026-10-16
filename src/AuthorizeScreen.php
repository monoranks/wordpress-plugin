<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress's own "Authorize Application" screen (wp-admin/authorize-application.php): when MonoRanks is the app asking,
 * the logo and one short line go above WordPress's buttons. Core's text, Yes and No stay as they are. Other apps: nothing.
 */
class AuthorizeScreen {

	/** The app id MonoRanks sends to authorize-application.php (WP_APP_ID in the MonoRanks app). */
	const APP_ID = '6f1c1b5e-3f7a-4d8e-9a51-7a2b0c4e8d10';

	public static function register() {
		add_action( 'wp_authorize_application_password_form', array( __CLASS__, 'render' ), 10, 2 );
	}

	/** Whether the approval request comes from MonoRanks. */
	public static function is_monoranks( $request ) {
		return is_array( $request ) && isset( $request['app_id'] ) && is_string( $request['app_id'] ) && 0 === strcasecmp( $request['app_id'], self::APP_ID );
	}

	public static function render( $request, $user = null ) {
		if ( ! self::is_monoranks( $request ) ) {
			return;
		}
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts and the plugin's own SVG
	}

	public static function markup() {
		$css = '#monoranks-authorize{display:flex;flex-direction:column;align-items:flex-start;gap:8px;margin:16px 0;padding:14px 16px;border:1px solid #e3e5e8;border-radius:8px;background:#f7f8f9}'
			. '#monoranks-authorize svg{display:block;width:132px;height:auto}'
			. '#monoranks-authorize p{margin:0;color:#3c434a;font-size:13px}'
			. '.form-wrap #approve.button-primary{background:#14181f;border-color:#14181f;border-radius:6px}'
			. '.form-wrap #approve.button-primary:hover,.form-wrap #approve.button-primary:focus{background:#2a2f38;border-color:#2a2f38}';
		return '<style id="monoranks-authorize-css">' . $css . '</style>'
			. '<div id="monoranks-authorize">' . Admin::asset( 'logo.svg' )
			. '<p>' . esc_html__( 'Reads your content and applies only fixes you approve.', 'monoranks' ) . '</p></div>';
	}
}
