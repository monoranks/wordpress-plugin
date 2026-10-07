<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Image alt text where it renders. A picture in a post body carries its own alt attribute, which wins over the alt stored
 * on the attachment, so MonoRanks reads and writes both. Plain string work on the <img> tags (no DOM round trip), so the
 * rest of the post markup stays byte for byte as it was. An image is matched by the wp-image-<id> class WordPress adds.
 */
class ImageAlt {
	/** The <img> tags of a body: attachment id (0 when unknown), src, and the alt attribute (null when there is none). */
	public static function images( $html ) {
		$out = array();
		if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $tags ) ) {
			return $out;
		}
		foreach ( $tags[0] as $tag ) {
			$id    = preg_match( '/\bclass\s*=\s*(["\'])[^"\']*\bwp-image-(\d+)\b/i', $tag, $m ) ? (int) $m[2] : 0;
			$src   = preg_match( '/\ssrc\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? html_entity_decode( $m[2] ) : '';
			$alt   = preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? html_entity_decode( $m[2], ENT_QUOTES ) : null;
			$out[] = array( 'id' => $id, 'src' => $src, 'alt' => $alt );
		}
		return $out;
	}

	/** The alt in the body for one attachment: null when the body has no such image, '' when it has no alt attribute. */
	public static function body_alt( $html, $attachment_id ) {
		foreach ( self::images( $html ) as $img ) {
			if ( (int) $attachment_id === $img['id'] ) {
				return null === $img['alt'] ? '' : $img['alt'];
			}
		}
		return null;
	}

	/** The body with the alt of every image of that attachment set to $alt. */
	public static function set_body_alt( $html, $attachment_id, $alt ) {
		$attr = ' alt="' . esc_attr( $alt ) . '"';
		return preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $m ) use ( $attachment_id, $attr ) {
				$tag = $m[0];
				if ( ! preg_match( '/\bclass\s*=\s*(["\'])[^"\']*\bwp-image-' . (int) $attachment_id . '\b/i', $tag ) ) {
					return $tag;
				}
				if ( preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag ) ) {
					return preg_replace_callback( '/\salt\s*=\s*(["\'])(.*?)\1/is', static function () use ( $attr ) { return $attr; }, $tag, 1 );
				}
				return preg_replace( '/\s*\/?>$/', $attr . ' />', $tag, 1 );
			},
			(string) $html
		);
	}
}
