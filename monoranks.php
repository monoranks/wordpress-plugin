<?php
/**
 * Plugin Name:       MonoRanks
 * Description:       Connects this site to MonoRanks (SEO, AEO and GEO audits): sends published content metadata and applies the fixes you approve there, with undo.
 * Plugin URI:        https://monoranks.com/product/wordpress/
 * Author URI:        https://monoranks.com
 * Version:           0.9.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MonoRanks
 * License:           GPL-2.0-or-later
 * Text Domain:       monoranks
 */

defined( 'ABSPATH' ) || exit;

define( 'MONORANKS_CONNECTOR_VERSION', '0.9.0' );
define( 'MONORANKS_CONNECTOR_FILE', __FILE__ );
// MonoRanks address used when a key is pasted by hand. The download build sets it; wp-config.php may override it.
if ( ! defined( 'MONORANKS_API_BASE' ) ) {
	define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
}

require_once __DIR__ . '/includes/class-seo-fields.php';
require_once __DIR__ . '/includes/class-content.php';
require_once __DIR__ . '/includes/class-connection.php';
require_once __DIR__ . '/includes/class-api.php';
require_once __DIR__ . '/includes/class-sync.php';
require_once __DIR__ . '/includes/class-writer.php';
require_once __DIR__ . '/includes/class-redirects.php';
require_once __DIR__ . '/includes/class-ai-access.php';
require_once __DIR__ . '/includes/class-notifier.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-admin.php';

add_action( 'rest_api_init', array( 'MonoRanks_Rest', 'register' ) );
add_action( 'template_redirect', array( 'MonoRanks_Redirects', 'maybe_redirect' ), 1 );
add_filter( 'robots_txt', array( 'MonoRanks_Ai_Access', 'robots_txt' ), 20 );
add_action( 'init', array( 'MonoRanks_Ai_Access', 'maybe_serve_llms' ), 1 );
add_action( 'transition_post_status', array( 'MonoRanks_Notifier', 'on_transition' ), 20, 3 );
add_action( 'post_updated', array( 'MonoRanks_Notifier', 'on_update' ), 20, 3 );
add_action( 'shutdown', array( 'MonoRanks_Notifier', 'flush' ) );
add_action( MonoRanks_Sync::STEP_HOOK, array( 'MonoRanks_Sync', 'run' ) );
add_action( MonoRanks_Sync::DAILY_HOOK, array( 'MonoRanks_Sync', 'daily' ) );
MonoRanks_Seo_Fields::register_output();
MonoRanks_Admin::register();

register_deactivation_hook( __FILE__, array( 'MonoRanks_Sync', 'unschedule' ) );
register_uninstall_hook( __FILE__, 'monoranks_connector_uninstall' );
function monoranks_connector_uninstall() {
	MonoRanks_Sync::unschedule();
	delete_option( 'monoranks_connection' );
	delete_option( 'monoranks_sync' );
	delete_option( 'monoranks_redirects' );
	delete_option( 'monoranks_change_log' );
}
