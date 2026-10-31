<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'          => 'schema-seo',
	'name'          => 'Schema SEO',
	'description'   => 'Organization, WebSite, WebPage, Article and breadcrumb structured data that enriches Yoast SEO when active and builds a single standalone graph when it is not.',
	'version'       => '1.6.1',
	'bootstrap'     => __DIR__ . '/src/Module.php',
	'class'         => 'DovetailStudio\\SchemaSeo\\Module',
	'admin_url'     => 'admin.php?page=dovetail-schema-seo',
	// Screens styled by the Studio and kept free of WordPress and third-party notices.
	'admin_screens' => array( 'pages' => array( 'dovetail-schema-seo' ) ),
);
