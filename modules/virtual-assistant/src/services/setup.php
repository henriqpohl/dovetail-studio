<?php

/** Company-neutral setup review. A review applies only to the exact current configuration. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_setup_sections() {
	return array(
		'knowledge' => array( __( 'Knowledge base', 'dovetail-studio' ), 'knowledge' ),
		'doors'     => array( __( 'Doors / Step questions', 'dovetail-studio' ), 'flow' ),
		'faq'       => array( __( 'Offline FAQ', 'dovetail-studio' ), 'faq' ),
		'portfolio' => array( __( 'Portfolio', 'dovetail-studio' ), 'case-studies' ),
	);
}

function dt_va_setup_normalize( $value ) {
	if ( is_string( $value ) ) {
		return preg_replace( '/\s+/u', ' ', trim( $value ) );
	}

	if ( is_array( $value ) ) {
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}

		return array_map( 'dt_va_setup_normalize', $value );
	}

	return $value;
}

function dt_va_setup_same( $a, $b ) {
	return dt_va_setup_normalize( $a ) === dt_va_setup_normalize( $b );
}

function dt_va_setup_state( $section ) {
	$eligible = true;
	$reason   = '';
	$button   = __( 'Confirm reviewed for this company', 'dovetail-studio' );
	$data     = null;

	if ( 'knowledge' === $section ) {
		$text    = dt_va_knowledge_override();
		$default = dt_va_knowledge_base_default();
		$data    = array( $text, $default );

		if ( '' === $text || dt_va_setup_same( $text, $default ) ) {
			$eligible = false;
			$reason   = __( 'Replace the supplied knowledge base with facts about this company, save, then review.', 'dovetail-studio' );
		}
	} elseif ( 'doors' === $section ) {
		$saved        = dt_va_flow_doors_config();
		$doors        = $saved ? $saved : dt_va_flow_doors_default();
		$steps        = dt_va_flow_steps_config();
		$parsed       = dt_va_json_validate( 'flow_doors', wp_json_encode( $doors ) );
		$parsed_steps = $steps ? dt_va_json_validate( 'flow_steps', wp_json_encode( $steps ) ) : array();
		$data         = array( $doors, $steps, dt_va_flow_steps_starter(), dt_va_flow_builtin_step_ids() );

		if ( is_wp_error( $parsed ) || is_wp_error( $parsed_steps ) ) {
			$eligible = false;
			$reason   = __( 'Fix the invalid Doors or Step questions configuration before reviewing.', 'dovetail-studio' );
		} else {
			$known = array_merge( dt_va_flow_builtin_step_ids(), array_keys( $steps ) );

			foreach ( $parsed as $door ) {
				if ( 'service' === $door['action'] && ! in_array( $door['service'] . '1', $known, true ) ) {
					$eligible = false; /* translators: 1: service id, 2: step id. */
					$reason   = sprintf( __( 'Missing step for service "%1$s": add "%2$s" in Step questions.', 'dovetail-studio' ), $door['service'], $door['service'] . '1' );

					break;
				}
			}
		}

		if ( dt_va_setup_same( $parsed, dt_va_flow_doors_validate( wp_json_encode( dt_va_flow_doors_default() ) ) )
			&& ! $steps
		) {
			$button = __( 'Confirm use of the default menu and flow', 'dovetail-studio' );
		}
	} elseif ( 'faq' === $section ) {
		$saved  = dt_va_faq_config();
		$sample = dt_va_faq_validate( wp_json_encode( dt_va_faq_starter() ) );
		$parsed = $saved ? dt_va_json_validate( 'faq_entries', wp_json_encode( $saved ) ) : array();
		$data   = array( $saved, $sample );

		if ( is_wp_error( $parsed ) ) {
			$eligible = false;
			$reason   = __( 'Fix the invalid FAQ before reviewing.', 'dovetail-studio' );
		} elseif ( ! $saved ) {
			$button = __( 'Confirm use of the built-in generic FAQ', 'dovetail-studio' );
		} elseif ( dt_va_setup_same( $parsed, $sample ) ) {
			$button = __( 'Confirm use of the generic sample FAQ', 'dovetail-studio' );
		}
	} elseif ( 'portfolio' === $section ) {
		$raw    = get_option( 'dt_va_case_studies', array() );
		$saved  = dt_va_case_studies_config();
		$sample = dt_va_case_studies_validate( wp_json_encode( dt_va_case_studies_starter() ) );
		$data   = array( $raw, $sample );

		if ( $raw && ! $saved ) {
			$eligible = false;
			$reason   = __( 'Fix the invalid portfolio before reviewing.', 'dovetail-studio' );
		} elseif ( ! $saved ) {
			$button = __( 'Confirm no portfolio will be displayed', 'dovetail-studio' );
		} else {
			foreach ( $saved as $entry ) {
				if ( dt_va_setup_same( $entry, $sample[0] ) ) {
					$eligible = false;
					$reason   = __( 'Replace or remove the sample project before reviewing the portfolio.', 'dovetail-studio' );

					break;
				}
			}
		}
	} else {
		return array(
			'eligible' => false,
			'reviewed' => false,
			'reason'   => __( 'Unknown section.', 'dovetail-studio' ),
			'hash'     => '',
			'button'   => '',
		);
	}

	$hash = hash( 'sha256', wp_json_encode( dt_va_setup_normalize( $data ) ) );

	return array(
		'eligible' => $eligible,
		'reviewed' => $eligible && get_option( 'dt_va_setup_review_' . $section, '' ) === $hash,
		'reason'   => $reason,
		'hash'     => $hash,
		'button'   => $button,
	);
}

/** Run before admin_notices; renderers reuse the result and never process the same POST twice. */
function dt_va_setup_process_request() {
	if ( ! current_user_can( 'manage_options' ) || 'dt-va-settings' !== ( $_GET['page'] ?? '' ) ) {
		return;
	}

	$tab      = $_GET['tab'] ?? '';
	$handlers = array(
		'knowledge'    => array( 'dt_va_knowledge_settings' ),
		'flow'         => array( 'dt_va_flow_settings', 'dt_va_flow_steps_settings' ),
		'faq'          => array( 'dt_va_faq_settings' ),
		'case-studies' => array( 'dt_va_case_studies_settings' ),
	);

	if ( ! is_string( $tab ) || ! isset( $handlers[ $tab ] ) ) {
		return;
	}

	foreach ( $handlers[ $tab ] as $handler ) {
		$GLOBALS['dt_va_setup_processed'][ $handler ] = call_user_func( $handler . '_process' );
	}

	if ( ! isset( $_POST['dt_va_setup_review'] ) ) {
		return;
	}

	check_admin_referer( 'dt_va_setup_review' );
	$section  = is_string( $_POST['dt_va_setup_review'] ) ? $_POST['dt_va_setup_review'] : '';
	$sections = dt_va_setup_sections();

	if ( ! isset( $sections[ $section ] ) || $sections[ $section ][1] !== $tab ) {
		return;
	}

	$state = dt_va_setup_state( $section );
	$hash  = isset( $_POST['setup_hash'] ) && is_string( $_POST['setup_hash'] )
		? wp_unslash( $_POST['setup_hash'] )
		: '';

	if ( ! $state['eligible'] || ! hash_equals( $state['hash'], $hash ) ) {
		$GLOBALS['dt_va_setup_review_error'] = __( 'The configuration changed or is not ready for review. Reload and review the current saved content.', 'dovetail-studio' );

		return;
	}

	update_option( 'dt_va_setup_review_' . $section, $state['hash'], false );
}

function dt_va_setup_review_controls( $section ) {
	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'partials/setup-review',
		array(
			'section' => $section,
			'state'   => dt_va_setup_state( $section ),
			'error'   => $GLOBALS['dt_va_setup_review_error'] ?? '',
		)
	);
}

function dt_va_setup_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || false === strpos( (string) $screen->id, 'dt_enquiry' ) ) {
		return;
	}

	$links = array();

	foreach ( dt_va_setup_sections() as $id => $section ) {
		if ( ! dt_va_setup_state( $id )['reviewed'] ) {
			$links[] = array(
				'url'   => admin_url( 'admin.php?page=dt-va-settings&tab=' . $section[1] ),
				'title' => $section[0],
			);
		}
	}

	if ( $links ) {
		\DovetailStudio\Core\View::render( DOVETAIL_VIRTUAL_ASSISTANT_PATH, 'partials/setup-notice', array( 'links' => $links ) );
	}
}
