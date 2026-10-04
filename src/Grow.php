<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * "What to do next" for the Overview: backlinks won and lost, outreach targets to contact, competitors and the keywords
 * they win, keyword movers this week, and the newest report. Read from MonoRanks API v1 with the connector key (scopes
 * search:read and sites:read), cached in one transient for six hours, so the screen never waits on more than one pull
 * and MonoRanks sees at most a few calls a day. Each section carries its own state, so a key without search:read, a
 * MonoRanks with the API off, or a site without data shows a plain line instead of the whole card failing.
 * docs/api.md lists the routes and the shapes the plugin keeps.
 */
class Grow {

	const CACHE        = 'monoranks_grow';
	const LOCK         = 'monoranks_grow_lock';
	const REFRESH_HOOK = 'monoranks_grow_refresh';
	const FRESH_FOR    = 6 * HOUR_IN_SECONDS;
	const RETRY_AFTER  = HOUR_IN_SECONDS;
	const KEEP_FOR     = 3 * DAY_IN_SECONDS;
	const SECTIONS     = array( 'outreach', 'backlinks', 'competitors', 'keywords', 'report' );

	public static function register() {
		add_action( self::REFRESH_HOOK, array( __CLASS__, 'refresh' ) );
	}

	/** The cached sections, or an empty array. */
	public static function cached() {
		$c = get_transient( self::CACHE );
		return is_array( $c ) ? $c : array();
	}

	/** True when the cache is missing, older than six hours, or has a section that failed more than an hour ago. */
	public static function is_stale( array $cache, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$at  = isset( $cache['fetched_at'] ) ? strtotime( (string) $cache['fetched_at'] ) : 0;
		if ( ! $at ) {
			return true;
		}
		$failed = false;
		foreach ( self::SECTIONS as $s ) {
			if ( ! isset( $cache[ $s ]['state'] ) || in_array( $cache[ $s ]['state'], array( 'error', 'pending' ), true ) ) {
				$failed = true;
			}
		}
		return $now - $at >= ( $failed ? self::RETRY_AFTER : self::FRESH_FOR );
	}

	/**
	 * What the Overview's Grow cards read (REST /admin/grow). Pulls first when the cache is stale, within a short budget;
	 * sections it had no time for are finished by WP-Cron.
	 */
	public static function data() {
		if ( ! Connection::has_key() ) {
			return array( 'connected' => false );
		}
		$cache = self::cached();
		if ( self::is_stale( $cache ) ) {
			$cache = self::refresh( 5, 12 );
		}
		$out = array( 'connected' => true );
		foreach ( self::SECTIONS as $s ) {
			$out[ $s ] = isset( $cache[ $s ] ) && is_array( $cache[ $s ] ) ? $cache[ $s ] : array( 'state' => 'pending' );
		}
		if ( 'ok' === $out['report']['state'] ) {
			$out['report']['when'] = Admin::ago( $out['report']['created_at'] );
		}
		$out['fetched']   = Admin::ago( isset( $cache['fetched_at'] ) ? $cache['fetched_at'] : '' );
		$out['scope_url'] = self::app_link( '/integrations' );
		return $out;
	}

	/**
	 * Pulls every section from MonoRanks and stores them. One pull at a time.
	 *
	 * @param int $timeout Seconds per request.
	 * @param int $budget  Seconds for the whole pull, 0 for no limit (WP-Cron).
	 * @return array The cache after the pull.
	 */
	public static function refresh( $timeout = 10, $budget = 0 ) {
		if ( ! Connection::has_key() ) {
			return array();
		}
		$cache = self::cached();
		if ( get_transient( self::LOCK ) ) {
			return $cache;
		}
		set_transient( self::LOCK, 1, 60 );
		$until = $budget > 0 ? microtime( true ) + (int) $budget : 0;
		$left  = false;
		foreach ( self::SECTIONS as $s ) {
			if ( $until && microtime( true ) > $until ) {
				$left = true;
				if ( ! isset( $cache[ $s ] ) ) {
					$cache[ $s ] = array( 'state' => 'pending' );
				}
				continue;
			}
			$cache[ $s ] = call_user_func( array( __CLASS__, 'pull_' . $s ), $timeout );
		}
		$cache['fetched_at'] = gmdate( 'c' );
		set_transient( self::CACHE, $cache, self::KEEP_FOR );
		delete_transient( self::LOCK );
		if ( $left && ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::REFRESH_HOOK );
			spawn_cron();
		}
		return $cache;
	}

	/** Forgets the cache. */
	public static function clear() {
		delete_transient( self::CACHE );
		delete_transient( self::LOCK );
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
	}

	/**
	 * Forgets every read of API v1 (the Grow cards, the page values, each post's reads): on disconnect and delete, and
	 * when a new key arrives, which may read more or less than the old one.
	 */
	public static function clear_all() {
		self::clear();
		PageValues::clear();
		PostPanel::clear();
	}

	// ---- the sections ----

	/** Backlinks: referring domains won and lost in 28 days, how many to review, the newest strong ones. */
	public static function pull_backlinks( $timeout = 10 ) {
		$res = Api::v1_get( 'backlinks?view=new&per=3&sort=strength', $timeout );
		if ( 200 !== $res['code'] || ! is_array( $res['body'] ) ) {
			return array( 'state' => Api::v1_state( $res ) );
		}
		return self::normalise_backlinks( $res['body'] );
	}

	public static function normalise_backlinks( array $b ) {
		$sum = isset( $b['summary'] ) && is_array( $b['summary'] ) ? $b['summary'] : null;
		if ( ! $sum ) {
			return array( 'state' => 'empty', 'link' => self::url( isset( $b['link'] ) ? $b['link'] : '' ) );
		}
		$top = array();
		foreach ( self::rows( $b, 'domains', 3 ) as $d ) {
			$name = self::str( isset( $d['domain'] ) ? $d['domain'] : '', 120 );
			if ( '' !== $name ) {
				$top[] = array( 'domain' => $name, 'rank' => self::int( isset( $d['domainRank'] ) ? $d['domainRank'] : null ), 'link' => self::url( isset( $d['link'] ) ? $d['link'] : '' ) );
			}
		}
		return array(
			'state'   => 'ok',
			'domains' => (int) self::int( isset( $sum['referringDomains'] ) ? $sum['referringDomains'] : 0 ),
			'new'     => (int) self::int( isset( $sum['new28Days'] ) ? $sum['new28Days'] : 0 ),
			'lost'    => (int) self::int( isset( $sum['lost28Days'] ) ? $sum['lost28Days'] : 0 ),
			'review'  => (int) self::int( isset( $b['counts']['review'] ) ? $b['counts']['review'] : 0 ),
			'rank'    => self::int( isset( $sum['domainRank'] ) ? $sum['domainRank'] : null ),
			'top'     => $top,
			'link'    => self::url( isset( $b['link'] ) ? $b['link'] : '' ),
		);
	}

	/** Outreach: how many targets wait to be contacted, and the three best with why. */
	public static function pull_outreach( $timeout = 10 ) {
		$res = Api::v1_get( 'outreach?status=to_contact&per=3', $timeout );
		if ( 200 !== $res['code'] || ! is_array( $res['body'] ) ) {
			return array( 'state' => Api::v1_state( $res ) );
		}
		return self::normalise_outreach( $res['body'] );
	}

	public static function normalise_outreach( array $b ) {
		$total = (int) self::int( isset( $b['counts']['status']['to_contact'] ) ? $b['counts']['status']['to_contact'] : ( isset( $b['total'] ) ? $b['total'] : 0 ) );
		$top   = array();
		foreach ( self::rows( $b, 'targets', 3 ) as $t ) {
			$url = self::url( isset( $t['url'] ) ? $t['url'] : '', true );
			if ( '' === $url ) {
				continue;
			}
			$top[] = array(
				'title'  => self::str( isset( $t['title'] ) && $t['title'] ? $t['title'] : ( isset( $t['domain'] ) ? $t['domain'] : $url ), 200 ),
				'domain' => self::str( isset( $t['domain'] ) ? $t['domain'] : '', 120 ),
				'url'    => $url,
				'source' => self::str( isset( $t['source'] ) ? $t['source'] : '', 20 ),
				'why'    => self::why( isset( $t['source'] ) ? (string) $t['source'] : '', isset( $t['reason'] ) && is_array( $t['reason'] ) ? $t['reason'] : array() ),
				'score'  => Insights::score( isset( $t['score'] ) ? $t['score'] : null ),
				'link'   => self::url( isset( $t['link'] ) ? $t['link'] : '' ),
			);
		}
		$all = (int) self::int( isset( $b['counts']['all'] ) ? $b['counts']['all'] : 0 );
		return array(
			'state'      => $total || $top ? 'ok' : 'empty',
			'to_contact' => $total,
			'added'      => (int) self::int( isset( $b['counts']['status']['added'] ) ? $b['counts']['status']['added'] : 0 ),
			'all'        => $all,
			'top'        => $top,
			'link'       => self::url( isset( $b['link'] ) ? $b['link'] : '' ),
		);
	}

	/**
	 * One plain line on why a target is worth contacting, from what its source found. Never names an AI model or provider.
	 */
	public static function why( $source, array $r ) {
		switch ( $source ) {
			case 'ai_source':
				$q = isset( $r['questions'] ) && is_array( $r['questions'] ) && $r['questions'] ? self::str( reset( $r['questions'] ), 120 ) : '';
				/* translators: %s: a question people ask */
				return $q ? sprintf( __( 'AI answers quote this page for "%s"', 'monoranks' ), $q ) : __( 'AI answers quote this page', 'monoranks' );
			case 'best_list':
				$k = isset( $r['keyword'] ) ? self::str( $r['keyword'], 120 ) : '';
				/* translators: %s: a search keyword */
				return $k ? sprintf( __( 'A "best of" list for "%s" that leaves you out', 'monoranks' ), $k ) : __( 'A "best of" list that leaves you out', 'monoranks' );
			case 'link_gap':
				$n = isset( $r['competitors'] ) && is_array( $r['competitors'] ) ? count( $r['competitors'] ) : 0;
				/* translators: %s: number of competitors */
				return $n ? sprintf( _n( 'Links to %s competitor, not to you', 'Links to %s of your competitors, not to you', $n, 'monoranks' ), Admin::n( $n ) ) : __( 'Links to your competitors, not to you', 'monoranks' );
			case 'lost_link':
				$to = isset( $r['lostTo'] ) ? self::path( (string) $r['lostTo'] ) : '';
				/* translators: %s: a page address on this site, like /pricing/ */
				return $to ? sprintf( __( 'Used to link to %s', 'monoranks' ), $to ) : __( 'Used to link to you', 'monoranks' );
			case 'manual':
				return __( 'Added by hand', 'monoranks' );
		}
		return '';
	}

	/** Competitors: the three that matter most (picked first), and how many keywords they rank for and this site does not. */
	public static function pull_competitors( $timeout = 10 ) {
		$res = Api::v1_get( 'competitors', $timeout );
		if ( 200 !== $res['code'] || ! is_array( $res['body'] ) ) {
			return array( 'state' => Api::v1_state( $res ) );
		}
		$gaps = Api::v1_get( 'competitors/gaps?view=gaps&per=3', $timeout );
		return self::normalise_competitors( $res['body'], 200 === $gaps['code'] && is_array( $gaps['body'] ) ? $gaps['body'] : null );
	}

	public static function normalise_competitors( array $b, $gaps = null ) {
		$driving = isset( $b['driving'] ) && is_array( $b['driving'] ) ? array_map( 'strval', $b['driving'] ) : array();
		$rows    = self::rows( $b, 'competitors', 50 );
		// The ones that drive the gaps first (picked, or the strongest confirmed suggestions), in the order MonoRanks sent.
		usort(
			$rows,
			static function ( $x, $y ) use ( $driving ) {
				$a = in_array( isset( $x['domain'] ) ? (string) $x['domain'] : '', $driving, true ) ? 0 : 1;
				$c = in_array( isset( $y['domain'] ) ? (string) $y['domain'] : '', $driving, true ) ? 0 : 1;
				return $a - $c;
			}
		);
		$top = array();
		foreach ( array_slice( $rows, 0, 3 ) as $c ) {
			$top[] = array(
				'domain'   => self::str( isset( $c['domain'] ) ? $c['domain'] : '', 120 ),
				'picked'   => isset( $c['kind'] ) && 'picked' === $c['kind'],
				'shared'   => (int) self::int( isset( $c['sharedKeywords'] ) ? $c['sharedKeywords'] : 0 ),
				'above_us' => (int) self::int( isset( $c['aboveUs'] ) ? $c['aboveUs'] : 0 ),
				'reason'   => self::str( isset( $c['review']['reason'] ) ? $c['review']['reason'] : '', 200 ),
			);
		}
		$out = array(
			'state'    => $top ? 'ok' : 'empty',
			'top'      => $top,
			'gaps'     => null,
			'keywords' => array(),
			'link'     => self::url( isset( $b['link'] ) ? $b['link'] : '' ),
		);
		if ( is_array( $gaps ) ) {
			$out['gaps'] = (int) self::int( isset( $gaps['counts']['gaps'] ) ? $gaps['counts']['gaps'] : ( isset( $gaps['total'] ) ? $gaps['total'] : 0 ) );
			foreach ( self::rows( $gaps, 'rows', 3 ) as $g ) {
				$k = self::str( isset( $g['keyword'] ) ? $g['keyword'] : '', 120 );
				if ( '' !== $k ) {
					$out['keywords'][] = array( 'keyword' => $k, 'volume' => self::int( isset( $g['volume'] ) ? $g['volume'] : null ) );
				}
			}
		}
		return $out;
	}

	/** Keyword movers: tracked keywords that moved most since a week ago, up and down. */
	public static function pull_keywords( $timeout = 10 ) {
		$up = Api::v1_get( 'keywords?view=tracked&sort=change&dir=desc&per=10', $timeout );
		if ( 200 !== $up['code'] || ! is_array( $up['body'] ) ) {
			return array( 'state' => Api::v1_state( $up ) );
		}
		$down = Api::v1_get( 'keywords?view=tracked&sort=change&dir=asc&per=10', $timeout );
		return self::normalise_keywords( $up['body'], 200 === $down['code'] && is_array( $down['body'] ) ? $down['body'] : array() );
	}

	public static function normalise_keywords( array $up, array $down ) {
		$moves = static function ( array $b, $sign ) {
			$out = array();
			foreach ( Grow::rows( $b, 'keywords', 10 ) as $k ) {
				$now  = Grow::int( isset( $k['latestPosition'] ) ? $k['latestPosition'] : null );
				$then = Grow::int( isset( $k['weekAgoPosition'] ) ? $k['weekAgoPosition'] : null );
				// 0 means "not in the top 100": a move into or out of it is not a position change to show as a number.
				if ( ! $now || ! $then || ( $then - $now ) * $sign <= 0 ) {
					continue;
				}
				$out[] = array( 'keyword' => Grow::str( isset( $k['keyword'] ) ? $k['keyword'] : '', 120 ), 'from' => $then, 'to' => $now, 'url' => Grow::url( isset( $k['latestUrl'] ) ? $k['latestUrl'] : '', true ) );
				if ( count( $out ) >= 3 ) {
					break;
				}
			}
			return $out;
		};
		$tracked = (int) self::int( isset( $up['total'] ) ? $up['total'] : 0 );
		$u       = $moves( $up, 1 );
		$d       = $moves( $down, -1 );
		return array(
			'state'   => $tracked ? 'ok' : 'empty',
			'tracked' => $tracked,
			'up'      => $u,
			'down'    => $d,
			'link'    => self::url( isset( $up['link'] ) ? $up['link'] : '' ),
		);
	}

	/** The newest report about this website (a site summary or its client's monthly report). */
	public static function pull_report( $timeout = 10 ) {
		$res = Api::v1_get( 'reports?limit=1', $timeout );
		if ( 200 !== $res['code'] || ! is_array( $res['body'] ) ) {
			return array( 'state' => Api::v1_state( $res ) );
		}
		return self::normalise_report( $res['body'] );
	}

	public static function normalise_report( array $b ) {
		$rows = self::rows( $b, 'reports', 1 );
		$all  = self::url( isset( $b['link'] ) ? $b['link'] : '' );
		if ( ! $rows ) {
			return array( 'state' => 'empty', 'all_link' => $all );
		}
		$r = $rows[0];
		return array(
			'state'      => 'ok',
			'title'      => self::str( isset( $r['title'] ) ? $r['title'] : '', 120 ),
			'scope'      => self::str( isset( $r['scope'] ) ? $r['scope'] : '', 120 ),
			'client'     => isset( $r['kind'] ) && 'client_summary' === $r['kind'],
			'created_at' => self::str( isset( $r['createdAt'] ) ? $r['createdAt'] : '', 40 ),
			'link'       => self::url( isset( $r['link'] ) ? $r['link'] : '' ),
			'all_link'   => $all,
		);
	}

	// ---- helpers, shared with PageValues ----

	/** The rows of a list in an answer, at most $max, arrays only. */
	public static function rows( array $b, $key, $max ) {
		if ( ! isset( $b[ $key ] ) || ! is_array( $b[ $key ] ) ) {
			return array();
		}
		return array_values( array_filter( array_slice( $b[ $key ], 0, $max ), 'is_array' ) );
	}

	public static function str( $v, $max = 300 ) {
		return is_scalar( $v ) ? mb_substr( sanitize_text_field( (string) $v ), 0, $max ) : '';
	}

	public static function int( $v ) {
		return null === $v || '' === $v || ! is_numeric( $v ) ? null : (int) round( (float) $v );
	}

	/**
	 * An http(s) address, or ''. Links into MonoRanks must be https (http on a local site); $any allows http for pages on
	 * other websites (outreach targets).
	 */
	public static function url( $v, $any = false ) {
		$u = is_scalar( $v ) ? esc_url_raw( (string) $v ) : '';
		if ( 0 === strpos( $u, 'https://' ) ) {
			return $u;
		}
		return 0 === strpos( $u, 'http://' ) && ( $any || Connection::local() ) ? $u : '';
	}

	/** "/pricing/" from "https://example.com/pricing/". */
	public static function path( $url ) {
		$p = wp_parse_url( (string) $url, PHP_URL_PATH );
		return $p ? self::str( $p, 120 ) : self::str( $url, 120 );
	}

	/** An address in the MonoRanks app for this website, e.g. "/integrations", or the app itself. */
	public static function app_link( $path ) {
		$c    = Connection::get();
		$base = ! empty( $c['api_base'] ) ? $c['api_base'] : MONORANKS_API_BASE;
		$site = Api::site_id();
		return $site ? $base . '/sites/' . rawurlencode( $site ) . $path : $base;
	}
}
