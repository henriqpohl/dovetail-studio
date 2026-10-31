<?php
/** Newsletter runtime functions. @package DovetailStudio\Newsletter */

if ( ! defined( 'ABSPATH' ) || ! defined( 'DOVETAIL_STUDIO_VERSION' ) ) {
	exit;
}

/** Encryption context of the Mailchimp key in the shared Core Crypto helper. */
const DOVETAIL_NEWSLETTER_CRYPTO_CONTEXT = 'dovetail-newsletter';

/**
 * Returns the saved plugin settings, allowing constants to override secrets.
 *
 * @return array{api_key:string,audience_id:string,double_opt_in:bool}
 */
function dovetail_newsletter_get_settings() {
	// Asked several times per form submission: decrypt the key once while the saved option is unchanged.
	static $memo = null, $raw = null;
	$saved       = get_option( 'dovetail_newsletter_settings', array() );

	if ( null !== $memo && $saved === $raw ) {
		return $memo;
	}

	$settings = wp_parse_args(
		$saved,
		array(
			'mode'          => '',
			'cf7_shortcode' => '',
			'api_key'       => '',
			'audience_id'   => '',
			'double_opt_in' => true,
			'merge_fields'  => '',
			'tags'          => '',
		)
	);

	$settings['api_key'] = \DovetailStudio\Core\Crypto::decrypt(
		(string) $settings['api_key'],
		DOVETAIL_NEWSLETTER_CRYPTO_CONTEXT
	);

	if ( defined( 'DOVETAIL_NEWSLETTER_API_KEY' ) ) {
		$settings['api_key'] = DOVETAIL_NEWSLETTER_API_KEY;
	}

	if ( defined( 'DOVETAIL_NEWSLETTER_AUDIENCE_ID' ) ) {
		$settings['audience_id'] = DOVETAIL_NEWSLETTER_AUDIENCE_ID;
	}

	$raw  = $saved;
	$memo = $settings;

	return $settings;
}

/**
 * Merge-field mapping: "MERGETAG=cf7-field-name", one per line.
 *
 * @param string $text Saved mapping.
 * @return array<string,string> Mailchimp merge tag => CF7 field name.
 */
function dovetail_newsletter_parse_merge_fields( $text ) {
	$map = array();

	foreach ( preg_split( '/[\r\n]+/', (string) $text ) as $line ) {
		if ( preg_match( '/^\s*([A-Za-z0-9_]{1,10})\s*=\s*([A-Za-z][A-Za-z0-9_-]*)\s*$/', $line, $m ) ) {
			$map[ strtoupper( $m[1] ) ] = $m[2];
		}
	}

	return $map;
}

/**
 * Tags added to every subscriber.
 *
 * @param string $text Comma-separated tags.
 * @return string[]
 */
function dovetail_newsletter_parse_tags( $text ) {
	$tags = array_filter( array_map( 'trim', explode( ',', (string) $text ) ), 'strlen' );

	return array_slice(
		array_values(
			array_unique(
				array_map(
					function ( $tag ) {
						return mb_substr( $tag, 0, 100 );
					},
					$tags
				)
			)
		),
		0,
		10
	);
}

/** Render the saved CF7 shortcode, allowing an explicit theme override. */
function dovetail_newsletter_render( $shortcode = '' ) {
	$settings = dovetail_newsletter_get_settings();

	if ( null === $shortcode || ( is_string( $shortcode ) && '' === trim( $shortcode ) ) ) {
		$shortcode = $settings['cf7_shortcode'];
	}

	$error = '';

	if ( ! is_string( $shortcode )
		|| ! preg_match( '/^\[contact-form-7\s+[^\[\]<>]+\]$/D', trim( $shortcode ) )
	) {
		$error = __( 'Newsletter is not configured. Add a Contact Form 7 shortcode in Settings > Dovetail Newsletter.', 'dovetail-studio' );
	} elseif ( ! shortcode_exists( 'contact-form-7' ) || ! class_exists( 'WPCF7_ContactForm' ) ) {
		$error = __( 'Newsletter is not configured. Activate Contact Form 7.', 'dovetail-studio' );
	} elseif ( ! in_array( $settings['mode'], array( 'cf7', 'mailchimp', 'both' ), true ) ) {
		$error = __( 'Newsletter is not configured. Select an integration in Dovetail Newsletter.', 'dovetail-studio' );
	} elseif ( in_array( $settings['mode'], array( 'mailchimp', 'both' ), true )
		&& ( ! preg_match( '/-us[0-9]+$/', trim( $settings['api_key'] ) ) || '' === trim( $settings['audience_id'] ) )
	) {
		$error = __( 'Newsletter is not configured. Complete the Mailchimp settings.', 'dovetail-studio' );
	}

	if ( $error ) {
		return \DovetailStudio\Core\View::capture( dirname( __DIR__ ), 'partials/status', array( 'message' => $error ) );
	}

	// The signed marker scopes Mailchimp processing to this rendered form instance.
	$marker = static function ( $fields ) {
		$form = WPCF7_ContactForm::get_current();

		if ( $form ) {
			$fields['_dovetail_newsletter'] = wp_hash( 'dovetail-newsletter:' . $form->id() );
		}

		return $fields;
	};
	add_filter( 'wpcf7_form_hidden_fields', $marker );

	try {
		$html = do_shortcode( trim( $shortcode ) );
	} finally {
		remove_filter( 'wpcf7_form_hidden_fields', $marker );
	}

	if ( ! preg_match( '/<form\b/i', $html ) ) {
		return \DovetailStudio\Core\View::capture(
			dirname( __DIR__ ),
			'partials/status',
			array( 'message' => __( 'Newsletter is not configured. Check the configured Contact Form 7 shortcode.', 'dovetail-studio' ) )
		);
	}

	return $html;
}

/** Verify that this submission belongs to a form rendered through the theme helper. */
function dovetail_newsletter_is_submission( $form ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 verifies the submission; this only reads the form marker.
	$marker = isset( $_POST['_dovetail_newsletter'] ) ? wp_unslash( $_POST['_dovetail_newsletter'] ) : '';

	return is_string( $marker ) && hash_equals( wp_hash( 'dovetail-newsletter:' . $form->id() ), $marker );
}

/** Runs only after CF7 validation, acceptance and spam checks have passed. */
function dovetail_newsletter_before_send( $form, &$abort, $submission ) {
	$settings = dovetail_newsletter_get_settings();

	if ( $abort
		|| ! dovetail_newsletter_is_submission( $form )
		|| ! in_array( $settings['mode'], array( 'mailchimp', 'both' ), true )
	) {
		return;
	}

	$emails = array();

	foreach ( $form->scan_form_tags() as $tag ) {
		if ( 'email' === $tag->basetype && $tag->name ) {
			$emails[ $tag->name ] = $submission->get_posted_data( $tag->name );
		}
	}

	if ( 1 !== count( $emails ) || ! is_string( reset( $emails ) ) ) {
		if ( 'both' === $settings['mode'] ) {
			return;
		}

		$abort = true;
		$submission->set_response(
			__( 'Newsletter is not configured. The CF7 form must contain exactly one email field.', 'dovetail-studio' )
		);

		return;
	}

	$merge = array();

	foreach ( dovetail_newsletter_parse_merge_fields( $settings['merge_fields'] ) as $tag => $field ) {
		$value = $submission->get_posted_data( $field );
		$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;

		if ( '' !== trim( $value ) ) {
			$merge[ $tag ] = sanitize_text_field( $value );
		}
	}

	$result = dovetail_newsletter_subscribe( reset( $emails ), $merge );

	if ( is_wp_error( $result ) ) {
		// In "Both" mode the form email still goes out: a Mailchimp failure must not lose the message.
		if ( 'both' === $settings['mode'] ) {
			$submission->add_result_props(
				array( 'dovetail_newsletter_error' => $result->get_error_message() )
			);

			return;
		}

		$abort = true;
		$submission->set_response( $result->get_error_message() );

		return;
	}

	$submission->add_result_props( array( 'dovetail_newsletter_message' => $result['message'] ) );
}

add_action( 'wpcf7_before_send_mail', 'dovetail_newsletter_before_send', 20, 3 );

function dovetail_newsletter_skip_mail( $skip, $form ) {
	return $skip
		|| ( dovetail_newsletter_is_submission( $form ) && 'mailchimp' === dovetail_newsletter_get_settings()['mode'] );
}

add_filter( 'wpcf7_skip_mail', 'dovetail_newsletter_skip_mail', 20, 2 );

function dovetail_newsletter_submission_result( $result ) {
	if ( 'mail_sent' === ( $result['status'] ?? '' ) && isset( $result['dovetail_newsletter_message'] ) ) {
		$result['message'] = $result['dovetail_newsletter_message'];
	}

	if ( 'mail_failed' === ( $result['status'] ?? '' ) && isset( $result['dovetail_newsletter_message'] ) ) {
		$result['message'] = $result['dovetail_newsletter_message']
			. ' ' . __( 'However, the form email could not be sent.', 'dovetail-studio' );
	}

	if ( 'mail_sent' === ( $result['status'] ?? '' ) && isset( $result['dovetail_newsletter_error'] ) ) {
		/* translators: %s: why the subscription failed. */
		$result['message'] = trim( ( $result['message'] ?? '' ) . ' ' . sprintf( __( 'The newsletter subscription could not be completed: %s', 'dovetail-studio' ), $result['dovetail_newsletter_error'] ) );
	}

	unset( $result['dovetail_newsletter_message'], $result['dovetail_newsletter_error'] );

	return $result;
}

add_filter( 'wpcf7_submission_result', 'dovetail_newsletter_submission_result' );

/**
 * Handles a newsletter subscription.
 *
 * @param string               $email        Validated CF7 email value.
 * @param array<string,string> $merge_fields Mailchimp merge fields (FNAME, PHONE …).
 * @return array|WP_Error
 */
function dovetail_newsletter_subscribe( $email, array $merge_fields = array() ) {
	$settings = dovetail_newsletter_get_settings();

	if ( ! in_array( $settings['mode'], array( 'mailchimp', 'both' ), true ) ) {
		return new WP_Error(
			'dovetail_newsletter_disabled',
			__( 'Mailchimp integration is disabled.', 'dovetail-studio' ),
			array( 'status' => 503 )
		);
	}

	if ( ! \DovetailStudio\Core\Http::rate_limit( 'newsletter_subscribe', 5, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_Error(
			'dovetail_newsletter_rate_limited',
			__( 'Too many attempts. Please wait a few minutes and try again.', 'dovetail-studio' ),
			array( 'status' => 429 )
		);
	}

	$email   = sanitize_email( $email );
	$api_key = trim( (string) $settings['api_key'] );
	$list_id = trim( (string) $settings['audience_id'] );

	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'dovetail_newsletter_invalid_email',
			__( 'Please enter a valid email address.', 'dovetail-studio' ),
			array( 'status' => 400 )
		);
	}

	if ( '' === $api_key || '' === $list_id || ! str_contains( $api_key, '-' ) ) {
		return new WP_Error(
			'dovetail_newsletter_not_configured',
			__( 'Newsletter integration is not configured.', 'dovetail-studio' ),
			array( 'status' => 503 )
		);
	}

	$data_center = sanitize_key( substr( $api_key, strrpos( $api_key, '-' ) + 1 ) );
	$member_hash = md5( strtolower( $email ) );
	$status      = ! empty( $settings['double_opt_in'] ) ? 'pending' : 'subscribed';
	$url         = sprintf(
		'https://%1$s.api.mailchimp.com/3.0/lists/%2$s/members/%3$s',
		$data_center,
		rawurlencode( $list_id ),
		$member_hash
	);

	$request = static function ( $method, $body = null, $path = '' ) use ( $url, $api_key ) {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'wordpress:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth for the Mailchimp API.
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url . $path, $args );

		if ( is_wp_error( $response ) ) {
			return array( 0, null );
		}

		return array(
			(int) wp_remote_retrieve_response_code( $response ),
			json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	};
	$failed  = static function ( $code ) {
		return new WP_Error(
			'dovetail_newsletter_api_error',
			__( 'We could not complete your subscription. Please try again.', 'dovetail-studio' ),
			array( 'status' => 0 === $code ? 502 : 400 )
		);
	};

	// Look the contact up first: an existing contact keeps its status on PUT, so someone who
	// unsubscribed would otherwise be told to check their email while nothing changes.
	list( $code, $member ) = $request( 'GET' );
	$current               = 200 === $code && is_array( $member ) && isset( $member['status'] )
		? (string) $member['status']
		: '';

	if ( 200 !== $code && 404 !== $code ) {
		return $failed( $code );
	}

	if ( 'subscribed' === $current ) {
		return array( 'message' => __( 'This email address is already subscribed.', 'dovetail-studio' ) );
	}

	if ( '' === $current ) {
		// New contact (or archived: Mailchimp answers 404 and PUT restores it).
		$body = array(
			'email_address' => $email,
			'status_if_new' => $status,
			'status'        => $status,
		);

		if ( $merge_fields ) {
			$body['merge_fields'] = $merge_fields;
		}

		list( $code ) = $request( 'PUT', $body );
	} elseif ( 'pending' === $current ) {
		// Already waiting for confirmation; the confirmation email is in their inbox.
		return array(
			'message' => __( 'Please check your email to confirm your subscription.', 'dovetail-studio' ),
		);
	} else {
		// unsubscribed, cleaned or transactional: join again through the configured opt-in.
		// Mailchimp refuses this for addresses it cleaned after hard bounces.
		list( $code ) = $request(
			'PATCH',
			$merge_fields ? array(
				'status'       => $status,
				'merge_fields' => $merge_fields,
			) : array( 'status' => $status )
		);
	}

	if ( $code < 200 || $code >= 300 ) {
		return $failed( $code );
	}

	// Tags are a bonus: a failure here does not undo the subscription.
	$tags = dovetail_newsletter_parse_tags( $settings['tags'] );

	if ( $tags ) {
		$request(
			'POST',
			array(
				'tags' => array_map(
					function ( $tag ) {
						return array(
							'name'   => $tag,
							'status' => 'active',
						);
					},
					$tags
				),
			),
			'/tags'
		);
	}

	$message = 'pending' === $status
		? __( 'Please check your email to confirm your subscription.', 'dovetail-studio' )
		: __( 'Thank you for subscribing.', 'dovetail-studio' );

	return array( 'message' => $message );
}

/**
 * Registers the settings page.
 */
function dovetail_newsletter_admin_init() {
	register_setting(
		'dovetail_newsletter',
		'dovetail_newsletter_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'dovetail_newsletter_sanitize_settings',
			'default'           => array(),
		)
	);
}

add_action( 'admin_init', 'dovetail_newsletter_admin_init' );

/**
 * Sanitizes settings while preserving a masked API key.
 *
 * @param array $input Submitted settings.
 * @return array
 */
function dovetail_newsletter_sanitize_settings( $input ) {
	$current = get_option( 'dovetail_newsletter_settings', array() );
	$mode    = $input['mode'] ?? '';

	if ( ! in_array( $mode, array( 'mailchimp', 'cf7', 'both' ), true ) ) {
		add_settings_error(
			'dovetail_newsletter_settings',
			'mode',
			__( 'Choose an integration first.', 'dovetail-studio' )
		);

		return $current;
	}

	$shortcode = $input['cf7_shortcode'] ?? $current['cf7_shortcode'] ?? '';

	if ( ! is_string( $shortcode )
		|| ( '' !== trim( $shortcode ) && ! preg_match( '/^\[contact-form-7\s+[^\[\]<>]+\]$/D', trim( $shortcode ) ) )
	) {
		add_settings_error(
			'dovetail_newsletter_settings',
			'cf7_shortcode',
			__( 'Enter a valid Contact Form 7 shortcode, for example [contact-form-7 id="123" title="Newsletter"].', 'dovetail-studio' )
		);

		return $current;
	}

	$shortcode = trim( sanitize_text_field( $shortcode ) );
	$mailchimp = in_array( $mode, array( 'mailchimp', 'both' ), true );
	$api_key   = sanitize_text_field( $input['api_key'] ?? '' );

	if ( '' === $api_key ) {
		$api_key = $current['api_key'] ?? '';
	} elseif ( ! \DovetailStudio\Core\Crypto::is_encrypted( $api_key ) ) {
		$encrypted_api_key = \DovetailStudio\Core\Crypto::encrypt(
			$api_key,
			DOVETAIL_NEWSLETTER_CRYPTO_CONTEXT
		);

		if ( is_wp_error( $encrypted_api_key ) ) {
			add_settings_error(
				'dovetail_newsletter_settings',
				$encrypted_api_key->get_error_code(),
				$encrypted_api_key->get_error_message(),
				'error'
			);

			$api_key = $current['api_key'] ?? '';
		} else {
			$api_key = $encrypted_api_key;
		}
	}

	$merge_text = (string) ( $input['merge_fields'] ?? $current['merge_fields'] ?? '' );
	$merge      = dovetail_newsletter_parse_merge_fields( $merge_text );

	return array(
		'mode'          => $mode,
		'cf7_shortcode' => $shortcode,
		'api_key'       => $api_key,
		'audience_id'   => sanitize_text_field( $input['audience_id'] ?? $current['audience_id'] ?? '' ),
		'double_opt_in' => $mailchimp ? ! empty( $input['double_opt_in'] ) : ( $current['double_opt_in'] ?? true ),
		'merge_fields'  => implode(
			"\n",
			array_map(
				function ( $tag, $field ) {
					return $tag . '=' . $field;
				},
				array_keys( $merge ),
				$merge
			)
		),
		'tags'          => implode( ', ', dovetail_newsletter_parse_tags( sanitize_text_field( (string) ( $input['tags'] ?? $current['tags'] ?? '' ) ) ) ),
	);
}

/**
 * Adds the settings page.
 */
function dovetail_newsletter_admin_menu() {
	add_submenu_page(
		'dovetail-studio',
		__( 'Newsletter', 'dovetail-studio' ),
		__( 'Newsletter', 'dovetail-studio' ),
		'manage_options',
		'dovetail-newsletter',
		'dovetail_newsletter_settings_page'
	);
}

add_action( 'admin_menu', 'dovetail_newsletter_admin_menu', 20 );

/**
 * Renders the settings page.
 */
function dovetail_newsletter_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_enqueue_script(
		'dovetail-newsletter-admin',
		DOVETAIL_NEWSLETTER_URL . 'assets/js/admin.js',
		array(),
		DOVETAIL_NEWSLETTER_VERSION,
		true
	);

	\DovetailStudio\Core\View::render(
		dirname( __DIR__ ),
		'admin-page',
		array(
			'version'           => DOVETAIL_NEWSLETTER_VERSION,
			'settings'          => dovetail_newsletter_get_settings(),
			'has_cf7'           => shortcode_exists( 'contact-form-7' ) && class_exists( 'WPCF7_ContactForm' ),
			'has_cf7_shortcode' => shortcode_exists( 'contact-form-7' ),
		)
	);
}
