<?php
/**
 * Plugin Name:       MonoRanks
 * Description:       Connects this site to MonoRanks (SEO, AEO and GEO audits): sends published content metadata and applies the fixes you approve there, with undo.
 * Plugin URI:        https://monoranks.com/product/wordpress/
 * Author URI:        https://monoranks.com
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MonoRanks
 * License:           GPL-2.0-or-later
 * Text Domain:       monoranks
 */

defined( 'ABSPATH' ) || exit;

// Classes live under src/ (PSR-4, namespace MonoRanks) and load through Composer's autoloader. Third-party libraries,
// when there are any, are prefixed by WP Scoper (veronalabs/wp-scoper) into packages/ under MonoRanks\Deps so they
// cannot collide with other plugins' copies.
require_once __DIR__ . '/vendor/autoload.php';
if ( file_exists( __DIR__ . '/packages/autoload.php' ) ) {
	require_once __DIR__ . '/packages/autoload.php';
}

define( 'MONORANKS_CONNECTOR_VERSION', '1.0.0' );
define( 'MONORANKS_CONNECTOR_FILE', __FILE__ );
// MonoRanks address used when a key is pasted by hand. The download build sets it; wp-config.php may override it.
if ( ! defined( 'MONORANKS_API_BASE' ) ) {
	define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
}


add_action( 'rest_api_init', array( 'MonoRanks\\Rest', 'register' ) );
add_action( 'template_redirect', array( 'MonoRanks\\Redirects', 'maybe_redirect' ), 1 );
add_filter( 'robots_txt', array( 'MonoRanks\\AiAccess', 'robots_txt' ), 20 );
add_action( 'init', array( 'MonoRanks\\AiAccess', 'maybe_serve_llms' ), 1 );
add_action( 'transition_post_status', array( 'MonoRanks\\Notifier', 'on_transition' ), 20, 3 );
add_action( 'post_updated', array( 'MonoRanks\\Notifier', 'on_update' ), 20, 3 );
add_action( 'shutdown', array( 'MonoRanks\\Notifier', 'flush' ) );
add_action( \MonoRanks\Sync::STEP_HOOK, array( 'MonoRanks\\Sync', 'run' ) );
add_action( \MonoRanks\Sync::DAILY_HOOK, array( 'MonoRanks\\Sync', 'daily' ) );
\MonoRanks\SeoFields::register_output();
\MonoRanks\Admin::register();

register_deactivation_hook( __FILE__, array( 'MonoRanks\\Sync', 'unschedule' ) );
register_uninstall_hook( __FILE__, 'monoranks_connector_uninstall' );
function monoranks_connector_uninstall() {
	\MonoRanks\Sync::unschedule();
	delete_option( 'monoranks_connection' );
	delete_option( 'monoranks_sync' );
	delete_option( 'monoranks_redirects' );
	delete_option( 'monoranks_change_log' );
}
