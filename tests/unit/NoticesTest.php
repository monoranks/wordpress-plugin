<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\Notices;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class NoticesTest extends TestCase {
	/** @var string */
	private $core_file = '';

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'wp_normalize_path' )->returnArg();
		$dir = ABSPATH . 'wp-admin';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir );
		}
		$this->core_file = $dir . '/mr-fake-core-notice.php';
		file_put_contents( $this->core_file, '<?php if ( ! function_exists( "mr_fake_core_notice" ) ) { function mr_fake_core_notice() {} }' );
		require_once $this->core_file;
		// Another plugin's code: a file outside WordPress and this plugin.
		$other = sys_get_temp_dir() . '/mr-other-plugin-notice.php';
		file_put_contents( $other, '<?php if ( ! class_exists( "MrOtherPluginNotice" ) ) { class MrOtherPluginNotice { public function show() {} public function __invoke() {} } function mr_other_plugin_notice() {} } $GLOBALS["mr_other_closure"] = static function () {};' );
		require $other;
		unlink( $other );
	}
	protected function tear_down() {
		unlink( $this->core_file );
		rmdir( ABSPATH . 'wp-admin' );
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_wordpress_and_this_plugin_keep_their_notices() {
		$this->assertTrue( Notices::allowed( 'mr_fake_core_notice' ) );
		$this->assertTrue( Notices::allowed( array( Notices::class, 'open' ) ) );
		$this->assertTrue( Notices::allowed( 'MonoRanks\\Notices::open' ) );
	}

	public function test_other_code_does_not() {
		$this->assertFalse( Notices::allowed( $GLOBALS['mr_other_closure'] ) );
		$this->assertFalse( Notices::allowed( 'mr_other_plugin_notice' ) );
		$this->assertFalse( Notices::allowed( array( new \MrOtherPluginNotice(), 'show' ) ) );
		$this->assertFalse( Notices::allowed( new \MrOtherPluginNotice() ) );
		$this->assertFalse( Notices::allowed( 'function_that_does_not_exist' ) );
	}

	public function test_other_screens_are_left_alone() {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'dashboard' ) );
		Functions\expect( 'remove_action' )->never();
		Functions\expect( 'add_action' )->never();
		$GLOBALS['wp_filter'] = array( 'admin_notices' => (object) array( 'callbacks' => array( 10 => array( array( 'function' => static function () {} ) ) ) ) );
		Notices::clean();
		unset( $GLOBALS['wp_filter'] );
		$this->addToAssertionCount( 1 );
	}
}
