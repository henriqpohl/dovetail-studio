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

/**
 * Enquiries → Settings: the page shell with the section tabs; each tab renders its own panel.
 *
 * @return void
 */
function dt_va_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$panels    = array(
		'ai-keys'      => 'dt_va_ai_keys_settings',
		'company'      => 'dt_va_company_settings',
		'theme'        => 'dt_va_theme_settings',
		'ai-assistant' => 'dt_va_ai_assistant_settings',
		'knowledge'    => 'dt_va_knowledge_settings',
		'flow'         => 'dt_va_flow_settings',
		'faq'          => 'dt_va_faq_settings',
		'case-studies' => 'dt_va_case_studies_settings',
	);
	$requested = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch, checked against the panels below.
	$current   = isset( $panels[ $requested ] ) ? $requested : 'ai-keys';
	$tabs      = array();

	foreach ( array(
		'ai-keys'      => __( 'AI Keys', 'dovetail-studio' ),
		'company'      => __( 'Company', 'dovetail-studio' ),
		'ai-assistant' => __( 'AI Assistant', 'dovetail-studio' ),
		'knowledge'    => __( 'Knowledge base', 'dovetail-studio' ),
		'flow'         => __( 'Doors', 'dovetail-studio' ),
		'faq'          => __( 'Offline FAQ', 'dovetail-studio' ),
		'case-studies' => __( 'Portfolio', 'dovetail-studio' ),
		'theme'        => __( 'Theme', 'dovetail-studio' ),
	) as $slug => $title ) {
		$tabs[ $slug ] = array(
			'title' => $title,
			'url'   => admin_url( 'edit.php?post_type=dt_enquiry&page=dt-va-settings&tab=' . $slug ),
		);
	}

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'settings-page',
		array(
			'version' => DOVETAIL_VIRTUAL_ASSISTANT_VERSION,
			'tabs'    => $tabs,
			'current' => $current,
			'panel'   => $panels[ $current ],
		)
	);
}

/**
 * Save the AI Keys form and, when asked, test the providers.
 *
 * @return string The notice to show, or an empty string when nothing was posted.
 */
function dt_va_ai_keys_save() {
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

	return $notice;
}

/**
 * The provider rows of the AI Keys form: key state, a safe key preview and the model references.
 *
 * @param array<string,mixed> $c Provider configuration.
 * @return array<int,array<string,mixed>>
 */
function dt_va_ai_keys_providers( $c ) {
	$rows = array();

	foreach ( array( 'openai', 'anthropic' ) as $p ) {
		$name = 'DT_VA_' . strtoupper( $p ) . '_KEY';
		$key  = dt_va_provider_key( $p );

		$rows[] = array(
			'id'             => $p,
			'label'          => 'openai' === $p ? 'OpenAI' : __( 'Anthropic', 'dovetail-studio' ),
			'external'       => ( defined( $name ) && '' !== constant( $name ) ) || getenv( $name ),
			'configured'     => '' !== $key,
			// Render only a short prefix as a placeholder, never the saved secret as an input value.
			// Short malformed values are fully masked so a preview cannot expose the entire value.
			'preview'        => '' === $key
				? ( 'openai' === $p ? 'sk-…' : 'sk-ant-…' )
				: ( strlen( $key ) > 16 ? substr( $key, 0, 12 ) . '…' : '••••••' ),
			'model'          => $c[ $p . '_model' ],
			'external_model' => dt_va_external_model( $p ),
			// Informational examples, not an allowlist: accounts and model availability change.
			'models'         => 'openai' === $p
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
				),
			'catalog'        => 'openai' === $p
				? 'https://developers.openai.com/api/docs/models'
				: 'https://platform.claude.com/docs/en/models/overview',
		);
	}

	return $rows;
}

/**
 * Settings → AI Keys: providers, keys, models, limits and the last checks.
 *
 * @return void
 */
function dt_va_ai_keys_settings() {
	$notice = dt_va_ai_keys_save();
	$c      = dt_va_provider_config();
	// The scheduled check has its own timestamp; conversations and manual tests must not replace it.
	$daily = get_option( 'dt_va_selfcheck', array() );
	$daily = is_array( $daily ) ? $daily : array();

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'tab-ai-keys',
		array(
			'notice'      => $notice,
			'config'      => $c,
			'providers'   => dt_va_ai_keys_providers( $c ),
			'daily'       => $daily,
			'diagnostics' => wp_json_encode( get_option( 'dt_va_last_provider_result', array() ), JSON_PRETTY_PRINT ),
		)
	);
}
