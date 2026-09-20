<?php
defined( 'ABSPATH' ) || exit;

/**
 * What AI assistants may read (MonoRanks GEO, connector 0.8.0): per-crawler allow/deny lines added to the virtual
 * robots.txt, and an llms.txt served at /llms.txt. Both are written only after the site owner approved them in MonoRanks
 * and can be undone from there. Sites with a physical robots.txt file are not touched (WordPress never serves the
 * virtual one then); MonoRanks shows that case.
 */
class MonoRanks_Ai_Access {

	const OPTION_BOTS = 'monoranks_ai_bots';
	const OPTION_LLMS = 'monoranks_llms_txt';
	const BOTS        = array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-User', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Bytespider', 'meta-externalagent', 'Amazonbot' );

	/** Stored as { "GPTBot": "allow" | "deny", ... }; JSON in and out so the change log keeps one string. */
	public static function get_bots() {
		$v = get_option( self::OPTION_BOTS, '' );
		return is_string( $v ) ? $v : '';
	}

	public static function set_bots( $json ) {
		if ( '' === $json ) {
			delete_option( self::OPTION_BOTS );
			return true;
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return false;
		}
		$clean = array();
		foreach ( $decoded as $bot => $mode ) {
			if ( in_array( $bot, self::BOTS, true ) && in_array( $mode, array( 'allow', 'deny' ), true ) ) {
				$clean[ $bot ] = $mode;
			}
		}
		update_option( self::OPTION_BOTS, wp_json_encode( $clean ), true );
		return true;
	}

	public static function get_llms() {
		$v = get_option( self::OPTION_LLMS, '' );
		return is_string( $v ) ? $v : '';
	}

	public static function set_llms( $text ) {
		if ( '' === trim( (string) $text ) ) {
			delete_option( self::OPTION_LLMS );
			return true;
		}
		update_option( self::OPTION_LLMS, wp_strip_all_tags( (string) $text ), false );
		return true;
	}

	/** Appended to WordPress's virtual robots.txt: one group per crawler the owner decided about. */
	public static function robots_txt( $output ) {
		$bots = json_decode( self::get_bots(), true );
		if ( ! is_array( $bots ) || ! $bots ) {
			return $output;
		}
		$lines = array( '', '# AI crawlers (set in MonoRanks)' );
		foreach ( $bots as $bot => $mode ) {
			$lines[] = 'User-agent: ' . $bot;
			$lines[] = 'deny' === $mode ? 'Disallow: /' : 'Allow: /';
		}
		return rtrim( (string) $output ) . "\n" . implode( "\n", $lines ) . "\n";
	}

	/** /llms.txt straight from the option, before WordPress looks for a page of that name. */
	public static function maybe_serve_llms() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '/llms.txt' !== $path ) {
			return;
		}
		$text = self::get_llms();
		if ( '' === $text ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput -- plain text written by the site owner through MonoRanks
		exit;
	}
}
