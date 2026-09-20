<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tells MonoRanks which published posts changed, with their current metadata, so it can recheck them within minutes
 * (TI-WP-002). Sent with the connector key at the end of the request; nothing is sent before the site is connected.
 */
class MonoRanks_Notifier {

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
		if ( MonoRanks_Writer::$applying || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, MonoRanks_Content::post_types(), true ) ) {
			return;
		}
		// A later event for the same post in this request wins, except that unpublishing is kept.
		if ( isset( self::$queue[ $post->ID ] ) && 'unpublished' === self::$queue[ $post->ID ]['event'] ) {
			return;
		}
		self::$queue[ $post->ID ] = array( 'post_id' => $post->ID, 'url' => get_permalink( $post ), 'event' => $event );
	}

	public static function flush() {
		if ( ! self::$queue || ! MonoRanks_Connection::has_key() ) {
			return;
		}
		$changes = array();
		foreach ( self::$queue as $change ) {
			$post = get_post( $change['post_id'] );
			if ( 'unpublished' !== $change['event'] && $post && 'publish' === $post->post_status && ! post_password_required( $post ) ) {
				$change['item'] = MonoRanks_Content::item( $post );
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
		MonoRanks_Api::post( '/changes', array( 'changes' => $changes ), 5 );
	}
}
