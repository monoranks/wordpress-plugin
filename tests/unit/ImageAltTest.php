<?php
namespace MonoRanks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MonoRanks\ImageAlt;
use MonoRanks\Writer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/** Alt text is read and checked where it renders: the alt in the post body, else the attachment's stored alt. */
class ImageAltTest extends TestCase {
	const BODY = '<!-- wp:image {"id":7} --><figure class="wp-block-image"><img src="https://e.test/a.jpg" alt="" class="wp-image-7"/></figure><!-- /wp:image --><p>x</p><img class="wp-image-9 size-full" src="https://e.test/b.jpg">';

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\when( 'esc_attr' )->alias( 'htmlspecialchars' );
	}
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_reads_the_alt_attribute_of_each_image_in_the_body() {
		$images = ImageAlt::images( self::BODY );
		$this->assertSame( array( 7, 9 ), array( $images[0]['id'], $images[1]['id'] ) );
		$this->assertSame( '', $images[0]['alt'] );
		$this->assertNull( $images[1]['alt'] );
		$this->assertSame( '', ImageAlt::body_alt( self::BODY, 9 ) );
		$this->assertNull( ImageAlt::body_alt( self::BODY, 11 ) );
	}

	public function test_sets_the_alt_on_that_image_only_and_leaves_the_rest_alone() {
		$out = ImageAlt::set_body_alt( self::BODY, 9, 'A "red" door' );
		$this->assertStringContainsString( 'src="https://e.test/b.jpg" alt="A &quot;red&quot; door" />', $out );
		$this->assertStringContainsString( 'alt="" class="wp-image-7"/>', $out );
		$out = ImageAlt::set_body_alt( self::BODY, 7, 'Door' );
		$this->assertStringContainsString( 'alt="Door" class="wp-image-7"/>', $out );
		$this->assertSame( 'Door', ImageAlt::body_alt( $out, 7 ) );
	}

	private function wp( $stored, $content ) {
		Functions\when( 'get_post_meta' )->justReturn( $stored );
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_content' => $content ) );
		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
	}

	public function test_the_alt_that_renders_is_the_body_alt_when_the_image_is_in_the_post() {
		$this->wp( 'Stored', self::BODY );
		$state = Writer::alt_state( 7, 5 );
		$this->assertSame( 'Stored', $state['stored'] );
		$this->assertSame( '', $state['current'] );
		$this->assertSame( 'Stored', Writer::alt_state( 7, 0 )['current'] );
		$this->assertSame( 'Stored', Writer::alt_state( 11, 5 )['current'] );
	}

	public function test_a_fix_is_out_of_date_only_when_its_value_matches_neither_place() {
		$this->wp( 'Stored', self::BODY );
		$fix = array( 'field' => 'alt', 'attachment_id' => 7, 'post_id' => 5 );
		$this->assertSame( '', Writer::current_value( $fix + array( 'before' => '' ) ) );
		$this->assertSame( 'Stored', Writer::current_value( $fix + array( 'before' => 'Stored' ) ) );
		$this->assertSame( '', Writer::current_value( $fix + array( 'before' => 'Someone else' ) ) );
	}
}
