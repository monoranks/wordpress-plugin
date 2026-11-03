<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Video;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/** The constant is set once per process, so the empty case comes first and the order matters. */
class VideoTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'plugins_url' )->alias( static function ( $p ) { return 'https://site.test/' . $p; } );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_no_card_without_an_id() {
		$this->assertSame( '', Video::id() );
		$this->assertNull( Video::card() );
	}

	/** @depends test_no_card_without_an_id */
	public function test_the_card_uses_only_the_plugins_poster_and_stays_away_after_dismissing() {
		define( 'MONORANKS_VIDEO_ID', 'aqz-KE-bpKQ' );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		$card = Video::card();
		$this->assertSame( 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', $card['watch'] );
		$this->assertStringStartsWith( 'https://www.youtube.com/embed/aqz-KE-bpKQ', $card['embed'] );
		$this->assertSame( 'https://site.test/resources/assets/video-poster.jpg', $card['poster'] );
		Functions\when( 'get_user_meta' )->justReturn( 'aqz-KE-bpKQ' );
		$this->assertNull( Video::card() );
		// A new video shows once more.
		Functions\when( 'get_user_meta' )->justReturn( 'older-video' );
		$this->assertNotNull( Video::card() );
	}
}
