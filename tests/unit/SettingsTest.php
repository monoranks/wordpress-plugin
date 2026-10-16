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
}
