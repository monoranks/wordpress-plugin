<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The "Watch how it works" card on the Overview. The video's id is the one constant MONORANKS_VIDEO_ID (monoranks.php);
 * while it is empty the card does not show. The poster ships with the plugin: nothing is asked of YouTube until someone
 * clicks play, and without JavaScript the card is a plain link. Dismissing is per user and per video, so a new video shows
 * once more.
 */
class Video {

	const DISMISSED = 'monoranks_video_dismissed';

	/** The YouTube id, '' when none is set or it does not look like one. */
	public static function id() {
		$id = defined( 'MONORANKS_VIDEO_ID' ) ? (string) MONORANKS_VIDEO_ID : '';
		return preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
	}

	/** What the card needs, or null when it does not show for this user. */
	public static function card() {
		$id = self::id();
		if ( '' === $id || get_user_meta( get_current_user_id(), self::DISMISSED, true ) === $id ) {
			return null;
		}
		return array(
			'watch'  => 'https://www.youtube.com/watch?v=' . $id,
			'embed'  => 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0',
			'poster' => plugins_url( 'resources/assets/video-poster.jpg', MONORANKS_CONNECTOR_FILE ),
		);
	}

	public static function dismiss() {
		update_user_meta( get_current_user_id(), self::DISMISSED, self::id() );
		return array( 'ok' => true );
	}
}
