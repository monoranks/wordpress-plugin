<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * What MonoRanks → Overview shows: the site's health and AEO scores, search clicks, pages needing attention, fixes
 * approved in MonoRanks and ready to write, and the recent changes. Read from the Insights cache, answered as JSON.
 */
class Overview {

	public static function data() {
		$conn      = Connection::get();
		$connected = Connection::has_key();
		$overview  = $connected ? Insights::overview() : null;
		$app       = $connected ? $conn['api_base'] : MONORANKS_API_BASE;
		$site_url  = $overview && ! empty( $overview['site_url'] ) ? $overview['site_url'] : ( $overview && ! empty( $overview['site_id'] ) ? $app . '/sites/' . rawurlencode( $overview['site_id'] ) : $app );
		return array(
			'connected'  => $connected,
			'revoked'    => $connected && isset( $conn['key_state'] ) && 'revoked' === $conn['key_state'],
			'status'     => Insights::status(),
			'overview'   => $overview,
			'host'       => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'audited'    => $overview ? Admin::ago( $overview['audited_at'] ) : '',
			'next_audit' => $overview ? Admin::ago( $overview['next_audit_at'] ) : '',
			'fetched'    => Admin::ago( Insights::fetched_at() ),
			'last_sent'  => Admin::ago( isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '' ),
			'items'      => $connected ? array_sum( Rest::status_payload()['post_types'] ) : 0,
			'app_url'    => $site_url,
			'log'        => self::log_rows( 10 ),
			'labels'     => self::field_labels(),
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

	/** The change log as the screens show it: newest first, dates formatted, targets named, each row keyed by its stored index. */
	public static function log_rows( $limit ) {
		$log    = (array) get_option( 'monoranks_change_log', array() );
		$labels = self::field_labels();
		$rows   = array();
		$total  = count( $log );
		for ( $i = $total - 1; $i >= 0 && count( $rows ) < $limit; $i-- ) {
			$row = is_array( $log[ $i ] ) ? $log[ $i ] : array();
			$field  = isset( $row['field'] ) ? (string) $row['field'] : '';
			$target = isset( $row['target'] ) ? (string) $row['target'] : '';
			$where  = $target;
			if ( 0 === strpos( $target, 'post:' ) ) {
				$where = get_the_title( (int) substr( $target, 5 ) );
			} elseif ( 0 === strpos( $target, 'attachment:' ) ) {
				/* translators: %d: attachment ID */
				$where = sprintf( __( 'Image #%d', 'monoranks' ), (int) substr( $target, 11 ) );
			} elseif ( 'site' === $target ) {
				$where = __( 'Site', 'monoranks' );
			}
			$ts     = isset( $row['at'] ) ? strtotime( $row['at'] ) : 0;
			$rows[] = array(
				'index'      => $i,
				'when'       => $ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '',
				'actor'      => isset( $row['actor'] ) ? (string) $row['actor'] : '',
				'field'      => $field,
				'fieldLabel' => isset( $labels[ $field ] ) ? $labels[ $field ] : $field,
				'where'      => (string) $where,
				'previous'   => wp_strip_all_tags( isset( $row['previous'] ) ? (string) $row['previous'] : '' ),
				'value'      => wp_strip_all_tags( isset( $row['value'] ) ? (string) $row['value'] : '' ),
			);
		}
		return $rows;
	}
}
