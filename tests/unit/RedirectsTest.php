<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Redirects;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class RedirectsTest extends TestCase {
	/** @var array<string,mixed> */
	private $options = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$options = &$this->options;
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$options ) { $options[ $k ] = $v; return true; } );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( (string) $s, '/' ); } );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_redirects_are_keyed_by_path_without_trailing_slash() {
		Redirects::set( 'https://example.com/old-page/', 'https://example.com/new-page/' );
		$this->assertSame( 'https://example.com/new-page/', Redirects::get( '/old-page' ) );
		$this->assertSame( 'https://example.com/new-page/', Redirects::get( 'https://other.host/old-page/?x=1' ) );
		$this->assertSame( '', Redirects::get( '/other' ) );
	}

	public function test_an_empty_target_removes_the_redirect() {
		Redirects::set( '/old', '/new' );
		Redirects::set( '/old', '' );
		$this->assertSame( array(), Redirects::all() );
	}
}
