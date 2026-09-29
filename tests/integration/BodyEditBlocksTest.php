<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The body edit against real WordPress (monoranks/monoranks#48): the opening goes in as its own core/paragraph block,
 * other plugins' blocks keep their exact markup, a write that breaks a block is rolled back, classic posts are unchanged.
 */
class BodyEditBlocksTest extends TestCase {

	const OPENING = '<p>MonoRanks puts the short answer first, before anything else on the page.</p>';

	private $posts = array();
	private $log;

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a local WordPress install to run the integration tests.' );
		}
		$this->log = get_option( 'monoranks_change_log', null );
	}

	protected function tear_down() {
		if ( defined( 'MONORANKS_TESTS_WP' ) ) {
			remove_all_filters( 'content_save_pre', 99 );
			foreach ( $this->posts as $id ) {
				wp_delete_post( $id, true );
			}
			null === $this->log ? delete_option( 'monoranks_change_log' ) : update_option( 'monoranks_change_log', $this->log, false );
		}
		parent::tear_down();
	}

	private function post( $content ) {
		// Created without kses so the fixture markup is stored exactly as written, the way an admin's editor stores it.
		kses_remove_filters();
		$id = wp_insert_post( array( 'post_title' => 'MonoRanks block test', 'post_status' => 'publish', 'post_content' => wp_slash( $content ) ) );
		kses_init_filters();
		$this->posts[] = $id;
		return $id;
	}

	private function content( $id ) {
		clean_post_cache( $id );
		return (string) get_post_field( 'post_content', $id, 'raw' );
	}

	private function write( $id, $value, $op = 'insert_top', $expected = null ) {
		$c = array( 'id' => 't', 'field' => 'content', 'post_id' => $id, 'value' => $value, 'op' => $op );
		if ( null !== $expected ) {
			$c['expected'] = $expected;
		}
		return Writer::apply( array( $c ), 'test' )[0];
	}

	public function test_insert_into_a_block_post_adds_one_valid_paragraph_block_and_undo_restores_it() {
		$body = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Intro</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Existing text.</p>\n<!-- /wp:paragraph -->";
		$id   = $this->post( $body );

		$r = $this->write( $id, self::OPENING );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$stored = $this->content( $id );
		$this->assertSame( Writer::paragraph_block( self::OPENING ) . $body, $stored );

		$first = parse_blocks( $stored )[0];
		$this->assertSame( 'core/paragraph', $first['blockName'] );
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'core/paragraph' ) );
		$this->assertSame( rtrim( Writer::paragraph_block( self::OPENING ) ), serialize_block( $first ) );
		$this->assertSame( self::OPENING, trim( $first['innerHTML'] ) );

		// Undo goes through the same checked path and gives back the post byte for byte.
		$log  = get_option( 'monoranks_change_log' );
		$undo = Writer::apply( array( Writer::reverse( end( $log ) ) ), 'test' )[0];
		$this->assertTrue( $undo['ok'], wp_json_encode( $undo ) );
		$this->assertSame( $body, $this->content( $id ) );
	}

	public function test_third_party_blocks_keep_their_exact_markup() {
		// Markup kses would strip for a user without unfiltered_html (the iframe, the data attribute, the unregistered
		// blocks' attributes): the write must leave all of it alone.
		$body = "<!-- wp:rank-math/toc-block {\"title\":\"Table of Contents\",\"headings\":[{\"key\":\"a\",\"content\":\"Intro\",\"level\":2,\"link\":\"#intro\"}]} -->\n<div class=\"wp-block-rank-math-toc-block\" data-toc=\"1\"><h2>Table of Contents</h2><nav><ul><li><a href=\"#intro\">Intro</a></li></ul></nav></div>\n<!-- /wp:rank-math/toc-block -->\n\n"
			. "<!-- wp:yoast/faq-block {\"questions\":[{\"id\":\"faq-1\",\"question\":[\"Why?\"],\"answer\":[\"Because.\"]}]} -->\n<div class=\"schema-faq wp-block-yoast-faq-block\"><div class=\"schema-faq-section\" id=\"faq-1\"><strong class=\"schema-faq-question\">Why?</strong> <p class=\"schema-faq-answer\">Because.</p> </div></div>\n<!-- /wp:yoast/faq-block -->\n\n"
			. "<!-- wp:html -->\n<iframe src=\"https://www.youtube.com/embed/x\" data-x=\"1\"></iframe>\n<!-- /wp:html -->";
		$id   = $this->post( $body );
		$this->assertSame( $body, $this->content( $id ) );

		$r = $this->write( $id, self::OPENING );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$stored = $this->content( $id );
		$this->assertSame( Writer::paragraph_block( self::OPENING ) . $body, $stored );

		$names = array_values( array_filter( wp_list_pluck( parse_blocks( $stored ), 'blockName' ) ) );
		$this->assertSame( array( 'core/paragraph', 'rank-math/toc-block', 'yoast/faq-block', 'core/html' ), $names );

		$r = $this->write( $id, '', 'remove', self::OPENING );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$this->assertSame( $body, $this->content( $id ) );
	}

	public function test_a_write_that_breaks_a_block_is_rolled_back() {
		$body = "<!-- wp:rank-math/toc-block {\"title\":\"Contents\"} -->\n<div class=\"wp-block-rank-math-toc-block\"><h2>Contents</h2></div>\n<!-- /wp:rank-math/toc-block -->";
		$id   = $this->post( $body );

		// Something on the save path drops the closing delimiter of the new block.
		add_filter( 'content_save_pre', function ( $c ) {
			return preg_replace( '#<!-- /wp:paragraph -->#', '', $c, 1 );
		}, 99 );
		$r = $this->write( $id, self::OPENING );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'block_check_failed', $r['error'] );
		$this->assertStringContainsString( 'put back', $r['reason'] );
		$this->assertSame( $body, $this->content( $id ) );
		remove_all_filters( 'content_save_pre', 99 );

		// Something rewrites another plugin's block on the way in: rolled back too.
		add_filter( 'content_save_pre', function ( $c ) {
			return str_replace( '{\\"title\\":\\"Contents\\"}', '{}', $c );
		}, 99 );
		$r = $this->write( $id, self::OPENING );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'block_check_failed', $r['error'] );
		$this->assertSame( $body, $this->content( $id ) );
		remove_all_filters( 'content_save_pre', 99 );

		// Undo that would break a block is refused the same way and leaves the opening in place.
		$this->assertTrue( $this->write( $id, self::OPENING )['ok'] );
		$with = $this->content( $id );
		add_filter( 'content_save_pre', function ( $c ) {
			return str_replace( '<!-- /wp:rank-math/toc-block -->', '', $c );
		}, 99 );
		$r = $this->write( $id, '', 'remove', self::OPENING );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'block_check_failed', $r['error'] );
		$this->assertSame( $with, $this->content( $id ) );
	}

	public function test_classic_posts_keep_the_marker_wrapped_paragraph() {
		$body = "<p>Classic post text.</p>\n<p>More text.</p>";
		$id   = $this->post( $body );

		$r = $this->write( $id, self::OPENING );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$this->assertSame( Writer::CONTENT_MARK_START . "\n" . self::OPENING . "\n" . Writer::CONTENT_MARK_END . "\n\n" . $body, $this->content( $id ) );

		$r = $this->write( $id, '', 'remove', self::OPENING );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$this->assertSame( $body, $this->content( $id ) );
	}

	public function test_an_opening_written_by_an_older_version_is_replaced_by_the_block_in_a_block_post() {
		$body   = "<!-- wp:paragraph -->\n<p>Existing text.</p>\n<!-- /wp:paragraph -->";
		$legacy = Writer::CONTENT_MARK_START . "\n" . self::OPENING . "\n" . Writer::CONTENT_MARK_END . "\n\n";
		$id     = $this->post( $legacy . $body );

		$r = $this->write( $id, self::OPENING );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$this->assertSame( Writer::paragraph_block( self::OPENING ) . $body, $this->content( $id ) );
	}
}
