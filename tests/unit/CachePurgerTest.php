<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MonoRanks\CachePurger;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Page caches are asked to drop what a write changed, only through caches that are active (#87, wordpress-plugin#11).
 * The order matters for the first test: it runs before any caching plugin's function or class exists in this process.
 */
class CachePurgerTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'home_url' )->alias( static function ( $path = '' ) { return 'https://example.com' . $path; } );
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( $s, '/\\' ); } );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_permalink' )->alias( static function ( $id ) { return 'https://example.com/post-' . $id . '/'; } );
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'url_to_postid' )->justReturn( 0 );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
		global $nginx_purger, $nginx_helper_admin, $kinsta_cache;
		$nginx_purger       = null;
		$nginx_helper_admin = null;
		$kinsta_cache       = null;
	}

	/** The plugin functions of the caches tested further down, made harmless (they exist once a test stubbed them). */
	private function quiet_function_caches( array $except = array() ) {
		foreach ( array_diff( array( 'rocket_clean_files', 'rocket_clean_home', 'w3tc_flush_url', 'wpsc_delete_url_cache', 'sg_cachepress_purge_cache', 'pantheon_wp_clear_edge_paths' ), $except ) as $fn ) {
			Functions\when( $fn )->justReturn( null );
		}
	}

	private function fixtures() {
		require_once __DIR__ . '/fixtures/caches.php';
		\WPO_Page_Cache::$enabled = true;
		\WPO_Page_Cache::$result  = true;
		\WPO_Page_Cache::$calls   = array();
		\WpeCommon::$calls        = array();
	}

	public function test_nothing_is_called_without_a_caching_plugin() {
		Functions\when( 'has_action' )->justReturn( false );
		Actions\expectDone( 'litespeed_purge_url' )->never();
		Actions\expectDone( 'monoranks_purged_file_cache' )->once()->with( array( 'https://example.com/llms.txt' ), '/llms.txt', array() );
		$this->assertSame( array( 'purged' => array(), 'failed' => array(), 'none' => true ), CachePurger::file( '/llms.txt' ) );
		$this->assertSame( array( 'purged' => array(), 'failed' => array(), 'none' => true ), CachePurger::post( 5 ) );
	}

	public function test_each_active_cache_gets_the_file_url() {
		Functions\when( 'has_action' )->alias( static function ( $hook ) { return 'litespeed_purge_url' === $hook; } );
		Functions\expect( 'rocket_clean_files' )->once()->with( array( 'https://example.com/robots.txt' ) );
		Functions\expect( 'w3tc_flush_url' )->once()->with( 'https://example.com/robots.txt' );
		Functions\expect( 'wpsc_delete_url_cache' )->once()->with( 'https://example.com/robots.txt' );
		Functions\expect( 'sg_cachepress_purge_cache' )->once()->with( 'https://example.com/robots.txt' );
		Actions\expectDone( 'litespeed_purge_url' )->once()->with( 'https://example.com/robots.txt' );
		$done = CachePurger::purge_file( '/robots.txt' );
		$this->assertSame( array( 'wp-rocket', 'litespeed', 'w3-total-cache', 'wp-super-cache', 'siteground' ), $done );
	}

	public function test_a_filter_can_add_urls() {
		Functions\when( 'has_action' )->justReturn( false );
		Filters\expectApplied( 'monoranks_purge_file_urls' )->once()->andReturn( array( 'https://example.com/llms.txt', 'https://www.example.com/llms.txt', '' ) );
		Functions\expect( 'w3tc_flush_url' )->twice();
		Functions\when( 'rocket_clean_files' )->justReturn( array() );
		Functions\when( 'wpsc_delete_url_cache' )->justReturn( true );
		Functions\when( 'sg_cachepress_purge_cache' )->justReturn( null );
		Actions\expectDone( 'monoranks_purged_file_cache' )->once()->with( array( 'https://example.com/llms.txt', 'https://www.example.com/llms.txt' ), '/llms.txt', \Mockery::type( 'array' ) );
		$this->assertSame( array( 'wp-rocket', 'w3-total-cache', 'wp-super-cache', 'siteground' ), CachePurger::purge_file( '/llms.txt' ) );
	}

	public function test_a_post_purges_its_url_and_the_home_page_when_it_is_the_front_page() {
		Functions\when( 'has_action' )->justReturn( false );
		Functions\when( 'get_option' )->alias( static function ( $name ) { return array( 'show_on_front' => 'page', 'page_on_front' => '7' )[ $name ] ?? false; } );
		$this->quiet_function_caches( array( 'w3tc_flush_url', 'rocket_clean_home', 'rocket_clean_files' ) );
		Functions\expect( 'w3tc_flush_url' )->twice()->with( \Mockery::anyOf( 'https://example.com/post-7/', 'https://example.com/' ) );
		// WP Rocket purges the home page with rocket_clean_home(): its folder holds the whole cache.
		Functions\expect( 'rocket_clean_home' )->once();
		Functions\expect( 'rocket_clean_files' )->once()->with( array( 'https://example.com/post-7/' ) );
		Actions\expectDone( 'monoranks_purged_cache' )->once()->with( array( 'https://example.com/post-7/', 'https://example.com/' ), 7, \Mockery::type( 'array' ) );
		$r = CachePurger::post( 7 );
		$this->assertContains( 'wp-rocket', $r['purged'] );
		$this->assertFalse( $r['none'] );
	}

	public function test_a_failing_cache_is_reported_never_fatal() {
		Functions\when( 'has_action' )->justReturn( false );
		$this->quiet_function_caches( array( 'wpsc_delete_url_cache', 'w3tc_flush_url' ) );
		Functions\when( 'wpsc_delete_url_cache' )->justReturn( false );
		Functions\when( 'w3tc_flush_url' )->alias( static function () { throw new \RuntimeException( 'boom' ); } );
		$r = CachePurger::url( '/old/' );
		$this->assertContains( 'wp-super-cache', $r['failed'] );
		$this->assertContains( 'w3-total-cache', $r['failed'] );
		$this->assertContains( 'siteground', $r['purged'] );
		$this->assertFalse( $r['none'] );
	}

	public function test_wp_optimize_purges_by_url_only_when_its_page_cache_is_on() {
		$this->fixtures();
		Functions\when( 'has_action' )->justReturn( false );
		$this->quiet_function_caches();
		$r = CachePurger::post( 3 );
		$this->assertContains( 'wp-optimize', $r['purged'] );
		$this->assertSame( array( 'https://example.com/post-3/' ), \WPO_Page_Cache::$calls );

		\WPO_Page_Cache::$result = false;
		$this->assertContains( 'wp-optimize', CachePurger::url( 'https://example.com/a/' )['failed'] );

		\WPO_Page_Cache::$enabled = false;
		\WPO_Page_Cache::$calls   = array();
		$r                        = CachePurger::post( 3 );
		$this->assertNotContains( 'wp-optimize', array_merge( $r['purged'], $r['failed'] ) );
		$this->assertSame( array(), \WPO_Page_Cache::$calls );
	}

	public function test_nginx_helper_purges_through_its_global_purger_when_purging_is_on() {
		$this->fixtures();
		Functions\when( 'has_action' )->justReturn( false );
		$this->quiet_function_caches();
		global $nginx_purger, $nginx_helper_admin;
		$nginx_purger       = new \MonoRanks_Test_Nginx_Purger();
		$nginx_helper_admin = (object) array( 'options' => array( 'enable_purge' => 0 ) );
		$this->assertNotContains( 'nginx-helper', CachePurger::post( 4 )['purged'] );
		$this->assertSame( array(), $nginx_purger->calls );

		$nginx_helper_admin->options['enable_purge'] = 1;
		$this->assertContains( 'nginx-helper', CachePurger::post( 4 )['purged'] );
		$this->assertSame( array( 'https://example.com/post-4/' ), $nginx_purger->calls );
	}

	public function test_breeze_purges_posts_through_its_action() {
		$this->fixtures();
		Functions\when( 'has_action' )->alias( static function ( $hook ) { return 'purge_post_cache' === $hook; } );
		$this->quiet_function_caches();
		Actions\expectDone( 'purge_post_cache' )->once()->with( 9 );
		$this->assertContains( 'breeze', CachePurger::post( 9 )['purged'] );
		// No post: Breeze has no public one-URL purge, so it is left alone.
		$this->assertNotContains( 'breeze', CachePurger::file( '/llms.txt' )['purged'] );
	}

	public function test_host_caches_purge_the_post_or_everything_for_a_site_wide_file() {
		$this->fixtures();
		Functions\when( 'has_action' )->justReturn( false );
		$this->quiet_function_caches();
		global $kinsta_cache;
		$kinsta_cache = (object) array( 'kinsta_cache_purge' => new \MonoRanks_Test_Kinsta_Purge() );

		$r = CachePurger::post( 6 );
		$this->assertContains( 'kinsta', $r['purged'] );
		$this->assertContains( 'wp-engine', $r['purged'] );
		$this->assertSame( array( array( 'post', 6, 'post' ) ), $kinsta_cache->kinsta_cache_purge->calls );
		$this->assertSame( array( 6 ), \WpeCommon::$calls );

		CachePurger::file( '/robots.txt' );
		$this->assertSame( array( 'all' ), $kinsta_cache->kinsta_cache_purge->calls[1] );
		$this->assertSame( array( 6, null ), \WpeCommon::$calls );

		// A URL that is not a post and not a site-wide file: neither host has a one-URL purge.
		$r = CachePurger::url( '/gone/' );
		$this->assertNotContains( 'kinsta', $r['purged'] );
		$this->assertNotContains( 'wp-engine', $r['purged'] );
		$this->assertCount( 2, $kinsta_cache->kinsta_cache_purge->calls );
	}

	public function test_pantheon_gets_paths() {
		Functions\when( 'has_action' )->justReturn( false );
		$this->quiet_function_caches( array( 'pantheon_wp_clear_edge_paths' ) );
		Functions\expect( 'pantheon_wp_clear_edge_paths' )->once()->with( array( '/post-2/' ) );
		$this->assertContains( 'pantheon', CachePurger::post( 2 )['purged'] );
	}

	public function test_a_redirect_source_that_is_a_post_uses_the_post_purges() {
		$this->fixtures();
		Functions\when( 'has_action' )->justReturn( false );
		$this->quiet_function_caches();
		Functions\when( 'url_to_postid' )->justReturn( 11 );
		Actions\expectDone( 'monoranks_purged_cache' )->once()->with( array( 'https://example.com/old/' ), 11, \Mockery::type( 'array' ) );
		CachePurger::url( '/old/' );
		$this->assertSame( array( 11 ), \WpeCommon::$calls );
	}
}
