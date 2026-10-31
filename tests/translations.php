<?php
/** Translations: the .pot covers the whole plugin (English source); any languages/*.po added later must be complete. */
require __DIR__ . '/lib/harness.php';

$lang = WP_CONTENT_DIR . '/plugins/dovetail-studio/languages';

/** Reads a GNU .mo file into msgid => msgstr (context prefixed with "ctx\x04", plurals split on "\0"). */
function read_mo($file) {
    $b = (string) file_get_contents($file);
    if (strlen($b) < 28) return null;
    $magic = unpack('V', substr($b, 0, 4))[1];
    if ($magic !== 0x950412de) return null;
    $h = unpack('V3', substr($b, 8, 12)); $n = $h[1]; $o = $h[2]; $t = $h[3];
    $out = array();
    for ($i = 0; $i < $n; $i++) {
        $os = unpack('V2', substr($b, $o + $i * 8, 8)); $ts = unpack('V2', substr($b, $t + $i * 8, 8));
        $out[substr($b, $os[2], $os[1])] = substr($b, $ts[2], $ts[1]);
    }
    return $out;
}

/** Reads the msgids of a .pot (with context and plural) without any gettext library. */
function read_pot($file) {
    $un = function ($s) { return stripcslashes(substr($s, 1, -1)); };
    $items = array(); $cur = array(); $field = null;
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d\])?) (".*")$/', $line, $m)) {
            if ($m[1] === 'msgctxt' || ($m[1] === 'msgid' && !isset($cur['msgctxt']))) { if (isset($cur['msgid'])) $items[] = $cur; $cur = array(); }
            $field = $m[1]; $cur[$field] = $un($m[2]);
        } elseif ($field && preg_match('/^".*"$/', $line)) {
            $cur[$field] .= $un($line);
        }
    }
    if (isset($cur['msgid'])) $items[] = $cur;
    return array_values(array_filter($items, function ($i) { return $i['msgid'] !== ''; }));
}

$pot = read_pot("$lang/dovetail-studio.pot");
$ids = array_column($pot, 'msgid');
t('.pot lists every translatable string', count($pot) > 900, (string) count($pot));
t('.pot has the Studio screens', !array_diff(array('Installed Modules', 'Save Modules', 'Upload Module', 'Remove Module', 'Dashboard'), $ids));
t('.pot has the module names and descriptions', in_array('Virtual Assistant', $ids, true) && (bool) preg_grep('/^Newsletter forms with Contact Form 7/', $ids));
t('source language is English: no bundled translation', !glob("$lang/*.po") && !glob("$lang/*.mo"));

// A translation added later (languages/dovetail-studio-{locale}.po, compiled by tools/build.php) must be complete.
$ph = function ($s) { preg_match_all('/%(?:\d+\$)?[sd]|\{\{[A-Z_]+\}\}/', $s, $m); sort($m[0]); return $m[0]; };
$tags = function ($s) { preg_match_all('~</?[a-z]+~', $s, $m); sort($m[0]); return $m[0]; };
foreach (glob("$lang/*.po") as $po) {
    $name = basename($po, '.po');
    $mo = read_mo("$lang/$name.mo");
    t("$name: compiled .mo is valid and current", is_array($mo) && filemtime("$lang/$name.mo") >= filemtime($po));
    $missing = array(); $bad = array();
    foreach ($pot as $it) {
        $key = (isset($it['msgctxt']) ? $it['msgctxt'] . "\x04" : '') . $it['msgid'] . (isset($it['msgid_plural']) ? "\0" . $it['msgid_plural'] : '');
        if (!isset($mo[$key]) || trim($mo[$key], "\0") === '') { $missing[] = $it['msgid']; continue; }
        $forms = explode("\0", $mo[$key]);
        // the singular of a plural may say "One" instead of %d, so only the plural form must keep every placeholder
        $pair = isset($it['msgid_plural']) ? array($it['msgid_plural'], $forms[1] ?? '') : array($it['msgid'], $forms[0]);
        if ($ph($pair[0]) !== $ph($pair[1]) || $tags($pair[0]) !== $tags($pair[1])) $bad[] = $it['msgid'];
    }
    t("$name: every string translated", !$missing, implode(' | ', array_slice($missing, 0, 5)));
    t("$name: placeholders and HTML kept", !$bad, implode(' | ', array_slice($bad, 0, 5)));
}
