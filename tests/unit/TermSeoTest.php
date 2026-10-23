<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MonoRanks\Content;
use MonoRanks\Rest;
use MonoRanks\SeoFields;
use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Category, tag and other archive pages (monoranks/wordpress-plugin#25): sent as content items and their SEO title and
 * meta description written and undone in Rank Math, Yoast or, without an SEO plugin, the term's own description.
 * An SEO plugin is detected by the constant it defines, which cannot be undefined, so those tests run in their own process.
 */
class TermSeoTest extends TestCase {
	/** @var array<int,array<string,mixed>> term id => meta key => value */
	private $term_meta = array();
	/** @var array<string,mixed> */
	private $options = array();
	/** @var array<int,\WP_Term> */
	private $terms = array();
	/** @var bool */
	private $can = true;

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		if ( ! class_exists( 'WP_Term' ) ) {
			eval( 'class WP_Term { public $term_id; public $taxonomy; public $name = ""; public $description = ""; public $count = 1; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a stand-in for core's class in unit tests.
		}
		$this->terms[12] = $this->term( 12, 'category', 'News', 'All our <b>news</b> in one place.' );
		$meta    = &$this->term_meta;
		$options = &$this->options;
		$terms   = &$this->terms;
		$can     = &$this->can;
		$tax     = static function ( $name, $public, $queryable, $rewrite ) {
			return (object) array(
				'name'               => $name,
				'public'             => $public,
				'publicly_queryable' => $queryable,
				'rewrite'            => $rewrite,
				'labels'             => (object) array( 'singular_name' => 'post_tag' === $name ? 'Tag' : 'Category' ),
				'cap'                => (object) array( 'edit_terms' => 'manage_categories' ),
			);
		};
		$taxes = array(
			'category'    => $tax( 'category', true, true, array( 'slug' => 'category' ) ),
			'post_tag'    => $tax( 'post_tag', true, true, array( 'slug' => 'tag' ) ),
			'post_format' => $tax( 'post_format', true, true, array( 'slug' => 'type' ) ),
			'hidden'      => $tax( 'hidden', true, false, false ),
		);
		Functions\when( 'get_taxonomies' )->justReturn( $taxes );
		Functions\when( 'get_taxonomy' )->alias( static function ( $name ) use ( $taxes ) { return isset( $taxes[ $name ] ) ? $taxes[ $name ] : false; } );
		Functions\when( 'get_term' )->alias( static function ( $id ) use ( &$terms ) { return isset( $terms[ (int) $id ] ) ? $terms[ (int) $id ] : null; } );
		Functions\when( 'get_term_link' )->alias( static function ( $term ) use ( &$terms ) {
			$term = is_object( $term ) ? $term : $terms[ (int) $term ];
			return 'https://example.com/category/' . strtolower( $term->name ) . '/';
		} );
		Functions\when( 'get_term_field' )->alias( static function ( $field, $id ) use ( &$terms ) { return $terms[ (int) $id ]->$field; } );
		Functions\when( 'wp_update_term' )->alias( static function ( $id, $taxonomy, $args ) use ( &$terms ) { $terms[ (int) $id ]->description = $args['description']; return array( 'term_id' => $id ); } );
		Functions\when( 'get_term_meta' )->alias( static function ( $id, $key ) use ( &$meta ) { return isset( $meta[ $id ][ $key ] ) ? $meta[ $id ][ $key ] : ''; } );
		Functions\when( 'update_term_meta' )->alias( static function ( $id, $key, $value ) use ( &$meta ) { $meta[ $id ][ $key ] = $value; return true; } );
		Functions\when( 'delete_term_meta' )->alias( static function ( $id, $key ) use ( &$meta ) { unset( $meta[ $id ][ $key ] ); return true; } );
		Functions\when( 'get_option' )->alias( static function ( $k, $d = false ) use ( &$options ) { return array_key_exists( $k, $options ) ? $options[ $k ] : $d; } );
		Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$options ) { $options[ $k ] = $v; return true; } );
		Functions\when( 'current_user_can' )->alias( static function () use ( &$can ) { return $can; } );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias( static function ( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); } );
		Functions\when( 'sanitize_text_field' )->alias( static function ( $s ) { return trim( wp_strip_all_tags( (string) $s ) ); } );
		Functions\when( 'wp_strip_all_tags' )->alias( static function ( $s ) { return strip_tags( (string) $s ); } );
		Functions\when( 'wp_kses_post' )->alias( static function ( $s ) { return strip_tags( (string) $s, '<b><strong><em><a><p>' ); } );
		Functions\when( 'wp_trim_words' )->alias( static function ( $s, $n ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( (string) $s ) ), 0, $n ) ); } );
		Functions\when( 'clean_term_cache' )->justReturn( null );
		Functions\when( 'home_url' )->alias( static function ( $path = '' ) { return 'https://example.com' . $path; } );
		Functions\when( 'untrailingslashit' )->alias( static function ( $s ) { return rtrim( $s, '/\\' ); } );
		Functions\when( 'has_action' )->justReturn( false );
		foreach ( array( 'rocket_clean_files', 'rocket_clean_home', 'w3tc_flush_url', 'wpsc_delete_url_cache', 'sg_cachepress_purge_cache', 'pantheon_wp_clear_edge_paths' ) as $fn ) {
			if ( function_exists( $fn ) ) {
				Functions\when( $fn )->justReturn( null );
			}
		}
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	private function term( $id, $taxonomy, $name, $description ) {
		$t              = new \WP_Term();
		$t->term_id     = $id;
		$t->taxonomy    = $taxonomy;
		$t->name        = $name;
		$t->description = $description;
		return $t;
	}

	private function write( $field, $value, $expected = null, $extra = array() ) {
		$c = array_merge( array( 'id' => 'c1', 'field' => $field, 'term_id' => 12, 'taxonomy' => 'category', 'value' => $value ), $extra );
		if ( null !== $expected ) {
			$c['expected'] = $expected;
		}
		return Writer::apply( array( $c ), 'test' )[0];
	}

	public function test_archive_taxonomies_have_a_public_archive_and_skip_post_formats() {
		$this->assertSame( array( 'category', 'post_tag' ), Content::taxonomies() );
	}

	public function test_without_an_seo_plugin_a_term_is_sent_with_its_description_as_meta_description() {
		$item = Content::term_item( $this->terms[12] );
		$this->assertSame(
			array(
				'id'              => 'term:category:12',
				'type'            => 'term:category',
				'type_label'      => 'Category',
				'url'             => 'https://example.com/category/news/',
				'title'           => 'News',
				'excerpt'         => 'All our news in one place.',
				'author'          => null,
				'author_avatar'   => null,
				'categories'      => array(),
				'tags'            => array(),
				'published_at'    => null,
				'modified_at'     => null,
				'seo_title'       => '',
				'seo_description' => 'All our <b>news</b> in one place.',
				'canonical'       => '',
				'noindex'         => false,
				'images'          => array(),
				'term_id'         => 12,
				'taxonomy'        => 'category',
			),
			$item
		);
	}

	public function test_without_an_seo_plugin_the_description_is_written_and_undone_but_a_title_is_refused() {
		$r = $this->write( 'seo_title', 'News from VeronaLabs' );
		$this->assertSame( array( 'id' => 'c1', 'ok' => false, 'error' => 'not_supported', 'reason' => 'Titles of category pages need an SEO plugin.' ), $r );

		$old = 'All our <b>news</b> in one place.';
		$r   = $this->write( 'seo_description', 'Product news and releases.', 'something else' );
		$this->assertSame( array( 'id' => 'c1', 'ok' => false, 'error' => 'changed_since_preview', 'current' => $old ), $r );
		$this->assertSame( $old, $this->terms[12]->description );

		$r = $this->write( 'seo_description', 'Product news and releases.', $old );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( $old, $r['previous'] );
		$this->assertSame( 12, $r['term_id'] );
		$this->assertSame( 'category', $r['taxonomy'] );
		$this->assertSame( 'Product news and releases.', $this->terms[12]->description );

		// Undo: a normal change back to the previous value, expecting what was written. The HTML of the old description stays.
		$r = $this->write( 'seo_description', $old, 'Product news and releases.' );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( $old, $this->terms[12]->description );

		// The change log row reverses into the same kind of change.
		$log = $this->options['monoranks_change_log'];
		$this->assertSame( 'term:category:12', $log[0]['target'] );
		$this->assertSame( array( 'id' => 'undo', 'field' => 'seo_description', 'value' => $old, 'expected' => 'Product news and releases.', 'taxonomy' => 'category', 'term_id' => 12 ), Writer::reverse( $log[0] ) );
	}

	public function test_a_term_write_purges_the_archive_page_from_page_caches() {
		Filters\expectApplied( 'monoranks_purge_urls' )->once()->with( array( 'https://example.com/category/news/' ), 0 )->andReturnFirstArg();
		$r = $this->write( 'seo_description', 'Product news.' );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( array( 'purged', 'failed', 'none' ), array_keys( $r['cache'] ) );
	}

	public function test_unknown_terms_other_fields_and_users_without_the_capability_are_refused() {
		$this->assertSame( 'term_not_found', $this->write( 'seo_description', 'x', null, array( 'term_id' => 99 ) )['error'] );
		$this->assertSame( 'term_not_found', $this->write( 'seo_description', 'x', null, array( 'taxonomy' => 'hidden' ) )['error'] );
		$this->assertSame( 'field_not_allowed', $this->write( 'canonical', 'https://example.com/' )['error'] );
		$this->can = false;
		$this->assertSame( 'forbidden', $this->write( 'seo_description', 'x' )['error'] );
		$this->assertSame( 'All our <b>news</b> in one place.', $this->terms[12]->description );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rank_math_term_meta_is_sent_written_and_cleared_by_undo() {
		define( 'RANK_MATH_VERSION', '1.0.250' );
		$this->term_meta[12] = array( 'rank_math_description' => 'Old description', 'rank_math_robots' => array( 'noindex' ) );
		$item                = Content::term_item( $this->terms[12] );
		$this->assertSame( '', $item['seo_title'] );
		$this->assertSame( 'Old description', $item['seo_description'] );
		$this->assertTrue( $item['noindex'] );

		$r = $this->write( 'seo_title', 'News | <i>VeronaLabs</i>', 'Stale title' );
		$this->assertSame( array( 'id' => 'c1', 'ok' => false, 'error' => 'changed_since_preview', 'current' => '' ), $r );

		$r = $this->write( 'seo_title', 'News | <i>VeronaLabs</i>', '' );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( '', $r['previous'] );
		$this->assertSame( 'News | VeronaLabs', $this->term_meta[12]['rank_math_title'] );

		// Undo writes '' back, which clears the field.
		$this->assertTrue( $this->write( 'seo_title', '', 'News | VeronaLabs' )['ok'] );
		$this->assertArrayNotHasKey( 'rank_math_title', $this->term_meta[12] );
		$this->assertSame( 'Old description', $this->term_meta[12]['rank_math_description'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_yoast_taxonomy_meta_is_sent_and_written_keeping_the_other_keys() {
		define( 'WPSEO_VERSION', '28.5' );
		$this->options['wpseo_taxonomy_meta'] = array(
			'category' => array( 12 => array( 'wpseo_desc' => 'Old description', 'wpseo_focuskw' => 'news', 'wpseo_noindex' => 'noindex' ) ),
			'post_tag' => array( 3 => array( 'wpseo_title' => 'A tag' ) ),
		);
		$item = Content::term_item( $this->terms[12] );
		$this->assertSame( '', $item['seo_title'] );
		$this->assertSame( 'Old description', $item['seo_description'] );
		$this->assertTrue( $item['noindex'] );

		$this->assertSame( 'changed_since_preview', $this->write( 'seo_description', 'New', 'Not this' )['error'] );
		$r = $this->write( 'seo_description', 'Product news and releases.', 'Old description' );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( 'Old description', $r['previous'] );
		$saved = $this->options['wpseo_taxonomy_meta'];
		$this->assertSame( array( 'wpseo_desc' => 'Product news and releases.', 'wpseo_focuskw' => 'news', 'wpseo_noindex' => 'noindex' ), $saved['category'][12] );
		$this->assertSame( array( 3 => array( 'wpseo_title' => 'A tag' ) ), $saved['post_tag'] );

		// Undo of a title written on a term with no other values removes the term's entry again.
		$this->terms[5] = $this->term( 5, 'category', 'Empty', '' );
		$this->assertTrue( $this->write( 'seo_title', 'Title', '', array( 'term_id' => 5 ) )['ok'] );
		$this->assertSame( array( 'wpseo_title' => 'Title' ), $this->options['wpseo_taxonomy_meta']['category'][5] );
		$this->assertTrue( $this->write( 'seo_title', '', 'Title', array( 'term_id' => 5 ) )['ok'] );
		$this->assertArrayNotHasKey( 5, $this->options['wpseo_taxonomy_meta']['category'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_yoast_own_api_gets_every_stored_value_of_the_term() {
		define( 'WPSEO_VERSION', '28.5' );
		eval( 'class WPSEO_Utils {} class WPSEO_Taxonomy_Meta { public static $calls = array(); public static function set_values( $id, $tax, array $values ) { self::$calls[] = array( $id, $tax, $values ); } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a stand-in for Yoast's class.
		$this->options['wpseo_taxonomy_meta'] = array( 'category' => array( 12 => array( 'wpseo_desc' => 'Old', 'wpseo_focuskw' => 'news' ) ) );
		$this->assertTrue( $this->write( 'seo_title', 'News', '' )['ok'] );
		$this->assertSame( array( array( 12, 'category', array( 'wpseo_desc' => 'Old', 'wpseo_focuskw' => 'news', 'wpseo_title' => 'News' ) ) ), \WPSEO_Taxonomy_Meta::$calls );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_other_seo_plugins_refuse_term_writes_and_do_not_offer_them() {
		define( 'AIOSEO_VERSION', '4.7.0' );
		$r = $this->write( 'seo_description', 'x' );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'not_supported', $r['error'] );
		$this->assertSame( 'MonoRanks cannot write category pages in All in One SEO yet.', $r['reason'] );
		$this->assertFalse( SeoFields::term_supported() );
		$this->assertSame( '', Content::term_item( $this->terms[12] )['seo_description'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_sync_sends_term_pages_after_the_post_pages() {
		// 3 published posts in pages of 2, then 3 terms: pages 1-2 are posts (not under test here), pages 3-4 terms.
		eval( 'class WP_Query { public $posts = array(); public $found_posts = 0; public $max_num_pages = 0; public function __construct( $a ) { $all = array( 1, 2, 3 ); $this->found_posts = 3; $this->posts = array_slice( $all, ( $a["paged"] - 1 ) * $a["posts_per_page"], $a["posts_per_page"] ); if ( ! $this->posts ) { $this->found_posts = 0; } } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a stand-in for core's class.
		$this->terms[13] = $this->term( 13, 'category', 'Releases', '' );
		$this->terms[14] = $this->term( 14, 'post_tag', 'Tips', '' );
		$terms           = $this->terms;
		Functions\when( 'get_post_types' )->justReturn( array( 'post' => 'post' ) );
		Functions\when( 'wp_count_terms' )->justReturn( 3 );
		Functions\when( 'get_terms' )->alias( static function ( $a ) use ( $terms ) {
			return $a['hide_empty'] ? array_slice( array_values( $terms ), $a['offset'], $a['number'] ) : array();
		} );

		$r = Content::page( 3, 2 );
		$this->assertSame( 6, $r['total'] );
		$this->assertSame( 4, $r['pages'] );
		$this->assertSame( array( 'term:category:12', 'term:category:13' ), array_column( $r['items'], 'id' ) );
		$r = Content::page( 4, 2 );
		$this->assertSame( array( 'term:post_tag:14' ), array_column( $r['items'], 'id' ) );
		$this->assertSame( 4, $r['pages'] );
	}

	public function test_edited_and_deleted_terms_are_pushed_like_post_changes() {
		$this->options['monoranks_connection'] = array( 'key' => 'k', 'api_base' => 'https://app.monoranks.com' );
		$this->terms[13]                       = $this->term( 13, 'category', 'Releases', '' );
		$sent                                  = array();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'wp_remote_post' )->alias( static function ( $url, $args ) use ( &$sent ) { $sent[] = json_decode( $args['body'], true ); return array(); } );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{}' );

		\MonoRanks\Notifier::on_term( 12, 0, 'category' );
		\MonoRanks\Notifier::on_term( 99, 0, 'post_format' );
		\MonoRanks\Notifier::on_term_delete( 13, 0, 'category', $this->terms[13] );
		\MonoRanks\Notifier::flush();

		$this->assertCount( 1, $sent );
		$changes = $sent[0]['changes'];
		$this->assertCount( 2, $changes );
		$this->assertSame( 'term:category:12', $changes[0]['post_id'] );
		$this->assertSame( 'updated', $changes[0]['event'] );
		$this->assertSame( 'https://example.com/category/news/', $changes[0]['url'] );
		$this->assertSame( 12, $changes[0]['item']['term_id'] );
		$this->assertArrayNotHasKey( 'term', $changes[0] );
		$this->assertSame( array( 'post_id' => 'term:category:13', 'url' => 'https://example.com/category/releases/', 'event' => 'unpublished' ), $changes[1] );
	}

	public function test_status_offers_term_writes_and_counts_terms_per_taxonomy() {
		Functions\when( 'get_post_types' )->justReturn( array( 'post' => 'post' ) );
		Functions\when( 'wp_count_posts' )->justReturn( (object) array( 'publish' => 4 ) );
		Functions\when( 'wp_count_terms' )->alias( static function ( $args ) { return 'category' === $args['taxonomy'][0] ? 6 : 2; } );
		Functions\when( 'get_bloginfo' )->justReturn( 'x' );
		$status = Rest::status_payload();
		$this->assertSame( array( 'category' => 6, 'post_tag' => 2 ), $status['taxonomies'] );
		$this->assertContains( 'term_seo', $status['can_write'] );
		$this->assertContains( 'seo_title', $status['can_write'] );
	}
}
