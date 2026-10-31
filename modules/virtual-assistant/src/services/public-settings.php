<?php

/** Public overrides are opt-in per field: missing means HTML default, empty means intentionally empty. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_public_fields() {
	return array(
		'COMPANY_TYPE'          => 'company_type',
		'COMPANY_ADDRESS'       => 'text',
		'COMPANY_CITY'          => 'text',
		'COMPANY_EMAIL'         => 'email',
		'COMPANY_PHONE_DISPLAY' => 'text',
		'COMPANY_PHONE_TEL'     => 'tel',
		'WHATSAPP'              => 'digits',
		'WHATSAPP_DISPLAY'      => 'text',
		'PRIVACY_URL'           => 'url',
		'WORK_URL'              => 'url',
		'RESPONSE_PROMISE'      => 'text',
		'OPENING_HOURS'         => 'hours',
		'BOOKING_URL'           => 'url',
		'GOOGLE_RATING'         => 'rating',
		'GOOGLE_REVIEWS'        => 'count',
		'GOOGLE_URL'            => 'url',
		'RESUME_HOURS'          => 'duration',
	);
}

/** Read the same simple literals used by the front-end script; never evaluate JavaScript in PHP. */
function dt_va_public_defaults() {
	// front.js is ~80 KB: parse it once per request, and across requests while the file is unchanged.
	static $memo = null;

	if ( null !== $memo ) {
		return $memo;
	}

	$file   = dirname( __DIR__, 2 ) . '/assets/js/front.js';
	$stamp  = (string) @filemtime( $file )
		. ':' . (string) @filesize( $file ) . ':' . DOVETAIL_VIRTUAL_ASSISTANT_VERSION;
	$cached = get_transient( 'dt_va_public_defaults' );

	if ( is_array( $cached )
		&& isset( $cached['stamp'], $cached['data'] )
		&& $cached['stamp'] === $stamp
		&& is_array( $cached['data'] )
	) {
		$memo = $cached['data'];

		return $memo;
	}

	$memo = dt_va_public_defaults_parse( (string) @file_get_contents( $file ) );
	set_transient(
		'dt_va_public_defaults',
		array(
			'stamp' => $stamp,
			'data'  => $memo,
		),
		DAY_IN_SECONDS
	);

	return $memo;
}

function dt_va_public_defaults_parse( $html ) {
	$defaults = array();

	foreach ( dt_va_public_fields() as $key => $type ) {
		if ( 'hours' === $type ) {
			if ( preg_match( '/OPENING_HOURS:\s*\{\s*open:\s*([0-9.]+),\s*close:\s*([0-9.]+),\s*days:\s*\[([0-6, ]*)\]/', $html, $m ) ) {
				$defaults[ $key ] = array(
					'open'  => (float) $m[1],
					'close' => (float) $m[2],
					'days'  => '' === $m[3] ? array() : array_map( 'intval', explode( ',', $m[3] ) ),
				);
			}
		} elseif ( preg_match( '/\\b' . $key . ':\\s*([\'\"])([^\'\"\\r\\n]*)\\1/', $html, $m ) ) {
			$defaults[ $key ] = $m[2];
		} elseif ( preg_match( '/\b' . $key . ':\s*([0-9.]+)/', $html, $m ) ) {
			$defaults[ $key ] = 'count' === $type ? (int) $m[1] : (float) $m[1];
		}
	}

	return $defaults;
}

function dt_va_public_help() {
	return array(
		'COMPANY_TYPE'          => array( __( 'Company Type', 'dovetail-studio' ), __( 'What visitors are told to call the place, for example "the Office is closed right now". Pick a suggestion or type your own; blank is not allowed and falls back to Office. Restore default uses the type in assets/front.js.', 'dovetail-studio' ) ),
		'COMPANY_ADDRESS'       => array( __( 'Company Address', 'dovetail-studio' ), __( 'Public studio address shown in Talk to a person. Leave blank to hide the Address line. Restore default uses the address in assets/front.js. This does not update the knowledge base or offline answers.', 'dovetail-studio' ) ),
		'COMPANY_CITY'          => array( __( 'Company City', 'dovetail-studio' ), __( 'City named in Talk to a person, for example "Of course. The team in Chicago takes the calls themselves." Restore default uses the city in assets/front.js.', 'dovetail-studio' ) ),
		'COMPANY_EMAIL'         => array( __( 'Public email', 'dovetail-studio' ), __( 'Email address displayed in the assistant. This does not change the address that receives briefs.', 'dovetail-studio' ) ),
		'COMPANY_PHONE_DISPLAY' => array( __( 'Phone — display', 'dovetail-studio' ), __( 'Readable phone number shown to visitors, for example (555) 010-0100.', 'dovetail-studio' ) ),
		'COMPANY_PHONE_TEL'     => array( __( 'Phone — dial link', 'dovetail-studio' ), __( 'Number used when visitors click to call. Include the country code, for example +15550100100.', 'dovetail-studio' ) ),
		'WHATSAPP'              => array( __( 'WhatsApp number', 'dovetail-studio' ), __( 'International number using digits only, without + or spaces. Leave blank to disable WhatsApp.', 'dovetail-studio' ) ),
		'WHATSAPP_DISPLAY'      => array( __( 'WhatsApp — display', 'dovetail-studio' ), __( 'Readable WhatsApp number shown to visitors. It does not change the destination number above.', 'dovetail-studio' ) ),
		'PRIVACY_URL'           => array( __( 'Privacy policy URL', 'dovetail-studio' ), __( 'Link to your privacy policy. Use an https:// URL or a local path such as /privacy-policy/.', 'dovetail-studio' ) ),
		'WORK_URL'              => array( __( 'Portfolio URL', 'dovetail-studio' ), __( 'Link to your portfolio or previous work, for example /our-work/.', 'dovetail-studio' ) ),
		'RESPONSE_PROMISE'      => array( __( 'Response time', 'dovetail-studio' ), __( 'Expected reply time shown in the assistant, for example within one working day.', 'dovetail-studio' ) ),
		'OPENING_HOURS'         => array( __( 'Opening hours', 'dovetail-studio' ), __( 'Hours used by the assistant, in the site time zone (Settings > General). 17.5 means 17:30. Select the working days; none means closed. Overnight hours are not supported.', 'dovetail-studio' ) ),
		'BOOKING_URL'           => array( __( 'Booking URL', 'dovetail-studio' ), __( 'Calendly or appointment booking link. Leave blank to disable booking.', 'dovetail-studio' ) ),
		'GOOGLE_RATING'         => array( __( 'Google rating', 'dovetail-studio' ), __( 'Enter the Google rating manually, from 0 to 5. Leave blank to hide ratings.', 'dovetail-studio' ) ),
		'GOOGLE_REVIEWS'        => array( __( 'Google review count', 'dovetail-studio' ), __( 'Enter the total number of Google ratings manually. Zero is allowed. Update this number when new ratings arrive.', 'dovetail-studio' ) ),
		'GOOGLE_URL'            => array( __( 'Google Maps profile URL', 'dovetail-studio' ), __( 'Public link to your business on Google Maps, opened when visitors click the ratings. This link does not fetch ratings automatically.', 'dovetail-studio' ) ),
		'RESUME_HOURS'          => array( __( 'Resume conversation — hours', 'dovetail-studio' ), __( 'How long an unfinished conversation can be resumed in this browser. Use 0 to disable resume. Does not delete submitted enquiries.', 'dovetail-studio' ) ),
	);
}

function dt_va_public_parse( $post ) {
	$out = array();

	foreach ( dt_va_public_fields() as $key => $type ) {
		if ( ! isset( $post['public'] )
			|| ! is_array( $post['public'] )
			|| ! array_key_exists( $key, $post['public'] )
		) {
			continue;
		}

		$v = $post['public'][ $key ] ?? '';

		if ( 'company_type' === $type ) {
			if ( ! is_string( $v ) ) {
				return new WP_Error(
					'settings',
					sprintf(
						/* translators: %s: field key. */
						__( 'Invalid value for %s', 'dovetail-studio' ),
						$key
					)
				);
			}

			$v = trim( wp_unslash( $v ) );

			if ( '' === $v || strlen( $v ) > 40 || preg_match( '/[<>\r\n]/', $v ) ) {
				return new WP_Error(
					'settings',
					__( 'Choose a company type or enter a short custom one, without line breaks or angle brackets.', 'dovetail-studio' )
				);
			}

			$out[ $key ] = sanitize_text_field( $v );

			continue;
		}

		if ( 'hours' === $type ) {
			if ( ! is_array( $v ) ) {
				return new WP_Error( 'settings', __( 'Invalid hours.', 'dovetail-studio' ) );
			}

			$open  = $v['open'] ?? '';
			$close = $v['close'] ?? '';

			if ( ! is_numeric( $open )
				|| ! is_numeric( $close )
				|| $open < 0
				|| $close > 24
				|| $open >= $close
			) {
				return new WP_Error(
					'settings',
					__( 'Hours must be between 0 and 24, with opening before closing.', 'dovetail-studio' )
				);
			}

			$days = $v['days'] ?? array();

			if ( ! is_array( $days ) ) {
				return new WP_Error( 'settings', __( 'Invalid days.', 'dovetail-studio' ) );
			}

			foreach ( $days as $d ) {
				if ( ! is_scalar( $d ) || ! preg_match( '/^[0-6]$/', (string) $d ) ) {
					return new WP_Error( 'settings', __( 'Invalid days.', 'dovetail-studio' ) );
				}
			}

			$closed = dt_va_parse_closed_dates(
				isset( $v['closed'] ) && is_string( $v['closed'] ) ? $v['closed'] : ''
			);

			if ( is_wp_error( $closed ) ) {
				return $closed;
			}

			$out[ $key ] = array(
				'open'   => (float) $open,
				'close'  => (float) $close,
				'days'   => array_values( array_unique( array_map( 'intval', $days ) ) ),
				'closed' => $closed,
			);

			continue;
		}

		if ( ! is_string( $v ) ) {
			return new WP_Error(
				'settings',
				sprintf(
					/* translators: %s: field key. */
					__( 'Invalid value for %s', 'dovetail-studio' ),
					$key
				)
			);
		}

		$v = trim( wp_unslash( $v ) );

		if ( strlen( $v ) > 2000 ) {
			return new WP_Error(
				'settings',
				sprintf(
					/* translators: %s: field key. */
					__( 'Value too long: %s', 'dovetail-studio' ),
					$key
				)
			);
		}

		if ( 'email' === $type && '' !== $v && ! is_email( $v ) ) {
			return new WP_Error( 'settings', __( 'Invalid email.', 'dovetail-studio' ) );
		}

		if ( 'url' === $type
			&& '' !== $v
			&& ! ( preg_match( '~^/(?!/)[^\\\\\s]*$~', $v ) || ( filter_var( $v, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) wp_parse_url( $v, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) )
		) {
			return new WP_Error(
				'settings',
				sprintf(
					/* translators: %s: field key. */
					__( 'Use an http(s) URL or a site-relative path: %s', 'dovetail-studio' ),
					$key
				)
			);
		}

		if ( 'digits' === $type && '' !== $v && ! preg_match( '/^[0-9]{6,15}$/', $v ) ) {
			return new WP_Error(
				'settings',
				__( 'WhatsApp must contain 6–15 digits, without +.', 'dovetail-studio' )
			);
		}

		if ( 'tel' === $type && '' !== $v && ! preg_match( '/^\+?[0-9 ()-]{3,25}$/', $v ) ) {
			return new WP_Error( 'settings', __( 'Invalid telephone number.', 'dovetail-studio' ) );
		}

		if ( in_array( $type, array( 'rating', 'count', 'duration' ), true ) ) {
			if ( 'rating' === $type && '' === $v ) {
				$out[ $key ] = '';

				continue;
			}

			$max = 'rating' === $type ? 5 : ( 'duration' === $type ? 8760 : 100000000 );

			if ( ! is_numeric( $v )
				|| $v < 0
				|| $v > $max
				|| ( 'count' === $type && floor( (float) $v ) !== (float) $v )
			) {
				return new WP_Error(
					'settings',
					sprintf(
						/* translators: %s: field key. */
						__( 'Invalid number: %s', 'dovetail-studio' ),
						$key
					)
				);
			}

			$out[ $key ] = 'count' === $type
				? (int) $v
				: ( 'rating' === $type ? number_format( (float) $v, 1, '.', '' ) : (float) $v );

			continue;
		}

		$out[ $key ] = sanitize_text_field( $v );
	}

	return $out;
}

function dt_va_public_config() {
	$saved = get_option( 'dt_va_public', array() );
	$out   = is_array( $saved ) ? array_intersect_key( $saved, dt_va_public_fields() ) : array();

	return $out;
}

/** Saved override for a single field, else the HTML default; used server-side (e.g. the system prompt) where the full config array is overkill. */
function dt_va_public_value( $key ) {
	$saved = dt_va_public_config();

	if ( array_key_exists( $key, $saved ) ) {
		return $saved[ $key ];
	}

	$defaults = dt_va_public_defaults();

	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/* Saves Public company details on admin_init, before any notice is printed, so the "assistant is hidden" notice
	reflects what was just saved. dt_va_public_settings() shows the result. */
function dt_va_public_settings_process() {
	if ( ! current_user_can( 'manage_options' ) || 'dt-va-settings' !== ( $_GET['page'] ?? '' ) ) {
		return;
	}

	if ( isset( $_POST['dt_va_public'] ) && check_admin_referer( 'dt_va_public' ) ) {
		$saved = get_option( 'dt_va_public', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$reset = $_POST['restore_default'] ?? null;

		if ( is_string( $reset ) && array_key_exists( $reset, dt_va_public_fields() ) ) {
			// Reset only this field; unrelated submitted edits are deliberately not saved.
			unset( $saved[ $reset ] );
			update_option( 'dt_va_public', $saved, false );
			$GLOBALS['dt_va_public_result'] = array(
				'success',
				sprintf(
					/* translators: %s: field key. */
					__( 'Default restored for %s. Other unsaved edits were not saved.', 'dovetail-studio' ),
					$reset
				),
			);
		} else {
			$parsed = dt_va_public_parse( $_POST );

			if ( ! is_wp_error( $parsed ) ) {
				$defaults = dt_va_public_defaults();

				foreach ( $parsed as $key => $value ) {
					// Unchanged inherited values stay inherited, so future HTML defaults still apply.
					if ( ! array_key_exists( $key, $saved ) && array_key_exists( $key, $defaults ) && $value == $defaults[ $key ] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Parsed numbers and arrays compare by value with the defaults.
						continue;
					}

					$saved[ $key ] = $value;
				}

				update_option( 'dt_va_public', $saved, false );
			}

			$GLOBALS['dt_va_public_result'] = is_wp_error( $parsed )
				? array( 'error', $parsed->get_error_message() )
				: array( 'success', __( 'Public settings saved.', 'dovetail-studio' ) );
		}
	}
}

add_action( 'admin_init', 'dt_va_public_settings_process' );

function dt_va_public_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! empty( $GLOBALS['dt_va_public_result'] ) ) {
		list($type, $message) = $GLOBALS['dt_va_public_result'];
		\DovetailStudio\Core\AdminScreens::notice( $type, esc_html( $message ) );
	}

	$saved    = get_option( 'dt_va_public', array() );
	$saved    = is_array( $saved ) ? $saved : array();
	$defaults = dt_va_public_defaults();
	$help     = dt_va_public_help();

	if ( count( $defaults ) !== count( dt_va_public_fields() ) ) {
		\DovetailStudio\Core\AdminScreens::notice(
			'error',
			esc_html__( 'Could not read all defaults from assets/front.js. Check the configuration literals before editing these fields.', 'dovetail-studio' )
		);

		return;
	}

	echo '<h2>'
		. esc_html__( 'Public company details', 'dovetail-studio' ) . '</h2><p>'
		. esc_html__( 'Edit a value and save to apply it. Optional blank values remain blank. Restore default resets only that field immediately; save other edits first. Google ratings are entered manually; no API key is needed.', 'dovetail-studio' )
		. '</p>';
	echo '<p><span class="dt-va-required" aria-hidden="true">*</span> '
		. esc_html__( 'Required: the assistant stays hidden on the site until these fields are filled in.', 'dovetail-studio' )
		. '</p>';
	echo '<style>.dt-va-required{color:#d63638;font-weight:600}.dt-va-missing th,.dt-va-missing td{background:#fcf0f1}.dt-va-missing input{border-color:#d63638}</style><form method="post">';
	wp_nonce_field( 'dt_va_public' );
	echo '<input type="hidden" name="dt_va_public" value="1"><table class="form-table">';

	foreach ( dt_va_public_fields() as $key => $type ) {
		$custom   = array_key_exists( $key, $saved );
		$v        = $custom ? $saved[ $key ] : $defaults[ $key ];
		$input_id = 'dt-va-public-' . $key;
		$required = array_key_exists( $key, dt_va_frontend_required_fields() );
		$missing  = $required && array_key_exists( $key, dt_va_frontend_missing() );
		echo '<tr id="'
			. esc_attr( $input_id . '-row' ) . '"' . ( $missing ? ' class="dt-va-missing"' : '' )
			. '><th><label for="' . esc_attr( $input_id ) . '">' . esc_html( $help[ $key ][0] )
			. ( $required ? ' <span class="dt-va-required" aria-hidden="true">*</span><span class="screen-reader-text"> ' . esc_html__( '(required)', 'dovetail-studio' ) . '</span>' : '' )
			. '</label></th><td>';

		if ( 'hours' === $type ) {
			foreach ( array(
				'open'  => __( 'Opens', 'dovetail-studio' ),
				'close' => __( 'Closes', 'dovetail-studio' ),
			) as $part => $part_label ) {
				echo '<label>'
					. esc_html( $part_label ) . ' <input id="'
					. esc_attr( 'open' === $part ? $input_id : $input_id . '-close' ) . '" aria-describedby="'
					. esc_attr( $input_id . '-help' )
					. '" type="number" step="0.25" min="0" max="24" name="public[OPENING_HOURS]['
					. esc_attr( $part ) . ']" value="' . esc_attr( $v[ $part ] ) . '"></label> ';
			}

			// 2023-01-01 was a Sunday: weekday names in the admin language.
			echo '<p>';

			for ( $d = 0; $d < 7; $d++ ) {
				echo '<label><input type="checkbox" name="public[OPENING_HOURS][days][]" value="'
					. esc_attr( $d ) . '" ' . checked( in_array( $d, $v['days'], true ), true, false ) . '>'
					. esc_html(
						wp_date(
							'D',
							strtotime( '2023-01-01 12:00:00 UTC' ) + $d * DAY_IN_SECONDS,
							new DateTimeZone( 'UTC' )
						)
					) . '</label> ';
			}

			echo '</p>';
			echo '<p><label for="'
				. esc_attr( $input_id . '-closed' ) . '">' . esc_html__( 'Closed dates', 'dovetail-studio' )
				. '</label><br><textarea id="' . esc_attr( $input_id . '-closed' )
				. '" name="public[OPENING_HOURS][closed]" rows="4" cols="40" placeholder="2026-12-24 to 2026-12-26">'
				. esc_textarea( dt_va_closed_dates_text( isset( $v['closed'] ) ? $v['closed'] : array() ) )
				. '</textarea><br><span class="description">'
				. esc_html__( 'Holidays and other days the office is closed: one date (YYYY-MM-DD) or range (YYYY-MM-DD to YYYY-MM-DD) per line. Past dates are dropped when saving.', 'dovetail-studio' )
				. '</span></p>';
		} elseif ( 'company_type' === $type ) {
			echo '<input id="'
				. esc_attr( $input_id ) . '" aria-describedby="' . esc_attr( $input_id . '-help' )
				. '" class="regular-text" type="text" list="dt-va-company-type-list" maxlength="40" name="public['
				. esc_attr( $key ) . ']" value="' . esc_attr( $v ) . '">';
			echo '<datalist id="dt-va-company-type-list"><option value="Office"><option value="Studio"><option value="Restaurant"><option value="Store"></datalist>';
		} else {
			echo '<input id="'
				. esc_attr( $input_id ) . '" aria-describedby="' . esc_attr( $input_id . '-help' )
				. '" class="regular-text" type="text" name="public[' . esc_attr( $key ) . ']" value="'
				. esc_attr( $v ) . '">';
		}

		echo ' <button class="button" name="restore_default" value="'
			. esc_attr( $key ) . '" formnovalidate>' . esc_html__( 'Restore default', 'dovetail-studio' )
			. '</button>';
		echo '<p class="description" id="'
			. esc_attr( $input_id . '-help' ) . '">' . esc_html( $help[ $key ][1] )
			. '</p><p class="description"><code>' . esc_html( $key ) . '</code> — '
			. ( $custom ? esc_html__( 'Saved value', 'dovetail-studio' ) : esc_html__( 'Using HTML default', 'dovetail-studio' ) )
			. '</p></td></tr>';
	}

	echo '</table><button class="button button-primary">'
		. esc_html__( 'Save public details', 'dovetail-studio' ) . '</button></form>';
}

/**
 * Closed dates from the admin textarea: one date or "from to to" range per line, in the site time zone.
 * Ranges that have already ended are dropped.
 *
 * @return array<int,array{from:string,to:string}>|WP_Error
 */
function dt_va_parse_closed_dates( $text ) {
	$out   = array();
	$today = wp_date( 'Y-m-d', null, wp_timezone() );

	foreach ( preg_split( '/[\r\n]+/', (string) $text ) as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})(?:\s*(?:to|-|–|\|)\s*(\d{4}-\d{2}-\d{2}))?$/u', $line, $m ) ) {
			/* translators: %s: the line that could not be read. */
			return new WP_Error( 'settings', sprintf( __( 'Closed dates: "%s" is not a date (YYYY-MM-DD) or a range (YYYY-MM-DD to YYYY-MM-DD).', 'dovetail-studio' ), $line ) );
		}

		$from = $m[1];
		$to   = isset( $m[2] ) && '' !== $m[2] ? $m[2] : $m[1];

		foreach ( array( $from, $to ) as $date ) {
			$parts = array_map( 'intval', explode( '-', $date ) );

			if ( ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
				/* translators: %s: the line that is not a date. */
				return new WP_Error( 'settings', sprintf( __( 'Closed dates: "%s" is not a date (YYYY-MM-DD) or a range (YYYY-MM-DD to YYYY-MM-DD).', 'dovetail-studio' ), $line ) );
			}
		}

		if ( $to < $from ) {
			list($from, $to) = array( $to, $from );
		}

		if ( $to >= $today ) {
			$out[] = array(
				'from' => $from,
				'to'   => $to,
			);
		}
	}

	usort(
		$out,
		function ( $a, $b ) {
			return strcmp( $a['from'], $b['from'] );
		}
	);

	return array_slice( $out, 0, 60 );
}

/** Closed dates back to the textarea format. */
function dt_va_closed_dates_text( $closed ) {
	$lines = array();

	foreach ( is_array( $closed ) ? $closed : array() as $range ) {
		if ( ! is_array( $range ) || empty( $range['from'] ) || empty( $range['to'] ) ) {
			continue;
		}

		$lines[] = $range['from'] === $range['to'] ? $range['from'] : $range['from'] . ' to ' . $range['to'];
	}

	return implode( "\n", $lines );
}
