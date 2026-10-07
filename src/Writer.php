<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Applies changes the site owner approved in MonoRanks. Safe fields (D-22): SEO title, meta description, canonical,
 * noindex, image alt text, redirects. Since 0.7.0 one bounded body edit (R-60): `content` with `insert_top` adds a single
 * approved paragraph at the top of the post body and `remove` takes exactly that paragraph out again (undo). Existing
 * text is never rewritten, and other plugins' blocks are never touched (see content()). Never plugins, themes, settings or users.
 * Each change carries the value MonoRanks saw; if the site changed since, the change is refused.
 */
class Writer {

	const FIELDS = array( 'seo_title', 'seo_description', 'canonical', 'noindex', 'alt', 'redirect', 'content', 'ai_bots', 'llms_txt' );
	const CONTENT_MARK_START = '<!-- monoranks:opening -->';
	const CONTENT_MARK_END   = '<!-- /monoranks:opening -->';
	/** The metadata name that marks the paragraph block MonoRanks inserted (shown in the editor's List View). */
	const BLOCK_NAME = 'MonoRanks opening';

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
			// Page caches may still hold the old file (often a 404 from before it existed): ask them to drop it (#87).
			$cache = CachePurger::file( 'llms_txt' === $field ? '/llms.txt' : '/robots.txt' );
			return array( 'id' => $id, 'ok' => true, 'previous' => $previous, 'cache' => $cache );
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
			// A cached copy of the old page at the source would keep hiding the redirect (#11).
			return array( 'id' => $id, 'ok' => true, 'previous' => $previous, 'cache' => CachePurger::url( $from ) );
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
			$result = array( 'id' => $id, 'ok' => true, 'previous' => $previous );
			// The page that shows the image: the post the change names, else the post the image was uploaded to (#11).
			$page = isset( $c['post_id'] ) ? (int) $c['post_id'] : (int) wp_get_post_parent_id( $attachment );
			if ( $page && 'publish' === get_post_status( $page ) ) {
				$result['cache'] = CachePurger::post( $page );
			}
			return $result;
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
		// An SEO plugin MonoRanks cannot write for, or a setting that overrides this field: refuse instead of storing a
		// value that would never show on the page.
		$refused = SeoFields::refusal( $post_id, $field );
		if ( $refused ) {
			return array( 'id' => $id, 'ok' => false, 'error' => $refused['error'], 'reason' => $refused['reason'] );
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
		return array( 'id' => $id, 'ok' => true, 'previous' => $previous, 'post_id' => $post_id, 'cache' => CachePurger::post( $post_id ) );
	}

	/**
	 * Body edit: one paragraph at the top of post_content. Only <p>, <strong>, <em>, <br> survive sanitising; the value
	 * MonoRanks approved must be at most 2,000 characters (checked above).
	 *
	 * Block safety rule (monoranks/monoranks#5, #48). A block post is only ever changed in two ways:
	 * - insert: a new core/paragraph block, with its own <!-- wp:paragraph --> delimiters and named "MonoRanks opening"
	 *   through the block's metadata, goes in front of the first block;
	 * - remove or replace: that same paragraph block (or the marker-wrapped paragraph older versions wrote) is taken out.
	 * The markup of every other block is never rewritten, and blocks from other plugins (rank-math/*, yoast/*, …) are never
	 * touched at all: their stored markup has to match what their own save() produces, which only their editor knows.
	 * Classic (non-block) posts keep the marker-wrapped paragraph they always had.
	 * Every write is checked afterwards (see write_checked()); a write that breaks a block is undone and reported as failed.
	 */
	private static function content( $post, array $c, $id, $actor ) {
		$op      = isset( $c['op'] ) ? (string) $c['op'] : 'insert_top';
		$value   = isset( $c['value'] ) ? (string) $c['value'] : '';
		$current = (string) $post->post_content;
		$found   = self::find_opening( $current );
		$has     = $found ? $found['html'] : '';
		$piece   = '';
		if ( 'remove' === $op ) {
			if ( '' === $has ) {
				return array( 'id' => $id, 'ok' => true, 'previous' => '' );
			}
			if ( isset( $c['expected'] ) && '' !== (string) $c['expected'] && trim( (string) $c['expected'] ) !== $has ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $has );
			}
			$next   = substr_replace( $current, '', $found['start'], $found['length'] );
			$stored = '';
		} else {
			$clean = wp_kses( $value, array( 'p' => array(), 'strong' => array(), 'em' => array(), 'br' => array() ) );
			if ( '' === trim( wp_strip_all_tags( $clean ) ) ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'empty_paragraph' );
			}
			$blocks = self::is_block_content( $current );
			$stored = $blocks ? self::paragraph_html( $clean ) : trim( $clean );
			if ( '' !== $has && $has !== trim( $value ) && $has !== $stored ) {
				return array( 'id' => $id, 'ok' => false, 'error' => 'changed_since_preview', 'current' => $has );
			}
			$piece = $blocks ? self::paragraph_block( $clean ) : self::CONTENT_MARK_START . "\n" . $clean . "\n" . self::CONTENT_MARK_END . "\n\n";
			$next  = $found ? substr_replace( $current, $piece, $found['start'], $found['length'] ) : $piece . $current;
		}
		$failed = self::write_checked( $post->ID, $current, $next, $piece );
		if ( $failed ) {
			return array( 'id' => $id, 'ok' => false, 'error' => $failed['error'], 'reason' => $failed['reason'] );
		}
		// The stored paragraph is the sanitised one, so that is what the log (and therefore Undo's expected value) carries.
		self::log( $actor, 'content', 'post:' . $post->ID, $has, $stored );
		return array( 'id' => $id, 'ok' => true, 'previous' => $has, 'post_id' => $post->ID, 'cache' => CachePurger::post( $post->ID ) );
	}

	/** Block content, as has_blocks() decides it: at least one block comment delimiter. */
	public static function is_block_content( $content ) {
		return false !== strpos( (string) $content, '<!-- wp:' );
	}

	/** One <p> around the sanitised paragraph (a paragraph block holds exactly one <p>). */
	public static function paragraph_html( $clean ) {
		return '<p>' . trim( (string) preg_replace( '#</?p>#i', '', (string) $clean ) ) . '</p>';
	}

	/**
	 * The paragraph block MonoRanks inserts, in exactly the form core's serialize_block() writes for these attributes, so
	 * the editor's own save() of core/paragraph produces the same markup (no "unexpected or invalid content" notice).
	 */
	public static function paragraph_block( $clean ) {
		return '<!-- wp:paragraph {"metadata":{"name":"' . self::BLOCK_NAME . '"}} -->' . "\n" . self::paragraph_html( $clean ) . "\n" . '<!-- /wp:paragraph -->' . "\n\n";
	}

	/**
	 * Where the opening MonoRanks wrote sits in the content: the named paragraph block, or the marker-wrapped paragraph
	 * that versions before 0.1.14 wrote. Returns start, length (with the whitespace after it) and the paragraph html, or
	 * null. Only our own paragraph is ever matched; no other block's markup is read or rewritten.
	 */
	public static function find_opening( $content ) {
		$content  = (string) $content;
		$patterns = array(
			// A paragraph block delimiter whose attributes carry our metadata name. Paragraph blocks have no inner blocks, so
			// the first closing delimiter after it is its own. (?!-->) keeps the attribute match inside one comment.
			'/<!-- wp:paragraph \{(?:(?!-->).)*?"name":"' . preg_quote( self::BLOCK_NAME, '/' ) . '"(?:(?!-->).)*?\} -->(.*?)<!-- \/wp:paragraph -->\s*/s',
			'/' . preg_quote( self::CONTENT_MARK_START, '/' ) . '(.*?)' . preg_quote( self::CONTENT_MARK_END, '/' ) . '\s*/s',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $m, PREG_OFFSET_CAPTURE ) ) {
				return array( 'start' => $m[0][1], 'length' => strlen( $m[0][0] ), 'html' => trim( $m[1][0] ) );
			}
		}
		return null;
	}

	/**
	 * Writes post_content and checks the result; on any problem the previous content is put back byte for byte and the
	 * reason is returned (null when the write is good). Checked after the write:
	 * - the stored content is exactly what we meant to store, so no filter rewrote another block's markup on the way in;
	 * - the block we inserted parses back as a registered core/paragraph and serialize_block() of it equals its stored
	 *   markup (the round trip the editor's validation relies on);
	 * - on a block post, no other block appeared or disappeared.
	 * $piece is the markup we added ('' for a removal).
	 */
	private static function write_checked( $post_id, $before, $next, $piece ) {
		// kses would re-filter the whole post (other plugins' blocks included) when the current user lacks unfiltered_html.
		// Our own paragraph is already sanitised above and the rest is content that is already stored, so kses is off for
		// this one save and back on afterwards.
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}
		try {
			$r = wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $next ) ), true );
		} finally {
			// Always back on, even if a save hook throws: the rest of the request must stay filtered.
			if ( $kses ) {
				kses_init_filters();
			}
		}
		clean_post_cache( $post_id );
		if ( is_wp_error( $r ) || ! $r ) {
			$stored = (string) get_post_field( 'post_content', $post_id, 'raw' );
			if ( $stored !== $before ) {
				self::restore( $post_id, $before );
			}
			return array( 'error' => 'update_failed', 'reason' => 'WordPress did not save the post.' );
		}
		$reason = self::check_stored( (string) get_post_field( 'post_content', $post_id, 'raw' ), $before, $next, $piece );
		if ( null === $reason ) {
			return null;
		}
		self::restore( $post_id, $before );
		return array( 'error' => 'block_check_failed', 'reason' => $reason . ' The post was put back as it was.' );
	}

	/** Why the stored content is not safe to keep, or null when it is. */
	public static function check_stored( $stored, $before, $next, $piece ) {
		if ( $stored !== $next ) {
			return 'WordPress or another plugin changed the post markup while saving it.';
		}
		if ( ! self::is_block_content( $before ) && ! self::is_block_content( $next ) ) {
			return null;
		}
		$ours = array();
		$rest = array();
		foreach ( parse_blocks( $stored ) as $block ) {
			if ( null === $block['blockName'] ) {
				continue;
			}
			if ( self::is_ours( $block ) ) {
				$ours[] = $block;
			} else {
				$rest[] = $block['blockName'];
			}
		}
		if ( self::is_block_content( $piece ) ) {
			if ( 1 !== count( $ours ) ) {
				return 'The new paragraph block did not parse back as one block.';
			}
			if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( $ours[0]['blockName'] ) ) {
				return 'The paragraph block is not registered on this site.';
			}
			if ( serialize_block( $ours[0] ) !== rtrim( $piece ) ) {
				return 'The new paragraph block does not match what the block editor would save.';
			}
		} elseif ( $ours ) {
			return 'The MonoRanks paragraph block is still in the post.';
		}
		// Every other block is still there, in the same order.
		$was = array();
		foreach ( parse_blocks( $before ) as $block ) {
			if ( null !== $block['blockName'] && ! self::is_ours( $block ) ) {
				$was[] = $block['blockName'];
			}
		}
		return $was === $rest ? null : 'The other blocks of the post changed.';
	}

	/** The paragraph block MonoRanks inserted: core/paragraph carrying our metadata name. */
	private static function is_ours( array $block ) {
		return 'core/paragraph' === $block['blockName'] && isset( $block['attrs']['metadata']['name'] ) && self::BLOCK_NAME === $block['attrs']['metadata']['name'];
	}

	/**
	 * Puts the content back exactly as it was before our write. A direct update, so no content filter can alter it on the
	 * way back in, then a revision so the post history ends on the restored content.
	 */
	private static function restore( $post_id, $before ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a byte-exact rollback must bypass content filters; the post cache is cleared right after.
		$wpdb->update( $wpdb->posts, array( 'post_content' => $before ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		if ( function_exists( 'wp_save_post_revision' ) ) {
			wp_save_post_revision( $post_id );
		}
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

	/**
	 * The value WordPress holds now for a fix that can be checked from here, or null. Only image alt text is checked: it is
	 * stored per image, so a changed alt means that one fix is out of date (never the whole page).
	 */
	public static function current_value( array $fix ) {
		if ( isset( $fix['field'] ) && 'alt' === $fix['field'] && ! empty( $fix['attachment_id'] ) && 'attachment' === get_post_type( (int) $fix['attachment_id'] ) ) {
			return (string) get_post_meta( (int) $fix['attachment_id'], '_wp_attachment_image_alt', true );
		}
		return null;
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
