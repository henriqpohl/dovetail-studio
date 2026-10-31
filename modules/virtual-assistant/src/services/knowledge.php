<?php

/** Optional admin override for the knowledge base; falls back to knowledge.md, then the built-in default. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/setup.php';

function dt_va_knowledge_override() {
	return trim( (string) get_option( 'dt_va_knowledge_override', '' ) );
}

function dt_va_knowledge_settings_process() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'notice' => '',
			'error'  => '',
			'raw'    => null,
		);
	}

	$error  = '';
	$raw    = null;
	$notice = '';

	if ( isset( $_POST['dt_va_knowledge'] ) && check_admin_referer( 'dt_va_knowledge' ) ) {
		if ( isset( $_POST['restore_default'] ) ) {
			delete_option( 'dt_va_knowledge_override' );
			$notice = __( 'Default knowledge base restored.', 'dovetail-studio' );
		} else {
			$raw = isset( $_POST['knowledge_text'] ) && is_string( $_POST['knowledge_text'] )
				? trim( wp_unslash( $_POST['knowledge_text'] ) )
				: '';
			update_option( 'dt_va_knowledge_override', sanitize_textarea_field( $raw ), false );
			$notice = __( 'Knowledge base saved.', 'dovetail-studio' );
		}
	}

	if ( '' !== $notice ) {
		delete_option( 'dt_va_setup_review_knowledge' );
	}

	return array(
		'notice' => $notice,
		'error'  => $error,
		'raw'    => $raw,
	);
}

function dt_va_knowledge_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = $GLOBALS['dt_va_setup_processed']['dt_va_knowledge_settings'] ?? dt_va_knowledge_settings_process();
	$notice = $result['notice'];
	$error  = $result['error'];
	$raw    = $result['raw'];

	if ( $notice ) {
		\DovetailStudio\Core\AdminScreens::notice( 'success', esc_html( $notice ) );
	}

	$override = dt_va_knowledge_override();
	echo '<h2>'
		. esc_html__( 'Knowledge base', 'dovetail-studio' ) . '</h2><p>'
		. wp_kses_post(
			__( 'The only facts the AI Assistant is allowed to answer questions from. Customize it for this installation here; until saved, the assistant uses the bundled <code>knowledge.md</code>. Restore default clears this override and goes back to <code>knowledge.md</code>.', 'dovetail-studio' )
		) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'dt_va_knowledge' );
	echo '<input type="hidden" name="dt_va_knowledge" value="1">';
	echo '<textarea name="knowledge_text" rows="30" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">'
		. esc_textarea( '' !== $override ? $override : dt_va_knowledge_base_default() ) . '</textarea>';
	echo '<p class="description">'
		. ( '' !== $override ? esc_html__( 'Using the saved override.', 'dovetail-studio' ) : esc_html__( 'Using the default from knowledge.md.', 'dovetail-studio' ) )
		. '</p>';
	echo '<p><button class="button button-primary">'
		. esc_html__( 'Save knowledge base', 'dovetail-studio' )
		. '</button> <button class="button" name="restore_default" value="1" formnovalidate>'
		. esc_html__( 'Restore default', 'dovetail-studio' ) . '</button></p></form>';
	dt_va_setup_review_controls( 'knowledge' );
}
