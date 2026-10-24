<?php

defined( 'ABSPATH' ) || exit;

return array(
    'slug'         => 'schema-seo-woocommerce',
    'name'         => 'Schema SEO WooCommerce',
    'description'  => 'Product structured data and merchant SEO enhancements for WooCommerce, with Yoast SEO integration.',
    'version'      => '1.1.0',
    'bootstrap'    => __DIR__ . '/src/Module.php',
    'class'        => 'DovetailStudio\\SchemaSeoWooCommerce\\Module',
    'admin_url'    => 'admin.php?page=dovetail-schema-seo-woocommerce',
);
