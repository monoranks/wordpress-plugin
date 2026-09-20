<?php
defined( 'ABSPATH' ) || exit;

/**
 * Full content sync pushed to MonoRanks in batches of 50 items. It runs inline for up to 20 seconds when MonoRanks asks for it
 * or when a key is pasted, then continues through WP-Cron; it also runs once a day.
 */
class MonoRanks_Sync {

	const STEP_HOOK  = 'monoranks_sync_step';
	const DAILY_HOOK = 'monoranks_daily_sync';
	const PER_PAGE   = 50;

	public static function start() {
		if ( ! MonoRanks_Connection::has_key() ) {
			return array( 'run_id' => '', 'sent_pages' => 0, 'pages' => 0, 'done' => false, 'error' => 'not_connected' );
		}
		update_option( 'monoranks_sync', array( 'run_id' => str_replace( '-', '', wp_generate_uuid4() ), 'page' => 1, 'pages' => null, 'attempt' => 0 ), false );
		self::schedule_daily();
		return self::run( 20 );
	}

	public static function run( $budget = 25 ) {
		$state = get_option( 'monoranks_sync', array() );
		if ( empty( $state['run_id'] ) ) {
			return array( 'run_id' => '', 'sent_pages' => 0, 'pages' => 0, 'done' => true );
		}
		$until = microtime( true ) + (int) $budget;
		$sent  = 0;
		do {
			$page  = MonoRanks_Content::page( (int) $state['page'], self::PER_PAGE );
			$pages = max( 1, (int) $page['pages'] );
			$final = (int) $state['page'] >= $pages;
			$res   = MonoRanks_Api::post( '/content', array( 'run_id' => $state['run_id'], 'page' => (int) $state['page'], 'pages' => $pages, 'total' => (int) $page['total'], 'items' => $page['items'], 'final' => $final ), 30 );
			if ( 401 === $res['code'] ) {
				delete_option( 'monoranks_sync' );
				return array( 'run_id' => $state['run_id'], 'sent_pages' => $sent, 'pages' => $pages, 'done' => false, 'error' => 'key_revoked' );
			}
			if ( $res['error'] ) {
				$state['attempt'] = (int) $state['attempt'] + 1;
				if ( $state['attempt'] > 5 ) {
					delete_option( 'monoranks_sync' );
					return array( 'run_id' => $state['run_id'], 'sent_pages' => $sent, 'pages' => $pages, 'done' => false, 'error' => $res['error'] );
				}
				update_option( 'monoranks_sync', $state, false );
				wp_schedule_single_event( time() + 300 * $state['attempt'], self::STEP_HOOK );
				return array( 'run_id' => $state['run_id'], 'sent_pages' => $sent, 'pages' => $pages, 'done' => false, 'error' => $res['error'] );
			}
			$sent++;
			if ( $final ) {
				delete_option( 'monoranks_sync' );
				return array( 'run_id' => $state['run_id'], 'sent_pages' => $sent, 'pages' => $pages, 'done' => true );
			}
			$state['page']    = (int) $state['page'] + 1;
			$state['pages']   = $pages;
			$state['attempt'] = 0;
			update_option( 'monoranks_sync', $state, false );
		} while ( microtime( true ) < $until );
		wp_schedule_single_event( time() + 10, self::STEP_HOOK );
		return array( 'run_id' => $state['run_id'], 'sent_pages' => $sent, 'pages' => $pages, 'done' => false );
	}

	public static function daily() {
		if ( MonoRanks_Connection::has_key() ) {
			MonoRanks_Api::send_status();
			self::start();
		}
	}

	public static function schedule_daily() {
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_clear_scheduled_hook( self::STEP_HOOK );
	}
}
