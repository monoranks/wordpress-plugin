<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Handoff;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The connect link's way back for sites MonoRanks cannot reach: the state must match the one stored for this user, the code
 * is traded for the key with a direct request, and nothing happens without manage_options.
 */
class HandoffTest extends TestCase {

	const STATE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	const CODE  = 'Zm9vYmFyYmF6cXV4cXV1eGNvcmdlZ3JhdWx0Z2FycGx5';

	/** @var array<string,mixed> */
	private $store = array();
	/** @var array<int,array{0:string,1:array}> */
	private $posts = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$this->store = array( 'monoranks_connect_7' => array( 'state' => self::STATE, 'base' => 'https://app.monoranks.com' ) );
		$this->posts = array();
		$store       = &$this->store;
		$posts       = &$this->posts;
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_transient' )->alias( static function ( $k ) use ( &$store ) { return isset( $store[ $k ] ) ? $store[ $k ] : false; } );
		Functions\when( 'delete_transient' )->alias( static function ( $k ) use ( &$store ) { unset( $store[ $k ] ); return true; } );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( (string) $s, '/' ); } );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'home_url' )->justReturn( 'https://wordpress-new.test/' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function ( $r ) { return $r['response']['code']; } );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static function ( $r ) { return $r['body']; } );
		Functions\when( 'wp_remote_post' )->alias(
			static function ( $url, $args ) use ( &$posts ) {
				$posts[] = array( $url, $args );
				return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'key' => 'mr_site_' . str_repeat( 'k', 32 ), 'site_id' => 'site-1' ) ) );
			}
		);
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_good_state_trades_the_code_for_the_key_once() {
		$got = Handoff::redeem( self::CODE, self::STATE );
		$this->assertSame( array( 'key' => 'mr_site_' . str_repeat( 'k', 32 ), 'site_id' => 'site-1', 'base' => 'https://app.monoranks.com' ), $got );
		$this->assertCount( 1, $this->posts );
		$this->assertSame( 'https://app.monoranks.com/api/wordpress/claim', $this->posts[0][0] );
		$this->assertSame( array( 'code' => self::CODE, 'site' => 'https://wordpress-new.test' ), json_decode( $this->posts[0][1]['body'], true ) );
		$this->assertSame( 0, $this->posts[0][1]['redirection'] );
		$this->assertArrayNotHasKey( 'monoranks_connect_7', $this->store, 'the state is used up' );
		$this->assertNull( Handoff::redeem( self::CODE, self::STATE ), 'the same link cannot be replayed' );
	}

	public function test_bad_state_is_refused_without_calling_monoranks() {
		$this->assertNull( Handoff::redeem( self::CODE, str_repeat( 'b', 40 ) ) );
		$this->assertNull( Handoff::redeem( self::CODE, '' ) );
		$this->assertSame( 'connect_failed', Handoff::claim( self::CODE, str_repeat( 'b', 40 ) ) );
		$this->assertCount( 0, $this->posts );
		$this->assertArrayHasKey( 'monoranks_connect_7', $this->store, 'a wrong state does not use up the right one' );
	}

	public function test_another_users_state_is_refused() {
		Functions\when( 'get_current_user_id' )->justReturn( 8 );
		$this->assertNull( Handoff::redeem( self::CODE, self::STATE ) );
		$this->assertCount( 0, $this->posts );
	}

	public function test_failed_claim_gives_no_key() {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 400 ), 'body' => '{"error":"bad_request"}' ) );
		$this->assertSame( 'connect_failed', Handoff::claim( self::CODE, self::STATE ) );
		$this->assertArrayNotHasKey( 'monoranks_connect_7', $this->store );
	}

	public function test_claim_answer_without_a_valid_key_gives_no_key() {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ), 'body' => '{"key":"not-a-key","site_id":"s"}' ) );
		$this->assertNull( Handoff::redeem( self::CODE, self::STATE ) );
	}

	public function test_malformed_code_is_not_sent() {
		$this->assertNull( Handoff::redeem( 'short', self::STATE ) );
		$this->assertCount( 0, $this->posts );
	}

	public function test_without_manage_options_nothing_happens() {
		global $pagenow;
		$pagenow = 'admin.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test
		$_GET    = array( 'page' => 'monoranks-settings', 'monoranks_code' => self::CODE, 'monoranks_state' => self::STATE );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_safe_redirect' )->never();
		Handoff::maybe_claim();
		$this->assertCount( 0, $this->posts );
		$this->assertArrayHasKey( 'monoranks_connect_7', $this->store );
		$_GET = array();
	}

	public function test_other_admin_pages_are_left_alone() {
		global $pagenow;
		$pagenow = 'admin.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test
		$_GET    = array( 'page' => 'monoranks', 'monoranks_code' => self::CODE, 'monoranks_state' => self::STATE );
		Functions\expect( 'current_user_can' )->never();
		Handoff::maybe_claim();
		$this->assertCount( 0, $this->posts );
		$_GET = array();
	}
}
