<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Tells MonoRanks which published posts changed, with their current metadata, so it can recheck them within minutes
 * (TI-WP-002). Sent with the connector key at the end of the request; nothing is sent before the site is connected.
 */
class Notifier {

	private static $queue = array();

	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			self::queue( $post, $new_status === $old_status ? 'updated' : ( 'publish' === $new_status ? 'published' : 'unpublished' ) );
		}
	}

	public static function on_update( $post_id, $after, $before ) {
		if ( 'publish' === $after->post_status ) {
			self::queue( $after, 'updated' );
		}
	}

	private static function queue( $post, $event ) {
		if ( Writer::$applying || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Content::post_types(), true ) ) {
			return;
		}
		// A later event for the same post in this request wins, except that unpublishing is kept.
		if ( isset( self::$queue[ $post->ID ] ) && 'unpublished' === self::$queue[ $post->ID ]['event'] ) {
			return;
		}
		self::$queue[ $post->ID ] = array( 'post_id' => $post->ID, 'url' => get_permalink( $post ), 'event' => $event );
	}

	/** created_term and edited_term: the term's archive page is sent again (or as gone when it has no posts left). */
	public static function on_term( $term_id, $tt_id = 0, $taxonomy = '' ) {
		if ( Writer::$applying || ! in_array( (string) $taxonomy, Content::taxonomies(), true ) ) {
			return;
		}
		$key = Content::term_key( $taxonomy, $term_id );
		if ( isset( self::$queue[ $key ] ) && 'unpublished' === self::$queue[ $key ]['event'] ) {
			return;
		}
		$link               = get_term_link( (int) $term_id, (string) $taxonomy );
		self::$queue[ $key ] = array( 'post_id' => $key, 'url' => is_string( $link ) ? $link : '', 'event' => 'updated', 'term' => array( (int) $term_id, (string) $taxonomy ) );
	}

	/** delete_term: the archive page is gone. The deleted term still knows its link. */
	public static function on_term_delete( $term_id, $tt_id = 0, $taxonomy = '', $deleted = null ) {
		if ( ! in_array( (string) $taxonomy, Content::taxonomies(), true ) ) {
			return;
		}
		$key  = Content::term_key( $taxonomy, $term_id );
		$link = $deleted instanceof \WP_Term ? get_term_link( $deleted ) : '';
		self::$queue[ $key ] = array( 'post_id' => $key, 'url' => is_string( $link ) ? $link : '', 'event' => 'unpublished' );
	}

	public static function flush() {
		if ( ! self::$queue || ! Connection::has_key() ) {
			return;
		}
		$changes = array();
		foreach ( self::$queue as $change ) {
			if ( isset( $change['term'] ) ) {
				$term = get_term( $change['term'][0], $change['term'][1] );
				$item = $term instanceof \WP_Term && (int) $term->count > 0 ? Content::term_item( $term ) : null;
				unset( $change['term'] );
				if ( $item ) {
					$change['item'] = $item;
				} else {
					$change['event'] = 'unpublished';
				}
				$changes[] = $change;
				continue;
			}
			if ( is_string( $change['post_id'] ) ) {
				$changes[] = $change; // A deleted term.
				continue;
			}
			$post = get_post( $change['post_id'] );
			if ( 'unpublished' !== $change['event'] && $post && 'publish' === $post->post_status && ! post_password_required( $post ) ) {
				$change['item'] = Content::item( $post );
			} else {
				$change['event'] = 'unpublished';
			}
			$changes[] = $change;
		}
		self::$queue = array();
		// Sent after the response where possible; blocking with a short timeout so it is not cut off (for example from WP-CLI).
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		Api::post( '/changes', array( 'changes' => $changes ), 5 );
	}
}
