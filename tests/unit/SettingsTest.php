<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class SettingsTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( $s, '/\\' ); } );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_connect_link_names_the_site_and_the_way_back() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/wp-admin/' . $path; } );
		$this->assertSame(
			'https://app.monoranks.com/connect/wordpress?site=https%3A%2F%2Fexample.com&return=https%3A%2F%2Fexample.com%2Fwp-admin%2Fadmin.php%3Fpage%3Dmonoranks-settings',
			Settings::connect_url()
		);
	}

	public function test_connect_link_keeps_a_subfolder_and_a_custom_base() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/blog/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/blog/wp-admin/' . $path; } );
		$this->assertSame(
			'http://localhost:3000/connect/wordpress?site=https%3A%2F%2Fexample.com%2Fblog&return=https%3A%2F%2Fexample.com%2Fblog%2Fwp-admin%2Fadmin.php%3Fpage%3Dmonoranks-settings',
			Settings::connect_url( 'http://localhost:3000/' )
		);
	}

	public function test_connect_link_carries_the_users_state_and_keeps_it_while_it_lives() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/wp-admin/' . $path; } );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		$store = array();
		Functions\when( 'get_transient' )->alias( static function ( $k ) use ( &$store ) { return isset( $store[ $k ] ) ? $store[ $k ] : false; } );
		Functions\when( 'set_transient' )->alias( static function ( $k, $v ) use ( &$store ) { $store[ $k ] = $v; return true; } );

		$url = Settings::connect_url();
		$this->assertMatchesRegularExpression( '/&state=[a-f0-9]{40}$/', $url );
		$this->assertSame( $url, Settings::connect_url(), 'a second tab gets the same link' );
		$this->assertSame( 'https://app.monoranks.com', $store['monoranks_connect_7']['base'] );
		$this->assertNotSame( substr( $url, -40 ), substr( Settings::connect_url( 'http://localhost:3000' ), -40 ), 'another MonoRanks address gets its own state' );
	}

	public function test_monoranks_password_is_found_by_app_id_or_name() {
		$this->assertTrue( Settings::is_monoranks_password( array( 'app_id' => '6F1C1B5E-3F7A-4D8E-9A51-7A2B0C4E8D10', 'name' => 'Whatever' ) ) );
		$this->assertTrue( Settings::is_monoranks_password( array( 'app_id' => '', 'name' => 'MonoRanks' ) ) );
		$this->assertFalse( Settings::is_monoranks_password( array( 'app_id' => 'other-app', 'name' => 'MonoRanks' ) ), 'another app named MonoRanks is not it' );
		$this->assertFalse( Settings::is_monoranks_password( array( 'name' => 'Jetpack' ) ) );
		$this->assertFalse( Settings::is_monoranks_password( null ) );
	}

	public function test_no_application_password_means_fixes_are_not_set_up() {
		Functions\when( 'wp_is_application_passwords_available' )->justReturn( true );
		Functions\when( 'get_users' )->justReturn( array( 1, 2 ) );
		\WP_Application_Passwords::$by_user = array( 1 => array( array( 'app_id' => '', 'name' => 'Jetpack' ) ) );
		$this->assertSame( array( 'exists' => false, 'revoke_url' => '', 'available' => true ), Settings::app_password() );
	}

	public function test_another_admins_monoranks_password_counts_and_links_to_their_profile() {
		Functions\when( 'wp_is_application_passwords_available' )->justReturn( true );
		Functions\when( 'get_users' )->justReturn( array( 1, 2 ) );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'https://example.com/wp-admin/' . $path; } );
		\WP_Application_Passwords::$by_user = array( 2 => array( array( 'app_id' => '6f1c1b5e-3f7a-4d8e-9a51-7a2b0c4e8d10', 'name' => 'MonoRanks' ) ) );
		$got = Settings::app_password();
		$this->assertTrue( $got['exists'] );
		$this->assertSame( 'https://example.com/wp-admin/user-edit.php?user_id=2#application-passwords-section', $got['revoke_url'] );
		Functions\when( 'get_current_user_id' )->justReturn( 2 );
		$this->assertSame( 'https://example.com/wp-admin/profile.php#application-passwords-section', Settings::app_password()['revoke_url'] );
	}

	public function test_allow_goes_to_the_approve_step_for_this_website() {
		Functions\when( 'home_url' )->justReturn( 'http://localhost:8091/' );
		Functions\when( 'admin_url' )->alias( static function ( $path ) { return 'http://localhost:8091/wp-admin/' . $path; } );
		Functions\when( 'get_option' )->justReturn( array( 'api_base' => 'http://localhost:3200', 'key' => 'mr_site_' . str_repeat( 'k', 30 ), 'site_id' => 'site-1' ) );
		$this->assertSame(
			'http://localhost:3200/api/wordpress/start?site=site-1&wp=http%3A%2F%2Flocalhost%3A8091&connect=1&return=http%3A%2F%2Flocalhost%3A8091%2Fwp-admin%2Fadmin.php%3Fpage%3Dmonoranks-settings',
			Settings::allow_url()
		);
		Functions\when( 'get_option' )->justReturn( array( 'api_base' => 'http://localhost:3200', 'key' => 'mr_site_' . str_repeat( 'k', 30 ) ) );
		$this->assertStringStartsWith( 'http://localhost:3200/connect/wordpress?site=', Settings::allow_url(), 'without the website id: the connect link' );
	}
}
