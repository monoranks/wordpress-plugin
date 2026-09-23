<?php
/**
 * Plugin Name:       MonoRanks
 * Description:       Connects this site to MonoRanks (SEO, AEO and GEO audits): sends published content metadata and applies the fixes you approve there, with undo.
 * Plugin URI:        https://monoranks.com/product/wordpress/
 * Author URI:        https://veronalabs.com/
 * Version:           0.1.5
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            VeronaLabs
 * License:           GPL-2.0-or-later
 * Text Domain:       monoranks
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Classes live under src/ (PSR-4, namespace MonoRanks). WP Scoper (veronalabs/wp-scoper, run by composer install) writes
// packages/autoload.php: it loads src/ and any third-party library prefixed into packages/ under MonoRanks\Deps, so the
// shipped plugin needs no vendor/ folder. A checkout that has Composer's autoloader but no packages/ yet uses that instead.
if ( file_exists( __DIR__ . '/packages/autoload.php' ) ) {
	require_once __DIR__ . '/packages/autoload.php';
} elseif ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	return;
}

define( 'MONORANKS_CONNECTOR_VERSION', '0.1.5' );
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
\MonoRanks\Assets::register();
\MonoRanks\Column::register();
\MonoRanks\Insights::register();

register_deactivation_hook( __FILE__, array( 'MonoRanks\\Sync', 'unschedule' ) );
// Deleting the plugin runs uninstall.php, which is the only cleanup path (WordPress ignores register_uninstall_hook when
// that file exists), so everything the plugin stores is listed there.
