<?php

/** Safe visual theme settings exposed to the public assistant. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_theme_fields() {
	return array(
		'accent'       => array(
			'label'   => __( 'Accent', 'dovetail-studio' ),
			'default' => '#e2231a',
		),
		'accent_hover' => array(
			'label'   => __( 'Accent hover', 'dovetail-studio' ),
			'default' => '#b81b14',
		),
		'panel'        => array(
			'label'   => __( 'Panel background', 'dovetail-studio' ),
			'default' => '#161618',
		),
		'surface'      => array(
			'label'   => __( 'Header and form background', 'dovetail-studio' ),
			'default' => '#1f1f23',
		),
		'message'      => array(
			'label'   => __( 'Assistant message background', 'dovetail-studio' ),
			'default' => '#26262b',
		),
		'input'        => array(
			'label'   => __( 'Input background', 'dovetail-studio' ),
			'default' => '#0f0f12',
		),
		'text'         => array(
			'label'   => __( 'Primary text', 'dovetail-studio' ),
			'default' => '#f5f4f0',
		),
		'user_message' => array(
			'label'   => __( 'Visitor message background', 'dovetail-studio' ),
			'default' => '#f5f4f0',
		),
		'user_text'    => array(
			'label'   => __( 'Visitor message text', 'dovetail-studio' ),
			'default' => '#111113',
		),
		'muted'        => array(
			'label'   => __( 'Secondary text', 'dovetail-studio' ),
			'default' => '#a3a3a8',
		),
		'border'       => array(
			'label'   => __( 'Borders', 'dovetail-studio' ),
			'default' => '#33333a',
		),
	);
}

function dt_va_theme_defaults() {
	return array_map(
		function ( $meta ) {
			return $meta['default'];
		},
		dt_va_theme_fields()
	);
}

function dt_va_theme_parse( $post ) {
	if ( ! isset( $post['theme'] ) || ! is_array( $post['theme'] ) ) {
		return new WP_Error( 'theme', __( 'Invalid theme settings.', 'dovetail-studio' ) );
	}

	$theme = array();

	foreach ( dt_va_theme_fields() as $key => $meta ) {
		$value = $post['theme'][ $key ] ?? '';

		if ( ! is_string( $value ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
			return new WP_Error(
				'theme',
				sprintf(
					/* translators: %s: color field label. */
					__( 'Choose a valid color for %s.', 'dovetail-studio' ),
					$meta['label']
				)
			);
		}

		$theme[ $key ] = strtolower( $value );
	}

	return $theme;
}

function dt_va_theme_config() {
	$saved = get_option( 'dt_va_theme', array() );

	return array_merge(
		dt_va_theme_defaults(),
		is_array( $saved ) ? array_intersect_key( $saved, dt_va_theme_fields() ) : array()
	);
}

/** CSS custom property for each field, matching the names read by assets/css/front-tab.css, front.css and front.js. */
function dt_va_theme_css_vars() {
	return array(
		'accent'       => '--dt-va-accent',
		'accent_hover' => '--dt-va-accent-hover',
		'panel'        => '--dt-va-panel',
		'surface'      => '--dt-va-surface',
		'message'      => '--dt-va-message',
		'input'        => '--dt-va-input',
		'text'         => '--dt-va-text',
		'user_message' => '--dt-va-user-message',
		'user_text'    => '--dt-va-user-text',
		'muted'        => '--dt-va-muted',
		'border'       => '--dt-va-border',
	);
}

/**
 * The saved colours as a :root override, added after front-tab.css with wp_add_inline_style() so the first paint
 * already has them; without it, front.js only applies them after it loads, causing a visible flash to the CSS defaults.
 * The rules keep !important: front-tab.css declares the same :root defaults, and a page optimiser that combines or
 * moves styles could otherwise print the stylesheet after this override and silently win.
 *
 * @return string CSS rules, or an empty string when no valid colour is saved.
 */
function dt_va_theme_css() {
	$theme = dt_va_theme_config();
	$rules = '';

	foreach ( dt_va_theme_css_vars() as $key => $var ) {
		if ( isset( $theme[ $key ] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $theme[ $key ] ) ) {
			$rules .= $var . ':' . strtolower( $theme[ $key ] ) . ' !important;';
		}
	}

	return '' === $rules ? '' : ':root{' . $rules . '}';
}

function dt_va_theme_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = '';
	$error  = '';

	if ( isset( $_POST['dt_va_theme'] ) && check_admin_referer( 'dt_va_theme' ) ) {
		if ( isset( $_POST['restore_theme'] ) ) {
			delete_option( 'dt_va_theme' );
			$notice = __( 'Default theme restored.', 'dovetail-studio' );
		} else {
			$parsed = dt_va_theme_parse( $_POST );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_theme', $parsed, false );
				$notice = __( 'Theme saved.', 'dovetail-studio' );
			}
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
		'tab-theme',
		array(
			'theme'  => dt_va_theme_config(),
			'fields' => dt_va_theme_fields(),
		)
	);
}
