<?php
/** Shared, read-only JSON import validation and editor download controls. */
if (!defined('ABSPATH')) exit;

function dt_va_json_formats() {
    return array(
        'flow_doors' => array('validate' => 'dt_va_flow_doors_validate', 'sample' => 'dt_va_flow_doors_default', 'nonce' => 'dt_va_flow', 'object' => false),
        'flow_steps' => array('validate' => 'dt_va_flow_steps_validate', 'sample' => 'dt_va_flow_steps_starter', 'nonce' => 'dt_va_flow_steps', 'object' => true),
        'faq_entries' => array('validate' => 'dt_va_faq_validate', 'sample' => 'dt_va_faq_starter', 'nonce' => 'dt_va_faq', 'object' => false),
        'case_studies_entries' => array('validate' => 'dt_va_case_studies_validate', 'sample' => 'dt_va_case_studies_starter', 'nonce' => 'dt_va_case_studies', 'object' => false),
    );
}

function dt_va_json_validate($format, $raw) {
    $formats = dt_va_json_formats();
    if (!is_string($format) || !isset($formats[$format])) return new WP_Error('json', 'Unknown JSON format.');
    if (!is_string($raw) || strlen($raw) > 1048576) return new WP_Error('json', 'Choose a JSON file up to 1 MB.');
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $decoded = json_decode($raw);
    $schema = $formats[$format];
    if (json_last_error() !== JSON_ERROR_NONE) return new WP_Error('json', 'Invalid JSON: ' . json_last_error_msg());
    if ($schema['object'] ? !is_object($decoded) : !is_array($decoded)) return new WP_Error('json', $schema['object'] ? 'Expected a JSON object keyed by step id.' : 'Expected a JSON array of objects.');
    foreach ($decoded as $entry) if (!is_object($entry)) return new WP_Error('json', 'Every entry must be a JSON object.');
    return call_user_func($schema['validate'], $raw);
}

function dt_va_json_validate_ajax() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Administrator access required.'), 403);
    $format = isset($_POST['format']) && is_string($_POST['format']) ? wp_unslash($_POST['format']) : '';
    $formats = dt_va_json_formats();
    if (!isset($formats[$format])) wp_send_json_error(array('message' => 'Unknown JSON format.'), 400);
    check_ajax_referer($formats[$format]['nonce'], 'nonce');
    $raw = isset($_POST['json']) && is_string($_POST['json']) ? wp_unslash($_POST['json']) : '';
    $parsed = dt_va_json_validate($format, $raw);
    if (is_wp_error($parsed)) wp_send_json_error(array('message' => $parsed->get_error_message()), 400);
    wp_send_json_success(array('json' => wp_json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
}

function dt_va_json_tools($format) {
    $formats = dt_va_json_formats();
    $sample = call_user_func($formats[$format]['sample']);
    echo '<span class="dt-va-json-tools" data-format="' . esc_html($format) . '" data-sample="' . esc_textarea(wp_json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '"> ';
    echo '<button type="button" class="button" data-json-action="import">Import</button> <button type="button" class="button" data-json-action="export">Export</button> <button type="button" class="button" data-json-action="sample">Download a Sample</button>';
    echo '<input type="file" accept=".json,application/json" hidden><span role="status" aria-live="polite" class="dt-va-json-status"></span></span>';
}
