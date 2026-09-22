<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * MonoRanks → Overview: the site's health and AEO scores, search clicks, pages needing attention, fixes approved in
 * MonoRanks and ready to write, and the recent changes with Undo. All of it comes from the Insights cache.
 */
class Overview {

	public static function render() {
		echo Admin::view( 'overview', self::data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes
	}

	public static function data() {
		$conn      = Connection::get();
		$connected = Connection::has_key();
		$overview  = $connected ? Insights::overview() : null;
		$app       = $connected ? $conn['api_base'] : MONORANKS_API_BASE;
		$site_url  = $overview && ! empty( $overview['site_url'] ) ? $overview['site_url'] : ( $overview && ! empty( $overview['site_id'] ) ? $app . '/sites/' . rawurlencode( $overview['site_id'] ) : $app );
		return array(
			'theme'        => Admin::theme(),
			'notice'       => Admin::notice(),
			'connected'    => $connected,
			'revoked'      => $connected && isset( $conn['key_state'] ) && 'revoked' === $conn['key_state'],
			'status'       => Insights::status(),
			'overview'     => $overview,
			'host'         => wp_parse_url( home_url(), PHP_URL_HOST ),
			'audited'      => $overview ? Admin::ago( $overview['audited_at'] ) : '',
			'next_audit'   => $overview ? Admin::ago( $overview['next_audit_at'] ) : '',
			'fetched'      => Admin::ago( Insights::fetched_at() ),
			'last_sent'    => Admin::ago( isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '' ),
			'items'        => $connected ? array_sum( Rest::status_payload()['post_types'] ) : 0,
			'app_url'      => $site_url,
			'settings_url' => Admin::settings_url(),
			'post_url'     => admin_url( 'admin-post.php' ),
			'log'          => array_slice( array_reverse( (array) get_option( 'monoranks_change_log', array() ) ), 0, 10 ),
			'labels'       => self::field_labels(),
		);
	}

	public static function field_labels() {
		return array(
			'seo_title'       => __( 'Title', 'monoranks' ),
			'seo_description' => __( 'Meta description', 'monoranks' ),
			'canonical'       => __( 'Canonical', 'monoranks' ),
			'noindex'         => __( 'Noindex', 'monoranks' ),
			'alt'             => __( 'Image alt', 'monoranks' ),
			'redirect'        => __( 'Redirect', 'monoranks' ),
			'content'         => __( 'Opening paragraph', 'monoranks' ),
			'ai_bots'         => __( 'robots.txt', 'monoranks' ),
			'llms_txt'        => __( 'llms.txt', 'monoranks' ),
		);
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

	/** Apply one fix (?fix=<id>) or all of them (fix=all), then tell MonoRanks what happened. */
	public static function apply() {
		Admin::guard( 'monoranks_apply' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard() above.
		$which    = isset( $_POST['fix'] ) ? sanitize_text_field( wp_unslash( $_POST['fix'] ) ) : '';
		$overview = Insights::overview();
		$ready    = $overview ? $overview['ready'] : array();
		$changes  = array();
		foreach ( $ready as $fix ) {
			if ( 'all' === $which || $fix['id'] === $which ) {
				$changes[] = self::change_from_ready( $fix );
			}
		}
		if ( ! $changes ) {
			Admin::back( 'apply_failed', 'overview' );
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
		Admin::back( count( $done ) === count( $changes ) ? 'applied' : 'apply_failed', 'overview' );
	}

	/** Puts the previous value of one logged change back (?entry=<index in the stored log>). */
	public static function undo() {
		Admin::guard( 'monoranks_undo' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard() above.
		$index = isset( $_POST['entry'] ) ? (int) $_POST['entry'] : -1;
		$log   = (array) get_option( 'monoranks_change_log', array() );
		if ( $index < 0 || ! isset( $log[ $index ] ) || ! is_array( $log[ $index ] ) ) {
			Admin::back( 'apply_failed', 'overview' );
		}
		$change = Writer::reverse( $log[ $index ] );
		$result = $change ? Writer::apply( array( $change ), 'Undo (' . wp_get_current_user()->user_login . ')' ) : array();
		Admin::back( $result && ! empty( $result[0]['ok'] ) ? 'undone' : 'apply_failed', 'overview' );
	}
}
