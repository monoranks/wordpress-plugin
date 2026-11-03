<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use MonoRanks\Video;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/** The constant is set once per process, so the empty case comes first and the order matters. */
class VideoTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_no_button_without_an_id() {
		$this->assertSame( '', Video::id() );
		$this->assertNull( Video::links() );
	}

	/** @depends test_no_button_without_an_id */
	public function test_the_links_use_the_id() {
		define( 'MONORANKS_VIDEO_ID', 'aqz-KE-bpKQ' );
		$links = Video::links();
		$this->assertSame( 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', $links['watch'] );
		$this->assertStringStartsWith( 'https://www.youtube.com/embed/aqz-KE-bpKQ', $links['embed'] );
		$this->assertSame( array( 'watch', 'embed' ), array_keys( $links ) );
	}
}
