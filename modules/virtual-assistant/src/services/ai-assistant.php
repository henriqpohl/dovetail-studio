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

	$name = dt_va_ai_assistant_name();
	echo '<h2>'
		. esc_html__( 'AI Assistant', 'dovetail-studio' ) . '</h2><p>'
		. esc_html__( 'Give the assistant a name if you\'d like. When set, the greeting reads “Hello. I\'m [Name] the AI Assistant at …” instead of the default “Hello. I\'m the AI Assistant at …”.', 'dovetail-studio' )
		. '</p><form method="post">';
	wp_nonce_field( 'dt_va_ai_assistant' );
	echo '<input type="hidden" name="dt_va_ai_assistant" value="1"><table class="form-table">';
	echo '<tr><th><label for="dt-va-ai-assistant-name">'
		. esc_html__( 'AI Assistant name', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-ai-assistant-name" name="ai_assistant_name" type="text" maxlength="60" value="'
		. esc_attr( $name ) . '"' . ( $external ? ' readonly' : '' ) . '>';
	echo '<p class="description">'
		. ( $external ? esc_html__( 'Defined by DT_VA_AI_ASSISTANT_NAME in wp-config.php. Edit it there, or remove the definition to use this field.', 'dovetail-studio' ) : esc_html__( 'Leave blank to use the default greeting.', 'dovetail-studio' ) )
		. '</p></td></tr>';
	echo '<tr><th><label for="dt-va-ai-assistant-ballpark">'
		. esc_html__( 'Offer a ballpark price', 'dovetail-studio' )
		. '</label></th><td><label><input id="dt-va-ai-assistant-ballpark" type="checkbox" name="ai_assistant_ballpark" value="1" '
		. checked( dt_va_ai_assistant_ballpark(), true, false ) . '> '
		. esc_html__( 'Allow a rough price range when the knowledge base has one and the client asks about cost', 'dovetail-studio' )
		. '</label>';
	echo '<p class="description">'
		. esc_html__( 'Always ends with "a fixed quote follows once the team has seen your brief", whichever way this is set. The assistant never gives a firm price either way.', 'dovetail-studio' )
		. '</p></td></tr>';
	echo '<tr><th><label for="dt-va-ai-assistant-locale">'
		. esc_html__( 'Spelling and dates', 'dovetail-studio' )
		. '</label></th><td><select id="dt-va-ai-assistant-locale" name="ai_assistant_locale">';
	echo '<option value="en_us" '
		. selected( dt_va_ai_assistant_locale(), 'en_us', false ) . '>'
		. esc_html__( 'American English (color, specialize; month/day/year)', 'dovetail-studio' )
		. '</option>';
	echo '<option value="en_gb" '
		. selected( dt_va_ai_assistant_locale(), 'en_gb', false ) . '>'
		. esc_html__( 'British and Irish English (colour, specialise; day/month/year)', 'dovetail-studio' )
		. '</option>';
	echo '</select><p class="description">'
		. esc_html__( 'Applies to the AI answering layer only, not the built-in offline FAQ.', 'dovetail-studio' )
		. '</p></td></tr>';
	echo '</table><p><button class="button button-primary">'
		. esc_html__( 'Save AI Assistant settings', 'dovetail-studio' ) . '</button></p></form>';
}
