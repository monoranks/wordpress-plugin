<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Applies changes the site owner approved in MonoRanks. Safe fields (D-22): SEO title, meta description, canonical,
 * noindex, image alt text, redirects. Since 0.7.0 one bounded body edit (R-60): `content` with `insert_top` adds a single
 * approved paragraph at the top of the post body and `remove` takes exactly that paragraph out again (undo). Existing
 * text is never rewritten. Never plugins, themes, settings or users.
 * Each change carries the value MonoRanks saw; if the site changed since, the change is refused.
 */
class Writer {

	const FIELDS = array( 'seo_title', 'seo_description', 'canonical', 'noindex', 'alt', 'redirect', 'content', 'ai_bots', 'llms_txt' );
	const CONTENT_MARK_START = '<!-- monoranks:opening -->';
	const CONTENT_MARK_END   = '<!-- /monoranks:opening -->';

	public static $applying = false;

	public static function apply( array $changes, $actor ) {
		self::$applying = true;
		$results        = array();
		foreach ( $changes as $change ) {
			$results[] = self::one( is_array( $change ) ? $change : array(), $actor );
		}
		self::$applying = false;
		return $results;
	}

	private static function one( array $c, $actor ) {
		$field = isset( $c['field'] ) ? (string) $c['field'] : '';
		$value = isset( $c['value'] ) ? (string) $c['value'] : '';
		$id    = isset( $c['id'] ) ? (string) $c['id'] : '';
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			return array( 'id' => $id, 'ok' => false, 'error' => 'field_not_allowed' );
		}
		if ( strlen( $value ) > ( 'llms_txt' === $field ? 60000 : 2000 ) ) {
			return array( 'id' => $id, 'ok' => false, 'error' => 'value_too_long' );
		}
		// Site-wide AI access (0.8.0): the AI crawler rules and llms.txt live in options, not on a post.
		if ( 'ai_bots' === $field || 'llms_txt' === $field ) {
			$previous = 'ai_bots' === $field ? AiAccess::get_bots() : AiAccess::get_llms();
			if ( isset( $c['expected'] ) && null !== $c['expected'] && (string) $c['expected'] !== $previous ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $previous );
			}
			$ok = 'ai_bots' === $field ? AiAccess::set_bots( $value ) : AiAccess::set_llms( $value );
			if ( ! $ok ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'invalid_value' );
			}
			self::log( $actor, $field, 'site', $previous, $value );
			return array( 'id' => $id, 'ok' => true, 'previous' => $previous );
		}
		if ( 'redirect' === $field ) {
			$from = isset( $c['from'] ) ? (string) $c['from'] : '';
			if ( '' === $from ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'missing_from' );
			}
			if ( '' !== $value && ! self::same_site( $value ) ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'redirect_off_site' );
			}
			$previous = Redirects::get( $from );
			if ( isset( $c['expected'] ) && (string) $c['expected'] !== $previous ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $previous );
			}
			Redirects::set( $from, $value );
			self::log( $actor, $field, $from, $previous, $value );
			return array( 'id' => $id, 'ok' => true, 'previous' => $previous );
		}
		if ( 'alt' === $field ) {
			$attachment = isset( $c['attachment_id'] ) ? (int) $c['attachment_id'] : 0;
			if ( ! $attachment || 'attachment' !== get_post_type( $attachment ) ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'attachment_not_found' );
			}
			$previous = (string) get_post_meta( $attachment, '_wp_attachment_image_alt', true );
			if ( isset( $c['expected'] ) && (string) $c['expected'] !== $previous ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $previous );
			}
			update_post_meta( $attachment, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $value ) ) );
			self::log( $actor, $field, 'attachment:' . $attachment, $previous, $value );
			return array( 'id' => $id, 'ok' => true, 'previous' => $previous );
		}
		$post_id = isset( $c['post_id'] ) ? (int) $c['post_id'] : 0;
		if ( ! $post_id && ! empty( $c['url'] ) ) {
			$post_id = url_to_postid( (string) $c['url'] );
		}
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array( 'id' => $id, 'ok' => false, 'error' => 'post_not_found' );
		}
		if ( 'content' === $field ) {
			return self::content( $post, $c, $id, $actor );
		}
		$previous = (string) SeoFields::get( $post_id, $field );
		if ( isset( $c['expected'] ) && null !== $c['expected'] && (string) $c['expected'] !== $previous ) {
			return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $previous );
		}
		if ( 'canonical' === $field && '' !== $value && ! self::same_site( $value ) ) {
			return array( 'id' => $id, 'ok' => false, 'error' => 'canonical_off_site' );
		}
		$clean = 'canonical' === $field ? esc_url_raw( $value ) : ( 'noindex' === $field ? ( '1' === $value ? '1' : '0' ) : sanitize_text_field( $value ) );
		SeoFields::set( $post_id, $field, $clean );
		clean_post_cache( $post_id );
		self::log( $actor, $field, 'post:' . $post_id, $previous, $clean );
		return array( 'id' => $id, 'ok' => true, 'previous' => $previous, 'post_id' => $post_id );
	}

	/**
	 * Body edit: one paragraph wrapped in markers at the top of post_content. Only <p>, <strong>, <em>, <br> survive
	 * sanitising; the value MonoRanks approved must be at most 2,000 characters (checked above).
	 */
	private static function content( $post, array $c, $id, $actor ) {
		$op      = isset( $c['op'] ) ? (string) $c['op'] : 'insert_top';
		$value   = isset( $c['value'] ) ? (string) $c['value'] : '';
		$current = (string) $post->post_content;
		$pattern = '/' . preg_quote( self::CONTENT_MARK_START, '/' ) . '(.*?)' . preg_quote( self::CONTENT_MARK_END, '/' ) . '\s*/s';
		$has     = preg_match( $pattern, $current, $m ) ? trim( $m[1] ) : '';
		if ( 'remove' === $op ) {
			if ( '' === $has ) {
				return array( 'id' => $id, 'ok' => true, 'previous' => '' );
			}
			if ( isset( $c['expected'] ) && '' !== (string) $c['expected'] && trim( (string) $c['expected'] ) !== $has ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $has );
			}
			$next = preg_replace( $pattern, '', $current, 1 );
		} else {
			if ( '' !== $has && $has !== trim( $value ) ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $has );
			}
			$clean = wp_kses( $value, array( 'p' => array(), 'strong' => array(), 'em' => array(), 'br' => array() ) );
			if ( '' === trim( wp_strip_all_tags( $clean ) ) ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'empty_paragraph' );
			}
			$block = self::CONTENT_MARK_START . "\n" . $clean . "\n" . self::CONTENT_MARK_END . "\n\n";
			$next  = '' !== $has ? preg_replace( $pattern, $block, $current, 1 ) : $block . $current;
		}
		$r = wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $next ) ), true );
		if ( is_wp_error( $r ) ) {
			return array( 'id' => $id, 'ok' => false, 'error' => 'update_failed' );
		}
		clean_post_cache( $post->ID );
		// The stored paragraph is the sanitised one, so that is what the log (and therefore Undo's expected value) carries.
		self::log( $actor, 'content', 'post:' . $post->ID, $has, 'remove' === $op ? '' : trim( $clean ) );
		return array( 'id' => $id, 'ok' => true, 'previous' => $has, 'post_id' => $post->ID );
	}

	/** The change that puts a logged change's previous value back, or null when the row cannot be reversed. */
	public static function reverse( array $row ) {
		$field  = isset( $row['field'] ) ? (string) $row['field'] : '';
		$target = isset( $row['target'] ) ? (string) $row['target'] : '';
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			return null;
		}
		$c = array( 'id' => 'undo', 'field' => $field, 'value' => (string) $row['previous'], 'expected' => (string) $row['value'] );
		if ( 'redirect' === $field ) {
			$c['from'] = $target;
		} elseif ( 'alt' === $field && 0 === strpos( $target, 'attachment:' ) ) {
			$c['attachment_id'] = (int) substr( $target, 11 );
		} elseif ( 0 === strpos( $target, 'post:' ) ) {
			$c['post_id'] = (int) substr( $target, 5 );
		} elseif ( 'site' !== $target ) {
			return null;
		}
		if ( 'content' === $field ) {
			$c['op'] = '' === trim( (string) $row['previous'] ) ? 'remove' : 'insert_top';
		}
		return $c;
	}

	private static function same_site( $url ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$to   = wp_parse_url( $url, PHP_URL_HOST );
		return ! $to || preg_replace( '/^www\./', '', (string) $to ) === preg_replace( '/^www\./', '', (string) $host );
	}

	private static function log( $actor, $field, $target, $previous, $value ) {
		$log   = get_option( 'monoranks_change_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( 'at' => gmdate( 'c' ), 'actor' => sanitize_text_field( (string) $actor ), 'field' => $field, 'target' => $target, 'previous' => $previous, 'value' => $value );
		update_option( 'monoranks_change_log', array_slice( $log, -200 ), false );
	}
}
