<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\AiAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class AiAccessTest extends TestCase {
	/** @var array<string,mixed> */
	private $options = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$options = &$this->options;
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$options ) { $options[ $k ] = $v; return true; } );
		Functions\when( 'delete_option' )->alias( static function ( $k ) use ( &$options ) { unset( $options[ $k ] ); return true; } );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_robots_txt_is_untouched_without_approved_rules() {
		$this->assertSame( "User-agent: *\nDisallow:\n", AiAccess::robots_txt( "User-agent: *\nDisallow:\n" ) );
	}

	public function test_only_known_bots_and_modes_are_kept() {
		$this->assertTrue( AiAccess::set_bots( json_encode( array( 'GPTBot' => 'deny', 'ClaudeBot' => 'allow', 'EvilBot' => 'deny', 'PerplexityBot' => 'maybe' ) ) ) );
		$this->assertSame( array( 'GPTBot' => 'deny', 'ClaudeBot' => 'allow' ), json_decode( AiAccess::get_bots(), true ) );
		$this->assertFalse( AiAccess::set_bots( 'not json' ) );
	}

	public function test_robots_txt_gets_one_group_per_decided_bot() {
		AiAccess::set_bots( json_encode( array( 'GPTBot' => 'deny', 'ClaudeBot' => 'allow' ) ) );
		$out = AiAccess::robots_txt( "User-agent: *\nDisallow:\n" );
		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $out );
		$this->assertStringContainsString( "User-agent: ClaudeBot\nAllow: /", $out );
		$this->assertStringStartsWith( "User-agent: *\nDisallow:\n\n# AI crawlers", $out );
	}

	public function test_llms_txt_is_stored_as_plain_text_and_cleared_when_empty() {
		$this->assertTrue( AiAccess::set_llms( "# Site\n<b>bold</b>" ) );
		$this->assertSame( "# Site\nbold", AiAccess::get_llms() );
		$this->assertTrue( AiAccess::set_llms( '   ' ) );
		$this->assertSame( '', AiAccess::get_llms() );
		AiAccess::set_bots( '' );
		$this->assertSame( '', AiAccess::get_bots() );
	}
}
