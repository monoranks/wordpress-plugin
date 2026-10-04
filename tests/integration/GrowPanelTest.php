<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\Grow;
use MonoRanks\PageValues;
use MonoRanks\PostPanel;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The Grow cards and the post editor box against real WordPress, with MonoRanks answered by a pre_http_request filter:
 * the REST routes and who may call them, the box's HTML (escaped, the revenue in it), and that disconnecting forgets
 * every cached read. Nothing here reaches the network.
 */
class GrowPanelTest extends TestCase {

	private $posts = array();
	private $users = array();
	private $conn;
	private $calls = array();
	private $permalink = '';
	private $structure;

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a local WordPress install to run the integration tests.' );
		}
		// Only the plugin's classes are loaded here, not monoranks.php: its constants and the admin helpers come from here.
		defined( 'MONORANKS_CONNECTOR_VERSION' ) || define( 'MONORANKS_CONNECTOR_VERSION', 'test' );
		defined( 'MONORANKS_CONNECTOR_FILE' ) || define( 'MONORANKS_CONNECTOR_FILE', dirname( __DIR__, 2 ) . '/monoranks.php' );
		defined( 'MONORANKS_API_BASE' ) || define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		$this->conn = get_option( 'monoranks_connection', null );
		// Pretty permalinks, so the post has a path of its own to match MonoRanks' rows on.
		$this->structure = get_option( 'permalink_structure' );
		update_option( 'permalink_structure', '/%postname%/' );
		$GLOBALS['wp_rewrite']->init();
		update_option( 'monoranks_connection', array( 'api_base' => 'https://app.monoranks.test', 'key' => 'mr_site_' . str_repeat( 'k', 30 ), 'site_id' => 'site_1', 'key_state' => 'ok' ), false );
		Grow::clear_all();
		add_filter( 'pre_http_request', array( $this, 'answer' ), 10, 3 );
		// The plugin file is not loaded here (only its classes), so its routes are registered on a fresh REST server.
		add_action( 'rest_api_init', array( 'MonoRanks\\Rest', 'register' ) );
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	protected function tear_down() {
		if ( defined( 'MONORANKS_TESTS_WP' ) ) {
			remove_filter( 'pre_http_request', array( $this, 'answer' ), 10 );
			remove_action( 'rest_api_init', array( 'MonoRanks\\Rest', 'register' ) );
			$GLOBALS['wp_rest_server'] = null;
			Grow::clear_all();
			foreach ( $this->posts as $id ) {
				wp_delete_post( $id, true );
			}
			foreach ( $this->users as $id ) {
				wp_delete_user( $id );
			}
			update_option( 'permalink_structure', $this->structure );
			$GLOBALS['wp_rewrite']->init();
			null === $this->conn ? delete_option( 'monoranks_connection' ) : update_option( 'monoranks_connection', $this->conn, false );
			wp_set_current_user( 0 );
		}
		parent::tear_down();
	}

	/** MonoRanks, faked: one answer per API v1 route the plugin reads. */
	public function answer( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://app.monoranks.test/api/v1/sites/site_1/' ) ) {
			return $pre;
		}
		$this->calls[] = $url;
		$path          = substr( $url, strlen( 'https://app.monoranks.test/api/v1/sites/site_1/' ) );
		$body          = array( 'error' => array( 'code' => 'no_route', 'message' => 'x' ) );
		$code          = 404;
		if ( 0 === strpos( $path, 'analytics/pages' ) ) {
			$code = 200;
			$body = array( 'currency' => 'USD', 'searchConsole' => 'joined', 'rows' => array( array( 'page' => '/mr-grow-test/', 'url' => $this->permalink, 'sessions' => 120, 'keyEvents' => 4, 'revenue' => 340, 'clicks' => 75, 'impressions' => 2000, 'position' => 5.2 ) ) );
		} elseif ( 0 === strpos( $path, 'outreach?source=lost_link' ) ) {
			$code = 200;
			$body = array( 'targets' => array( array( 'domain' => 'old<script>.test', 'url' => 'https://old.test/', 'status' => 'to_contact', 'reason' => array( 'lostTo' => $this->permalink ) ) ) );
		} elseif ( 0 === strpos( $path, 'pages/history' ) ) {
			$code = 200;
			$body = array( 'entries' => array( array( 'at' => '2026-09-25T04:00:00Z', 'changes' => array( array( 'field' => 'title', 'before' => 'a', 'after' => 'b' ) ) ) ), 'link' => 'https://app.monoranks.test/sites/site_1/pages/p1#history' );
		} elseif ( 0 === strpos( $path, 'search/rows' ) ) {
			$code = 200;
			$body = array( 'rows' => array( array( 'query' => 'grow <b>test</b>', 'clicks' => 30, 'position' => 2 ) ) );
		} elseif ( 0 === strpos( $path, 'outreach?status=to_contact' ) ) {
			$code = 200;
			$body = array( 'counts' => array( 'status' => array( 'to_contact' => 5 ) ), 'targets' => array( array( 'source' => 'best_list', 'url' => 'https://list.test/', 'reason' => array( 'keyword' => 'best widgets' ) ) ) );
		} elseif ( 0 === strpos( $path, 'backlinks' ) || 0 === strpos( $path, 'competitors' ) || 0 === strpos( $path, 'keywords' ) ) {
			$code = 403;
			$body = array( 'error' => array( 'code' => 'missing_scope', 'message' => 'This key does not have the search:read scope.' ) );
		}
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	private function user( $role ) {
		$id            = wp_insert_user( array( 'user_login' => 'mr_' . $role . '_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$this->users[] = $id;
		return $id;
	}

	private function post() {
		$id            = wp_insert_post( array( 'post_title' => 'MonoRanks grow test', 'post_name' => 'mr-grow-test', 'post_status' => 'publish', 'post_type' => 'post' ) );
		$this->posts[] = $id;
		$this->permalink = (string) get_permalink( $id );
		return $id;
	}

	public function test_the_routes_need_an_administrator() {
		$id = $this->post();
		wp_set_current_user( $this->user( 'editor' ) );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/monoranks/v1/admin/grow' ) )->get_status() );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/monoranks/v1/admin/post/' . $id ) )->get_status() );
		$this->assertSame( array(), $this->calls );
	}

	public function test_the_grow_route_answers_each_card_with_its_own_state() {
		wp_set_current_user( $this->user( 'administrator' ) );
		$res = rest_do_request( new \WP_REST_Request( 'GET', '/monoranks/v1/admin/grow' ) );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertSame( 'ok', $data['outreach']['state'] );
		$this->assertSame( 5, $data['outreach']['to_contact'] );
		$this->assertSame( 'A "best of" list for "best widgets" that leaves you out', $data['outreach']['top'][0]['why'] );
		$this->assertSame( 'no_access', $data['backlinks']['state'] );
		$this->assertSame( 'off', $data['report']['state'] );
		// The second load is served from the cache.
		$n = count( $this->calls );
		rest_do_request( new \WP_REST_Request( 'GET', '/monoranks/v1/admin/grow' ) );
		$this->assertCount( $n, $this->calls );
	}

	public function test_the_post_box_shows_clicks_revenue_lost_links_and_the_last_change_escaped() {
		$id = $this->post();
		wp_set_current_user( $this->user( 'administrator' ) );
		$res = rest_do_request( new \WP_REST_Request( 'GET', '/monoranks/v1/admin/post/' . $id ) );
		$this->assertSame( 200, $res->get_status() );
		$html = $res->get_data()['html'];
		$this->assertStringContainsString( 'Google search, 28 days', $html );
		$this->assertMatchesRegularExpression( '#<dd>75</dd>#', $html );
		$this->assertStringContainsString( '340', $html );
		$this->assertStringContainsString( '1 lost link to win back', $html );
		$this->assertStringContainsString( 'Last change MonoRanks saw', $html );
		$this->assertStringContainsString( '<li>Title</li>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>test</b>', $html );
		$this->assertStringContainsString( 'https://app.monoranks.test/sites/site_1/pages/p1#history', $html );
	}

	public function test_the_box_is_added_for_administrators_on_a_connected_site_only() {
		global $wp_meta_boxes;
		$id = $this->post();
		wp_set_current_user( $this->user( 'administrator' ) );
		PostPanel::add( 'post', get_post( $id ) );
		$this->assertArrayHasKey( PostPanel::ID, $wp_meta_boxes['post']['side']['default'] );
		unset( $wp_meta_boxes['post'] );
		wp_set_current_user( $this->user( 'editor' ) );
		PostPanel::add( 'post', get_post( $id ) );
		$this->assertTrue( empty( $wp_meta_boxes['post']['side']['default'][ PostPanel::ID ] ) );
	}

	public function test_forgetting_the_reads_drops_every_cache() {
		$id = $this->post();
		wp_set_current_user( $this->user( 'administrator' ) );
		rest_do_request( new \WP_REST_Request( 'GET', '/monoranks/v1/admin/post/' . $id ) );
		$this->assertNotFalse( get_transient( PostPanel::CACHE . $id ) );
		$this->assertNotFalse( get_transient( PageValues::CACHE ) );
		Grow::clear_all();
		$this->assertFalse( get_transient( PostPanel::CACHE . $id ) );
		$this->assertFalse( get_transient( PageValues::CACHE ) );
		$this->assertFalse( get_transient( Grow::CACHE ) );
	}
}
