<?php

/** Optional admin override for the offline FAQ (offlineAnswer() fallback). Plain keywords only, never regex, to avoid a ReDoS surface from admin-entered content. Replaces the built-in FAQ wholesale when set, same convention as Knowledge base and Doors. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/json-tools.php';

function dt_va_faq_config() {
	$saved = get_option( 'dt_va_faq_entries', array() );

	return is_array( $saved ) && $saved ? $saved : array();
}

/** Returns a validated array on success, or a WP_Error describing the first problem found. Keywords are plain words/phrases (letters, numbers, spaces, apostrophes); never compiled as regex. */
function dt_va_faq_validate( $json ) {
	$decoded = json_decode( (string) $json, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
		return new WP_Error( 'faq', __( 'Not valid JSON, or not a JSON array.', 'dovetail-studio' ) );
	}

	if ( count( $decoded ) < 1 || count( $decoded ) > 60 ) {
		return new WP_Error( 'faq', __( 'Provide between 1 and 60 entries.', 'dovetail-studio' ) );
	}

	$out = array();

	foreach ( $decoded as $i => $entry ) {
		if ( ! is_array( $entry ) ) {
			return new WP_Error(
				'faq',
				sprintf(
					/* translators: %d: entry number. */
					__( 'Entry %d is not an object.', 'dovetail-studio' ),
					$i + 1
				)
			);
		}

		$id     = isset( $entry['id'] ) && is_string( $entry['id'] ) ? trim( $entry['id'] ) : '';
		$answer = isset( $entry['answer'] ) && is_string( $entry['answer'] ) ? trim( $entry['answer'] ) : '';

		if ( '' === $id || '' === $answer ) {
			return new WP_Error(
				'faq',
				sprintf(
					/* translators: %d: entry number. */
					__( 'Entry %d needs an id and an answer.', 'dovetail-studio' ),
					$i + 1
				)
			);
		}

		if ( mb_strlen( $answer ) > 1200 ) {
			return new WP_Error(
				'faq',
				sprintf(
					/* translators: 1: entry number, 2: entry id. */
					__( 'Entry %1$d (%2$s): answer is too long (max 1200 characters).', 'dovetail-studio' ),
					$i + 1,
					$id
				)
			);
		}

		$keywords     = array();
		$raw_keywords = isset( $entry['keywords'] ) && is_array( $entry['keywords'] )
			? $entry['keywords']
			: array();

		foreach ( $raw_keywords as $k ) {
			if ( ! is_string( $k ) ) {
				continue;
			}

			$k = mb_strtolower( trim( $k ) );

			if ( '' === $k || mb_strlen( $k ) > 40 || ! preg_match( '/^[a-z0-9\' ]+$/u', $k ) ) {
				continue;
			}

			$keywords[] = $k;
		}

		if ( ! $keywords ) {
			return new WP_Error(
				'faq',
				sprintf(
					/* translators: 1: entry number, 2: entry id. */
					__( 'Entry %1$d (%2$s): needs at least one plain keyword (letters, numbers, spaces, apostrophes only, max 40 characters).', 'dovetail-studio' ),
					$i + 1,
					$id
				)
			);
		}

		$out[] = array(
			'id'       => mb_substr( $id, 0, 60 ),
			'keywords' => array_slice( array_values( array_unique( $keywords ) ), 0, 20 ),
			'answer'   => mb_substr( $answer, 0, 1200 ),
		);
	}

	return $out;
}

/** Shown in the admin textarea as a starting point when no override is saved; matches the generic built-in fallback in front.js (var T), not real facts about any one company. */
function dt_va_faq_starter() {
	return array(
		array(
			'id'       => 'cost',
			'keywords' => array( 'cost', 'price', 'budget', 'quote' ),
			'answer'   => 'Cost follows scope, so nothing is priced until the brief is understood. Tell me what you have in mind and the {{COMPANY_NAME}} team will come back with a quote.',
		),
		array(
			'id'       => 'contact',
			'keywords' => array( 'phone number', 'contact', 'get in touch' ),
			'answer'   => 'You can leave your details here and the {{COMPANY_NAME}} team will call you, or use the contact details on the site.',
		),
	);
}

function dt_va_faq_settings_process() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'notice' => '',
			'error'  => '',
			'raw'    => null,
		);
	}

	$notice = '';
	$error  = '';
	$raw    = null;

	if ( isset( $_POST['dt_va_faq_add'] ) && check_admin_referer( 'dt_va_faq_add' ) ) {
		$base = dt_va_faq_config();
		$v    = $base ? $base : dt_va_faq_starter();
		$id   = isset( $_POST['add_id'] ) ? trim( wp_unslash( $_POST['add_id'] ) ) : '';

		if ( '' === $id ) {
			$error = __( 'Enter an id.', 'dovetail-studio' );
		} else {
			$keywords_raw = isset( $_POST['add_keywords'] )
				? trim( wp_unslash( $_POST['add_keywords'] ) )
				: '';
			$new          = array(
				'id'       => $id,
				'keywords' => array_values( array_filter( array_map( 'trim', explode( ',', $keywords_raw ) ) ) ),
				'answer'   => isset( $_POST['add_answer'] ) ? trim( wp_unslash( $_POST['add_answer'] ) ) : '',
			);
			$candidate    = $v;
			$candidate[]  = $new;
			$parsed       = dt_va_faq_validate( wp_json_encode( $candidate ) );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_faq_entries', $parsed, false );
				$notice = __( 'FAQ entry added.', 'dovetail-studio' );
			}
		}
	} elseif ( isset( $_POST['dt_va_faq'] ) && check_admin_referer( 'dt_va_faq' ) ) {
		if ( isset( $_POST['restore_default'] ) ) {
			delete_option( 'dt_va_faq_entries' );
			$notice = __( 'Default offline FAQ restored.', 'dovetail-studio' );
		} else {
			$raw    = isset( $_POST['faq_entries'] ) && is_string( $_POST['faq_entries'] )
				? wp_unslash( $_POST['faq_entries'] )
				: '';
			$parsed = dt_va_faq_validate( $raw );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_faq_entries', $parsed, false );
				$notice = __( 'Offline FAQ saved.', 'dovetail-studio' );
			}
		}
	}

	if ( '' !== $notice ) {
		delete_option( 'dt_va_setup_review_faq' );
	}

	return array(
		'notice' => $notice,
		'error'  => $error,
		'raw'    => $raw,
	);
}

function dt_va_faq_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = $GLOBALS['dt_va_setup_processed']['dt_va_faq_settings'] ?? dt_va_faq_settings_process();
	$notice = $result['notice'];
	$error  = $result['error'];
	$raw    = $result['raw'];

	if ( $notice || $error ) {
		\DovetailStudio\Core\AdminScreens::notice(
			$error ? 'error' : 'success',
			esc_html( $error ? $error : $notice )
		);
	}

	$saved = dt_va_faq_config();

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'tab-faq',
		array(
			'saved'      => $saved,
			'json'       => (string) wp_json_encode( $saved ? $saved : dt_va_faq_starter(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'json_tools' => dt_va_json_tools_args( 'faq_entries' ),
		)
	);
	dt_va_setup_review_controls( 'faq' );
}
