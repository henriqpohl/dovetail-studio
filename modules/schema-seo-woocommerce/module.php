<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'          => 'schema-seo-woocommerce',
	'name'          => 'Schema SEO WooCommerce',
	'description'   => 'WooCommerce Product in the site graph, merchant data enrichment, breadcrumbs and technical SEO, with Yoast SEO integration.',
	'version'       => '1.3.0',
	'bootstrap'     => __DIR__ . '/src/Module.php',
	'class'         => 'DovetailStudio\\SchemaSeoWooCommerce\\Module',
	'admin_url'     => 'admin.php?page=dovetail-schema-seo-woocommerce',
	// Screens styled by the Studio and kept free of WordPress and third-party notices.
	'admin_screens' => array( 'pages' => array( 'dovetail-schema-seo-woocommerce' ) ),
);
