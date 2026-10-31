<?php
/**
 * WebSite, WebPage, Article and Person pieces for the standalone graph.
 *
 * @package DovetailStudio\SchemaSeo
 */

namespace DovetailStudio\SchemaSeo;

use DovetailStudio\Core\Seo\SchemaGraph;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the site-level pieces Yoast SEO would normally provide. These pieces
 * are only added to the standalone graph, which the core prints only when
 * Yoast SEO does not provide the graph, so they never duplicate Yoast output.
 */
final class SiteGraph {
	/** Register frontend hooks. */
	public function register_hooks() {
		add_filter( SchemaGraph::FILTER, array( $this, 'add_pieces' ), 20 );
	}

	/**
	 * Add WebSite, WebPage, primary image, Article and author pieces.
	 *
	 * Runs after the Organization (priority 10) and BreadcrumbList (priority 15)
	 * pieces so it can reference them only when they exist.
	 *
	 * @param array $pieces Standalone graph pieces.
	 * @return array
	 */
	public function add_pieces( $pieces ) {
		$pieces  = is_array( $pieces ) ? $pieces : array();
		$options = Schema::get_options();

		if ( empty( $options['site_graph_enabled'] ) || is_404() ) {
			return $pieces;
		}

		// The site's publisher: the person it represents (Person mode), otherwise the business.
		$publisher_id = '';

		foreach ( array( 'person', 'organization' ) as $key ) {
			if ( SchemaGraph::has_piece( $pieces, SchemaGraph::id( $key ) ) ) {
				$publisher_id = SchemaGraph::id( $key );

				break;
			}
		}

		$post = is_singular() ? get_queried_object() : null;
		$post = $post instanceof \WP_Post ? $post : null;

		$pieces[] = $this->website( $publisher_id );

		$image = $post ? $this->primary_image( $post ) : array();

		if ( $image ) {
			$pieces[] = $image;
		}

		$pieces[] = $this->webpage( $post, $pieces, $publisher_id, $image );

		if ( $post && $this->is_article( $post ) ) {
			$author = $this->person( (int) $post->post_author );

			$pieces[] = $this->article( $post, $author, $publisher_id, $image );

			if ( $author ) {
				$pieces[] = $author;
			}
		}

		return $pieces;
	}

	/**
	 * Build the WebSite piece.
	 *
	 * @param string $publisher_id Publisher `@id` (Person or Organization), or empty when absent.
	 * @return array
	 */
	private function website( $publisher_id ) {
		$website = array(
			'@type'       => 'WebSite',
			'@id'         => SchemaGraph::id( 'website' ),
			'url'         => home_url( '/' ),
			'name'        => $this->clean_text( get_bloginfo( 'name' ) ),
			'description' => $this->clean_text( get_bloginfo( 'description' ) ),
			'inLanguage'  => get_bloginfo( 'language' ),
		);

		if ( '' !== $publisher_id ) {
			$website['publisher'] = array( '@id' => $publisher_id );
		}

		/**
		 * Filters the standalone WebSite piece.
		 *
		 * @param array $website WebSite piece.
		 */
		return $this->remove_empty( (array) apply_filters( 'dovetail_studio_schema_website', $website ) );
	}

	/**
	 * Build the WebPage piece for the current request.
	 *
	 * @param \WP_Post|null $post            Current singular post, if any.
	 * @param array         $pieces          Pieces already in the graph.
	 * @param string        $publisher_id    Publisher `@id` (Person or Organization), or empty when absent.
	 * @param array         $image           Primary image piece, if any.
	 * @return array
	 */
	private function webpage( $post, array $pieces, $publisher_id, array $image ) {
		$url     = SchemaGraph::current_url();
		$webpage = array(
			'@type'       => $this->webpage_type(),
			'@id'         => SchemaGraph::id( 'webpage' ),
			'url'         => $url,
			'name'        => $this->clean_text( wp_get_document_title() ),
			'description' => $this->description( $post ),
			'isPartOf'    => array( '@id' => SchemaGraph::id( 'website' ) ),
			'inLanguage'  => get_bloginfo( 'language' ),
		);

		if ( $post ) {
			$webpage['datePublished'] = get_post_time( 'c', true, $post );
			$webpage['dateModified']  = get_post_modified_time( 'c', true, $post );
		}

		if ( $image ) {
			$webpage['primaryImageOfPage'] = array( '@id' => $image['@id'] );
			$webpage['image']              = array( '@id' => $image['@id'] );
			$webpage['thumbnailUrl']       = $image['url'];
		}

		if ( '' !== $publisher_id && is_front_page() ) {
			$webpage['about'] = array( '@id' => $publisher_id );
		}

		$breadcrumb_id = SchemaGraph::id( 'breadcrumb' );

		if ( SchemaGraph::has_piece( $pieces, $breadcrumb_id ) ) {
			$webpage['breadcrumb'] = array( '@id' => $breadcrumb_id );
		}

		/**
		 * Filters the standalone WebPage piece.
		 *
		 * @param array         $webpage WebPage piece.
		 * @param \WP_Post|null $post    Current singular post, if any.
		 */
		return $this->remove_empty(
			(array) apply_filters( 'dovetail_studio_schema_webpage', $webpage, $post )
		);
	}

	/**
	 * Resolve the WebPage type for the current request.
	 *
	 * @return string|array
	 */
	private function webpage_type() {
		$type = 'WebPage';

		if ( is_search() ) {
			$type = array( 'CollectionPage', 'SearchResultsPage' );
		} elseif ( is_author() ) {
			$type = 'ProfilePage';
		} elseif ( is_home() || is_archive() ) {
			$type = 'CollectionPage';
		}

		/**
		 * Filters the standalone WebPage type, for example `ItemPage` on products.
		 *
		 * @param string|array $type WebPage type.
		 */
		return apply_filters( 'dovetail_studio_schema_webpage_type', $type );
	}

	/**
	 * Build the primary image piece from the featured image.
	 *
	 * @param \WP_Post $post Current post.
	 * @return array
	 */
	private function primary_image( \WP_Post $post ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $post );
		$source       = $thumbnail_id ? wp_get_attachment_image_src( $thumbnail_id, 'full' ) : false;

		if ( ! is_array( $source ) || empty( $source[0] ) ) {
			return array();
		}

		$caption = $this->clean_text(
			(string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true )
		);

		return $this->remove_empty(
			array(
				'@type'      => 'ImageObject',
				'@id'        => SchemaGraph::id( 'primaryimage' ),
				'url'        => $source[0],
				'contentUrl' => $source[0],
				'width'      => isset( $source[1] ) ? (int) $source[1] : 0,
				'height'     => isset( $source[2] ) ? (int) $source[2] : 0,
				'caption'    => $caption,
				'inLanguage' => get_bloginfo( 'language' ),
			)
		);
	}

	/**
	 * Whether the post should be described as an Article.
	 *
	 * @param \WP_Post $post Current post.
	 * @return bool
	 */
	private function is_article( \WP_Post $post ) {
		/**
		 * Filters the post types described as Article in the standalone graph.
		 *
		 * @param string[] $post_types Post types. Default `post`.
		 */
		$post_types = (array) apply_filters( 'dovetail_studio_schema_article_post_types', array( 'post' ) );

		return in_array( $post->post_type, $post_types, true );
	}

	/**
	 * Build the Article piece.
	 *
	 * @param \WP_Post $post            Current post.
	 * @param array    $author          Author Person piece, if any.
	 * @param string   $publisher_id    Publisher `@id` (Person or Organization), or empty when absent.
	 * @param array    $image           Primary image piece, if any.
	 * @return array
	 */
	private function article( \WP_Post $post, array $author, $publisher_id, array $image ) {
		$webpage_id = SchemaGraph::id( 'webpage' );
		$headline   = $this->clean_text( get_the_title( $post ) );
		$content    = $this->clean_text( (string) $post->post_content );
		$words      = '' === $content ? array() : preg_split( '/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY );

		$article = array(
			'@type'            => 'Article',
			'@id'              => SchemaGraph::id( 'article' ),
			'isPartOf'         => array( '@id' => $webpage_id ),
			'mainEntityOfPage' => array( '@id' => $webpage_id ),
			'headline'         => function_exists( 'mb_substr' ) ? mb_substr( $headline, 0, 110 ) : substr( $headline, 0, 110 ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'wordCount'        => is_array( $words ) ? count( $words ) : 0,
			'commentCount'     => (int) get_comments_number( $post ),
			'articleSection'   => $this->term_names( $post, 'category' ),
			'keywords'         => $this->term_names( $post, 'post_tag' ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);

		if ( $author ) {
			$article['author'] = array(
				'name' => $author['name'],
				'@id'  => $author['@id'],
			);
		}

		if ( '' !== $publisher_id ) {
			$article['publisher'] = array( '@id' => $publisher_id );
		}

		if ( $image ) {
			$article['image']        = array( '@id' => $image['@id'] );
			$article['thumbnailUrl'] = $image['url'];
		}

		/**
		 * Filters the standalone Article piece.
		 *
		 * @param array    $article Article piece.
		 * @param \WP_Post $post    Current post.
		 */
		return $this->remove_empty(
			(array) apply_filters( 'dovetail_studio_schema_article', $article, $post )
		);
	}

	/**
	 * Build the author Person piece.
	 *
	 * @param int $user_id Author user ID.
	 * @return array
	 */
	private function person( $user_id ) {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( ! $user instanceof \WP_User ) {
			return array();
		}

		$person = array(
			'@type'       => 'Person',
			'@id'         => untrailingslashit( home_url( '/' ) ) . '/#/schema/person/' . md5( (string) $user->ID ),
			'name'        => $this->clean_text( $user->display_name ),
			'url'         => get_author_posts_url( $user->ID ),
			'description' => $this->clean_text( (string) get_the_author_meta( 'description', $user->ID ) ),
		);

		$website = esc_url_raw( (string) $user->user_url );

		if ( '' !== $website ) {
			$person['sameAs'] = array( $website );
		}

		if ( get_option( 'show_avatars' ) ) {
			$avatar = get_avatar_url( $user->ID, array( 'size' => 96 ) );

			if ( is_string( $avatar ) && '' !== $avatar ) {
				$person['image'] = array(
					'@type'      => 'ImageObject',
					'@id'        => untrailingslashit( home_url( '/' ) ) . '/#/schema/person/image/' . md5( (string) $user->ID ),
					'url'        => $avatar,
					'contentUrl' => $avatar,
					'caption'    => $person['name'],
				);
			}
		}

		/**
		 * Filters the standalone author Person piece.
		 *
		 * @param array    $person Person piece.
		 * @param \WP_User $user   Author.
		 */
		return $this->remove_empty(
			(array) apply_filters( 'dovetail_studio_schema_person', $person, $user )
		);
	}

	/**
	 * Resolve the page description.
	 *
	 * @param \WP_Post|null $post Current singular post, if any.
	 * @return string
	 */
	private function description( $post ) {
		if ( $post && has_excerpt( $post ) ) {
			return $this->clean_text( get_the_excerpt( $post ) );
		}

		if ( is_front_page() ) {
			return $this->clean_text( get_bloginfo( 'description' ) );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return $this->clean_text( term_description() );
		}

		return '';
	}

	/**
	 * Names of the terms assigned to a post.
	 *
	 * @param \WP_Post $post     Post.
	 * @param string   $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function term_names( \WP_Post $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					function ( $term ) {
						return $term instanceof \WP_Term ? $this->clean_text( $term->name ) : '';
					},
					$terms
				)
			)
		);
	}

	/**
	 * Convert HTML or entity-encoded text to plain text for JSON-LD.
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	private function clean_text( $text ) {
		if ( ! is_scalar( $text ) ) {
			return '';
		}

		$text      = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$collapsed = preg_replace( '/\s+/u', ' ', $text );

		return trim( is_string( $collapsed ) ? $collapsed : $text );
	}

	/**
	 * Remove empty values without removing numeric zero.
	 *
	 * @param array $data Piece data.
	 * @return array
	 */
	private function remove_empty( array $data ) {
		foreach ( $data as $key => $value ) {
			if ( null === $value
				|| '' === $value
				|| false === $value
				|| ( is_array( $value ) && array() === $value )
			) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}
}
