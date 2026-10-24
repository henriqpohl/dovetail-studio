<?php
/** Public overrides are opt-in per field: missing means HTML default, empty means intentionally empty. */
if (!defined('ABSPATH')) exit;
function dt_va_public_fields() {
    return array('COMPANY_TYPE'=>'company_type', 'COMPANY_ADDRESS'=>'text', 'COMPANY_CITY'=>'text', 'COMPANY_EMAIL'=>'email', 'COMPANY_PHONE_DISPLAY'=>'text', 'COMPANY_PHONE_TEL'=>'tel', 'WHATSAPP'=>'digits', 'WHATSAPP_DISPLAY'=>'text', 'PRIVACY_URL'=>'url', 'WORK_URL'=>'url', 'RESPONSE_PROMISE'=>'text', 'OPENING_HOURS'=>'hours', 'BOOKING_URL'=>'url', 'GOOGLE_RATING'=>'rating', 'GOOGLE_REVIEWS'=>'count', 'GOOGLE_URL'=>'url', 'RESUME_HOURS'=>'duration');
}
/** Read the same simple literals used by the front-end script; never evaluate JavaScript in PHP. */
function dt_va_public_defaults() {
    $html = file_get_contents(dirname( __DIR__, 2 ) . '/assets/js/front.js');
    $defaults = array();
    foreach (dt_va_public_fields() as $key => $type) {
        if ($type === 'hours') {
            if (preg_match('/OPENING_HOURS:\s*\{\s*open:\s*([0-9.]+),\s*close:\s*([0-9.]+),\s*days:\s*\[([0-6, ]*)\]/', $html, $m)) {
                $defaults[$key] = array('open'=>(float)$m[1], 'close'=>(float)$m[2], 'days'=>$m[3] === '' ? array() : array_map('intval', explode(',', $m[3])));
            }
        } elseif (preg_match('/\\b' . $key . ':\\s*([\'\"])([^\'\"\\r\\n]*)\\1/', $html, $m)) {
            $defaults[$key] = $m[2];
        } elseif (preg_match('/\b' . $key . ':\s*([0-9.]+)/', $html, $m)) {
            $defaults[$key] = $type === 'count' ? (int)$m[1] : (float)$m[1];
        }
    }
    return $defaults;
}
function dt_va_public_help() {
    return array(
        'COMPANY_TYPE'=>array('Company Type', 'What visitors are told to call the place, for example "the Office is closed just now". Pick a suggestion or type your own; blank is not allowed and falls back to Office. Restore default uses the type in assets/front.js.'),
        'COMPANY_ADDRESS'=>array('Company Address', 'Public studio address shown in Talk to a person. Leave blank to hide the Address line. Restore default uses the address in assets/front.js. This does not update the knowledge base or offline answers.'),
        'COMPANY_CITY'=>array('Company City', 'City named in Talk to a person, for example "Of course. The team in Malahide take the calls themselves." Restore default uses the city in assets/front.js.'),
        'COMPANY_EMAIL'=>array('Public email', 'Email address displayed in the assistant. This does not change the address that receives briefs.'),
        'COMPANY_PHONE_DISPLAY'=>array('Phone — display', 'Readable phone number shown to visitors, for example 01 961 0034.'),
        'COMPANY_PHONE_TEL'=>array('Phone — dial link', 'Number used when visitors click to call. Include the country code, for example +35319610034.'),
        'WHATSAPP'=>array('WhatsApp number', 'International number using digits only, without + or spaces. Leave blank to disable WhatsApp.'),
        'WHATSAPP_DISPLAY'=>array('WhatsApp — display', 'Readable WhatsApp number shown to visitors. It does not change the destination number above.'),
        'PRIVACY_URL'=>array('Privacy policy URL', 'Link to your privacy policy. Use an https:// URL or a local path such as /privacy-policy/.'),
        'WORK_URL'=>array('Portfolio URL', 'Link to your portfolio or previous work, for example /our-work/.'),
        'RESPONSE_PROMISE'=>array('Response time', 'Expected reply time shown in the assistant, for example within one working day.'),
        'OPENING_HOURS'=>array('Opening hours', 'Hours used by the assistant in Europe/Dublin time. 17.5 means 17:30. Select the working days; none means closed. Overnight hours are not supported.'),
        'BOOKING_URL'=>array('Booking URL', 'Calendly or appointment booking link. Leave blank to disable booking.'),
        'GOOGLE_RATING'=>array('Google rating', 'Enter the Google rating manually, from 0 to 5. Leave blank to hide ratings.'),
        'GOOGLE_REVIEWS'=>array('Google review count', 'Enter the total number of Google ratings manually. Zero is allowed. Update this number when new ratings arrive.'),
        'GOOGLE_URL'=>array('Google Maps profile URL', 'Public link to your business on Google Maps, opened when visitors click the ratings. This link does not fetch ratings automatically.'),
        'RESUME_HOURS'=>array('Resume conversation — hours', 'How long an unfinished conversation can be resumed in this browser. Use 0 to disable resume. Does not delete submitted enquiries.'),
    );
}
function dt_va_public_parse($post) {
    $out = array();
    foreach (dt_va_public_fields() as $key => $type) {
        if (!isset($post['public']) || !is_array($post['public']) || !array_key_exists($key, $post['public'])) continue;
        $v = $post['public'][$key] ?? '';
        if ($type === 'company_type') {
            if (!is_string($v)) return new WP_Error('settings', 'Invalid value for ' . $key);
            $v = trim(wp_unslash($v));
            if ($v === '' || strlen($v) > 40 || preg_match('/[<>\r\n]/', $v)) return new WP_Error('settings', 'Choose a company type or enter a short custom one, without line breaks or angle brackets.');
            $out[$key] = sanitize_text_field($v); continue;
        }
        if ($type === 'hours') {
            if (!is_array($v)) return new WP_Error('settings', 'Invalid hours.');
            $open = $v['open'] ?? ''; $close = $v['close'] ?? '';
            if (!is_numeric($open) || !is_numeric($close) || $open < 0 || $close > 24 || $open >= $close) return new WP_Error('settings', 'Hours must be between 0 and 24, with opening before closing.');
            $days = $v['days'] ?? array();
            if (!is_array($days)) return new WP_Error('settings', 'Invalid days.');
            foreach ($days as $d) if (!is_scalar($d) || !preg_match('/^[0-6]$/', (string)$d)) return new WP_Error('settings', 'Invalid days.');
            $out[$key] = array('open'=>(float)$open, 'close'=>(float)$close, 'days'=>array_values(array_unique(array_map('intval',$days)))); continue;
        }
        if (!is_string($v)) return new WP_Error('settings', 'Invalid value for ' . $key);
        $v = trim(wp_unslash($v));
        if (strlen($v)>2000) return new WP_Error('settings', 'Value too long: ' . $key);
        if ($type==='email' && $v!=='' && !is_email($v)) return new WP_Error('settings', 'Invalid email.');
        if ($type==='url' && $v!=='' && !(preg_match('~^/(?!/)[^\\\\\s]*$~',$v) || (filter_var($v,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($v,PHP_URL_SCHEME)),array('http','https'),true)))) return new WP_Error('settings', 'Use an http(s) URL or a site-relative path: ' . $key);
        if ($type==='digits' && $v!=='' && !preg_match('/^[0-9]{6,15}$/',$v)) return new WP_Error('settings','WhatsApp must contain 6–15 digits, without +.');
        if ($type==='tel' && $v!=='' && !preg_match('/^\+?[0-9 ()-]{3,25}$/',$v)) return new WP_Error('settings','Invalid telephone number.');
        if (in_array($type,array('rating','count','duration'),true)) {
            if ($type==='rating' && $v==='') { $out[$key]=''; continue; }
            $max = $type==='rating' ? 5 : ($type==='duration' ? 8760 : 100000000);
            if (!is_numeric($v) || $v<0 || $v>$max || ($type==='count' && floor((float)$v)!=(float)$v)) return new WP_Error('settings','Invalid number: ' . $key);
            $out[$key] = $type==='count' ? (int)$v : ($type==='rating' ? number_format((float)$v,1,'.','') : (float)$v); continue;
        }
        $out[$key]=sanitize_text_field($v);
    }
    return $out;
}
function dt_va_public_config() {
    $saved=get_option('dt_va_public',array());
    $out=is_array($saved) ? array_intersect_key($saved,dt_va_public_fields()) : array();
    return $out;
}
/** Saved override for a single field, else the HTML default; used server-side (e.g. the system prompt) where the full config array is overkill. */
function dt_va_public_value($key) {
    $saved = dt_va_public_config();
    if (array_key_exists($key, $saved)) return $saved[$key];
    $defaults = dt_va_public_defaults();
    return isset($defaults[$key]) ? $defaults[$key] : '';
}
function dt_va_public_settings() {
    if (!current_user_can('manage_options')) return;
    if (isset($_POST['dt_va_public']) && check_admin_referer('dt_va_public')) {
        $saved = get_option('dt_va_public', array());
        $saved = is_array($saved) ? $saved : array();
        $reset = $_POST['restore_default'] ?? null;
        if (is_string($reset) && array_key_exists($reset, dt_va_public_fields())) {
            // Reset only this field; unrelated submitted edits are deliberately not saved.
            unset($saved[$reset]);
            update_option('dt_va_public', $saved, false);
            echo '<div class="notice notice-success"><p>Default restored for ' . esc_html($reset) . '. Other unsaved edits were not saved.</p></div>';
        } else {
            $parsed=dt_va_public_parse($_POST);
            if (!is_wp_error($parsed)) {
                $defaults=dt_va_public_defaults();
                foreach ($parsed as $key=>$value) {
                    // Unchanged inherited values stay inherited, so future HTML defaults still apply.
                    if (!array_key_exists($key,$saved) && array_key_exists($key,$defaults) && $value == $defaults[$key]) continue;
                    $saved[$key]=$value;
                }
                update_option('dt_va_public',$saved,false);
            }
            echo '<div class="notice '.(is_wp_error($parsed)?'notice-error':'notice-success').'"><p>'.esc_html(is_wp_error($parsed)?$parsed->get_error_message():'Public settings saved.').'</p></div>';
        }
    }
    $saved=get_option('dt_va_public',array()); $saved=is_array($saved)?$saved:array();
    $defaults=dt_va_public_defaults(); $help=dt_va_public_help();
    if (count($defaults)!==count(dt_va_public_fields())) {
        echo '<div class="notice notice-error"><p>Could not read all defaults from assets/front.js. Check the configuration literals before editing these fields.</p></div>';
        return;
    }
    echo '<h2>Public company details</h2><p>Edit a value and save to apply it. Optional blank values remain blank. Restore default resets only that field immediately; save other edits first. Google ratings are entered manually; no API key is needed.</p><form method="post">';
    wp_nonce_field('dt_va_public'); echo '<input type="hidden" name="dt_va_public" value="1"><table class="form-table">';
    foreach (dt_va_public_fields() as $key=>$type) {
        $custom=array_key_exists($key,$saved); $v=$custom?$saved[$key]:$defaults[$key];
        $input_id='dt-va-public-'.$key;
        echo '<tr><th><label for="'.esc_attr($input_id).'">'.esc_html($help[$key][0]).'</label></th><td>';
        if ($type==='hours') {
            foreach (array('open','close') as $part) echo '<label>'.esc_html(ucfirst($part)).' <input id="'.esc_attr($part==='open'?$input_id:$input_id.'-close').'" aria-describedby="'.esc_attr($input_id.'-help').'" type="number" step="0.25" min="0" max="24" name="public[OPENING_HOURS]['.$part.']" value="'.esc_attr($v[$part]).'"></label> ';
            echo '<p>'; foreach(array('Sun','Mon','Tue','Wed','Thu','Fri','Sat') as $d=>$label) echo '<label><input type="checkbox" name="public[OPENING_HOURS][days][]" value="'.$d.'" '.checked(in_array($d,$v['days'],true),true,false).'>'.$label.'</label> '; echo '</p>';
        } elseif ($type==='company_type') {
            echo '<input id="'.esc_attr($input_id).'" aria-describedby="'.esc_attr($input_id.'-help').'" class="regular-text" type="text" list="dt-va-company-type-list" maxlength="40" name="public['.esc_attr($key).']" value="'.esc_attr($v).'">';
            echo '<datalist id="dt-va-company-type-list"><option value="Office"><option value="Studio"><option value="Restaurant"><option value="Store"></datalist>';
        } else {
            echo '<input id="'.esc_attr($input_id).'" aria-describedby="'.esc_attr($input_id.'-help').'" class="regular-text" type="text" name="public['.esc_attr($key).']" value="'.esc_attr($v).'">';
        }
        echo ' <button class="button" name="restore_default" value="'.esc_attr($key).'" formnovalidate>Restore default</button>';
        echo '<p class="description" id="'.esc_attr($input_id.'-help').'">'.esc_html($help[$key][1]).'</p><p class="description"><code>'.esc_html($key).'</code> — '.($custom?'Saved value':'Using HTML default').'</p></td></tr>';
    }
    echo '</table><button class="button button-primary">Save public details</button></form>';
}
