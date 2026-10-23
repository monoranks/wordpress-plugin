<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes SEO fields in whichever SEO plugin is active (Yoast SEO, Rank Math, All in One SEO, The SEO
 * Framework). Without one, the connector stores its own values and prints them in the page head. When an SEO plugin
 * MonoRanks cannot write for is active, SEO field writes are refused with a reason that names it (see refusal()), and
 * nothing is printed next to that plugin's own tags.
 */
class SeoFields {

	const OWN_TITLE       = '_monoranks_seo_title';
	const OWN_DESCRIPTION = '_monoranks_seo_description';
	const OWN_CANONICAL   = '_monoranks_canonical';
	const OWN_NOINDEX     = '_monoranks_noindex';
	/** The SEO Framework: the title MonoRanks wrote into _genesis_title, so only that title loses the site name suffix. */
	const TSF_TITLE_MARK = '_monoranks_tsf_title';
	/** The SEO Framework's settings option (its THE_SEO_FRAMEWORK_SITE_OPTIONS). */
	const TSF_OPTIONS = 'autodescription-site-settings';

	const NAMES = array(
		'yoast'    => 'Yoast SEO',
		'rankmath' => 'Rank Math',
		'aioseo'   => 'All in One SEO',
		'tsf'      => 'The SEO Framework',
	);

	/**
	 * SEO plugins that print their own title, description, canonical and robots tags but whose fields MonoRanks does
	 * not write yet: the constant each one defines => its name. With one of these active, plugin() is 'other'.
	 */
	const UNSUPPORTED = array(
		'SEOPRESS_VERSION'   => 'SEOPress',
		'SLIM_SEO_VER'       => 'Slim SEO',
		'SQ_VERSION'         => 'Squirrly SEO',
		'SMARTCRAWL_VERSION' => 'SmartCrawl',
		'WPMSEO_VERSION'     => 'WP Meta SEO',
		'SSP_VERSION'        => 'SEO SIMPLE PACK',
	);

	/** The fields that live in the SEO plugin (the rest of Writer::FIELDS does not depend on it). */
	const SEO_FIELDS = array( 'seo_title', 'seo_description', 'canonical', 'noindex' );

	/** 'yoast', 'rankmath', 'aioseo', 'tsf', 'other' (an SEO plugin MonoRanks cannot write for) or 'none'. */
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
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || class_exists( 'The_SEO_Framework\Load', false ) ) {
			return 'tsf';
		}
		return '' !== self::unsupported() ? 'other' : 'none';
	}

	/** The name of the active SEO plugin MonoRanks cannot write for, or ''. */
	private static function unsupported() {
		foreach ( self::UNSUPPORTED as $constant => $name ) {
			if ( defined( $constant ) ) {
				return $name;
			}
		}
		return '';
	}

	/** The active SEO plugin's name ('' without one). */
	public static function plugin_name() {
		$plugin = self::plugin();
		if ( 'other' === $plugin ) {
			return self::unsupported();
		}
		return isset( self::NAMES[ $plugin ] ) ? self::NAMES[ $plugin ] : '';
	}

	/** False only when an SEO plugin MonoRanks cannot write for is active. */
	public static function supported() {
		return 'other' !== self::plugin();
	}

	/**
	 * Why a write of this SEO field to this post would not show on the page, as array( error, reason ), or null when the
	 * write can go ahead. Writer checks this before it changes anything.
	 */
	public static function refusal( $post_id, $field ) {
		if ( ! in_array( $field, self::SEO_FIELDS, true ) ) {
			return null;
		}
		$plugin = self::plugin();
		if ( 'other' === $plugin ) {
			$name = self::unsupported();
			return array(
				'error'  => 'seo_plugin_unsupported',
				/* The reason is shown in the MonoRanks app, which is in English. */
				'reason' => sprintf( '%1$s is active on this site and prints its own SEO tags. MonoRanks cannot write into %1$s yet, so nothing was changed. Make this change in %1$s instead.', $name ),
			);
		}
		if ( 'tsf' === $plugin && '' !== self::tsf_home_option( $post_id, $field ) ) {
			$labels = array( 'seo_title' => 'Meta Title', 'seo_description' => 'Meta Description', 'canonical' => 'Canonical URL' );
			return array(
				'error'  => 'seo_plugin_homepage_setting',
				'reason' => sprintf( 'On the homepage, The SEO Framework uses the %s from its Homepage Settings, which wins over the page\'s own field. Nothing was changed. Clear that setting in The SEO Framework\'s Homepage Settings and try again, or change the value there.', $labels[ $field ] ),
			);
		}
		return null;
	}

	/**
	 * The SEO Framework's Homepage Settings value for this field when the post is the static front page, else ''. Its
	 * title, description and canonical there override the front page's own post meta; noindex does not (the page's own
	 * robots setting wins, see its Meta\Robots\Front::assert_no()).
	 */
	private static function tsf_home_option( $post_id, $field ) {
		$keys = array( 'seo_title' => 'homepage_title', 'seo_description' => 'homepage_description', 'canonical' => 'homepage_canonical' );
		if ( ! isset( $keys[ $field ] ) || ! self::is_front_page( $post_id ) ) {
			return '';
		}
		$options = get_option( self::TSF_OPTIONS );
		$value   = is_array( $options ) && isset( $options[ $keys[ $field ] ] ) ? $options[ $keys[ $field ] ] : '';
		return is_string( $value ) ? trim( $value ) : '';
	}

	private static function is_front_page( $post_id ) {
		return 'page' === get_option( 'show_on_front' ) && (int) $post_id > 0 && (int) get_option( 'page_on_front' ) === (int) $post_id;
	}

	private static function keys() {
		switch ( self::plugin() ) {
			case 'yoast':
				return array( 'seo_title' => '_yoast_wpseo_title', 'seo_description' => '_yoast_wpseo_metadesc', 'canonical' => '_yoast_wpseo_canonical', 'noindex' => '_yoast_wpseo_meta-robots-noindex' );
			case 'rankmath':
				return array( 'seo_title' => 'rank_math_title', 'seo_description' => 'rank_math_description', 'canonical' => 'rank_math_canonical_url', 'noindex' => 'rank_math_robots' );
			case 'tsf':
				// The SEO Framework's post meta (Data\Plugin\Post::get_default_meta()). _genesis_noindex is a "qubit":
				// 1 forces noindex, -1 forces index, 0 or missing follows its global settings.
				return array( 'seo_title' => '_genesis_title', 'seo_description' => '_genesis_description', 'canonical' => '_genesis_canonical_uri', 'noindex' => '_genesis_noindex' );
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
		if ( 'tsf' === self::plugin() ) {
			// On the front page the Homepage Settings value is what the page shows, when there is one.
			$home = self::tsf_home_option( $post_id, $field );
			if ( '' !== $home ) {
				return $home;
			}
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
		$plugin = self::plugin();
		if ( 'noindex' === $field ) {
			$on = '1' === (string) $value;
			if ( 'rankmath' === $plugin ) {
				$robots = get_post_meta( $post_id, 'rank_math_robots', true );
				$robots = is_array( $robots ) ? array_values( array_diff( $robots, array( 'noindex', 'index' ) ) ) : array();
				$robots[] = $on ? 'noindex' : 'index';
				return false !== update_post_meta( $post_id, 'rank_math_robots', $robots );
			}
			if ( 'tsf' === $plugin ) {
				if ( $on ) {
					update_post_meta( $post_id, '_genesis_noindex', 1 );
				} elseif ( (int) get_post_meta( $post_id, '_genesis_noindex', true ) > 0 ) {
					// Back to "Default", the way The SEO Framework stores 0 (it deletes it). A forced index (-1) stays.
					delete_post_meta( $post_id, '_genesis_noindex' );
				}
				return true;
			}
			$value = $on ? '1' : ( 'yoast' === $plugin ? '2' : '0' );
		}
		if ( 'tsf' === $plugin && 'seo_title' === $field ) {
			// The SEO Framework adds the site name (or the tagline on the homepage) to a custom title. The approved
			// title is the whole title, as with the other plugins, so the mark lets tsf_branding() drop the addition
			// for as long as the field still holds this title.
			'' === $value ? delete_post_meta( $post_id, self::TSF_TITLE_MARK ) : update_post_meta( $post_id, self::TSF_TITLE_MARK, wp_slash( $value ) );
		}
		if ( '' === $value && 'noindex' !== $field ) {
			delete_post_meta( $post_id, $keys[ $field ] );
			return true;
		}
		update_post_meta( $post_id, $keys[ $field ], wp_slash( $value ) );
		return true;
	}

	/** Rank Math's term meta for the two term fields. */
	const RANKMATH_TERM = array( 'seo_title' => 'rank_math_title', 'seo_description' => 'rank_math_description' );
	/** Yoast's taxonomy meta option and its keys for the two term fields. */
	const YOAST_TAX_OPTION = 'wpseo_taxonomy_meta';
	const YOAST_TERM       = array( 'seo_title' => 'wpseo_title', 'seo_description' => 'wpseo_desc' );
	/** The fields MonoRanks writes on category, tag and other archive pages. */
	const TERM_FIELDS = array( 'seo_title', 'seo_description' );

	/** True when term SEO fields can be written with the active SEO setup: Rank Math, Yoast or no SEO plugin. */
	public static function term_supported() {
		return in_array( self::plugin(), array( 'rankmath', 'yoast', 'none' ), true );
	}

	/**
	 * Why a write of this field to a term would not show, as array( error, reason ), or null when it can go ahead.
	 * Without an SEO plugin only the description can be written: it is the term's own description.
	 */
	public static function term_refusal( $field ) {
		$plugin = self::plugin();
		if ( ! self::term_supported() ) {
			/* The reason is shown in the MonoRanks app, which is in English. */
			return array( 'error' => 'not_supported', 'reason' => sprintf( 'MonoRanks cannot write category pages in %s yet.', self::plugin_name() ) );
		}
		if ( 'none' === $plugin && 'seo_title' === $field ) {
			return array( 'error' => 'not_supported', 'reason' => 'Titles of category pages need an SEO plugin.' );
		}
		return null;
	}

	/** Yoast's stored values for one term, or an empty array. */
	private static function yoast_term_meta( $term_id, $taxonomy ) {
		$all = get_option( self::YOAST_TAX_OPTION );
		return is_array( $all ) && isset( $all[ $taxonomy ][ $term_id ] ) && is_array( $all[ $taxonomy ][ $term_id ] ) ? $all[ $taxonomy ][ $term_id ] : array();
	}

	/**
	 * The stored (raw, not rendered) value of a term field in the active SEO setup, '' when unset. Without an SEO plugin
	 * the description is the term's own description and there is no title.
	 */
	public static function term_get( $term_id, $taxonomy, $field ) {
		$term_id = (int) $term_id;
		switch ( self::plugin() ) {
			case 'rankmath':
				if ( ! isset( self::RANKMATH_TERM[ $field ] ) ) {
					return '';
				}
				$value = get_term_meta( $term_id, self::RANKMATH_TERM[ $field ], true );
				return is_string( $value ) ? $value : '';
			case 'yoast':
				if ( ! isset( self::YOAST_TERM[ $field ] ) ) {
					return '';
				}
				$meta = self::yoast_term_meta( $term_id, $taxonomy );
				return isset( $meta[ self::YOAST_TERM[ $field ] ] ) && is_string( $meta[ self::YOAST_TERM[ $field ] ] ) ? $meta[ self::YOAST_TERM[ $field ] ] : '';
			case 'none':
				if ( 'seo_description' !== $field ) {
					return '';
				}
				$value = get_term_field( 'description', $term_id, $taxonomy, 'raw' );
				return is_string( $value ) ? $value : '';
			default:
				return '';
		}
	}

	/** Noindex of a term in the active SEO plugin, false when unknown. */
	public static function term_noindex( $term_id, $taxonomy ) {
		$plugin = self::plugin();
		if ( 'rankmath' === $plugin ) {
			$robots = get_term_meta( (int) $term_id, 'rank_math_robots', true );
			return is_array( $robots ) && in_array( 'noindex', $robots, true );
		}
		if ( 'yoast' === $plugin ) {
			$meta = self::yoast_term_meta( (int) $term_id, $taxonomy );
			return isset( $meta['wpseo_noindex'] ) && 'noindex' === $meta['wpseo_noindex'];
		}
		return false;
	}

	/**
	 * Writes a term field into the active SEO setup ('' clears it). Values arrive sanitised by Writer. Returns true on
	 * success. Callers check term_refusal() first.
	 */
	public static function term_set( $term_id, $taxonomy, $field, $value ) {
		$term_id = (int) $term_id;
		$value   = (string) $value;
		switch ( self::plugin() ) {
			case 'rankmath':
				if ( ! isset( self::RANKMATH_TERM[ $field ] ) ) {
					return false;
				}
				if ( '' === $value ) {
					delete_term_meta( $term_id, self::RANKMATH_TERM[ $field ] );
				} else {
					update_term_meta( $term_id, self::RANKMATH_TERM[ $field ], wp_slash( $value ) );
				}
				return true;
			case 'yoast':
				return isset( self::YOAST_TERM[ $field ] ) ? self::yoast_term_set( $term_id, $taxonomy, self::YOAST_TERM[ $field ], $value ) : false;
			case 'none':
				if ( 'seo_description' !== $field ) {
					return false;
				}
				$r = wp_update_term( $term_id, $taxonomy, array( 'description' => wp_slash( $value ) ) );
				return ! is_wp_error( $r );
			default:
				return false;
		}
	}

	/**
	 * Yoast keeps term values in one option, wpseo_taxonomy_meta[ taxonomy ][ term_id ]. Its own API
	 * (WPSEO_Taxonomy_Meta::set_values) is used when present, with every stored value of the term passed back in, because
	 * it resets the keys it is not given. Then Yoast's indexable for the term is rebuilt, since its pages read the
	 * indexable, not the option.
	 */
	private static function yoast_term_set( $term_id, $taxonomy, $key, $value ) {
		$meta         = self::yoast_term_meta( $term_id, $taxonomy );
		$meta[ $key ] = $value;
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) && method_exists( 'WPSEO_Taxonomy_Meta', 'set_values' ) && class_exists( 'WPSEO_Utils' ) ) {
			\WPSEO_Taxonomy_Meta::set_values( $term_id, $taxonomy, $meta );
		} else {
			$all = get_option( self::YOAST_TAX_OPTION );
			$all = is_array( $all ) ? $all : array();
			$meta = array_filter( $meta, static function ( $v ) { return '' !== $v; } );
			if ( $meta ) {
				$all[ $taxonomy ][ $term_id ] = $meta;
			} else {
				unset( $all[ $taxonomy ][ $term_id ] );
				if ( isset( $all[ $taxonomy ] ) && array() === $all[ $taxonomy ] ) {
					unset( $all[ $taxonomy ] );
				}
			}
			// Yoast's marker that the option is already clean, so it does not validate the whole option again.
			$all['wpseo_already_validated'] = true;
			update_option( self::YOAST_TAX_OPTION, $all );
		}
		self::yoast_rebuild_term( $term_id );
		return true;
	}

	/** Rebuild Yoast's indexable for a term, through its own term watcher, when Yoast offers it. */
	private static function yoast_rebuild_term( $term_id ) {
		$watcher = 'Yoast\\WP\\SEO\\Integrations\\Watchers\\Indexable_Term_Watcher';
		if ( ! function_exists( 'YoastSEO' ) || ! class_exists( $watcher ) ) {
			return;
		}
		try {
			$instance = YoastSEO()->classes->get( $watcher );
			if ( $instance && method_exists( $instance, 'build_indexable' ) ) {
				$instance->build_indexable( (int) $term_id );
			}
		} catch ( \Throwable $e ) {
			// The value is saved; Yoast rebuilds the indexable itself the next time the term is saved.
			unset( $e );
		}
	}

	/** When no SEO plugin handles output, print the connector's own values. */
	public static function register_output() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'core_robots' ) );
		// All in One SEO prints its own tags: hand it the approved values through its filters instead of printing a
		// second description, canonical or robots tag next to its own.
		add_filter( 'aioseo_title', array( __CLASS__, 'aioseo_title' ), 20 );
		add_filter( 'aioseo_description', array( __CLASS__, 'aioseo_description' ), 20 );
		add_filter( 'aioseo_canonical_url', array( __CLASS__, 'aioseo_canonical' ), 20 );
		add_filter( 'aioseo_robots_meta', array( __CLASS__, 'aioseo_robots' ), 20 );
		// The SEO Framework prints the values from its own post meta; only its title addition needs a word.
		add_filter( 'the_seo_framework_use_title_branding', array( __CLASS__, 'tsf_branding' ), 20, 3 );
	}

	/**
	 * The SEO Framework: no site name or tagline addition on a title MonoRanks wrote, while the field still holds it.
	 * $args is null for the current page, or The SEO Framework's query arguments ('id', 'tax', 'pta', 'uid').
	 */
	public static function tsf_branding( $use, $args = null, $social = false ) {
		if ( ! $use || $social || 'tsf' !== self::plugin() ) {
			return $use;
		}
		if ( null === $args ) {
			$id = is_singular() ? (int) get_queried_object_id() : 0;
		} else {
			$single = is_array( $args ) && empty( $args['tax'] ) && empty( $args['taxonomy'] ) && empty( $args['pta'] ) && empty( $args['uid'] );
			$id     = $single && ! empty( $args['id'] ) ? (int) $args['id'] : 0;
		}
		if ( ! $id || '' !== self::tsf_home_option( $id, 'seo_title' ) ) {
			return $use;
		}
		$mark = get_post_meta( $id, self::TSF_TITLE_MARK, true );
		return is_string( $mark ) && '' !== $mark && get_post_meta( $id, '_genesis_title', true ) === $mark ? false : $use;
	}

	/** The connector's own value for the current singular page, or '' when none was approved. */
	private static function own( $key ) {
		if ( 'aioseo' !== self::plugin() || ! is_singular() ) {
			return '';
		}
		$value = get_post_meta( get_queried_object_id(), $key, true );
		return is_string( $value ) ? $value : '';
	}

	public static function aioseo_title( $title ) {
		$own = self::own( self::OWN_TITLE );
		return '' !== $own ? $own : $title;
	}

	public static function aioseo_description( $description ) {
		$own = self::own( self::OWN_DESCRIPTION );
		return '' !== $own ? $own : $description;
	}

	public static function aioseo_canonical( $url ) {
		$own = self::own( self::OWN_CANONICAL );
		return '' !== $own ? $own : $url;
	}

	public static function aioseo_robots( $robots ) {
		if ( '1' === self::own( self::OWN_NOINDEX ) && is_array( $robots ) ) {
			$robots['noindex'] = 'noindex';
			unset( $robots['index'] );
		}
		return $robots;
	}

	/** Without an SEO plugin, an approved noindex joins WordPress's own robots tag instead of printing a second one. */
	public static function core_robots( $robots ) {
		if ( 'none' !== self::plugin() || ! is_singular() ) {
			return $robots;
		}
		if ( '1' === (string) get_post_meta( get_queried_object_id(), self::OWN_NOINDEX, true ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	public static function filter_title( $title ) {
		if ( 'none' !== self::plugin() ) {
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

	/** Only without any SEO plugin: every SEO plugin, known or not, prints its own description and canonical. */
	public static function print_head() {
		if ( 'none' !== self::plugin() || ! is_singular() ) {
			return;
		}
		$id          = get_queried_object_id();
		$description = get_post_meta( $id, self::OWN_DESCRIPTION, true );
		$canonical   = get_post_meta( $id, self::OWN_CANONICAL, true );
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		if ( $canonical ) {
			remove_action( 'wp_head', 'rel_canonical' );
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}
	}
}
