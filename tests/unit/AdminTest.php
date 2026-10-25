<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Admin;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class AdminTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'number_format_i18n' )->alias( static function ( $n ) { return number_format( (float) $n ); } );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_digits_follow_the_admin_language() {
		Functions\when( 'determine_locale' )->justReturn( 'fa_IR' );
		$this->assertSame( '۹۱ از ۱۰۰', Admin::digits( '91 از 100' ) );
		$this->assertSame( '۱۲,۴۸۰', Admin::n( 12480 ) );
	}

	public function test_arabic_and_latin_admins_get_their_own_digits() {
		Functions\when( 'determine_locale' )->justReturn( 'ar' );
		$this->assertSame( '٢٠٢٦', Admin::digits( '2026' ) );
		Functions\when( 'determine_locale' )->justReturn( 'de_DE' );
		$this->assertSame( '2026', Admin::digits( '2026' ) );
	}

	public function test_outbound_links_carry_utm_tags_except_into_the_app() {
		Functions\when( 'add_query_arg' )->alias( static function ( $args, $url ) { return $url . '?' . http_build_query( $args ); } );
		Functions\when( 'wp_parse_url' )->alias( static function ( $url, $component ) { return parse_url( $url, $component ); } );
		Functions\when( 'get_option' )->justReturn( array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) ) );
		$this->assertSame(
			'https://monoranks.com/docs/wordpress-plugin/?utm_source=wordpress-plugin&utm_medium=admin&utm_campaign=overview&utm_content=header-help',
			Admin::out( 'https://monoranks.com/docs/wordpress-plugin/', 'header-help', 'overview' )
		);
		$this->assertSame( 'https://app.monoranks.com/sites/x', Admin::out( 'https://app.monoranks.com/sites/x', 'posts-column', 'posts-list' ), 'a link into the app keeps its plain address' );
		$this->assertSame( '', Admin::out( '', 'x' ) );
	}

	public function test_last_sync_time_is_short_and_the_date_is_separate() {
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
		Functions\when( '__' )->returnArg();
		Functions\when( 'human_time_diff' )->justReturn( '20 seconds' );
		Functions\when( 'get_option' )->alias( static function ( $k ) { return 'date_format' === $k ? 'M j, Y' : 'H:i'; } );
		Functions\when( 'wp_date' )->alias( static function ( $f, $ts ) { return gmdate( $f, $ts ); } );
		$iso = gmdate( 'c', time() - 20 );
		$this->assertSame( '20 seconds ago', Admin::ago_short( $iso ) );
		$this->assertSame( gmdate( 'M j, Y, H:i', strtotime( $iso ) ), Admin::date_of( $iso ) );
		$this->assertSame( 'Never', Admin::ago_short( '', 'Never' ) );
		$this->assertSame( '', Admin::date_of( '' ) );
	}

	private function activation_env( array $over = array() ) {
		$o = array_merge( array( 'multisite' => false, 'ajax' => false, 'can' => true ), $over );
		Functions\when( 'plugin_basename' )->justReturn( 'monoranks/monoranks.php' );
		Functions\when( 'is_multisite' )->justReturn( $o['multisite'] );
		Functions\when( 'wp_doing_ajax' )->justReturn( $o['ajax'] );
		Functions\when( 'current_user_can' )->justReturn( $o['can'] );
		Functions\when( 'get_current_user_id' )->justReturn( 3 );
		Functions\when( 'sanitize_key' )->alias( static function ( $k ) { return strtolower( (string) $k ); } );
		Functions\when( 'wp_unslash' )->returnArg();
	}

	public function test_activating_this_plugin_alone_sets_a_one_time_redirect() {
		$this->activation_env();
		Functions\expect( 'set_transient' )->once()->with( Admin::ACTIVATION_REDIRECT, 3, 60 );
		Admin::activated( 'monoranks/monoranks.php', false );
		$this->addToAssertionCount( 1 );
	}

	public function test_no_redirect_for_other_plugins_network_bulk_or_non_admins() {
		Functions\expect( 'set_transient' )->never();
		$this->activation_env();
		Admin::activated( 'akismet/akismet.php', false );
		Admin::activated( 'monoranks/monoranks.php', true );
		$_REQUEST['action'] = 'activate-selected';
		Admin::activated( 'monoranks/monoranks.php', false );
		unset( $_REQUEST['action'] );
		$this->activation_env( array( 'multisite' => true ) );
		Admin::activated( 'monoranks/monoranks.php', false );
		$this->activation_env( array( 'can' => false ) );
		Admin::activated( 'monoranks/monoranks.php', false );
		$this->activation_env( array( 'ajax' => true ) );
		Admin::activated( 'monoranks/monoranks.php', false );
		$this->addToAssertionCount( 1 );
	}

	public function test_the_redirect_is_used_once_and_only_by_the_same_user() {
		$this->activation_env();
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 9 );
		Functions\expect( 'delete_transient' )->once()->with( Admin::ACTIVATION_REDIRECT );
		Functions\expect( 'wp_safe_redirect' )->never();
		Admin::after_activation();
		$this->addToAssertionCount( 1 );
	}

	public function test_no_transient_no_redirect() {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'wp_safe_redirect' )->never();
		Functions\expect( 'delete_transient' )->never();
		Admin::after_activation();
		$this->addToAssertionCount( 1 );
	}

	public function test_plugin_row_says_connect_until_connected_then_settings() {
		Functions\when( 'admin_url' )->alias( static function ( $p ) { return 'https://example.com/wp-admin/' . $p; } );
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->justReturn( array() );
		$links = Admin::action_links( array( 'deactivate' => '<a>Deactivate</a>' ) );
		$this->assertSame( '<a href="https://example.com/wp-admin/admin.php?page=monoranks-settings">Connect</a>', $links[0] );
		$this->assertCount( 2, $links );
		Functions\when( 'get_option' )->justReturn( array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) ) );
		$this->assertStringContainsString( '>Settings</a>', Admin::action_links( array() )[0] );
	}

	public function test_a_failed_connect_notice_is_hidden_when_the_site_is_connected() {
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( '__' )->returnArg();
		$_GET['monoranks'] = 'connect_failed';
		Functions\when( 'get_option' )->justReturn( array() );
		$this->assertSame( 'error', Admin::notice()['type'] );
		Functions\when( 'get_option' )->justReturn( array( 'api_base' => 'https://app.monoranks.com', 'key' => 'mr_ws_' . str_repeat( 'a', 30 ) ) );
		$this->assertNull( Admin::notice() );
		unset( $_GET['monoranks'] );
	}
}
