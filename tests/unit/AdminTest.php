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

	public function test_outbound_links_carry_utm_tags() {
		Functions\when( 'add_query_arg' )->alias( static function ( $args, $url ) { return $url . '?' . http_build_query( $args ); } );
		$this->assertSame( 'https://app.monoranks.com/sites/x?utm_source=wordpress-plugin&utm_medium=admin&utm_campaign=posts-list&utm_content=posts-column', Admin::out( 'https://app.monoranks.com/sites/x', 'posts-column', 'posts-list' ) );
		$this->assertSame( '', Admin::out( '', 'x' ) );
	}
}
