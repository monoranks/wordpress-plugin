<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * After MonoRanks writes something (an SEO field, alt text, the opening paragraph, a redirect, llms.txt or the AI crawler
 * rules), page caches may keep serving the old page until they expire, so visitors, crawlers and MonoRanks' own check
 * still see the old version (monoranks/monoranks#87, monoranks/wordpress-plugin#11). This asks the common page caches to
 * drop the affected URL, each through its own public function, method or action, and only when that cache is active.
 * Every call is guarded and wrapped, so a missing or failing cache never breaks the write.
 *
 * Supported: WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, SiteGround Optimizer, WP-Optimize, Nginx Helper,
 * Breeze (posts), Kinsta (posts), WP Engine (posts) and Pantheon Advanced Page Cache. Site-wide files (/llms.txt,
 * /robots.txt) purge a whole cache only where that cache has no way to purge one URL (Kinsta, WP Engine).
 *
 * Not covered: the official Cloudflare plugin has no public function or action to purge one URL; its purge runs only
 * from WordPress events it listens to (a post status change, which our opening-paragraph write does trigger). A cache in
 * front of WordPress that no plugin controls (for example a Cloudflare Cache Rule) clears when its time runs out.
 *
 * Each purge returns `array( 'purged' => string[], 'failed' => string[], 'none' => bool )`: the caches asked to purge,
 * the ones that reported a failure or threw, and whether no supported cache was found active at all.
 */
class CachePurger {

	/**
	 * Drop a file (a path such as "/llms.txt") from every page cache we know how to reach. Kept for older callers.
	 *
	 * @param string $path Path on the site, with a leading slash.
	 * @return string[] Names of the caches that were asked to purge.
	 */
	public static function purge_file( $path ) {
		return self::file( $path )['purged'];
	}

	/**
	 * Purge a site-wide file such as /llms.txt or /robots.txt.
	 *
	 * @param string $path Path on the site, with a leading slash.
	 * @return array{purged: string[], failed: string[], none: bool}
	 */
	public static function file( $path ) {
		$urls = array( home_url( $path ) );
		/**
		 * Filters the URLs purged from page caches after MonoRanks writes llms.txt or robots.txt rules.
		 *
		 * @param string[] $urls The file's URL.
		 * @param string   $path The file's path, "/llms.txt" or "/robots.txt".
		 */
		$urls   = self::clean( apply_filters( 'monoranks_purge_file_urls', $urls, $path ) );
		$result = self::run( $urls, 0, true );
		/**
		 * Fires after the page caches above were asked to drop the file, so other caches can do the same.
		 *
		 * @param string[] $urls The URLs.
		 * @param string   $path The file's path.
		 * @param string[] $done Names of the caches that were asked to purge.
		 */
		do_action( 'monoranks_purged_file_cache', $urls, $path, $result['purged'] );
		return $result;
	}

	/**
	 * Purge one post's page, plus the home page when the post is the static front page.
	 *
	 * @param int $post_id Post ID.
	 * @return array{purged: string[], failed: string[], none: bool}
	 */
	public static function post( $post_id ) {
		$post_id = (int) $post_id;
		$link    = $post_id ? get_permalink( $post_id ) : '';
		$urls    = $link ? array( (string) $link ) : array();
		if ( $post_id && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id ) {
			$urls[] = home_url( '/' );
		}
		return self::urls( $urls, $post_id );
	}

	/**
	 * Purge one URL (for example a redirect's source). When it belongs to a post, that post's purge is used too.
	 *
	 * @param string $url Absolute URL or a path on this site.
	 * @return array{purged: string[], failed: string[], none: bool}
	 */
	public static function url( $url ) {
		$url = (string) $url;
		if ( '' !== $url && '/' === $url[0] ) {
			$url = home_url( $url );
		}
		$post_id = function_exists( 'url_to_postid' ) ? (int) url_to_postid( $url ) : 0;
		return self::urls( array( $url ), $post_id );
	}

	/** Shared by post() and url(): the filter, the purge and the action. */
	private static function urls( array $urls, $post_id ) {
		/**
		 * Filters the URLs purged from page caches after MonoRanks changes a page (SEO fields, alt text, the opening
		 * paragraph, a redirect).
		 *
		 * @param string[] $urls    The URLs.
		 * @param int      $post_id The post they belong to, or 0.
		 */
		$urls   = self::clean( apply_filters( 'monoranks_purge_urls', $urls, $post_id ) );
		$result = self::run( $urls, $post_id, false );
		/**
		 * Fires after the page caches were asked to drop the URLs, so other caches can do the same.
		 *
		 * @param string[] $urls    The URLs.
		 * @param int      $post_id The post they belong to, or 0.
		 * @param array    $result  `purged`, `failed` and `none`, as returned.
		 */
		do_action( 'monoranks_purged_cache', $urls, $post_id, $result );
		return $result;
	}

	/** Unique non-empty strings. */
	private static function clean( $urls ) {
		return array_values( array_unique( array_filter( array_map( 'strval', (array) $urls ) ) ) );
	}

	/**
	 * Every adapter for each URL, then the post-based ones once.
	 *
	 * @param string[] $urls      URLs to drop.
	 * @param int      $post_id   The post they belong to, or 0.
	 * @param bool     $site_wide A site-wide file: caches without a one-URL purge may purge everything.
	 */
	private static function run( array $urls, $post_id, $site_wide ) {
		$purged = array();
		$failed = array();
		$note   = static function ( $name, $ok ) use ( &$purged, &$failed ) {
			if ( null === $ok ) {
				return; // Not active.
			}
			if ( false === $ok ) {
				$failed[ $name ] = true;
			} else {
				$purged[ $name ] = true;
			}
		};
		foreach ( self::adapters() as $name => $adapter ) {
			$ok = null;
			try {
				$ok = $adapter( $urls, (int) $post_id, (bool) $site_wide );
			} catch ( \Throwable $e ) {
				$ok = false;
			}
			$note( $name, $ok );
		}
		$failed = array_diff_key( $failed, $purged );
		return array(
			'purged' => array_keys( $purged ),
			'failed' => array_keys( $failed ),
			'none'   => ! $purged && ! $failed,
		);
	}

	/**
	 * One callable per cache: `fn( string[] $urls, int $post_id, bool $site_wide ): ?bool`. null means the cache is not
	 * active (or has nothing to purge for this kind of change), true purged, false failed.
	 *
	 * @return array<string, callable>
	 */
	private static function adapters() {
		return array(
			// WP Rocket: rocket_clean_home() for the home page (purging its folder by URL would empty the whole cache),
			// rocket_clean_files( $urls ) for the rest.
			'wp-rocket'      => static function ( $urls ) {
				if ( ! function_exists( 'rocket_clean_files' ) ) {
					return null;
				}
				$home  = untrailingslashit( home_url( '/' ) );
				$other = array();
				foreach ( $urls as $url ) {
					if ( untrailingslashit( $url ) === $home && function_exists( 'rocket_clean_home' ) ) {
						rocket_clean_home();
					} else {
						$other[] = $url;
					}
				}
				if ( $other ) {
					rocket_clean_files( $other );
				}
				return true;
			},
			// LiteSpeed Cache: the litespeed_purge_url action.
			'litespeed'      => static function ( $urls ) {
				if ( ! has_action( 'litespeed_purge_url' ) ) {
					return null;
				}
				foreach ( $urls as $url ) {
					do_action( 'litespeed_purge_url', $url );
				}
				return true;
			},
			// W3 Total Cache: w3tc_flush_url( $url ).
			'w3-total-cache' => static function ( $urls ) {
				if ( ! function_exists( 'w3tc_flush_url' ) ) {
					return null;
				}
				foreach ( $urls as $url ) {
					w3tc_flush_url( $url );
				}
				return true;
			},
			// WP Super Cache: wpsc_delete_url_cache( $url ), false when it could not map the URL to a cache folder.
			'wp-super-cache' => static function ( $urls ) {
				if ( ! function_exists( 'wpsc_delete_url_cache' ) ) {
					return null;
				}
				$ok = true;
				foreach ( $urls as $url ) {
					if ( false === wpsc_delete_url_cache( $url ) ) {
						$ok = false;
					}
				}
				return $ok;
			},
			// SiteGround Optimizer: sg_cachepress_purge_cache( $url ).
			'siteground'     => static function ( $urls ) {
				if ( ! function_exists( 'sg_cachepress_purge_cache' ) ) {
					return null;
				}
				foreach ( $urls as $url ) {
					sg_cachepress_purge_cache( $url );
				}
				return true;
			},
			// WP-Optimize: only when its page cache is on (WPO_Page_Cache::instance()->is_enabled()), then
			// WPO_Page_Cache::delete_cache_by_url( $url ), which returns false when files could not be deleted.
			'wp-optimize'    => static function ( $urls ) {
				if ( ! class_exists( 'WPO_Page_Cache' ) || ! method_exists( 'WPO_Page_Cache', 'delete_cache_by_url' ) || ! method_exists( 'WPO_Page_Cache', 'instance' ) ) {
					return null;
				}
				$cache = \WPO_Page_Cache::instance();
				if ( ! is_object( $cache ) || ! method_exists( $cache, 'is_enabled' ) || ! $cache->is_enabled() ) {
					return null;
				}
				$ok = true;
				foreach ( $urls as $url ) {
					if ( false === \WPO_Page_Cache::delete_cache_by_url( $url ) ) {
						$ok = false;
					}
				}
				return $ok;
			},
			// Nginx Helper: the global $nginx_purger's purge_url( $url, $feed ), only when purging is on in its settings.
			'nginx-helper'   => static function ( $urls ) {
				global $nginx_purger, $nginx_helper_admin;
				if ( ! is_object( $nginx_purger ) || ! method_exists( $nginx_purger, 'purge_url' ) ) {
					return null;
				}
				if ( ! is_object( $nginx_helper_admin ) || empty( $nginx_helper_admin->options['enable_purge'] ) ) {
					return null;
				}
				foreach ( $urls as $url ) {
					$nginx_purger->purge_url( $url, false );
				}
				return true;
			},
			// Breeze: its documented do_action( 'purge_post_cache', $post_id ). Posts only.
			'breeze'         => static function ( $urls, $post_id ) {
				if ( ! $post_id || ! class_exists( 'Breeze_PurgeCache' ) || ! has_action( 'purge_post_cache' ) ) {
					return null;
				}
				do_action( 'purge_post_cache', $post_id );
				return true;
			},
			// Kinsta (host cache, its must-use plugin): $kinsta_cache->kinsta_cache_purge->initiate_purge( $post_id, 'post' ),
			// or purge_complete_caches() for a site-wide file. Other URLs: no public way, skipped.
			'kinsta'         => static function ( $urls, $post_id, $site_wide ) {
				global $kinsta_cache;
				$purge = is_object( $kinsta_cache ) && isset( $kinsta_cache->kinsta_cache_purge ) ? $kinsta_cache->kinsta_cache_purge : null;
				if ( ! is_object( $purge ) ) {
					return null;
				}
				if ( $post_id && method_exists( $purge, 'initiate_purge' ) ) {
					$purge->initiate_purge( $post_id, 'post' );
					return true;
				}
				if ( $site_wide && method_exists( $purge, 'purge_complete_caches' ) ) {
					$purge->purge_complete_caches();
					return true;
				}
				return null;
			},
			// WP Engine (host cache, its must-use plugin): WpeCommon::purge_varnish_cache( $post_id ), or
			// purge_varnish_cache() for a site-wide file. Other URLs: no public way, skipped.
			'wp-engine'      => static function ( $urls, $post_id, $site_wide ) {
				if ( ! class_exists( 'WpeCommon' ) || ! method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
					return null;
				}
				if ( $post_id ) {
					\WpeCommon::purge_varnish_cache( $post_id );
					return true;
				}
				if ( $site_wide ) {
					\WpeCommon::purge_varnish_cache();
					return true;
				}
				return null;
			},
			// Pantheon Advanced Page Cache: pantheon_wp_clear_edge_paths( $paths ).
			'pantheon'       => static function ( $urls ) {
				if ( ! function_exists( 'pantheon_wp_clear_edge_paths' ) ) {
					return null;
				}
				$paths = array();
				foreach ( $urls as $url ) {
					$path    = wp_parse_url( $url, PHP_URL_PATH );
					$paths[] = $path ? $path : '/';
				}
				pantheon_wp_clear_edge_paths( array_values( array_unique( $paths ) ) );
				return true;
			},
		);
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
