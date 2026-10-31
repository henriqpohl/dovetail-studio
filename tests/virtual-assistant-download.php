<?php
/** Virtual Assistant: enquiry attachments are downloaded only through WP Admin. (Own process: the handler exits.) */
require __DIR__ . '/lib/harness.php';

as_visitor();
boot_plugin();
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '01 234 5678', 'COMPANY_EMAIL' => 'hello@example.com'));
$tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, '%PDF-1.4 secret');
$r = req('/dt-va/v1/submit', array('name' => 'Ann', 'email' => 'a@example.org', 'phone' => '0871234567', 'brief' => 'x', 'token' => aged_token()), '', array('attachment' => array('name' => 'brief.pdf', 'tmp_name' => $tmp, 'size' => 15, 'error' => 0, 'type' => 'application/pdf')));
$pid = max(array_keys($GLOBALS['__posts']));

// A visitor (no capability) is refused.
$_GET = $_REQUEST = array('id' => $pid, '_wpnonce' => wp_create_nonce('dt_va_download_' . $pid));
try { do_action('admin_post_dt_va_download'); t('visitor refused', false); } catch (Exception $e) { t('visitor refused', strpos($e->getMessage(), 'wp_die') === 0 || strpos($e->getMessage(), 'nonce') === 0); }

// The administrator gets the file; the handler exits, so the check runs at shutdown.
as_admin();
$_GET = $_REQUEST = array('id' => $pid, '_wpnonce' => wp_create_nonce('dt_va_download_' . $pid));
ob_start(function ($out) { return ''; });
register_shutdown_function(function () { $o = ob_get_contents(); ob_end_clean(); t('admin downloads the file', strpos((string) $o, '%PDF-1.4 secret') === 0); });
do_action('admin_post_dt_va_download');
