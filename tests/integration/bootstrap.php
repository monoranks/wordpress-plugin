<?php
// Integration tests run inside a real WordPress: point MONORANKS_WP_PATH at a local install (the folder with
// wp-load.php), for example `MONORANKS_WP_PATH=~/Sites/wordpress composer test:integration`. No other plugin is loaded,
// so the tests see core's block parser and registry only. Every post a test creates is deleted again.
// Without MONORANKS_WP_PATH the tests are skipped.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$monoranks_wp = getenv( 'MONORANKS_WP_PATH' );
if ( $monoranks_wp && is_file( rtrim( $monoranks_wp, '/' ) . '/wp-load.php' ) ) {
	// Hooks set before WordPress loads (WP_Hook::build_preinitialized_hooks): keep every plugin off, the installed
	// MonoRanks copy included, so these tests load the classes from this checkout.
	$GLOBALS['wp_filter']['option_active_plugins'][1][]           = array( 'function' => '__return_empty_array', 'accepted_args' => 1 );
	$GLOBALS['wp_filter']['site_option_active_sitewide_plugins'][1][] = array( 'function' => '__return_empty_array', 'accepted_args' => 1 );
	$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
	require_once rtrim( $monoranks_wp, '/' ) . '/wp-load.php';
	define( 'MONORANKS_TESTS_WP', true );
}
