<?php
/** Schema SEO WooCommerce module bootstrap. @package DovetailStudio\SchemaSeoWooCommerce */
namespace DovetailStudio\SchemaSeoWooCommerce;

defined( 'ABSPATH' ) || exit;

final class Module {
	private static $instance = null;
	private $booted          = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// The version comes from module.php only, so it can never drift from the manifest.
		$manifest = require dirname( __DIR__ ) . '/module.php';
		defined( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION' ) || define(
			'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION',
			(string) $manifest['version']
		);
		defined( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_PATH' ) || define(
			'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_PATH',
			trailingslashit( dirname( __DIR__ ) )
		);
		defined( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_URL' ) || define(
			'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_URL',
			\DovetailStudio\Core\Loader::path_url( dirname( __DIR__ ) )
		);

		require_once __DIR__ . '/functions.php';
	}
}
