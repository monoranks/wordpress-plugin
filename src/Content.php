<?php
namespace MonoRanks;

defined( 'ABSPATH' ) || exit;

/** Published content metadata only: no drafts, no private posts, no user emails, no body text. */
class Content {

	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/**
	 * Taxonomies whose terms have archive pages: public, publicly queryable and with a rewrite (category, post_tag,
	 * product_cat, product_tag and the like). Post formats are left out: their archives are not pages anyone writes for.
	 */
	public static function taxonomies() {
		$out = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $name => $tax ) {
			if ( 'post_format' === $name || empty( $tax->publicly_queryable ) || empty( $tax->rewrite ) ) {
				continue;
			}
			$out[] = $name;
		}
		return $out;
	}

	/** The content item id of a term: "term:<taxonomy>:<term_id>". */
	public static function term_key( $taxonomy, $term_id ) {
		return 'term:' . $taxonomy . ':' . (int) $term_id;
	}

	/** One term (an archive page) as a content item, in the same shape as a post, or null when it has no link. */
	public static function term_item( \WP_Term $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) || ! $link ) {
			return null;
		}
		$tax = get_taxonomy( $term->taxonomy );
		return array(
			'id'              => self::term_key( $term->taxonomy, $term->term_id ),
			'type'            => 'term:' . $term->taxonomy,
			'type_label'      => $tax ? $tax->labels->singular_name : $term->taxonomy,
			'url'             => $link,
			'title'           => $term->name,
			'excerpt'         => wp_trim_words( wp_strip_all_tags( (string) $term->description ), 40, '' ),
			'author'          => null,
			'author_avatar'   => null,
			'categories'      => array(),
			'tags'            => array(),
			'published_at'    => null,
			'modified_at'     => null,
			'seo_title'       => SeoFields::term_get( $term->term_id, $term->taxonomy, 'seo_title' ),
			'seo_description' => SeoFields::term_get( $term->term_id, $term->taxonomy, 'seo_description' ),
			'canonical'       => '',
			'noindex'         => SeoFields::term_noindex( $term->term_id, $term->taxonomy ),
			'images'          => array(),
			'term_id'         => (int) $term->term_id,
			'taxonomy'        => $term->taxonomy,
		);
	}

	/** Terms with at least one post (an empty archive is not a page, like an unpublished post). */
	public static function term_count( $taxonomies = null ) {
		$taxonomies = null === $taxonomies ? self::taxonomies() : $taxonomies;
		if ( ! $taxonomies ) {
			return 0;
		}
		$n = wp_count_terms( array( 'taxonomy' => $taxonomies, 'hide_empty' => true ) );
		return is_wp_error( $n ) ? 0 : (int) $n;
	}

	public static function item( \WP_Post $post ) {
		$author = get_userdata( (int) $post->post_author );
		$cats   = array();
		$tags   = array();
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
			if ( ! $tax->public ) {
				continue;
			}
			$terms = get_the_terms( $post, $tax->name );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( $tax->hierarchical ) {
					$cats[] = $term->name;
				} else {
					$tags[] = $term->name;
				}
			}
		}
		$images = array();
		foreach ( get_attached_media( 'image', $post->ID ) as $image ) {
			$images[] = array( 'id' => $image->ID, 'src' => wp_get_attachment_url( $image->ID ), 'alt' => (string) get_post_meta( $image->ID, '_wp_attachment_image_alt', true ) );
		}
		$thumb = get_post_thumbnail_id( $post );
		if ( $thumb && ! wp_list_filter( $images, array( 'id' => $thumb ) ) ) {
			$images[] = array( 'id' => (int) $thumb, 'src' => wp_get_attachment_url( $thumb ), 'alt' => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
		}
		// Images in the body: WordPress renders the alt attribute written there, not the attachment's stored alt, so both go to MonoRanks.
		foreach ( ImageAlt::images( $post->post_content ) as $found ) {
			if ( ! $found['id'] ) {
				continue;
			}
			$content_alt = null === $found['alt'] ? '' : $found['alt'];
			$at          = null;
			foreach ( $images as $i => $known ) {
				if ( (int) $known['id'] === $found['id'] ) {
					$at = $i;
					break;
				}
			}
			if ( null === $at ) {
				$src = wp_get_attachment_url( $found['id'] );
				if ( ! $src ) {
					continue;
				}
				$images[] = array( 'id' => $found['id'], 'src' => $src, 'alt' => (string) get_post_meta( $found['id'], '_wp_attachment_image_alt', true ) );
				$at       = count( $images ) - 1;
			}
			if ( empty( $images[ $at ]['in_content'] ) ) {
				$images[ $at ]['in_content']  = true;
				$images[ $at ]['content_alt'] = $content_alt;
			}
		}
		$type_object = get_post_type_object( $post->post_type );
		return array(
			'id'              => $post->ID,
			'type'            => $post->post_type,
			'type_label'      => $type_object ? $type_object->labels->singular_name : $post->post_type,
			'url'             => get_permalink( $post ),
			'title'           => get_the_title( $post ),
			'excerpt'         => wp_trim_words( wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content ), 40, '' ),
			'author'          => $author ? $author->display_name : null,
			'author_avatar'   => $author ? get_avatar_url( $author->ID, array( 'size' => 64 ) ) : null,
			'categories'      => $cats,
			'tags'            => $tags,
			'published_at'    => get_post_time( 'c', true, $post ),
			'modified_at'     => get_post_modified_time( 'c', true, $post ),
			'seo_title'       => SeoFields::get( $post->ID, 'seo_title' ),
			'seo_description' => SeoFields::get( $post->ID, 'seo_description' ),
			'canonical'       => SeoFields::get( $post->ID, 'canonical' ),
			'noindex'         => '1' === SeoFields::get( $post->ID, 'noindex' ),
			'images'          => $images,
		);
	}

	/**
	 * One page of the content sync: published posts first, then the terms of archive taxonomies, in pages of $per_page.
	 * Post pages are numbered as before; term pages follow the last post page. Terms have no modified date, so a sync
	 * limited to recent changes ($modified_after) sends posts only.
	 */
	public static function page( $page, $per_page, $modified_after = null ) {
		$page     = max( 1, (int) $page );
		$per_page = max( 1, (int) $per_page );
		$args     = array(
			'post_type'      => self::post_types(),
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => false,
		);
		if ( $modified_after ) {
			$args['date_query'] = array( array( 'column' => 'post_modified_gmt', 'after' => $modified_after ) );
		}
		$query      = new \WP_Query( $args );
		$items      = array_map( array( __CLASS__, 'item' ), $query->posts );
		$post_total = (int) $query->found_posts;
		if ( ! $query->posts && $page > 1 ) {
			// Past the last post page WP_Query reports no total, so count the posts on their own.
			$count      = new \WP_Query( array_merge( $args, array( 'posts_per_page' => 1, 'paged' => 1, 'fields' => 'ids' ) ) );
			$post_total = (int) $count->found_posts;
		}
		$post_pages = (int) ceil( $post_total / $per_page );
		$taxonomies = $modified_after ? array() : self::taxonomies();
		$term_total = self::term_count( $taxonomies );
		if ( $term_total && $page > $post_pages ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomies,
					'hide_empty' => true,
					'orderby'    => 'term_id',
					'order'      => 'ASC',
					'number'     => $per_page,
					'offset'     => ( $page - $post_pages - 1 ) * $per_page,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$item = $term instanceof \WP_Term ? self::term_item( $term ) : null;
				if ( $item ) {
					$items[] = $item;
				}
			}
		}
		return array(
			'items' => $items,
			'total' => $post_total + $term_total,
			'pages' => $post_pages + (int) ceil( $term_total / $per_page ),
		);
	}
}
