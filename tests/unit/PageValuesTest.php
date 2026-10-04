<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Column;
use MonoRanks\PageValues;
use MonoRanks\PostPanel;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Per-page data: clicks and revenue per page path (one pull a day), lost links per page, and the post editor box's own
 * reads (the last change MonoRanks saw, the top searches).
 */
class PageValuesTest extends TestCase {
	/** @var array<string,mixed> */
	private $options = array();
	/** @var array<string,mixed> */
	private $transients = array();
	/** @var string[] */
	private $called = array();
	/** @var bool */
	private $admin = true;

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$options    = &$this->options;
		$transients = &$this->transients;
		$admin      = &$this->admin;
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'get_transient' )->alias( static function ( $k ) use ( &$transients ) { return array_key_exists( $k, $transients ) ? $transients[ $k ] : false; } );
		Functions\when( 'set_transient' )->alias( static function ( $k, $v ) use ( &$transients ) { $transients[ $k ] = $v; return true; } );
		Functions\when( 'delete_transient' )->alias( static function ( $k ) use ( &$transients ) { unset( $transients[ $k ] ); return true; } );
		Functions\when( 'sanitize_text_field' )->alias( static function ( $s ) { return trim( strip_tags( (string) $s ) ); } );
		Functions\when( 'esc_url_raw' )->alias( static function ( $u ) { return preg_match( '#^https?://#', (string) $u ) ? (string) $u : ''; } );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function ( $r ) { return $r['code']; } );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static function ( $r ) { return $r['body']; } );
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias( static function ( $one, $many, $n ) { return 1 === $n ? $one : $many; } );
		Functions\when( 'number_format_i18n' )->alias( static function ( $n, $d = 0 ) { return number_format( (float) $n, $d ); } );
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
		Functions\when( 'current_user_can' )->alias( static function () use ( &$admin ) { return $admin; } );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/pricing/' );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	private function answer( array $routes ) {
		$called = &$this->called;
		Functions\when( 'wp_remote_get' )->alias(
			static function ( $url ) use ( $routes, &$called ) {
				$called[] = $url;
				foreach ( $routes as $re => $r ) {
					if ( preg_match( $re, $url ) ) {
						return array( 'code' => $r[0], 'body' => (string) json_encode( $r[1] ) );
					}
				}
				return array( 'code' => 404, 'body' => '{"error":{"code":"no_route","message":"x"}}' );
			}
		);
	}

	private function connect() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_site_' . str_repeat( 'a', 30 ), 'site_id' => 'site_1' );
	}

	public function test_pages_match_on_their_path_whatever_the_host_case_query_or_slash() {
		$this->assertSame( '/pricing', PageValues::key( 'https://www.example.com/Pricing/?ref=x#top' ) );
		$this->assertSame( '/pricing', PageValues::key( '/pricing' ) );
		$this->assertSame( '/', PageValues::key( 'https://example.com' ) );
		$this->assertSame( '/', PageValues::key( '/' ) );
		$this->assertSame( '/café', PageValues::key( 'https://example.com/caf%C3%A9/' ) );
		$this->assertSame( '', PageValues::key( '(not set)' ) );
		$this->assertSame( '', PageValues::key( 'https://example.com/?p=12' ) );
		$this->assertSame( '/blog', PageValues::key( 'https://example.com/blog/?page=2' ) );
		$this->assertSame( '', PageValues::key( '' ) );
	}

	public function test_the_period_is_the_28_days_ending_three_days_ago() {
		$this->assertSame( array( 'from' => '2026-09-04', 'to' => '2026-10-01' ), PageValues::period( strtotime( '2026-10-04T12:00:00Z' ) ) );
	}

	public function test_with_analytics_one_call_gives_clicks_and_revenue() {
		$this->connect();
		$this->answer(
			array(
				'#/analytics/pages\?#' => array( 200, array( 'currency' => 'EUR', 'searchConsole' => 'joined', 'rows' => array(
					array( 'page' => '/pricing/', 'url' => 'https://example.com/pricing/', 'sessions' => 300, 'keyEvents' => 12, 'revenue' => 1240.456, 'clicks' => 210, 'impressions' => 5000, 'position' => 6.43 ),
					array( 'page' => '(not set)', 'url' => null, 'sessions' => 4, 'revenue' => 0 ),
				) ) ),
				'#/outreach\?source=lost_link#' => array( 200, array( 'targets' => array(
					array( 'domain' => 'old.test', 'url' => 'https://old.test/a', 'status' => 'to_contact', 'reason' => array( 'lostTo' => 'https://example.com/pricing/' ) ),
					array( 'domain' => 'done.test', 'url' => 'https://done.test/a', 'status' => 'added', 'reason' => array( 'lostTo' => 'https://example.com/pricing/' ) ),
					array( 'domain' => 'nowhere.test', 'url' => 'https://nowhere.test/', 'status' => 'to_contact', 'reason' => array() ),
				) ) ),
			)
		);
		$cache = PageValues::refresh();
		$this->assertCount( 2, $this->called );
		$this->assertStringContainsString( 'analytics/pages?limit=1000&sort=-revenue', $this->called[0] );
		$this->assertSame( array( 'ok', 'ok', 'EUR' ), array( $cache['analytics'], $cache['search'], $cache['currency'] ) );
		$this->assertSame( array( '/pricing' ), array_keys( $cache['pages'] ) );
		$this->assertSame( 1240.46, $cache['pages']['/pricing']['revenue'] );
		$this->assertSame( 6.4, $cache['pages']['/pricing']['position'] );
		$v = PageValues::for_url( 'https://example.com/pricing', $cache );
		$this->assertSame( 210, $v['row']['clicks'] );
		$this->assertSame( 1, $v['lost']['count'] );
		$this->assertSame( 'old.test', $v['lost']['top'][0]['domain'] );
	}

	public function test_without_analytics_it_falls_back_to_search_clicks() {
		$this->connect();
		$this->answer(
			array(
				'#/analytics/pages\?#' => array( 409, array( 'error' => array( 'code' => 'ga4_not_connected', 'message' => 'x' ) ) ),
				'#/search/rows\?dimension=page&#' => array( 200, array( 'rows' => array( array( 'page' => 'https://example.com/pricing/', 'clicks' => 40, 'impressions' => 900, 'position' => 8.04 ) ) ) ),
				'#/outreach\?#' => array( 403, array( 'error' => array( 'code' => 'missing_scope', 'message' => 'x' ) ) ),
			)
		);
		$cache = PageValues::refresh();
		$this->assertSame( array( 'not_connected', 'ok' ), array( $cache['analytics'], $cache['search'] ) );
		$this->assertMatchesRegularExpression( '#search/rows\?dimension=page&limit=1000&from=\d{4}-\d\d-\d\d&to=\d{4}-\d\d-\d\d$#', $this->called[1] );
		$this->assertSame( 40, $cache['pages']['/pricing']['clicks'] );
		$this->assertSame( array(), $cache['lost'] );
	}

	public function test_a_key_without_read_scopes_is_reported_as_such() {
		$this->connect();
		$this->answer( array( '#.#' => array( 403, array( 'error' => array( 'code' => 'missing_scope', 'message' => 'x' ) ) ) ) );
		$cache = PageValues::refresh();
		$this->assertSame( array( 'no_access', 'no_access' ), array( $cache['analytics'], $cache['search'] ) );
	}

	public function test_nothing_cached_means_no_values() {
		$this->assertNull( PageValues::for_url( 'https://example.com/pricing/', array() ) );
	}

	public function test_the_column_card_shows_clicks_revenue_and_lost_links_to_admins_only() {
		$this->transients[ PageValues::CACHE ] = array( 'fetched_at' => gmdate( 'c' ), 'analytics' => 'ok', 'search' => 'ok', 'currency' => '', 'pages' => array( '/pricing' => array( 'clicks' => 1200, 'revenue' => 80.5, 'sessions' => 10 ) ), 'lost' => array( '/pricing' => array( 'count' => 2, 'top' => array() ) ) );
		$post = (object) array( 'ID' => 5 );
		$rows = Column::value_rows( $post );
		$this->assertSame( array( 'Clicks, 28 days', '1,200' ), $rows[0] );
		$this->assertSame( 'Revenue, 28 days', $rows[1][0] );
		$this->assertStringContainsString( '80.50', $rows[1][1] );
		$this->assertSame( array( 'Lost links', '2 to win back' ), $rows[2] );
		$this->admin = false;
		$this->assertSame( array(), Column::value_rows( $post ) );
	}

	public function test_history_names_the_newest_change() {
		$out = PostPanel::normalise_history(
			array(
				'entries' => array(
					array( 'at' => '2026-10-02T04:00:00Z', 'changes' => array() ),
					array( 'at' => '2026-09-25T04:00:00Z', 'changes' => array( array( 'field' => 'title', 'before' => 'Old', 'after' => 'New' ), array( 'field' => 'wordCount', 'before' => 812, 'after' => 1040 ), array( 'field' => 'unknownField' ) ) ),
					array( 'at' => '2026-09-18T04:00:00Z', 'first' => true, 'changes' => array() ),
				),
				'link'    => 'https://app.monoranks.com/sites/site_1/pages/p1#history',
			)
		);
		$this->assertSame( 'ok', $out['state'] );
		$this->assertSame( '2026-09-25T04:00:00Z', $out['at'] );
		$this->assertSame( array( 'Title', 'Word count 812 → 1,040' ), $out['changes'] );
		$this->assertSame( 'unchanged', PostPanel::normalise_history( array( 'entries' => array( array( 'changes' => array() ) ) ) )['state'] );
		$this->assertSame( 'empty', PostPanel::normalise_history( array( 'entries' => array() ) )['state'] );
	}

	public function test_top_searches_for_the_page() {
		$out = PostPanel::normalise_queries( array( 'rows' => array( array( 'query' => 'widget pricing', 'page' => 'https://example.com/pricing/', 'clicks' => 30, 'position' => 3.26 ), array( 'query' => '' ) ) ) );
		$this->assertSame( array( array( 'query' => 'widget pricing', 'clicks' => 30, 'position' => 3.3 ) ), $out['rows'] );
		$this->assertSame( 'empty', PostPanel::normalise_queries( array( 'rows' => array() ) )['state'] );
	}

	public function test_the_post_reads_are_cached_per_post_and_url() {
		$this->connect();
		$this->answer(
			array(
				'#/pages/history\?#' => array( 404, array( 'error' => array( 'code' => 'not_found', 'message' => 'Page not found.' ) ) ),
				'#/search/rows\?dimension=query_page#' => array( 200, array( 'rows' => array() ) ),
			)
		);
		$one = PostPanel::detail( 5, 'https://example.com/pricing/' );
		$this->assertSame( 'empty', $one['history']['state'] );
		$this->assertStringContainsString( 'page=https%3A%2F%2Fexample.com%2Fpricing%2F', $this->called[1] );
		PostPanel::detail( 5, 'https://example.com/pricing/' );
		$this->assertCount( 2, $this->called );
		// A new address (the slug changed) reads again.
		PostPanel::detail( 5, 'https://example.com/plans/' );
		$this->assertCount( 4, $this->called );
	}
}
