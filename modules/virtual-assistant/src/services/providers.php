<?php
/** Remote AI integrations. No frontend code receives credentials or upstream errors. */
if (!defined('ABSPATH')) exit;

function dt_va_provider_key($provider) {
    $name = 'DT_VA_' . strtoupper($provider) . '_KEY';
    if (defined($name) && constant($name) !== '') return trim((string) constant($name));
    $env = getenv($name);
    return $env !== false && trim($env) !== '' ? trim($env) : trim((string) get_option('dt_va_' . $provider . '_key', ''));
}

/** Empty external values fall through; invalid nonempty IDs disable the provider. */
function dt_va_external_model($provider) {
    $name = 'DT_VA_' . strtoupper($provider) . '_MODEL';
    if (defined($name) && trim((string) constant($name)) !== '') return array('value' => trim((string) constant($name)), 'source' => 'wp-config.php');
    $env = getenv($name);
    if ($env !== false && trim($env) !== '') return array('value' => trim($env), 'source' => 'environment');
    return null;
}

function dt_va_provider_config($resolve_external = true) {
    $saved = get_option('dt_va_providers', array());
    $saved = is_array($saved) ? $saved : array();
    $c = array_merge(array('primary' => 'openai', 'secondary' => 'anthropic', 'openai_model' => '', 'anthropic_model' => '', 'timeout' => 10, 'max_tokens' => 700), $saved);
    $c['primary'] = in_array($c['primary'], array('openai', 'anthropic'), true) ? $c['primary'] : 'openai';
    $c['secondary'] = in_array($c['secondary'], array('openai', 'anthropic', 'none'), true) ? $c['secondary'] : 'none';
    $c['timeout'] = max(3, min(12, (int) $c['timeout']));
    $c['max_tokens'] = max(64, min(4096, (int) $c['max_tokens']));
    foreach (array('openai', 'anthropic') as $p) {
        $external = $resolve_external ? dt_va_external_model($p) : null;
        $m = $external !== null ? $external['value'] : $c[$p . '_model'];
        $c[$p . '_model'] = is_string($m) && preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $m) ? $m : '';
    }
    return $c;
}

function dt_va_configured_providers() {
    $c = dt_va_provider_config();
    return array_values(array_filter(array_unique(array($c['primary'], $c['secondary'])), function ($p) use ($c) {
        return $p !== 'none' && dt_va_provider_key($p) !== '' && $c[$p . '_model'] !== '';
    }));
}

/** q is authoritative; old clients may also include it as their last history item. */
function dt_va_messages($history, $q) {
    $messages = array();
    foreach (is_array($history) ? array_slice($history, -10) : array() as $m) {
        if (!is_array($m) || !isset($m['role'], $m['content']) || !in_array($m['role'], array('user', 'assistant'), true) || !is_string($m['content'])) continue;
        $text = dt_va_clean($m['content'], 1500);
        if ($text !== '') $messages[] = array('role' => $m['role'], 'content' => $text);
    }
    if ($messages && end($messages)['role'] === 'user' && dt_va_clean(end($messages)['content'], 1000) === $q) array_pop($messages);
    // Keep recent context within a character budget, independent of provider tokenization.
    $kept = array(); $remaining = 8000 - mb_strlen($q);
    foreach (array_reverse($messages) as $m) {
        $m['content'] = mb_substr($m['content'], 0, $remaining);
        array_unshift($kept, $m);
        $remaining -= mb_strlen($m['content']);
        if ($remaining <= 0) break;
    }
    while ($kept && $kept[0]['role'] !== 'user') array_shift($kept);
    $kept[] = array('role' => 'user', 'content' => $q);
    return $kept;
}

function dt_va_provider_request($provider, $messages, $system, $timeout, $config) {
    $headers = array('content-type' => 'application/json');
    if ($provider === 'openai') {
        $url = 'https://api.openai.com/v1/responses';
        $headers['Authorization'] = 'Bearer ' . dt_va_provider_key($provider);
        $body = array('model' => $config['openai_model'], 'instructions' => $system, 'input' => $messages, 'max_output_tokens' => $config['max_tokens'], 'store' => false);
    } else {
        $url = 'https://api.anthropic.com/v1/messages';
        $headers['x-api-key'] = dt_va_provider_key($provider);
        $headers['anthropic-version'] = '2023-06-01';
        $body = array('model' => $config['anthropic_model'], 'system' => $system, 'messages' => $messages, 'max_tokens' => $config['max_tokens']);
    }
    $r = wp_remote_post($url, array('timeout' => $timeout, 'redirection' => 0, 'limit_response_size' => 262144, 'headers' => $headers, 'body' => wp_json_encode($body)));
    if (is_wp_error($r)) return array('error' => 'network');
    $status = (int) wp_remote_retrieve_response_code($r);
    if ($status < 200 || $status >= 300) return array('error' => 'http_' . $status);
    $j = json_decode(wp_remote_retrieve_body($r), true);
    if (!is_array($j)) return array('error' => 'invalid_json');
    $text = '';
    if ($provider === 'openai') {
        if (($j['status'] ?? '') !== 'completed') return array('error' => 'incomplete');
        // Raw HTTP responses contain output items; output_text is an SDK convenience.
        foreach (($j['output'] ?? array()) as $item) {
            if (($item['type'] ?? '') !== 'message') continue;
            foreach (($item['content'] ?? array()) as $part) if (($part['type'] ?? '') === 'output_text' && is_string($part['text'] ?? null)) $text .= $part['text'];
        }
    } else {
        if (in_array($j['stop_reason'] ?? '', array('max_tokens', 'refusal'), true)) return array('error' => 'incomplete');
        foreach (($j['content'] ?? array()) as $part) if (($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) $text .= $part['text'];
    }
    return trim($text) === '' ? array('error' => 'empty_answer') : array('answer' => trim($text));
}

/** One attempt per configured provider: no retry loop that doubles latency and cost. */
function dt_va_provider_answer($messages, $system) {
    $config = dt_va_provider_config(); $attempts = array();
    $deadline = microtime(true) + 24;
    foreach (dt_va_configured_providers() as $p) {
        $remaining = $deadline - microtime(true);
        if ($remaining < 1) break;
        $start = microtime(true);
        $r = dt_va_provider_request($p, $messages, $system, min($config['timeout'], $remaining), $config);
        // Only operational metadata is persisted. Never save prompts, answers or credentials.
        $attempts[] = array('provider' => $p, 'result' => isset($r['answer']) ? 'ok' : $r['error'], 'ms' => (int) ((microtime(true) - $start) * 1000));
        if (isset($r['answer'])) {
            update_option('dt_va_last_provider_result', array('at' => time(), 'attempts' => $attempts), false);
            return array('answer' => $r['answer'], 'provider' => $p, 'attempts' => $attempts);
        }
    }
    update_option('dt_va_last_provider_result', array('at' => time(), 'attempts' => $attempts), false);
    return array('error' => $attempts ? 'providers_unavailable' : 'not_configured', 'attempts' => $attempts);
}
