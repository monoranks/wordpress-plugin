<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class SettingsTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( $s, '/\\' ); } );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_connect_link_names_the_site_and_the_way_back() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/wp-admin/' . $path; } );
		$this->assertSame(
			'https://app.monoranks.com/connect/wordpress?site=https%3A%2F%2Fexample.com&return=https%3A%2F%2Fexample.com%2Fwp-admin%2Fadmin.php%3Fpage%3Dmonoranks-settings',
			Settings::connect_url()
		);
	}

	public function test_connect_link_keeps_a_subfolder_and_a_custom_base() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/blog/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/blog/wp-admin/' . $path; } );
		$this->assertSame(
			'http://localhost:3000/connect/wordpress?site=https%3A%2F%2Fexample.com%2Fblog&return=https%3A%2F%2Fexample.com%2Fblog%2Fwp-admin%2Fadmin.php%3Fpage%3Dmonoranks-settings',
			Settings::connect_url( 'http://localhost:3000/' )
		);
	}

	public function test_connect_link_carries_the_users_state_and_keeps_it_while_it_lives() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/wp-admin/' . $path; } );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		$store = array();
		Functions\when( 'get_transient' )->alias( static function ( $k ) use ( &$store ) { return isset( $store[ $k ] ) ? $store[ $k ] : false; } );
		Functions\when( 'set_transient' )->alias( static function ( $k, $v ) use ( &$store ) { $store[ $k ] = $v; return true; } );

		$url = Settings::connect_url();
		$this->assertMatchesRegularExpression( '/&state=[a-f0-9]{40}$/', $url );
		$this->assertSame( $url, Settings::connect_url(), 'a second tab gets the same link' );
		$this->assertSame( 'https://app.monoranks.com', $store['monoranks_connect_7']['base'] );
		$this->assertNotSame( substr( $url, -40 ), substr( Settings::connect_url( 'http://localhost:3000' ), -40 ), 'another MonoRanks address gets its own state' );
	}
}
