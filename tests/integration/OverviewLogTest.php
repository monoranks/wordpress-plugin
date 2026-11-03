<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\Actions;
use MonoRanks\Overview;
use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/** Recent changes name things in plain words, and what the log stores is what Undo finds. */
class OverviewLogTest extends TestCase {

	private $term = 0;
	private $media = 0;
	private $user = 0;
	private $log;

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a WordPress install to run the integration tests.' );
		}
		defined( 'MONORANKS_CONNECTOR_VERSION' ) || define( 'MONORANKS_CONNECTOR_VERSION', 'test' );
		defined( 'MONORANKS_CONNECTOR_FILE' ) || define( 'MONORANKS_CONNECTOR_FILE', dirname( __DIR__, 2 ) . '/monoranks.php' );
		defined( 'MONORANKS_API_BASE' ) || define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->log = get_option( 'monoranks_change_log', null );
		delete_option( 'monoranks_change_log' );
	}

	protected function tear_down() {
		if ( defined( 'MONORANKS_TESTS_WP' ) ) {
			if ( $this->term ) {
				wp_delete_term( $this->term, 'category' );
			}
			if ( $this->media ) {
				wp_delete_post( $this->media, true );
			}
			if ( $this->user ) {
				wp_delete_user( $this->user );
			}
			delete_option( 'monoranks_ai_bots' );
			null === $this->log ? delete_option( 'monoranks_change_log' ) : update_option( 'monoranks_change_log', $this->log, false );
			wp_set_current_user( 0 );
		}
		parent::tear_down();
	}

	public function test_a_category_row_shows_its_name_and_a_deleted_post_its_number() {
		$this->term = (int) wp_insert_term( 'Shoes ' . wp_generate_password( 6, false ), 'category' )['term_id'];
		update_option(
			'monoranks_change_log',
			array(
				array( 'at' => gmdate( 'c' ), 'actor' => 'x', 'field' => 'seo_description', 'target' => 'term:category:' . $this->term, 'previous' => '', 'value' => 'a' ),
				array( 'at' => gmdate( 'c' ), 'actor' => 'x', 'field' => 'seo_title', 'target' => 'post:999999', 'previous' => '', 'value' => 'b' ),
			),
			false
		);
		$rows = Overview::log_rows( 10 );
		$this->assertSame( 'Page #999999', $rows[0]['where'] );
		$this->assertStringStartsWith( 'Shoes', $rows[1]['where'] );
	}

	public function test_undo_works_when_the_stored_value_was_cleaned() {
		$this->user = wp_insert_user( array( 'user_login' => 'mr' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		wp_set_current_user( $this->user );
		$this->media = wp_insert_attachment( array( 'post_title' => 'x', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
		$alt         = Writer::apply( array( array( 'id' => 'a', 'field' => 'alt', 'attachment_id' => $this->media, 'value' => '  <b>Red</b> shoes ', 'expected' => '' ) ), 'test' );
		$this->assertTrue( $alt[0]['ok'] );
		$this->assertSame( 'Red shoes', get_post_meta( $this->media, '_wp_attachment_image_alt', true ) );
		$bots = Writer::apply( array( array( 'id' => 'b', 'field' => 'ai_bots', 'value' => '{ "GPTBot": "deny", "Nope": "allow" }' ) ), 'test' );
		$this->assertTrue( $bots[0]['ok'] );
		$this->assertSame( 'undone', Actions::undo( 1 ) );
		$this->assertSame( 'undone', Actions::undo( 0 ) );
		$this->assertSame( '', get_post_meta( $this->media, '_wp_attachment_image_alt', true ) );
		$this->assertSame( '', get_option( 'monoranks_ai_bots', '' ) );
	}
}
