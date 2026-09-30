<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Api;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ApiTest extends TestCase {
	/** @var array<string,mixed> */
	private $options = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$options = &$this->options;
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$options ) { $options[ $k ] = $v; return true; } );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function ( $r ) { return $r['code']; } );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static function ( $r ) { return $r['body']; } );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_get_is_not_attempted_without_a_key() {
		$this->assertSame( 'not_connected', Api::get( '/overview' )['error'] );
	}

	public function test_get_sends_the_key_and_reads_json() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) );
		Functions\expect( 'wp_remote_get' )->once()->withArgs( static function ( $url, $args ) {
			return 'https://app.monoranks.com/api/connector/overview' === $url && 'Bearer mr_ws_' . str_repeat( 'a', 30 ) === $args['headers']['Authorization'];
		} )->andReturn( array( 'code' => 200, 'body' => '{"health":74}' ) );
		$res = Api::get( '/overview' );
		$this->assertSame( 200, $res['code'] );
		$this->assertSame( 74, $res['body']['health'] );
		$this->assertNull( $res['error'] );
	}

	public function test_a_404_not_supported_is_reported_without_marking_the_key() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'code' => 404, 'body' => '{"error":"not_supported"}' ) );
		$res = Api::get( '/pages' );
		$this->assertSame( 'not_supported', $res['error'] );
		$this->assertArrayNotHasKey( 'key_state', $this->options['monoranks_connection'] );
	}

	public function test_a_401_marks_the_key_revoked() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'code' => 401, 'body' => '' ) );
		Api::get( '/overview' );
		$this->assertSame( 'revoked', $this->options['monoranks_connection']['key_state'] );
	}

	public function test_a_status_the_app_refuses_for_a_new_seo_plugin_value_is_sent_again_as_none() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$sent = array();
		Functions\when( 'wp_remote_post' )->alias( static function ( $url, $args ) use ( &$sent ) {
			$body   = json_decode( $args['body'], true );
			$sent[] = $body['seo_plugin'];
			return 'tsf' === $body['seo_plugin'] ? array( 'code' => 400, 'body' => '{"error":"bad_payload"}' ) : array( 'code' => 200, 'body' => '{"ok":true}' );
		} );
		$res = Api::send_status( array( 'site_url' => 'https://example.com', 'seo_plugin' => 'tsf', 'seo_plugin_name' => 'The SEO Framework' ) );
		$this->assertSame( 200, $res['code'] );
		$this->assertSame( array( 'tsf', 'none' ), $sent );

		// A known value that is refused is not sent twice.
		$sent = array();
		Functions\when( 'wp_remote_post' )->alias( static function ( $url, $args ) use ( &$sent ) {
			$sent[] = json_decode( $args['body'], true )['seo_plugin'];
			return array( 'code' => 400, 'body' => '{"error":"bad_payload"}' );
		} );
		$this->assertSame( 400, Api::send_status( array( 'seo_plugin' => 'yoast' ) )['code'] );
		$this->assertSame( array( 'yoast' ), $sent );
	}
}
