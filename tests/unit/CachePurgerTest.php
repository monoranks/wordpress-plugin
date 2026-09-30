<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MonoRanks\CachePurger;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/** Page caches are asked to drop /llms.txt or /robots.txt after a write, only through plugins that are active (#87). */
class CachePurgerTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'home_url' )->alias( static function ( $path = '' ) { return 'https://example.com' . $path; } );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	// Runs first: none of the caching plugins' functions exist yet in this process.
	public function test_nothing_is_called_without_a_caching_plugin() {
		Functions\when( 'has_action' )->justReturn( false );
		Actions\expectDone( 'litespeed_purge_url' )->never();
		Actions\expectDone( 'monoranks_purged_file_cache' )->once()->with( array( 'https://example.com/llms.txt' ), '/llms.txt', array() );
		$this->assertSame( array(), CachePurger::purge_file( '/llms.txt' ) );
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
}
