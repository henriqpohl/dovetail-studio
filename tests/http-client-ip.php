<?php
/** Core\Http::client_ip(): which forwarding headers are believed. One PHP process per case (the IP is cached per request). */
require __DIR__ . '/lib/harness.php';

$cases = array(
    'direct visitor, spoofed headers ignored' => array(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'HTTP_CF_CONNECTING_IP' => '8.8.8.8', 'HTTP_X_REAL_IP' => '7.7.7.7'), '', '198.51.100.7'),
    'behind Cloudflare: CF-Connecting-IP' => array(array('REMOTE_ADDR' => '172.68.1.1', 'HTTP_CF_CONNECTING_IP' => '81.2.3.4'), '', '81.2.3.4'),
    'Cloudflare: X-Forwarded-For alone is not believed' => array(array('REMOTE_ADDR' => '172.68.1.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6', 'HTTP_X_REAL_IP' => '7.7.7.7'), '', '172.68.1.1'),
    'Cloudflare: invalid header ignored' => array(array('REMOTE_ADDR' => '172.68.1.1', 'HTTP_CF_CONNECTING_IP' => '<script>'), '', '172.68.1.1'),
    'own load balancer: first untrusted hop from the right' => array(array('REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 5.5.5.5, 10.0.0.2'), '10.0.0.0/8', '5.5.5.5'),
    'own load balancer: X-Real-IP' => array(array('REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_REAL_IP' => '5.5.5.5'), '10.0.0.0/8', '5.5.5.5'),
    'IPv6 visitor' => array(array('REMOTE_ADDR' => '2001:db8::1'), '', '2001:db8::1'),
);
$http = WP_CONTENT_DIR . '/plugins/dovetail-studio/src/Core/Http.php';
foreach ($cases as $name => $c) {
    $code = '<?php define("ABSPATH", "/"); function apply_filters($h, $v) { return $v; } function wp_unslash($v) { return $v; }'
        . ($c[1] !== '' ? ' define("DOVETAIL_STUDIO_TRUSTED_PROXIES", ' . var_export($c[1], true) . ');' : '')
        . ' $_SERVER = ' . var_export($c[0], true) . '; require ' . var_export($http, true) . '; echo \DovetailStudio\Core\Http::client_ip();';
    $f = tempnam(sys_get_temp_dir(), 'ip'); file_put_contents($f, $code);
    $ip = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($f) . ' 2>&1'));
    @unlink($f);
    t($name, $ip === $c[2], $ip);
}
