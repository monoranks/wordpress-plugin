<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/** MonoRanks → Settings: connection state, the connector key, last sync, what is sent, recent changes, disconnect. */
class Settings {

	public static function render() {
		echo Admin::view( 'settings', self::data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes
	}

	public static function data() {
		$conn      = Connection::get();
		$connected = Connection::has_key();
		$revoked   = $connected && isset( $conn['key_state'] ) && 'revoked' === $conn['key_state'];
		$sync      = get_option( 'monoranks_sync', array() );
		$overview  = Insights::overview();
		$plugins   = array( 'yoast' => 'Yoast SEO', 'rankmath' => 'Rank Math', 'aioseo' => 'All in One SEO' );
		$seo       = SeoFields::plugin();
		return array(
			'theme'          => Admin::theme(),
			'notice'         => Admin::notice(),
			'connected'      => $connected,
			'revoked'        => $revoked,
			'state'          => $revoked ? 'revoked' : ( $connected ? 'connected' : 'none' ),
			'key_hint'       => $connected ? substr( $conn['key'], 0, 12 ) . '…' : '',
			'key_via'        => $connected && 'pairing' === ( isset( $conn['via'] ) ? $conn['via'] : '' )
				/* translators: 1: user, 2: date */
				? sprintf( __( 'set when %1$s connected MonoRanks on %2$s', 'monoranks' ), $conn['paired_by'], wp_date( get_option( 'date_format' ), strtotime( $conn['paired_at'] ) ) )
				: __( 'pasted by hand', 'monoranks' ),
			'api_base'       => $connected ? $conn['api_base'] : MONORANKS_API_BASE,
			'last_sent'      => Admin::ago( isset( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '', __( 'Never', 'monoranks' ) ),
			'last_error'     => isset( $conn['last_error'] ) ? (string) $conn['last_error'] : '',
			'sending'        => ! empty( $sync['run_id'] ) ? array( 'page' => (int) $sync['page'], 'pages' => (int) $sync['pages'] ) : null,
			'items'          => array_sum( Rest::status_payload()['post_types'] ),
			'next_audit'     => $overview && ! empty( $overview['next_audit_at'] ) ? Admin::ago( $overview['next_audit_at'] ) : '',
			'seo_plugin'     => isset( $plugins[ $seo ] ) ? $plugins[ $seo ] : '',
			'profile_url'    => admin_url( 'profile.php#application-passwords-section' ),
			'post_url'       => admin_url( 'admin-post.php' ),
			'show_address'   => Admin::shows_address_field(),
			'app_url'        => $connected ? $conn['api_base'] : MONORANKS_API_BASE,
			'log'            => array_slice( array_reverse( (array) get_option( 'monoranks_change_log', array() ) ), 0, 50 ),
			'can_write'      => Writer::FIELDS,
		);
	}
}
