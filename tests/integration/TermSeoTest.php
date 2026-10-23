<?php
namespace MonoRanks\Tests\Integration;

use MonoRanks\Content;
use MonoRanks\SeoFields;
use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Category archives in a real WordPress (monoranks/wordpress-plugin#25): sent as content items, their meta description
 * written and undone. Without other plugins the description is the category's own; with Yoast SEO loaded it goes into
 * Yoast's taxonomy meta and the page's indexable:
 *   MONORANKS_WP_PLUGINS=wordpress-seo/wp-seo.php composer test:integration -- --filter TermSeoTest
 * Every term a test creates is deleted again.
 */
class TermSeoTest extends TestCase {

	const DESCRIPTION = 'Privacy guides for WordPress sites: consent, cookies and data you keep.';

	private $terms   = array();
	private $options = array();
	private $user    = 0;

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MONORANKS_TESTS_WP' ) ) {
			$this->markTestSkipped( 'Set MONORANKS_WP_PATH to a local WordPress install to run the integration tests.' );
		}
		foreach ( array( 'monoranks_change_log', SeoFields::YOAST_TAX_OPTION ) as $name ) {
			$this->options[ $name ] = get_option( $name, null );
		}
		$this->user = get_current_user_id();
		$admins     = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		wp_set_current_user( $admins ? (int) $admins[0] : 1 );
	}

	protected function tear_down() {
		if ( defined( 'MONORANKS_TESTS_WP' ) ) {
			foreach ( $this->terms as $id ) {
				wp_delete_term( $id, 'category' );
			}
			foreach ( $this->options as $name => $value ) {
				null === $value ? delete_option( $name ) : update_option( $name, $value );
			}
			wp_set_current_user( $this->user );
		}
		parent::tear_down();
	}

	private function category( $description = '' ) {
		$t             = wp_insert_term( 'MonoRanks term test ' . wp_generate_password( 6, false ), 'category', array( 'description' => $description ) );
		$this->terms[] = (int) $t['term_id'];
		return get_term( (int) $t['term_id'], 'category' );
	}

	private function write( $term, $field, $value, $expected ) {
		return Writer::apply( array( array( 'id' => 'c', 'field' => $field, 'term_id' => $term->term_id, 'taxonomy' => 'category', 'value' => $value, 'expected' => $expected ) ), 'test' )[0];
	}

	public function test_a_category_is_a_content_item() {
		$term = $this->category( 'Old <em>words</em>.' );
		$item = Content::term_item( $term );
		$this->assertSame( 'term:category:' . $term->term_id, $item['id'] );
		$this->assertSame( 'term:category', $item['type'] );
		$this->assertSame( get_term_link( $term ), $item['url'] );
		$this->assertSame( 'Old words.', $item['excerpt'] );
		$this->assertSame( $term->term_id, $item['term_id'] );
		$this->assertContains( 'category', Content::taxonomies() );
	}

	public function test_the_meta_description_is_written_and_undone() {
		$plugin = SeoFields::plugin();
		if ( ! in_array( $plugin, array( 'none', 'yoast' ), true ) ) {
			$this->markTestSkipped( 'Runs without an SEO plugin or with Yoast SEO.' );
		}
		$term = $this->category( 'Old description.' );
		$old  = 'none' === $plugin ? 'Old description.' : '';
		$this->assertSame( $old, Content::term_item( $term )['seo_description'] );

		$this->assertSame( 'changed_since_preview', $this->write( $term, 'seo_description', self::DESCRIPTION, 'not this' )['error'] );
		$r = $this->write( $term, 'seo_description', self::DESCRIPTION, $old );
		$this->assertTrue( $r['ok'], wp_json_encode( $r ) );
		$this->assertSame( $old, $r['previous'] );
		$this->assertSame( self::DESCRIPTION, Content::term_item( get_term( $term->term_id, 'category' ) )['seo_description'] );
		if ( 'yoast' === $plugin ) {
			$meta = get_option( SeoFields::YOAST_TAX_OPTION );
			$this->assertSame( self::DESCRIPTION, $meta['category'][ $term->term_id ]['wpseo_desc'] );
			// The page reads Yoast's indexable, which was rebuilt with the new description.
			$indexable = YoastSEO()->meta->for_term( $term->term_id );
			$this->assertSame( self::DESCRIPTION, $indexable ? $indexable->description : null );
		} else {
			$this->assertSame( self::DESCRIPTION, get_term( $term->term_id, 'category' )->description );
			$this->assertSame( 'not_supported', $this->write( $term, 'seo_title', 'A title', '' )['error'] );
		}

		$undo = Writer::apply( array( Writer::reverse( array_slice( get_option( 'monoranks_change_log' ), -1 )[0] ) ), 'test' )[0];
		$this->assertTrue( $undo['ok'], wp_json_encode( $undo ) );
		$this->assertSame( $old, Content::term_item( get_term( $term->term_id, 'category' ) )['seo_description'] );
		if ( 'yoast' === $plugin ) {
			$meta = get_option( SeoFields::YOAST_TAX_OPTION );
			$this->assertTrue( empty( $meta['category'][ $term->term_id ]['wpseo_desc'] ) );
		}
	}

	/**
	 * Needs Yoast SEO loaded (MONORANKS_WP_PLUGINS=wordpress-seo/wp-seo.php), so CI leaves it out with the yoast group.
	 *
	 * @group yoast
	 */
	public function test_with_yoast_a_title_is_written_keeping_the_other_values() {
		if ( 'yoast' !== SeoFields::plugin() ) {
			$this->markTestSkipped( 'Needs Yoast SEO: MONORANKS_WP_PLUGINS=wordpress-seo/wp-seo.php.' );
		}
		$term = $this->category();
		\WPSEO_Taxonomy_Meta::set_values( $term->term_id, 'category', array( 'wpseo_focuskw' => 'privacy', 'wpseo_desc' => 'Kept.' ) );
		$this->assertTrue( $this->write( $term, 'seo_title', 'Privacy | VeronaLabs', '' )['ok'] );
		$meta = get_option( SeoFields::YOAST_TAX_OPTION )['category'][ $term->term_id ];
		$this->assertSame( 'Privacy | VeronaLabs', $meta['wpseo_title'] );
		$this->assertSame( 'Kept.', $meta['wpseo_desc'] );
		$this->assertSame( 'privacy', $meta['wpseo_focuskw'] );
	}
}
