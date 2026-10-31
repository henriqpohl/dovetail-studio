<?php

/** Optional admin override for the built-in case studies (work()'s portfolio cards). Replaces the built-in list wholesale when set, same convention as Doors and Offline FAQ. Plain keywords only, never regex. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/json-tools.php';

function dt_va_case_studies_config() {
	$saved = get_option( 'dt_va_case_studies', array() );

	if ( ! is_array( $saved ) || ! $saved ) {
		return array();
	}

	$parsed = dt_va_case_studies_validate( wp_json_encode( $saved ) );

	return is_wp_error( $parsed ) ? array() : $parsed;
}

/** Returns a validated list or the first validation error. */
function dt_va_case_studies_validate( $json ) {
	$decoded = json_decode( (string) $json );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
		return new WP_Error(
			'case_studies',
			__( 'Not valid JSON, or not a JSON array.', 'dovetail-studio' )
		);
	}

	if ( count( $decoded ) < 1 || count( $decoded ) > 30 ) {
		return new WP_Error( 'case_studies', __( 'Provide between 1 and 30 entries.', 'dovetail-studio' ) );
	}

	$out = array();

	foreach ( $decoded as $i => $entry ) {
		/* translators: %d: entry number. */
		$prefix = sprintf( __( 'Entry %d: ', 'dovetail-studio' ), $i + 1 );

		if ( ! is_object( $entry ) ) {
			return new WP_Error( 'case_studies', $prefix . __( 'must be an object.', 'dovetail-studio' ) );
		}

		$row = array();

		foreach ( array(
			't'   => 60,
			's'   => 140,
			'o'   => 80,
			'url' => 2048,
			'img' => 2048,
			'sec' => 60,
			'ind' => 200,
		) as $key => $max ) {
			if ( property_exists( $entry, $key ) && ! is_string( $entry->$key ) ) {
				return new WP_Error(
					'case_studies',
					$prefix
						. sprintf(
							/* translators: %s: field key. */
							__( '%s must be a string.', 'dovetail-studio' ),
							$key
						)
				);
			}

			$value = isset( $entry->$key ) ? trim( $entry->$key ) : '';

			if ( ( 't' === $key || 's' === $key ) && '' === $value ) {
				return new WP_Error(
					'case_studies',
					$prefix . __( 'needs a title (t) and description (s).', 'dovetail-studio' )
				);
			}

			if ( mb_strlen( $value ) > $max ) {
				return new WP_Error(
					'case_studies',
					$prefix
						. sprintf(
							/* translators: 1: field key, 2: maximum length. */
							__( '%1$s is too long (max %2$d characters).', 'dovetail-studio' ),
							$key,
							$max
						)
				);
			}

			if ( ( 'url' === $key || 'img' === $key ) && '' !== $value ) {
				$absolute = filter_var( $value, FILTER_VALIDATE_URL ) && in_array(
					strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) ),
					array( 'http', 'https' ),
					true
				);
				$relative = 'url' === $key && preg_match( '~^/(?!/)[^\\\\\s]*$~', $value );

				if ( ! $absolute && ! $relative ) {
					return new WP_Error(
						'case_studies',
						$prefix
							. sprintf(
								'url' === $key
									? /* translators: %s: field key. */ __( '%s needs an http(s) URL or a site-relative path.', 'dovetail-studio' )
									: /* translators: %s: field key. */ __( '%s needs an http(s) URL.', 'dovetail-studio' ),
								$key
							)
					);
				}
			}

			if ( 'ind' === $key ) {
				$value = mb_strtolower( $value );

				if ( '' !== $value && ! preg_match( "/^[a-z0-9' ]+$/u", $value ) ) {
					return new WP_Error(
						'case_studies',
						$prefix
							. __( 'keywords must contain only letters, numbers, spaces and apostrophes (no regex).', 'dovetail-studio' )
					);
				}
			}

			$row[ $key ] = $value;
		}

		$out[] = $row;
	}

	return $out;
}

/** Placeholder examples only; never automatically added to the saved portfolio. */
function dt_va_case_studies_starter() {
	return array(
		array(
			't'   => __( 'Example project', 'dovetail-studio' ),
			's'   => __( 'Describe the client brief and the work delivered.', 'dovetail-studio' ),
			'o'   => __( 'Describe the outcome.', 'dovetail-studio' ),
			'url' => '/work/example/',
			'img' => '',
			'sec' => '',
			'ind' => '',
		),
	);
}

function dt_va_case_studies_settings_process() {
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

	if ( isset( $_POST['dt_va_case_studies_add'] ) && check_admin_referer( 'dt_va_case_studies_add' ) ) {
		$candidate = dt_va_case_studies_config();
		$entry     = array();

		foreach ( array( 't', 's', 'o', 'url', 'img', 'sec', 'ind' ) as $key ) {
			$entry[ $key ] = isset( $_POST[ 'add_' . $key ] ) && is_string( $_POST[ 'add_' . $key ] )
				? wp_unslash( $_POST[ 'add_' . $key ] )
				: '';
		}

		$candidate[] = $entry;
		$parsed      = dt_va_case_studies_validate( wp_json_encode( $candidate ) );

		if ( is_wp_error( $parsed ) ) {
			$error = $parsed->get_error_message();
		} else {
			update_option( 'dt_va_case_studies', $parsed, false );
			$notice = __( 'Portfolio entry added.', 'dovetail-studio' );
		}
	} elseif ( isset( $_POST['dt_va_case_studies'] ) && check_admin_referer( 'dt_va_case_studies' ) ) {
		if ( isset( $_POST['restore_default'] ) ) {
			delete_option( 'dt_va_case_studies' );
			$notice = __( 'Default portfolio restored.', 'dovetail-studio' );
		} else {
			$raw    = isset( $_POST['case_studies_entries'] ) && is_string( $_POST['case_studies_entries'] )
				? wp_unslash( $_POST['case_studies_entries'] )
				: '';
			$parsed = dt_va_case_studies_validate( $raw );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_case_studies', $parsed, false );
				$notice = __( 'Portfolio saved.', 'dovetail-studio' );
			}
		}
	}

	if ( '' !== $notice ) {
		delete_option( 'dt_va_setup_review_portfolio' );
	}

	return array(
		'notice' => $notice,
		'error'  => $error,
		'raw'    => $raw,
	);
}

function dt_va_case_studies_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = $GLOBALS['dt_va_setup_processed']['dt_va_case_studies_settings'] ?? dt_va_case_studies_settings_process();
	$notice = $result['notice'];
	$error  = $result['error'];
	$raw    = $result['raw'];

	if ( $notice || $error ) {
		\DovetailStudio\Core\AdminScreens::notice(
			$error ? 'error' : 'success',
			esc_html( $error ? $error : $notice )
		);
	}

	$saved = dt_va_case_studies_config();

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'tab-portfolio',
		array(
			'saved'      => $saved,
			'json'       => $error && null !== $raw
				? $raw
				: ( $saved ? (string) wp_json_encode( $saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '' ),
			'starter'    => (string) wp_json_encode( dt_va_case_studies_starter(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			'json_tools' => dt_va_json_tools_args( 'case_studies_entries' ),
		)
	);
	dt_va_setup_review_controls( 'portfolio' );
}
