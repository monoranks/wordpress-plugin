<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Connection;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ConnectionTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( (string) $s, '/' ); } );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_valid_key_accepts_the_monoranks_site_key_shape() {
		$this->assertSame( 'mr_site_' . str_repeat( 'a', 32 ), Connection::valid_key( '  mr_site_' . str_repeat( 'a', 32 ) . ' ' ) );
	}

	public function test_valid_key_rejects_other_shapes() {
		$this->assertSame( '', Connection::valid_key( 'not-a-key' ) );
		$this->assertSame( '', Connection::valid_key( 'mr_ws_' . str_repeat( 'a', 32 ) ) );
		$this->assertSame( '', Connection::valid_key( 'mr_site_short' ) );
		$this->assertSame( '', Connection::valid_key( 'mr_site_' . str_repeat( 'a', 81 ) ) );
		$this->assertSame( '', Connection::valid_key( 'mr_site_' . str_repeat( 'a', 20 ) . '<script>' ) );
	}

	public function test_valid_base_requires_https_outside_local_environments() {
		$this->assertSame( 'https://app.monoranks.com', Connection::valid_base( 'https://app.monoranks.com/' ) );
		$this->assertSame( '', Connection::valid_base( 'http://app.monoranks.com' ) );
		$this->assertSame( '', Connection::valid_base( '' ) );
	}

	public function test_valid_base_allows_http_on_a_local_environment() {
		Functions\when( 'wp_get_environment_type' )->justReturn( 'local' );
		$this->assertSame( 'http://localhost:3000', Connection::valid_base( 'http://localhost:3000/' ) );
	}
}
