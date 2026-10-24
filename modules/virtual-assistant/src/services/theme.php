<?php
/** Safe visual theme settings exposed to the public assistant. */
if (!defined('ABSPATH')) exit;

function dt_va_theme_fields() {
    return array(
        'accent' => array('label' => 'Accent', 'default' => '#e2231a'),
        'accent_hover' => array('label' => 'Accent hover', 'default' => '#b81b14'),
        'panel' => array('label' => 'Panel background', 'default' => '#161618'),
        'surface' => array('label' => 'Header and form background', 'default' => '#1f1f23'),
        'message' => array('label' => 'Assistant message background', 'default' => '#26262b'),
        'input' => array('label' => 'Input background', 'default' => '#0f0f12'),
        'text' => array('label' => 'Primary text', 'default' => '#f5f4f0'),
        'user_message' => array('label' => 'Visitor message background', 'default' => '#f5f4f0'),
        'user_text' => array('label' => 'Visitor message text', 'default' => '#111113'),
        'muted' => array('label' => 'Secondary text', 'default' => '#a3a3a8'),
        'border' => array('label' => 'Borders', 'default' => '#33333a'),
    );
}

function dt_va_theme_defaults() {
    return array_map(function ($meta) { return $meta['default']; }, dt_va_theme_fields());
}

function dt_va_theme_parse($post) {
    if (!isset($post['theme']) || !is_array($post['theme'])) return new WP_Error('theme', 'Invalid theme settings.');
    $theme = array();
    foreach (dt_va_theme_fields() as $key => $meta) {
        $value = $post['theme'][$key] ?? '';
        if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) return new WP_Error('theme', 'Choose a valid colour for ' . $meta['label'] . '.');
        $theme[$key] = strtolower($value);
    }
    return $theme;
}

function dt_va_theme_config() {
    $saved = get_option('dt_va_theme', array());
    return array_merge(dt_va_theme_defaults(), is_array($saved) ? array_intersect_key($saved, dt_va_theme_fields()) : array());
}

/** CSS custom property for each field, matching the names read by assets/front.css and front.js. */
function dt_va_theme_css_vars() {
    return array(
        'accent' => '--dt-va-accent',
        'accent_hover' => '--dt-va-accent-hover',
        'panel' => '--dt-va-panel',
        'surface' => '--dt-va-surface',
        'message' => '--dt-va-message',
        'input' => '--dt-va-input',
        'text' => '--dt-va-text',
        'user_message' => '--dt-va-user-message',
        'user_text' => '--dt-va-user-text',
        'muted' => '--dt-va-muted',
        'border' => '--dt-va-border',
    );
}

/** Inline :root override, printed in wp_head so the first paint already has the saved colours;
 *  without this, front.js only applies them after it loads, causing a visible flash to the CSS defaults.
 *  !important is required: front.css's own enqueued stylesheet declares the same :root defaults and prints
 *  after this (wp_print_styles runs on wp_head at priority 8, this hook at priority 5), so without it the
 *  later, equally-specific stylesheet rule would win and silently override the colour set here. */
function dt_va_theme_css() {
    $theme = dt_va_theme_config();
    $rules = '';
    foreach (dt_va_theme_css_vars() as $key => $var) {
        if (isset($theme[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $theme[$key])) $rules .= $var . ':' . strtolower($theme[$key]) . ' !important;';
    }
    return $rules === '' ? '' : '<style id="dt-va-theme-vars">:root{' . $rules . '}</style>';
}

function dt_va_theme_settings() {
    if (!current_user_can('manage_options')) return;
    $notice = '';
    $error = '';
    if (isset($_POST['dt_va_theme']) && check_admin_referer('dt_va_theme')) {
        if (isset($_POST['restore_theme'])) {
            delete_option('dt_va_theme');
            $notice = 'Default theme restored.';
        } else {
            $parsed = dt_va_theme_parse($_POST);
            if (is_wp_error($parsed)) $error = $parsed->get_error_message();
            else { update_option('dt_va_theme', $parsed, false); $notice = 'Theme saved.'; }
        }
    }
    if ($notice || $error) echo '<div class="notice ' . ($error ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($error ?: $notice) . '</p></div>';
    $theme = dt_va_theme_config();
    echo '<h2>Theme</h2><p>Customise the assistant colours. Changes appear on the public chat after saving.</p><form method="post">';
    wp_nonce_field('dt_va_theme');
    echo '<input type="hidden" name="dt_va_theme" value="1"><table class="form-table">';
    foreach (dt_va_theme_fields() as $key => $meta) {
        echo '<tr><th><label for="dt-va-theme-' . esc_attr($key) . '">' . esc_html($meta['label']) . '</label></th><td><input id="dt-va-theme-' . esc_attr($key) . '" name="theme[' . esc_attr($key) . ']" type="color" value="' . esc_attr($theme[$key]) . '"> <code>' . esc_html($theme[$key]) . '</code></td></tr>';
    }
    echo '</table><p><button class="button button-primary">Save theme</button> <button class="button" name="restore_theme" value="1" formnovalidate>Restore default theme</button></p></form>';
}
