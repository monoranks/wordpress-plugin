<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Calls from this site to MonoRanks, authenticated with the website's connector key (Authorization: Bearer).
 * The key can only send this website's content, change alerts and status; it cannot read anything from MonoRanks.
 */
class Api {

	/** @return array{code:int, body:array|null, error:string|null} */
	public static function post( $path, array $body, $timeout = 15 ) {
		$c = Connection::get();
		if ( empty( $c['key'] ) || empty( $c['api_base'] ) ) {
			return array( 'code' => 0, 'body' => null, 'error' => 'not_connected' );
		}
		$res = wp_remote_post(
			$c['api_base'] . '/api/connector' . $path,
			array(
				'timeout'   => $timeout,
				'headers'   => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $c['key'], 'User-Agent' => 'MonoRanks Connector/' . MONORANKS_CONNECTOR_VERSION ),
				'body'      => wp_json_encode( $body ),
				'sslverify' => ! Connection::local(),
			)
		);
		if ( is_wp_error( $res ) ) {
			Connection::update( array( 'last_error' => $res->get_error_message() ) );
			return array( 'code' => 0, 'body' => null, 'error' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 401 === $code ) {
			Connection::update( array( 'key_state' => 'revoked', 'last_error' => 'MonoRanks did not accept the key' ) );
		} elseif ( $code >= 200 && $code < 300 ) {
			Connection::update( array( 'key_state' => 'ok', 'last_sent_at' => gmdate( 'c' ), 'last_error' => null ) );
		} else {
			Connection::update( array( 'last_error' => is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'MonoRanks answered ' . $code ) );
		}
		return array( 'code' => $code, 'body' => is_array( $json ) ? $json : null, 'error' => $code >= 200 && $code < 300 ? null : ( is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'http_' . $code ) );
	}

	public static function send_status() {
		return self::post( '/status', Rest::status_payload() );
	}
}
