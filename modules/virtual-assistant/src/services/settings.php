<?php

/** Provider settings: models are explicit because availability depends on the API account. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=dt_enquiry',
			__( 'Virtual Assistant settings', 'dovetail-studio' ),
			__( 'Settings', 'dovetail-studio' ),
			'manage_options',
			'dt-va-settings',
			'dt_va_settings_page'
		);

		// Shortcut in the Dovetail Studio menu (registered at priority 5) to the settings above.
		add_submenu_page(
			'dovetail-studio',
			__( 'Virtual Assistant', 'dovetail-studio' ),
			__( 'Virtual Assistant', 'dovetail-studio' ),
			'manage_options',
			'dovetail-studio-virtual-assistant',
			'dt_va_studio_shortcut'
		);
	}
);

/**
 * Dovetail Studio → Virtual Assistant: open the settings under the Enquiries menu.
 *
 * @return void
 */
function dt_va_studio_shortcut() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'dovetail-studio' ) );
	}

	wp_safe_redirect( admin_url( 'edit.php?post_type=dt_enquiry&page=dt-va-settings' ) );
	exit;
}

function dt_va_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$allowed_tabs = array(
		'ai-keys',
		'company',
		'theme',
		'ai-assistant',
		'knowledge',
		'flow',
		'faq',
		'case-studies',
	);
	$tab          = isset( $_GET['tab'] ) && in_array( $_GET['tab'], $allowed_tabs, true )
		? sanitize_key( wp_unslash( $_GET['tab'] ) )
		: 'ai-keys';
	echo '<div class="wrap dovetail-wrap">';
	echo '<div class="dovetail-page-header"><div><h1>'
		. esc_html__( 'Virtual Assistant', 'dovetail-studio' ) . '</h1><p>'
		. esc_html__( 'Configure AI providers, company data, knowledge, conversation flow and appearance.', 'dovetail-studio' )
		. '</p></div><span class="dovetail-version">v' . esc_html( DOVETAIL_VIRTUAL_ASSISTANT_VERSION )
		. '</span></div>';
	echo '<nav class="dovetail-section-nav" aria-label="'
		. esc_attr__( 'Virtual Assistant settings', 'dovetail-studio' ) . '">';

	foreach ( array(
		'ai-keys'      => __( 'AI Keys', 'dovetail-studio' ),
		'company'      => __( 'Company', 'dovetail-studio' ),
		'ai-assistant' => __( 'AI Assistant', 'dovetail-studio' ),
		'knowledge'    => __( 'Knowledge base', 'dovetail-studio' ),
		'flow'         => __( 'Doors', 'dovetail-studio' ),
		'faq'          => __( 'Offline FAQ', 'dovetail-studio' ),
		'case-studies' => __( 'Portfolio', 'dovetail-studio' ),
		'theme'        => __( 'Theme', 'dovetail-studio' ),
	) as $id => $title ) {
		$url = admin_url( 'edit.php?post_type=dt_enquiry&page=dt-va-settings&tab=' . $id );
		echo '<a class="'
			. ( $tab === $id ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '"'
			. ( $tab === $id ? ' aria-current="page"' : '' ) . '>' . esc_html( $title ) . '</a>';
	}

	echo '</nav>';

	if ( 'ai-keys' !== $tab ) {
		echo '<section class="dovetail-card"><div class="dovetail-card-body">';

		if ( 'company' === $tab ) {
			dt_va_company_settings();
		} elseif ( 'ai-assistant' === $tab ) {
			dt_va_ai_assistant_settings();
		} elseif ( 'knowledge' === $tab ) {
			dt_va_knowledge_settings();
		} elseif ( 'flow' === $tab ) {
			dt_va_flow_settings();
		} elseif ( 'case-studies' === $tab ) {
			dt_va_case_studies_settings();
		} elseif ( 'faq' === $tab ) {
			dt_va_faq_settings();
		} elseif ( 'theme' === $tab ) {
			dt_va_theme_settings();
		}

		echo '</div></section></div>';

		return;
	}

	$notice = '';

	if ( isset( $_POST['dt_va_settings'] ) && check_admin_referer( 'dt_va_settings' ) ) {
		$key_error = '';
		$c         = dt_va_provider_config( false );

		// Save only admin values, never persist an external model override into the database.
		foreach ( array( 'primary', 'secondary', 'openai_model', 'anthropic_model', 'timeout', 'max_tokens' ) as $field ) {
			if ( in_array( $field, array( 'openai_model', 'anthropic_model' ), true )
				&& null !== dt_va_external_model( str_replace( '_model', '', $field ) )
			) {
				continue;
			}

			if ( isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ) {
				$c[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}

		update_option( 'dt_va_providers', $c, false );
		update_option( 'dt_va_providers', dt_va_provider_config( false ), false );

		foreach ( array( 'openai', 'anthropic' ) as $p ) {
			// An external secret takes precedence; never copy it into the database or HTML.
			$name = 'DT_VA_' . strtoupper( $p ) . '_KEY';

			if ( ( defined( $name ) && '' !== constant( $name ) ) || getenv( $name ) ) {
				continue;
			}

			if ( ! empty( $_POST[ $p . '_clear' ] ) ) {
				delete_option( 'dt_va_' . $p . '_key' );
			} elseif ( isset( $_POST[ $p . '_key' ] ) && is_string( $_POST[ $p . '_key' ] ) ) {
				$key = trim( wp_unslash( $_POST[ $p . '_key' ] ) );

				if ( '' !== $key
					&& strlen( $key ) <= 512
					&& ! preg_match( '/\s/', $key )
					&& ! dt_va_save_key( $p, $key )
				) {
					$key_error = sprintf(
						/* translators: %s: provider name. */
						__( 'The %s key was not saved: the OpenSSL extension is needed to store it encrypted.', 'dovetail-studio' ),
						'openai' === $p ? 'OpenAI' : __( 'Anthropic', 'dovetail-studio' )
					);
				}
			}
		}

		$notice = __( 'Settings saved.', 'dovetail-studio' )
			. ( ! empty( $key_error ) ? ' ' . $key_error : '' );

		if ( ! empty( $_POST['dt_va_test'] ) ) {
			// Manual tests must not trigger the daily check's notification emails.
			$r = dt_va_provider_answer(
				array(
					array(
						'role'    => 'user',
						'content' => 'Reply with OK.',
					),
				),
				'Connection test. Reply briefly.'
			);
			// A manual test always shows its own timings below.
			update_option(
				'dt_va_last_provider_result',
				array(
					'at'       => time(),
					'attempts' => $r['attempts'],
				),
				false
			);
			$notice .= ' '
				. ( isset( $r['answer'] ) ? sprintf( /* translators: %s: provider name. */ __( 'Working via %s.', 'dovetail-studio' ), $r['provider'] ) : __( 'No usable answer. Check diagnostics below.', 'dovetail-studio' ) );
		}
	}

	$c = dt_va_provider_config();
	echo '<section class="dovetail-card"><div class="dovetail-card-header"><h2>'
		. esc_html__( 'AI Keys', 'dovetail-studio' ) . '</h2><p class="description">'
		. esc_html__( 'Configure a key and model for each provider. Unconfigured providers are skipped. Offline answers remain available.', 'dovetail-studio' )
		. '</p></div><div class="dovetail-card-body">';

	if ( $notice ) {
		\DovetailStudio\Core\AdminScreens::notice( 'info', esc_html( $notice ) );
	}

	echo '<form method="post">';
	wp_nonce_field( 'dt_va_settings' );
	echo '<input type="hidden" name="dt_va_settings" value="1"><table class="form-table">';
	$provider_names = array(
		'none'      => __( 'None', 'dovetail-studio' ),
		'openai'    => 'OpenAI',
		'anthropic' => __( 'Anthropic', 'dovetail-studio' ),
	);

	foreach ( array(
		'primary'   => __( 'Primary provider', 'dovetail-studio' ),
		'secondary' => __( 'Secondary provider', 'dovetail-studio' ),
	) as $field => $field_label ) {
		echo '<tr><th>' . esc_html( $field_label ) . '</th><td><select name="' . esc_attr( $field ) . '">';

		foreach ( 'primary' === $field ? array( 'openai', 'anthropic' ) : array( 'none', 'openai', 'anthropic' ) as $value ) {
			echo '<option value="'
				. esc_attr( $value ) . '" ' . selected( $c[ $field ], $value, false ) . '>'
				. esc_html( $provider_names[ $value ] ) . '</option>';
		}

		echo '</select>'
			. ( 'secondary' === $field ? '<p class="description">' . esc_html__( 'Asked only when the primary provider fails.', 'dovetail-studio' ) . '</p>' : '' )
			. '</td></tr>';
	}

	foreach ( array( 'openai', 'anthropic' ) as $p ) {
		$name     = 'DT_VA_' . strtoupper( $p ) . '_KEY';
		$external = ( defined( $name ) && '' !== constant( $name ) ) || getenv( $name );
		$label    = 'openai' === $p ? 'OpenAI' : __( 'Anthropic', 'dovetail-studio' );
		$key      = dt_va_provider_key( $p );
		// Render only a short prefix as a placeholder, never the saved secret as an input value.
		// Short malformed values are fully masked so a preview cannot expose the entire value.
		$preview = '' === $key
			? ( 'openai' === $p ? 'sk-…' : 'sk-ant-…' )
			: ( strlen( $key ) > 16 ? substr( $key, 0, 12 ) . '…' : '••••••' );
		echo '<tr><th>'
			. esc_html(
				sprintf( /* translators: %s: provider name. */ __( '%s API key', 'dovetail-studio' ), $label )
			) . '</th><td><p>'
			. ( '' !== $key ? esc_html__( 'Configured', 'dovetail-studio' ) : esc_html__( 'Not configured', 'dovetail-studio' ) )
			. ( $external ? ' ' . esc_html__( 'externally (wp-config.php or environment).', 'dovetail-studio' ) : ' ' . esc_html__( 'in admin settings.', 'dovetail-studio' ) )
			. '</p>';

		if ( $external ) {
			echo '<input type="text" class="regular-text code" aria-label="'
				. esc_attr(
					sprintf(
						/* translators: %s: provider name. */
						__( '%s saved key prefix', 'dovetail-studio' ),
						$label
					)
				) . '" value="' . esc_attr( $preview ) . '" disabled><p class="description">'
				. esc_html__( 'Edit the key in wp-config.php or the environment. Only its prefix is displayed here.', 'dovetail-studio' )
				. '</p>';
		} else {
			echo '<input type="password" class="regular-text code" autocomplete="new-password" aria-label="'
				. esc_attr(
					sprintf(
						/* translators: %s: provider name. */
						__( '%s API key', 'dovetail-studio' ),
						$label
					)
				) . '" name="' . esc_attr( $p ) . '_key" value="" placeholder="' . esc_attr( $preview )
				. '"> <label><input type="checkbox" name="' . esc_attr( $p ) . '_clear" value="1"> '
				. esc_html__( 'Clear saved key', 'dovetail-studio' ) . '</label><p class="description">'
				. esc_html__( 'Only the saved key prefix is shown. Leave blank to keep it; paste a new key to replace it. Saved in the WordPress database. Prefer environment or wp-config.php.', 'dovetail-studio' )
				. '</p>';
		}

		$models         = 'openai' === $p
			? array(
				'GPT-6 Luna'   => 'gpt-6-luna',
				'GPT-6 Sol'    => 'gpt-6-sol',
				'GPT-6 Astra'  => 'gpt-6-astra',
				'GPT-4.1 Mini' => 'gpt-4.1-mini',
			)
			: array(
				__( 'Claude Haiku 4.5', 'dovetail-studio' ) => 'claude-haiku-4-5-20251001',
				__( 'Claude Sonnet 5', 'dovetail-studio' ) => 'claude-sonnet-5',
				__( 'Claude Opus 5.5', 'dovetail-studio' ) => 'claude-opus-5-5',
				__( 'Claude Fable 5.1', 'dovetail-studio' ) => 'claude-fable-5-1',
			);
		$catalog        = 'openai' === $p
			? 'https://developers.openai.com/api/docs/models'
			: 'https://platform.claude.com/docs/en/models/overview';
		$external_model = dt_va_external_model( $p );
		echo '</td></tr><tr><th>'
			. esc_html(
				sprintf( /* translators: %s: provider name. */ __( '%s model', 'dovetail-studio' ), $label )
			) . '</th><td><input class="regular-text" name="' . esc_attr( $p ) . '_model" value="'
			. esc_attr( $c[ $p . '_model' ] ) . '"' . ( null !== $external_model ? ' readonly' : '' )
			. '><p class="description">'
			. esc_html__( 'Enter a model ID, not an API key. Blank disables this provider.', 'dovetail-studio' )
			. '</p><p class="description"><strong>'
			. esc_html__( 'Model IDs (reference checked 28 Sep 2026):', 'dovetail-studio' )
			. '</strong></p><ul>';

		if ( null !== $external_model ) {
			echo '<li><strong>'
				. esc_html__( 'Configured via', 'dovetail-studio' ) . ' '
				. esc_html( $external_model['source'] ) . '.</strong> '
				. esc_html__( 'Edit DT_VA_', 'dovetail-studio' ) . esc_html( strtoupper( $p ) )
				. esc_html__( '_MODEL there.', 'dovetail-studio' )
				. ( '' === $c[ $p . '_model' ] ? ' ' . esc_html__( 'Invalid external model ID: provider disabled.', 'dovetail-studio' ) : '' )
				. '</li>';
		}

		// Informational examples, not an allowlist: accounts and model availability change.
		foreach ( $models as $model_name => $id ) {
			echo '<li>' . esc_html( $model_name ) . ': <code>' . esc_html( $id ) . '</code></li>';
		}

		echo '</ul><p class="description"><a href="'
			. esc_url( $catalog ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Official model catalog', 'dovetail-studio' ) . '</a>'
			. esc_html__( '. Availability depends on your API account. Reasoning models may need a larger output-token budget and more time. Use Save and test providers to verify your choice.', 'dovetail-studio' )
			. '</p></td></tr>';

	}

	foreach ( array(
		'timeout'    => array( __( 'Timeout per provider (seconds)', 'dovetail-studio' ), 3, 12 ),
		'max_tokens' => array( __( 'Maximum output tokens', 'dovetail-studio' ), 64, 4096 ),
	) as $field => $meta ) {
		echo '<tr><th>'
			. esc_html( $meta[0] ) . '</th><td><input type="number" name="' . esc_attr( $field ) . '" min="'
			. esc_attr( $meta[1] ) . '" max="' . esc_attr( $meta[2] ) . '" value="' . esc_attr( $c[ $field ] )
			. '"></td></tr>';
	}

	// The scheduled check has its own timestamp; conversations and manual tests must not replace it.
	$daily = get_option( 'dt_va_selfcheck', array() );
	$daily = is_array( $daily ) ? $daily : array();
	echo '<tr><th>' . esc_html__( 'Last daily check', 'dovetail-studio' ) . '</th><td>';

	if ( ! empty( $daily['checked'] ) ) {
		$status = 'ok' === ( $daily['status'] ?? '' )
			? __( 'working', 'dovetail-studio' )
			: ( 'nokey' === ( $daily['status'] ?? '' ) ? __( 'not configured', 'dovetail-studio' ) : __( 'not answering', 'dovetail-studio' ) );
		echo esc_html( wp_date( 'j M Y H:i', (int) $daily['checked'] ) . ': ' . $status );

		if ( ! empty( $daily['detail'] ) ) {
			echo '<p class="description">' . esc_html( $daily['detail'] ) . '</p>';
		}
	} else {
		echo esc_html__( 'Not yet run.', 'dovetail-studio' );
	}

	echo '<p class="description">'
		. esc_html__( 'Time shown in the WordPress site timezone. This is the automatic daily check, not the manual provider test.', 'dovetail-studio' )
		. '</p></td></tr>';
	echo '</table><p><button class="button button-primary">'
		. esc_html__( 'Save', 'dovetail-studio' )
		. '</button> <button class="button" name="dt_va_test" value="1">'
		. esc_html__( 'Save and test providers', 'dovetail-studio' ) . '</button></p></form><h2>'
		. esc_html__( 'Last provider diagnostics', 'dovetail-studio' ) . '</h2><pre>'
		. esc_html( wp_json_encode( get_option( 'dt_va_last_provider_result', array() ), JSON_PRETTY_PRINT ) )
		. '</pre></div></section></div>';
}
