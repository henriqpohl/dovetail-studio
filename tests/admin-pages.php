<?php
/** Every admin screen of the Studio and its modules renders without PHP warnings, and its texts are translatable. */
require __DIR__ . '/lib/harness.php';

$GLOBALS['__tr'] = array('Save theme' => 'Salvar tema', 'Save Newsletter Settings' => 'Salvar newsletter');
$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
boot_plugin();
$GLOBALS['__q']['admin'] = 1;
do_action('admin_menu');
do_action('admin_init');

$L = \DovetailStudio\Core\Loader::instance();
$schema_admin = null;
foreach ($GLOBALS['__hooks']['admin_menu'] as $list) foreach ($list as $e) if (is_array($e[0]) && $e[0][0] instanceof \DovetailStudio\SchemaSeo\Admin) $schema_admin = $e[0][0];

$pages = array(
    'Studio dashboard'      => function () use ($L) { $L->render_dashboard(); },
    'Studio modules'        => function () use ($L) { $L->render_modules_page(); },
    'Newsletter'            => 'dovetail_newsletter_settings_page',
    'Schema SEO'            => function () use ($schema_admin) { $schema_admin->render_page(); },
    'Schema SEO WooCommerce'=> 'dovetail_schema_seo_woocommerce_render_settings_page',
    'VA company notes'      => 'dt_va_company_notes_page',
    'Login Guard: blocking' => function () { $_GET = array('page' => 'dovetail-login-guard'); ( new \DovetailStudio\LoginGuard\Admin() )->render_page(); },
    'Vulnerability Watch'   => function () { $_GET = array('page' => 'dovetail-vulnerability-watch'); ( new \DovetailStudio\VulnerabilityWatch\Admin() )->render_page(); },
    'Hide Login Path'       => function () { $_GET = array('page' => 'dovetail-hide-login-path'); ( new \DovetailStudio\HideLoginPath\Admin() )->render_page(); },
    'Login Guard: settings' => function () { $_GET = array('page' => 'dovetail-login-guard', 'tab' => 'settings'); ( new \DovetailStudio\LoginGuard\Admin() )->render_page(); },
);
foreach (array('ai-keys', 'company', 'theme', 'ai-assistant', 'knowledge', 'flow', 'faq', 'case-studies') as $tab) {
    $pages["VA settings: $tab"] = function () use ($tab) { $_GET['tab'] = $tab; $_GET['page'] = 'dt-va-settings'; dt_va_settings_page(); };
}
$html = array();
foreach ($pages as $name => $cb) {
    try {
        $html[$name] = capture($cb);
        t("renders: $name", strlen($html[$name]) > 200, (string) strlen($html[$name]));
    } catch (Throwable $e) {
        t("renders: $name", false, $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}
t('module page text translated', strpos($html['Newsletter'] ?? '', 'Salvar newsletter') !== false);
t('VA admin text translated', strpos($html['VA settings: theme'] ?? '', 'Salvar tema') !== false);
t('no Europe/Dublin left in the VA admin', stripos(implode('', $html), 'Europe/Dublin') === false);
t('no literal \\u2014 in the VA admin', strpos(implode('', $html), '\\u2014') === false);
