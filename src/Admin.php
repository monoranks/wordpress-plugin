<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/** Settings → MonoRanks: connection status, connect with a key by hand, send content now, disconnect, change log. */
class Admin {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_monoranks_connect_key', array( __CLASS__, 'connect_key' ) );
		add_action( 'admin_post_monoranks_send_now', array( __CLASS__, 'send_now' ) );
		add_action( 'admin_post_monoranks_disconnect', array( __CLASS__, 'disconnect' ) );
	}

	public static function menu() {
		add_options_page( 'MonoRanks', 'MonoRanks', 'manage_options', 'monoranks', array( __CLASS__, 'render' ) );
	}

	private static function notice() {
		$msg = isset( $_GET['monoranks'] ) ? sanitize_key( wp_unslash( $_GET['monoranks'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		$map = array(
			'connected'   => array( 'success', __( 'Connected. Your published content is on its way to MonoRanks.', 'monoranks' ) ),
			'sent'        => array( 'success', __( 'Content sent to MonoRanks.', 'monoranks' ) ),
			'bad_key'     => array( 'error', __( 'That does not look like a MonoRanks connector key. Copy it again from MonoRanks → Settings → API and MCP.', 'monoranks' ) ),
			'bad_address' => array( 'error', __( 'The MonoRanks address must start with https://.', 'monoranks' ) ),
			'rejected'    => array( 'error', __( 'MonoRanks did not accept this key. It may have been revoked or replaced; create a new one in MonoRanks.', 'monoranks' ) ),
			'other_site'  => array( 'error', __( 'This key belongs to a different website in MonoRanks. Create the key on the website with this address.', 'monoranks' ) ),
			'unreachable' => array( 'error', __( 'MonoRanks could not be reached. Check the address and try again.', 'monoranks' ) ),
		);
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $msg ][0] ) . '"><p>' . esc_html( $map[ $msg ][1] ) . '</p></div>';
		}
	}

	public static function render() {
		$conn = Connection::get();
		$log  = array_reverse( (array) get_option( 'monoranks_change_log', array() ) );
		$post = esc_url( admin_url( 'admin-post.php' ) );
		echo '<div class="wrap"><h1>MonoRanks</h1>';
		self::notice();
		if ( Connection::has_key() ) {
			$revoked = isset( $conn['key_state'] ) && 'revoked' === $conn['key_state'];
			echo '<table class="form-table" role="presentation"><tbody>';
			echo '<tr><th>' . esc_html__( 'Sending to MonoRanks', 'monoranks' ) . '</th><td>' . ( $revoked ? '<strong style="color:#b32d2e">' . esc_html__( 'Key revoked', 'monoranks' ) . '</strong> · ' . esc_html__( 'connect again from MonoRanks or paste a new key below', 'monoranks' ) : '<strong style="color:#008a20">' . esc_html__( 'Connected', 'monoranks' ) . '</strong>' ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Key', 'monoranks' ) . '</th><td><code>' . esc_html( substr( $conn['key'], 0, 14 ) ) . '…</code> · ' . esc_html( 'pairing' === ( isset( $conn['via'] ) ? $conn['via'] : '' ) ? sprintf( /* translators: 1: user, 2: date */ __( 'set when %1$s connected MonoRanks on %2$s', 'monoranks' ), $conn['paired_by'], $conn['paired_at'] ) : __( 'pasted by hand', 'monoranks' ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'MonoRanks address', 'monoranks' ) . '</th><td><code>' . esc_html( $conn['api_base'] ) . '</code></td></tr>';
			echo '<tr><th>' . esc_html__( 'Last sent', 'monoranks' ) . '</th><td>' . esc_html( ! empty( $conn['last_sent_at'] ) ? $conn['last_sent_at'] : '—' ) . ( ! empty( $conn['last_error'] ) ? ' · ' . esc_html( $conn['last_error'] ) : '' ) . '</td></tr>';
			echo '</tbody></table>';
			echo '<p>' . esc_html__( 'MonoRanks receives published content metadata (titles, a short excerpt, dates, authors, categories, tags, SEO fields, image alt text) and the posts you publish or update. It can change only SEO titles, meta descriptions, canonical URLs, noindex, image alt text and redirects, and only after you approve the change in MonoRanks.', 'monoranks' ) . '</p>';
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: profile URL */ __( 'Fixes are written with the "MonoRanks" Application Password. Revoke it in <a href="%s">your profile</a> to stop writes; content keeps flowing until you disconnect below.', 'monoranks' ), esc_url( admin_url( 'profile.php#application-passwords-section' ) ) ) ) . '</p>';
			echo '<div style="display:flex;gap:8px">';
			echo '<form method="post" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			wp_nonce_field( 'monoranks_send_now' );
			echo '<input type="hidden" name="action" value="monoranks_send_now"><button class="button button-primary">' . esc_html__( 'Send content now', 'monoranks' ) . '</button></form>';
			echo '<form method="post" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			wp_nonce_field( 'monoranks_disconnect' );
			echo '<input type="hidden" name="action" value="monoranks_disconnect"><button class="button">' . esc_html__( 'Disconnect (stop sending)', 'monoranks' ) . '</button></form>';
			echo '</div>';
		} else {
			echo '<p>' . esc_html__( 'Not connected. The easiest way: in MonoRanks, open your website → Integrations → Connect WordPress → Connect to WordPress, and allow MonoRanks when WordPress asks. Nothing is sent before you connect.', 'monoranks' ) . '</p>';
		}
		$revoked_or_new = ! Connection::has_key() || ( isset( $conn['key_state'] ) && 'revoked' === $conn['key_state'] );
		// Connected and working: keep the manual key form out of the way.
		echo $revoked_or_new ? '' : '<details style="margin-top:24px"><summary style="cursor:pointer;font-weight:600">' . esc_html__( 'Use a different key', 'monoranks' ) . '</summary>';
		echo '<h2>' . esc_html__( 'Connect with a key', 'monoranks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use this when your host blocks Application Passwords. In MonoRanks: Settings → API and MCP → New credential with the content:write scope, then paste the key here. With a key alone MonoRanks receives your content; to apply fixes, also use Connect to WordPress in MonoRanks.', 'monoranks' ) . '</p>';
		echo '<form method="post" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		wp_nonce_field( 'monoranks_connect_key' );
		echo '<input type="hidden" name="action" value="monoranks_connect_key"><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="monoranks-key">' . esc_html__( 'Connector key', 'monoranks' ) . '</label></th><td><input id="monoranks-key" name="key" type="password" class="regular-text" autocomplete="off" placeholder="mr_ws_… or mr_site_…" required></td></tr>';
		echo '<tr><th><label for="monoranks-address">' . esc_html__( 'MonoRanks address', 'monoranks' ) . '</label></th><td><input id="monoranks-address" name="api_base" type="url" class="regular-text" value="' . esc_attr( ! empty( $conn['api_base'] ) ? $conn['api_base'] : MONORANKS_API_BASE ) . '"></td></tr>';
		echo '</tbody></table><p><button class="button">' . esc_html__( 'Connect', 'monoranks' ) . '</button></p></form>';
		echo $revoked_or_new ? '' : '</details>';
		if ( $log ) {
			echo '<h2>' . esc_html__( 'Recent changes applied from MonoRanks', 'monoranks' ) . '</h2><table class="widefat striped"><thead><tr><th>When</th><th>Who</th><th>Field</th><th>Target</th><th>Before</th><th>After</th></tr></thead><tbody>';
			foreach ( array_slice( $log, 0, 50 ) as $row ) {
				echo '<tr><td>' . esc_html( $row['at'] ) . '</td><td>' . esc_html( $row['actor'] ) . '</td><td>' . esc_html( $row['field'] ) . '</td><td>' . esc_html( $row['target'] ) . '</td><td>' . esc_html( $row['previous'] ) . '</td><td>' . esc_html( $row['value'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	private static function back( $code ) {
		wp_safe_redirect( admin_url( 'options-general.php?page=monoranks&monoranks=' . rawurlencode( $code ) ) );
		exit;
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed', 'monoranks' ) );
		}
		check_admin_referer( $nonce );
	}

	public static function connect_key() {
		self::guard( 'monoranks_connect_key' ); // Capability and nonce checked here.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard() above.
		$key  = Connection::valid_key( isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '' );
		$base = Connection::valid_base( isset( $_POST['api_base'] ) ? sanitize_text_field( wp_unslash( $_POST['api_base'] ) ) : '' );
		// phpcs:enable
		if ( ! $key ) {
			self::back( 'bad_key' );
		}
		if ( ! $base ) {
			self::back( 'bad_address' );
		}
		$previous = Connection::get();
		Connection::update( array( 'api_base' => $base, 'key' => $key, 'via' => 'manual', 'key_state' => 'ok', 'paired_at' => gmdate( 'c' ), 'paired_by' => wp_get_current_user()->user_login ) );
		$res = Api::send_status();
		if ( 200 !== $res['code'] ) {
			$previous ? update_option( 'monoranks_connection', $previous, false ) : Connection::clear();
			self::back( 401 === $res['code'] ? 'rejected' : ( 403 === $res['code'] ? 'other_site' : 'unreachable' ) );
		}
		if ( isset( $res['body']['site_id'] ) ) {
			Connection::update( array( 'site_id' => sanitize_text_field( (string) $res['body']['site_id'] ) ) );
		}
		Sync::start();
		self::back( 'connected' );
	}

	public static function send_now() {
		self::guard( 'monoranks_send_now' );
		Api::send_status();
		Sync::start();
		self::back( 'sent' );
	}

	public static function disconnect() {
		self::guard( 'monoranks_disconnect' );
		Sync::unschedule();
		Connection::clear();
		wp_safe_redirect( admin_url( 'options-general.php?page=monoranks' ) );
		exit;
	}
}
