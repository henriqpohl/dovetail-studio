<?php

/** Remote AI integrations. No frontend code receives credentials or upstream errors. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_provider_key( $provider ) {
	$name = 'DT_VA_' . strtoupper( $provider ) . '_KEY';

	if ( defined( $name ) && '' !== constant( $name ) ) {
		return trim( (string) constant( $name ) );
	}

	$env = getenv( $name );

	return false !== $env && '' !== trim( $env ) ? trim( $env ) : dt_va_stored_key( $provider );
}

/** Context of the AI key encryption (shared Core helper, same format as the Newsletter Mailchimp key). */
const DT_VA_CRYPTO_CONTEXT = 'dovetail-virtual-assistant';

/** Saved admin key, decrypted. Keys are always stored encrypted (see dt_va_save_key()). */
function dt_va_stored_key( $provider ) {
	$stored = (string) get_option( 'dt_va_' . $provider . '_key', '' );

	if ( '' === $stored ) {
		return '';
	}

	return trim( \DovetailStudio\Core\Crypto::decrypt( $stored, DT_VA_CRYPTO_CONTEXT ) );
}

/** Encrypt and save an admin key. Returns false when encryption is unavailable (nothing is saved in plain text). */
function dt_va_save_key( $provider, $key ) {
	$encrypted = \DovetailStudio\Core\Crypto::encrypt( (string) $key, DT_VA_CRYPTO_CONTEXT );

	if ( is_wp_error( $encrypted ) ) {
		return false;
	}

	update_option( 'dt_va_' . $provider . '_key', $encrypted, false );

	return true;
}

/** Empty external values fall through; invalid nonempty IDs disable the provider. */
function dt_va_external_model( $provider ) {
	$name = 'DT_VA_' . strtoupper( $provider ) . '_MODEL';

	if ( defined( $name ) && '' !== trim( (string) constant( $name ) ) ) {
		return array(
			'value'  => trim( (string) constant( $name ) ),
			'source' => 'wp-config.php',
		);
	}

	$env = getenv( $name );

	if ( false !== $env && '' !== trim( $env ) ) {
		return array(
			'value'  => trim( $env ),
			'source' => 'environment',
		);
	}

	return null;
}

function dt_va_provider_config( $resolve_external = true ) {
	$saved           = get_option( 'dt_va_providers', array() );
	$saved           = is_array( $saved ) ? $saved : array();
	$c               = array_merge(
		array(
			'primary'         => 'openai',
			'secondary'       => 'anthropic',
			'openai_model'    => '',
			'anthropic_model' => '',
			'timeout'         => 10,
			'max_tokens'      => 700,
		),
		$saved
	);
	$c['primary']    = in_array( $c['primary'], array( 'openai', 'anthropic' ), true )
		? $c['primary']
		: 'openai';
	$c['secondary']  = in_array( $c['secondary'], array( 'openai', 'anthropic', 'none' ), true )
		? $c['secondary']
		: 'none';
	$c['timeout']    = max( 3, min( 12, (int) $c['timeout'] ) );
	$c['max_tokens'] = max( 64, min( 4096, (int) $c['max_tokens'] ) );

	foreach ( array( 'openai', 'anthropic' ) as $p ) {
		$external           = $resolve_external ? dt_va_external_model( $p ) : null;
		$m                  = null !== $external ? $external['value'] : $c[ $p . '_model' ];
		$c[ $p . '_model' ] = is_string( $m ) && preg_match( '/^[a-zA-Z0-9._:-]{1,100}$/', $m ) ? $m : '';
	}

	return $c;
}

function dt_va_configured_providers() {
	$c = dt_va_provider_config();

	return array_values(
		array_filter(
			array_unique( array( $c['primary'], $c['secondary'] ) ),
			function ( $p ) use ( $c ) {
				return 'none' !== $p && '' !== dt_va_provider_key( $p ) && '' !== $c[ $p . '_model' ];
			}
		)
	);
}

/** Previous turns from the visitor's browser plus the current question q. */
function dt_va_messages( $history, $q ) {
	$messages = array();

	foreach ( is_array( $history ) ? array_slice( $history, -10 ) : array() as $m ) {
		if ( ! is_array( $m )
			|| ! isset( $m['role'], $m['content'] )
			|| ! in_array( $m['role'], array( 'user', 'assistant' ), true )
			|| ! is_string( $m['content'] )
		) {
			continue;
		}

		$text = dt_va_clean( $m['content'], 1500 );

		if ( '' !== $text ) {
			$messages[] = array(
				'role'    => $m['role'],
				'content' => $text,
			);
		}
	}

	// Keep recent context within a character budget, independent of provider tokenization.
	$kept      = array();
	$remaining = 8000 - mb_strlen( $q );

	foreach ( array_reverse( $messages ) as $m ) {
		$m['content'] = mb_substr( $m['content'], 0, $remaining );
		array_unshift( $kept, $m );
		$remaining -= mb_strlen( $m['content'] );

		if ( $remaining <= 0 ) {
			break;
		}
	}

	while ( $kept && 'user' !== $kept[0]['role'] ) {
		array_shift( $kept );
	}

	$kept[] = array(
		'role'    => 'user',
		'content' => $q,
	);

	return $kept;
}

/**
 * @param string|array $system A plain prompt, or dt_va_system_prompt()'s array('cached' => …, 'turn' => …):
 *                             the cached part first so the provider's prompt cache can reuse it across visitors.
 */
function dt_va_provider_request( $provider, $messages, $system, $timeout, $config ) {
	$headers = array( 'content-type' => 'application/json' );
	$cached  = is_array( $system ) ? (string) ( $system['cached'] ?? '' ) : (string) $system;
	$turn    = is_array( $system ) ? (string) ( $system['turn'] ?? '' ) : '';

	if ( 'openai' === $provider ) {
		$url                      = 'https://api.openai.com/v1/responses';
		$headers['Authorization'] = 'Bearer ' . dt_va_provider_key( $provider );
		// OpenAI caches identical prompt prefixes by itself; the changing part is kept at the end.
		$body = array(
			'model'             => $config['openai_model'],
			'instructions'      => $cached . ( '' !== $turn ? "\n\n" . $turn : '' ),
			'input'             => $messages,
			'max_output_tokens' => $config['max_tokens'],
			'store'             => false,
		);
	} else {
		$url                          = 'https://api.anthropic.com/v1/messages';
		$headers['x-api-key']         = dt_va_provider_key( $provider );
		$headers['anthropic-version'] = '2023-06-01';
		$blocks                       = array(
			array(
				'type'          => 'text',
				'text'          => $cached,
				'cache_control' => array( 'type' => 'ephemeral' ),
			),
		);

		if ( '' !== $turn ) {
			$blocks[] = array(
				'type' => 'text',
				'text' => $turn,
			);
		}

		$body = array(
			'model'      => $config['anthropic_model'],
			'system'     => $blocks,
			'messages'   => $messages,
			'max_tokens' => $config['max_tokens'],
		);
	}

	$r = wp_remote_post(
		$url,
		array(
			'timeout'             => $timeout,
			'redirection'         => 0,
			'limit_response_size' => 262144,
			'headers'             => $headers,
			'body'                => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $r ) ) {
		return array( 'error' => 'network' );
	}

	$status = (int) wp_remote_retrieve_response_code( $r );

	if ( $status < 200 || $status >= 300 ) {
		return array( 'error' => 'http_' . $status );
	}

	$j = json_decode( wp_remote_retrieve_body( $r ), true );

	if ( ! is_array( $j ) ) {
		return array( 'error' => 'invalid_json' );
	}

	$text = '';

	if ( 'openai' === $provider ) {
		if ( 'completed' !== ( $j['status'] ?? '' ) ) {
			return array( 'error' => 'incomplete' );
		}

		// Raw HTTP responses contain output items; output_text is an SDK convenience.
		foreach ( ( $j['output'] ?? array() ) as $item ) {
			if ( 'message' !== ( $item['type'] ?? '' ) ) {
				continue;
			}

			foreach ( ( $item['content'] ?? array() ) as $part ) {
				if ( 'output_text' === ( $part['type'] ?? '' ) && is_string( $part['text'] ?? null ) ) {
					$text .= $part['text'];
				}
			}
		}
	} else {
		if ( in_array( $j['stop_reason'] ?? '', array( 'max_tokens', 'refusal' ), true ) ) {
			return array( 'error' => 'incomplete' );
		}

		foreach ( ( $j['content'] ?? array() ) as $part ) {
			if ( 'text' === ( $part['type'] ?? '' ) && is_string( $part['text'] ?? null ) ) {
				$text .= $part['text'];
			}
		}
	}

	return '' === trim( $text ) ? array( 'error' => 'empty_answer' ) : array( 'answer' => trim( $text ) );
}

/** One attempt per configured provider: no retry loop that doubles latency and cost. */
function dt_va_provider_answer( $messages, $system ) {
	$config   = dt_va_provider_config();
	$attempts = array();
	$deadline = microtime( true ) + max( 3, (int) DT_VA_ASK_DEADLINE );

	foreach ( dt_va_configured_providers() as $p ) {
		$remaining = $deadline - microtime( true );

		if ( $remaining < 1 ) {
			break;
		}

		$start = microtime( true );
		$r     = dt_va_provider_request( $p, $messages, $system, min( $config['timeout'], $remaining ), $config );
		// Only operational metadata is persisted. Never save prompts, answers or credentials.
		$attempts[] = array(
			'provider' => $p,
			'result'   => isset( $r['answer'] ) ? 'ok' : $r['error'],
			'ms'       => (int) ( ( microtime( true ) - $start ) * 1000 ),
		);

		if ( isset( $r['answer'] ) ) {
			dt_va_record_provider_result( $attempts );

			return array(
				'answer'   => $r['answer'],
				'provider' => $p,
				'attempts' => $attempts,
			);
		}
	}

	dt_va_record_provider_result( $attempts );

	return array(
		'error'    => $attempts ? 'providers_unavailable' : 'not_configured',
		'attempts' => $attempts,
	);
}

/** Saves the diagnostics shown in Settings, only when the outcome changed or the saved one is an hour old: not one database write per question. */
function dt_va_record_provider_result( $attempts ) {
	$saved   = get_option( 'dt_va_last_provider_result', array() );
	$outcome = function ( $items ) {
		return array_map(
			function ( $a ) {
				return ( $a['provider'] ?? '' ) . ':' . ( $a['result'] ?? '' );
			},
			is_array( $items ) ? $items : array()
		);
	};
	$fresh   = is_array( $saved ) && isset( $saved['at'] ) && time() - (int) $saved['at'] < HOUR_IN_SECONDS;

	if ( $fresh && $outcome( $saved['attempts'] ?? array() ) === $outcome( $attempts ) ) {
		return;
	}

	update_option(
		'dt_va_last_provider_result',
		array(
			'at'       => time(),
			'attempts' => $attempts,
		),
		false
	);
}
