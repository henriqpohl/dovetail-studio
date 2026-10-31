<?php
/** Login Guard: settings tabs, XML-RPC, activity log, email alerts, two-factor authentication, captcha (and Core\Captcha). */
require __DIR__ . '/lib/harness.php';

use DovetailStudio\Core\Captcha as CoreCaptcha;
use DovetailStudio\LoginGuard\Activity;
use DovetailStudio\LoginGuard\Admin;
use DovetailStudio\LoginGuard\Alerts;
use DovetailStudio\LoginGuard\Captcha;
use DovetailStudio\LoginGuard\Settings;
use DovetailStudio\LoginGuard\Store;
use DovetailStudio\LoginGuard\TwoFactor;

const LG_IP = '203.0.113.9';
$_SERVER = array('REMOTE_ADDR' => LG_IP, 'HTTP_USER_AGENT' => 'TestBrowser/1.0');
$GLOBALS['__users'] = array(
    'boss' => array('ID' => 1, 'roles' => array('administrator'), 'email' => 'boss@example.org'),
    'ann'  => array('ID' => 7, 'roles' => array('editor'), 'email' => 'ann@example.org'),
);
$GLOBALS['__user'] = array('id' => 1, 'caps' => array('manage_options', 'install_plugins', 'read', 'edit_user'), 'roles' => array('administrator'), 'login' => 'boss');
boot_plugin();

$boss = get_userdata(1);
$ann  = get_userdata(7);
function lg_reset() { global $wpdb; lg_shutdown(); foreach (array(Store::blocks_table(), Store::failures_table(), Store::activity_table()) as $t) $wpdb->pdo->exec("DELETE FROM $t"); Store::refresh_marker(); $wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'"); $GLOBALS['__mail'] = array(); }
function lg_settings($over) { update_option(Settings::OPTION, array_merge(Settings::defaults(), $over)); }
function lg_rows($event = '') { return Activity::query($event)['rows']; }
function lg_redirect($cb) { $GLOBALS['__redirect_throws'] = 1; try { $cb(); } catch (Exception $e) { return urldecode(urldecode(substr($e->getMessage(), 9))); } finally { $GLOBALS['__redirect_throws'] = 0; } return ''; }
function lg_shutdown() { do_action('shutdown'); unset($GLOBALS['__hooks']['shutdown']); }
class LG_Errors extends WP_Error { public $codes = array(); function add($c, $m = '', $d = null) { $this->codes[] = $c; } }

// ---------------------------------------------------------------- settings tabs
lg_settings(array('max_login_failures' => 5));
$s = Settings::sanitize(array('_section' => 'alerts', 'summary_days' => '7', 'alert_recipients' => "a@example.org\nnope"));
t('saving one tab keeps the others', $s['max_login_failures'] === 5 && $s['summary_days'] === 7 && $s['alert_recipients'] === array('a@example.org'));
t('unchecked boxes of the saved tab are off', $s['alert_admin_login'] === false && $s['brute_force'] === true);
$GLOBALS['__settings_errors'] = array();
$c = Settings::sanitize(array('_section' => 'captcha', 'captcha_enabled' => '1', 'captcha_provider' => 'turnstile'));
t('captcha stays off without keys', $c['captcha_enabled'] === false && !empty($GLOBALS['__settings_errors']));
$c = Settings::sanitize(array('_section' => 'captcha', 'captcha_enabled' => '1', 'captcha_provider' => 'recaptcha', 'captcha_site_key' => 'site-KEY_1', 'captcha_secret' => 'sec-RET_2', 'captcha_threshold' => '7'));
update_option(Settings::OPTION, $c);
t('captcha secret stored encrypted', $c['captcha_enabled'] && strpos($c['captcha_secret'], 'dovetail_enc_v1:') === 0 && Settings::captcha_secret() === 'sec-RET_2' && $c['captcha_threshold'] === 7);
$c2 = Settings::sanitize(array('_section' => 'captcha', 'captcha_enabled' => '1', 'captcha_provider' => 'recaptcha', 'captcha_site_key' => 'site-KEY_1', 'captcha_secret' => ''));
t('blank secret field keeps the saved secret', $c2['captcha_secret'] === $c['captcha_secret']);
$c3 = Settings::sanitize(array('_section' => 'captcha', 'captcha_provider' => 'turnstile', 'captcha_site_key' => 'x', 'captcha_secret' => ''));
t('changing provider clears the old secret', $c3['captcha_secret'] === '');
$f = Settings::sanitize(array('_section' => 'two_factor', 'two_factor_roles' => array('editor' => 'required', 'author' => 'bogus', 'administrator' => 'optional'), 'two_factor_grace_days' => '3'));
t('two-factor roles sanitized', $f['two_factor_roles'] === array('administrator' => 'optional', 'editor' => 'required') && $f['two_factor_grace_days'] === 3);

// ---------------------------------------------------------------- XML-RPC
lg_settings(array());
t('XML-RPC authentication off by default', apply_filters('xmlrpc_enabled', true) === false && apply_filters('xmlrpc_methods', array('a' => 1)) === array('a' => 1));
lg_settings(array('disable_xmlrpc' => true));
t('XML-RPC off completely', apply_filters('xmlrpc_methods', array('a' => 1)) === array() && !isset(apply_filters('wp_headers', array('X-Pingback' => 'x'))['X-Pingback']));
lg_settings(array('disable_xmlrpc_auth' => false));
t('XML-RPC authentication can be allowed', apply_filters('xmlrpc_enabled', true) === true);

// ---------------------------------------------------------------- activity log
lg_settings(array('alert_admin_login' => false));
lg_reset();
do_action('wp_login', 'boss', $boss);
do_action('wp_login_failed', 'ann', new WP_Error('incorrect_password', 'x'));
Store::add('198.51.100.40', 'block', 'username', 'Tried admin', 3600);
$rows = lg_rows();
t('activity records sign-ins, failures and blocks', count($rows) === 3 && count(lg_rows(Activity::LOGIN_SUCCESS)) === 1 && lg_rows(Activity::BLOCK)[0]['ip'] === '198.51.100.40');
t('activity stores IP and browser', lg_rows(Activity::LOGIN_SUCCESS)[0]['ip'] === LG_IP && lg_rows(Activity::LOGIN_SUCCESS)[0]['user_agent'] === 'TestBrowser/1.0');
$sum = Activity::summary(time() - 60);
t('summary counts events and top IPs', $sum['counts'][Activity::LOGIN_FAILED] === 1 && isset($sum['top_ips']['198.51.100.40']));
$GLOBALS['wpdb']->query('UPDATE ' . Store::activity_table() . ' SET created_at = 5');
Activity::cleanup();
t('cleanup removes entries past the retention period', Activity::query()['total'] === 0);
lg_settings(array('activity_enabled' => false, 'alert_admin_login' => false));
do_action('wp_login', 'boss', $boss);
t('activity log can be switched off', Activity::query()['total'] === 0);

// ---------------------------------------------------------------- email alerts
lg_settings(array());
lg_reset();
delete_option(Alerts::QUOTA_OPTION);
do_action('wp_login', 'boss', $boss);
lg_shutdown();
$m = $GLOBALS['__mail'][0] ?? array();
t('admin sign-in alert sent after the response', count($GLOBALS['__mail']) === 1 && $m['s'] === '[Login Guard Alert] example.com Admin Login', json_encode($GLOBALS['__mail']));
t('alert body like Wordfence\'s', strpos($m['b'] ?? '', 'A user with username "boss" who has administrator access signed in') !== false && strpos($m['b'] ?? '', 'User IP: ' . LG_IP) !== false && strpos($m['b'] ?? '', 'User hostname:') !== false && strpos($m['b'] ?? '', 'Browser: TestBrowser/1.0') !== false);
t('alert goes to the admin email by default', ($m['to'] ?? null) === array('admin@example.com'));
t('no location without Cloudflare', strpos($m['b'] ?? '', 'User location') === false);
$GLOBALS['__mail'] = array();
do_action('wp_login', 'ann', $ann);
lg_shutdown();
t('no alert for non-administrators', count($GLOBALS['__mail']) === 0);
lg_settings(array('alert_admin_login_scope' => 'new_ip', 'alert_recipients' => array('sec@example.org')));
do_action('wp_login', 'boss', $boss);
lg_shutdown();
t('"only new IP": known IP sends nothing', count($GLOBALS['__mail']) === 0);
delete_user_meta(1, Alerts::KNOWN_IPS_META);
do_action('wp_login', 'boss', $boss);
lg_shutdown();
t('"only new IP": new IP alerts the chosen recipients', count($GLOBALS['__mail']) === 1 && $GLOBALS['__mail'][0]['to'] === array('sec@example.org'));

lg_settings(array());
lg_reset();
Store::add('198.51.100.41', 'block', 'username', 'Tried to sign in as "admin"', 3600, 'admin');
Store::add('198.51.100.42', 'lockout', 'login', 'Too many failures', 3600, 'ann');
Store::add('198.51.100.43', 'block', 'manual', 'Manual', 0);
lg_shutdown();
$subjects = array_column($GLOBALS['__mail'], 's');
t('block and lockout alerts, none for manual blocks', $subjects === array('[Login Guard Alert] example.com IP Blocked', '[Login Guard Alert] example.com User Locked Out'), implode(' | ', $subjects));
t('block alert has the reason, username and unblock link', strpos($GLOBALS['__mail'][0]['b'], 'Reason: Tried to sign in as "admin"') !== false && strpos($GLOBALS['__mail'][0]['b'], 'Username tried: admin') !== false && strpos($GLOBALS['__mail'][0]['b'], 'tab=blocking') !== false);
lg_reset();
lg_settings(array('alert_lockout' => false, 'alert_max_per_hour' => 1));
delete_option(Alerts::QUOTA_OPTION);
Store::add('198.51.100.44', 'lockout', 'login', 'x', 3600);
Store::add('198.51.100.45', 'block', 'blank_ua', 'x', 3600);
Store::add('198.51.100.46', 'block', 'blank_ua', 'x', 3600);
lg_shutdown();
t('lockout alerts can be switched off; hourly cap respected', count($GLOBALS['__mail']) === 1);

// summary
lg_settings(array('summary_days' => 2));
lg_reset();
delete_option(Alerts::SUMMARY_OPTION);
$A = new Alerts();
$A->maybe_send_summary();
t('first run only starts the clock', count($GLOBALS['__mail']) === 0 && (int) get_option(Alerts::SUMMARY_OPTION) > 0);
update_option(Alerts::SUMMARY_OPTION, time() - DAY_IN_SECONDS);
do_action('wp_login_failed', 'x', new WP_Error('invalid_username', 'x'));
$A->maybe_send_summary();
t('summary waits for the chosen number of days', count($GLOBALS['__mail']) === 0);
update_option(Alerts::SUMMARY_OPTION, time() - 3 * DAY_IN_SECONDS);
$A->maybe_send_summary();
t('summary sent after the period', count($GLOBALS['__mail']) === 1 && strpos($GLOBALS['__mail'][0]['s'], 'Summary') !== false && strpos($GLOBALS['__mail'][0]['b'], 'Failed sign-in: 1') !== false);
lg_reset();
update_option(Alerts::SUMMARY_OPTION, time() - 3 * DAY_IN_SECONDS);
$A->maybe_send_summary();
t('empty summary skipped by default', count($GLOBALS['__mail']) === 0);
lg_settings(array('summary_days' => 2, 'summary_skip_empty' => false));
update_option(Alerts::SUMMARY_OPTION, time() - 3 * DAY_IN_SECONDS);
$A->maybe_send_summary();
t('empty summary sent when asked', count($GLOBALS['__mail']) === 1);
lg_settings(array('summary_enabled' => false));
update_option(Alerts::SUMMARY_OPTION, time() - 30 * DAY_IN_SECONDS);
$GLOBALS['__mail'] = array();
$A->maybe_send_summary();
t('summary can be switched off', count($GLOBALS['__mail']) === 0);

// ---------------------------------------------------------------- two-factor
t('base32 round trip', TwoFactor::base32_decode(TwoFactor::base32_encode('12345678901234567890')) === '12345678901234567890' && TwoFactor::base32_encode('12345678901234567890') === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
t('RFC 6238 test vector (T = 59 s)', TwoFactor::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1) === '287082');
$sec = TwoFactor::new_secret();
$now = TwoFactor::code($sec, (int) floor(time() / 30));
$st  = TwoFactor::match($sec, $now);
t('current code matches, a used step is refused', $st > 0 && TwoFactor::match($sec, $now, $st) === 0 && TwoFactor::match($sec, '000000x') === 0);

lg_settings(array('two_factor_roles' => array('administrator' => 'optional', 'editor' => 'required'), 'alert_admin_login' => false));
lg_reset();
t('mode follows the strictest role', TwoFactor::mode_for($boss) === 'optional' && TwoFactor::mode_for($ann) === 'required' && TwoFactor::mode_for(get_userdata(99)) === 'off');
$TF = new TwoFactor();
$html = capture(function () use ($TF, $boss) { $TF->render_profile($boss); });
$pending = \DovetailStudio\Core\Crypto::decrypt((string) get_user_meta(1, TwoFactor::PENDING_META, true), Settings::CRYPTO_CONTEXT);
t('profile shows a QR code and the key', strpos($html, '<svg') !== false && strpos($html, 'role="img"') !== false && strlen($pending) === 32 && strpos($html, substr($pending, 0, 4)) !== false);
$_POST = array('dovetail_lg_setup_code' => '123456');
$err = new LG_Errors(); $TF->save_profile($err, true, $boss);
t('wrong set-up code refused', $err->codes === array('dovetail_lg_2fa_code') && !TwoFactor::is_active(1));
$_POST = array('dovetail_lg_setup_code' => TwoFactor::code($pending, (int) floor(time() / 30)));
$err = new LG_Errors(); $TF->save_profile($err, true, $boss);
t('right set-up code turns it on with 10 recovery codes', $err->codes === array() && TwoFactor::is_active(1) && TwoFactor::recovery_left(1) === 10);
$html = capture(function () use ($TF, $boss) { $TF->render_profile($boss); });
preg_match_all('#<code>([A-Z2-7]{5}-[A-Z2-7]{5})</code>#', $html, $codes);
t('recovery codes shown once', count($codes[1]) === 10 && strpos(capture(function () use ($TF, $boss) { $TF->render_profile($boss); }), $codes[1][0]) === false);
$_POST = array();

$_POST = array('rememberme' => '1', 'pwd' => 'secret');
$url = lg_redirect(function () use ($TF, $boss) { $TF->intercept('boss', $boss); });
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
t('password accepted: session cleared, code screen next', ($q['action'] ?? '') === 'dovetail_2fa' && (int) $q['uid'] === 1 && $GLOBALS['__auth'] === null && TwoFactor::pending_login(1, $q['token']) !== null);
$GLOBALS['__auth'] = array(1, false); $_POST = array();
t('session renewed from a cookie (no password typed) is left alone', lg_redirect(function () use ($TF, $boss) { $TF->intercept('boss', $boss); }) === '' && $GLOBALS['__auth'] === array(1, false));
$login = TwoFactor::pending_login(1, $q['token']);
$r = $TF->complete($boss, $login, '000000', false);
t('wrong code refused and counted as a failed sign-in', is_wp_error($r) && (int) $GLOBALS['wpdb']->get_var('SELECT COUNT(*) FROM ' . Store::failures_table()) === 1 && count(lg_rows(Activity::TWOFA_FAILED)) === 1 && count(lg_rows(Activity::LOGIN_FAILED)) === 0);
$r = $TF->complete($boss, $login, strtolower($codes[1][0]), false);
t('recovery code signs in once', is_string($r) && $r === admin_url() && $GLOBALS['__auth'] === array(1, true) && TwoFactor::recovery_left(1) === 9 && TwoFactor::pending_login(1, $q['token']) === null);
t('sign-in completed after the code', count(lg_rows(Activity::LOGIN_SUCCESS)) === 1 && count(lg_rows(Activity::TWOFA_SUCCESS)) === 1);
t('a used recovery code is refused', !TwoFactor::use_recovery_code(1, $codes[1][0]));
$html = capture(function () use ($TF, $q) { $m = new ReflectionMethod($TF, 'render_form'); $m->setAccessible(true); $m->invoke($TF, 1, $q['token'], 'Bad code'); });
t('code screen: labelled field, one-time-code autocomplete, remember device', strpos($html, 'for="dovetail-lg-code"') !== false && strpos($html, 'autocomplete="one-time-code"') !== false && strpos($html, 'Remember this device for 30 days') !== false && strpos($html, 'Bad code') !== false);

// required role
$GLOBALS['__user'] = array('id' => 7, 'caps' => array('read', 'edit_user'), 'roles' => array('editor'), 'login' => 'ann');
$GLOBALS['pagenow'] = 'index.php';
update_user_meta(7, TwoFactor::SINCE_META, time() - 10 * DAY_IN_SECONDS);
t('after the grace period a required user is sent to the profile', strpos(lg_redirect(array($TF, 'enforce_required')), 'profile.php#dovetail-lg-2fa') !== false);
$GLOBALS['pagenow'] = 'profile.php';
t('the profile itself stays open', lg_redirect(array($TF, 'enforce_required')) === '');
update_user_meta(7, TwoFactor::SINCE_META, time());
$GLOBALS['pagenow'] = 'index.php';
t('within the grace period: notice only', lg_redirect(array($TF, 'enforce_required')) === '' && strpos(capture(array($TF, 'required_notice')), 'requires two-factor') !== false);

// admin reset
$GLOBALS['__user'] = array('id' => 1, 'caps' => array('manage_options', 'read', 'edit_user'), 'roles' => array('administrator'), 'login' => 'boss');
update_user_meta(7, TwoFactor::SECRET_META, get_user_meta(1, TwoFactor::SECRET_META, true));
$_POST = array('dovetail_lg_reset' => '1');
$TF->save_profile(new LG_Errors(), true, $ann);
t('administrator resets another user\'s two-factor', !TwoFactor::is_active(7) && TwoFactor::is_active(1));
$_POST = array('dovetail_lg_disable' => '1');
$TF->save_profile(new LG_Errors(), true, $boss);
t('optional role: user turns it off', !TwoFactor::is_active(1));
$_POST = array();

// ---------------------------------------------------------------- Core\Captcha
function lg_reply($json, $code = 200) { $GLOBALS['__http_reply'] = array('response' => array('code' => $code), 'body' => json_encode($json)); }
$GLOBALS['__http_router'] = null;
lg_reply(array('success' => true, 'score' => 0.9, 'action' => 'login'));
$r = CoreCaptcha::verify('recaptcha', 'sec', 'tok', 'login');
$sent = end($GLOBALS['__http']);
t('Core captcha: success with score', $r['success'] && $r['score'] === 0.9 && $sent[1] === 'https://www.google.com/recaptcha/api/siteverify' && $sent[2]['body']['remoteip'] === LG_IP);
t('Core captcha: action mismatch fails', !CoreCaptcha::verify('recaptcha', 'sec', 'tok', 'register')['success']);
t('Core captcha: missing token fails without a request', ($n = count($GLOBALS['__http'])) && !CoreCaptcha::verify('turnstile', 'sec', '')['success'] && count($GLOBALS['__http']) === $n);
lg_reply(array(), 500);
$r = CoreCaptcha::verify('turnstile', 'sec', 'tok');
t('Core captcha: unreachable provider reported', !$r['reachable'] && !$r['success'] && end($GLOBALS['__http'])[1] === 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
t('Core captcha: script URLs', CoreCaptcha::script_url('recaptcha', 'K1') === 'https://www.google.com/recaptcha/api.js?render=K1' && CoreCaptcha::script_url('turnstile', 'x') === 'https://challenges.cloudflare.com/turnstile/v0/api.js');

// ---------------------------------------------------------------- Login Guard captcha
$cap = Settings::sanitize(array('_section' => 'captcha', 'captcha_enabled' => '1', 'captcha_provider' => 'recaptcha', 'captcha_site_key' => 'SITEKEY', 'captcha_secret' => 'SECRET', 'captcha_threshold' => '5', 'captcha_email_verify' => '1'));
update_option(Settings::OPTION, array_merge($cap, array('alert_admin_login' => false)));
lg_reset();
delete_option(Captcha::SCORES_OPTION);
$GLOBALS['pagenow'] = 'wp-login.php';
$_POST = array('g-recaptcha-response' => 'tok', 'log' => 'boss');
t('captcha enabled with its keys', Captcha::enabled());
lg_reply(array('success' => true, 'score' => 0.9, 'action' => 'login'));
t('high score passes', (new Captcha())->check_login($boss) === $boss);
lg_reply(array('success' => false, 'error-codes' => array('invalid-input-response')));
$r = (new Captcha())->check_login($boss);
t('invalid token refused and logged', is_wp_error($r) && $r->get_error_code() === 'dovetail_login_guard_captcha' && count(lg_rows(Activity::CAPTCHA_FAILED)) === 1);
$GLOBALS['__mail'] = array();
lg_reply(array('success' => true, 'score' => 0.1, 'action' => 'login'));
$r = (new Captcha())->check_login($boss);
t('low score with the right password: email with a sign-in link', is_wp_error($r) && $r->get_error_code() === 'dovetail_login_guard_captcha_verify' && count($GLOBALS['__mail']) === 1 && $GLOBALS['__mail'][0]['to'] === 'boss@example.org');
preg_match('#https://example\.com/wp-login\.php\?\S+#', $GLOBALS['__mail'][0]['b'], $link);
parse_str((string) parse_url($link[0] ?? '', PHP_URL_QUERY), $lq);
$GLOBALS['__auth'] = null;
t('a wrong link token does nothing', (new Captcha())->complete_verify(1, 'nope') === wp_login_url() && $GLOBALS['__auth'] === null);
t('the emailed link signs the user in once', ($lq['action'] ?? '') === 'dovetail_verify' && (new Captcha())->complete_verify(1, $lq['token']) === admin_url() && $GLOBALS['__auth'][0] === 1 && (new Captcha())->complete_verify(1, $lq['token']) === wp_login_url());
$GLOBALS['__mail'] = array();
update_option(Settings::OPTION, array_merge(Settings::get(), array('captcha_email_verify' => false)));
$r = (new Captcha())->check_login($boss);
t('low score without email verification: refused', is_wp_error($r) && $r->get_error_code() === 'dovetail_login_guard_captcha' && count($GLOBALS['__mail']) === 0);
update_option(Settings::OPTION, array_merge(Settings::get(), array('captcha_test_mode' => true)));
t('test mode lets it through and logs the result', (new Captcha())->check_login($boss) === $boss && strpos(lg_rows(Activity::CAPTCHA_TEST)[0]['detail'] ?? '', 'failed 0.1') === 0);
t('scores counted in the histogram', Captcha::scores()[9] === 1 && Captcha::scores()[1] >= 1 && array_sum(Captcha::scores()) >= 4);
update_option(Settings::OPTION, array_merge(Settings::get(), array('captcha_test_mode' => false)));
lg_reply(array(), 503);
t('provider down: sign-in goes through', (new Captcha())->check_login($boss) === $boss);
lg_reply(array('success' => false));
$GLOBALS['pagenow'] = 'admin-ajax.php';
$n = count($GLOBALS['__http']);
t('other sign-in forms (no widget) are not checked', (new Captcha())->check_login($boss) === $boss && count($GLOBALS['__http']) === $n);
$_POST['woocommerce-login-nonce'] = 'x';
t('WooCommerce login form is checked', is_wp_error((new Captcha())->check_login($boss)));
$GLOBALS['pagenow'] = 'wp-login.php';
$e = (new Captcha())->check_registration(new LG_Errors());
t('failed registration refused', $e->codes === array('dovetail_login_guard_captcha'));
update_option(Settings::OPTION, array_merge(Settings::get(), array('allowlist' => array(LG_IP))));
t('allowlisted IPs skip the captcha', (new Captcha())->check_login($boss) === $boss);
update_option(Settings::OPTION, array_merge(Settings::get(), array('allowlist' => array())));
$html = capture(function () { (new Captcha())->render_login(); });
t('reCAPTCHA: hidden token field with the site key', strpos($html, 'name="g-recaptcha-response" value="" data-dovetail-lg-recaptcha="login" data-sitekey="SITEKEY"') !== false);
$ts = Settings::sanitize(array('_section' => 'captcha', 'captcha_enabled' => '1', 'captcha_provider' => 'turnstile', 'captcha_site_key' => 'TSKEY', 'captcha_secret' => 'TSSECRET'));
update_option(Settings::OPTION, $ts);
$html = capture(function () { (new Captcha())->render_register(); });
t('Turnstile: widget with the site key', strpos($html, 'class="cf-turnstile" data-sitekey="TSKEY" data-action="register"') !== false);
$_POST = array('cf-turnstile-response' => 'tok', 'woocommerce-login-nonce' => 'x');
lg_reply(array('success' => false));
t('Turnstile failure refused', is_wp_error((new Captcha())->check_login($boss)) && strpos(end($GLOBALS['__http'])[1], 'challenges.cloudflare.com') !== false);
lg_reply(array('success' => true));
t('Turnstile success passes', (new Captcha())->check_login($boss) === $boss);
$_POST = array();
$GLOBALS['__http_reply'] = null;

// ---------------------------------------------------------------- admin tabs
$GLOBALS['__q']['admin'] = 1;
$AD = new Admin();
foreach (array('activity' => 'All events', 'two-factor' => 'Save Two-Factor Settings', 'captcha' => 'Requests per reCAPTCHA score', 'alerts' => 'Save Alert Settings', 'settings' => 'Disable XML-RPC authentication') as $tab => $needle) {
    $_GET = array('page' => Admin::PAGE, 'tab' => $tab);
    $html = capture(array($AD, 'render_page'));
    t("admin tab renders: $tab", strpos($html, $needle) !== false && strpos($html, 'aria-current="page"') !== false, (string) strlen($html));
}
t('reCAPTCHA token script loads after Google\'s', in_array(Captcha::PROVIDER_HANDLE, $GLOBALS['__enq'], true) && in_array(Captcha::SCRIPT_HANDLE, $GLOBALS['__enq'], true) && empty($GLOBALS['__inline']));
$_GET = array();
t('no text translated before init', empty($GLOBALS['__early_i18n']), implode(' | ', (array) ($GLOBALS['__early_i18n'] ?? array())));
