<?php

/** Shared, read-only JSON import validation and editor download controls. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_json_formats() {
	return array(
		'flow_doors'           => array(
			'validate' => 'dt_va_flow_doors_validate',
			'sample'   => 'dt_va_flow_doors_default',
			'nonce'    => 'dt_va_flow',
			'object'   => false,
		),
		'flow_steps'           => array(
			'validate' => 'dt_va_flow_steps_validate',
			'sample'   => 'dt_va_flow_steps_starter',
			'nonce'    => 'dt_va_flow_steps',
			'object'   => true,
		),
		'faq_entries'          => array(
			'validate' => 'dt_va_faq_validate',
			'sample'   => 'dt_va_faq_starter',
			'nonce'    => 'dt_va_faq',
			'object'   => false,
		),
		'case_studies_entries' => array(
			'validate' => 'dt_va_case_studies_validate',
			'sample'   => 'dt_va_case_studies_starter',
			'nonce'    => 'dt_va_case_studies',
			'object'   => false,
		),
	);
}

function dt_va_json_validate( $format, $raw ) {
	$formats = dt_va_json_formats();

	if ( ! is_string( $format ) || ! isset( $formats[ $format ] ) ) {
		return new WP_Error( 'json', __( 'Unknown JSON format.', 'dovetail-studio' ) );
	}

	if ( ! is_string( $raw ) || strlen( $raw ) > 1048576 ) {
		return new WP_Error( 'json', __( 'Choose a JSON file up to 1 MB.', 'dovetail-studio' ) );
	}

	$raw     = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$decoded = json_decode( $raw );
	$schema  = $formats[ $format ];

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error(
			'json',
			sprintf(
				/* translators: %s: JSON parser error. */
				__( 'Invalid JSON: %s', 'dovetail-studio' ),
				json_last_error_msg()
			)
		);
	}

	if ( $schema['object'] ? ! is_object( $decoded ) : ! is_array( $decoded ) ) {
		return new WP_Error(
			'json',
			$schema['object']
				? __( 'Expected a JSON object keyed by step id.', 'dovetail-studio' )
				: __( 'Expected a JSON array of objects.', 'dovetail-studio' )
		);
	}

	foreach ( $decoded as $entry ) {
		if ( ! is_object( $entry ) ) {
			return new WP_Error( 'json', __( 'Every entry must be a JSON object.', 'dovetail-studio' ) );
		}
	}

	return call_user_func( $schema['validate'], $raw );
}

function dt_va_json_validate_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Administrator access required.', 'dovetail-studio' ) ),
			403
		);
	}

	$format  = isset( $_POST['format'] ) && is_string( $_POST['format'] )
		? wp_unslash( $_POST['format'] )
		: '';
	$formats = dt_va_json_formats();

	if ( ! isset( $formats[ $format ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Unknown JSON format.', 'dovetail-studio' ) ), 400 );
	}

	check_ajax_referer( $formats[ $format ]['nonce'], 'nonce' );
	$raw    = isset( $_POST['json'] ) && is_string( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';
	$parsed = dt_va_json_validate( $format, $raw );

	if ( is_wp_error( $parsed ) ) {
		wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 400 );
	}

	wp_send_json_success(
		array( 'json' => wp_json_encode( $parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) )
	);
}

function dt_va_json_tools( $format ) {
	$formats = dt_va_json_formats();
	$sample  = call_user_func( $formats[ $format ]['sample'] );
	echo '<span class="dt-va-json-tools" data-format="'
		. esc_html( $format ) . '" data-sample="'
		. esc_textarea( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '"> ';
	echo '<button type="button" class="button" data-json-action="import">'
		. esc_html__( 'Import', 'dovetail-studio' )
		. '</button> <button type="button" class="button" data-json-action="export">'
		. esc_html__( 'Export', 'dovetail-studio' )
		. '</button> <button type="button" class="button" data-json-action="sample">'
		. esc_html__( 'Download a Sample', 'dovetail-studio' ) . '</button>';
	echo '<input type="file" accept=".json,application/json" hidden><span role="status" aria-live="polite" class="dt-va-json-status"></span></span>';
}
