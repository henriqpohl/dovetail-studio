<?php

/** Optional display name for the assistant, prefixed into the greeting when set. Also holds the small, enumerated (never free-text) business-rule toggles used by dt_va_system_prompt() — keeping these to fixed choices avoids opening a prompt-injection surface through the admin form. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_ai_assistant_name() {
	if ( defined( 'DT_VA_AI_ASSISTANT_NAME' ) ) {
		return trim( (string) constant( 'DT_VA_AI_ASSISTANT_NAME' ) );
	}

	return trim( (string) get_option( 'dt_va_ai_assistant_name', '' ) );
}

function dt_va_ai_assistant_ballpark() {
	return (bool) get_option( 'dt_va_ai_assistant_ballpark', true );
}

function dt_va_ai_assistant_locale() {
	return 'en_gb' === get_option( 'dt_va_ai_assistant_locale', 'en_us' ) ? 'en_gb' : 'en_us';
}

/** Text for system-prompt rule 2. Enumerated, not free text, by design. */
function dt_va_ai_assistant_spelling_rule() {
	return 'en_gb' === dt_va_ai_assistant_locale()
		? 'British English spelling (colour, specialise, organisation). Dates day/month/year.'
		: 'American English spelling (color, specialize, organization). Dates month/day/year.';
}

/** Text for system-prompt rule 4. Enumerated, not free text, by design. */
function dt_va_ai_assistant_ballpark_rule( $company_name ) {
	return dt_va_ai_assistant_ballpark()
		? 'A ballpark only if the knowledge base has one and the client asks about cost, and always end it with: A fixed quote follows once the ' . $company_name . ' team has seen your brief.'
		: 'Do not give a ballpark figure even if the knowledge base has one; say a fixed quote follows once the '
			. $company_name . ' team has seen the brief.';
}

function dt_va_ai_assistant_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$external = defined( 'DT_VA_AI_ASSISTANT_NAME' );
	$notice   = '';
	$error    = '';

	if ( isset( $_POST['dt_va_ai_assistant'] ) && check_admin_referer( 'dt_va_ai_assistant' ) ) {
		$raw = isset( $_POST['ai_assistant_name'] ) && is_string( $_POST['ai_assistant_name'] )
			? trim( wp_unslash( $_POST['ai_assistant_name'] ) )
			: '';

		if ( ! $external && ( preg_match( '/[\r\n<>]/', $raw ) || strlen( $raw ) > 60 ) ) {
			$error = __( 'Enter a short name without line breaks or angle brackets. Nothing was saved.', 'dovetail-studio' );
		} else {
			if ( ! $external ) {
				update_option( 'dt_va_ai_assistant_name', sanitize_text_field( $raw ), false );
			}

			update_option( 'dt_va_ai_assistant_ballpark', ! empty( $_POST['ai_assistant_ballpark'] ), false );
			$locale = isset( $_POST['ai_assistant_locale'] ) && 'en_gb' === $_POST['ai_assistant_locale']
				? 'en_gb'
				: 'en_us';
			update_option( 'dt_va_ai_assistant_locale', $locale, false );
			$notice = __( 'AI Assistant settings saved.', 'dovetail-studio' );
		}
	}

	if ( $notice || $error ) {
		\DovetailStudio\Core\AdminScreens::notice(
			$error ? 'error' : 'success',
			esc_html( $error ? $error : $notice )
		);
	}

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'tab-ai-assistant',
		array(
			'name'     => dt_va_ai_assistant_name(),
			'external' => $external,
			'ballpark' => dt_va_ai_assistant_ballpark(),
			'locale'   => dt_va_ai_assistant_locale(),
		)
	);
}
