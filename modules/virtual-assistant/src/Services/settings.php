<?php
/** Provider settings: models are explicit because availability depends on the API account. */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=dt_enquiry', 'Virtual Assistant settings', 'Settings', 'manage_options', 'dt-va-settings', 'dt_va_settings_page');
});
function dt_va_settings_page() {
    if (!current_user_can('manage_options')) return;
    $allowed_tabs = array('ai-keys', 'company', 'theme', 'ai-assistant', 'knowledge', 'flow', 'faq', 'case-studies');
    $tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs, true) ? sanitize_key(wp_unslash($_GET['tab'])) : 'ai-keys';
    echo '<div class="wrap dovetail-wrap">';
    echo '<div class="dovetail-page-header"><div><h1>Virtual Assistant</h1><p>Configure AI providers, company data, knowledge, conversation flow and appearance.</p></div><span class="dovetail-version">v1.31.0</span></div>';
    echo '<nav class="dovetail-section-nav" aria-label="Virtual Assistant settings">';
    foreach (array('ai-keys' => 'AI Keys', 'company' => 'Company', 'ai-assistant' => 'AI Assistant', 'knowledge' => 'Knowledge base', 'flow' => 'Doors', 'faq' => 'Offline FAQ', 'case-studies' => 'Portfolio', 'theme' => 'Theme') as $id => $title) {
        $url = admin_url('edit.php?post_type=dt_enquiry&page=dt-va-settings&tab=' . $id);
        echo '<a class="' . ($tab === $id ? 'is-active' : '') . '" href="' . esc_url($url) . '"' . ($tab === $id ? ' aria-current="page"' : '') . '>' . esc_html($title) . '</a>';
    }
    echo '</nav>';
    if ($tab !== 'ai-keys') {
        echo '<section class="dovetail-card"><div class="dovetail-card-body">';
        if ($tab === 'company') dt_va_company_settings();
        elseif ($tab === 'ai-assistant') dt_va_ai_assistant_settings();
        elseif ($tab === 'knowledge') dt_va_knowledge_settings();
        elseif ($tab === 'flow') dt_va_flow_settings();
        elseif ($tab === 'case-studies') dt_va_case_studies_settings();
        elseif ($tab === 'faq') dt_va_faq_settings();
        elseif ($tab === 'theme') dt_va_theme_settings();
        echo '</div></section></div>';
        return;
    }
    $notice = '';
    if (isset($_POST['dt_va_settings']) && check_admin_referer('dt_va_settings')) {
        $c = dt_va_provider_config(false);
        // Save only admin values, never persist an external model override into the database.
        foreach (array('primary', 'secondary', 'openai_model', 'anthropic_model', 'timeout', 'max_tokens') as $field) {
            if (in_array($field, array('openai_model', 'anthropic_model'), true) && dt_va_external_model(str_replace('_model', '', $field)) !== null) continue;
            if (isset($_POST[$field]) && is_string($_POST[$field])) $c[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
        }
        update_option('dt_va_providers', $c, false);
        update_option('dt_va_providers', dt_va_provider_config(false), false);
        foreach (array('openai', 'anthropic') as $p) {
            // An external secret takes precedence; never copy it into the database or HTML.
            $name = 'DT_VA_' . strtoupper($p) . '_KEY';
            if ((defined($name) && constant($name) !== '') || getenv($name)) continue;
            if (!empty($_POST[$p . '_clear'])) delete_option('dt_va_' . $p . '_key');
            elseif (isset($_POST[$p . '_key']) && is_string($_POST[$p . '_key'])) {
                $key = trim(wp_unslash($_POST[$p . '_key']));
                if ($key !== '' && strlen($key) <= 512 && !preg_match('/\s/', $key)) update_option('dt_va_' . $p . '_key', $key, false);
            }
        }
        $notice = 'Settings saved.';
        if (!empty($_POST['dt_va_test'])) {
            // Manual tests must not trigger the daily check's notification emails.
            $r = dt_va_provider_answer(array(array('role' => 'user', 'content' => 'Reply with OK.')), 'Connection test. Reply briefly.');
            $notice .= isset($r['answer']) ? ' Working via ' . $r['provider'] . '.' : ' No usable answer. Check diagnostics below.';
        }
    }
    $c = dt_va_provider_config();
    echo '<section class="dovetail-card"><div class="dovetail-card-header"><h2>AI Keys</h2><p class="description">Configure a key and model for each provider. Unconfigured providers are skipped. Offline answers remain available.</p></div><div class="dovetail-card-body">';
    if ($notice) echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
    echo '<form method="post">'; wp_nonce_field('dt_va_settings');
    echo '<input type="hidden" name="dt_va_settings" value="1"><table class="form-table">';
    foreach (array('primary', 'secondary') as $field) {
        echo '<tr><th>' . esc_html(ucfirst($field)) . '</th><td><select name="' . esc_attr($field) . '">';
        foreach ($field === 'primary' ? array('openai', 'anthropic') : array('none', 'openai', 'anthropic') as $value) echo '<option value="' . esc_attr($value) . '" ' . selected($c[$field], $value, false) . '>' . esc_html(ucfirst($value)) . '</option>';
        echo '</select></td></tr>';
    }
    foreach (array('openai', 'anthropic') as $p) {
        $name = 'DT_VA_' . strtoupper($p) . '_KEY';
        $external = (defined($name) && constant($name) !== '') || getenv($name);
        $label = $p === 'openai' ? 'OpenAI' : 'Anthropic';
        $key = dt_va_provider_key($p);
        // Render only a short prefix as a placeholder, never the saved secret as an input value.
        // Short malformed values are fully masked so a preview cannot expose the entire value.
        $preview = $key === '' ? ($p === 'openai' ? 'sk-…' : 'sk-ant-…') : (strlen($key) > 16 ? substr($key, 0, 12) . '…' : '••••••');
        echo '<tr><th>' . esc_html($label) . ' API key</th><td><p>' . ($key !== '' ? 'Configured' : 'Not configured') . ($external ? ' externally (wp-config.php or environment).' : ' in admin settings.') . '</p>';
        if ($external) {
            echo '<input type="text" class="regular-text code" aria-label="' . esc_attr($label) . ' saved key prefix" value="' . esc_attr($preview) . '" disabled><p class="description">Edit the key in wp-config.php or the environment. Only its prefix is displayed here.</p>';
        } else {
            echo '<input type="password" class="regular-text code" autocomplete="new-password" aria-label="' . esc_attr($label) . ' API key" name="' . esc_attr($p) . '_key" value="" placeholder="' . esc_attr($preview) . '"> <label><input type="checkbox" name="' . esc_attr($p) . '_clear" value="1"> Clear saved key</label><p class="description">Only the saved key prefix is shown. Leave blank to keep it; paste a new key to replace it. Saved in the WordPress database. Prefer environment or wp-config.php.</p>';
        }
        $models = $p === 'openai'
            ? array('GPT-6 Luna' => 'gpt-6-luna', 'GPT-6 Sol' => 'gpt-6-sol', 'GPT-6 Astra' => 'gpt-6-astra', 'GPT-4.1 Mini' => 'gpt-4.1-mini')
            : array('Claude Haiku 4.5' => 'claude-haiku-4-5-20251001', 'Claude Sonnet 5' => 'claude-sonnet-5', 'Claude Opus 5.5' => 'claude-opus-5-5', 'Claude Fable 5.1' => 'claude-fable-5-1');
        $catalog = $p === 'openai' ? 'https://developers.openai.com/api/docs/models' : 'https://platform.claude.com/docs/en/models/overview';
        $external_model = dt_va_external_model($p);
        echo '</td></tr><tr><th>' . esc_html($label) . ' model</th><td><input class="regular-text" name="' . esc_attr($p) . '_model" value="' . esc_attr($c[$p . '_model']) . '"' . ($external_model !== null ? ' readonly' : '') . '><p class="description">Enter a model ID, not an API key. Blank disables this provider.</p><p class="description"><strong>Model IDs (reference checked 28 Sep 2026):</strong></p><ul>';
        if ($external_model !== null) echo '<li><strong>Configured via ' . esc_html($external_model['source']) . '.</strong> Edit DT_VA_' . esc_html(strtoupper($p)) . '_MODEL there.' . ($c[$p . '_model'] === '' ? ' Invalid external model ID: provider disabled.' : '') . '</li>';
        // Informational examples, not an allowlist: accounts and model availability change.
        foreach ($models as $model_name => $id) echo '<li>' . esc_html($model_name) . ': <code>' . esc_html($id) . '</code></li>';
        echo '</ul><p class="description"><a href="' . esc_url($catalog) . '" target="_blank" rel="noopener noreferrer">Official model catalog</a>. Availability depends on your API account. Reasoning models may need a larger output-token budget and more time. Use Save and test providers to verify your choice.</p></td></tr>';

    }
    foreach (array('timeout' => array('Timeout per provider (seconds)', 3, 12), 'max_tokens' => array('Maximum output tokens', 64, 4096)) as $field => $meta) echo '<tr><th>' . esc_html($meta[0]) . '</th><td><input type="number" name="' . esc_attr($field) . '" min="' . $meta[1] . '" max="' . $meta[2] . '" value="' . esc_attr($c[$field]) . '"></td></tr>';
    // The scheduled check has its own timestamp; conversations and manual tests must not replace it.
    $daily = get_option('dt_va_selfcheck', array());
    $daily = is_array($daily) ? $daily : array();
    echo '<tr><th>Last daily check</th><td>';
    if (!empty($daily['checked'])) {
        $status = ($daily['status'] ?? '') === 'ok' ? 'working' : (($daily['status'] ?? '') === 'nokey' ? 'not configured' : 'error');
        echo esc_html(wp_date('j M Y H:i', (int) $daily['checked']) . ': ' . $status);
        if (!empty($daily['detail'])) echo '<p class="description">' . esc_html($daily['detail']) . '</p>';
    } else {
        echo 'Not yet run.';
    }
    echo '<p class="description">Time shown in the WordPress site timezone. This is the automatic daily check, not the manual provider test.</p></td></tr>';
    echo '</table><p><button class="button button-primary">Save</button> <button class="button" name="dt_va_test" value="1">Save and test providers</button></p></form><h2>Last provider diagnostics</h2><pre>' . esc_html(wp_json_encode(get_option('dt_va_last_provider_result', array()), JSON_PRETTY_PRINT)) . '</pre></div></section></div>';
}
