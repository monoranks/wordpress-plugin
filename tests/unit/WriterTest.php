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

	public function test_the_opening_block_is_a_core_paragraph_with_its_own_delimiters() {
		$this->assertSame(
			"<!-- wp:paragraph {\"metadata\":{\"name\":\"MonoRanks opening\"}} -->\n<p>Short answer <strong>first</strong>.</p>\n<!-- /wp:paragraph -->\n\n",
			Writer::paragraph_block( '<p>Short answer <strong>first</strong>.</p>' )
		);
		$this->assertSame( '<p>Two</p>', Writer::paragraph_html( 'Two' ) );
		$this->assertTrue( Writer::is_block_content( "<!-- wp:heading -->\n<h2>A</h2>\n<!-- /wp:heading -->" ) );
		$this->assertFalse( Writer::is_block_content( '<p>Classic post</p>' ) );
	}

	public function test_only_our_own_paragraph_is_found_never_another_block() {
		$third = "<!-- wp:rank-math/toc-block {\"title\":\"Contents\"} -->\n<div class=\"wp-block-rank-math-toc-block\"><h2>Contents</h2></div>\n<!-- /wp:rank-math/toc-block -->";
		$plain = "<!-- wp:paragraph -->\n<p>Somebody else's paragraph</p>\n<!-- /wp:paragraph -->\n\n";
		$this->assertNull( Writer::find_opening( $plain . $third ) );

		$ours  = Writer::paragraph_block( '<p>Ours</p>' );
		$found = Writer::find_opening( $ours . $plain . $third );
		$this->assertSame( array( 'start' => 0, 'length' => strlen( $ours ), 'html' => '<p>Ours</p>' ), $found );
		$this->assertSame( $plain . $third, substr_replace( $ours . $plain . $third, '', $found['start'], $found['length'] ) );

		$legacy = Writer::CONTENT_MARK_START . "\n<p>Old</p>\n" . Writer::CONTENT_MARK_END . "\n\n";
		$this->assertSame( '<p>Old</p>', Writer::find_opening( $legacy . '<p>Classic</p>' )['html'] );
	}

	public function test_a_save_that_changed_the_markup_fails_the_check() {
		$this->assertSame( 'WordPress or another plugin changed the post markup while saving it.', Writer::check_stored( '<p>A</p>', '', '<p>B</p>', '' ) );
		$this->assertNull( Writer::check_stored( '<p>Classic</p>', '', '<p>Classic</p>', '' ) );
	}
}
