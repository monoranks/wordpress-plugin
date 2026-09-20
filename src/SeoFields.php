<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes SEO fields in whichever SEO plugin is active (Yoast SEO, Rank Math, All in One SEO).
 * Without one, the connector stores its own values and prints them in the page head.
 */
class SeoFields {

	const OWN_TITLE       = '_monoranks_seo_title';
	const OWN_DESCRIPTION = '_monoranks_seo_description';
	const OWN_CANONICAL   = '_monoranks_canonical';
	const OWN_NOINDEX     = '_monoranks_noindex';

	public static function plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		return 'none';
	}

	private static function keys() {
		switch ( self::plugin() ) {
			case 'yoast':
				return array( 'seo_title' => '_yoast_wpseo_title', 'seo_description' => '_yoast_wpseo_metadesc', 'canonical' => '_yoast_wpseo_canonical', 'noindex' => '_yoast_wpseo_meta-robots-noindex' );
			case 'rankmath':
				return array( 'seo_title' => 'rank_math_title', 'seo_description' => 'rank_math_description', 'canonical' => 'rank_math_canonical_url', 'noindex' => 'rank_math_robots' );
			default:
				// AIOSEO keeps its values in its own table; the connector uses its own meta and filters for it.
				return array( 'seo_title' => self::OWN_TITLE, 'seo_description' => self::OWN_DESCRIPTION, 'canonical' => self::OWN_CANONICAL, 'noindex' => self::OWN_NOINDEX );
		}
	}

	public static function get( $post_id, $field ) {
		$keys = self::keys();
		if ( ! isset( $keys[ $field ] ) ) {
			return null;
		}
		$value = get_post_meta( $post_id, $keys[ $field ], true );
		if ( 'noindex' === $field ) {
			if ( 'rankmath' === self::plugin() ) {
				return is_array( $value ) && in_array( 'noindex', $value, true ) ? '1' : '0';
			}
			return in_array( (string) $value, array( '1', 'yes', 'true' ), true ) ? '1' : '0';
		}
		return is_string( $value ) ? $value : '';
	}

	public static function set( $post_id, $field, $value ) {
		$keys = self::keys();
		if ( ! isset( $keys[ $field ] ) ) {
			return false;
		}
		if ( 'noindex' === $field ) {
			$on = '1' === (string) $value;
			if ( 'rankmath' === self::plugin() ) {
				$robots = get_post_meta( $post_id, 'rank_math_robots', true );
				$robots = is_array( $robots ) ? array_values( array_diff( $robots, array( 'noindex', 'index' ) ) ) : array();
				$robots[] = $on ? 'noindex' : 'index';
				return false !== update_post_meta( $post_id, 'rank_math_robots', $robots );
			}
			$value = $on ? '1' : ( 'yoast' === self::plugin() ? '2' : '0' );
		}
		if ( '' === $value && 'noindex' !== $field ) {
			delete_post_meta( $post_id, $keys[ $field ] );
			return true;
		}
		update_post_meta( $post_id, $keys[ $field ], wp_slash( $value ) );
		return true;
	}

	/** When no SEO plugin handles output, print the connector's own values. */
	public static function register_output() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 1 );
	}

	public static function filter_title( $title ) {
		if ( 'none' !== self::plugin() && 'aioseo' !== self::plugin() ) {
			return $title;
		}
		if ( is_singular() ) {
			$own = get_post_meta( get_queried_object_id(), self::OWN_TITLE, true );
			if ( is_string( $own ) && '' !== $own ) {
				return $own;
			}
		}
		return $title;
	}

	public static function print_head() {
		if ( ( 'none' !== self::plugin() && 'aioseo' !== self::plugin() ) || ! is_singular() ) {
			return;
		}
		$id          = get_queried_object_id();
		$description = get_post_meta( $id, self::OWN_DESCRIPTION, true );
		$canonical   = get_post_meta( $id, self::OWN_CANONICAL, true );
		$noindex     = get_post_meta( $id, self::OWN_NOINDEX, true );
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		if ( $canonical ) {
			remove_action( 'wp_head', 'rel_canonical' );
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}
		if ( '1' === (string) $noindex ) {
			echo '<meta name="robots" content="noindex, follow">' . "\n";
		}
	}
}
