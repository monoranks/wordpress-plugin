<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Api;
use MonoRanks\Grow;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The Grow cards (Overview → What to do next): the API v1 reads, how each answer is shaped and sanitised, and the plain
 * states a card shows when the key cannot read, the API is off, or MonoRanks is down.
 */
class GrowTest extends TestCase {
	/** @var array<string,mixed> */
	private $options = array();
	/** @var array<string,mixed> */
	private $transients = array();
	/** @var string[] */
	private $called = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$options    = &$this->options;
		$transients = &$this->transients;
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$options ) { $options[ $k ] = $v; return true; } );
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
		Functions\when( 'number_format_i18n' )->alias( static function ( $n ) { return (string) $n; } );
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );
		Functions\when( 'spawn_cron' )->justReturn( true );
		Functions\when( 'wp_date' )->justReturn( '1 Oct 2026' );
		Functions\when( 'human_time_diff' )->justReturn( '3 days' );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	private function connect() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_site_' . str_repeat( 'a', 30 ), 'site_id' => 'site_1' );
	}

	/** Answers each v1 path with the first matching [regex => [code, body]] and records the calls. */
	private function answer( array $routes ) {
		$called = &$this->called;
		Functions\when( 'wp_remote_get' )->alias(
			static function ( $url ) use ( $routes, &$called ) {
				$called[] = $url;
				foreach ( $routes as $re => $r ) {
					if ( preg_match( $re, $url ) ) {
						return array( 'code' => $r[0], 'body' => wp_json_encode_for_test( $r[1] ) );
					}
				}
				return array( 'code' => 404, 'body' => '{"error":{"code":"no_route","message":"x"}}' );
			}
		);
	}

	public function test_v1_reads_go_to_this_website_with_the_key() {
		$this->connect();
		Functions\expect( 'wp_remote_get' )->once()->withArgs( static function ( $url, $args ) {
			return 'https://app.monoranks.com/api/v1/sites/site_1/backlinks?view=new' === $url && 'Bearer mr_site_' . str_repeat( 'a', 30 ) === $args['headers']['Authorization'];
		} )->andReturn( array( 'code' => 403, 'body' => '{"error":{"code":"missing_scope","message":"no"}}' ) );
		$res = Api::v1_get( 'backlinks?view=new' );
		$this->assertSame( 'missing_scope', $res['error'] );
		$this->assertSame( 'no_access', Api::v1_state( $res ) );
		// A missing read scope never marks the key revoked.
		$this->assertArrayNotHasKey( 'key_state', $this->options['monoranks_connection'] );
	}

	public function test_failed_reads_become_plain_states() {
		$this->assertSame( 'off', Api::v1_state( array( 'code' => 503, 'error' => 'api_disabled' ) ) );
		$this->assertSame( 'off', Api::v1_state( array( 'code' => 404, 'error' => 'no_route' ) ) );
		$this->assertSame( 'off', Api::v1_state( array( 'code' => 0, 'error' => 'no_site_id' ) ) );
		$this->assertSame( 'error', Api::v1_state( array( 'code' => 500, 'error' => 'internal' ) ) );
		$this->assertSame( 'error', Api::v1_state( array( 'code' => 0, 'error' => 'unreachable' ) ) );
		$this->assertSame( 'error', Api::v1_state( array( 'code' => 429, 'error' => 'rate_limited' ) ) );
	}

	public function test_without_a_site_id_nothing_is_called() {
		$this->options['monoranks_connection'] = array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_site_' . str_repeat( 'a', 30 ) );
		Functions\expect( 'wp_remote_get' )->never();
		$this->assertSame( 'no_site_id', Api::v1_get( 'outreach' )['error'] );
	}

	public function test_outreach_keeps_the_top_three_with_why_and_never_names_a_model() {
		$out = Grow::normalise_outreach(
			array(
				'counts'  => array( 'all' => 9, 'status' => array( 'to_contact' => 7, 'added' => 1 ) ),
				'targets' => array(
					array( 'source' => 'ai_source', 'url' => 'https://list.test/best/', 'domain' => 'list.test', 'title' => 'Best <b>widgets</b>', 'score' => 91, 'reason' => array( 'questions' => array( 'best widget' ), 'models' => array( 'some-model' ) ), 'link' => 'https://app.monoranks.com/sites/site_1/outreach?open=t1' ),
					array( 'source' => 'link_gap', 'url' => 'https://dir.test/', 'domain' => 'dir.test', 'reason' => array( 'competitors' => array( 'a.test', 'b.test' ) ) ),
					array( 'source' => 'lost_link', 'url' => 'http://old.test/post', 'domain' => 'old.test', 'reason' => array( 'lostTo' => 'https://example.com/pricing/' ) ),
					array( 'source' => 'best_list', 'url' => 'https://fourth.test/' ),
					array( 'source' => 'manual', 'url' => 'javascript:alert(1)' ),
				),
				'link'    => 'https://app.monoranks.com/sites/site_1/outreach',
			)
		);
		$this->assertSame( 'ok', $out['state'] );
		$this->assertSame( 7, $out['to_contact'] );
		$this->assertCount( 3, $out['top'] );
		$this->assertSame( 'Best widgets', $out['top'][0]['title'] );
		$this->assertSame( 'AI answers quote this page for "best widget"', $out['top'][0]['why'] );
		$this->assertStringNotContainsString( 'some-model', wp_json_encode_for_test( $out ) );
		$this->assertSame( 'Links to 2 of your competitors, not to you', $out['top'][1]['why'] );
		$this->assertSame( 'dir.test', $out['top'][1]['title'] );
		$this->assertSame( 'Used to link to /pricing/', $out['top'][2]['why'] );
		$this->assertSame( 'http://old.test/post', $out['top'][2]['url'] );
		$this->assertSame( 'empty', Grow::normalise_outreach( array( 'counts' => array( 'status' => array( 'to_contact' => 0 ) ), 'targets' => array() ) )['state'] );
	}

	public function test_backlinks_summary_and_newest_strong_domains() {
		$out = Grow::normalise_backlinks(
			array(
				'summary' => array( 'referringDomains' => 120, 'new28Days' => 6, 'lost28Days' => 2, 'domainRank' => 41 ),
				'counts'  => array( 'review' => 3 ),
				'domains' => array( array( 'domain' => 'strong.test', 'domainRank' => 70 ), array( 'domain' => '' ) ),
				'link'    => 'https://app.monoranks.com/sites/site_1/backlinks',
			)
		);
		$this->assertSame( array( 'ok', 120, 6, 2, 3 ), array( $out['state'], $out['domains'], $out['new'], $out['lost'], $out['review'] ) );
		$this->assertSame( array( array( 'domain' => 'strong.test', 'rank' => 70, 'link' => '' ) ), $out['top'] );
		$this->assertSame( 'empty', Grow::normalise_backlinks( array( 'summary' => null ) )['state'] );
	}

	public function test_competitors_put_the_ones_driving_gaps_first_and_count_the_gaps() {
		$out = Grow::normalise_competitors(
			array(
				'competitors' => array(
					array( 'domain' => 'sugg.test', 'kind' => 'suggested', 'sharedKeywords' => 3 ),
					array( 'domain' => 'rival.test', 'kind' => 'picked', 'sharedKeywords' => 12, 'aboveUs' => 5, 'review' => array( 'reason' => 'Sells the same plugins' ) ),
				),
				'driving'     => array( 'rival.test' ),
			),
			array( 'counts' => array( 'gaps' => 48 ), 'rows' => array( array( 'keyword' => 'best widget', 'volume' => 2000 ) ) )
		);
		$this->assertSame( array( 'rival.test', 'sugg.test' ), array_column( $out['top'], 'domain' ) );
		$this->assertTrue( $out['top'][0]['picked'] );
		$this->assertSame( 'Sells the same plugins', $out['top'][0]['reason'] );
		$this->assertSame( 48, $out['gaps'] );
		$this->assertSame( array( array( 'keyword' => 'best widget', 'volume' => 2000 ) ), $out['keywords'] );
		$this->assertNull( Grow::normalise_competitors( array( 'competitors' => array() ) )['gaps'] );
	}

	public function test_keyword_movers_skip_keywords_that_left_or_entered_the_top_100() {
		$up   = array( 'total' => 30, 'keywords' => array(
			array( 'keyword' => 'entered', 'latestPosition' => 4, 'weekAgoPosition' => 0 ),
			array( 'keyword' => 'climbed', 'latestPosition' => 3, 'weekAgoPosition' => 9 ),
			array( 'keyword' => 'same', 'latestPosition' => 5, 'weekAgoPosition' => 5 ),
		) );
		$down = array( 'keywords' => array(
			array( 'keyword' => 'dropped out', 'latestPosition' => 0, 'weekAgoPosition' => 8 ),
			array( 'keyword' => 'slipped', 'latestPosition' => 12, 'weekAgoPosition' => 7 ),
		) );
		$out = Grow::normalise_keywords( $up, $down );
		$this->assertSame( array( 'climbed' ), array_column( $out['up'], 'keyword' ) );
		$this->assertSame( array( 9, 3 ), array( $out['up'][0]['from'], $out['up'][0]['to'] ) );
		$this->assertSame( array( 'slipped' ), array_column( $out['down'], 'keyword' ) );
		$this->assertSame( 'empty', Grow::normalise_keywords( array( 'total' => 0, 'keywords' => array() ), array() )['state'] );
	}

	public function test_the_newest_report_and_links_only_to_https() {
		$out = Grow::normalise_report( array( 'reports' => array( array( 'kind' => 'client_summary', 'title' => 'Client report', 'scope' => 'Acme · client', 'createdAt' => '2026-10-01T10:00:00.000Z', 'link' => 'http://evil.test/x' ) ), 'link' => 'https://app.monoranks.com/reports' ) );
		$this->assertTrue( $out['client'] );
		$this->assertSame( '', $out['link'] );
		$this->assertSame( 'https://app.monoranks.com/reports', $out['all_link'] );
		$this->assertSame( 'empty', Grow::normalise_report( array( 'reports' => array() ) )['state'] );
	}

	public function test_weekly_emails_are_listed_with_their_reports() {
		$out = Grow::normalise_report( array(
			'weeklyEmails' => array(
				array( 'name' => 'WP Statistics', 'enabled' => true, 'recipients' => 1, 'nextSendAt' => '2026-10-12', 'lastSentAt' => null, 'link' => 'https://app.monoranks.com/reports' ),
				array( 'name' => 'Paused', 'enabled' => false, 'recipients' => 3, 'nextSendAt' => null, 'lastSentAt' => '2026-10-05T08:00:00.000Z', 'link' => 'http://evil.test/' ),
			),
			'reports'      => array(),
			'link'         => 'https://app.monoranks.com/reports',
		) );
		// Weekly emails alone are enough: the panel no longer says there are no reports.
		$this->assertSame( 'ok', $out['state'] );
		$this->assertSame( array( 'WP Statistics', 'Paused' ), array_column( $out['emails'], 'name' ) );
		$this->assertSame( array( true, false ), array_column( $out['emails'], 'enabled' ) );
		$this->assertSame( array( 1, 3 ), array_column( $out['emails'], 'recipients' ) );
		$this->assertSame( '2026-10-12', $out['emails'][0]['next'] );
		$this->assertSame( '', $out['emails'][1]['next'] );
		$this->assertSame( '', $out['emails'][1]['link'] );
		$this->assertSame( array(), $out['reports'] );
	}

	public function test_refresh_reads_every_section_and_caches_them() {
		$this->connect();
		$this->answer(
			array(
				'#/outreach\?#'         => array( 200, array( 'counts' => array( 'status' => array( 'to_contact' => 2 ) ), 'targets' => array( array( 'source' => 'manual', 'url' => 'https://a.test/' ) ) ) ),
				'#/backlinks\?#'        => array( 403, array( 'error' => array( 'code' => 'missing_scope', 'message' => 'x' ) ) ),
				'#/competitors/gaps\?#' => array( 200, array( 'counts' => array( 'gaps' => 4 ), 'rows' => array() ) ),
				'#/competitors$#'       => array( 200, array( 'competitors' => array( array( 'domain' => 'r.test', 'kind' => 'picked' ) ) ) ),
				'#/keywords\?#'         => array( 503, array( 'error' => array( 'code' => 'api_disabled', 'message' => 'x' ) ) ),
				'#/reports\?#'          => array( 500, array( 'error' => array( 'code' => 'internal', 'message' => 'x' ) ) ),
			)
		);
		$cache = Grow::refresh();
		$this->assertSame( 'ok', $cache['outreach']['state'] );
		$this->assertSame( 'no_access', $cache['backlinks']['state'] );
		$this->assertSame( 4, $cache['competitors']['gaps'] );
		$this->assertSame( 'off', $cache['keywords']['state'] );
		$this->assertSame( 'error', $cache['report']['state'] );
		$this->assertSame( $cache, $this->transients[ Grow::CACHE ] );
		// Outreach, backlinks, competitors and their gaps, keywords (stops at the first refusal), reports.
		$this->assertCount( 6, $this->called );
		// A section that failed is asked again within the hour, not after six.
		$this->assertFalse( Grow::is_stale( $cache, time() + 30 * 60 ) );
		$this->assertTrue( Grow::is_stale( $cache, time() + 61 * 60 ) );
	}

	public function test_a_second_pull_while_one_runs_does_nothing() {
		$this->connect();
		$this->transients[ Grow::LOCK ] = 1;
		Functions\expect( 'wp_remote_get' )->never();
		$this->assertSame( array(), Grow::refresh() );
	}

	public function test_data_without_a_connection_says_so_and_calls_nothing() {
		Functions\expect( 'wp_remote_get' )->never();
		$this->assertSame( array( 'connected' => false ), Grow::data() );
	}

	public function test_a_fresh_cache_is_served_without_calls() {
		$this->connect();
		$fresh = array( 'fetched_at' => gmdate( 'c' ) );
		foreach ( Grow::SECTIONS as $s ) {
			$fresh[ $s ] = array( 'state' => 'empty' );
		}
		$this->transients[ Grow::CACHE ] = $fresh;
		Functions\expect( 'wp_remote_get' )->never();
		$data = Grow::data();
		$this->assertTrue( $data['connected'] );
		$this->assertSame( 'empty', $data['outreach']['state'] );
		$this->assertSame( 'https://app.monoranks.com/sites/site_1/integrations', $data['scope_url'] );
	}
}

/** json_encode for the fakes (wp_json_encode is not loaded in unit tests). */
function wp_json_encode_for_test( $v ) {
	return (string) json_encode( $v );
}
