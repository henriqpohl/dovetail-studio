<?php
/** Virtual Assistant: opening hours in the site time zone, and translations reaching front.js and the server. */
require __DIR__ . '/lib/harness.php';

$GLOBALS['__tz'] = 'America/Sao_Paulo';
$GLOBALS['__locale'] = 'pt_BR';
$GLOBALS['__tr'] = array(
    'Budget' => 'Orçamento',
    'Hello. I\'m the AI Assistant at %s.' => 'Olá. Sou o assistente de IA da %s.',
    'Have a question?' => 'Tem uma pergunta?',
    'Thanks for your enquiry to %1$s. We have received it, reference %2$s.' => 'Obrigado pelo contato com %1$s. Recebemos sua mensagem, referência %2$s.',
);
as_visitor();
boot_plugin();

update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '11 1234 5678', 'COMPANY_EMAIL' => 'oi@example.com', 'OPENING_HOURS' => array('open' => 8.5, 'close' => 18.0, 'days' => array(1, 2, 3, 4, 5, 6))));
$h = dt_va_hours_config();
t('front.js gets the site time zone', $h['timezone'] === 'America/Sao_Paulo');
t('days label from the saved days', dt_va_days_label() === 'Monday to Saturday', dt_va_days_label());
t('short days label', dt_va_days_label(true) === 'Mon to Sat', dt_va_days_label(true));
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '11 1234 5678', 'COMPANY_EMAIL' => 'oi@example.com', 'OPENING_HOURS' => array('open' => 9.0, 'close' => 17.0, 'days' => array(1, 3, 5))));
t('non-contiguous days', dt_va_days_label() === 'Monday, Wednesday and Friday', dt_va_days_label());
$next = dt_va_next_opening(true);
t('next opening is on an open day at the opening time', $next && in_array((int) $next->format('w'), array(1, 3, 5), true) && $next->format('H:i') === '09:00' && $next->getTimezone()->getName() === 'America/Sao_Paulo');
t('reopens text uses the opening time', (bool) preg_match('/at 9:00 am|on (Monday|Wednesday|Friday) at 9:00 am/', dt_va_reopens()), dt_va_reopens());
$ics = dt_va_callback_ics('Ana', '', '0871234567', '', 'brief', 'DT-2610-ABCDEF');
t('call-back invite at the next opening (UTC in the file)', strpos($ics, 'DTSTART:' . (clone $next)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z')) !== false);
t('invite UID uses the site domain', strpos($ics, '@example.com') !== false && strpos($ics, 'designminds') === false);

$cfg = req('/dt-va/v1/config');
t('translations sent to front.js', ($cfg->data['i18n']['Hello. I\'m the AI Assistant at %s.'] ?? '') === 'Olá. Sou o assistente de IA da %s.');
t('only translated strings are sent', !isset($cfg->data['i18n']['Enquiry']));
$html = capture(function () { do_action('wp_footer'); });
t('static panel markup translated', strpos($html, 'Tem uma pergunta?') !== false);
t('footer days label from settings', strpos($html, '<span id="dt-va-hours">Mon, Wed and Fri</span>') !== false, preg_match('~<span id="dt-va-hours">[^<]*</span>~', $html, $m) ? $m[0] : '');

// The server reads brief labels with the same translations front.js used.
t('brief labels read in the site language', strpos(dt_va_glance("Orçamento: 10 mil\nTiming: x", '', 0, false, '', '', '', ''), 'budget given (10 mil)') !== false);
$aged = aged_token();
$GLOBALS['__mail'] = array();
req('/dt-va/v1/submit', array('name' => 'Ana', 'email' => 'ana@example.org', 'phone' => '11912345678', 'brief' => "Orçamento: 10 mil", 'token' => $aged));
t('visitor email translated', strpos($GLOBALS['__mail'][1]['b'] ?? '', 'Obrigado pelo contato com') !== false);

// Every msgid used by front.js is listed for translation.
$js = file_get_contents(WP_CONTENT_DIR . '/plugins/dovetail-studio/modules/virtual-assistant/assets/js/front.js');
preg_match_all('/(?<![\w$.])(?:__|_f)\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $js, $m);
$missing = array_diff(array_map(function ($x) { return json_decode('"' . $x . '"'); }, $m[1]), array_keys(dt_va_js_strings()));
t('i18n-js.php is up to date with front.js (run php tools/build.php)', !$missing, implode(' | ', array_slice($missing, 0, 3)));

// ---------------------------------------------------------------- closed dates (holidays)
$today = new DateTime('now', wp_timezone());
$in    = function ($n) use ($today) { $d = clone $today; $d->modify("+$n day"); return $d->format('Y-m-d'); };
$parsed = dt_va_parse_closed_dates("2020-01-01\n" . $in(1) . ' to ' . $in(12) . "\n" . $in(20));
t('closed dates: past dropped, ranges and single days kept', is_array($parsed) && count($parsed) === 2 && $parsed[0] === array('from' => $in(1), 'to' => $in(12)) && $parsed[1]['from'] === $parsed[1]['to']);
t('closed dates: a bad line is refused', is_wp_error(dt_va_parse_closed_dates("next monday")) && is_wp_error(dt_va_parse_closed_dates('2026-02-30')));
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '11 1234 5678', 'COMPANY_EMAIL' => 'oi@example.com', 'OPENING_HOURS' => array('open' => 0.0, 'close' => 24.0, 'days' => array(0, 1, 2, 3, 4, 5, 6), 'closed' => array(array('from' => $in(0), 'to' => $in(12))))));
t('closed today: not open, even within opening hours', !dt_va_studio_open());
$next = dt_va_next_opening();
t('next opening skips the closure', $next && $next->format('Y-m-d') === $in(13), $next ? $next->format('Y-m-d') : 'none');
t('reopening beyond a week is named by date', (bool) preg_match('/^on \d{1,2} \w+ at /', dt_va_reopens()), dt_va_reopens());
t('closed dates reach front.js', (req('/dt-va/v1/config')->data['publicConfig']['OPENING_HOURS']['closed'][0]['to'] ?? '') === $in(12));
