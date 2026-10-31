<?php

/** Mail identity settings. Getters keep saved changes effective within the same request. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dt_va_company_fields() {
	// The site name is the default company and sender name, so a new installation never shows another company's name.
	$site = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );

	return array(
		'name'      => array(
			'constant' => 'DT_VA_COMPANY_NAME',
			'label'    => __( 'Company name', 'dovetail-studio' ),
			'default'  => $site,
		),
		'avatar'    => array(
			'constant' => 'DT_VA_COMPANY_AVATAR',
			'label'    => __( 'Avatar', 'dovetail-studio' ),
			'default'  => 'DT',
		),
		'to'        => array(
			'constant' => 'DT_VA_TO',
			'label'    => __( 'Enquiry email', 'dovetail-studio' ),
			'default'  => '',
		),
		'from_name' => array(
			'constant' => 'DT_VA_FROM_NAME',
			'label'    => __( 'Sender name', 'dovetail-studio' ),
			'default'  => $site,
		),
		'from'      => array(
			'constant' => 'DT_VA_FROM',
			'label'    => __( 'Sender email', 'dovetail-studio' ),
			'default'  => '',
		),
	);
}

/** wordpress@ the site's domain (without www.), the address WordPress itself sends from. */
function dt_va_default_sender() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	if ( 0 === strpos( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}

	return 'wordpress@' . $host;
}

/**
 * Headers for the assistant's emails. They are sent from an address on the site's own domain, so they pass
 * SPF/DKIM/DMARC; the enquiry email (or the visitor) goes in Reply-To. Sending "from" a Gmail or Outlook
 * address through the website's server is what sends mail to spam or gets it rejected.
 *
 * @param string|null $reply_to Reply-To header value; null for the enquiry email, '' for none.
 * @return string[]
 */
function dt_va_mail_headers( $reply_to = null ) {
	$from = dt_va_company_value( 'from' );

	if ( ! is_email( $from ) ) {
		$from = dt_va_default_sender();
	}

	$headers  = array(
		'From: ' . dt_va_header_text( dt_va_company_value( 'from_name' ) ) . ' <' . $from . '>',
	);
	$reply_to = null === $reply_to ? dt_va_company_value( 'to' ) : $reply_to;

	if ( '' !== $reply_to ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}

	return $headers;
}

function dt_va_company_value( $field ) {
	$meta = dt_va_company_fields()[ $field ];
	// Explicit wp-config.php definitions always win over values saved in the admin.
	$value = defined( $meta['constant'] )
		? (string) constant( $meta['constant'] )
		: (string) get_option( 'dt_va_company_' . $field, $meta['default'] );

	// Until the enquiry email is saved, enquiries go to the site administrator, never to a built-in address.
	if ( 'to' === $field && ! is_email( $value ) ) {
		$value = (string) get_option( 'admin_email' );
	}

	return in_array( $field, array( 'name', 'avatar', 'from_name' ), true ) && '' === trim( $value )
		? $meta['default']
		: $value;
}

function dt_va_company_collapse_avatar() {
	return '1' === get_option( 'dt_va_company_collapse_avatar', '' );
}

function dt_va_digest_days() {
	return array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
}

function dt_va_digest_day() {
	$v = strtolower( (string) get_option( 'dt_va_digest_day', 'monday' ) );

	return in_array( $v, dt_va_digest_days(), true ) ? $v : 'monday';
}

function dt_va_digest_time() {
	$v = (string) get_option( 'dt_va_digest_time', '08:00' );

	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ? $v : '08:00';
}

/** Falls back to the enquiry email ('to') when no digest-specific recipient is set. */
function dt_va_digest_recipient() {
	$v = trim( (string) get_option( 'dt_va_digest_recipient', '' ) );

	return '' !== $v && is_email( $v ) ? $v : dt_va_company_value( 'to' );
}

function dt_va_digest_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$error  = '';
	$notice = '';

	if ( isset( $_POST['dt_va_digest'] ) && check_admin_referer( 'dt_va_digest' ) ) {
		$day       = isset( $_POST['digest_day'] ) && is_string( $_POST['digest_day'] )
			? strtolower( trim( wp_unslash( $_POST['digest_day'] ) ) )
			: '';
		$time      = isset( $_POST['digest_time'] ) && is_string( $_POST['digest_time'] )
			? trim( wp_unslash( $_POST['digest_time'] ) )
			: '';
		$recipient = isset( $_POST['digest_recipient'] ) && is_string( $_POST['digest_recipient'] )
			? trim( wp_unslash( $_POST['digest_recipient'] ) )
			: '';

		if ( ! in_array( $day, dt_va_digest_days(), true ) ) {
			$error = __( 'Choose a valid day of the week.', 'dovetail-studio' );
		} elseif ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$error = __( 'Enter the time as HH:MM, 24-hour, for example 08:00.', 'dovetail-studio' );
		} elseif ( '' !== $recipient && ! is_email( $recipient ) ) {
			$error = __( 'Enter a valid recipient email, or leave it blank to use the enquiry email.', 'dovetail-studio' );
		} else {
			update_option( 'dt_va_digest_day', $day, false );
			update_option( 'dt_va_digest_time', $time, false );
			update_option(
				'dt_va_digest_recipient',
				'' !== $recipient ? sanitize_email( $recipient ) : '',
				false
			);

			// Reschedule immediately; dt_va_schedule_events() would otherwise wait for the next page load.
			if ( function_exists( 'dt_va_digest_reschedule' ) ) {
				dt_va_digest_reschedule();
			}

			$notice = __( 'Weekly digest schedule saved.', 'dovetail-studio' );
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
		'partials/digest-settings',
		array(
			'days'      => dt_va_digest_days(),
			'day'       => dt_va_digest_day(),
			'time'      => dt_va_digest_time(),
			'recipient' => trim( (string) get_option( 'dt_va_digest_recipient', '' ) ),
			'to'        => dt_va_company_value( 'to' ),
		)
	);
}

function dt_va_company_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$error = '';

	if ( isset( $_POST['dt_va_company'] ) && check_admin_referer( 'dt_va_company' ) ) {
		$values = array();

		foreach ( dt_va_company_fields() as $field => $meta ) {
			if ( defined( $meta['constant'] ) ) {
				continue;
			}

			$raw = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] )
				? trim( wp_unslash( $_POST[ $field ] ) )
				: '';

			if ( 'avatar' === $field ) {
				if ( preg_match( '/[\r\n<>]/', $raw ) || mb_strlen( $raw ) > 2 ) {
					$error = __( 'Avatar must be at most 2 characters, without line breaks or angle brackets. Nothing was saved.', 'dovetail-studio' );

					break;
				}

				$values[ $field ] = sanitize_text_field( $raw );

				continue;
			}

			if ( 'from' === $field ) {
				// Optional: blank sends from the default address on the site's domain.
				if ( '' !== $raw && ( preg_match( '/[\r\n]/', $raw ) || ! is_email( $raw ) ) ) {
					$error = __( 'Enter a valid sender email, or leave it blank to send from the site\'s own address. Nothing was saved.', 'dovetail-studio' );

					break;
				}

				$values[ $field ] = sanitize_email( $raw );

				continue;
			}

			// Reject header delimiters rather than silently turning a malformed address into a different one.
			if ( '' === $raw
				|| preg_match( '/[\r\n]/', $raw )
				|| ( 'to' === $field && ! is_email( $raw ) )
			) {
				$error = __( 'Enter a company name, a valid enquiry email and a non-empty sender name. Nothing was saved.', 'dovetail-studio' );

				break;
			}

			$values[ $field ] = 'to' === $field ? sanitize_email( $raw ) : sanitize_text_field( $raw );

			if ( '' === $values[ $field ] || ( 'from_name' === $field && preg_match( '/[<>]/', $raw ) ) ) {
				$error = __( 'Enter a valid sender name without email header characters. Nothing was saved.', 'dovetail-studio' );

				break;
			}
		}

		if ( '' === $error ) {
			foreach ( $values as $field => $value ) {
				update_option( 'dt_va_company_' . $field, $value, false );
			}

			update_option(
				'dt_va_company_collapse_avatar',
				! empty( $_POST['collapse_avatar'] ) ? '1' : '',
				false
			);
		}

		\DovetailStudio\Core\AdminScreens::notice(
			'' === $error ? 'success' : 'error',
			esc_html( '' === $error ? __( 'Company settings saved.', 'dovetail-studio' ) : $error )
		);
	}

	$help   = array(
		'name'      => __( 'Displayed in the assistant and used in enquiry email copy. Defaults to the site title.', 'dovetail-studio' ),
		'to'        => __( 'Receives briefs and automated notifications, and is the Reply-To address of the emails sent to visitors.', 'dovetail-studio' ),
		'from_name' => __( 'Display name in outgoing email headers. Defaults to the site title.', 'dovetail-studio' ),
		/* translators: %s: default sender address, e.g. wordpress@example.com. */
		'from'      => sprintf( __( 'Address the assistant sends from. Use an address on this site\'s domain so the emails pass SPF and DKIM and do not land in spam; leave blank to use %s. Replies still go to the enquiry email.', 'dovetail-studio' ), dt_va_default_sender() ),
		'avatar'    => '',
	);
	$fields = array();

	foreach ( dt_va_company_fields() as $field => $meta ) {
		$fields[ $field ] = array(
			'label'    => $meta['label'],
			'constant' => $meta['constant'],
			'external' => defined( $meta['constant'] ),
			'value'    => dt_va_company_value( $field ),
			'help'     => $help[ $field ],
		);
	}

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'tab-company',
		array(
			'fields'          => $fields,
			'collapse_avatar' => dt_va_company_collapse_avatar(),
			'default_sender'  => dt_va_default_sender(),
		)
	);

	if ( function_exists( 'dt_va_public_settings' ) ) {
		dt_va_public_settings();
	}

	dt_va_digest_settings();
	dt_va_privacy_settings();
}
