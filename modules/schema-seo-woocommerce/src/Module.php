<?php
/** Schema SEO WooCommerce module bootstrap. @package DovetailStudio\SchemaSeoWooCommerce */
namespace DovetailStudio\SchemaSeoWooCommerce;

defined( 'ABSPATH' ) || exit;

final class Module {
    private static $instance = null;
    private $booted = false;

    public static function instance(): self {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    public function boot(): void {
        if ( $this->booted ) { return; }
        $this->booted = true;

        defined( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION' ) || define( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION', '1.1.0' );
        defined( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_PATH' ) || define( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_PATH', trailingslashit( dirname( __DIR__ ) ) );
        defined( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_URL' ) || define( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_URL', DOVETAIL_STUDIO_URL . 'modules/schema-seo-woocommerce/' );

        require_once __DIR__ . '/functions.php';
    }
}
