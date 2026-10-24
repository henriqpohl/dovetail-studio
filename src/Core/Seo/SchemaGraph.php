<?php
/**
 * Single JSON-LD graph shared by every Dovetail Studio schema module.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Collects schema pieces from every module and prints them as one `@graph`
 * when Yoast SEO does not provide the graph for the current request.
 *
 * Modules never print their own JSON-LD. With Yoast SEO they add or enrich
 * pieces through Yoast's `wpseo_schema_graph` filter; without Yoast they add
 * pieces through the `dovetail_studio_schema_graph` filter handled here.
 */
final class SchemaGraph {

	/** Filter modules use to add pieces to the standalone graph. */
	const FILTER = 'dovetail_studio_schema_graph';

	/** Shared instance. */
	private static $instance = null;

	/** Whether Yoast built a schema graph during this request. */
	private $yoast_graph_seen = false;

	/** Whether Yoast's final JSON-LD output is enabled. Null means unknown. */
	private $yoast_output_enabled = null;

	/** Whether the standalone graph was already printed. */
	private $rendered = false;

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

	/**
	 * Registers the frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpseo_schema_graph', array( $this, 'track_yoast_graph' ), PHP_INT_MAX, 1 );
		add_filter( 'wpseo_json_ld_output', array( $this, 'track_yoast_output' ), PHP_INT_MAX, 1 );
		add_action( 'wp_head', array( $this, 'render' ), 99 );
	}

	/**
	 * Records that Yoast built a graph for this request.
	 *
	 * @param mixed $graph Yoast graph.
	 * @return mixed Unchanged graph.
	 */
	public function track_yoast_graph( $graph ) {
		if ( is_array( $graph ) ) {
			$this->yoast_graph_seen = true;
		}

		return $graph;
	}

	/**
	 * Records whether Yoast's final JSON-LD output was disabled.
	 *
	 * @param mixed $output Yoast output control value.
	 * @return mixed Unchanged value.
	 */
	public function track_yoast_output( $output ) {
		$this->yoast_output_enabled = false !== $output && array() !== $output;

		return $output;
	}

	/**
	 * Whether Yoast SEO owns the schema graph of the current request.
	 *
	 * Before `wp_head` runs the answer is a prediction based on Yoast being
	 * active; from `wp_head` on it reflects whether Yoast actually built and
	 * printed its graph.
	 *
	 * @return bool
	 */
	public function yoast_handles_graph(): bool {
		$handles = Environment::has_yoast() && false !== $this->yoast_output_enabled;

		if ( $handles && did_action( 'wp_head' ) ) {
			$handles = $this->yoast_graph_seen;
		}

		/**
		 * Filters whether Yoast SEO owns the schema graph of the current request.
		 *
		 * @param bool $handles Whether Yoast owns the graph.
		 */
		return (bool) apply_filters( 'dovetail_studio_yoast_handles_graph', $handles );
	}

	/**
	 * Prints the standalone graph when Yoast does not provide one.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( $this->rendered || is_admin() || $this->yoast_handles_graph() ) {
			return;
		}

		$this->rendered = true;
		$pieces         = $this->get_pieces();

		if ( array() === $pieces ) {
			return;
		}

		self::normalize_json_float_precision();

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $pieces,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		);

		if ( ! is_string( $json ) || '' === $json ) {
			return;
		}

		echo "\n<script type=\"application/ld+json\" class=\"dovetail-schema-graph local-business-schema\">";
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is encoded with JSON_HEX_TAG.
		echo "</script>\n";
	}

	/**
	 * Returns the valid, de-duplicated pieces of the standalone graph.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_pieces(): array {
		/**
		 * Filters the pieces of the standalone graph printed when Yoast SEO
		 * does not provide the graph.
		 *
		 * @param array<int, array<string, mixed>> $pieces Schema pieces.
		 */
		$pieces = apply_filters( self::FILTER, array() );
		$pieces = is_array( $pieces ) ? $pieces : array();
		$clean  = array();
		$ids    = array();

		foreach ( $pieces as $piece ) {
			if ( ! is_array( $piece ) || empty( $piece['@type'] ) ) {
				continue;
			}

			$id = isset( $piece['@id'] ) && is_string( $piece['@id'] ) ? $piece['@id'] : '';

			if ( '' !== $id && isset( $ids[ $id ] ) ) {
				continue;
			}

			if ( '' !== $id ) {
				$ids[ $id ] = true;
			}

			$clean[] = $piece;
		}

		return $clean;
	}

	/**
	 * Whether a list of pieces contains a piece with the given `@id`.
	 *
	 * @param array<int, mixed> $pieces Schema pieces.
	 * @param string            $id     Piece identifier.
	 * @return bool
	 */
	public static function has_piece( array $pieces, string $id ): bool {
		foreach ( $pieces as $piece ) {
			if ( is_array( $piece ) && isset( $piece['@id'] ) && $id === $piece['@id'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds a stable `@id` following the conventions used by Yoast SEO.
	 *
	 * Supported keys: `organization`, `website`, `webpage`, `breadcrumb`,
	 * `primaryimage`, `article`; any other key becomes a fragment of the
	 * current page URL.
	 *
	 * @param string $key Piece key.
	 * @return string
	 */
	public static function id( string $key ): string {
		$home = untrailingslashit( home_url( '/' ) );

		switch ( $key ) {
			case 'organization':
				return $home . '/#organization';
			case 'website':
				return $home . '/#website';
			case 'webpage':
				return self::current_url();
			default:
				return self::current_url() . '#' . sanitize_key( $key );
		}
	}

	/**
	 * Returns the canonical URL of the current request, without query arguments.
	 *
	 * @return string
	 */
	public static function current_url(): string {
		$url = '';

		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_singular() ) {
			$post_id = get_queried_object_id();
			$url     = (string) wp_get_canonical_url( $post_id );
			$url     = '' !== $url ? $url : (string) get_permalink( $post_id );
		} elseif ( is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$url        = $posts_page ? (string) get_permalink( $posts_page ) : home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$link = $term instanceof \WP_Term ? get_term_link( $term ) : '';
			$url  = is_string( $link ) ? $link : '';
		} elseif ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$link      = is_string( $post_type ) ? get_post_type_archive_link( $post_type ) : '';
			$url       = is_string( $link ) ? $link : '';
		} elseif ( is_author() ) {
			$url = (string) get_author_posts_url( get_queried_object_id() );
		} elseif ( is_search() ) {
			$url = (string) get_search_link();
		} elseif ( is_day() ) {
			$url = (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
		} elseif ( is_month() ) {
			$url = (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
		} elseif ( is_year() ) {
			$url = (string) get_year_link( (int) get_query_var( 'year' ) );
		}

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$paged = (int) get_query_var( 'paged' );

		if ( $paged > 1 && ! is_singular() ) {
			$url = self::paged_url( $url, $paged );
		}

		/**
		 * Filters the canonical URL used for the current page `@id`.
		 *
		 * @param string $url Current page URL.
		 */
		return (string) apply_filters( 'dovetail_studio_schema_current_url', $url );
	}

	/**
	 * Prevents PHP float artifacts from leaking into JSON-LD numbers.
	 *
	 * @return void
	 */
	public static function normalize_json_float_precision(): void {
		if ( '-1' !== (string) ini_get( 'serialize_precision' ) ) {
			@ini_set( 'serialize_precision', '-1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Some hosts disable runtime INI changes.
		}
	}

	/**
	 * Adds the pagination segment to an archive URL.
	 *
	 * @param string $url   Archive URL.
	 * @param int    $paged Page number.
	 * @return string
	 */
	private static function paged_url( string $url, int $paged ): string {
		global $wp_rewrite;

		if ( $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
			return trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
		}

		return add_query_arg( 'paged', $paged, $url );
	}
}
