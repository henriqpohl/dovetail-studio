<?php
/** Doors (initial menu) configuration: a flat, low-risk slice of the guided flow. Per-step questions stay hardcoded for now. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/json-tools.php';

function dt_va_flow_door_actions() { return array('service', 'other', 'human'); }
/** Any plain id-shaped string is accepted (not a fixed business-specific list), so a door's service can name anything relevant to the installation (e.g. "plumbing", "consulta"), not just the original logo/print/web/client. */
function dt_va_flow_service_valid($service) {
    return is_string($service) && preg_match('/^[a-z0-9_-]{1,40}$/', $service);
}

/** Starting point shown in the admin textarea and used as a scaffold; front.js keeps its own literal default in sync separately. Generic on purpose — no business-specific service names, since this is what a fresh install falls back to before anyone configures doors. */
function dt_va_flow_doors_default() {
    return array(
        array('id' => 'enquiry', 'label' => 'Enquire about a product or service', 'sub' => "Tell us what you're looking for", 'icon' => 'logo', 'action' => 'service', 'service' => 'enquiry'),
        array('id' => 'client', 'label' => "I'm already a client", 'sub' => 'Changes, updates or an account query', 'icon' => 'client', 'action' => 'service', 'service' => 'client'),
        array('id' => 'other', 'label' => 'Something else', 'sub' => 'Or just ask me a question', 'icon' => 'other', 'action' => 'other'),
        array('id' => 'human', 'label' => "I'd rather talk to a person", 'sub' => 'Ring us or ask for a call back', 'icon' => 'human', 'action' => 'human'),
    );
}

/** Returns a validated array on success, or a WP_Error describing the first problem found. Never trusts the shape of decoded JSON. */
function dt_va_flow_doors_validate($json) {
    $decoded = json_decode((string) $json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return new WP_Error('flow', 'Not valid JSON, or not a JSON array.');
    if (count($decoded) < 1 || count($decoded) > 12) return new WP_Error('flow', 'Provide between 1 and 12 doors.');
    $actions = dt_va_flow_door_actions();
    $out = array();
    foreach ($decoded as $i => $door) {
        if (!is_array($door)) return new WP_Error('flow', 'Door ' . ($i + 1) . ' is not an object.');
        $id = isset($door['id']) && is_string($door['id']) ? trim($door['id']) : '';
        $label = isset($door['label']) && is_string($door['label']) ? trim($door['label']) : '';
        if ($id === '' || $label === '') return new WP_Error('flow', 'Door ' . ($i + 1) . ' needs an id and a label.');
        $action = isset($door['action']) && in_array($door['action'], $actions, true) ? $door['action'] : '';
        if ($action === '') return new WP_Error('flow', 'Door ' . ($i + 1) . ': action must be one of ' . implode(', ', $actions) . '.');
        $row = array('id' => mb_substr($id, 0, 40), 'label' => mb_substr($label, 0, 80), 'action' => $action);
        if ($action === 'service') {
            $service = isset($door['service']) && dt_va_flow_service_valid($door['service']) ? $door['service'] : '';
            if ($service === '') return new WP_Error('flow', 'Door ' . ($i + 1) . ': service must be a lowercase id (letters, numbers, - or _, max 40 characters).');
            $row['service'] = $service;
        }
        foreach (array('sub', 'short', 'icon') as $optional) {
            if (isset($door[$optional]) && is_string($door[$optional]) && trim($door[$optional]) !== '') $row[$optional] = mb_substr(trim($door[$optional]), 0, 80);
        }
        $out[] = $row;
    }
    return $out;
}

function dt_va_flow_doors_config() {
    $saved = get_option('dt_va_flow_doors', array());
    return is_array($saved) && $saved ? $saved : array();
}

/* ================= STEPS (Peça 1b) =================
   The questions asked after a door is picked. Only choice/text steps are configurable; the
   fixed pipeline (work/money/timing/capture) keeps its own internal chaining in front.js and
   can only be referenced as a target, never redefined. A step not overridden here keeps using
   the built-in front.js behaviour for that id, so a partial override is safe by default. */
function dt_va_flow_step_types() { return array('choice', 'text'); }
function dt_va_flow_condition_ops() { return array('answerContains', 'answerMissing', 'answerEquals'); }
/** ids front.js already knows how to run; a step's "next" may target these even when the admin never defines them. */
function dt_va_flow_builtin_step_ids() {
    return array('open', 'enquiry1', 'client1', 'describe', 'work', 'money', 'timing', 'capture', 'other', 'human');
}

/** Recursively validates a "next" value: a step id string, or a branch {if:{key,op:...}, then, else}. Returns the cleaned value or a WP_Error. */
function dt_va_flow_step_next_validate($next, $depth = 0) {
    if ($depth > 5) return new WP_Error('flow', 'Branch nesting is too deep (max 5 levels).');
    if (is_string($next)) {
        $next = trim($next);
        return $next === '' ? new WP_Error('flow', 'A step id is required.') : $next;
    }
    if (!is_array($next) || !isset($next['if']) || !is_array($next['if'])) return new WP_Error('flow', '"next" must be a step id string, or a branch object with "if"/"then".');
    $cond = $next['if'];
    $key = isset($cond['key']) && is_string($cond['key']) ? trim($cond['key']) : '';
    $op = null;
    foreach (dt_va_flow_condition_ops() as $candidate) if (array_key_exists($candidate, $cond)) { $op = $candidate; break; }
    if ($key === '' || $op === null) return new WP_Error('flow', 'Branch condition needs a "key" and one of: ' . implode(', ', dt_va_flow_condition_ops()) . '.');
    $condOut = array('key' => mb_substr($key, 0, 40));
    if ($op === 'answerContains') {
        $words = array();
        if (is_array($cond['answerContains'])) foreach ($cond['answerContains'] as $w) if (is_string($w) && trim($w) !== '') $words[] = mb_strtolower(mb_substr(trim($w), 0, 40));
        if (!$words) return new WP_Error('flow', 'answerContains needs at least one word.');
        $condOut['answerContains'] = $words;
    } elseif ($op === 'answerEquals') {
        $val = $cond['answerEquals'];
        if (!is_string($val) || trim($val) === '') return new WP_Error('flow', 'answerEquals needs a non-empty string.');
        $condOut['answerEquals'] = mb_substr(trim($val), 0, 80);
    } else {
        $condOut['answerMissing'] = true;
    }
    if (!isset($next['then'])) return new WP_Error('flow', 'A branch needs "then".');
    $then = dt_va_flow_step_next_validate($next['then'], $depth + 1);
    if (is_wp_error($then)) return $then;
    $out = array('if' => $condOut, 'then' => $then);
    if (isset($next['else'])) {
        $else = dt_va_flow_step_next_validate($next['else'], $depth + 1);
        if (is_wp_error($else)) return $else;
        $out['else'] = $else;
    }
    return $out;
}

/** Every string reachable inside a "next" value must resolve to a known step id: one defined in this same submission, or one of the built-ins. */
function dt_va_flow_step_next_refs($next, $known) {
    if (is_string($next)) return in_array($next, $known, true) ? true : new WP_Error('flow', '"next" points to unknown step "' . $next . '".');
    $then = dt_va_flow_step_next_refs($next['then'], $known);
    if (is_wp_error($then)) return $then;
    if (isset($next['else'])) { $else = dt_va_flow_step_next_refs($next['else'], $known); if (is_wp_error($else)) return $else; }
    return true;
}

function dt_va_flow_steps_validate($json) {
    $decoded = json_decode((string) $json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return new WP_Error('flow', 'Not valid JSON, or not a JSON object.');
    if (count($decoded) < 1 || count($decoded) > 60) return new WP_Error('flow', 'Provide between 1 and 60 steps.');
    $types = dt_va_flow_step_types();
    $out = array();
    foreach ($decoded as $id => $step) {
        $id = is_string($id) ? trim($id) : (is_int($id) ? '' : '');
        if ($id === '' || !is_array($step)) return new WP_Error('flow', 'Every step needs a non-empty id (object key) and must itself be an object.');
        $type = isset($step['type']) && in_array($step['type'], $types, true) ? $step['type'] : '';
        if ($type === '') return new WP_Error('flow', 'Step "' . $id . '": type must be one of ' . implode(', ', $types) . '.');
        $question = isset($step['question']) && is_string($step['question']) ? trim($step['question']) : '';
        $answerKey = isset($step['answerKey']) && is_string($step['answerKey']) ? trim($step['answerKey']) : '';
        if ($question === '' || $answerKey === '') return new WP_Error('flow', 'Step "' . $id . '": needs a question and an answerKey.');
        $row = array('type' => $type, 'question' => mb_substr($question, 0, 400), 'answerKey' => mb_substr($answerKey, 0, 40));
        if (isset($step['example']) && is_string($step['example']) && trim($step['example']) !== '') $row['example'] = mb_substr(trim($step['example']), 0, 400);
        if ($type === 'choice') {
            $options = array();
            if (isset($step['options']) && is_array($step['options'])) foreach ($step['options'] as $o) if (is_string($o) && trim($o) !== '') $options[] = mb_substr(trim($o), 0, 80);
            if (count($options) < 1 || count($options) > 8) return new WP_Error('flow', 'Step "' . $id . '": choice needs between 1 and 8 options.');
            $row['options'] = $options;
        }
        if (!isset($step['next'])) return new WP_Error('flow', 'Step "' . $id . '": needs "next".');
        $next = dt_va_flow_step_next_validate($step['next']);
        if (is_wp_error($next)) return new WP_Error('flow', 'Step "' . $id . '": ' . $next->get_error_message());
        $row['next'] = $next;
        $out[mb_substr($id, 0, 60)] = $row;
    }
    $known = array_merge(array_keys($out), dt_va_flow_builtin_step_ids());
    foreach ($out as $id => $row) {
        $ok = dt_va_flow_step_next_refs($row['next'], $known);
        if (is_wp_error($ok)) return new WP_Error('flow', 'Step "' . $id . '": ' . $ok->get_error_message());
    }
    return $out;
}

function dt_va_flow_steps_config() {
    $saved = get_option('dt_va_flow_steps', array());
    return is_array($saved) && $saved ? $saved : array();
}

/** Shown in the admin textarea as a starting example; not a copy of the real front.js flow. */
function dt_va_flow_steps_starter() {
    return array(
        'enquiry1' => array(
            'type' => 'choice', 'question' => 'What are you enquiring about?', 'answerKey' => 'type',
            'options' => array('A product', 'A service', 'Not sure yet'), 'next' => 'describe',
        ),
        'describe' => array(
            'type' => 'text', 'question' => "Tell me a little about the business and who it's for.", 'answerKey' => 'business', 'next' => 'work',
        ),
    );
}

function dt_va_flow_steps_settings_process() {
    if (!current_user_can('manage_options')) return array('notice' => '', 'error' => '', 'raw' => null);
    $notice = ''; $error = ''; $raw = null;
    if (isset($_POST['dt_va_flow_add_step']) && check_admin_referer('dt_va_flow_add_step')) {
        $base = dt_va_flow_steps_config();
        $id = isset($_POST['add_id']) ? trim(wp_unslash($_POST['add_id'])) : '';
        if ($id === '') {
            $error = 'Enter a step id.';
        } else {
            $new = array(
                'type' => isset($_POST['add_type']) ? trim(wp_unslash($_POST['add_type'])) : '',
                'question' => isset($_POST['add_question']) ? trim(wp_unslash($_POST['add_question'])) : '',
                'answerKey' => isset($_POST['add_answerkey']) ? trim(wp_unslash($_POST['add_answerkey'])) : '',
                'next' => isset($_POST['add_next']) ? trim(wp_unslash($_POST['add_next'])) : '',
            );
            if ($new['type'] === 'choice') {
                $opts = isset($_POST['add_options']) ? trim(wp_unslash($_POST['add_options'])) : '';
                $new['options'] = array_values(array_filter(array_map('trim', explode(',', $opts))));
            }
            $example = isset($_POST['add_example']) ? trim(wp_unslash($_POST['add_example'])) : '';
            if ($example !== '') $new['example'] = $example;
            $candidate = $base;
            $candidate[$id] = $new;
            $parsed = dt_va_flow_steps_validate(wp_json_encode($candidate));
            if (is_wp_error($parsed)) $error = $parsed->get_error_message();
            else { update_option('dt_va_flow_steps', $parsed, false); $notice = 'Step added.'; }
        }
    } elseif (isset($_POST['dt_va_flow_steps']) && check_admin_referer('dt_va_flow_steps')) {
        if (isset($_POST['restore_default'])) {
            delete_option('dt_va_flow_steps');
            $notice = 'Default step questions restored.';
        } else {
            $raw = isset($_POST['flow_steps']) && is_string($_POST['flow_steps']) ? wp_unslash($_POST['flow_steps']) : '';
            $parsed = dt_va_flow_steps_validate($raw);
            if (is_wp_error($parsed)) $error = $parsed->get_error_message();
            else { update_option('dt_va_flow_steps', $parsed, false); $notice = 'Step questions saved.'; }
        }
    }
    if ($notice !== '') delete_option('dt_va_setup_review_doors');
    return array('notice' => $notice, 'error' => $error, 'raw' => $raw);
}

function dt_va_flow_steps_settings() {
    if (!current_user_can('manage_options')) return;
    $result = $GLOBALS['dt_va_setup_processed']['dt_va_flow_steps_settings'] ?? dt_va_flow_steps_settings_process();
    $notice = $result['notice']; $error = $result['error']; $raw = $result['raw'];
    if ($notice || $error) echo '<div class="notice ' . ($error ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($error ?: $notice) . '</p></div>';
    echo '<h3>Add a step</h3><p class="description">Adds one step without hand-editing JSON. <code>next</code> here only accepts a plain step id (no branching); use the JSON below for a branch. <code>options</code> is comma-separated and only used when Type is "choice".</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_flow_add_step');
    echo '<input type="hidden" name="dt_va_flow_add_step" value="1"><table class="form-table">';
    echo '<tr><th><label for="dt-va-step-id">Step id</label></th><td><input class="regular-text" id="dt-va-step-id" name="add_id" placeholder="e.g. cook1" required></td></tr>';
    echo '<tr><th><label for="dt-va-step-type">Type</label></th><td><select id="dt-va-step-type" name="add_type"><option value="choice">choice</option><option value="text">text</option></select></td></tr>';
    echo '<tr><th><label for="dt-va-step-question">Question</label></th><td><input class="regular-text" id="dt-va-step-question" name="add_question" required></td></tr>';
    echo '<tr><th><label for="dt-va-step-example">Example (optional)</label></th><td><input class="regular-text" id="dt-va-step-example" name="add_example"></td></tr>';
    echo '<tr><th><label for="dt-va-step-answerkey">Answer key</label></th><td><input class="regular-text" id="dt-va-step-answerkey" name="add_answerkey" placeholder="e.g. type" required></td></tr>';
    echo '<tr><th><label for="dt-va-step-options">Options</label></th><td><input class="regular-text" id="dt-va-step-options" name="add_options" placeholder="Comma-separated, e.g. A new business, A refresh, Not sure yet"></td></tr>';
    echo '<tr><th><label for="dt-va-step-next">Next step id</label></th><td><input class="regular-text" id="dt-va-step-next" name="add_next" placeholder="e.g. describe" required></td></tr>';
    echo '</table><p><button class="button">Add step</button></p></form><hr>';
    $saved = dt_va_flow_steps_config();
    $v = $saved ? $saved : dt_va_flow_steps_starter();
    echo '<h2>Step questions</h2><p>The questions asked after a door is picked, keyed by step id. Each step needs a <code>type</code> (<code>choice</code> or <code>text</code>), a <code>question</code>, an <code>answerKey</code> (where the answer is stored) and a <code>next</code> step id. <code>choice</code> also needs 1&ndash;8 <code>options</code>.</p>';
    echo '<p class="description">A step id you don\'t redefine here keeps its built-in question and behaviour, so you can override just one step at a time. Step ids already used by the assistant: <code>' . esc_html(implode(', ', dt_va_flow_builtin_step_ids())) . '</code>. <code>next</code> can target any of those even if you never define them here \u2014 the fixed pipeline (<code>work</code>/<code>money</code>/<code>timing</code>/<code>capture</code>) always collects the budget, timing and contact details itself and cannot be redefined, only referenced.</p>';
    echo '<p class="description">Branch instead of a plain <code>next</code> id: <code>{"if": {"key": "type", "answerContains": ["brochure"]}, "then": "size", "else": "describe"}</code>. Condition keys: <code>' . esc_html(implode(', ', dt_va_flow_condition_ops())) . '</code>.</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_flow_steps');
    echo '<input type="hidden" name="dt_va_flow_steps" value="1">';
    echo '<textarea name="flow_steps" rows="24" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">' . esc_textarea(wp_json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</textarea>';
    echo '<p class="description">' . ($saved ? 'Using the saved steps (' . count($saved) . ').' : 'Using the built-in steps; the text above is only a starting example.') . '</p>';
    echo '<p><button class="button button-primary">Save step questions</button> <button class="button" name="restore_default" value="1" formnovalidate>Restore default</button>';
    dt_va_json_tools('flow_steps');
    echo '</p><p class="description">Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.</p></form>';
}

function dt_va_flow_settings_process() {
    if (!current_user_can('manage_options')) return array('notice' => '', 'error' => '', 'raw' => null);
    $notice = ''; $error = ''; $raw = null;
    if (isset($_POST['dt_va_flow_add_door']) && check_admin_referer('dt_va_flow_add_door')) {
        $existing = dt_va_flow_doors_config();
        $base = $existing ? $existing : dt_va_flow_doors_default();
        $new = array(
            'id' => isset($_POST['add_id']) ? trim(wp_unslash($_POST['add_id'])) : '',
            'label' => isset($_POST['add_label']) ? trim(wp_unslash($_POST['add_label'])) : '',
            'action' => isset($_POST['add_action']) ? trim(wp_unslash($_POST['add_action'])) : '',
        );
        if ($new['action'] === 'service') $new['service'] = isset($_POST['add_service']) ? trim(wp_unslash($_POST['add_service'])) : '';
        foreach (array('sub', 'short', 'icon') as $f) {
            $v = isset($_POST['add_' . $f]) ? trim(wp_unslash($_POST['add_' . $f])) : '';
            if ($v !== '') $new[$f] = $v;
        }
        $candidate = array_merge($base, array($new));
        $parsed = dt_va_flow_doors_validate(wp_json_encode($candidate));
        if (is_wp_error($parsed)) $error = $parsed->get_error_message();
        else { update_option('dt_va_flow_doors', $parsed, false); $notice = 'Door added.'; }
    } elseif (isset($_POST['dt_va_flow']) && check_admin_referer('dt_va_flow')) {
        if (isset($_POST['restore_default'])) {
            delete_option('dt_va_flow_doors');
            $notice = 'Default doors restored.';
        } else {
            $raw = isset($_POST['flow_doors']) && is_string($_POST['flow_doors']) ? wp_unslash($_POST['flow_doors']) : '';
            $parsed = dt_va_flow_doors_validate($raw);
            if (is_wp_error($parsed)) $error = $parsed->get_error_message();
            else { update_option('dt_va_flow_doors', $parsed, false); $notice = 'Doors saved.'; }
        }
    }
    if ($notice !== '') delete_option('dt_va_setup_review_doors');
    return array('notice' => $notice, 'error' => $error, 'raw' => $raw);
}

function dt_va_flow_settings() {
    if (!current_user_can('manage_options')) return;
    $result = $GLOBALS['dt_va_setup_processed']['dt_va_flow_settings'] ?? dt_va_flow_settings_process();
    $notice = $result['notice']; $error = $result['error']; $raw = $result['raw'];
    if ($notice || $error) echo '<div class="notice ' . ($error ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($error ?: $notice) . '</p></div>';
    echo '<h3>Add a door</h3><p class="description">Adds one door without hand-editing JSON. Service is only used when Action is "service".</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_flow_add_door');
    echo '<input type="hidden" name="dt_va_flow_add_door" value="1"><table class="form-table">';
    echo '<tr><th><label for="dt-va-door-id">Id</label></th><td><input class="regular-text" id="dt-va-door-id" name="add_id" required></td></tr>';
    echo '<tr><th><label for="dt-va-door-label">Label</label></th><td><input class="regular-text" id="dt-va-door-label" name="add_label" required></td></tr>';
    echo '<tr><th><label for="dt-va-door-action">Action</label></th><td><select id="dt-va-door-action" name="add_action"><option value="service">service</option><option value="other">other</option><option value="human">human</option></select></td></tr>';
    echo '<tr><th><label for="dt-va-door-service">Service</label></th><td><input class="regular-text" id="dt-va-door-service" name="add_service" placeholder="e.g. plumbing"></td></tr>';
    echo '<tr><th><label for="dt-va-door-sub">Sub (optional)</label></th><td><input class="regular-text" id="dt-va-door-sub" name="add_sub"></td></tr>';
    echo '<tr><th><label for="dt-va-door-short">Short (optional)</label></th><td><input class="regular-text" id="dt-va-door-short" name="add_short"></td></tr>';
    echo '<tr><th><label for="dt-va-door-icon">Icon (optional)</label></th><td><select id="dt-va-door-icon" name="add_icon"><option value="">(default)</option><option>logo</option><option>print</option><option>web</option><option>client</option><option>other</option><option>human</option></select></td></tr>';
    echo '</table><p><button class="button">Add door</button></p></form><hr>';
    $saved = dt_va_flow_doors_config();
    $v = $saved ? $saved : dt_va_flow_doors_default();
    echo '<h2>Doors (initial menu)</h2><p>The buttons visitors see when the assistant opens. Each door needs an <code>id</code>, a <code>label</code> and an <code>action</code> (<code>service</code>, <code>other</code> or <code>human</code>); <code>service</code> doors also need a <code>service</code>: a lowercase id (letters, numbers, <code>-</code> or <code>_</code>) of your choosing, not limited to <code>logo</code>/<code>print</code>/<code>web</code>/<code>client</code> \u2014 use whatever names fit this installation\'s services. Optional: <code>sub</code>, <code>short</code>, <code>icon</code> (<code>logo</code>, <code>print</code>, <code>web</code>, <code>client</code>, <code>other</code> or <code>human</code>; any other value falls back to a generic icon).</p>';
    echo '<p class="description">A custom <code>service</code> needs a matching step id (<code>service</code> + <code>"1"</code>, e.g. <code>plumbing1</code>) defined below in Step questions \u2014 otherwise picking that door just restarts the assistant.</p>';
    echo '<form method="post">'; wp_nonce_field('dt_va_flow');
    echo '<input type="hidden" name="dt_va_flow" value="1">';
    echo '<textarea name="flow_doors" rows="24" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">' . esc_textarea(wp_json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</textarea>';
    echo '<p class="description">' . ($saved ? 'Using the saved doors.' : 'Using the default doors.') . '</p>';
    echo '<p><button class="button button-primary">Save doors</button> <button class="button" name="restore_default" value="1" formnovalidate>Restore default</button>';
    dt_va_json_tools('flow_doors');
    echo '</p><p class="description">Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.</p></form><hr>';
    dt_va_flow_steps_settings();
    dt_va_setup_review_controls('doors');
}
