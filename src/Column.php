<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The "MonoRanks" column in Posts, Pages and every public post type: the page's health and AEO scores from the last audit,
 * how many fixes wait for it and when it was audited. Sortable by health; a "Needs attention" view lists pages under 60.
 */
class Column {

	const KEY       = 'monoranks';
	const ATTENTION = 60;

	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'hooks' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter' ), 9 );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
	}

	public static function hooks() {
		foreach ( Content::post_types() as $type ) {
			add_filter( 'manage_' . $type . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . $type . '_posts_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
			add_filter( 'manage_edit-' . $type . '_sortable_columns', array( __CLASS__, 'sortable' ) );
			add_filter( 'views_edit-' . $type, array( __CLASS__, 'views' ) );
		}
	}

	/** After Title. */
	public static function columns( $columns ) {
		$out = array();
		foreach ( (array) $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out[ self::KEY ] = 'MonoRanks';
			}
		}
		if ( ! isset( $out[ self::KEY ] ) ) {
			$out[ self::KEY ] = 'MonoRanks';
		}
		return $out;
	}

	public static function sortable( $columns ) {
		$columns[ self::KEY ] = array( self::KEY, true );
		return $columns;
	}

	public static function cell( $column, $post_id ) {
		if ( self::KEY !== $column ) {
			return;
		}
		echo Admin::view( 'column-cell', self::cell_data( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes
	}

	/** What the cell shows: state (unconnected | draft | none | scored) plus the scores and the line under them. */
	public static function cell_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! Connection::has_key() ) {
			return array( 'state' => 'unconnected', 'settings_url' => Admin::settings_url() );
		}
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array( 'state' => 'draft' );
		}
		$score = Insights::post_score( $post_id );
		if ( ! $score || ( null === $score['health'] && null === $score['aeo'] ) ) {
			$overview = Insights::overview();
			return array( 'state' => 'none', 'next_audit' => $overview && ! empty( $overview['next_audit_at'] ) ? $overview['next_audit_at'] : '' );
		}
		return array(
			'state'        => 'scored',
			'health'       => $score['health'],
			'aeo'          => $score['aeo'],
			'line'         => self::cell_line( $score ),
			'fixes_ready'  => (int) $score['fixes_ready'],
			'page_url'     => $score['page_url'],
			'overview_url' => Admin::overview_url(),
		);
	}

	/** "3 fixes ready · audited 2 days ago", "No open issues · audited …", "2 open issues · audited …". */
	public static function cell_line( array $score ) {
		if ( $score['fixes_ready'] > 0 ) {
			/* translators: %s: number of fixes */
			$first = sprintf( _n( '%s fix ready', '%s fixes ready', $score['fixes_ready'], 'monoranks' ), number_format_i18n( $score['fixes_ready'] ) );
		} elseif ( $score['open_issues'] > 0 ) {
			/* translators: %s: number of issues */
			$first = sprintf( _n( '%s open issue', '%s open issues', $score['open_issues'], 'monoranks' ), number_format_i18n( $score['open_issues'] ) );
		} else {
			$first = __( 'No open issues', 'monoranks' );
		}
		$ts = $score['audited_at'] ? strtotime( $score['audited_at'] ) : 0;
		/* translators: %s: relative time such as "2 days" */
		$when = $ts ? sprintf( __( 'audited %s ago', 'monoranks' ), human_time_diff( $ts, time() ) ) : '';
		return $when ? $first . ' · ' . $when : $first;
	}

	/** Orders by health when the column header is clicked; unscored posts stay in the list, at the end. */
	public static function query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::KEY !== $query->get( 'orderby' ) ) {
			return;
		}
		$attention = isset( $_GET['monoranks'] ) && 'attention' === $_GET['monoranks']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter
		foreach ( self::sort_args( $query->get( 'order' ), $attention ) as $k => $v ) {
			$query->set( $k, $v );
		}
	}

	/** Inside the "Needs attention" view every row has a score, so a plain numeric sort on the meta value does. */
	public static function sort_args( $order, $attention = false ) {
		$dir = 'DESC' === strtoupper( (string) $order ) ? 'DESC' : 'ASC';
		if ( $attention ) {
			return array( 'meta_key' => Insights::META_HEALTH, 'orderby' => 'meta_value_num', 'order' => $dir ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list sort
		}
		return array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list sort
				'relation'         => 'OR',
				'monoranks_health' => array( 'key' => Insights::META_HEALTH, 'type' => 'NUMERIC' ),
				'monoranks_none'   => array( 'key' => Insights::META_HEALTH, 'compare' => 'NOT EXISTS' ),
			),
			'orderby'    => array( 'monoranks_health' => $dir ),
		);
	}

	/** A "Needs attention" view next to All / Published, filtering to pages under 60 (?monoranks=attention). */
	public static function views( $views ) {
		if ( ! Connection::has_key() ) {
			return $views;
		}
		$type  = get_current_screen() ? get_current_screen()->post_type : 'post';
		$count = self::attention_count( $type );
		if ( ! $count ) {
			return $views;
		}
		$on   = isset( $_GET['monoranks'] ) && 'attention' === $_GET['monoranks']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter
		$url  = add_query_arg( array( 'post_type' => $type, 'monoranks' => 'attention', 'orderby' => self::KEY, 'order' => 'asc' ), admin_url( 'edit.php' ) );
		$html = sprintf( '<a href="%s"%s>%s <span class="count">(%s)</span></a>', esc_url( $url ), $on ? ' class="current" aria-current="page"' : '', esc_html__( 'Needs attention', 'monoranks' ), esc_html( number_format_i18n( $count ) ) );
		$views[ self::KEY ] = $html;
		return $views;
	}

	public static function attention_count( $type ) {
		global $wpdb;
		$cache = wp_cache_get( 'monoranks_attention_' . $type, 'monoranks' );
		if ( false !== $cache ) {
			return (int) $cache;
		}
		$n = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- one count, cached
			"SELECT COUNT(*) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND CAST(m.meta_value AS SIGNED) < %d AND p.post_type = %s AND p.post_status = 'publish'",
			Insights::META_HEALTH,
			self::ATTENTION,
			$type
		) );
		wp_cache_set( 'monoranks_attention_' . $type, $n, 'monoranks', 5 * MINUTE_IN_SECONDS );
		return $n;
	}

	/** The "Needs attention" filter on the list query. */
	public static function filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( isset( $_GET['monoranks'] ) && 'attention' === $_GET['monoranks'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter
			$query->set( 'meta_query', array( array( 'key' => Insights::META_HEALTH, 'value' => self::ATTENTION, 'compare' => '<', 'type' => 'NUMERIC' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$query->set( 'post_status', 'publish' );
		}
	}
}
