<?php
/** Schema SEO and Schema SEO WooCommerce: the single graph, Organization/LocalBusiness rules, ProductGroup. */
require __DIR__ . '/lib/harness.php';

class WooCommerce {}   // WooCommerce "active"

/** Minimal WooCommerce products for the ProductGroup checks. */
class Fake_Product {
    public $d;
    function __construct($d) { $this->d = $d; }
    function is_type($t) { return $this->d['type'] === $t; }
    function get_id() { return $this->d['id']; }
    function get_sku() { return $this->d['sku'] ?? ''; }
    function get_name() { return $this->d['name']; }
    function get_permalink() { return 'https://example.com/p/' . $this->d['id'] . '/'; }
    function get_children() { return $this->d['children'] ?? array(); }
    function get_variation_attributes() { return $this->d['vattrs'] ?? array(); }
    function get_attribute($a) { return $this->d['attrs'][$a] ?? ''; }
    function get_meta($k, $single = true) { return $this->d['meta'][$k] ?? ''; }
    function get_parent_id() { return $this->d['parent'] ?? 0; }
    function is_purchasable() { return true; }
    function get_price() { return $this->d['price'] ?? ''; }
    function get_image_id() { return 0; }
    function get_stock_status() { return $this->d['stock'] ?? 'instock'; }
}
$GLOBALS['__products'] = array(
    10 => new Fake_Product(array('id' => 10, 'type' => 'variable', 'name' => 'Shirt', 'sku' => 'SHIRT', 'children' => array(11, 12), 'vattrs' => array('pa_size' => array('s', 'm')))),
    11 => new Fake_Product(array('id' => 11, 'type' => 'variation', 'name' => 'Shirt - S', 'sku' => 'SHIRT-S', 'price' => '20', 'attrs' => array('pa_size' => 'S'), 'meta' => array('_gtin' => '4006381333931'), 'parent' => 10)),
    12 => new Fake_Product(array('id' => 12, 'type' => 'variation', 'name' => 'Shirt - M', 'sku' => 'SHIRT-M', 'price' => '22', 'attrs' => array('pa_size' => 'M'), 'stock' => 'outofstock', 'parent' => 10)),
);
function wc_get_product($id) { return $GLOBALS['__products'][$id] ?? null; }
function get_woocommerce_currency() { return 'EUR'; }
function wc_format_decimal($v, $d = 2) { return number_format((float) $v, $d, '.', ''); }
function wc_get_price_decimals() { return 2; }
class Fake_Countries { function get_base_country() { return 'BR'; } }
function WC() { static $wc = null; if (!$wc) { $wc = new stdClass(); $wc->countries = new Fake_Countries(); } return $wc; }

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_visitor();
boot_plugin();
$render = function () {
    $sg = \DovetailStudio\Core\Seo\SchemaGraph::instance();
    $r = new ReflectionProperty($sg, 'rendered'); $r->setAccessible(true); $r->setValue($sg, false);
    $o = capture(function () { do_action('wp_head'); });
    preg_match('~<script type="application/ld\+json"[^>]*>(.*?)</script>~s', $o, $m);
    $g = json_decode($m[1] ?? '', true);
    return array($o, $g['@graph'] ?? array());
};
$types = function ($graph) { return array_map(function ($p) { return is_array($p['@type']) ? implode('+', $p['@type']) : $p['@type']; }, $graph); };

$GLOBALS['__q'] = array('is_front_page' => 1);
list($o, $graph) = $render();
t('one JSON-LD graph', substr_count($o, 'application/ld+json') === 1);
t('no business entity before a name is set', !in_array('Organization+LocalBusiness', $types($graph), true) && !in_array('Organization', $types($graph), true), implode(', ', $types($graph)));

$opts = \DovetailStudio\SchemaSeo\Schema::get_options();
update_option('dovetail_schema_seo', array_merge($opts, array('name' => 'Example Co', 'schema_type' => 'LocalBusiness')));
list($o, $graph) = $render();
t('LocalBusiness without an address is printed as Organization', in_array('Organization', $types($graph), true) && !in_array('Organization+LocalBusiness', $types($graph), true), implode(', ', $types($graph)));
t('settings screen lists what is missing', in_array(__('Address', 'dovetail-studio'), (new \DovetailStudio\SchemaSeo\Schema())->missing_required_fields(), true));

update_option('dovetail_schema_seo', array_merge($opts, array('name' => 'Example Co', 'schema_type' => 'LocalBusiness', 'street_address' => '1 Main St', 'address_locality' => 'Dublin', 'address_country' => 'IE')));
list($o, $graph) = $render();
t('LocalBusiness with an address', in_array('Organization+LocalBusiness', $types($graph), true), implode(', ', $types($graph)));
t('nothing missing once name and address are set', (new \DovetailStudio\SchemaSeo\Schema())->missing_required_fields() === array());
t('site graph pieces present', in_array('WebSite', $types($graph), true) && in_array('WebPage', $types($graph), true));

$GLOBALS['__posts'][5] = new WP_Post(array('ID' => 5, 'post_type' => 'post', 'post_title' => 'Hello', 'post_content' => 'Body.'));
$GLOBALS['__q'] = array('is_singular' => 1, 'id' => 5);
list($o, $graph) = $render();
t('posts get Article and the author Person', in_array('Article', $types($graph), true) && in_array('Person', $types($graph), true));

// ---------------------------------------------------------------- WooCommerce ProductGroup
$node = dovetail_schema_seo_woocommerce_enrich_product_node(array('@type' => 'Product', 'name' => 'Shirt', 'sku' => 'SHIRT', 'offers' => array(array('@type' => 'AggregateOffer', 'lowPrice' => '20', 'highPrice' => '22', 'priceCurrency' => 'EUR', 'priceValidUntil' => '2027-12-31'))), $GLOBALS['__products'][10]);
t('variable product becomes a ProductGroup', $node['@type'] === 'ProductGroup' && $node['productGroupID'] === 'SHIRT');
t('no aggregate offer on the group', !isset($node['offers']));
t('one Product per variant', count($node['hasVariant'] ?? array()) === 2 && $node['hasVariant'][0]['@type'] === 'Product');
$v = $node['hasVariant'][0];
t('variant: sku, size, gtin13, group id', $v['sku'] === 'SHIRT-S' && $v['size'] === 'S' && ($v['gtin13'] ?? '') === '4006381333931' && $v['inProductGroupWithID'] === 'SHIRT');
t('variant offer: price, currency, stock, shared validity', $v['offers']['price'] === '20.00' && $v['offers']['priceCurrency'] === 'EUR' && $v['offers']['availability'] === 'https://schema.org/InStock' && $v['offers']['priceValidUntil'] === '2027-12-31');
t('out of stock variant', $node['hasVariant'][1]['offers']['availability'] === 'https://schema.org/OutOfStock');
t('variesBy size', in_array('https://schema.org/size', $node['variesBy'] ?? array(), true));
$simple = dovetail_schema_seo_woocommerce_enrich_product_node(array('@type' => 'Product', 'name' => 'Mug', 'offers' => array('@type' => 'Offer', 'price' => '9')), new Fake_Product(array('id' => 20, 'type' => 'simple', 'name' => 'Mug')));
t('simple product stays a Product', $simple['@type'] === 'Product' && isset($simple['offers']));

// ---------------------------------------------------------------- Return policy and shipping
update_option('dovetail_schema_seo_woocommerce_settings', array_merge(dovetail_schema_seo_woocommerce_defaults(), array('merchant_returns_enabled' => 1, 'shipping_enabled' => 1, 'shipping_rate' => '0', 'transit_min' => 2, 'transit_max' => 4)));
$policy = dovetail_schema_seo_woocommerce_return_policy();
t('return policy uses the store country when none is set', ($policy['applicableCountry'] ?? '') === 'BR' && ($policy['returnPolicyCountry'] ?? '') === 'BR');
$simple = dovetail_schema_seo_woocommerce_enrich_product_node(array('@type' => 'Product', 'name' => 'Mug', 'offers' => array('@type' => 'Offer', 'price' => '9')), new Fake_Product(array('id' => 20, 'type' => 'simple', 'name' => 'Mug')));
$ship = $simple['offers']['shippingDetails'] ?? array();
t('offer gets shipping details', ($ship['@type'] ?? '') === 'OfferShippingDetails' && $ship['shippingRate']['value'] === 0.0 && $ship['shippingRate']['currency'] === 'EUR' && $ship['shippingDestination']['addressCountry'] === 'BR' && $ship['deliveryTime']['transitTime']['maxValue'] === 4);
t('offer gets the return policy', ($simple['offers']['hasMerchantReturnPolicy']['merchantReturnDays'] ?? 0) === 14);
$group = dovetail_schema_seo_woocommerce_enrich_product_node(array('@type' => 'Product', 'name' => 'Shirt', 'sku' => 'SHIRT', 'offers' => array(array('@type' => 'AggregateOffer', 'lowPrice' => '20'))), $GLOBALS['__products'][10]);
t('variant offers get shipping and returns', isset($group['hasVariant'][0]['offers']['shippingDetails'], $group['hasVariant'][0]['offers']['hasMerchantReturnPolicy']));
$GLOBALS['__q'] = array('is_front_page' => 1);
list($o, $graph) = $render();
$org = null; foreach ($graph as $p) if (($p['@id'] ?? '') === 'https://example.com/#organization') $org = $p;
t('Organization carries the store return policy', $org && ($org['hasMerchantReturnPolicy']['applicableCountry'] ?? '') === 'BR');
t('sanitize: bad country and negative rate are dropped', ($c = dovetail_schema_seo_woocommerce_sanitize_settings(array('return_country' => 'Brazil', 'shipping_rate' => '-3', 'transit_min' => 9, 'transit_max' => 2))) && $c['return_country'] === '' && $c['shipping_rate'] === '' && $c['transit_min'] === 2 && $c['transit_max'] === 9);
update_option('dovetail_schema_seo_woocommerce_settings', dovetail_schema_seo_woocommerce_defaults());

// ---------------------------------------------------------------- Person mode (no Yoast)
$reset_crumbs = function () { $b = \DovetailStudio\SchemaSeo\Breadcrumbs::instance(); $r = new ReflectionProperty($b, 'items'); $r->setAccessible(true); $r->setValue($b, null); };
$opts = \DovetailStudio\SchemaSeo\Schema::get_options();
update_option('dovetail_schema_seo', array_merge($opts, array('entity' => 'person', 'person_name' => 'Ana Lima', 'person_job_title' => 'Photographer', 'person_image' => 'https://example.com/ana.jpg', 'person_same_as' => "https://www.linkedin.com/in/ana\nnot a url", 'name' => '')));
$GLOBALS['__q'] = array('is_front_page' => 1);
list($o, $graph) = $render();
$find = function ($graph, $id) { foreach ($graph as $p) if (($p['@id'] ?? '') === $id) return $p; return null; };
$person = $find($graph, 'https://example.com/#/schema/person/site');
t('Person mode: the site Person is printed', $person && in_array('Person', (array) $person['@type'], true) && $person['name'] === 'Ana Lima' && $person['jobTitle'] === 'Photographer');
t('Person mode: photo and logo, valid profiles only', $person && $person['image']['url'] === 'https://example.com/ana.jpg' && $person['sameAs'] === array('https://www.linkedin.com/in/ana'));
t('Person mode: no business without a business name', !$find($graph, 'https://example.com/#organization'));
$site = $find($graph, 'https://example.com/#website');
t('Person mode: the Person publishes the site', ($site['publisher']['@id'] ?? '') === 'https://example.com/#/schema/person/site');
t('no SearchAction on the WebSite', !isset($site['potentialAction']));
t('settings: Person mode needs only the person name', (new \DovetailStudio\SchemaSeo\Schema())->missing_required_fields() === array());

update_option('dovetail_schema_seo', array_merge(get_option('dovetail_schema_seo'), array('name' => 'Ana Lima Fotografia', 'schema_type' => 'Organization')));
list($o, $graph) = $render();
$person = $find($graph, 'https://example.com/#/schema/person/site'); $biz = $find($graph, 'https://example.com/#organization');
t('Person mode + business: linked both ways', $person && $biz && ($person['worksFor']['@id'] ?? '') === $biz['@id'] && ($biz['founder'][0]['@id'] ?? '') === $person['@id']);
t('Person mode + business: the Person still publishes the site', ($find($graph, 'https://example.com/#website')['publisher']['@id'] ?? '') === $person['@id']);

// ---------------------------------------------------------------- Breadcrumb parents without a URL
update_option('dovetail_schema_seo', array_merge($opts, array('name' => 'Example Co', 'schema_type' => 'Organization')));
$GLOBALS['__q'] = array('is_singular' => 1, 'id' => 5);
add_filter('dovetail_studio_breadcrumb_items', function ($items) { array_splice($items, 1, 0, array(array('label' => 'No link', 'url' => ''))); return $items; });
$reset_crumbs();
list($o, $graph) = $render();
$crumbs = null; foreach ($graph as $p) if (($p['@type'] ?? '') === 'BreadcrumbList') $crumbs = $p['itemListElement'];
$names = $crumbs ? array_column($crumbs, 'name') : array();
t('a parent without a URL is left out of BreadcrumbList', $crumbs && !in_array('No link', $names, true) && count($crumbs) >= 2);
t('every item but the last has a URL', $crumbs && count(array_filter(array_slice($crumbs, 0, -1), function ($c) { return empty($c['item']); })) === 0);
t('the visible trail still shows it', in_array('No link', array_column(dovetail_studio_get_breadcrumbs(), 'label'), true));
