<?php

defined( 'ABSPATH' ) || exit;

return array(
    'slug'        => 'schema-seo',
    'name'        => 'Schema SEO',
    'description' => 'Local Business structured data with Yoast SEO integration and native JSON-LD output when Yoast SEO is unavailable.',
    'version'     => '1.4.2',
    'bootstrap'   => __DIR__ . '/src/Module.php',
    'class'       => 'DovetailStudio\\SchemaSeo\\Module',
    'admin_url'   => 'admin.php?page=local-business-schema',
);
