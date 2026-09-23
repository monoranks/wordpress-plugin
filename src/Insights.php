<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * What MonoRanks knows about this site, read with the connector key and cached here: the site overview (scores, traffic,
 * pages needing attention, fixes ready to apply) in one option and each page's scores in post meta, so the admin screens
 * and the Posts list never wait on HTTP. Refreshed in the background once an hour, on "Send content now", and after a sync.
 * A MonoRanks that does not answer these routes yet (404 not_supported) is asked again a day later. docs/api.md has the shapes.
 */
class Insights {

	const OPTION       = 'monoranks_insights';
	const META         = '_monoranks_score';
	const META_HEALTH  = '_monoranks_health';
	const REFRESH_HOOK = 'monoranks_refresh';
	const FRESH_FOR    = HOUR_IN_SECONDS;
	const RETRY_AFTER  = HOUR_IN_SECONDS;
	const PAGE_LIMIT   = 25;
	const LOCK         = 'monoranks_refreshing';
	const VERSION      = 'monoranks_version';

	public static function register() {
		add_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ) );
	}

	/**
	 * Runs once after the plugin is updated. Up to 0.1.7 a pull could mark an audit as stored while most of its pages had
	 * never arrived, so those marks are dropped here and every score is read from MonoRanks again.
	 */
	public static function maybe_upgrade() {
		$was = (string) get_option( self::VERSION, '' );
		if ( MONORANKS_CONNECTOR_VERSION === $was ) {
			return;
		}
		// An install that predates this option has no version stored at all, and that is exactly the case that needs it;
		// on a fresh install there is nothing stored to drop.
		if ( '' === $was || version_compare( $was, '0.1.8', '<' ) ) {
			$state = self::state();
			unset( $state['pages_synced_at'], $state['pages_cursor'], $state['pages_newest'] );
			$state['fetched_at'] = '';
			update_option( self::OPTION, $state, false );
		}
		update_option( self::VERSION, MONORANKS_CONNECTOR_VERSION, false );
	}

	public static function state() {
		$s = get_option( self::OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	/** The normalised overview MonoRanks last sent, or null when there is none (not connected, not supported, not audited). */
	public static function overview() {
		$s = self::state();
		return isset( $s['overview'] ) && is_array( $s['overview'] ) ? $s['overview'] : null;
	}

	/** ok | none | unsupported | error | never */
	public static function status() {
		$s = self::state();
		return isset( $s['status'] ) ? (string) $s['status'] : 'never';
	}

	public static function fetched_at() {
		$s = self::state();
		return isset( $s['fetched_at'] ) ? (string) $s['fetched_at'] : '';
	}

	/** True when the cache is older than an hour (also after MonoRanks said it does not support the routes yet, so a MonoRanks update is picked up the same hour). */
	public static function is_stale( array $state, $now = null ) {
		$now  = null === $now ? time() : (int) $now;
		$at   = isset( $state['fetched_at'] ) ? strtotime( (string) $state['fetched_at'] ) : 0;
		$wait = isset( $state['status'] ) && 'unsupported' === $state['status'] ? self::RETRY_AFTER : self::FRESH_FOR;
		return ! $at || $now - $at >= $wait;
	}

	/** Admin page loads that show MonoRanks data queue a background refresh when the cache is stale. */
	public static function maybe_schedule() {
		global $pagenow;
		if ( ! Connection::has_key() || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen check only
		$ours = 'edit.php' === $pagenow || ( 'admin.php' === $pagenow && in_array( $page, array( Admin::MENU, Admin::SETTINGS ), true ) );
		if ( ! $ours || ! self::is_stale( self::state() ) ) {
			return;
		}
		// Pull the scores in this request, with a short deadline: many sites turn WP-Cron off (DISABLE_WP_CRON) or block
		// the loopback request that starts it, and the screen would sit on "waiting" until their own cron ran, if ever.
		// Only on the plugin's own screens; the Posts list keeps to the background so it never waits on MonoRanks.
		if ( 'admin.php' === $pagenow ) {
			self::refresh( 8, 6 );
		}
		if ( wp_next_scheduled( self::REFRESH_HOOK ) ) {
			return;
		}
		// The rest of the pages, in the background, so a big site finishes without holding up the screen.
		wp_schedule_single_event( time() + 30, self::REFRESH_HOOK );
		spawn_cron();
	}

	/** Pulls what the screens need now and leaves the rest of the pages to WP-Cron. */
	public static function refresh_soon() {
		if ( ! Connection::has_key() ) {
			return;
		}
		// Forget an earlier "this MonoRanks cannot answer yet" so the next pull really happens.
		$state = self::state();
		if ( isset( $state['status'] ) && 'unsupported' === $state['status'] ) {
			$state['fetched_at'] = '';
			update_option( self::OPTION, $state, false );
		}
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::REFRESH_HOOK );
			spawn_cron();
		}
		// A site with WP-Cron switched off would never run that event, so the scores come down here and now as well,
		// on a short deadline: the button has already spent time sending the content.
		if ( ! wp_doing_cron() ) {
			self::refresh( 6, 4 );
		}
	}

	/**
	 * Pulls the overview and the changed pages from MonoRanks. Returns the new state.
	 *
	 * @param int $timeout Seconds to wait for each request.
	 * @param int $budget  Seconds to spend on the pages, 0 for as long as it takes (WP-Cron).
	 */
	public static function refresh( $timeout = 15, $budget = 0 ) {
		if ( ! Connection::has_key() ) {
			return self::state();
		}
		// One pull at a time: two screens opened together would otherwise both wait on the same requests.
		if ( get_transient( self::LOCK ) ) {
			return self::state();
		}
		set_transient( self::LOCK, 1, 30 );
		$state = self::state();
		$res   = Api::get( '/overview', $timeout );
		if ( 404 === $res['code'] ) {
			$state['status']     = 'unsupported';
			$state['fetched_at'] = gmdate( 'c' );
			update_option( self::OPTION, $state, false );
			delete_transient( self::LOCK );
			return $state;
		}
		if ( 200 !== $res['code'] || ! is_array( $res['body'] ) ) {
			$state['status']     = 401 === $res['code'] ? 'revoked' : 'error';
			$state['fetched_at'] = gmdate( 'c' );
			$state['error']      = (string) $res['error'];
			update_option( self::OPTION, $state, false );
			delete_transient( self::LOCK );
			return $state;
		}
		$overview            = self::normalise_overview( $res['body'] );
		$state['status']     = $overview ? 'ok' : 'none';
		$state['overview']   = $overview;
		$state['fetched_at'] = gmdate( 'c' );
		unset( $state['error'] );
		update_option( self::OPTION, $state, false );
		self::refresh_pages( $state, $timeout, $budget );
		delete_transient( self::LOCK );
		// A run that ran out of time carries on in the background in a minute, instead of waiting for the next hour.
		$after = self::state();
		if ( ! empty( $after['pages_cursor'] ) && ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::REFRESH_HOOK );
			spawn_cron();
		}
		return $state;
	}

	/**
	 * Pages changed since the last pull, in batches, written to post meta. A run that stops early (its time is up, or
	 * MonoRanks did not answer) remembers where it got to, so the next one carries on from there instead of fetching the
	 * same first batch for ever; only a run that reached the end moves the "everything up to here is stored" mark.
	 */
	private static function refresh_pages( array $state, $timeout = 15, $budget = 0 ) {
		$since  = isset( $state['pages_synced_at'] ) ? (string) $state['pages_synced_at'] : '';
		$cursor = isset( $state['pages_cursor'] ) ? (string) $state['pages_cursor'] : '';
		$newest = isset( $state['pages_newest'] ) ? (string) $state['pages_newest'] : $since;
		$done   = false;
		$until  = $budget > 0 ? microtime( true ) + (int) $budget : 0;
		for ( $i = 0; $i < self::PAGE_LIMIT; $i++ ) {
			if ( $until && microtime( true ) > $until ) {
				break;
			}
			$path = '/pages' . ( $since || $cursor ? '?' . http_build_query( array_filter( array( 'since' => $since, 'cursor' => $cursor ) ) ) : '' );
			$res  = Api::get( $path, $timeout );
			if ( 200 !== $res['code'] || ! is_array( $res['body'] ) || ! isset( $res['body']['pages'] ) || ! is_array( $res['body']['pages'] ) ) {
				break;
			}
			foreach ( $res['body']['pages'] as $row ) {
				$page = self::normalise_page( is_array( $row ) ? $row : array() );
				if ( $page ) {
					self::set_post_score( $page['post_id'], $page );
					if ( $page['audited_at'] > $newest ) {
						$newest = $page['audited_at'];
					}
				}
			}
			$cursor = isset( $res['body']['next'] ) && is_string( $res['body']['next'] ) ? $res['body']['next'] : '';
			if ( '' === $cursor ) {
				$done = true;
				break;
			}
		}
		$state = self::state();
		if ( $done ) {
			if ( $newest ) {
				$state['pages_synced_at'] = $newest;
			}
			unset( $state['pages_cursor'], $state['pages_newest'] );
		} else {
			$state['pages_cursor'] = $cursor;
			$state['pages_newest'] = $newest;
		}
		update_option( self::OPTION, $state, false );
	}

	/** 0–100 or null. */
	public static function score( $v ) {
		if ( null === $v || '' === $v || ! is_numeric( $v ) ) {
			return null;
		}
		return max( 0, min( 100, (int) round( (float) $v ) ) );
	}

	/** good | warn | critical | none, the app's scoreColor thresholds. */
	public static function tone( $score ) {
		$s = self::score( $score );
		if ( null === $s ) {
			return 'none';
		}
		return $s >= 80 ? 'good' : ( $s >= 60 ? 'warn' : 'critical' );
	}

	/** Shapes the overview MonoRanks sent into what the screens read. Returns null when there is nothing to show. */
	public static function normalise_overview( array $o ) {
		$int = static function ( $v ) {
			return null === $v || ! is_numeric( $v ) ? null : (int) round( (float) $v );
		};
		$str = static function ( $v, $max = 300 ) {
			return is_scalar( $v ) ? mb_substr( sanitize_text_field( (string) $v ), 0, $max ) : '';
		};
		$url = static function ( $v ) {
			$u = is_scalar( $v ) ? esc_url_raw( (string) $v ) : '';
			return 0 === strpos( $u, 'https://' ) || ( Connection::local() && 0 === strpos( $u, 'http://' ) ) ? $u : '';
		};
		$out = array(
			'site_id'       => $str( isset( $o['site_id'] ) ? $o['site_id'] : '' ),
			'site_url'      => $url( isset( $o['site_url'] ) ? $o['site_url'] : '' ),
			'audited_at'    => $str( isset( $o['audited_at'] ) ? $o['audited_at'] : '', 40 ),
			'next_audit_at' => $str( isset( $o['next_audit_at'] ) ? $o['next_audit_at'] : '', 40 ),
			'health'        => self::score( isset( $o['health'] ) ? $o['health'] : null ),
			'aeo'           => self::score( isset( $o['aeo'] ) ? $o['aeo'] : null ),
			'pages_scored'  => (int) $int( isset( $o['pages_scored'] ) ? $o['pages_scored'] : 0 ),
			'pages_total'   => (int) $int( isset( $o['pages_total'] ) ? $o['pages_total'] : 0 ),
			'deltas'        => array(
				'health' => $int( isset( $o['deltas']['health'] ) ? $o['deltas']['health'] : null ),
				'aeo'    => $int( isset( $o['deltas']['aeo'] ) ? $o['deltas']['aeo'] : null ),
			),
			'traffic'       => null,
			'fixes'         => array(
				'ready'       => (int) $int( isset( $o['fixes']['ready'] ) ? $o['fixes']['ready'] : 0 ),
				'applied_30d' => (int) $int( isset( $o['fixes']['applied_30d'] ) ? $o['fixes']['applied_30d'] : 0 ),
			),
			'ai'            => array(
				'bots_rules' => ! empty( $o['ai']['bots_rules'] ),
				'llms_txt'   => ! empty( $o['ai']['llms_txt'] ),
			),
			'attention'     => array(),
			'ready'         => array(),
		);
		if ( isset( $o['traffic'] ) && is_array( $o['traffic'] ) && isset( $o['traffic']['clicks_28d'] ) ) {
			$series = isset( $o['traffic']['series'] ) && is_array( $o['traffic']['series'] ) ? array_map( 'intval', array_slice( $o['traffic']['series'], -28 ) ) : array();
			$out['traffic'] = array(
				'clicks_28d' => (int) $int( $o['traffic']['clicks_28d'] ),
				'delta_pct'  => $int( isset( $o['traffic']['delta_pct'] ) ? $o['traffic']['delta_pct'] : null ),
				'series'     => array_values( array_map( static function ( $n ) { return max( 0, $n ); }, $series ) ),
			);
		}
		if ( isset( $o['attention'] ) && is_array( $o['attention'] ) ) {
			foreach ( array_slice( $o['attention'], 0, 10 ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$out['attention'][] = array(
					'post_id'  => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
					'url'      => $url( isset( $row['url'] ) ? $row['url'] : '' ),
					'title'    => $str( isset( $row['title'] ) ? $row['title'] : '' ),
					'health'   => self::score( isset( $row['health'] ) ? $row['health'] : null ),
					'aeo'      => self::score( isset( $row['aeo'] ) ? $row['aeo'] : null ),
					'issue'    => $str( isset( $row['issue'] ) ? $row['issue'] : '' ),
					'page_url' => $url( isset( $row['page_url'] ) ? $row['page_url'] : '' ),
				);
			}
		}
		if ( isset( $o['ready'] ) && is_array( $o['ready'] ) ) {
			foreach ( array_slice( $o['ready'], 0, 50 ) as $row ) {
				if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['field'] ) || ! in_array( (string) $row['field'], Writer::FIELDS, true ) ) {
					continue;
				}
				$out['ready'][] = array(
					'id'            => $str( $row['id'], 80 ),
					'field'         => (string) $row['field'],
					'post_id'       => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
					'attachment_id' => isset( $row['attachment_id'] ) ? (int) $row['attachment_id'] : 0,
					'from'          => $str( isset( $row['from'] ) ? $row['from'] : '', 2000 ),
					'op'            => isset( $row['op'] ) && 'remove' === $row['op'] ? 'remove' : 'insert_top',
					'title'         => $str( isset( $row['title'] ) ? $row['title'] : '' ),
					'before'        => isset( $row['before'] ) && null !== $row['before'] ? mb_substr( (string) $row['before'], 0, 60000 ) : null,
					'after'         => isset( $row['after'] ) ? mb_substr( (string) $row['after'], 0, 60000 ) : '',
					'page_url'      => $url( isset( $row['page_url'] ) ? $row['page_url'] : '' ),
				);
			}
		}
		$has_anything = null !== $out['health'] || null !== $out['aeo'] || $out['pages_scored'] > 0 || $out['attention'] || $out['ready'] || $out['traffic'];
		return $has_anything ? $out : null;
	}

	/** One page row from /pages. Returns null when it cannot be matched to a post. */
	public static function normalise_page( array $p ) {
		$post_id = isset( $p['post_id'] ) ? (int) $p['post_id'] : 0;
		if ( ! $post_id && ! empty( $p['url'] ) ) {
			$post_id = (int) url_to_postid( (string) $p['url'] );
		}
		if ( $post_id <= 0 ) {
			return null;
		}
		$page_url = isset( $p['page_url'] ) && is_scalar( $p['page_url'] ) ? esc_url_raw( (string) $p['page_url'] ) : '';
		return array(
			'post_id'     => $post_id,
			'health'      => self::score( isset( $p['health'] ) ? $p['health'] : null ),
			'aeo'         => self::score( isset( $p['aeo'] ) ? $p['aeo'] : null ),
			'open_issues' => isset( $p['open_issues'] ) ? max( 0, (int) $p['open_issues'] ) : 0,
			'fixes_ready' => isset( $p['fixes_ready'] ) ? max( 0, (int) $p['fixes_ready'] ) : 0,
			'audited_at'  => isset( $p['audited_at'] ) && is_scalar( $p['audited_at'] ) ? mb_substr( sanitize_text_field( (string) $p['audited_at'] ), 0, 40 ) : '',
			'page_url'    => 0 === strpos( $page_url, 'https://' ) || ( Connection::local() && 0 === strpos( $page_url, 'http://' ) ) ? $page_url : '',
		);
	}

	public static function set_post_score( $post_id, array $page ) {
		update_post_meta( (int) $post_id, self::META, $page );
		if ( null === $page['health'] ) {
			delete_post_meta( (int) $post_id, self::META_HEALTH );
		} else {
			update_post_meta( (int) $post_id, self::META_HEALTH, (int) $page['health'] );
		}
	}

	/** The page's cached scores, or null when MonoRanks has not scored it. */
	public static function post_score( $post_id ) {
		$m = get_post_meta( (int) $post_id, self::META, true );
		return is_array( $m ) && isset( $m['post_id'] ) ? $m : null;
	}

	/** Forgets everything pulled from MonoRanks (disconnect, uninstall). */
	public static function clear() {
		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
		delete_post_meta_by_key( self::META );
		delete_post_meta_by_key( self::META_HEALTH );
	}

	/** Drops one applied fix from the cached overview so the screen does not offer it twice. */
	public static function forget_ready( array $ids ) {
		$state = self::state();
		if ( empty( $state['overview']['ready'] ) ) {
			return;
		}
		$state['overview']['ready']          = array_values( array_filter( $state['overview']['ready'], static function ( $row ) use ( $ids ) { return ! in_array( $row['id'], $ids, true ); } ) );
		$state['overview']['fixes']['ready'] = max( 0, (int) $state['overview']['fixes']['ready'] - count( $ids ) );
		update_option( self::OPTION, $state, false );
	}
}
