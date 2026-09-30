<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\SeoFields;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Which SEO plugin is active decides where the SEO fields go. A plugin is detected by the constant it defines, and a
 * constant cannot be undefined again, so every test that defines one runs in its own PHP process.
 */
class SeoFieldsTest extends TestCase {
	/** @var array<int,array<string,mixed>> post id => meta key => value */
	private $meta = array();
	/** @var array<string,mixed> */
	private $options = array();

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$meta    = &$this->meta;
		$options = &$this->options;
		Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single = false ) use ( &$meta ) { return isset( $meta[ $id ][ $key ] ) ? $meta[ $id ][ $key ] : ''; } );
		Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $value ) use ( &$meta ) { $meta[ $id ][ $key ] = $value; return true; } );
		Functions\when( 'delete_post_meta' )->alias( static function ( $id, $key ) use ( &$meta ) { unset( $meta[ $id ][ $key ] ); return true; } );
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object_id' )->justReturn( 7 );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_without_an_seo_plugin_the_connector_keeps_and_prints_its_own_values() {
		$this->assertSame( 'none', SeoFields::plugin() );
		$this->assertSame( '', SeoFields::plugin_name() );
		$this->assertTrue( SeoFields::supported() );
		$this->assertNull( SeoFields::refusal( 7, 'seo_title' ) );
		SeoFields::set( 7, 'seo_title', 'Own title' );
		SeoFields::set( 7, 'seo_description', 'Own description' );
		$this->assertSame( 'Own title', $this->meta[7][ SeoFields::OWN_TITLE ] );
		$this->assertSame( 'Own title', SeoFields::filter_title( 'Theme title' ) );
		$this->expectOutputString( '<meta name="description" content="Own description">' . "\n" );
		SeoFields::print_head();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_seo_framework_is_detected_and_gets_its_own_post_meta() {
		define( 'THE_SEO_FRAMEWORK_VERSION', '5.1.4' );
		$this->assertSame( 'tsf', SeoFields::plugin() );
		$this->assertSame( 'The SEO Framework', SeoFields::plugin_name() );
		$this->assertNull( SeoFields::refusal( 7, 'seo_title' ) );

		SeoFields::set( 7, 'seo_title', 'VeronaLabs | WordPress Plugins' );
		SeoFields::set( 7, 'seo_description', 'Plugins for WordPress.' );
		SeoFields::set( 7, 'canonical', 'https://example.com/a/' );
		$this->assertSame( 'VeronaLabs | WordPress Plugins', $this->meta[7]['_genesis_title'] );
		$this->assertSame( 'Plugins for WordPress.', $this->meta[7]['_genesis_description'] );
		$this->assertSame( 'https://example.com/a/', $this->meta[7]['_genesis_canonical_uri'] );
		$this->assertSame( 'VeronaLabs | WordPress Plugins', SeoFields::get( 7, 'seo_title' ) );
		$this->assertArrayNotHasKey( SeoFields::OWN_TITLE, $this->meta[7] );

		// The SEO Framework prints its own tags: nothing is printed or filtered next to them.
		$this->assertSame( 'Theme title', SeoFields::filter_title( 'Theme title' ) );
		$this->expectOutputString( '' );
		SeoFields::print_head();

		// Clearing a field deletes it, the way The SEO Framework stores an empty field, and the title mark with it.
		SeoFields::set( 7, 'seo_title', '' );
		$this->assertArrayNotHasKey( '_genesis_title', $this->meta[7] );
		$this->assertArrayNotHasKey( SeoFields::TSF_TITLE_MARK, $this->meta[7] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_seo_framework_noindex_is_its_qubit() {
		define( 'THE_SEO_FRAMEWORK_VERSION', '5.1.4' );
		$this->assertSame( '0', SeoFields::get( 7, 'noindex' ) );
		SeoFields::set( 7, 'noindex', '1' );
		$this->assertSame( 1, $this->meta[7]['_genesis_noindex'] );
		$this->assertSame( '1', SeoFields::get( 7, 'noindex' ) );
		// Off goes back to "Default" (The SEO Framework deletes a 0).
		SeoFields::set( 7, 'noindex', '0' );
		$this->assertArrayNotHasKey( '_genesis_noindex', $this->meta[7] );
		// A page forced to index (-1) reads as not noindexed and stays forced.
		$this->meta[8]['_genesis_noindex'] = '-1';
		$this->assertSame( '0', SeoFields::get( 8, 'noindex' ) );
		SeoFields::set( 8, 'noindex', '0' );
		$this->assertSame( '-1', $this->meta[8]['_genesis_noindex'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_seo_framework_drops_the_site_name_only_from_a_title_monoranks_wrote() {
		define( 'THE_SEO_FRAMEWORK_VERSION', '5.1.4' );
		SeoFields::set( 7, 'seo_title', 'Approved title' );
		$this->assertFalse( SeoFields::tsf_branding( true, null, false ) );
		$this->assertFalse( SeoFields::tsf_branding( true, array( 'id' => 7, 'tax' => '', 'pta' => '', 'uid' => 0 ), false ) );
		// Social titles, terms and a site that already turned additions off are left alone.
		$this->assertTrue( SeoFields::tsf_branding( true, null, true ) );
		$this->assertTrue( SeoFields::tsf_branding( true, array( 'id' => 7, 'tax' => 'category' ), false ) );
		$this->assertFalse( SeoFields::tsf_branding( false, null, false ) );
		// Once someone edits the title in The SEO Framework, its own setting applies again.
		$this->meta[7]['_genesis_title'] = 'Edited by hand';
		$this->assertTrue( SeoFields::tsf_branding( true, null, false ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_seo_framework_homepage_settings_win_on_the_front_page_so_the_write_is_refused() {
		define( 'THE_SEO_FRAMEWORK_VERSION', '5.1.4' );
		$this->options = array(
			'show_on_front'               => 'page',
			'page_on_front'               => '7',
			SeoFields::TSF_OPTIONS        => array( 'homepage_title' => 'Providing Web Solutions To Grow Businesses', 'homepage_description' => '', 'homepage_tagline' => 1 ),
		);
		$this->assertSame( 'Providing Web Solutions To Grow Businesses', SeoFields::get( 7, 'seo_title' ) );
		$refused = SeoFields::refusal( 7, 'seo_title' );
		$this->assertSame( 'seo_plugin_homepage_setting', $refused['error'] );
		$this->assertStringContainsString( 'Homepage Settings', $refused['reason'] );
		$this->assertStringContainsString( 'Meta Title', $refused['reason'] );
		// An empty Homepage Settings field leaves the page's own field in charge; noindex is always the page's own.
		$this->assertNull( SeoFields::refusal( 7, 'seo_description' ) );
		$this->assertNull( SeoFields::refusal( 7, 'noindex' ) );
		// Other pages are not the front page.
		$this->assertNull( SeoFields::refusal( 9, 'seo_title' ) );
		// With a latest-posts front page there is no page 7 front page.
		$this->options['show_on_front'] = 'posts';
		$this->assertNull( SeoFields::refusal( 7, 'seo_title' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_an_seo_plugin_monoranks_does_not_know_refuses_seo_writes_and_gets_no_second_tags() {
		define( 'SEOPRESS_VERSION', '10.2' );
		$this->assertSame( 'other', SeoFields::plugin() );
		$this->assertSame( 'SEOPress', SeoFields::plugin_name() );
		$this->assertFalse( SeoFields::supported() );
		foreach ( SeoFields::SEO_FIELDS as $field ) {
			$refused = SeoFields::refusal( 7, $field );
			$this->assertSame( 'seo_plugin_unsupported', $refused['error'] );
			$this->assertStringContainsString( 'SEOPress is active', $refused['reason'] );
		}
		// Fields that do not live in the SEO plugin are not affected.
		$this->assertNull( SeoFields::refusal( 7, 'alt' ) );
		$this->assertNull( SeoFields::refusal( 7, 'content' ) );

		// Values stored before the plugin was there are not printed next to its tags.
		$this->meta[7][ SeoFields::OWN_TITLE ]       = 'Old own title';
		$this->meta[7][ SeoFields::OWN_DESCRIPTION ] = 'Old own description';
		$this->meta[7][ SeoFields::OWN_NOINDEX ]     = '1';
		$this->assertSame( 'Theme title', SeoFields::filter_title( 'Theme title' ) );
		$this->assertSame( array(), SeoFields::core_robots( array() ) );
		$this->expectOutputString( '' );
		SeoFields::print_head();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_yoast_is_unchanged_and_wins_over_the_seo_framework() {
		define( 'WPSEO_VERSION', '26.0' );
		define( 'THE_SEO_FRAMEWORK_VERSION', '5.1.4' );
		$this->assertSame( 'yoast', SeoFields::plugin() );
		$this->assertNull( SeoFields::refusal( 7, 'seo_title' ) );
		SeoFields::set( 7, 'seo_title', 'Yoast title' );
		SeoFields::set( 7, 'noindex', '0' );
		$this->assertSame( 'Yoast title', $this->meta[7]['_yoast_wpseo_title'] );
		$this->assertSame( '2', $this->meta[7]['_yoast_wpseo_meta-robots-noindex'] );
		$this->assertArrayNotHasKey( SeoFields::TSF_TITLE_MARK, $this->meta[7] );
	}
}
