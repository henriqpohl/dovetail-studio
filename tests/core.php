<?php
/** Core: Crypto, Http, Loader (modules, ZIP upload, removal, cron), AdminScreens, uninstall. */
namespace DovetailStudio\Core { function is_uploaded_file($f) { return true; } }   // uploads in the simulator are local files

namespace {
require __DIR__ . '/lib/harness.php';
use DovetailStudio\Core\Crypto;
use DovetailStudio\Core\Http;
use DovetailStudio\Core\Loader;
use DovetailStudio\Core\AdminScreens;

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
boot_plugin();
$L = Loader::instance();

// ---------------------------------------------------------------- Crypto
$e = Crypto::encrypt('sk-test', 'ctx-a');
t('crypto round trip', Crypto::decrypt($e, 'ctx-a') === 'sk-test');
t('crypto contexts are isolated', Crypto::decrypt($e, 'ctx-b') === '');
t('crypto rejects plain and tampered values', Crypto::decrypt('sk-plain', 'ctx-a') === '' && Crypto::decrypt(substr($e, 0, -4) . 'AAAA', 'ctx-a') === '');

// ---------------------------------------------------------------- Http
t('IPv4 CIDR', Http::ip_in_range('104.16.5.9', '104.16.0.0/13') && !Http::ip_in_range('104.24.0.1', '104.16.0.0/13'));
t('IPv6 CIDR', Http::ip_in_range('2606:4700:10::1', '2606:4700::/32') && Http::ip_in_range('2a06:98c7::1', '2a06:98c0::/29') && !Http::ip_in_range('2a06:98c8::1', '2a06:98c0::/29'));
t('mixed families never match', !Http::ip_in_range('1.2.3.4', '2606:4700::/32'));
t('Cloudflare edge detected', Http::is_cloudflare('172.68.1.1') && !Http::is_cloudflare('8.8.8.8'));
$limits = array(); for ($i = 0; $i < 7; $i++) $limits[] = Http::rate_limit('t', 5) ? 1 : 0;
t('rate limit allows 5 then blocks', implode('', $limits) === '1111100');
$g = array(); for ($i = 0; $i < 3; $i++) $g[] = Http::rate_limit('g', 2, 3600, 'global') ? 1 : 0;
t('global limit', implode('', $g) === '110');
t('limit 0 means unlimited', Http::rate_limit('z', 0));

// ---------------------------------------------------------------- modules
t('seven bundled modules loaded', defined('DOVETAIL_HIDE_LOGIN_PATH_VERSION') && defined('DOVETAIL_VULNERABILITY_WATCH_VERSION') && defined('DOVETAIL_LOGIN_GUARD_VERSION') && defined('DOVETAIL_VIRTUAL_ASSISTANT_VERSION') && defined('DOVETAIL_NEWSLETTER_VERSION') && defined('DOVETAIL_SCHEMA_SEO_VERSION') && defined('DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION'));
foreach (array('hide-login-path' => 'DOVETAIL_HIDE_LOGIN_PATH_VERSION', 'vulnerability-watch' => 'DOVETAIL_VULNERABILITY_WATCH_VERSION', 'login-guard' => 'DOVETAIL_LOGIN_GUARD_VERSION', 'virtual-assistant' => 'DOVETAIL_VIRTUAL_ASSISTANT_VERSION', 'newsletter' => 'DOVETAIL_NEWSLETTER_VERSION', 'schema-seo' => 'DOVETAIL_SCHEMA_SEO_VERSION', 'schema-seo-woocommerce' => 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION') as $slug => $const) {
    t("$slug version from module.php", constant($const) === (require DOVETAIL_STUDIO_PATH . "modules/$slug/module.php")['version']);
}
t('uploaded modules live in wp-content/dovetail-modules', Loader::external_modules_dir() === WP_CONTENT_DIR . '/dovetail-modules');
t('path_url for bundled and uploaded modules', Loader::path_url(DOVETAIL_STUDIO_PATH . 'modules/newsletter') === DOVETAIL_STUDIO_URL . 'modules/newsletter/' && Loader::path_url(WP_CONTENT_DIR . '/dovetail-modules/x') === 'https://example.com/wp-content/dovetail-modules/x/');

$GLOBALS['__q']['admin'] = 1;
do_action('admin_menu'); do_action('admin_init');
$o = capture(function () use ($L) { $L->render_modules_page(); });
t('modules page: upload card, source column, no remove for bundled', strpos($o, 'Upload Module') !== false && substr_count($o, 'Bundled') === 7 && strpos($o, 'Remove Module') === false);
$GLOBALS['__user']['caps'] = array('manage_options', 'read');
t('upload card hidden without install_plugins', strpos(capture(function () use ($L) { $L->render_modules_page(); }), 'Upload Module') === false);
as_admin();

// ZIP upload, update, removal.
$GLOBALS['__redirect_throws'] = 1;
$zipdir = sys_get_temp_dir() . '/dt-zip-' . bin2hex(random_bytes(3));
@mkdir("$zipdir/hello/src", 0777, true);
file_put_contents("$zipdir/hello/module.php", "<?php\ndefined('ABSPATH') || exit;\nreturn array('slug' => 'hello', 'name' => 'Hello', 'version' => '1.0.0', 'description' => 'x', 'bootstrap' => __DIR__ . '/src/Module.php', 'class' => 'DovetailStudio\\\\Hello\\\\Module', 'cron_hooks' => array('hello_tick'), 'admin_screens' => array('pages' => array('hello-page')));\n");
file_put_contents("$zipdir/hello/src/Module.php", "<?php\nnamespace DovetailStudio\\Hello; final class Module { public function boot() { \$GLOBALS['hello_booted'] = \\DovetailStudio\\Core\\Loader::path_url(dirname(__DIR__)); } }\n");
$zip = new ZipArchive(); $zip->open("$zipdir/hello.zip", ZipArchive::CREATE); $zip->addFile("$zipdir/hello/module.php", 'hello/module.php'); $zip->addFile("$zipdir/hello/src/Module.php", 'hello/src/Module.php'); $zip->close();
$zip = new ZipArchive(); $zip->open("$zipdir/evil.zip", ZipArchive::CREATE); $zip->addFromString('../../x.php', '<?php'); $zip->close();
$upload = function ($file) use ($L) {
    $tmp = tempnam(sys_get_temp_dir(), 'z'); copy($file, $tmp);
    $_FILES = array('dovetail_module_zip' => array('name' => basename($file), 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)));
    $_REQUEST = $_POST = array('_wpnonce' => wp_create_nonce('dovetail_studio_upload_module'));
    try { $L->handle_upload_module(); } catch (Exception $e) { return $e->getMessage(); }
    return 'no redirect';
};
t('ZIP with ../ paths refused', strpos($upload("$zipdir/evil.zip"), 'dovetail_code=unsafe_path') !== false && !file_exists(WP_CONTENT_DIR . '/x.php'));
t('module installed from ZIP, disabled until enabled', strpos($upload("$zipdir/hello.zip"), 'dovetail_code=installed') !== false && get_option('dovetail_studio_modules')['hello'] === 0 && is_file(WP_CONTENT_DIR . '/dovetail-modules/hello/module.php') && is_file(WP_CONTENT_DIR . '/dovetail-modules/index.php'));
t('no staging folders left behind', !glob(WP_CONTENT_DIR . '/dovetail-modules/.*-*'));
t('module updated from ZIP, state kept', strpos($upload("$zipdir/hello.zip"), 'dovetail_code=updated') !== false && get_option('dovetail_studio_modules')['hello'] === 0);
$GLOBALS['__user']['caps'] = array('manage_options', 'read');
t('upload needs install_plugins', strpos($upload("$zipdir/hello.zip"), 'not allowed') !== false);
as_admin();
$m = new ReflectionMethod($L, 'discover_modules'); $m->setAccessible(true); $mods = $m->invoke($L);
t('uploaded module discovered as external', ($mods['hello']['_source'] ?? '') === 'external' && ($mods['newsletter']['_source'] ?? '') === 'bundled');
update_option('dovetail_studio_modules', array_merge(get_option('dovetail_studio_modules'), array('hello' => 1)));
$p = new ReflectionProperty($L, 'modules'); $p->setAccessible(true); $p->setValue($L, $mods);
$lm = new ReflectionMethod($L, 'load_modules'); $lm->setAccessible(true); $lm->invoke($L);
t('uploaded module boots with its own URL', ($GLOBALS['hello_booted'] ?? '') === 'https://example.com/wp-content/dovetail-modules/hello/');
$remove = function ($slug) use ($L) {
    $_REQUEST = array('module' => $slug, '_wpnonce' => wp_create_nonce('dovetail_studio_remove_module_' . $slug));
    try { $L->handle_remove_module(); } catch (Exception $e) { return $e->getMessage(); }
    return 'no redirect';
};
t('enabled module cannot be removed', strpos($remove('hello'), 'dovetail_code=disable_first') !== false);
update_option('dovetail_studio_modules', array('hello' => 0, 'newsletter' => 0));
wp_schedule_event(time(), 'hourly', 'hello_tick');
t('disabled uploaded module removed', strpos($remove('hello'), 'dovetail_code=removed') !== false && !is_dir(WP_CONTENT_DIR . '/dovetail-modules/hello'));
t('removal clears its cron events', !isset($GLOBALS['__cron']['hello_tick']));
t('bundled modules cannot be removed', strpos($remove('newsletter'), 'dovetail_code=bundled') !== false && is_dir(DOVETAIL_STUDIO_PATH . 'modules/newsletter'));
$GLOBALS['__redirect_throws'] = 0;
$_GET = array('dovetail_status' => 'error', 'dovetail_code' => 'bundled', 'dovetail_message' => 'Your site is hacked, call 555');
$o = capture(function () use ($L) { $L->render_modules_page(); });
t('notice text comes from the code, never from the URL', strpos($o, 'Bundled modules are part of the plugin') !== false && strpos($o, 'hacked') === false);
$_GET = array('dovetail_status' => 'error', 'dovetail_code' => 'nope');
t('unknown codes print nothing', strpos(capture(function () use ($L) { $L->render_modules_page(); }), 'dovetail-notice') === false);
$_GET = array();

// Disabling a module and deactivating the plugin stop cron events.
$L->sanitize_module_settings(array('newsletter' => 1, 'schema-seo' => 1, 'schema-seo-woocommerce' => 1));
t('disabling the VA clears its cron events', !isset($GLOBALS['__cron']['dt_va_daily']) && !isset($GLOBALS['__cron']['dt_va_flush_events']));
wp_schedule_event(time(), 'daily', 'dt_va_selfcheck');
call_user_func($GLOBALS['__deact']);
t('plugin deactivation clears module cron events', !isset($GLOBALS['__cron']['dt_va_selfcheck']));

// ---------------------------------------------------------------- AdminScreens
$A = AdminScreens::instance();
$reset = function () use ($A) { $r = new ReflectionProperty($A, 'current'); $r->setAccessible(true); $r->setValue($A, null); };
$GLOBALS['__screen'] = (object) array('id' => 'toplevel_page_dovetail-studio', 'base' => 'toplevel_page_dovetail-studio', 'post_type' => '');
$_GET = array('page' => 'dovetail-newsletter'); $reset();
t('module page is a Studio screen', $A->is_current());
$_GET = array('page' => 'some-other-plugin'); $reset();
t('other plugin pages are not', !$A->is_current());
$_GET = array(); $GLOBALS['__screen'] = (object) array('id' => 'edit-dt_enquiry', 'base' => 'edit', 'post_type' => 'dt_enquiry'); $reset();
t('VA enquiry list is a Studio screen (post type from the manifest)', $A->is_current());
add_action('admin_notices', function () { echo '<div class="notice notice-warning update-nag">WordPress 9 is available</div>'; });
add_action('all_admin_notices', function () { echo '<div class="notice">Other plugin</div>'; });
add_action('dovetail_studio_notices', function () { AdminScreens::notice('warning', 'Studio says hi'); });
$out = capture(function () { do_action('admin_head'); do_action('admin_notices'); do_action('all_admin_notices'); });
t('WordPress and third-party notices removed on Studio screens', strpos($out, 'WordPress 9') === false && strpos($out, 'Other plugin') === false);
t('Studio notices still shown, with the class CSS keeps visible', strpos($out, 'Studio says hi') !== false && strpos($out, 'dovetail-notice') !== false);
t('body class for the CSS fallback', strpos($A->body_class('x'), 'dovetail-admin-page') !== false);
$css = file_get_contents(DOVETAIL_STUDIO_PATH . 'assets/admin/dovetail-admin.css');
t('CSS hides only non-Studio notices', strpos($css, '.notice:not(.dovetail-notice)') !== false);
$GLOBALS['__settings_errors_out'] = array(array('setting' => 'general', 'code' => 'settings_updated', 'message' => 'Settings saved.', 'type' => 'success'));
t('settings_errors() prints with the Studio class', strpos(capture(function () { AdminScreens::settings_errors(); }), 'notice-success dovetail-notice') !== false);
$schema_admin = file_get_contents(DOVETAIL_STUDIO_PATH . 'modules/schema-seo/src/Admin.php');
t('Schema SEO no longer removes notices itself', strpos($schema_admin, 'remove_all_actions') === false);

// ---------------------------------------------------------------- uninstall
update_option('dovetail_schema_seo', array(1)); update_option('dt_va_public', array(1));
$GLOBALS['__posts'][900] = new WP_Post(array('ID' => 900, 'post_type' => 'dt_enquiry'));
define('WP_UNINSTALL_PLUGIN', 1);
wp_schedule_event(time(), 'daily', 'dt_va_selfcheck'); wp_schedule_event(time(), 'hourly', 'hello_tick');
include DOVETAIL_STUDIO_PATH . 'uninstall.php';
t('uninstall clears the cron events of every manifest', !isset($GLOBALS['__cron']['dt_va_selfcheck']));
t('uninstall keeps data by default', get_option('dovetail_schema_seo') !== false && isset($GLOBALS['__posts'][900]));
define('DOVETAIL_STUDIO_DELETE_DATA', true);
include DOVETAIL_STUDIO_PATH . 'uninstall.php';
t('uninstall deletes data with DOVETAIL_STUDIO_DELETE_DATA', get_option('dovetail_schema_seo') === false && !isset($GLOBALS['__posts'][900]));
t('uninstall drops the Login Guard tables', !in_array('wp_dovetail_login_guard_blocks', $GLOBALS['wpdb']->pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN), true));
}

namespace {
// ---------------------------------------------------------------- rate limits (atomic counters)
$GLOBALS['wpdb']->query("DELETE FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'");
$hits = array(); for ($i = 0; $i < 4; $i++) $hits[] = \DovetailStudio\Core\Http::rate_limit('t_bucket', 3, 600);
t('rate limit: the 4th hit in the window is refused', $hits === array(true, true, true, false));
$rows = $GLOBALS['wpdb']->get_results("SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'");
t('one counter row per window, named with its expiry', count($rows) === 1 && (int) $rows[0]['option_value'] === 4 && preg_match('/^dovetail_rl_(\d{10})_[a-f0-9]{20}$/', $rows[0]['option_name'], $m) && (int) $m[1] > time());
t('other buckets and visitors are counted apart', \DovetailStudio\Core\Http::rate_limit('t_other', 1, 600) && \DovetailStudio\Core\Http::rate_limit('t_bucket', 3, 600, 'someone-else'));
$GLOBALS['wpdb']->query("INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('dovetail_rl_1000000000_" . str_repeat('a', 20) . "', '9', 'no')");
\DovetailStudio\Core\Http::purge_rate_limits();
$left = array_column($GLOBALS['wpdb']->get_results("SELECT option_name FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'"), 'option_name');
t('purge removes ended windows only', !in_array('dovetail_rl_1000000000_' . str_repeat('a', 20), $left, true) && count($left) === 3);
$GLOBALS['__ext_cache'] = true;
$hits = array(); for ($i = 0; $i < 3; $i++) $hits[] = \DovetailStudio\Core\Http::rate_limit('t_cache', 2, 600);
t('with a persistent object cache the count uses wp_cache_incr', $hits === array(true, true, false) && count($GLOBALS['wpdb']->get_results("SELECT option_name FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'")) === 3);
$GLOBALS['__ext_cache'] = false;
}

namespace {
// The menu icon starts in the admin menu gray, so it does not flash black before svg-painter.js recolors it.
$icon = (new ReflectionMethod(\DovetailStudio\Core\Loader::instance(), 'get_menu_icon'));
$icon->setAccessible(true);
$svg = base64_decode(substr($icon->invoke(\DovetailStudio\Core\Loader::instance()), strlen('data:image/svg+xml;base64,')));
t('menu icon: no black fill, admin gray instead', strpos($svg, 'fill="#a7aaad"') !== false && stripos($svg, 'fill="#000') === false);
}
