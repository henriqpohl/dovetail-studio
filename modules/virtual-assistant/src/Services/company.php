<?php
/** Mail identity settings. Getters keep saved changes effective within the same request. */
if (!defined('ABSPATH')) exit;

function dt_va_company_fields() {
    return array(
        'name' => array('constant' => 'DT_VA_COMPANY_NAME', 'label' => 'Company name', 'default' => 'Dovetail Consultancy'),
        'avatar' => array('constant' => 'DT_VA_COMPANY_AVATAR', 'label' => 'Avatar', 'default' => 'DT'),
        'to' => array('constant' => 'DT_VA_TO', 'label' => 'Enquiry email', 'default' => 'henriqpohl@gmail.com'),
        'from_name' => array('constant' => 'DT_VA_FROM_NAME', 'label' => 'Sender name', 'default' => 'Dovetail Consultancy'),
    );
}

function dt_va_company_value($field) {
    $meta = dt_va_company_fields()[$field];
    // Explicit wp-config.php definitions always win over values saved in the admin.
    $value = defined($meta['constant']) ? (string) constant($meta['constant']) : (string) get_option('dt_va_company_' . $field, $meta['default']);
    return in_array($field, array('name', 'avatar'), true) && trim($value) === '' ? $meta['default'] : $value;
}

function dt_va_company_collapse_avatar() {
    return get_option('dt_va_company_collapse_avatar', '') === '1';
}

function dt_va_digest_days() {
    return array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
}

function dt_va_digest_day() {
    $v = strtolower((string) get_option('dt_va_digest_day', 'monday'));
    return in_array($v, dt_va_digest_days(), true) ? $v : 'monday';
}

function dt_va_digest_time() {
    $v = (string) get_option('dt_va_digest_time', '08:00');
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : '08:00';
}

/** Falls back to the enquiry email ('to') when no digest-specific recipient is set. */
function dt_va_digest_recipient() {
    $v = trim((string) get_option('dt_va_digest_recipient', ''));
    return $v !== '' && is_email($v) ? $v : dt_va_company_value('to');
}

function dt_va_digest_settings() {
    if (!current_user_can('manage_options')) return;
    $error = '';
    $notice = '';
    if (isset($_POST['dt_va_digest']) && check_admin_referer('dt_va_digest')) {
        $day = isset($_POST['digest_day']) && is_string($_POST['digest_day']) ? strtolower(trim(wp_unslash($_POST['digest_day']))) : '';
        $time = isset($_POST['digest_time']) && is_string($_POST['digest_time']) ? trim(wp_unslash($_POST['digest_time'])) : '';
        $recipient = isset($_POST['digest_recipient']) && is_string($_POST['digest_recipient']) ? trim(wp_unslash($_POST['digest_recipient'])) : '';
        if (!in_array($day, dt_va_digest_days(), true)) {
            $error = 'Choose a valid day of the week.';
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $error = 'Enter the time as HH:MM, 24-hour, for example 08:00.';
        } elseif ($recipient !== '' && !is_email($recipient)) {
            $error = 'Enter a valid recipient email, or leave it blank to use the enquiry email.';
        } else {
            update_option('dt_va_digest_day', $day, false);
            update_option('dt_va_digest_time', $time, false);
            update_option('dt_va_digest_recipient', $recipient !== '' ? sanitize_email($recipient) : '', false);
            // Reschedule immediately; dt-va-server.php's per-request safety net would otherwise wait for the next page load.
            if (function_exists('dt_va_digest_reschedule')) dt_va_digest_reschedule();
            $notice = 'Weekly digest schedule saved.';
        }
    }
    if ($notice || $error) echo '<div class="notice ' . ($error ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($error ?: $notice) . '</p></div>';
    echo '<h2>Weekly digest</h2><p>Schedule for the weekly summary email of enquiries and the assistant funnel. Default: Monday 08:00, Europe/Dublin time, sent to the enquiry email above.</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_digest');
    echo '<input type="hidden" name="dt_va_digest" value="1"><table class="form-table">';
    echo '<tr><th><label for="dt-va-digest-day">Day</label></th><td><select id="dt-va-digest-day" name="digest_day">';
    foreach (dt_va_digest_days() as $d) echo '<option value="' . esc_attr($d) . '" ' . selected(dt_va_digest_day(), $d, false) . '>' . esc_html(ucfirst($d)) . '</option>';
    echo '</select></td></tr>';
    echo '<tr><th><label for="dt-va-digest-time">Time</label></th><td><input type="time" id="dt-va-digest-time" name="digest_time" value="' . esc_attr(dt_va_digest_time()) . '"><p class="description">Europe/Dublin time, 24-hour.</p></td></tr>';
    echo '<tr><th><label for="dt-va-digest-recipient">Recipient (optional)</label></th><td><input class="regular-text" id="dt-va-digest-recipient" name="digest_recipient" type="email" value="' . esc_attr(trim((string) get_option('dt_va_digest_recipient', ''))) . '" placeholder="' . esc_attr(dt_va_company_value('to')) . '"><p class="description">Leave blank to send the digest to the enquiry email above.</p></td></tr>';
    echo '</table><p><button class="button button-primary">Save digest schedule</button></p></form>';
}

function dt_va_company_settings() {
    if (!current_user_can('manage_options')) return;
    $error = '';
    if (isset($_POST['dt_va_company']) && check_admin_referer('dt_va_company')) {
        $values = array();
        foreach (dt_va_company_fields() as $field => $meta) {
            if (defined($meta['constant'])) continue;
            $raw = isset($_POST[$field]) && is_string($_POST[$field]) ? trim(wp_unslash($_POST[$field])) : '';
            if ($field === 'avatar') {
                if (preg_match('/[\r\n<>]/', $raw) || mb_strlen($raw) > 2) {
                    $error = 'Avatar must be at most 2 characters, without line breaks or angle brackets. Nothing was saved.';
                    break;
                }
                $values[$field] = sanitize_text_field($raw);
                continue;
            }
            // Reject header delimiters rather than silently turning a malformed address into a different one.
            if ($raw === '' || preg_match('/[\r\n]/', $raw) || ($field === 'to' && !is_email($raw))) {
                $error = 'Enter a company name, a valid enquiry email and a non-empty sender name. Nothing was saved.';
                break;
            }
            $values[$field] = $field === 'to' ? sanitize_email($raw) : sanitize_text_field($raw);
            if ($values[$field] === '' || ($field === 'from_name' && preg_match('/[<>]/', $raw))) {
                $error = 'Enter a valid sender name without email header characters. Nothing was saved.';
                break;
            }
        }
        if ($error === '') {
            foreach ($values as $field => $value) update_option('dt_va_company_' . $field, $value, false);
            update_option('dt_va_company_collapse_avatar', !empty($_POST['collapse_avatar']) ? '1' : '', false);
        }
        echo '<div class="notice ' . ($error === '' ? 'notice-success' : 'notice-error') . '"><p>' . esc_html($error ?: 'Company settings saved.') . '</p></div>';
    }
    echo '<h2>Company</h2><p>Configure the public company name and the email identity used by the assistant.</p><form method="post">';
    wp_nonce_field('dt_va_company');
    echo '<input type="hidden" name="dt_va_company" value="1"><table class="form-table">';
    foreach (dt_va_company_fields() as $field => $meta) {
        $external = defined($meta['constant']);
        if ($field === 'avatar') {
            echo '<tr><th><label for="dt-va-company-avatar">Avatar</label></th><td><input class="regular-text" id="dt-va-company-avatar" name="avatar" type="text" maxlength="2" value="' . esc_attr(dt_va_company_value('avatar')) . '"' . ($external ? ' readonly' : '') . '>';
            echo '<p class="description">' . ($external ? 'Defined by ' . esc_html($meta['constant']) . ' in wp-config.php. Edit it there, or remove the definition to use this field.' : 'Up to 2 characters, shown in the tab and panel header. Leave blank to use the default, DT.') . '</p></td></tr>';
            echo '<tr><th><label for="dt-va-company-collapse-avatar">Collapse Avatar</label></th><td><label style="display:flex;align-items:center;gap:8px;font-weight:400"><input type="checkbox" id="dt-va-company-collapse-avatar" name="collapse_avatar" value="1"' . checked(dt_va_company_collapse_avatar(), true, false) . '> Hide the avatar in the tab shown before the assistant is opened</label><p class="description">The avatar still appears inside the open assistant panel; this only affects the closed tab.</p></td></tr>';
            continue;
        }
        echo '<tr><th><label for="dt-va-company-' . esc_attr($field) . '">' . esc_html($meta['label']) . '</label></th><td><input class="regular-text" id="dt-va-company-' . esc_attr($field) . '" name="' . esc_attr($field) . '" type="' . ($field === 'to' ? 'email' : 'text') . '" value="' . esc_attr(dt_va_company_value($field)) . '" required' . ($external ? ' readonly' : '') . '>';
        echo '<p class="description">' . ($external ? 'Defined by ' . esc_html($meta['constant']) . ' in wp-config.php. Edit it there, or remove the definition to use this field.' : ($field === 'name' ? 'Displayed in the assistant and used in enquiry email copy.' : ($field === 'to' ? 'Receives briefs and automated notifications. Also used as the From and Reply-To address where applicable.' : 'Display name in outgoing email headers.'))) . '</p></td></tr>';
    }
    echo '</table><p><button class="button button-primary">Save company settings</button></p></form>';
    if (function_exists('dt_va_public_settings')) dt_va_public_settings();
    dt_va_digest_settings();
}
