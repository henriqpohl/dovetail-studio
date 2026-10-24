<?php
/** Optional admin override for the knowledge base; falls back to knowledge.md, then the built-in default. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/setup.php';

function dt_va_knowledge_override() {
    return trim((string) get_option('dt_va_knowledge_override', ''));
}

function dt_va_knowledge_settings_process() {
    if (!current_user_can('manage_options')) return array('notice' => '', 'error' => '', 'raw' => null);
    $error = ''; $raw = null;
    $notice = '';
    if (isset($_POST['dt_va_knowledge']) && check_admin_referer('dt_va_knowledge')) {
        if (isset($_POST['restore_default'])) {
            delete_option('dt_va_knowledge_override');
            $notice = 'Default knowledge base restored.';
        } else {
            $raw = isset($_POST['knowledge_text']) && is_string($_POST['knowledge_text']) ? trim(wp_unslash($_POST['knowledge_text'])) : '';
            update_option('dt_va_knowledge_override', sanitize_textarea_field($raw), false);
            $notice = 'Knowledge base saved.';
        }
    }
    if ($notice !== '') delete_option('dt_va_setup_review_knowledge');
    return array('notice' => $notice, 'error' => $error, 'raw' => $raw);
}

function dt_va_knowledge_settings() {
    if (!current_user_can('manage_options')) return;
    $result = $GLOBALS['dt_va_setup_processed']['dt_va_knowledge_settings'] ?? dt_va_knowledge_settings_process();
    $notice = $result['notice']; $error = $result['error']; $raw = $result['raw'];
    if ($notice) echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
    $override = dt_va_knowledge_override();
    echo '<h2>Knowledge base</h2><p>The only facts the AI Assistant is allowed to answer questions from. Customise it for this installation here; until saved, the assistant uses the bundled <code>knowledge.md</code>. Restore default clears this override and goes back to <code>knowledge.md</code>.</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_knowledge');
    echo '<input type="hidden" name="dt_va_knowledge" value="1">';
    echo '<textarea name="knowledge_text" rows="30" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">' . esc_textarea($override !== '' ? $override : dt_va_knowledge_base_default()) . '</textarea>';
    echo '<p class="description">' . ($override !== '' ? 'Using the saved override.' : 'Using the default from knowledge.md.') . '</p>';
    echo '<p><button class="button button-primary">Save knowledge base</button> <button class="button" name="restore_default" value="1" formnovalidate>Restore default</button></p></form>';
    dt_va_setup_review_controls('knowledge');
}
