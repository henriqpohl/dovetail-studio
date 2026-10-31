<?php
/** Newsletter: encrypted Mailchimp key, settings validation, subscription states, rate limit. */
require __DIR__ . '/lib/harness.php';

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9');
as_admin();
boot_plugin();

$clean = dovetail_newsletter_sanitize_settings(array('mode' => 'mailchimp', 'cf7_shortcode' => '[contact-form-7 id="1" title="N"]', 'api_key' => 'abc123-us21', 'audience_id' => 'list1', 'double_opt_in' => '1'));
t('API key saved encrypted', strpos($clean['api_key'], 'dovetail_enc_v1:') === 0);
update_option('dovetail_newsletter_settings', $clean);
t('API key decrypted for use', dovetail_newsletter_get_settings()['api_key'] === 'abc123-us21');
t('blank key field keeps the saved key', dovetail_newsletter_sanitize_settings(array('mode' => 'mailchimp', 'cf7_shortcode' => '[contact-form-7 id="1" title="N"]', 'api_key' => '', 'audience_id' => 'list1'))['api_key'] === $clean['api_key']);
$GLOBALS['__settings_errors'] = array();
t('invalid mode keeps the current settings and reports an error', dovetail_newsletter_sanitize_settings(array('mode' => 'x')) === $clean && !empty($GLOBALS['__settings_errors']));
$page = capture('dovetail_newsletter_settings_page');
t('settings page prints the save result', strpos($page, 'dovetail-version') !== false);

/* Mailchimp replies keyed by HTTP method. */
function mc($method, $code, $body) { $GLOBALS['__mc'][$method] = array('response' => array('code' => $code), 'body' => json_encode($body)); }
function dt_mc_reply($method) { return $GLOBALS['__mc'][$method] ?? array('response' => array('code' => 500), 'body' => ''); }

$cases = array(
    'new contact'            => array(array('GET' => array(404, array()), 'PUT' => array(200, array('status' => 'pending'))), 'check your email', array('GET', 'PUT')),
    'already subscribed'     => array(array('GET' => array(200, array('status' => 'subscribed'))), 'already subscribed', array('GET')),
    'unsubscribed, rejoins'  => array(array('GET' => array(200, array('status' => 'unsubscribed')), 'PATCH' => array(200, array('status' => 'pending'))), 'check your email', array('GET', 'PATCH')),
    'pending, reminded'      => array(array('GET' => array(200, array('status' => 'pending'))), 'check your email', array('GET')),
    'cleaned, refused'       => array(array('GET' => array(200, array('status' => 'cleaned')), 'PATCH' => array(400, array('title' => 'Member In Compliance State'))), 'could not complete', array('GET', 'PATCH')),
    'Mailchimp down'         => array(array('GET' => array(500, array())), 'could not complete', array('GET')),
);
foreach ($cases as $name => $case) {
    $GLOBALS['__mc'] = array();
    foreach ($case[0] as $method => $reply) mc($method, $reply[0], $reply[1]);
    $GLOBALS['__http'] = array();
    $GLOBALS['wpdb']->query("DELETE FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'");
    $GLOBALS['__http_router'] = 'dt_mc_reply';
    $r = dovetail_newsletter_subscribe('a@example.org');
    $msg = is_wp_error($r) ? $r->get_error_message() : $r['message'];
    $methods = array_map(function ($h) { return $h[0]; }, $GLOBALS['__http']);
    t("Mailchimp: $name", stripos($msg, $case[1]) !== false && $methods === $case[2], $msg . ' / ' . implode(',', $methods));
}
$last = end($GLOBALS['__http']);
t('Mailchimp URL and auth', strpos($last[1], 'https://us21.api.mailchimp.com/3.0/lists/list1/members/' . md5('a@example.org')) === 0 && $last[2]['headers']['Authorization'] === 'Basic ' . base64_encode('wordpress:abc123-us21'));

$GLOBALS['__mc'] = array(); mc('GET', 200, array('status' => 'subscribed'));
$GLOBALS['wpdb']->query("DELETE FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'");
$c = array(); for ($i = 0; $i < 6; $i++) { $x = dovetail_newsletter_subscribe('b@example.org'); $c[] = is_wp_error($x) ? $x->get_error_code() : 'ok'; }
t('5 attempts per 10 minutes per IP', in_array('dovetail_newsletter_rate_limited', $c, true) && count(array_keys($c, 'ok')) === 5);

// ---------------------------------------------------------------- merge fields, tags, "Both" mode
$both = dovetail_newsletter_sanitize_settings(array('mode' => 'both', 'cf7_shortcode' => '[contact-form-7 id="1" title="N"]', 'api_key' => '', 'audience_id' => 'list1', 'double_opt_in' => '1', 'merge_fields' => "fname = your-name\nbad line\nPHONE=your-phone", 'tags' => 'Website, Footer form, Website'));
t('merge fields and tags sanitized', $both['merge_fields'] === "FNAME=your-name\nPHONE=your-phone" && $both['tags'] === 'Website, Footer form');
update_option('dovetail_newsletter_settings', $both);

class Fake_Tag { public $basetype; public $name; function __construct($t, $n) { $this->basetype = $t; $this->name = $n; } }
class Fake_Form { function id() { return 1; } function scan_form_tags() { return array(new Fake_Tag('text', 'your-name'), new Fake_Tag('email', 'your-email')); } }
class Fake_Submission { public $props = array(); public $response = ''; public $data; function __construct($d) { $this->data = $d; } function get_posted_data($k) { return $this->data[$k] ?? ''; } function add_result_props($p) { $this->props = array_merge($this->props, $p); } function set_response($r) { $this->response = $r; } }
$_POST['_dovetail_newsletter'] = wp_hash('dovetail-newsletter:1');
$GLOBALS['wpdb']->query("DELETE FROM wp_options WHERE option_name LIKE 'dovetail\\_rl\\_%'");

$GLOBALS['__mc'] = array(); mc('GET', 404, array()); mc('PUT', 200, array('status' => 'pending')); mc('POST', 204, array());
$GLOBALS['__http'] = array();
$abort = false; $sub = new Fake_Submission(array('your-name' => 'Ann Lee', 'your-email' => 'ann@example.org'));
dovetail_newsletter_before_send(new Fake_Form(), $abort, $sub);
$put = null; $post = null; foreach ($GLOBALS['__http'] as $h) { if ($h[0] === 'PUT') $put = $h; if ($h[0] === 'POST') $post = $h; }
t('merge fields sent to Mailchimp', $put && json_decode($put[2]['body'], true)['merge_fields'] === array('FNAME' => 'Ann Lee'));
t('tags added after subscribing', $post && substr($post[1], -5) === '/tags' && array_column(json_decode($post[2]['body'], true)['tags'], 'name') === array('Website', 'Footer form'));
t('subscription message kept for the form', !$abort && isset($sub->props['dovetail_newsletter_message']));

$GLOBALS['__mc'] = array(); mc('GET', 500, array());
$abort = false; $sub = new Fake_Submission(array('your-name' => 'Bob', 'your-email' => 'bob@example.org'));
dovetail_newsletter_before_send(new Fake_Form(), $abort, $sub);
t('"Both": a Mailchimp failure does not stop the form email', $abort === false && isset($sub->props['dovetail_newsletter_error']));
$res = dovetail_newsletter_submission_result(array('status' => 'mail_sent', 'message' => 'Thanks.', 'dovetail_newsletter_error' => 'We could not complete your subscription.'));
t('"Both": the visitor is told the subscription failed', strpos($res['message'], 'Thanks.') === 0 && strpos($res['message'], 'could not be completed') !== false && !isset($res['dovetail_newsletter_error']));

update_option('dovetail_newsletter_settings', array_merge($both, array('mode' => 'mailchimp')));
$abort = false; $sub = new Fake_Submission(array('your-email' => 'cy@example.org'));
dovetail_newsletter_before_send(new Fake_Form(), $abort, $sub);
t('"Mailchimp" mode still stops on failure', $abort === true);
