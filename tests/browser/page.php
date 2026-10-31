<?php
/** Builds the test page for tests/browser/run.js: the real wp_head/wp_footer output plus the /config response. */
require dirname(__DIR__) . '/lib/harness.php';
if (getenv('DT_LOCALE') === 'pt_BR') {
    $GLOBALS['__locale'] = 'pt_BR';
    $GLOBALS['__tr'] = array('Hello. I\'m the AI Assistant at %s.' => 'Olá. Sou o assistente de IA da %s.', 'What are you looking at?' => 'O que você procura?', 'Have a question?' => 'Tem uma pergunta?');
}
as_visitor();
boot_plugin();
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '01 234 5678', 'COMPANY_PHONE_TEL' => '+35312345678', 'COMPANY_EMAIL' => 'hello@example.com', 'OPENING_HOURS' => array('open' => 0.0, 'close' => 24.0, 'days' => array(0, 1, 2, 3, 4, 5, 6))));
dt_va_save_key('openai', 'sk');
update_option('dt_va_providers', array('primary' => 'openai', 'secondary' => 'none', 'openai_model' => 'm'));
$head = capture(function () { do_action('wp_enqueue_scripts'); do_action('wp_head'); });
$foot = capture(function () { do_action('wp_footer'); });
$va = WP_CONTENT_DIR . '/plugins/dovetail-studio/modules/virtual-assistant/';
$GLOBALS['__R'] = array('ok' => 0, 'fail' => 0);
ob_start();
echo json_encode(array(
    'html' => "<!doctype html><html><head><meta charset=utf-8>$head<link rel=stylesheet href='https://example.com/front-tab.css'></head><body><h1>Test page</h1>$foot<script src='https://example.com/front-loader.js'></script></body></html>",
    'config' => req('/dt-va/v1/config')->data,
    'js' => $va . 'assets/js/' . (getenv('DT_MIN') === '0' ? 'front.js' : 'front.min.js'),
    'loader' => $va . 'assets/js/front-loader.min.js',
    'css' => $va . 'assets/css/front.min.css',
    'tabcss' => $va . 'assets/css/front-tab.min.css',
));
$json = ob_get_clean();
file_put_contents(getenv('DT_OUT'), $json);
