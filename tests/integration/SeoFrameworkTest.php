<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\SeoFields;
use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Approved SEO fields with The SEO Framework active (monoranks/wordpress-plugin#2): they go into its own post meta, the
 * page shows them once (its tags only, none from MonoRanks next to them), and on the front page its Homepage Settings
 * are respected. Needs The SEO Framework installed in the test WordPress, loaded for the run only:
 *   MONORANKS_WP_PLUGINS=autodescription/autodescription.php composer test:integration -- --group tsf
 * The SEO Framework keeps per-request caches of the query, so each test renders its page in its own PHP process.
 *
 * @group tsf
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SeoFrameworkTest extends TestCase {

	const TITLE       = 'VeronaLabs | WordPress Plugins: WP Statistics, WSMS, SlimStat';
	const DESCRIPTION = 'Plugins that millions of WordPress sites use for statistics, SMS and analytics.';

	private $posts = array();
	private $options = array();

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a local WordPress install to run the integration tests.' );
		}
		if ( ! defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			$this->markTestSkipped( 'Install The SEO Framework and set MONORANKS_WP_PLUGINS=autodescription/autodescription.php.' );
		}
		foreach ( array( 'monoranks_change_log', 'show_on_front', 'page_on_front', SeoFields::TSF_OPTIONS ) as $name ) {
			$this->options[ $name ] = get_option( $name, null );
		}
		SeoFields::register_output();
	}

	protected function tear_down() {
		if ( defined( 'MONORANKS_TESTS_WP' ) && defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			foreach ( $this->posts as $id ) {
				wp_delete_post( $id, true );
			}
			foreach ( $this->options as $name => $value ) {
				null === $value ? delete_option( $name ) : update_option( $name, $value );
			}
		}
		parent::tear_down();
	}

	private function page() {
		$id            = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'MonoRanks TSF test', 'post_status' => 'publish', 'post_content' => '<p>Body.</p>' ) );
		$this->posts[] = $id;
		return $id;
	}

	private function write( $id, $field, $value ) {
		return Writer::apply( array( array( 'id' => $field, 'field' => $field, 'post_id' => $id, 'value' => $value ) ), 'test' )[0];
	}

	/** Makes the page the static front page, with these Homepage Settings in The SEO Framework. */
	private function front_page( $id, array $homepage ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
		$settings = get_option( SeoFields::TSF_OPTIONS );
		update_option( SeoFields::TSF_OPTIONS, array_merge( is_array( $settings ) ? $settings : \The_SEO_Framework\Data\Plugin\Setup::get_default_options(), $homepage ) );
		// The SEO Framework keeps its settings in memory once read (Data\Plugin::$options_memo), and its
		// refresh_static_properties() does not clear them here, so drop them by hand.
		$memo = new \ReflectionProperty( \The_SEO_Framework\Data\Plugin::class, 'options_memo' );
		$memo->setAccessible( true );
		$memo->setValue( null, null );
	}

	/** The document title and the wp_head output of the page, as a visitor gets them. */
	private function render( $id ) {
		clean_post_cache( $id );
		$GLOBALS['wp_the_query'] = new \WP_Query( array( 'page_id' => $id ) );
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$GLOBALS['post']         = get_post( $id );
		\The_SEO_Framework\Front\Title::overwrite_title_filters();
		$title = wp_get_document_title();
		// Core's wp_head reads the host name a web server would set.
		$_SERVER['SERVER_NAME'] = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'localhost';
		ob_start();
		try {
			do_action( 'wp_head' );
		} finally {
			$head = (string) ob_get_clean();
		}
		return array( html_entity_decode( $title, ENT_QUOTES ), $head );
	}

	private function tags( $head, $pattern ) {
		preg_match_all( $pattern, $head, $m );
		return array_map( static function ( $v ) { return html_entity_decode( $v, ENT_QUOTES ); }, $m[1] );
	}

	public function test_approved_fields_go_into_its_meta_and_show_once_on_the_page() {
		$this->assertSame( 'tsf', SeoFields::plugin() );
		$id        = $this->page();
		$canonical = home_url( '/monoranks-canonical-test/' );
		foreach ( array( 'seo_title' => self::TITLE, 'seo_description' => self::DESCRIPTION, 'canonical' => $canonical, 'noindex' => '1' ) as $field => $value ) {
			$r = $this->write( $id, $field, $value );
			$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		}
		$this->assertSame( self::TITLE, get_post_meta( $id, '_genesis_title', true ) );
		$this->assertSame( self::DESCRIPTION, get_post_meta( $id, '_genesis_description', true ) );
		$this->assertSame( $canonical, get_post_meta( $id, '_genesis_canonical_uri', true ) );
		$this->assertSame( '1', get_post_meta( $id, '_genesis_noindex', true ) );
		$this->assertSame( '', get_post_meta( $id, SeoFields::OWN_TITLE, true ) );

		list( $title, $head ) = $this->render( $id );
		// The whole approved title, without The SEO Framework's " | Site name" addition.
		$this->assertSame( self::TITLE, $title );
		$this->assertSame( array( self::DESCRIPTION ), $this->tags( $head, '/<meta name="description" content="([^"]*)"/' ) );
		$this->assertSame( array( $canonical ), $this->tags( $head, '/<link rel="canonical" href="([^"]*)"/' ) );
		$robots = $this->tags( $head, '/<meta name=["\']robots["\'] content=["\']([^"\']*)["\']/' );
		$this->assertCount( 1, $robots, $head );
		$this->assertStringContainsString( 'noindex', $robots[0] );
	}

	public function test_undo_restores_the_fields_and_the_site_name_addition() {
		$id = $this->page();
		$this->assertTrue( $this->write( $id, 'seo_title', self::TITLE )['ok'] );
		$this->assertTrue( $this->write( $id, 'noindex', '1' )['ok'] );
		$log = get_option( 'monoranks_change_log' );
		foreach ( array_reverse( array_slice( $log, -2 ) ) as $row ) {
			$undo = Writer::apply( array( Writer::reverse( $row ) ), 'test' )[0];
			$this->assertTrue( $undo['ok'], wp_json_encode( $undo ) );
		}
		$this->assertSame( array(), get_post_meta( $id, '_genesis_title' ) );
		$this->assertSame( array(), get_post_meta( $id, '_genesis_noindex' ) );
		$this->assertSame( array(), get_post_meta( $id, SeoFields::TSF_TITLE_MARK ) );

		list( $title, $head ) = $this->render( $id );
		$this->assertStringContainsString( 'MonoRanks TSF test', $title );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $title );
		$this->assertStringNotContainsString( 'noindex', implode( ' ', $this->tags( $head, '/<meta name=["\']robots["\'] content=["\']([^"\']*)["\']/' ) ) );
	}

	public function test_on_the_front_page_its_homepage_settings_win_so_that_write_is_refused() {
		$id = $this->page();
		$this->front_page( $id, array( 'homepage_title' => 'Providing Web Solutions To Grow Businesses', 'homepage_description' => '', 'homepage_tagline' => 1 ) );

		$r = $this->write( $id, 'seo_title', self::TITLE );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'seo_plugin_homepage_setting', $r['error'] );
		$this->assertStringContainsString( 'Homepage Settings', $r['reason'] );
		$this->assertSame( '', get_post_meta( $id, '_genesis_title', true ) );

		// The description field there is empty, so the page's own field is used.
		$this->assertTrue( $this->write( $id, 'seo_description', self::DESCRIPTION )['ok'] );
		list( $title, $head ) = $this->render( $id );
		$this->assertStringStartsWith( 'Providing Web Solutions To Grow Businesses', $title );
		$this->assertSame( array( self::DESCRIPTION ), $this->tags( $head, '/<meta name="description" content="([^"]*)"/' ) );
	}

	public function test_on_the_front_page_without_a_homepage_title_the_approved_title_shows_without_the_tagline() {
		$id = $this->page();
		$this->front_page( $id, array( 'homepage_title' => '', 'homepage_tagline' => 1, 'homepage_title_tagline' => 'A tagline' ) );

		$this->assertTrue( $this->write( $id, 'seo_title', self::TITLE )['ok'] );
		list( $title ) = $this->render( $id );
		$this->assertSame( self::TITLE, $title );
	}
}
