<?php
/**
 * Breadcrumb trail shared by the theme component and the schema graph.
 *
 * @package DovetailStudio\SchemaSeo
 */

namespace DovetailStudio\SchemaSeo;

use DovetailStudio\Core\Seo\SchemaGraph;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves one breadcrumb trail per request and keeps the visible trail and
 * the BreadcrumbList schema identical:
 *
 * - When Yoast SEO owns the graph, the trail is Yoast's (its settings apply)
 *   and Yoast prints the BreadcrumbList.
 * - Otherwise the plugin builds the trail and adds the BreadcrumbList to the
 *   standalone graph.
 *
 * Every item is an array with a plain-text `label` and a `url`; the last item
 * is the current page.
 */
final class Breadcrumbs {

	/** Shared instance. */
	private static $instance = null;

	/** Trail cached once the source decision is final. */
	private $items = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Register frontend hooks. */
	public function register_hooks() {
		add_filter( SchemaGraph::FILTER, array( $this, 'add_graph_piece' ), 15 );
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'filter_yoast_links' ), 20 );
	}

	/**
	 * Whether breadcrumbs are enabled in the module settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$options = Schema::get_options();

		return ! empty( $options['breadcrumbs_enabled'] );
	}

	/**
	 * Return the breadcrumb trail of the current request, current page last.
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	public function get_items() {
		if ( null !== $this->items ) {
			return $this->items;
		}

		$items = array();

		if ( $this->is_enabled() && ! is_admin() && ! is_front_page() && ! is_404() ) {
			$from_yoast = SchemaGraph::instance()->yoast_handles_graph();
			$items      = $from_yoast ? $this->yoast_items() : array();

			if ( array() === $items ) {
				/**
				 * Filters the trail built by the plugin. With Yoast SEO the same
				 * filter runs inside `wpseo_breadcrumb_links`, so a change applies
				 * to the visible trail and the BreadcrumbList schema alike.
				 *
				 * @param array  $items  Trail items (`label`, `url`), current page last.
				 * @param string $source `plugin` or `yoast`.
				 */
				$items = apply_filters( 'dovetail_studio_breadcrumb_items', $this->plugin_items(), 'plugin' );
			}

			$items = $this->normalize( $items );
		}

		// The Yoast decision is final only once wp_head has run.
		if ( did_action( 'wp_head' ) ) {
			$this->items = $items;
		}

		return $items;
	}

	/**
	 * Add the BreadcrumbList piece to the standalone graph.
	 *
	 * @param array $pieces Standalone graph pieces.
	 * @return array
	 */
	public function add_graph_piece( $pieces ) {
		$pieces = is_array( $pieces ) ? $pieces : array();
		$items  = $this->get_items();
		$last   = count( $items ) - 1;

		// Google needs a URL on every item but the last, so a parent without a link is left out of the schema.
		$items = array_values(
			array_filter(
				$items,
				function ( $item, $index ) use ( $last ) {
					return $index === $last || '' !== $item['url'];
				},
				ARRAY_FILTER_USE_BOTH
			)
		);
		$last  = count( $items ) - 1;

		if ( $last < 1 ) {
			return $pieces;
		}

		$elements = array();

		foreach ( $items as $index => $item ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['label'],
			);

			// The current page is the last item and is identified by the list itself.
			if ( $index < $last ) {
				$element['item'] = $item['url'];
			}

			$elements[] = $element;
		}

		$pieces[] = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => SchemaGraph::id( 'breadcrumb' ),
			'itemListElement' => $elements,
		);

		return $pieces;
	}

	/**
	 * Apply `dovetail_studio_breadcrumb_items` to Yoast's trail so code-level
	 * changes reach both the visible trail and Yoast's BreadcrumbList.
	 *
	 * @param mixed $links Yoast breadcrumb links.
	 * @return mixed
	 */
	public function filter_yoast_links( $links ) {
		if ( ! is_array( $links ) || ! has_filter( 'dovetail_studio_breadcrumb_items' ) ) {
			return $links;
		}

		$items    = $this->from_yoast_links( $links );
		$filtered = apply_filters( 'dovetail_studio_breadcrumb_items', $items, 'yoast' );

		if ( ! is_array( $filtered ) ) {
			return $links;
		}

		$result = array();

		foreach ( $this->normalize( $filtered ) as $index => $item ) {
			$original = isset( $links[ $index ] ) && is_array( $links[ $index ] )
				? $links[ $index ]
				: array();
			$same_url = isset( $original['url'] ) && $original['url'] === $item['url'];
			$link     = $same_url ? $original : array();

			$link['url']  = $item['url'];
			$link['text'] = $item['label'];
			$result[]     = $link;
		}

		return $result;
	}

	/**
	 * Read the trail Yoast built for the current page.
	 *
	 * @return array
	 */
	private function yoast_items() {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return array();
		}

		try {
			$meta  = YoastSEO()->meta->for_current_page();
			$links = is_object( $meta ) ? $meta->breadcrumbs : array();
		} catch ( \Throwable $error ) {
			return array();
		}

		return is_array( $links ) ? $this->from_yoast_links( $links ) : array();
	}

	/**
	 * Convert Yoast links (`text`, `url`) to trail items (`label`, `url`).
	 *
	 * @param array $links Yoast breadcrumb links.
	 * @return array
	 */
	private function from_yoast_links( array $links ) {
		$items = array();

		foreach ( $links as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$items[] = array(
				'label' => isset( $link['text'] ) ? $link['text'] : '',
				'url'   => isset( $link['url'] ) ? $link['url'] : '',
			);
		}

		return $items;
	}

	/**
	 * Build the plugin trail for the current request.
	 *
	 * @return array
	 */
	private function plugin_items() {
		$home = array(
			'label' => $this->home_label(),
			'url'   => home_url( '/' ),
		);

		/**
		 * Short-circuits the plugin trail, for example with WooCommerce's own
		 * trail on shop pages. Return an array of items (home first, current
		 * page last) to replace the default builder.
		 *
		 * @param array|null $trail Trail, or null to use the default builder.
		 * @param array      $home  Home item.
		 */
		$trail = apply_filters( 'dovetail_studio_breadcrumb_trail', null, $home );

		if ( is_array( $trail ) ) {
			return $trail;
		}

		$items   = array( $home );
		$current = null;

		if ( is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$current    = $this->item(
				$posts_page ? get_the_title( $posts_page ) : __( 'Blog', 'dovetail-studio' ),
				$posts_page ? get_permalink( $posts_page ) : home_url( '/' )
			);
		} elseif ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof \WP_Post ) {
				$items   = array_merge( $items, $this->singular_parents( $post ) );
				$current = $this->item( get_the_title( $post ), SchemaGraph::current_url() );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$items   = array_merge(
					$items,
					$this->taxonomy_parent( $term->taxonomy ),
					$this->term_ancestors( $term )
				);
				$current = $this->item( $term->name, get_term_link( $term ) );
			}
		} elseif ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$current   = $this->item(
				post_type_archive_title( '', false ),
				is_string( $post_type ) ? get_post_type_archive_link( $post_type ) : ''
			);
		} elseif ( is_author() ) {
			$author  = get_queried_object();
			$current = $this->item(
				$author instanceof \WP_User ? $author->display_name : '',
				get_author_posts_url( get_queried_object_id() )
			);
		} elseif ( is_search() ) {
			/* translators: %s: search query. */
			$current = $this->item( sprintf( __( 'Search results for "%s"', 'dovetail-studio' ), get_search_query( false ) ), get_search_link() );
		} elseif ( is_date() ) {
			$year  = (int) get_query_var( 'year' );
			$month = (int) get_query_var( 'monthnum' );
			$day   = (int) get_query_var( 'day' );

			$current = $this->item( (string) $year, get_year_link( $year ) );

			if ( $month && ( is_month() || is_day() ) ) {
				$items[] = $current;
				$current = $this->item(
					date_i18n( 'F', mktime( 0, 0, 0, $month, 1, $year ) ),
					get_month_link( $year, $month )
				);
			}

			if ( $day && is_day() ) {
				$items[] = $current;
				$current = $this->item( (string) $day, get_day_link( $year, $month, $day ) );
			}
		}

		if ( null === $current ) {
			return array();
		}

		$paged = (int) get_query_var( 'paged' );

		if ( $paged > 1 && ! is_singular() ) {
			$items[] = $current;
			/* translators: %d: page number. */
			$current = $this->item( sprintf( __( 'Page %d', 'dovetail-studio' ), $paged ), SchemaGraph::current_url() );
		} else {
			$current['url'] = SchemaGraph::current_url();
		}

		$items[] = $current;

		return $items;
	}

	/**
	 * Parent items of a singular post: blog page or post type archive, primary
	 * term ancestors, and published page ancestors for hierarchical types.
	 *
	 * @param \WP_Post $post Current post.
	 * @return array
	 */
	private function singular_parents( \WP_Post $post ) {
		$items = array();

		if ( 'page' !== $post->post_type ) {
			$items = $this->post_type_parent( $post->post_type );
			$term  = $this->primary_term( $post );

			if ( $term ) {
				$items   = array_merge( $items, $this->term_ancestors( $term ) );
				$items[] = $this->item( $term->name, get_term_link( $term ) );
			}
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				if ( 'publish' === get_post_status( $ancestor_id ) ) {
					$items[] = $this->item( get_the_title( $ancestor_id ), get_permalink( $ancestor_id ) );
				}
			}
		}

		return $items;
	}

	/**
	 * Blog page for posts, or the archive page for other post types.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	private function post_type_parent( $post_type ) {
		if ( 'post' === $post_type ) {
			$posts_page = (int) get_option( 'page_for_posts' );

			if ( $posts_page && 'page' === get_option( 'show_on_front' ) ) {
				return array( $this->item( get_the_title( $posts_page ), get_permalink( $posts_page ) ) );
			}

			return array();
		}

		$object  = get_post_type_object( $post_type );
		$archive = get_post_type_archive_link( $post_type );

		if ( $object && is_string( $archive ) && '' !== $archive ) {
			return array( $this->item( $object->labels->name, $archive ) );
		}

		return array();
	}

	/**
	 * Parent of a term archive: the blog page for post taxonomies, or the
	 * post type archive when the taxonomy belongs to one post type with archive.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	private function taxonomy_parent( $taxonomy ) {
		$object = get_taxonomy( $taxonomy );

		if ( ! $object || empty( $object->object_type ) ) {
			return array();
		}

		$post_types = (array) $object->object_type;

		if ( in_array( 'post', $post_types, true ) ) {
			return $this->post_type_parent( 'post' );
		}

		return 1 === count( $post_types ) ? $this->post_type_parent( reset( $post_types ) ) : array();
	}

	/**
	 * Primary term of a post: the deepest term of its main taxonomy.
	 *
	 * @param \WP_Post $post Post.
	 * @return \WP_Term|null
	 */
	private function primary_term( \WP_Post $post ) {
		$taxonomy = 'post' === $post->post_type ? 'category' : '';

		if ( '' === $taxonomy ) {
			foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $object ) {
				if ( $object->hierarchical && $object->public ) {
					$taxonomy = $object->name;

					break;
				}
			}
		}

		/**
		 * Filters the taxonomy used for the breadcrumb of a post.
		 *
		 * @param string   $taxonomy Taxonomy, or empty for none.
		 * @param \WP_Post $post     Post.
		 */
		$taxonomy = (string) apply_filters( 'dovetail_studio_breadcrumb_taxonomy', $taxonomy, $post );
		$terms    = '' !== $taxonomy ? get_the_terms( $post, $taxonomy ) : false;
		$primary  = null;
		$depth    = -1;

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_depth = count( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );

				if ( $term_depth > $depth ) {
					$primary = $term;
					$depth   = $term_depth;
				}
			}
		}

		/**
		 * Filters the primary term used for the breadcrumb of a post.
		 *
		 * @param \WP_Term|null $primary Primary term.
		 * @param \WP_Post      $post    Post.
		 */
		$primary = apply_filters( 'dovetail_studio_breadcrumb_primary_term', $primary, $post );

		return $primary instanceof \WP_Term ? $primary : null;
	}

	/**
	 * Ancestor items of a term, root first.
	 *
	 * @param \WP_Term $term Term.
	 * @return array
	 */
	private function term_ancestors( \WP_Term $term ) {
		$items = array();

		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );

			if ( $ancestor instanceof \WP_Term ) {
				$items[] = $this->item( $ancestor->name, get_term_link( $ancestor ) );
			}
		}

		return $items;
	}

	/**
	 * Home label from the settings, or the translated default.
	 *
	 * @return string
	 */
	private function home_label() {
		$options = Schema::get_options();
		$label   = isset( $options['breadcrumbs_home_label'] )
			? trim( (string) $options['breadcrumbs_home_label'] )
			: '';

		return '' !== $label ? $label : __( 'Home', 'dovetail-studio' );
	}

	/**
	 * Build one trail item.
	 *
	 * @param mixed $label Label.
	 * @param mixed $url   URL; a WP_Error (from get_term_link) becomes empty.
	 * @return array
	 */
	private function item( $label, $url ) {
		return array(
			'label' => is_scalar( $label ) ? (string) $label : '',
			'url'   => is_string( $url ) ? $url : '',
		);
	}

	/**
	 * Clean labels and URLs and drop items without a label.
	 *
	 * @param mixed $items Raw items.
	 * @return array<int, array{label: string, url: string}>
	 */
	private function normalize( $items ) {
		$clean = array();

		if ( ! is_array( $items ) ) {
			return $clean;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label = isset( $item['label'] ) && is_scalar( $item['label'] ) ? (string) $item['label'] : '';
			$label = trim(
				html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
			);
			$url   = isset( $item['url'] ) && is_string( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';

			if ( '' === $label ) {
				continue;
			}

			$clean[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $clean;
	}
}
