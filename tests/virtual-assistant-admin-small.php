<?php
/** Small admin fixes: /config URL versioned by the saved settings, door step hint, AI Keys labels, modules directory label. */
require __DIR__ . '/lib/harness.php';

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
boot_plugin();
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '01 234 5678', 'COMPANY_EMAIL' => 'hello@example.com'));

$loader = function () { $GLOBALS['__inline'] = array(); do_action('wp_enqueue_scripts'); preg_match('~^window\.DT_VA_LOADER=(\{.*\});$~', $GLOBALS['__inline']['dt-va-loader'][0] ?? '', $m); return json_decode($m[1] ?? '{}', true); };
$before = $loader()['config'] ?? '';
t('config URL carries a version', (bool) preg_match('~/dt-va/v1/config\?v=[a-f0-9]{10}$~', $before), $before);
update_option('dt_va_flow_doors', array(array('id' => 'web', 'label' => 'A new website', 'action' => 'service', 'service' => 'web')));
$after = $loader()['config'] ?? '';
t('saving a setting changes the config URL (no 5-minute wait)', $after !== '' && $after !== $before);
$GLOBALS['wpdb']->query("INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('dt_va_evc_x', '1', 'no')");
update_option('dt_va_stats_2026-44', array('n' => array('open' => 5)));
update_option('dt_va_last_provider_result', array('at' => time()));
t('statistics and diagnostics do not change it', ($loader()['config'] ?? '') === $after);
$attrs = apply_filters('wp_inline_script_attributes', array('id' => 'dt-va-loader-js-before'), '');
t('loader settings are kept away from page optimisers', ($attrs['data-no-optimize'] ?? '') === '1' && ($attrs['data-cfasync'] ?? '') === 'false' && !isset(apply_filters('wp_inline_script_attributes', array('id' => 'other-js-before'), '')['data-no-optimize']));

$GLOBALS['__q']['admin'] = 1;
$_GET = array('page' => 'dt-va-settings', 'tab' => 'flow');
$flow = capture(function () { dt_va_settings_page(); });
t('door form explains the Service + 1 rule', strpos($flow, 'dt-va-door-step-hint') !== false && strpos($flow, 'This door opens the step %s.') !== false && strpos($flow, '&quot;steps&quot;:[') !== false && strpos($flow, '<script') === false);
$GLOBALS['__enq'] = array(); $_GET = array('page' => 'dt-va-settings', 'tab' => 'flow'); do_action('admin_enqueue_scripts', 'edit.php');
t('door hint script loads on the Doors tab', in_array('dt-va-admin-doors', $GLOBALS['__enq'], true) && in_array('dt-va-admin-json', $GLOBALS['__enq'], true));
$GLOBALS['__enq'] = array(); $_GET = array('page' => 'dt-va-settings', 'tab' => 'faq'); do_action('admin_enqueue_scripts', 'edit.php');
t('and only there', !in_array('dt-va-admin-doors', $GLOBALS['__enq'], true) && in_array('dt-va-admin-json', $GLOBALS['__enq'], true));
$_GET = array('page' => 'dt-va-settings', 'tab' => 'flow');
t('step form explains naming and order', strpos($flow, 'The order is set only by each step') !== false);
$_GET = array('page' => 'dt-va-settings', 'tab' => 'ai-keys');
$keys = capture(function () { dt_va_settings_page(); });
t('AI Keys: provider labels spelled right', strpos($keys, '>OpenAI<') !== false && strpos($keys, 'Openai') === false && strpos($keys, 'Primary provider') !== false && strpos($keys, 'Secondary provider') !== false);

define('DOVETAIL_STUDIO_MODULES_DIR', WP_CONTENT_DIR . '/custom-modules');
t('modules directory shown as configured', \DovetailStudio\Core\Loader::external_modules_dir_label() === 'wp-content/custom-modules', \DovetailStudio\Core\Loader::external_modules_dir_label());
$o = capture(function () { \DovetailStudio\Core\Loader::instance()->render_modules_page(); });
$row = array('slug' => 'x', 'name' => 'Ex "1"', 'description' => '', 'version' => '1', 'enabled' => false, 'uploaded' => true, 'open_url' => '', 'remove_url' => 'https://example.com/rm');
$m = \DovetailStudio\Core\View::capture(DOVETAIL_STUDIO_PATH . 'src/Core', 'modules', array('version' => '1', 'notice' => null, 'can_upload' => false, 'modules_dir' => '', 'modules' => array($row)));
t('module removal asks through the shared script, not inline JS', strpos($m, 'onclick') === false && strpos($m, 'data-dovetail-confirm="Remove Ex &quot;1&quot;? The module files') !== false);
t('upload card names the real directory', strpos($o, '<code>wp-content/custom-modules</code>') !== false && strpos($o, 'wp-content/dovetail-modules') === false);
