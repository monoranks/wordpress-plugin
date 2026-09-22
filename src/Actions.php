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
		Insights::refresh();
		return 'connected';
	}

	public static function send_now() {
		Api::send_status();
		Sync::start();
		Insights::refresh();
		return 'sent';
	}

	public static function disconnect() {
		Sync::unschedule();
		Connection::clear();
		Insights::clear();
		return 'disconnected';
	}

	/** Apply one fix approved in MonoRanks (its id) or all of them ('all'), then tell MonoRanks what happened. */
	public static function apply( $which ) {
		$overview = Insights::overview();
		$changes  = array();
		foreach ( $overview ? $overview['ready'] : array() as $fix ) {
			if ( 'all' === $which || $fix['id'] === $which ) {
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
		return count( $done ) === count( $changes ) ? 'applied' : 'apply_failed';
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

	/** Puts the previous value of one logged change back ($index in the stored log). */
	public static function undo( $index ) {
		$log = (array) get_option( 'monoranks_change_log', array() );
		if ( $index < 0 || ! isset( $log[ $index ] ) || ! is_array( $log[ $index ] ) ) {
			return 'apply_failed';
		}
		$change = Writer::reverse( $log[ $index ] );
		$result = $change ? Writer::apply( array( $change ), 'Undo (' . wp_get_current_user()->user_login . ')' ) : array();
		return $result && ! empty( $result[0]['ok'] ) ? 'undone' : 'apply_failed';
	}
}
