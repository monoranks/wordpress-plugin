<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\AuthorizeScreen;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class AuthorizeScreenTest extends TestCase {
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'esc_html__' )->returnArg();
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	private function rendered( array $request ) {
		ob_start();
		AuthorizeScreen::render( $request );
		return (string) ob_get_clean();
	}

	public function test_monoranks_gets_its_logo_and_line() {
		$html = $this->rendered( array( 'app_name' => 'MonoRanks', 'app_id' => AuthorizeScreen::APP_ID ) );
		$this->assertStringContainsString( 'id="monoranks-authorize"', $html );
		$this->assertStringContainsString( 'aria-label="MonoRanks"', $html, 'the logo is printed' );
		$this->assertStringContainsString( 'Reads your content and applies only fixes you approve.', $html );
	}

	public function test_any_other_app_gets_nothing() {
		$this->assertSame( '', $this->rendered( array( 'app_name' => 'Other app', 'app_id' => '11111111-2222-3333-4444-555555555555' ) ) );
		$this->assertSame( '', $this->rendered( array( 'app_name' => 'MonoRanks', 'app_id' => '' ) ), 'the name alone is not enough' );
		$this->assertSame( '', $this->rendered( array( 'app_name' => 'MonoRanks' ) ) );
	}
}
