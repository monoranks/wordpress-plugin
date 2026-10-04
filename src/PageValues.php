<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * What each page is worth, for the post panel and the MonoRanks column: search clicks, and when Google Analytics is
 * connected in MonoRanks, sessions, key events and revenue over the last 28 days of final data; plus the lost links
 * MonoRanks found that pointed at the page (outreach targets of the lost_link source). One pull a day of at most three
 * calls (analytics/pages, else search/rows; outreach), cached in one transient keyed by page path. Revenue is only ever
 * shown to administrators.
 */
class PageValues {

	const CACHE        = 'monoranks_page_values';
	const LOCK         = 'monoranks_page_values_lock';
	const REFRESH_HOOK = 'monoranks_page_values_refresh';
	const FRESH_FOR    = DAY_IN_SECONDS;
	const RETRY_AFTER  = 2 * HOUR_IN_SECONDS;
	const KEEP_FOR     = 3 * DAY_IN_SECONDS;
	const ROWS         = 1000;

	public static function register() {
		add_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ) );
	}

	public static function cached() {
		$c = get_transient( self::CACHE );
		return is_array( $c ) ? $c : array();
	}

	public static function is_stale( array $cache, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$at  = isset( $cache['fetched_at'] ) ? strtotime( (string) $cache['fetched_at'] ) : 0;
		if ( ! $at ) {
			return true;
		}
		$failed = ( isset( $cache['search'] ) && 'error' === $cache['search'] ) || ( isset( $cache['analytics'] ) && 'error' === $cache['analytics'] );
		return $now - $at >= ( $failed ? self::RETRY_AFTER : self::FRESH_FOR );
	}

	/** The Posts list and the post editor queue a background pull when the values are a day old. Never inline there. */
	public static function maybe_schedule() {
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'edit.php', 'post.php' ), true ) || ! Connection::has_key() || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		if ( ! self::is_stale( self::cached() ) || wp_next_scheduled( self::REFRESH_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + 5, self::REFRESH_HOOK );
		spawn_cron();
	}

	/**
	 * The 28 days the values cover: the last 28 days of final Search Console data, ending three days ago, the range
	 * MonoRanks itself uses by default.
	 *
	 * @return array{from:string, to:string}
	 */
	public static function period( $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$to  = $now - 3 * DAY_IN_SECONDS;
		return array( 'from' => gmdate( 'Y-m-d', $to - 27 * DAY_IN_SECONDS ), 'to' => gmdate( 'Y-m-d', $to ) );
	}

	/** Pulls the values and the lost links. Returns the new cache. */
	public static function refresh( $timeout = 15 ) {
		if ( ! Connection::has_key() || get_transient( self::LOCK ) ) {
			return self::cached();
		}
		set_transient( self::LOCK, 1, 60 );
		$cache = array( 'fetched_at' => gmdate( 'c' ), 'pages' => array(), 'lost' => array(), 'currency' => '', 'analytics' => 'off', 'search' => 'off', 'period' => self::period() );
		$res   = Api::v1_get( 'analytics/pages?limit=' . self::ROWS . '&sort=-revenue', $timeout );
		if ( 200 === $res['code'] && is_array( $res['body'] ) ) {
			$cache = array_merge( $cache, self::normalise_analytics( $res['body'] ) );
		} else {
			// 409: Google Analytics is not connected (or needs reconnecting) for this website in MonoRanks.
			$cache['analytics'] = 409 === $res['code'] ? 'not_connected' : Api::v1_state( $res );
			$p                  = self::period();
			$rows               = Api::v1_get( 'search/rows?dimension=page&limit=' . self::ROWS . '&from=' . $p['from'] . '&to=' . $p['to'], $timeout );
			if ( 200 === $rows['code'] && is_array( $rows['body'] ) ) {
				$cache['pages']  = self::normalise_search( $rows['body'] );
				$cache['search'] = 'ok';
			} else {
				$cache['search'] = Api::v1_state( $rows );
			}
		}
		$lost = Api::v1_get( 'outreach?source=lost_link&per=200', $timeout );
		if ( 200 === $lost['code'] && is_array( $lost['body'] ) ) {
			$cache['lost'] = self::normalise_lost( $lost['body'] );
		}
		set_transient( self::CACHE, $cache, self::KEEP_FOR );
		delete_transient( self::LOCK );
		return $cache;
	}

	public static function normalise_analytics( array $b ) {
		$pages = array();
		foreach ( Grow::rows( $b, 'rows', self::ROWS ) as $r ) {
			$key = self::key( isset( $r['url'] ) && $r['url'] ? $r['url'] : ( isset( $r['page'] ) ? $r['page'] : '' ) );
			if ( '' === $key ) {
				continue;
			}
			$pages[ $key ] = array(
				'sessions'    => (int) Grow::int( isset( $r['sessions'] ) ? $r['sessions'] : 0 ),
				'key_events'  => (int) Grow::int( isset( $r['keyEvents'] ) ? $r['keyEvents'] : 0 ),
				'revenue'     => isset( $r['revenue'] ) && is_numeric( $r['revenue'] ) ? round( (float) $r['revenue'], 2 ) : 0.0,
				'clicks'      => Grow::int( isset( $r['clicks'] ) ? $r['clicks'] : null ),
				'impressions' => Grow::int( isset( $r['impressions'] ) ? $r['impressions'] : null ),
				'position'    => isset( $r['position'] ) && is_numeric( $r['position'] ) ? round( (float) $r['position'], 1 ) : null,
			);
		}
		$joined = isset( $b['searchConsole'] ) && 'joined' === $b['searchConsole'];
		return array( 'pages' => $pages, 'currency' => Grow::str( isset( $b['currency'] ) ? $b['currency'] : '', 3 ), 'analytics' => 'ok', 'search' => $joined ? 'ok' : 'off' );
	}

	public static function normalise_search( array $b ) {
		$pages = array();
		foreach ( Grow::rows( $b, 'rows', self::ROWS ) as $r ) {
			$key = self::key( isset( $r['page'] ) ? $r['page'] : '' );
			if ( '' === $key || isset( $pages[ $key ] ) ) {
				continue;
			}
			$pages[ $key ] = array(
				'clicks'      => (int) Grow::int( isset( $r['clicks'] ) ? $r['clicks'] : 0 ),
				'impressions' => (int) Grow::int( isset( $r['impressions'] ) ? $r['impressions'] : 0 ),
				'position'    => isset( $r['position'] ) && is_numeric( $r['position'] ) ? round( (float) $r['position'], 1 ) : null,
			);
		}
		return $pages;
	}

	/** Lost links still worth asking back (not added, not declined), grouped by the page of ours they pointed at. */
	public static function normalise_lost( array $b ) {
		$by = array();
		foreach ( Grow::rows( $b, 'targets', 200 ) as $t ) {
			$status = isset( $t['status'] ) ? (string) $t['status'] : '';
			$to     = isset( $t['reason']['lostTo'] ) ? self::key( $t['reason']['lostTo'] ) : '';
			if ( '' === $to || in_array( $status, array( 'added', 'declined' ), true ) ) {
				continue;
			}
			if ( ! isset( $by[ $to ] ) ) {
				$by[ $to ] = array( 'count' => 0, 'top' => array() );
			}
			++$by[ $to ]['count'];
			if ( count( $by[ $to ]['top'] ) < 3 ) {
				$by[ $to ]['top'][] = array( 'domain' => Grow::str( isset( $t['domain'] ) ? $t['domain'] : '', 120 ), 'url' => Grow::url( isset( $t['url'] ) ? $t['url'] : '', true ), 'link' => Grow::url( isset( $t['link'] ) ? $t['link'] : '' ) );
			}
		}
		return $by;
	}

	/**
	 * The key a page is matched on: its path, lower case, without query, fragment or trailing slash ("/" for the home
	 * page), so https://www.example.com/Pricing/?ref=x and /pricing match. '' when there is no path to match, as with
	 * plain permalinks (https://example.com/?p=12).
	 */
	public static function key( $url ) {
		$url = is_scalar( $url ) ? trim( (string) $url ) : '';
		if ( '' === $url || '(not set)' === $url ) {
			return '';
		}
		$full = 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ? 'https://x' . $url : $url;
		$path = rtrim( strtolower( rawurldecode( (string) wp_parse_url( $full, PHP_URL_PATH ) ) ), '/' );
		// Plain permalinks (?p=12) have no path of their own: matching them on "/" would give every post the home page's numbers.
		if ( '' === $path && '' !== (string) wp_parse_url( $full, PHP_URL_QUERY ) ) {
			return '';
		}
		return '' === $path ? '/' : $path;
	}

	/**
	 * The values of one post: null when nothing is cached. `analytics` and `search` say what the numbers come from
	 * (ok, not_connected, no_access, off, error), so the panel can tell "0 clicks" from "no data".
	 */
	public static function for_url( $url, $cache = null ) {
		$cache = null === $cache ? self::cached() : $cache;
		if ( empty( $cache['fetched_at'] ) ) {
			return null;
		}
		$key = self::key( $url );
		$row = $key && isset( $cache['pages'][ $key ] ) ? $cache['pages'][ $key ] : null;
		return array(
			'analytics' => isset( $cache['analytics'] ) ? $cache['analytics'] : 'off',
			'search'    => isset( $cache['search'] ) ? $cache['search'] : 'off',
			'currency'  => isset( $cache['currency'] ) ? $cache['currency'] : '',
			'row'       => $row,
			'lost'      => $key && isset( $cache['lost'][ $key ] ) ? $cache['lost'][ $key ] : null,
		);
	}

	/** "€1,240" in the admin's locale, or the plain number with the currency code when intl is missing. */
	public static function money( $amount, $currency ) {
		if ( class_exists( '\NumberFormatter' ) && $currency ) {
			$f   = new \NumberFormatter( determine_locale(), \NumberFormatter::CURRENCY );
			$f->setAttribute( \NumberFormatter::MAX_FRACTION_DIGITS, $amount >= 100 ? 0 : 2 );
			$out = $f->formatCurrency( (float) $amount, $currency );
			if ( false !== $out ) {
				return Admin::digits( $out );
			}
		}
		return Admin::digits( trim( number_format_i18n( (float) $amount, $amount >= 100 ? 0 : 2 ) . ' ' . $currency ) );
	}

	public static function clear() {
		delete_transient( self::CACHE );
		delete_transient( self::LOCK );
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
	}
}
