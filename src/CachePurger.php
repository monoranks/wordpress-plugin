<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * After MonoRanks writes llms.txt or the AI crawler rules, page caches may still hold the old /llms.txt or /robots.txt
 * (often a 404 cached before the file existed), so AI crawlers and MonoRanks' own check keep seeing it
 * (monoranks/monoranks#87). This asks the common caching plugins to drop those URLs, each through its public function or
 * action, and only when that plugin is active. A cache in front of WordPress that no plugin controls (for example a
 * Cloudflare Cache Rule) is not reached; it clears when its time runs out or when it is purged there.
 *
 * Not covered: the official Cloudflare plugin has no public way to purge one URL (it only purges the links of a post, or
 * everything), so it is left alone rather than purging the whole site.
 */
class CachePurger {

	/**
	 * Drop the given file (a path such as "/llms.txt") from every page cache we know how to reach.
	 *
	 * @param string $path Path on the site, with a leading slash.
	 * @return string[] Names of the caches that were asked to purge.
	 */
	public static function purge_file( $path ) {
		$urls = array( home_url( $path ) );
		/**
		 * Filters the URLs purged from page caches after MonoRanks writes llms.txt or robots.txt rules.
		 *
		 * @param string[] $urls The file's URL.
		 * @param string   $path The file's path, "/llms.txt" or "/robots.txt".
		 */
		$urls = array_values( array_filter( array_map( 'strval', (array) apply_filters( 'monoranks_purge_file_urls', $urls, $path ) ) ) );
		$done = array();
		foreach ( $urls as $url ) {
			foreach ( self::purge_url( $url ) as $name ) {
				$done[ $name ] = true;
			}
		}
		$done = array_keys( $done );
		/**
		 * Fires after the page caches above were asked to drop the file, so other caches can do the same.
		 *
		 * @param string[] $urls The URLs.
		 * @param string   $path The file's path.
		 * @param string[] $done Names of the caches that were asked to purge.
		 */
		do_action( 'monoranks_purged_file_cache', $urls, $path, $done );
		return $done;
	}

	/** One URL, through each active caching plugin's public API. */
	private static function purge_url( $url ) {
		$done = array();
		// WP Rocket: rocket_clean_files( $urls ).
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( array( $url ) );
			$done[] = 'wp-rocket';
		}
		// LiteSpeed Cache: the litespeed_purge_url action.
		if ( has_action( 'litespeed_purge_url' ) ) {
			do_action( 'litespeed_purge_url', $url );
			$done[] = 'litespeed';
		}
		// W3 Total Cache: w3tc_flush_url( $url ).
		if ( function_exists( 'w3tc_flush_url' ) ) {
			w3tc_flush_url( $url );
			$done[] = 'w3-total-cache';
		}
		// WP Super Cache: wpsc_delete_url_cache( $url ).
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( $url );
			$done[] = 'wp-super-cache';
		}
		// SiteGround Optimizer: sg_cachepress_purge_cache( $url ).
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache( $url );
			$done[] = 'siteground';
		}
		return $done;
	}

	/** Headers for a file the plugin serves itself: never kept by browsers, proxies or page caches. */
	public static function no_cache_response() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // WP Super Cache, W3 Total Cache, WP Rocket and others skip the page.
		}
		if ( has_action( 'litespeed_control_set_nocache' ) ) {
			do_action( 'litespeed_control_set_nocache', 'MonoRanks serves this file' );
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private' );
		}
	}
}
