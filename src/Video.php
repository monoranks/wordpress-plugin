<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The "Video" button in the top bar of the plugin's screens. The video's id is the one constant MONORANKS_VIDEO_ID
 * (monoranks.php); while it is not valid the button does not show. Nothing is asked of YouTube until someone presses it,
 * and without JavaScript it is a plain link.
 */
class Video {

	/** The YouTube id, '' when none is set or it does not look like one. */
	public static function id() {
		$id = defined( 'MONORANKS_VIDEO_ID' ) ? (string) MONORANKS_VIDEO_ID : '';
		return preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
	}

	/** Where the button goes, or null when there is no video. */
	public static function links() {
		$id = self::id();
		if ( '' === $id ) {
			return null;
		}
		return array(
			'watch' => 'https://www.youtube.com/watch?v=' . $id,
			'embed' => 'https://www.youtube.com/embed/' . $id . '?autoplay=1&rel=0',
		);
	}
}
