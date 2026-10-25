<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\AuthorizeScreen;
use MonoRanks\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Settings → "Fixes written with" against real WordPress: it says App password only when an admin has the MonoRanks
 * Application Password (made by MonoRanks' approve step), whichever admin approved, and "not set up" otherwise.
 */
class AppPasswordTest extends TestCase {

	private $users = array();

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a local WordPress install to run the integration tests.' );
		}
		defined( 'MONORANKS_CONNECTOR_VERSION' ) || define( 'MONORANKS_CONNECTOR_VERSION', 'test' );
		defined( 'MONORANKS_CONNECTOR_FILE' ) || define( 'MONORANKS_CONNECTOR_FILE', dirname( __DIR__, 2 ) . '/monoranks.php' );
		defined( 'MONORANKS_API_BASE' ) || define( 'MONORANKS_API_BASE', 'https://app.monoranks.com' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	protected function tear_down() {
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function admin( $login ) {
		$id            = wp_insert_user( array( 'user_login' => $login . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		$this->users[] = $id;
		return $id;
	}

	public function test_only_a_monoranks_application_password_counts() {
		$me    = $this->admin( 'mr-me-' );
		$other = $this->admin( 'mr-other-' );
		wp_set_current_user( $me );
		\WP_Application_Passwords::create_new_application_password( $me, array( 'name' => 'Some other app', 'app_id' => wp_generate_uuid4() ) );
		$this->assertFalse( Settings::app_password()['exists'], 'another app\'s password is not MonoRanks\'' );

		\WP_Application_Passwords::create_new_application_password( $other, array( 'name' => 'MonoRanks', 'app_id' => AuthorizeScreen::APP_ID ) );
		$got = Settings::app_password();
		$this->assertTrue( $got['exists'], 'approved by another admin still counts' );
		$this->assertStringContainsString( 'user-edit.php?user_id=' . $other, $got['revoke_url'] );

		\WP_Application_Passwords::delete_all_application_passwords( $other );
		$this->assertFalse( Settings::app_password()['exists'] );
	}
}
