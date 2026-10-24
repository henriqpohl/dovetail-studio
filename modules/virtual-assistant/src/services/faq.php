<?php
/** Optional admin override for the offline FAQ (offlineAnswer() fallback). Plain keywords only, never regex, to avoid a ReDoS surface from admin-entered content. Replaces the built-in FAQ wholesale when set, same convention as Knowledge base and Doors. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/json-tools.php';

function dt_va_faq_config() {
    $saved = get_option('dt_va_faq_entries', array());
    return is_array($saved) && $saved ? $saved : array();
}

/** Returns a validated array on success, or a WP_Error describing the first problem found. Keywords are plain words/phrases (letters, numbers, spaces, apostrophes); never compiled as regex. */
function dt_va_faq_validate($json) {
    $decoded = json_decode((string) $json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return new WP_Error('faq', 'Not valid JSON, or not a JSON array.');
    if (count($decoded) < 1 || count($decoded) > 60) return new WP_Error('faq', 'Provide between 1 and 60 entries.');
    $out = array();
    foreach ($decoded as $i => $entry) {
        if (!is_array($entry)) return new WP_Error('faq', 'Entry ' . ($i + 1) . ' is not an object.');
        $id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
        $answer = isset($entry['answer']) && is_string($entry['answer']) ? trim($entry['answer']) : '';
        if ($id === '' || $answer === '') return new WP_Error('faq', 'Entry ' . ($i + 1) . ' needs an id and an answer.');
        if (mb_strlen($answer) > 1200) return new WP_Error('faq', 'Entry ' . ($i + 1) . ' (' . $id . '): answer is too long (max 1200 characters).');
        $keywords = array();
        $raw_keywords = isset($entry['keywords']) && is_array($entry['keywords']) ? $entry['keywords'] : array();
        foreach ($raw_keywords as $k) {
            if (!is_string($k)) continue;
            $k = mb_strtolower(trim($k));
            if ($k === '' || mb_strlen($k) > 40 || !preg_match('/^[a-z0-9\' ]+$/u', $k)) continue;
            $keywords[] = $k;
        }
        if (!$keywords) return new WP_Error('faq', 'Entry ' . ($i + 1) . ' (' . $id . '): needs at least one plain keyword (letters, numbers, spaces, apostrophes only, max 40 characters).');
        $out[] = array('id' => mb_substr($id, 0, 60), 'keywords' => array_slice(array_values(array_unique($keywords)), 0, 20), 'answer' => mb_substr($answer, 0, 1200));
    }
    return $out;
}

/** Shown in the admin textarea as a starting point when no override is saved; matches the generic built-in fallback in front.js (var T), not real facts about any one company. */
function dt_va_faq_starter() {
    return array(
        array('id' => 'cost', 'keywords' => array('cost', 'price', 'budget', 'quote'), 'answer' => 'Cost follows scope, so nothing is priced until the brief is understood. Tell me what you have in mind and the {{COMPANY_NAME}} team will come back with a quote.'),
        array('id' => 'contact', 'keywords' => array('phone number', 'contact', 'get in touch'), 'answer' => 'You can leave your details here and the {{COMPANY_NAME}} team will ring you, or use the contact details on the site.'),
    );
}

function dt_va_faq_settings_process() {
    if (!current_user_can('manage_options')) return array('notice' => '', 'error' => '', 'raw' => null);
    $notice = ''; $error = ''; $raw = null;
    if (isset($_POST['dt_va_faq_add']) && check_admin_referer('dt_va_faq_add')) {
        $base = dt_va_faq_config();
        $v = $base ? $base : dt_va_faq_starter();
        $id = isset($_POST['add_id']) ? trim(wp_unslash($_POST['add_id'])) : '';
        if ($id === '') {
            $error = 'Enter an id.';
        } else {
            $keywords_raw = isset($_POST['add_keywords']) ? trim(wp_unslash($_POST['add_keywords'])) : '';
            $new = array(
                'id' => $id,
                'keywords' => array_values(array_filter(array_map('trim', explode(',', $keywords_raw)))),
                'answer' => isset($_POST['add_answer']) ? trim(wp_unslash($_POST['add_answer'])) : '',
            );
            $candidate = $v;
            $candidate[] = $new;
            $parsed = dt_va_faq_validate(wp_json_encode($candidate));
            if (is_wp_error($parsed)) $error = $parsed->get_error_message();
            else { update_option('dt_va_faq_entries', $parsed, false); $notice = 'FAQ entry added.'; }
        }
    } elseif (isset($_POST['dt_va_faq']) && check_admin_referer('dt_va_faq')) {
        if (isset($_POST['restore_default'])) {
            delete_option('dt_va_faq_entries');
            $notice = 'Default offline FAQ restored.';
        } else {
            $raw = isset($_POST['faq_entries']) && is_string($_POST['faq_entries']) ? wp_unslash($_POST['faq_entries']) : '';
            $parsed = dt_va_faq_validate($raw);
            if (is_wp_error($parsed)) $error = $parsed->get_error_message();
            else { update_option('dt_va_faq_entries', $parsed, false); $notice = 'Offline FAQ saved.'; }
        }
    }
    if ($notice !== '') delete_option('dt_va_setup_review_faq');
    return array('notice' => $notice, 'error' => $error, 'raw' => $raw);
}

function dt_va_faq_settings() {
    if (!current_user_can('manage_options')) return;
    $result = $GLOBALS['dt_va_setup_processed']['dt_va_faq_settings'] ?? dt_va_faq_settings_process();
    $notice = $result['notice']; $error = $result['error']; $raw = $result['raw'];
    if ($notice || $error) echo '<div class="notice ' . ($error ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($error ?: $notice) . '</p></div>';
    echo '<h3>Add an entry</h3><p class="description">Adds one FAQ entry without hand-editing JSON. Keywords are comma-separated plain words/phrases (no regex).</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_faq_add');
    echo '<input type="hidden" name="dt_va_faq_add" value="1"><table class="form-table">';
    echo '<tr><th><label for="dt-va-faq-id">Id</label></th><td><input class="regular-text" id="dt-va-faq-id" name="add_id" placeholder="e.g. warranty" required></td></tr>';
    echo '<tr><th><label for="dt-va-faq-keywords">Keywords</label></th><td><input class="regular-text" id="dt-va-faq-keywords" name="add_keywords" placeholder="Comma-separated, e.g. warranty, guarantee, how long" required></td></tr>';
    echo '<tr><th><label for="dt-va-faq-answer">Answer</label></th><td><textarea class="large-text" id="dt-va-faq-answer" name="add_answer" rows="3" required></textarea></td></tr>';
    echo '</table><p><button class="button">Add entry</button></p></form><hr>';
    $saved = dt_va_faq_config();
    $v = $saved ? $saved : dt_va_faq_starter();
    echo '<h2>Offline FAQ</h2><p>Answers shown when the AI provider is unavailable or not configured, and as a quick check before sending a short question to the AI. Each entry needs an <code>id</code>, at least one plain <code>keyword</code> (no regex; words or short phrases only) and an <code>answer</code>. <code>{{COMPANY_NAME}}</code> in the answer is replaced with the configured company name. First matching entry, in list order, wins.</p>';
    echo '<p class="description">Saving here replaces the built-in offline FAQ entirely for this installation. Restore default removes the override and goes back to the built-in list.</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_faq');
    echo '<input type="hidden" name="dt_va_faq" value="1">';
    echo '<textarea name="faq_entries" rows="24" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">' . esc_textarea(wp_json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</textarea>';
    echo '<p class="description">' . ($saved ? 'Using the saved offline FAQ (' . count($saved) . ' entries).' : 'Using the built-in offline FAQ; the text above is only a starting example.') . '</p>';
    echo '<p><button class="button button-primary">Save offline FAQ</button> <button class="button" name="restore_default" value="1" formnovalidate>Restore default</button>';
    dt_va_json_tools('faq_entries');
    echo '</p><p class="description">Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.</p></form>';
    dt_va_setup_review_controls('faq');
}
