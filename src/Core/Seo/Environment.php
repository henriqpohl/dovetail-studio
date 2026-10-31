<?php
/**
 * Shared detection of the SEO and commerce plugins Dovetail Studio integrates with.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Environment detection shared by every schema-related module, so all modules
 * agree on whether Yoast SEO, WooCommerce, or Yoast WooCommerce SEO is active.
 */
final class Environment {
	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public static function has_yoast(): bool {
		return defined( 'WPSEO_VERSION' ) || function_exists( 'YoastSEO' ) || class_exists( 'WPSEO_Options' );
	}

	/**
	 * The other SEO plugin that publishes its own schema graph, if one is active.
	 *
	 * Dovetail Studio integrates with Yoast SEO only. With one of these active,
	 * that plugin owns the JSON-LD and Dovetail Studio prints no second graph.
	 *
	 * @return string Plugin name, or empty when none is active.
	 */
	public static function other_seo_plugin(): string {
		$plugins = array(
			'Rank Math'         => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ),
			'SEOPress'          => defined( 'SEOPRESS_VERSION' ),
			'All in One SEO'    => defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ),
			'The SEO Framework' => defined( 'THE_SEO_FRAMEWORK_VERSION' ),
		);

		$active = '';

		foreach ( $plugins as $name => $is_active ) {
			if ( $is_active ) {
				$active = $name;

				break;
			}
		}

		/**
		 * Filters the name of the other SEO plugin that owns the schema graph.
		 * Return an empty string to let Dovetail Studio print its graph anyway,
		 * for example when that plugin's schema output is switched off.
		 *
		 * @param string $active Plugin name, or empty.
		 */
		return (string) apply_filters( 'dovetail_studio_other_seo_plugin', $active );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
	}

	/**
	 * Whether the official Yoast WooCommerce SEO extension is active.
	 *
	 * @return bool
	 */
	public static function has_yoast_woocommerce(): bool {
		if ( defined( 'WPSEO_WOO_VERSION' ) || class_exists( 'Yoast_WooCommerce_SEO' ) ) {
			return true;
		}

		$active  = get_option( 'active_plugins', array() );
		$active  = is_array( $active ) ? $active : array();
		$network = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$network = is_array( $network ) ? $network : array();

		$known = array(
			'wpseo-woocommerce/wpseo-woocommerce.php',
			'yoast-woocommerce-seo/wpseo-woocommerce.php',
		);

		foreach ( $known as $plugin ) {
			if ( in_array( $plugin, $active, true ) || isset( $network[ $plugin ] ) ) {
				return true;
			}
		}

		return false;
	}
}
