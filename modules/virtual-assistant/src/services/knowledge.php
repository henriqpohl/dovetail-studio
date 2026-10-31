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

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'tab-knowledge',
		array(
			'override' => $override,
			'text'     => '' !== $override ? $override : dt_va_knowledge_base_default(),
		)
	);
	dt_va_setup_review_controls( 'knowledge' );
}
