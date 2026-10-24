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
