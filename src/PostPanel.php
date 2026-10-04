<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * The "MonoRanks" box in the post editor (block and classic editor, side column), for administrators of a connected
 * site: the page's scores, what MonoRanks saw change last, search clicks and the queries behind them, sessions and
 * revenue when Google Analytics is connected, and lost links that pointed here. The box loads its contents after the
 * editor (REST /admin/post/<id>), so opening a post never waits on MonoRanks; the page's own reads (history and
 * queries) are cached for twelve hours per post.
 */
class PostPanel {

	const ID        = 'monoranks-panel';
	const CACHE     = 'monoranks_post_';
	const FRESH_FOR = 12 * HOUR_IN_SECONDS;
	const HANDLE    = 'monoranks-post-panel';

	public static function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** Who sees the box: administrators (revenue and search data are site-owner data), on a connected site. */
	public static function visible() {
		return Connection::has_key() && current_user_can( 'manage_options' );
	}

	public static function add( $post_type, $post = null ) {
		if ( ! self::visible() || ! in_array( $post_type, Content::post_types(), true ) ) {
			return;
		}
		add_meta_box( self::ID, __( 'MonoRanks', 'monoranks' ), array( __CLASS__, 'render' ), $post_type, 'side', 'default' );
	}

	public static function render( $post ) {
		if ( ! $post || 'publish' !== $post->post_status ) {
			echo '<p class="mr-panel-note">' . esc_html__( 'MonoRanks reads published pages. Scores, clicks and changes appear here after the first audit that follows publishing.', 'monoranks' ) . '</p>';
			return;
		}
		printf(
			'<div class="mr-panel" data-post="%1$d" data-error="%2$s" aria-live="polite" aria-busy="true"><p class="mr-panel-note">%3$s</p></div>',
			(int) $post->ID,
			esc_attr__( 'MonoRanks could not be reached. Reload the page to try again.', 'monoranks' ),
			esc_html__( 'Loading MonoRanks data…', 'monoranks' )
		);
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! self::visible() ) {
			return;
		}
		$dir = dirname( MONORANKS_CONNECTOR_FILE );
		wp_enqueue_style( 'monoranks-column', plugins_url( 'build/column.css', MONORANKS_CONNECTOR_FILE ), array(), self::version( $dir . '/build/column.css' ) );
		wp_enqueue_style( self::HANDLE, plugins_url( 'build/post-panel.css', MONORANKS_CONNECTOR_FILE ), array( 'monoranks-column' ), self::version( $dir . '/build/post-panel.css' ) );
		wp_enqueue_script( self::HANDLE, plugins_url( 'resources/assets/post-panel.js', MONORANKS_CONNECTOR_FILE ), array( 'wp-api-fetch' ), self::version( $dir . '/resources/assets/post-panel.js' ), true );
	}

	private static function version( $path ) {
		$m = file_exists( $path ) ? filemtime( $path ) : false;
		return $m ? (string) $m : MONORANKS_CONNECTOR_VERSION;
	}

	/** REST: the box's contents as HTML (escaped by the view). */
	public static function rest( \WP_REST_Request $req ) {
		$id = (int) $req->get_param( 'id' );
		return array( 'html' => Admin::view( 'post-panel', self::data( $id ) ) );
	}

	public static function can_read( \WP_REST_Request $req ) {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_post', (int) $req->get_param( 'id' ) );
	}

	/** Everything the box shows for one post. */
	public static function data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! Connection::has_key() ) {
			return array( 'state' => 'unconnected', 'settings_url' => Admin::settings_url() );
		}
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array( 'state' => 'draft' );
		}
		$url    = (string) get_permalink( $post );
		$score  = Insights::post_score( $post_id );
		$values = PageValues::cached();
		// The first time ever, pull the site's values here (one to three calls); after that WP-Cron keeps them fresh.
		if ( empty( $values['fetched_at'] ) ) {
			$values = PageValues::refresh( 6 );
		}
		$detail = self::detail( $post_id, $url );
		return array(
			'state'   => 'ok',
			'score'   => $score,
			'values'  => PageValues::for_url( $url, $values ),
			'history' => $detail['history'],
			'queries' => $detail['queries'],
			'period'  => isset( $values['period'] ) ? $values['period'] : PageValues::period(),
			'page_url'     => $score && ! empty( $score['page_url'] ) ? $score['page_url'] : ( ! empty( $detail['history']['link'] ) ? $detail['history']['link'] : '' ),
			'scope_url'    => Grow::app_link( '/integrations' ),
			'analytics_url' => Grow::app_link( '/analytics' ),
		);
	}

	/** The post's own reads, cached for twelve hours: the last change MonoRanks saw, and the top search queries. */
	public static function detail( $post_id, $url ) {
		$key    = self::CACHE . (int) $post_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['url'] ) && $cached['url'] === $url ) {
			return $cached;
		}
		$hist = Api::v1_get( 'pages/history?limit=10&url=' . rawurlencode( $url ), 6 );
		$p    = PageValues::period();
		$rows = Api::v1_get( 'search/rows?dimension=query_page&limit=5&from=' . $p['from'] . '&to=' . $p['to'] . '&page=' . rawurlencode( $url ), 6 );
		$out  = array(
			'url'     => $url,
			'history' => 200 === $hist['code'] && is_array( $hist['body'] ) ? self::normalise_history( $hist['body'] ) : array( 'state' => 404 === $hist['code'] && 'not_found' === $hist['error'] ? 'empty' : Api::v1_state( $hist ) ),
			'queries' => 200 === $rows['code'] && is_array( $rows['body'] ) ? self::normalise_queries( $rows['body'] ) : array( 'state' => Api::v1_state( $rows ) ),
		);
		// A failed read is asked again in an hour, not twelve.
		$failed = 'error' === $out['history']['state'] || 'error' === $out['queries']['state'];
		set_transient( $key, $out, $failed ? HOUR_IN_SECONDS : self::FRESH_FOR );
		return $out;
	}

	/** The newest reading that changed something, with the changes named. */
	public static function normalise_history( array $b ) {
		$link = Grow::url( isset( $b['link'] ) ? $b['link'] : '' );
		$seen = 0;
		foreach ( Grow::rows( $b, 'entries', 100 ) as $e ) {
			++$seen;
			$changes = isset( $e['changes'] ) && is_array( $e['changes'] ) ? $e['changes'] : array();
			if ( ! $changes || ! empty( $e['first'] ) ) {
				continue;
			}
			$named = array();
			foreach ( array_slice( $changes, 0, 6 ) as $c ) {
				if ( is_array( $c ) && isset( $c['field'] ) ) {
					$named[] = self::change_text( (string) $c['field'], isset( $c['before'] ) ? $c['before'] : null, isset( $c['after'] ) ? $c['after'] : null );
				}
			}
			return array( 'state' => 'ok', 'at' => Grow::str( isset( $e['at'] ) ? $e['at'] : '', 40 ), 'changes' => array_values( array_filter( $named ) ), 'more' => max( 0, count( $changes ) - 6 ), 'link' => $link );
		}
		return array( 'state' => $seen ? 'unchanged' : 'empty', 'link' => $link );
	}

	/** "Title", "Word count 812 → 1,040", "Health score 64 → 71". Numbers are shown for the fields where they help. */
	public static function change_text( $field, $before, $after ) {
		$labels = array(
			'status'        => __( 'HTTP status', 'monoranks' ),
			'redirect'      => __( 'Redirect', 'monoranks' ),
			'title'         => __( 'Title', 'monoranks' ),
			'description'   => __( 'Meta description', 'monoranks' ),
			'h1'            => __( 'H1', 'monoranks' ),
			'headings'      => __( 'Headings', 'monoranks' ),
			'canonical'     => __( 'Canonical', 'monoranks' ),
			'noindex'       => __( 'Noindex', 'monoranks' ),
			'robots'        => __( 'Robots', 'monoranks' ),
			'wordCount'     => __( 'Word count', 'monoranks' ),
			'schemaTypes'   => __( 'Structured data', 'monoranks' ),
			'internalLinks' => __( 'Internal links', 'monoranks' ),
			'pageScore'     => __( 'Health score', 'monoranks' ),
			'aeoScore'      => __( 'AEO score', 'monoranks' ),
		);
		if ( ! isset( $labels[ $field ] ) ) {
			return '';
		}
		$numeric = array( 'status', 'headings', 'wordCount', 'internalLinks', 'pageScore', 'aeoScore' );
		if ( in_array( $field, $numeric, true ) && is_numeric( $before ) && is_numeric( $after ) ) {
			/* translators: 1: what changed, such as "Word count", 2: the number before, 3: the number after */
			return sprintf( __( '%1$s %2$s → %3$s', 'monoranks' ), $labels[ $field ], Admin::n( (int) $before ), Admin::n( (int) $after ) );
		}
		return $labels[ $field ];
	}

	public static function normalise_queries( array $b ) {
		$rows = array();
		foreach ( Grow::rows( $b, 'rows', 5 ) as $r ) {
			$q = Grow::str( isset( $r['query'] ) ? $r['query'] : '', 120 );
			if ( '' !== $q ) {
				$rows[] = array( 'query' => $q, 'clicks' => (int) Grow::int( isset( $r['clicks'] ) ? $r['clicks'] : 0 ), 'position' => isset( $r['position'] ) && is_numeric( $r['position'] ) ? round( (float) $r['position'], 1 ) : null );
			}
		}
		return array( 'state' => $rows ? 'ok' : 'empty', 'rows' => $rows );
	}

	/** Forgets every post's cached reads (disconnect, delete, uninstall). */
	public static function clear() {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_' . self::CACHE ) . '%';
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one cleanup query
		foreach ( (array) $keys as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}
}
