<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\Actions;
use MonoRanks\Connection;
use MonoRanks\Overview;
use MonoRanks\Rest;
use MonoRanks\Sync;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Connecting against real WordPress: the public ping says whether the plugin holds a key (so MonoRanks can offer Connect
 * again after a Disconnect here), pairing starts the first content send by itself, and the Overview asks for "Sync content
 * now" only when a send failed.
 */
class ConnectFlowTest extends TestCase {

	private $conn;
	private $urls = array();

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a local WordPress install to run the integration tests.' );
		}
		defined( 'MONORANKS_CONNECTOR_VERSION' ) || define( 'MONORANKS_CONNECTOR_VERSION', 'test' );
		defined( 'MONORANKS_CONNECTOR_FILE' ) || define( 'MONORANKS_CONNECTOR_FILE', dirname( __DIR__, 2 ) . '/monoranks.php' );
		defined( 'MONORANKS_API_BASE' ) || define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
		$this->conn = get_option( 'monoranks_connection', null );
		Connection::clear();
		wp_clear_scheduled_hook( Sync::STEP_HOOK );
		// Nothing leaves this machine: MonoRanks and the WP-Cron loopback are answered here.
		add_filter( 'pre_http_request', array( $this, 'answer' ), 10, 3 );
	}

	protected function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'answer' ), 10 );
		Connection::clear();
		wp_clear_scheduled_hook( Sync::STEP_HOOK );
		wp_clear_scheduled_hook( Sync::DAILY_HOOK );
		if ( is_array( $this->conn ) ) {
			update_option( 'monoranks_connection', $this->conn, false );
		}
		parent::tear_down();
	}

	public function answer( $pre, $args, $url ) {
		$this->urls[] = $url;
		return array( 'headers' => array(), 'body' => '{"ok":true}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}

	public function test_ping_says_whether_the_plugin_holds_a_key() {
		$this->assertFalse( Rest::ping()['connected'] );
		Connection::update( array( 'api_base' => 'https://app.monoranks.test', 'key' => 'mr_site_' . str_repeat( 'k', 30 ) ) );
		$this->assertTrue( Rest::ping()['connected'] );
	}

	public function test_pairing_starts_the_first_send_by_itself() {
		$req = new \WP_REST_Request( 'POST', '/monoranks/v1/pair' );
		$req->set_param( 'api_base', 'https://app.monoranks.test' );
		$req->set_param( 'key', 'mr_site_' . str_repeat( 'p', 30 ) );
		$req->set_param( 'site_id', 'site-1' );
		Rest::pair( $req );
		$this->assertTrue( Connection::has_key() );
		$this->assertNotEmpty( get_option( 'monoranks_sync' )['run_id'] );
		$this->assertNotFalse( wp_next_scheduled( Sync::STEP_HOOK ), 'the send runs in WP-Cron right away' );
		$data = Overview::data();
		$this->assertTrue( $data['sending'] );
		$this->assertFalse( $data['send_failed'] );
	}

	public function test_only_a_failed_send_asks_for_sync_now() {
		Connection::update( array( 'api_base' => 'https://app.monoranks.test', 'key' => 'mr_site_' . str_repeat( 'k', 30 ), 'last_error' => 'cURL error 28' ) );
		delete_option( 'monoranks_sync' );
		$this->assertTrue( Overview::data()['send_failed'] );
		Connection::update( array( 'last_sent_at' => gmdate( 'c' ), 'last_error' => null ) );
		$this->assertFalse( Overview::data()['send_failed'] );
	}

	public function test_disconnect_tells_monoranks_so_it_can_connect_again() {
		Connection::update( array( 'api_base' => 'https://app.monoranks.test', 'key' => 'mr_site_' . str_repeat( 'k', 30 ) ) );
		$this->assertSame( 'disconnected', Actions::disconnect() );
		$this->assertContains( 'https://app.monoranks.test/api/connector/disconnect', $this->urls );
		$this->assertFalse( Connection::has_key() );
		$this->assertFalse( Rest::ping()['connected'] );
	}
}
