<?php
/** Virtual Assistant personal data: sender address, export/erase tools, retention, attachment clean-up, privacy text. */
require __DIR__ . '/lib/harness.php';

$_SERVER = array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_ORIGIN' => 'https://example.com');
as_visitor();
boot_plugin();
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '01 234 5678', 'COMPANY_EMAIL' => 'hello@example.com'));
update_option('dt_va_company_to', 'team@gmail.com');

// ---------------------------------------------------------------- sender
$aged = aged_token();
$GLOBALS['__mail'] = array();
$tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, '%PDF-1.4 test');
$r = req('/dt-va/v1/submit', array('name' => 'Ann Lee', 'email' => 'ann@example.org', 'phone' => '0871234567', 'brief' => 'Business: bakery', 'token' => $aged, 't' => (string) ((time() - 60) * 1000)), '', array('attachment' => array('name' => 'brief.pdf', 'tmp_name' => $tmp, 'size' => 14, 'error' => 0, 'type' => 'application/pdf')));
t('enquiry sent', $r->status === 200 && !empty($r->data['ok']));
$studio = $GLOBALS['__mail'][0]; $client = $GLOBALS['__mail'][1];
$from = function ($m) { foreach ($m['h'] as $h) if (stripos($h, 'From:') === 0) return $h; return ''; };
$reply = function ($m) { foreach ($m['h'] as $h) if (stripos($h, 'Reply-To:') === 0) return $h; return ''; };
t('studio email sent from the site domain, not the Gmail inbox', strpos($from($studio), '<wordpress@example.com>') !== false && strpos($from($studio), 'gmail') === false, $from($studio));
t('studio email replies to the visitor', strpos($reply($studio), 'ann@example.org') !== false);
t('visitor email sent from the site domain, replies to the team', strpos($from($client), '<wordpress@example.com>') !== false && strpos($reply($client), 'team@gmail.com') !== false);
t('sender name defaults to the site title', strpos($from($studio), 'Example Site') !== false || strpos($from($studio), get_bloginfo('name')) !== false, $from($studio));
update_option('dt_va_company_from', 'hello@example.com');
t('a saved sender email is used', strpos(dt_va_mail_headers()[0], '<hello@example.com>') !== false);

// ---------------------------------------------------------------- export / erase
$pid = max(array_keys($GLOBALS['__posts']));
$file = dt_va_attachment_path($pid);
$exporters = apply_filters('wp_privacy_personal_data_exporters', array());
$erasers = apply_filters('wp_privacy_personal_data_erasers', array());
t('exporter and eraser registered', isset($exporters['dt-va-enquiries'], $erasers['dt-va-enquiries']));
$export = call_user_func($exporters['dt-va-enquiries']['callback'], 'ann@example.org', 1);
$values = $export['data'] ? array_column($export['data'][0]['data'], 'value') : array();
t('export has the enquiry with its details', count($export['data']) === 1 && in_array('0871234567', $values, true) && in_array('Business: bakery', array_map(function ($v) { return strtok($v, "\n"); }, $values), true) && $export['done']);
t('another address exports nothing', call_user_func($exporters['dt-va-enquiries']['callback'], 'bob@example.org', 1)['data'] === array());
t('attachment on disk before erasing', $file !== '' && is_file($file));
$erase = call_user_func($erasers['dt-va-enquiries']['callback'], 'ann@example.org', 1);
t('eraser removes the enquiry', $erase['items_removed'] === 1 && !isset($GLOBALS['__posts'][$pid]));
t('and its attachment file', !is_file($file));

// ---------------------------------------------------------------- retention
$old = wp_insert_post(array('post_type' => 'dt_enquiry', 'post_status' => 'private', 'post_title' => 'old', 'meta_input' => array('email' => 'x@example.org')));
$GLOBALS['__posts'][$old]->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-25 months'));
$new = wp_insert_post(array('post_type' => 'dt_enquiry', 'post_status' => 'private', 'post_title' => 'new', 'meta_input' => array('email' => 'y@example.org')));
t('retention default is 24 months', dt_va_retention_months() === 24);
do_action('dt_va_daily');
t('daily run deletes enquiries past the retention period only', !isset($GLOBALS['__posts'][$old]) && isset($GLOBALS['__posts'][$new]));
update_option('dt_va_retention_months', 0);
$GLOBALS['__posts'][$new]->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-10 years'));
dt_va_purge_old_enquiries();
t('0 keeps enquiries', isset($GLOBALS['__posts'][$new]));

// ---------------------------------------------------------------- privacy policy text
update_option('dt_va_retention_months', 12);
do_action('admin_init');
$text = implode('', $GLOBALS['__privacy'] ?? array());
t('privacy policy text suggested, with the retention period', strpos($text, 'OpenAI or Anthropic') !== false && strpos($text, '12 months') !== false);
