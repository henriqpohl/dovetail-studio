<?php
/** Schema SEO with Yoast SEO: Organization mode is enriched, Person mode keeps the Person and adds the business beside it. */
require __DIR__ . '/lib/harness.php';
define('WPSEO_VERSION', '25.0');

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_visitor();
boot_plugin();
$GLOBALS['__q'] = array('is_front_page' => 1);
$defaults = \DovetailStudio\SchemaSeo\Schema::get_options();
$clinic   = array_merge($defaults, array('name' => 'Clínica Sorriso', 'schema_type' => 'Dentist', 'telephone' => '+55 11 3456-7890', 'street_address' => 'Rua A, 100', 'address_locality' => 'São Paulo', 'address_country' => 'BR'));
$person   = array('@type' => array('Person', 'Organization'), '@id' => 'https://example.com/#/schema/person/abc', 'name' => 'Dra. Ana Lima');
$website  = array('@type' => 'WebSite', '@id' => 'https://example.com/#website', 'publisher' => array('@id' => $person['@id']));
$graph    = function ($g) { return apply_filters('wpseo_schema_graph', $g, null); };
$by_id    = function ($g, $id) { foreach ($g as $p) if (($p['@id'] ?? '') === $id) return $p; return null; };

// Person mode, nothing configured: the graph stays exactly as Yoast built it.
update_option('dovetail_schema_seo', $defaults);
$out = $graph(array($website, $person));
t('Person mode, no business: graph unchanged', $out === array($website, $person));

// Person mode with a business: the Person is untouched except worksFor, the business is a separate entity.
update_option('dovetail_schema_seo', $clinic);
$out = $graph(array($website, $person));
$p = $by_id($out, $person['@id']); $b = $by_id($out, 'https://example.com/#organization');
t('Person keeps its types and name', $p && $p['@type'] === array('Person', 'Organization') && $p['name'] === 'Dra. Ana Lima');
t('no business data on the Person', $p && !isset($p['address']) && !isset($p['telephone']) && !isset($p['openingHoursSpecification']));
t('Person worksFor the business', $p && ($p['worksFor']['@id'] ?? '') === 'https://example.com/#organization');
t('business is a separate Dentist with its own name and address', $b && in_array('Dentist', (array) $b['@type'], true) && $b['name'] === 'Clínica Sorriso' && ($b['address']['addressLocality'] ?? '') === 'São Paulo');
t('business founder is the Person', $b && ($b['founder'][0]['@id'] ?? '') === $person['@id']);
t('WebSite publisher stays the Person', $by_id($out, 'https://example.com/#website')['publisher']['@id'] === $person['@id']);

// Organization mode: Yoast's Organization is enriched in place, nothing is added.
$org = array('@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'Yoast Name');
$out = $graph(array($website, $org));
$o = $by_id($out, 'https://example.com/#organization');
t('Organization mode: one business entity, enriched', count($out) === 2 && in_array('Dentist', (array) $o['@type'], true) && ($o['telephone'] ?? '') === '+55 11 3456-7890');
t('Organization mode: Automatic keeps the Yoast name', $o['name'] === 'Yoast Name');

// No business entity in Yoast's graph and no name in the module: nothing without a name is added.
update_option('dovetail_schema_seo', $defaults);
$out = $graph(array(array('@type' => 'WebPage', '@id' => 'https://example.com/')));
t('no nameless Organization is added', count($out) === 1);

// The Organization filter never touches a Person.
update_option('dovetail_schema_seo', $clinic);
t('wpseo_schema_organization leaves a Person alone', apply_filters('wpseo_schema_organization', $person, null) === $person);
t('no standalone graph while Yoast prints its own', strpos(capture(function () { do_action('wp_head'); }), 'dovetail-schema-graph') === false);
