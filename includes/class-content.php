<?php
defined( 'ABSPATH' ) || exit;

/** Published content metadata only: no drafts, no private posts, no user emails, no body text. */
class MonoRanks_Content {

	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	public static function item( WP_Post $post ) {
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
			'seo_title'       => MonoRanks_Seo_Fields::get( $post->ID, 'seo_title' ),
			'seo_description' => MonoRanks_Seo_Fields::get( $post->ID, 'seo_description' ),
			'canonical'       => MonoRanks_Seo_Fields::get( $post->ID, 'canonical' ),
			'noindex'         => '1' === MonoRanks_Seo_Fields::get( $post->ID, 'noindex' ),
			'images'          => $images,
		);
	}

	public static function page( $page, $per_page, $modified_after = null ) {
		$args = array(
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
		$query = new WP_Query( $args );
		return array(
			'items' => array_map( array( __CLASS__, 'item' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}
}
