<?php
/** Virtual Assistant: REST endpoints, visitor token, enquiry mail, attachments, events, digest. */
require __DIR__ . '/lib/harness.php';

as_visitor();
boot_plugin();

t('module loaded', defined('DOVETAIL_VIRTUAL_ASSISTANT_VERSION'));
t('version comes from module.php', DOVETAIL_VIRTUAL_ASSISTANT_VERSION === (require WP_CONTENT_DIR . '/plugins/dovetail-studio/modules/virtual-assistant/module.php')['version']);
$routes = array_keys($GLOBALS['__routes']);
foreach (array('/dt-va/v1/token', '/dt-va/v1/config', '/dt-va/v1/ask', '/dt-va/v1/submit', '/dt-va/v1/event') as $r) t("route $r", in_array($r, $routes, true));
t('no /front hot-patch route', !in_array('/dt-va/v1/front', $routes, true));
t('enquiries need manage_options', ($GLOBALS['__cpt']['dt_enquiry']['capabilities']['read_private_posts'] ?? '') === 'manage_options' && ($GLOBALS['__cpt']['dt_enquiry']['capabilities']['edit_posts'] ?? '') === 'manage_options');
t('cron events scheduled', isset($GLOBALS['__cron']['dt_va_selfcheck'], $GLOBALS['__cron']['dt_va_weekly'], $GLOBALS['__cron']['dt_va_daily'], $GLOBALS['__cron']['dt_va_flush_events']));
t('enquiry email defaults to admin_email', dt_va_company_value('to') === 'admin@example.com');

// ---------------------------------------------------------------- front end
$html = capture(function () { do_action('wp_enqueue_scripts'); do_action('wp_head'); do_action('wp_footer'); });
t('hidden until phone and email are set', strpos($html, 'DT_VA_LOADER') === false && strpos($html, 'dt-va-tab') === false);
t('config endpoint off until configured', req('/dt-va/v1/config')->status === 404);
update_option('dt_va_public', array('COMPANY_PHONE_DISPLAY' => '01 234 5678', 'COMPANY_EMAIL' => 'hello@example.com'));
$html = capture(function () { do_action('wp_enqueue_scripts'); do_action('wp_head'); do_action('wp_footer'); });
t('page carries only the loader config', strpos($html, 'window.DT_VA_LOADER') !== false && strpos($html, 'DT_VA_SERVER') === false);
t('tab in the page, panel in an inert template', strpos($html, 'id="dt-va-tab"') !== false && strpos($html, '<template id="dt-va-panel-tpl"><section') !== false);
t('only the small loader script is enqueued', in_array('dt-va-loader', $GLOBALS['__enq'], true) && preg_match('~"script":"[^"]*front(\.min)?\.js~', $html));
t('no studio-specific data in the output', stripos($html, 'design minds') === false && stripos($html, 'malahide') === false);
$cfg = req('/dt-va/v1/config');
t('config endpoint', $cfg->status === 200 && isset($cfg->data['token'], $cfg->data['hours']['timezone'], $cfg->data['i18n']));
t('config is cacheable for 5 minutes', ($cfg->headers['Cache-Control'] ?? '') === 'public, max-age=300');
t('English site sends no translations', $cfg->data['i18n'] === array());

// ---------------------------------------------------------------- token + ask
$r = req('/dt-va/v1/ask', array('q' => 'hello'));
t('ask without token is refused', $r->status === 403 && $r->data['error'] === 'token');
$tok = req('/dt-va/v1/token');
t('token response is not cached', ($tok->headers['Cache-Control'] ?? '') === 'no-store, private');
$tok = $tok->data['token'];
$r = req('/dt-va/v1/ask', array('q' => 'hello', 'token' => $tok));
t('ask without a provider: not_configured', $r->status === 503 && $r->data['error'] === 'not_configured');
dt_va_save_key('openai', 'sk-test-123');
update_option('dt_va_providers', array('primary' => 'openai', 'secondary' => 'none', 'openai_model' => 'gpt-4.1-mini'));
t('AI key stored encrypted', strpos(get_option('dt_va_openai_key'), 'dovetail_enc_v1:') === 0 && dt_va_provider_key('openai') === 'sk-test-123');
$GLOBALS['__http_reply'] = array('response' => array('code' => 200), 'body' => json_encode(array('status' => 'completed', 'output' => array(array('type' => 'message', 'content' => array(array('type' => 'output_text', 'text' => 'We do branding.')))))));
$r = req('/dt-va/v1/ask', array('q' => 'what do you do', 'token' => $tok, 'history' => array(array('role' => 'user', 'content' => 'hi'), array('role' => 'assistant', 'content' => 'Hello'))));
t('ask answers', $r->status === 200 && $r->data['answer'] === 'We do branding.');
$last = end($GLOBALS['__http']);
t('provider called with the decrypted key', ($last[2]['headers']['Authorization'] ?? '') === 'Bearer sk-test-123');
$body = json_decode($last[2]['body'], true);
t('system prompt: public phone, no studio data, visitor language rule', strpos($body['instructions'], '01 234 5678') !== false && stripos($body['instructions'], 'design minds') === false && strpos($body['instructions'], 'language the visitor writes in') !== false);
t('history passed to the model', count($body['input']) === 3);
t('ask deadline is 15 s by default', DT_VA_ASK_DEADLINE === 15);
$door_prompt = dt_va_system_prompt('Websites');
t('system prompt: the cacheable part is the same for every door, the door goes last', strpos($door_prompt['cached'], 'Websites') === false && strpos($door_prompt['turn'], 'Websites') !== false && $door_prompt['cached'] === dt_va_system_prompt('Logos')['cached']);
t('OpenAI: the door is at the end of the instructions', substr($body['instructions'], -strlen(dt_va_system_prompt('')['turn'])) === dt_va_system_prompt('')['turn']);
$GLOBALS['__http'] = array();
dt_va_provider_request('anthropic', array(array('role' => 'user', 'content' => 'hi')), $door_prompt, 5, array('anthropic_model' => 'claude-x', 'max_tokens' => 100));
$anthropic = json_decode(end($GLOBALS['__http'])[2]['body'], true);
t('Anthropic: knowledge base block marked for the prompt cache', ($anthropic['system'][0]['cache_control']['type'] ?? '') === 'ephemeral' && strpos($anthropic['system'][1]['text'], 'Websites') !== false);
$saved_at = get_option('dt_va_last_provider_result')['at'] ?? 0;
update_option('dt_va_last_provider_result', array('at' => $saved_at - 60, 'attempts' => get_option('dt_va_last_provider_result')['attempts']));
req('/dt-va/v1/ask', array('q' => 'and logos?', 'token' => $tok));
t('diagnostics are not rewritten on every question', (get_option('dt_va_last_provider_result')['at'] ?? 0) === $saved_at - 60);

// ---------------------------------------------------------------- submit
$fresh = dt_va_token_issue();
$r = req('/dt-va/v1/submit', array('name' => 'Ann', 'email' => 'victim@example.org', 'phone' => '0871234567', 'brief' => 'x', 'token' => $fresh));
t('submit refuses a token younger than 3 s', $r->status === 403 && $r->data['error'] === 'token');
$aged = aged_token();
$tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, '%PDF-1.4 test');
$GLOBALS['__mail'] = array();
$brief = "Enquiry: Enquiry\nBusiness: CLICK http://evil.example now\nBudget: 5k\nTiming: soon";
$r = req('/dt-va/v1/submit', array('name' => "Ann\r\nBcc: x@y.z", 'email' => 'victim@example.org', 'phone' => '0871234567', 'brief' => $brief, 'token' => $aged, 't' => (string) ((time() - 60) * 1000)), '', array('attachment' => array('name' => 'My Brief.pdf', 'tmp_name' => $tmp, 'size' => 14, 'error' => 0, 'type' => 'application/pdf')));
t('submit ok', $r->status === 200 && !empty($r->data['ok']), json_encode($r->data));
t('reference format DT-YYMM-XXXXXX', (bool) preg_match('/^DT-\d{4}-[23456789A-HJ-NP-Z]{6}$/', $r->data['ref'] ?? ''), $r->data['ref'] ?? '');
t('two emails', count($GLOBALS['__mail']) === 2);
$studio = $GLOBALS['__mail'][0]; $client = $GLOBALS['__mail'][1] ?? array('b' => '', 'to' => '', 'h' => array());
t('studio email has the brief, to admin_email', strpos($studio['b'], 'CLICK http://evil.example') !== false && $studio['to'] === 'admin@example.com');
t('at a glance reads the budget from the brief', strpos($studio['b'], 'budget given (5k)') !== false);
t('Reply-To has no line breaks', strpos(implode('|', $studio['h']), "\r") === false && strpos(implode('|', $studio['h']), "\n") === false);
t('visitor email has no visitor text', $client['to'] === 'victim@example.org' && strpos($client['b'], 'evil') === false && strpos($client['b'], 'Ann') === false);
$pid = max(array_keys($GLOBALS['__posts']));
$rel = get_post_meta($pid, 'file_path', true);
t('attachment: random name in uploads/dt-va', $rel !== '' && strpos($rel, 'dt-va/') === 0 && strpos($rel, 'Brief') === false && is_file(dt_va_attachment_path($pid)));
t('attachment folder denies direct access', is_file(WP_CONTENT_DIR . '/uploads/dt-va/.htaccess') && strpos(file_get_contents(WP_CONTENT_DIR . '/uploads/dt-va/.htaccess'), 'Require all denied') !== false);
t('original file name kept for the download', get_post_meta($pid, 'file_original', true) === 'My-Brief.pdf');

// Call back without email: no visitor email, no Reply-To.
$GLOBALS['__mail'] = array();
$r = req('/dt-va/v1/submit', array('name' => 'Bob', 'email' => '', 'phone' => '0871234567', 'callback' => '1', 'brief' => 'Call back', 'token' => $aged));
t('call back accepted without email', $r->status === 200 && !empty($r->data['ok']), json_encode($r->data));
t('only the studio email is sent', count($GLOBALS['__mail']) === 1 && strpos(implode('|', $GLOBALS['__mail'][0]['h']), 'Reply-To') === false && strpos($GLOBALS['__mail'][0]['b'], 'No email given') !== false);
$r = req('/dt-va/v1/submit', array('name' => 'Bob', 'email' => '', 'phone' => '0871234567', 'brief' => 'x', 'token' => $aged));
t('an enquiry without call back still needs an email', $r->status === 400 && $r->data['error'] === 'fields');

$GLOBALS['__mail'] = array();
$r = req('/dt-va/v1/submit', array('name' => 'B', 'email' => 'b@example.org', 'phone' => '0871234567', 'website2' => 'spam', 'token' => $aged));
t('honeypot answers success and sends nothing', $r->data['ref'] === 'DT-0000' && !$GLOBALS['__mail']);
$codes = array();
for ($i = 0; $i < 10; $i++) $codes[] = req('/dt-va/v1/submit', array('name' => 'C', 'email' => 'c@example.org', 'phone' => '0871234567', 'brief' => 'x', 'token' => $aged))->status;
t('submit is rate limited per IP', in_array(429, $codes, true));

// ---------------------------------------------------------------- events, flush, digest
foreach (array('open', 'open', 'door', 'step', 'ask', 'feedback', 'nudge_open') as $n) {
    req('/dt-va/v1/event', array(), json_encode(array('n' => $n, 'door' => 'Enquiry', 'step' => $n === 'ask' ? 'openai' : 'describe', 'useful' => 'yes', 'page' => '/services/', 'token' => $aged)));
}
t('events need the visitor token', req('/dt-va/v1/event', array(), json_encode(array('n' => 'open')))->status === 403);
t('unknown events refused', req('/dt-va/v1/event', array(), json_encode(array('n' => 'evil', 'token' => $aged)))->status === 400);
$rows = $GLOBALS['wpdb']->get_results("SELECT option_name FROM wp_options WHERE option_name LIKE 'dt\\_va\\_evc\\_%'");
t('each counter is its own row', count($rows) >= 6);
dt_va_flush_events();
$wk = get_option(dt_va_week_key());
t('flush folds counters into the week', ($wk['n']['open'] ?? 0) === 2 && ($wk['doors']['Enquiry'] ?? 0) === 1 && ($wk['q']['answer | openai'] ?? 0) === 1 && ($wk['pages']['/services/'] ?? 0) === 1);
t('counter rows removed', count($GLOBALS['wpdb']->get_results("SELECT option_name FROM wp_options WHERE option_name LIKE 'dt\\_va\\_ev%'")) === 0);
req('/dt-va/v1/event', array(), json_encode(array('n' => 'open', 'token' => $aged))); dt_va_flush_events();
t('later flushes add up', (get_option(dt_va_week_key())['n']['open'] ?? 0) === 3);
update_option('dt_va_stats_2020-20', array('n' => array('open' => 1)));
update_option(dt_va_week_key(), get_option(dt_va_week_key()));
$GLOBALS['__mail'] = array(); dt_va_weekly_digest();
t('weekly digest sent', count($GLOBALS['__mail']) === 1 && strpos($GLOBALS['__mail'][0]['s'], 'weekly digest') !== false);
t('stats older than 8 weeks deleted, current kept', get_option('dt_va_stats_2020-20') === false && get_option(dt_va_week_key()) !== false);
dt_va_daily_reminder();
$s = dt_va_selfcheck();
t('self-check runs', isset($s['status']));
