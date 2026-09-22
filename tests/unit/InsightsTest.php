<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Insights;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class InsightsTest extends TestCase {
	/** @var array<string,mixed> */
	private $options = array();
	/** @var array<int,array<string,mixed>> */
	private $meta = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$options = &$this->options;
		$meta    = &$this->meta;
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$options ) { $options[ $k ] = $v; return true; } );
		Functions\when( 'delete_option' )->alias( static function ( $k ) use ( &$options ) { unset( $options[ $k ] ); return true; } );
		Functions\when( 'update_post_meta' )->alias( static function ( $id, $k, $v ) use ( &$meta ) { $meta[ $id ][ $k ] = $v; return true; } );
		Functions\when( 'delete_post_meta' )->alias( static function ( $id, $k ) use ( &$meta ) { unset( $meta[ $id ][ $k ] ); return true; } );
		Functions\when( 'get_post_meta' )->alias( static function ( $id, $k ) use ( &$meta ) { return isset( $meta[ $id ][ $k ] ) ? $meta[ $id ][ $k ] : ''; } );
		Functions\when( 'sanitize_text_field' )->alias( static function ( $s ) { return trim( strip_tags( (string) $s ) ); } );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'url_to_postid' )->alias( static function ( $url ) { return '/known/' === parse_url( $url, PHP_URL_PATH ) ? 42 : 0; } );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_scores_are_clamped_and_null_stays_null() {
		$this->assertSame( 100, Insights::score( 140 ) );
		$this->assertSame( 0, Insights::score( -3 ) );
		$this->assertSame( 74, Insights::score( '73.6' ) );
		$this->assertNull( Insights::score( null ) );
		$this->assertNull( Insights::score( 'n/a' ) );
	}

	public function test_tone_follows_the_app_thresholds() {
		$this->assertSame( 'good', Insights::tone( 80 ) );
		$this->assertSame( 'warn', Insights::tone( 79 ) );
		$this->assertSame( 'warn', Insights::tone( 60 ) );
		$this->assertSame( 'critical', Insights::tone( 59 ) );
		$this->assertSame( 'none', Insights::tone( null ) );
	}

	public function test_overview_is_normalised_and_unknown_fields_dropped() {
		$o = Insights::normalise_overview( array(
			'site_id'   => 'site_1',
			'health'    => 74.4,
			'aeo'       => '61',
			'deltas'    => array( 'health' => 3, 'aeo' => -2 ),
			'traffic'   => array( 'clicks_28d' => 12480, 'delta_pct' => 8, 'series' => array( 1, 2, -5 ) ),
			'attention' => array( array( 'post_id' => 5, 'title' => '<b>Pricing</b>', 'health' => 38, 'aeo' => 44, 'issue' => 'Meta description missing', 'page_url' => 'https://app.monoranks.com/sites/site_1/pages/9', 'url' => 'http://insecure.example/' ) ),
			'ready'     => array(
				array( 'id' => 'fix_1', 'field' => 'seo_title', 'post_id' => 5, 'before' => 'Old', 'after' => 'New', 'page_url' => 'javascript:alert(1)' ),
				array( 'id' => 'fix_2', 'field' => 'plugins', 'after' => 'nope' ),
			),
			'junk'      => 'ignored',
		) );
		$this->assertSame( 74, $o['health'] );
		$this->assertSame( 61, $o['aeo'] );
		$this->assertSame( -2, $o['deltas']['aeo'] );
		$this->assertSame( array( 1, 2, 0 ), $o['traffic']['series'] );
		$this->assertSame( 'Pricing', $o['attention'][0]['title'] );
		$this->assertSame( '', $o['attention'][0]['url'], 'http page links are dropped outside local environments' );
		$this->assertCount( 1, $o['ready'], 'fields Writer cannot write are dropped' );
		$this->assertSame( '', $o['ready'][0]['page_url'] );
		$this->assertArrayNotHasKey( 'junk', $o );
	}

	public function test_an_overview_without_any_data_is_null() {
		$this->assertNull( Insights::normalise_overview( array( 'site_id' => 'x', 'health' => null, 'aeo' => null, 'pages_scored' => 0 ) ) );
	}

	public function test_a_page_row_is_matched_by_post_id_or_url() {
		$this->assertSame( 7, Insights::normalise_page( array( 'post_id' => 7, 'health' => 88 ) )['post_id'] );
		$this->assertSame( 42, Insights::normalise_page( array( 'url' => 'https://example.com/known/', 'health' => 50 ) )['post_id'] );
		$this->assertNull( Insights::normalise_page( array( 'url' => 'https://example.com/unknown/' ) ) );
	}

	public function test_scores_land_in_post_meta_with_a_sortable_health_value() {
		Insights::set_post_score( 7, Insights::normalise_page( array( 'post_id' => 7, 'health' => 52, 'aeo' => 31, 'fixes_ready' => 3, 'audited_at' => '2026-09-20T04:10:00Z' ) ) );
		$this->assertSame( 52, $this->meta[7][ Insights::META_HEALTH ] );
		$this->assertSame( 31, Insights::post_score( 7 )['aeo'] );
		Insights::set_post_score( 7, Insights::normalise_page( array( 'post_id' => 7, 'health' => null ) ) );
		$this->assertArrayNotHasKey( Insights::META_HEALTH, $this->meta[7] );
	}

	public function test_cache_is_stale_after_an_hour_or_a_day_when_unsupported() {
		$now = 1000000;
		$this->assertTrue( Insights::is_stale( array(), $now ) );
		$this->assertFalse( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3000 ), 'status' => 'ok' ), $now ) );
		$this->assertTrue( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3700 ), 'status' => 'ok' ), $now ) );
		$this->assertFalse( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3700 ), 'status' => 'unsupported' ), $now ) );
		$this->assertTrue( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 90000 ), 'status' => 'unsupported' ), $now ) );
	}

	public function test_an_applied_fix_leaves_the_cached_overview() {
		$this->options[ Insights::OPTION ] = array( 'overview' => array( 'fixes' => array( 'ready' => 2 ), 'ready' => array( array( 'id' => 'a' ), array( 'id' => 'b' ) ) ) );
		Insights::forget_ready( array( 'a' ) );
		$this->assertSame( array( array( 'id' => 'b' ) ), Insights::overview()['ready'] );
		$this->assertSame( 1, Insights::overview()['fixes']['ready'] );
	}
}
