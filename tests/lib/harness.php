<?php
/** Shared helpers for the Dovetail Studio tests. */
require __DIR__ . '/wp-sim.php';

$GLOBALS['__R'] = array('ok' => 0, 'fail' => 0);

/** Record one check. */
function t($name, $cond, $detail = '') {
    if ($cond) { $GLOBALS['__R']['ok']++; return; }
    $GLOBALS['__R']['fail']++;
    echo "FAIL: $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}
/** Call a registered REST route. */
function req($route, $params = array(), $body = '', $files = array()) {
    $r = $GLOBALS['__routes'][$route] ?? null;
    if (!$r) throw new Exception("route $route not registered");
    return call_user_func($r['callback'], new WP_REST_Request($params, $body, $files));
}
function as_admin() { $GLOBALS['__user'] = array('id' => 1, 'caps' => array('manage_options', 'install_plugins', 'read')); }
function as_visitor($ip = '203.0.113.9') { $GLOBALS['__user'] = array('id' => 0, 'caps' => array()); $_SERVER = array('REMOTE_ADDR' => $ip, 'HTTP_ORIGIN' => 'https://example.com'); }
/** Load the plugin the way WordPress does and run the early hooks. */
function boot_plugin() {
    require DT_PLUGIN_MAIN;
    do_action('plugins_loaded');
    do_action('init');
    do_action('rest_api_init');
}
function capture($cb) { ob_start(); try { $cb(); } finally { $o = ob_get_clean(); } return $o; }
/** A visitor token issued a little while ago (submit refuses tokens younger than 3 s). */
function aged_token($ip = '203.0.113.9', $age = 10) {
    $ts = time() - $age;
    return $ts . '.' . substr(hash_hmac('sha256', $ts . '|' . $ip, wp_salt('nonce') . '|dt-va-token'), 0, 32);
}
// Report last, after any shutdown checks a test registers itself.
register_shutdown_function(function () { register_shutdown_function('dt_test_report'); });
function dt_test_report() {
    $w = array_values(array_unique($GLOBALS['__warnings']));
    foreach ($w as $x) { echo "FAIL: PHP warning — $x\n"; $GLOBALS['__R']['fail']++; }
    echo 'RESULT ' . json_encode($GLOBALS['__R']) . "\n";
}
