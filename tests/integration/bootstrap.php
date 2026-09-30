<?php
// Integration tests run inside a real WordPress: point MONORANKS_WP_PATH at a local install (the folder with
// wp-load.php), for example `MONORANKS_WP_PATH=~/Sites/wordpress composer test:integration`. No other plugin is loaded,
// so the tests see core's block parser and registry only. Every post a test creates is deleted again.
// Without MONORANKS_WP_PATH the tests are skipped.
//
// MONORANKS_WP_PLUGINS loads installed plugins for this run only (comma separated, as in active_plugins), without
// activating them in the database. The tests in group "tsf" need The SEO Framework:
//   MONORANKS_WP_PLUGINS=autodescription/autodescription.php composer test:integration -- --group tsf
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
// On PHP 7.4 the test tools' symfony/polyfill-php80 defines str_starts_with(), whose class loads lazily. The SEO
// Framework's autoloader calls str_starts_with() while that class is being loaded and fails, so load it up front.
class_exists( 'Symfony\\Polyfill\\Php80\\Php80' );

$monoranks_wp = getenv( 'MONORANKS_WP_PATH' );
if ( $monoranks_wp && is_file( rtrim( $monoranks_wp, '/' ) . '/wp-load.php' ) ) {
	// Hooks set before WordPress loads (WP_Hook::build_preinitialized_hooks): keep every plugin off but those in
	// MONORANKS_WP_PLUGINS, the installed MonoRanks copy included, so these tests load the classes from this checkout.
	$monoranks_plugins = array_values( array_filter( array_map( 'trim', explode( ',', (string) getenv( 'MONORANKS_WP_PLUGINS' ) ) ) ) );
	$GLOBALS['wp_filter']['option_active_plugins'][1][]           = array(
		'function'      => static function () use ( $monoranks_plugins ) {
			return $monoranks_plugins;
		},
		'accepted_args' => 1,
	);
	$GLOBALS['wp_filter']['site_option_active_sitewide_plugins'][1][] = array( 'function' => '__return_empty_array', 'accepted_args' => 1 );
	$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
	require_once rtrim( $monoranks_wp, '/' ) . '/wp-load.php';
	define( 'MONORANKS_TESTS_WP', true );
}
