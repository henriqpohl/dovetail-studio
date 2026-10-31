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

	echo '<h3>'
		. esc_html__( 'Add an entry', 'dovetail-studio' ) . '</h3><p class="description">'
		. esc_html__( 'Add a real project. Keywords are space-separated plain words (no regex). Example projects are never added automatically.', 'dovetail-studio' )
		. '</p><form method="post">';
	wp_nonce_field( 'dt_va_case_studies_add' );
	echo '<input type="hidden" name="dt_va_case_studies_add" value="1"><table class="form-table">';

	foreach ( array(
		't'   => __( 'Title', 'dovetail-studio' ),
		's'   => __( 'Description', 'dovetail-studio' ),
		'o'   => __( 'Outcome', 'dovetail-studio' ),
		'url' => __( 'Project URL', 'dovetail-studio' ),
		'img' => __( 'Image URL (http/https)', 'dovetail-studio' ),
		'sec' => __( 'Sector', 'dovetail-studio' ),
		'ind' => __( 'Industry keywords', 'dovetail-studio' ),
	) as $key => $label ) {
		echo '<tr><th><label for="dt-va-case-'
			. esc_attr( $key ) . '">' . esc_html( $label )
			. '</label></th><td><input class="regular-text" id="dt-va-case-' . esc_attr( $key )
			. '" name="add_' . esc_attr( $key ) . '"'
			. ( in_array( $key, array( 't', 's' ), true ) ? ' required' : '' ) . '></td></tr>';
	}

	echo '</table><p><button class="button">'
		. esc_html__( 'Add entry', 'dovetail-studio' ) . '</button></p></form><hr>';
	$saved = dt_va_case_studies_config();
	echo '<h2>'
		. esc_html__( 'Portfolio', 'dovetail-studio' ) . '</h2><p>'
		. wp_kses_post(
			__( 'Provide 1–30 projects with a title (<code>t</code>, max 60 characters) and description (<code>s</code>, max 140). Optional fields: outcome (<code>o</code>, max 80), project URL (<code>url</code>, http(s) or /path), image (<code>img</code>, http(s) only), sector (<code>sec</code>, max 60) and plain industry keywords (<code>ind</code>, max 200). Matching projects appear first, followed by other saved projects, up to three cards.', 'dovetail-studio' )
		) . '</p><p>'
		. esc_html__( 'Saving replaces the built-in portfolio entirely. Restore default removes the override; the default portfolio is empty.', 'dovetail-studio' )
		. '</p><form method="post">';
	wp_nonce_field( 'dt_va_case_studies' );
	echo '<input type="hidden" name="dt_va_case_studies" value="1">';
	$value = $error && null !== $raw
		? $raw
		: ( $saved ? wp_json_encode( $saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '' );
	echo '<textarea aria-label="'
		. esc_attr__( 'Portfolio JSON', 'dovetail-studio' )
		. '" name="case_studies_entries" rows="24" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px" placeholder="'
		. esc_textarea(
			wp_json_encode( dt_va_case_studies_starter(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		) . '">' . esc_textarea( $value ) . '</textarea>';
	echo '<p class="description">'
		. ( $saved ? esc_html( sprintf( /* translators: %d: number of entries. */ _n( 'Using the saved portfolio (%d entry).', 'Using the saved portfolio (%d entries).', count( $saved ), 'dovetail-studio' ), count( $saved ) ) ) : esc_html__( 'The placeholder is only a starting example, not your real portfolio. No projects are published by default.', 'dovetail-studio' ) )
		. '</p>';
	echo '<p><button class="button button-primary">'
		. esc_html__( 'Save portfolio', 'dovetail-studio' )
		. '</button> <button class="button" name="restore_default" value="1" formnovalidate>'
		. esc_html__( 'Restore default', 'dovetail-studio' ) . '</button>';
	dt_va_json_tools( 'case_studies_entries' );
	echo '</p><p class="description">'
		. esc_html__( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' )
		. '</p></form>';
	dt_va_setup_review_controls( 'portfolio' );
}
