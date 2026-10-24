<?php

defined( 'ABSPATH' ) || exit;

return array(
    'slug'        => 'schema-seo',
    'name'        => 'Schema SEO',
    'description' => 'Organization, WebSite, WebPage, Article and breadcrumb structured data that enriches Yoast SEO when active and builds a single standalone graph when it is not.',
    'version'     => '1.5.0',
    'bootstrap'   => __DIR__ . '/src/Module.php',
    'class'       => 'DovetailStudio\\SchemaSeo\\Module',
    'admin_url'   => 'admin.php?page=local-business-schema',
);
