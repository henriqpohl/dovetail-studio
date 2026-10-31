<?php
/** The "assistant not shown" notice names the missing required fields, and disappears in the same request they are saved. */
require __DIR__ . '/lib/harness.php';

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
boot_plugin();
$GLOBALS['__q']['admin'] = 1;
$notice = function () { return capture(function () { do_action('dovetail_studio_notices'); }); };

$n = $notice();
t('notice when phone and email are empty', strpos($n, 'not shown on the site yet') !== false);
t('notice names both required fields', strpos($n, 'Phone — display') !== false && strpos($n, 'Public email') !== false);
t('link goes to the first missing field', strpos($n, 'tab=company#dt-va-public-COMPANY_PHONE_DISPLAY-row') !== false);

update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '01 234 5678'));
$n = $notice();
t('only the field still missing is named', strpos($n, 'Public email') !== false && strpos($n, 'Phone — display') === false);

$GLOBALS['__screen'] = (object) array('id' => 'plugins', 'base' => 'plugins', 'post_type' => '');
t('also on the Plugins screen', strpos(capture(function () { do_action('admin_notices'); }), 'not shown on the site yet') !== false);
$GLOBALS['__screen'] = (object) array('id' => 'edit-post', 'base' => 'edit', 'post_type' => 'post');
t('not on other WordPress screens', strpos(capture(function () { do_action('admin_notices'); }), 'not shown on the site yet') === false);

// Saving the missing field: the notice is gone on the page that shows the save result.
$_GET = array('page' => 'dt-va-settings', 'tab' => 'company');
$_POST = $_REQUEST = array('dt_va_public' => '1', '_wpnonce' => wp_create_nonce('dt_va_public'), 'public' => array('COMPANY_PHONE_DISPLAY' => '01 234 5678', 'COMPANY_EMAIL' => 'hello@example.com'));
do_action('admin_init');
t('saved on admin_init', dt_va_public_value('COMPANY_EMAIL') === 'hello@example.com');
t('no notice once the fields are filled in', strpos($notice(), 'not shown on the site yet') === false);
$form = capture('dt_va_public_settings');
t('save result shown in the form', strpos($form, 'Public settings saved.') !== false);
t('required fields marked in the form', substr_count($form, 'class="dt-va-required"') === 3 && strpos($form, 'class="dt-va-missing"') === false);
