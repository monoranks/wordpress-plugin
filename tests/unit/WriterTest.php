<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class WriterTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_a_logged_change_reverses_into_the_change_that_restores_it() {
		$c = Writer::reverse( array( 'field' => 'seo_title', 'target' => 'post:12', 'previous' => 'Old title', 'value' => 'New title' ) );
		$this->assertSame( array( 'id' => 'undo', 'field' => 'seo_title', 'value' => 'Old title', 'expected' => 'New title', 'post_id' => 12 ), $c );
		$this->assertSame( 9, Writer::reverse( array( 'field' => 'alt', 'target' => 'attachment:9', 'previous' => '', 'value' => 'A dog' ) )['attachment_id'] );
		$this->assertSame( '/old/', Writer::reverse( array( 'field' => 'redirect', 'target' => '/old/', 'previous' => '', 'value' => '/new/' ) )['from'] );
		$this->assertSame( 'remove', Writer::reverse( array( 'field' => 'content', 'target' => 'post:3', 'previous' => '', 'value' => '<p>Hi</p>' ) )['op'] );
		$this->assertArrayNotHasKey( 'post_id', Writer::reverse( array( 'field' => 'ai_bots', 'target' => 'site', 'previous' => '', 'value' => 'x' ) ) );
	}

	public function test_rows_that_cannot_be_reversed_give_null() {
		$this->assertNull( Writer::reverse( array( 'field' => 'plugins', 'target' => 'post:1', 'previous' => '', 'value' => '' ) ) );
		$this->assertNull( Writer::reverse( array( 'field' => 'seo_title', 'target' => 'nowhere', 'previous' => '', 'value' => '' ) ) );
	}
}
