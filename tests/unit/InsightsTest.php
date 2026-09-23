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

	public function test_cache_is_stale_after_an_hour_including_when_unsupported() {
		$now = 1000000;
		$this->assertTrue( Insights::is_stale( array(), $now ) );
		$this->assertFalse( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3000 ), 'status' => 'ok' ), $now ) );
		$this->assertTrue( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3700 ), 'status' => 'ok' ), $now ) );
		// A MonoRanks that could not answer yet is asked again the next hour, so an update to it is picked up the same day.
		$this->assertFalse( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3000 ), 'status' => 'unsupported' ), $now ) );
		$this->assertTrue( Insights::is_stale( array( 'fetched_at' => gmdate( 'c', $now - 3700 ), 'status' => 'unsupported' ), $now ) );
	}

	public function test_an_applied_fix_leaves_the_cached_overview() {
		$this->options[ Insights::OPTION ] = array( 'overview' => array( 'fixes' => array( 'ready' => 2 ), 'ready' => array( array( 'id' => 'a' ), array( 'id' => 'b' ) ) ) );
		Insights::forget_ready( array( 'a' ) );
		$this->assertSame( array( array( 'id' => 'b' ) ), Insights::overview()['ready'] );
		$this->assertSame( 1, Insights::overview()['fixes']['ready'] );
	}

	/**
	 * A site with WP-Cron switched off (or a host that blocks the loopback request starting it) would never run the
	 * background refresh, and the screen would sit on "Waiting for the first audit" forever, so opening the plugin's
	 * own screen pulls the scores during that request.
	 */
	public function test_opening_the_plugin_screen_pulls_the_scores_without_wp_cron() {
		$calls = array();
		$this->options['monoranks_connection'] = array( 'key' => 'mr_site_test', 'api_base' => 'https://app.monoranks.com', 'key_state' => 'ok' );
		$transients = array();
		Functions\when( 'get_transient' )->alias( static function ( $k ) use ( &$transients ) { return isset( $transients[ $k ] ) ? $transients[ $k ] : false; } );
		Functions\when( 'set_transient' )->alias( static function ( $k, $v ) use ( &$transients ) { $transients[ $k ] = $v; return true; } );
		Functions\when( 'delete_transient' )->alias( static function ( $k ) use ( &$transients ) { unset( $transients[ $k ] ); return true; } );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias( static function ( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); } );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );
		Functions\when( 'spawn_cron' )->justReturn( null );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function ( $r ) { return $r['code']; } );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static function ( $r ) { return $r['body']; } );
		Functions\when( 'wp_remote_get' )->alias( static function ( $url, $args ) use ( &$calls ) {
			$calls[] = array( 'url' => $url, 'timeout' => $args['timeout'] );
			$body = false === strpos( $url, '/overview' )
				? array( 'pages' => array( array( 'post_id' => 7, 'health' => 52, 'audited_at' => '2026-09-22T19:38:46.962Z' ) ), 'next' => null )
				: array( 'site_id' => 'site_1', 'health' => 94, 'aeo' => 53, 'pages_scored' => 449, 'audited_at' => '2026-09-22T19:38:46.962Z' );
			return array( 'code' => 200, 'body' => wp_json_encode( $body ) );
		} );
		Functions\when( 'wp_json_encode' )->alias( static function ( $v ) { return json_encode( $v ); } );

		global $pagenow;
		$pagenow       = 'admin.php';
		$_GET['page']  = 'monoranks';
		Insights::maybe_schedule();

		$this->assertSame( 'ok', Insights::status(), 'the scores are there when the screen is drawn, not an hour later' );
		$this->assertSame( 94, Insights::overview()['health'] );
		$this->assertSame( 52, $this->meta[7][ Insights::META_HEALTH ] );
		$this->assertSame( 8, $calls[0]['timeout'], 'the screen waits seconds, not the full connector timeout' );
		$this->assertStringContainsString( '/api/connector/overview', $calls[0]['url'] );
	}
}
