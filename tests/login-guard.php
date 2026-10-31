<?php
/** Login Guard: brute force lockouts, blocks, block list actions, hardening and password policies. */
require __DIR__ . '/lib/harness.php';

use DovetailStudio\LoginGuard\Admin;
use DovetailStudio\LoginGuard\Guard;
use DovetailStudio\LoginGuard\Hardening;
use DovetailStudio\LoginGuard\Passwords;
use DovetailStudio\LoginGuard\Settings;
use DovetailStudio\LoginGuard\Store;

const LG_IP = '203.0.113.9'; // Http::client_ip() is cached per request, so every check runs as this visitor.
$_SERVER = array('REMOTE_ADDR' => LG_IP);
$GLOBALS['__users'] = array('editor-ann' => array('ID' => 7));
as_admin();
boot_plugin();

$G = Guard::instance();
function lg_reset() { global $wpdb; $wpdb->pdo->exec('DELETE FROM ' . Store::blocks_table()); $wpdb->pdo->exec('DELETE FROM ' . Store::failures_table()); Store::refresh_marker(); }
function lg_err($code) { return new WP_Error($code, 'x'); }
function lg_dies($cb) { try { $cb(); return ''; } catch (Exception $e) { return strpos($e->getMessage(), 'wp_die') === 0 ? $e->getMessage() : 'other: ' . $e->getMessage(); } }
function lg_redirect($cb) { $GLOBALS['__redirect_throws'] = 1; try { $cb(); } catch (Exception $e) { parse_str((string) parse_url(substr($e->getMessage(), 9), PHP_URL_QUERY), $q); return $q['lg_code'] ?? $e->getMessage(); } finally { $GLOBALS['__redirect_throws'] = 0; } return ''; }
function lg_failures() { return (int) $GLOBALS['wpdb']->get_var('SELECT COUNT(*) FROM ' . Store::failures_table()); }
/** WP_Error whose add() records the codes (the simulator's WP_Error has no add()). */
class LG_Errors extends WP_Error { public $codes = array(); function add($c, $m = '', $d = null) { $this->codes[] = $c; } }

// ---------------------------------------------------------------- install and settings
$tables = $GLOBALS['wpdb']->pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
t('tables created on boot', in_array(Store::blocks_table(), $tables, true) && in_array(Store::failures_table(), $tables, true) && get_option(Store::DB_OPTION) === Store::DB_VERSION);
t('defaults match the usual Wordfence setup', Settings::get()['max_login_failures'] === 3 && Settings::get()['max_lostpw_attempts'] === 2 && Settings::get()['failure_window'] === 4 * 3600 && Settings::get()['lockout_duration'] === 86400);
$GLOBALS['__settings_errors'] = array();
$s = Settings::sanitize(array('max_login_failures' => '999', 'failure_window' => '3600', 'blocked_usernames' => "Admin\nroot, root\n<b>x</b>", 'allowlist' => "198.51.100.0/24\nnot-an-ip\n2001:db8::/129\n2001:db8::1", 'leaked_passwords' => 'nope', 'brute_force' => '1'));
t('sanitize: values outside the choices fall back to the default', $s['max_login_failures'] === 3 && $s['failure_window'] === 3600 && $s['leaked_passwords'] === 'admins');
t('sanitize: usernames lowercased and unique', $s['blocked_usernames'] === array('admin', 'root', 'x'), json_encode($s['blocked_usernames']));
t('sanitize: invalid allowlist entries left out and reported', $s['allowlist'] === array('198.51.100.0/24', '2001:db8::1') && !empty($GLOBALS['__settings_errors']));
t('sanitize: unchecked boxes are off', $s['brute_force'] === true && $s['hide_login_errors'] === false);
update_option(Settings::OPTION, Settings::defaults());

// ---------------------------------------------------------------- brute force
lg_reset();
$G->login_failed('editor-ann', lg_err('incorrect_password'));
$G->login_failed('editor-ann', lg_err('incorrect_password'));
t('two failures: not locked yet', Store::active(LG_IP) === null);
$G->login_failed('editor-ann', lg_err('incorrect_password'));
$row = Store::active(LG_IP);
t('third failure locks the IP out', $row && $row['kind'] === 'lockout' && $row['source'] === 'login' && (int) $row['expires_at'] > time() + 86000);
$auth = $G->authenticate(null);
t('locked out IP cannot sign in', is_wp_error($auth) && $auth->get_error_code() === 'dovetail_login_guard_locked');
t('wp-login.php shows the block page', strpos(lg_dies(array($G, 'enforce_login')), 'wp_die') === 0);
t('blocked requests are counted', (int) Store::active(LG_IP)['hits'] === 2 && (int) Store::active(LG_IP)['last_attempt'] > 0);
t('a lockout does not block the rest of the site', lg_dies(array($G, 'enforce_site')) === '');
$errs = new LG_Errors();
$G->lostpassword($errs, false);
t('locked out IP cannot request a password reset', $errs->codes === array('dovetail_login_guard_locked'));
$G->login_failed('editor-ann', lg_err('dovetail_login_guard_locked'));
t('own refusals are not counted as failures', lg_failures() === 0);

lg_reset();
$G->login_failed('editor-ann', lg_err('incorrect_password'));
$G->login_failed('editor-ann', lg_err('incorrect_password'));
$ann = get_userdata(7); do_action('wp_login', 'editor-ann', $ann);
$G->login_failed('editor-ann', lg_err('incorrect_password'));
t('a successful sign-in clears the failures', Store::active(LG_IP) === null);

lg_reset();
$G->login_failed('nobody', lg_err('invalid_username'));
t('invalid username locks out at once', ($r = Store::active(LG_IP)) && $r['source'] === 'invalid_username' && $r['username'] === 'nobody');

lg_reset();
$G->login_failed('Admin', lg_err('invalid_username'));
$r = Store::active(LG_IP);
t('blocked username blocks the IP from the whole site', $r && $r['kind'] === 'block' && $r['source'] === 'username' && Store::may_have_site_blocks());
t('blocked IP sees the block page everywhere (403)', strpos(lg_dies(array($G, 'enforce_site')), 'wp_die') === 0);
Store::delete_ip(LG_IP);
t('unblocking clears the site-wide marker', !Store::may_have_site_blocks() && lg_dies(array($G, 'enforce_site')) === '');

lg_reset();
$GLOBALS['__users']['admin'] = array('ID' => 1);
$G->login_failed('admin', lg_err('incorrect_password'));
t('a blocked username that is a real account only counts as a failure', Store::active(LG_IP) === null && lg_failures() === 1);
unset($GLOBALS['__users']['admin']);

lg_reset();
update_option(Settings::OPTION, array_merge(Settings::defaults(), array('brute_force' => false)));
$G->login_failed('nobody', lg_err('invalid_username'));
t('brute force protection off: nothing recorded', Store::active(LG_IP) === null);
update_option(Settings::OPTION, array_merge(Settings::defaults(), array('allowlist' => array('203.0.113.0/24'))));
$G->login_failed('nobody', lg_err('invalid_username'));
t('allowlisted IP is never locked out', Store::active(LG_IP) === null && Guard::is_allowlisted(LG_IP));
update_option(Settings::OPTION, Settings::defaults());

// ---------------------------------------------------------------- lost password
lg_reset();
$G->lostpassword(new LG_Errors(), false);
t('first reset request allowed', Store::active(LG_IP) === null);
$G->lostpassword(new LG_Errors(), false);
t('second reset request locks the IP out', ($r = Store::active(LG_IP)) && $r['source'] === 'lostpw');

// ---------------------------------------------------------------- blank User-Agent POST
lg_reset();
$_SERVER = array('REMOTE_ADDR' => LG_IP, 'REQUEST_METHOD' => 'POST', 'HTTP_USER_AGENT' => 'Mozilla/5.0');
t('POST with a User-Agent is allowed', lg_dies(array($G, 'enforce_site')) === '' && Store::active(LG_IP) === null);
$_SERVER = array('REMOTE_ADDR' => LG_IP, 'REQUEST_METHOD' => 'POST', 'SERVER_ADDR' => LG_IP);
t('loopback POST from the server itself is allowed', lg_dies(array($G, 'enforce_site')) === '');
$_SERVER = array('REMOTE_ADDR' => LG_IP, 'REQUEST_METHOD' => 'POST');
t('POST without User-Agent and Referer is blocked', strpos(lg_dies(array($G, 'enforce_site')), 'wp_die') === 0 && Store::active(LG_IP)['source'] === 'blank_ua');
$_SERVER = array('REMOTE_ADDR' => LG_IP);

// ---------------------------------------------------------------- store rules
lg_reset();
Store::add('198.51.100.5', 'block', 'manual', 'm', 0);
Store::add('198.51.100.5', 'lockout', 'login', 'l', 300);
$r = Store::find('198.51.100.5');
t('a rule is never weakened (block and permanent kept)', $r['kind'] === 'block' && (int) $r['expires_at'] === 0 && $r['reason'] === 'm');
Store::add('2001:DB8:0:0::1', 'lockout', 'login', 'v6', 300);
t('IPv6 stored in canonical form', Store::find('2001:db8::1') !== null);
$GLOBALS['wpdb']->query('UPDATE ' . Store::blocks_table() . " SET expires_at = 5 WHERE ip = '2001:db8::1'");
t('expired rule is not active', Store::active('2001:db8::1') === null);
Store::cleanup();
t('cleanup removes expired rules', Store::find('2001:db8::1') === null && Store::find('198.51.100.5') !== null);
t('daily cleanup scheduled', wp_next_scheduled(Store::CLEANUP_HOOK) !== false);

// ---------------------------------------------------------------- admin actions
lg_reset();
$A = new Admin();
$_REQUEST['_wpnonce'] = wp_create_nonce('dovetail_login_guard_block');
$_POST = array('ip' => '198.51.100.77', 'reason' => 'Spam bot', 'duration' => '86400');
t('manual block', lg_redirect(array($A, 'handle_block')) === 'blocked' && ($r = Store::active('198.51.100.77')) && $r['source'] === 'manual' && $r['kind'] === 'block');
$_POST = array('ip' => '999.1.1.1');
t('manual block: invalid IP refused', lg_redirect(array($A, 'handle_block')) === 'invalid_ip');
$_POST = array('ip' => LG_IP);
t('manual block: own IP refused', lg_redirect(array($A, 'handle_block')) === 'own_ip');
Store::add('198.51.100.78', 'lockout', 'username', '=HYPERLINK("x")', 300, '=cmd');
$csv = Admin::csv();
t('export lists the blocks and neutralises formulas', strpos($csv, '198.51.100.77') !== false && strpos($csv, "'=cmd") !== false && strpos($csv, ',=cmd') === false);
$ids = array_map('intval', array_column(Store::query()['rows'], 'id'));
$_REQUEST['_wpnonce'] = wp_create_nonce('dovetail_login_guard_bulk');
$_POST = array('ids' => array($ids[0]), 'bulk' => 'permanent');
t('make permanent', lg_redirect(array($A, 'handle_bulk')) === 'made_permanent' && (int) Store::find('198.51.100.78')['expires_at'] === 0);
$_POST = array('ids' => $ids, 'bulk' => 'unblock');
t('unblock', lg_redirect(array($A, 'handle_bulk')) === 'unblocked' && Store::query()['total'] === 0);
$_POST = array('bulk' => 'unblock');
t('bulk action without selection', lg_redirect(array($A, 'handle_bulk')) === 'none_selected');
$_POST = array();

Store::add('198.51.100.90', 'block', 'blank_ua', 'Blank UA', 3600);
Store::add('198.51.100.91', 'lockout', 'invalid_username', 'Invalid', 3600, 'guest');
$GLOBALS['__q']['admin'] = 1;
do_action('admin_menu');
do_action('admin_init');
t('settings registered and page added', isset($GLOBALS['__settings'][Settings::OPTION]) && in_array(Admin::PAGE, $GLOBALS['__menu'], true));
$_GET = array('page' => Admin::PAGE);
$html = capture(array($A, 'render_page'));
t('Blocking tab lists the blocks', strpos($html, '198.51.100.90') !== false && strpos($html, 'Locked out') !== false && strpos($html, 'guest') !== false && strpos($html, LG_IP) !== false);
$_GET = array('page' => Admin::PAGE, 'source' => 'manual');
t('filter: manual blocks only', strpos(capture(array($A, 'render_page')), '198.51.100.90') === false);
$_GET = array('page' => Admin::PAGE, 's' => '.91');
$f = capture(array($A, 'render_page'));
t('filter: search', strpos($f, '198.51.100.91') !== false && strpos($f, '198.51.100.90') === false);
$_GET = array('page' => Admin::PAGE, 'lg_code' => 'unblocked', 'lg_n' => '2');
t('action notice', strpos(capture(array($A, 'render_page')), '2 IP addresses were unblocked.') !== false);
$_GET = array('page' => Admin::PAGE, 'tab' => 'settings');
$GLOBALS['__users']['admin'] = array('ID' => 1);
$html = capture(array($A, 'render_page'));
t('Settings tab renders', strpos($html, 'Brute Force Protection') !== false && strpos($html, 'Save Login Guard Settings') !== false && strpos($html, 'dovetail_login_guard_settings[allowlist]') !== false);
t('Settings tab names blocked usernames that are real accounts', strpos($html, 'Ignored because an account uses them: admin') !== false);
unset($GLOBALS['__users']['admin']);
$_GET = array();

// ---------------------------------------------------------------- hardening
$H = new Hardening();
$e = $H->generic_login_error(lg_err('invalid_username'));
t('login error hides whether the user exists, keeps the code', $e->get_error_code() === 'invalid_username' && strpos($e->get_error_message(), 'username, email address or password is incorrect') !== false);
t('other errors untouched', $H->generic_login_error(lg_err('empty_password'))->get_error_message() === 'x');
t('admin cannot be registered while it does not exist', in_array('admin', $H->illegal_user_logins(array()), true));
class LG_Req { public $r; function __construct($r) { $this->r = $r; } function get_route() { return $this->r; } }
$GLOBALS['__user'] = array('id' => 0, 'caps' => array());
t('REST users hidden from visitors', is_wp_error($H->block_rest_users(null, null, new LG_Req('/wp/v2/users/1'))) && $H->block_rest_users(null, null, new LG_Req('/wp/v2/posts')) === null);
as_admin();
t('REST users kept for signed-in users', $H->block_rest_users(null, null, new LG_Req('/wp/v2/users')) === null);
t('oEmbed author removed', $H->strip_oembed_author(array('title' => 't', 'author_name' => 'a', 'author_url' => 'u')) === array('title' => 't'));
t('users sitemap removed', $H->remove_users_sitemap('p', 'users') === false && $H->remove_users_sitemap('p', 'posts') === 'p');
t('application passwords disabled', apply_filters('wp_is_application_passwords_available', true) === false);

// ---------------------------------------------------------------- passwords
t('weak password refused', Passwords::weakness('short') !== '' && Passwords::weakness('alllowercaseletters') !== '');
t('strong password accepted', Passwords::weakness('Tr0ub4dor&3-horse', 'ann', 'ann@example.org') === '');
t('password with the username refused', Passwords::weakness('Ann-Is-Great-2026', 'ann', 'x@example.org') !== '');
$pw = 'Password123!';
$hash = strtoupper(sha1($pw));
$GLOBALS['__http_router'] = function ($m, $u) use ($hash) { return array('response' => array('code' => 200), 'body' => strpos($u, substr($hash, 0, 5)) !== false ? "0000000000000000000000000000000000A:0\r\n" . substr($hash, 5) . ':42' : ''); };
t('leaked password found with only the hash prefix sent', Passwords::is_leaked($pw) && end($GLOBALS['__http'])[1] === 'https://api.pwnedpasswords.com/range/' . substr($hash, 0, 5));
t('unknown password not leaked', !Passwords::is_leaked('N0t-In-Any-Breach!'));
$GLOBALS['__http_router'] = function () { return array('response' => array('code' => 503), 'body' => ''); };
t('service down: password accepted', !Passwords::is_leaked($pw));
$GLOBALS['__http_router'] = function () use ($hash) { return array('response' => array('code' => 200), 'body' => substr($hash, 5) . ':42'); };
$P = new Passwords();
$adm = new WP_User(); $adm->ID = 1; $adm->roles = array('administrator'); $adm->user_login = 'boss'; $adm->user_email = 'boss@example.org';
$sub = new WP_User(); $sub->ID = 2; $sub->roles = array('subscriber'); $sub->user_login = 'reader';
$r = $P->refuse_leaked_on_login($adm, 'boss', $pw);
t('admin with a leaked password must reset it', is_wp_error($r) && $r->get_error_code() === 'dovetail_login_guard_leaked');
t('subscriber not checked with "admins only"', $P->refuse_leaked_on_login($sub, 'reader', $pw) === $sub);
$ed = (object) array('ID' => 0, 'role' => 'editor', 'user_login' => 'ed', 'user_email' => 'ed@example.org', 'user_pass' => 'weak');
$sb = (object) array('ID' => 0, 'role' => 'subscriber', 'user_login' => 'sb', 'user_email' => 'sb@example.org', 'user_pass' => 'weak');
$err = new LG_Errors(); $P->profile_errors($err, false, $ed);
t('editor must use a strong password', $err->codes === array('dovetail_login_guard_weak_password'));
$err = new LG_Errors(); $P->profile_errors($err, false, $sb);
t('subscriber not forced with "admins and publishers"', $err->codes === array());
$adm->user_pass = $pw;
$err = new LG_Errors(); $P->profile_errors($err, true, $adm);
t('admin cannot set a leaked password', $err->codes === array('dovetail_login_guard_leaked_password'));
$GLOBALS['__http_router'] = null;

t('no text translated before init', empty($GLOBALS['__early_i18n']), implode(' | ', (array) ($GLOBALS['__early_i18n'] ?? array())));
