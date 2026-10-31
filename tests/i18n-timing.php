<?php
/** No text is translated before `init`: WordPress 6.7+ warns ("_load_textdomain_just_in_time was called incorrectly") when a plugin does. */
require __DIR__ . '/lib/harness.php';

class WooCommerce {}   // WooCommerce "active", so its module runs its include-time code too

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
$GLOBALS['__q']['admin'] = 1;
$GLOBALS['__early_i18n'] = array();
require DT_PLUGIN_MAIN;
do_action('plugins_loaded');
$early = array_values(array_unique($GLOBALS['__early_i18n'] ?? array()));
t('nothing translated before init', !$early, implode(' | ', array_slice($early, 0, 6)));
