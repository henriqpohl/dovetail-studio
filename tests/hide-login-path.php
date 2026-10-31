<?php
/** Hide Login Path: settings, URL rewriting, request routing and the admin page. */
require __DIR__ . '/lib/harness.php';

use DovetailStudio\HideLoginPath\Admin;
use DovetailStudio\HideLoginPath\Router;
use DovetailStudio\HideLoginPath\Settings;

/* WordPress functions the simulator does not have. */
function sanitize_title_with_dashes($t, $r = '', $c = 'display') { return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9_\-]/', '', str_replace(' ', '-', strtolower(strip_tags((string) $t))))), '-'); }
function get_post_types($a = array()) { return array('post' => 'post', 'page' => 'page'); }
function get_page_by_path($p, $o = OBJECT, $t = 'page') { return $p === 'about' ? new WP_Post(array('ID' => 2)) : null; }
if (!function_exists('remove_action')) { function remove_action($h, $cb, $p = 10) { return remove_filter($h, $cb, $p); } }
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
boot_plugin();

/** A fresh router for one request. */
function hlp_request($uri, $pagenow = 'index.php', $get = array(), $admin = false) {
    $_SERVER['REQUEST_URI'] = $uri; $_GET = $get; $_POST = array();
    $GLOBALS['pagenow'] = $pagenow; $GLOBALS['__q']['admin'] = $admin ? 1 : 0;
    $r = new Router();
    $r->detect();
    return $r;
}
function hlp_save($input) { $GLOBALS['__settings_errors'] = array(); update_option(Settings::OPTION, Settings::sanitize($input)); }
function hlp_errors() { return implode(' | ', array_column((array) ($GLOBALS['__settings_errors'] ?? array()), 2)); }
/** Whether a callback is hooked. */
function hlp_hooked($hook, $cb) { foreach ((array) ($GLOBALS['__hooks'][$hook] ?? array()) as $list) foreach ($list as $e) if ($e[0] === $cb) return true; return false; }

t('module loaded with its version', DOVETAIL_HIDE_LOGIN_PATH_VERSION === (require DOVETAIL_STUDIO_PATH . 'modules/hide-login-path/module.php')['version']);
t('off by default', !Settings::active() && Settings::get()['redirect_slug'] === '404');
$r = new Router();
t('off: URLs untouched', $r->filter_site_url('https://example.com/wp-login.php') === 'https://example.com/wp-login.php');
as_visitor();
t('off: wp-login.php opens', hlp_request('/wp-login.php', 'wp-login.php')->decide() === '');
as_admin();

// ---------------------------------------------------------------- settings
hlp_save(array('slug' => ''));
t('no path: login stays at wp-login.php', !Settings::active() && hlp_errors() === '');
hlp_save(array('slug' => 'Login'));
t('guessable path refused', Settings::get()['slug'] === '' && !Settings::active() && strpos(hlp_errors(), 'bots try it first') !== false);
hlp_save(array('slug' => 'wp-secret'));
t('wp- paths refused', Settings::get()['slug'] === '');
hlp_save(array('slug' => 'about'));
t('existing page address refused', Settings::get()['slug'] === '' && strpos(hlp_errors(), 'already has this address') !== false);
hlp_save(array('slug' => ' /My Door/ ', 'redirect_slug' => 'not-here'));
t('path cleaned and saved', Settings::get()['slug'] === 'my-door' && Settings::get()['redirect_slug'] === 'not-here' && Settings::active(), hlp_errors());
hlp_save(array('slug' => 'my-door', 'redirect_slug' => 'my-door'));
t('redirect cannot be the login path', Settings::get()['redirect_slug'] === 'not-here' && strpos(hlp_errors(), 'cannot be the login path') !== false);
hlp_save(array('slug' => 'about', 'redirect_slug' => '404'));
t('a refused path keeps the saved one', Settings::get()['slug'] === 'my-door' && Settings::active());

// ---------------------------------------------------------------- URLs
update_option('permalink_structure', '/%postname%/');
$r = new Router();
t('login URL', Router::login_url() === 'https://example.com/my-door/');
t('wp_login_url moved', $r->filter_site_url('https://example.com/wp-login.php', 'wp-login.php', 'login') === 'https://example.com/my-door/');
$lost = 'https://example.com/wp-login.php?action=rp&key=a%2Bb&login=j%40x.com';
t('query string kept encoded', $r->filter_site_url($lost) === 'https://example.com/my-door/?action=rp&key=a%2Bb&login=j%40x.com');
t('relative redirect moved', $r->filter_redirect('wp-login.php?checkemail=confirm') === 'https://example.com/my-door/?checkemail=confirm');
t('protected post form untouched', $r->filter_site_url('https://example.com/wp-login.php?action=postpass') === 'https://example.com/wp-login.php?action=postpass');
t('other URLs untouched', $r->filter_site_url('https://example.com/shop/?redirect_to=https%3A%2F%2Fexample.com%2Fwp-login.php') === 'https://example.com/shop/?redirect_to=https%3A%2F%2Fexample.com%2Fwp-login.php');
t('redirect URL', Router::redirect_url() === 'https://example.com/404/');
update_option('permalink_structure', '');
t('plain permalinks', Router::login_url() === 'https://example.com/?my-door' && $r->filter_site_url('https://example.com/wp-login.php?action=lostpassword') === 'https://example.com/?my-door&action=lostpassword');
update_option('permalink_structure', '/%postname%/');

// ---------------------------------------------------------------- requests
add_action('template_redirect', 'wp_redirect_admin_locations', 1000);
as_visitor();
t('wp-login.php hidden', hlp_request('/wp-login.php', 'wp-login.php')->decide() === 'hide');
t('wp-signup.php hidden', hlp_request('/wp-signup.php', 'wp-signup.php')->decide() === 'hide');
t('password-protected post form still works', hlp_request('/wp-login.php?action=postpass', 'wp-login.php', array('action' => 'postpass'))->decide() === '');
$r = hlp_request('/my-door/', 'index.php');
t('login path shows the login form', $r->decide() === 'login' && $GLOBALS['pagenow'] === 'wp-login.php');
t('login path without trailing slash', hlp_request('/my-door', 'index.php')->decide() === 'login');
t('other pages untouched', hlp_request('/my-door/other/', 'index.php')->decide() === '' && $GLOBALS['pagenow'] === 'index.php');
t('wp-admin hidden when signed out', hlp_request('/wp-admin/', 'index.php', array(), true)->decide() === 'hide');
t('admin-post.php still answers', hlp_request('/wp-admin/admin-post.php', 'admin-post.php', array(), true)->decide() === '');
t('/login shortcut removed', !hlp_hooked('template_redirect', 'wp_redirect_admin_locations'));
as_admin();
t('signed in: wp-admin opens', hlp_request('/wp-admin/', 'index.php', array(), true)->decide() === '');
t('signed in: login path goes to the dashboard', hlp_request('/my-door/', 'index.php')->decide() === 'dashboard');
t('signed in: login path with an action shows the form', hlp_request('/my-door/?action=logout', 'index.php', array('action' => 'logout'))->decide() === 'login');
update_option('permalink_structure', '');
as_visitor();
t('plain permalinks: ?my-door shows the login form', hlp_request('/?my-door', 'index.php', array('my-door' => ''))->decide() === 'login');
update_option('permalink_structure', '/%postname%/');

// ---------------------------------------------------------------- admin page
as_admin();
$GLOBALS['__q']['admin'] = 1;
$o = capture(function () { ( new Admin() )->render_page(); });
t('admin page shows the login address', strpos($o, 'https://example.com/my-door/') !== false && strpos($o, 'name="dovetail_hide_login_path_settings[slug]"') !== false && strpos($o, 'for="dovetail-hlp-slug"') !== false);
t('no on/off field', strpos($o, '[enabled]') === false);
hlp_save(array('slug' => ''));
t('clearing the path brings wp-login.php back', !Settings::active());
$o = capture(function () { ( new Admin() )->render_page(); });
t('admin page shows it is off', strpos($o, 'Not active yet: save a login path') !== false);

// ---------------------------------------------------------------- wp-config switch
hlp_save(array('slug' => 'my-door'));
define('DOVETAIL_HIDE_LOGIN_PATH_DISABLED', true);
as_visitor();
t('DOVETAIL_HIDE_LOGIN_PATH_DISABLED brings wp-login.php back', !Settings::active() && hlp_request('/wp-login.php', 'wp-login.php')->decide() === '' && (new Router())->filter_site_url('https://example.com/wp-login.php') === 'https://example.com/wp-login.php');
