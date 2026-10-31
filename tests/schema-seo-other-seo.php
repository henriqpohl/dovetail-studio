<?php
/** With Rank Math (or SEOPress, All in One SEO, The SEO Framework) Dovetail Studio prints no second schema graph. */
require __DIR__ . '/lib/harness.php';
define('RANK_MATH_VERSION', '1.0.250');
class WooCommerce {}

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_visitor();
boot_plugin();
$GLOBALS['__q'] = array('is_front_page' => 1);
update_option('dovetail_schema_seo', array_merge(\DovetailStudio\SchemaSeo\Schema::get_options(), array('name' => 'Example Co', 'schema_type' => 'Organization')));

t('Rank Math detected', \DovetailStudio\Core\Seo\Environment::other_seo_plugin() === 'Rank Math');
t('no Dovetail JSON-LD next to Rank Math', strpos(capture(function () { do_action('wp_head'); }), 'application/ld+json') === false);
t('WooCommerce keeps its own product JSON-LD', apply_filters('woocommerce_structured_data_product', array('@type' => 'Product', 'name' => 'Mug'), null) !== array());
t('WooCommerce breadcrumb JSON-LD is left alone', apply_filters('woocommerce_structured_data_breadcrumblist', array('@type' => 'BreadcrumbList')) !== array());
add_filter('dovetail_studio_other_seo_plugin', '__return_empty_string');
t('filter lets the graph print when that plugin\'s schema is off', strpos(capture(function () { $sg = \DovetailStudio\Core\Seo\SchemaGraph::instance(); $r = new ReflectionProperty($sg, 'rendered'); $r->setAccessible(true); $r->setValue($sg, false); do_action('wp_head'); }), 'dovetail-schema-graph') !== false);
