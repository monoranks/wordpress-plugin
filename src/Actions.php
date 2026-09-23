<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/** What the buttons on the plugin's screens do. Each method returns a result code that Admin::notice_for() turns into a notice. */
class Actions {

	/** A key pasted by hand. $base is only honoured on development sites; everywhere else the address is MONORANKS_API_BASE. */
	public static function connect_key( $key, $base = '' ) {
		$key  = Connection::valid_key( $key );
		$base = Connection::valid_base( $base && Admin::shows_address_field() ? $base : MONORANKS_API_BASE );
		if ( ! $key ) {
			return 'bad_key';
		}
		if ( ! $base ) {
			return 'bad_address';
		}
		$previous = Connection::get();
		Connection::update( array( 'api_base' => $base, 'key' => $key, 'via' => 'manual', 'key_state' => 'ok', 'paired_at' => gmdate( 'c' ), 'paired_by' => wp_get_current_user()->user_login ) );
		$res = Api::send_status();
		if ( 200 !== $res['code'] ) {
			$previous ? update_option( 'monoranks_connection', $previous, false ) : Connection::clear();
			return 401 === $res['code'] ? 'rejected' : ( 403 === $res['code'] ? 'other_site' : 'unreachable' );
		}
		if ( isset( $res['body']['site_id'] ) ) {
			Connection::update( array( 'site_id' => sanitize_text_field( (string) $res['body']['site_id'] ) ) );
		}
		Sync::start();
		Insights::refresh_soon();
		return 'connected';
	}

	public static function send_now() {
		Api::send_status();
		Sync::start();
		Insights::refresh_soon();
		return 'sent';
	}

	public static function disconnect() {
		// Tell MonoRanks first, so it revokes this website's key and stops showing the site as connected; a failure here
		// must not keep the plugin connected, so the answer is not checked.
		Api::post( '/disconnect', array( 'reason' => 'disconnected in WordPress' ), 8 );
		Sync::unschedule();
		Connection::clear();
		Insights::clear();
		return 'disconnected';
	}

	/**
	 * Erases everything the plugin stored in WordPress: the connection, the cached audit results and per-page scores,
	 * the redirects and AI access files it was asked to write, and the change log. Pages themselves are left as they
	 * are — a fix that was written stays written; use Undo for those first.
	 */
	public static function delete_everything() {
		Sync::unschedule();
		Connection::clear();
		Insights::clear();
		foreach ( array( 'monoranks', 'monoranks_redirects', 'monoranks_change_log', 'monoranks_ai_bots', 'monoranks_llms_txt' ) as $option ) {
			delete_option( $option );
		}
		delete_transient( 'monoranks_sync_lock' );
		delete_transient( 'monoranks_refreshing' );
		return 'deleted';
	}

	/**
	 * Apply one fix approved in MonoRanks (its id) or all of them ('all'), then tell MonoRanks what happened. A body edit
	 * is never part of 'all': it is reviewed as a before/after in MonoRanks and applied one at a time from there.
	 */
	public static function apply( $which ) {
		$overview = Insights::overview();
		$changes  = array();
		foreach ( $overview ? $overview['ready'] : array() as $fix ) {
			$mine = 'all' === $which ? 'content' !== $fix['field'] : $fix['id'] === $which;
			if ( $mine ) {
				$changes[] = self::change_from_ready( $fix );
			}
		}
		if ( ! $changes ) {
			return 'apply_failed';
		}
		$results = Writer::apply( $changes, 'MonoRanks (' . wp_get_current_user()->user_login . ')' );
		$done    = array();
		foreach ( $results as $r ) {
			if ( ! empty( $r['ok'] ) ) {
				$done[] = $r['id'];
			}
		}
		Insights::forget_ready( $done );
		Api::post( '/fixes', array( 'results' => $results ), 5 );
		if ( count( $done ) === count( $changes ) ) {
			return 'applied';
		}
		return $done ? 'applied_some' : 'apply_failed';
	}

	/** The change Writer::apply expects for one fix approved in MonoRanks. */
	public static function change_from_ready( array $fix ) {
		$c = array( 'id' => $fix['id'], 'field' => $fix['field'], 'value' => (string) $fix['after'], 'expected' => $fix['before'] );
		if ( $fix['post_id'] ) {
			$c['post_id'] = (int) $fix['post_id'];
		}
		if ( 'alt' === $fix['field'] ) {
			$c['attachment_id'] = (int) $fix['attachment_id'];
		}
		if ( 'redirect' === $fix['field'] ) {
			$c['from'] = (string) $fix['from'];
		}
		if ( 'content' === $fix['field'] ) {
			$c['op'] = $fix['op'];
		}
		return $c;
	}

	/**
	 * Puts the previous value of one logged change back. The row is addressed by its position in the stored log and by
	 * the moment it was written, because the log is trimmed to its last 200 entries and positions shift under it.
	 */
	public static function undo( $index, $at = '' ) {
		$log = (array) get_option( 'monoranks_change_log', array() );
		if ( $index < 0 || ! isset( $log[ $index ] ) || ! is_array( $log[ $index ] ) ) {
			return 'apply_failed';
		}
		if ( $at && ( ! isset( $log[ $index ]['at'] ) || $at !== $log[ $index ]['at'] ) ) {
			return 'log_moved';
		}
		$change = Writer::reverse( $log[ $index ] );
		$result = $change ? Writer::apply( array( $change ), 'Undo (' . wp_get_current_user()->user_login . ')' ) : array();
		return $result && ! empty( $result[0]['ok'] ) ? 'undone' : 'apply_failed';
	}
}
