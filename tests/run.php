<?php
/**
 * Dovetail Studio test runner. No WordPress, database server or Composer needed.
 *
 *     php tests/run.php            all test files
 *     php tests/run.php newsletter one file (tests/newsletter.php)
 *
 * Every test file runs in its own PHP process against a fresh copy of the plugin
 * in a temporary WordPress-like folder, through the simulator in tests/lib.
 */
if (PHP_SAPI !== 'cli') exit(1);
foreach (array('pdo_sqlite', 'openssl', 'zip', 'mbstring') as $ext) {
    if (!extension_loaded($ext)) { fwrite(STDERR, "Missing PHP extension: $ext\n"); exit(2); }
}
$plugin = dirname(__DIR__);
$files  = glob(__DIR__ . '/*.php');
$files  = array_values(array_filter($files, function ($f) { return basename($f) !== 'run.php'; }));
if (!empty($argv[1])) $files = array(__DIR__ . '/' . basename($argv[1], '.php') . '.php');

$copy = function ($src, $dst) use (&$copy) {
    @mkdir($dst, 0777, true);
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..' || in_array($f, array('.git', 'tests', 'tools', 'node_modules'), true)) continue;
        is_dir("$src/$f") ? $copy("$src/$f", "$dst/$f") : copy("$src/$f", "$dst/$f");
    }
};
$rm = function ($dir) use (&$rm) {
    foreach (array_diff((array) @scandir($dir), array('.', '..')) as $f) { is_dir("$dir/$f") && !is_link("$dir/$f") ? $rm("$dir/$f") : @unlink("$dir/$f"); }
    @rmdir($dir);
};

$total = array('ok' => 0, 'fail' => 0); $failed_files = array();
foreach ($files as $file) {
    $root = sys_get_temp_dir() . '/dovetail-tests-' . bin2hex(random_bytes(4));
    $copy($plugin, "$root/wp-content/plugins/dovetail-studio");
    @mkdir("$root/wp-content/uploads", 0777, true);
    @mkdir("$root/wp-admin/includes", 0777, true);
    file_put_contents("$root/wp-admin/includes/file.php", "<?php\n");
    $cmd = 'DT_TEST_ROOT=' . escapeshellarg($root) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1';
    $out = (string) shell_exec($cmd);
    $rm($root);
    $result = preg_match('/RESULT (\{.*\})/', $out, $m) ? json_decode($m[1], true) : array('ok' => 0, 'fail' => 1);
    if (preg_match('/PHP Fatal error|Uncaught/', $out)) $result['fail']++;   // the file stopped before its last checks
    $total['ok'] += $result['ok']; $total['fail'] += $result['fail'];
    printf("%-28s %3d ok  %2d failed\n", basename($file), $result['ok'], $result['fail']);
    foreach (explode("\n", $out) as $line) {
        if (strpos($line, 'FAIL') === 0 || strpos($line, 'PHP Fatal') === 0) echo "    $line\n";
    }
    if ($result['fail']) $failed_files[] = basename($file);
}
printf("\n%d checks, %d failed\n", $total['ok'] + $total['fail'], $total['fail']);
exit($total['fail'] ? 1 : 0);
