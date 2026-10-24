<?php
/** Newsletter runtime functions. @package DovetailStudio\Newsletter */

if ( ! defined( 'ABSPATH' ) || ! defined( 'DOVETAIL_STUDIO_VERSION' ) ) {
	exit;
}


/**
 * Creates a site-specific encryption key from the WordPress security salts.
 *
 * @return string Binary encryption key.
 */
function dovetail_newsletter_get_encryption_key() {
	$material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) . '|dovetail-newsletter|v1';

	return hash( 'sha256', $material, true );
}

/**
 * Encrypts a Mailchimp API key for database storage.
 *
 * @param string $api_key Plain-text API key.
 * @return string|WP_Error
 */
function dovetail_newsletter_encrypt_api_key( $api_key ) {
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		return new WP_Error(
			'dovetail_newsletter_openssl_missing',
			__( 'The API key could not be saved because the OpenSSL extension is unavailable.', 'dovetail-newsletter' )
		);
	}

	try {
		$iv = random_bytes( 12 );
	} catch ( Exception $exception ) {
		return new WP_Error(
			'dovetail_newsletter_random_bytes_failed',
			__( 'The API key could not be encrypted. Please contact the website administrator.', 'dovetail-newsletter' )
		);
	}

	$tag        = '';
	$ciphertext = openssl_encrypt(
		$api_key,
		'aes-256-gcm',
		dovetail_newsletter_get_encryption_key(),
		OPENSSL_RAW_DATA,
		$iv,
		$tag,
		'dovetail-newsletter'
	);

	if ( false === $ciphertext ) {
		return new WP_Error(
			'dovetail_newsletter_encryption_failed',
			__( 'The API key could not be encrypted. Please contact the website administrator.', 'dovetail-newsletter' )
		);
	}

	return DOVETAIL_NEWSLETTER_ENCRYPTION_PREFIX . base64_encode( $iv . $tag . $ciphertext );
}

/**
 * Decrypts a Mailchimp API key immediately before use.
 *
 * @param string $stored_value Encrypted database value.
 * @return string
 */
function dovetail_newsletter_decrypt_api_key( $stored_value ) {
	if ( '' === $stored_value ) {
		return '';
	}

	if ( ! str_starts_with( $stored_value, DOVETAIL_NEWSLETTER_ENCRYPTION_PREFIX ) ) {
		return '';
	}

	if ( ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}

	$encoded = substr( $stored_value, strlen( DOVETAIL_NEWSLETTER_ENCRYPTION_PREFIX ) );
	$payload = base64_decode( $encoded, true );

	if ( false === $payload || strlen( $payload ) < 29 ) {
		return '';
	}

	$iv         = substr( $payload, 0, 12 );
	$tag        = substr( $payload, 12, 16 );
	$ciphertext = substr( $payload, 28 );
	$plain_text = openssl_decrypt(
		$ciphertext,
		'aes-256-gcm',
		dovetail_newsletter_get_encryption_key(),
		OPENSSL_RAW_DATA,
		$iv,
		$tag,
		'dovetail-newsletter'
	);

	return false === $plain_text ? '' : $plain_text;
}

/**
 * Returns the saved plugin settings, allowing constants to override secrets.
 *
 * @return array{api_key:string,audience_id:string,double_opt_in:bool}
 */
function dovetail_newsletter_get_settings() {
	$settings = wp_parse_args(
		get_option( 'dovetail_newsletter_settings', array() ),
		array(
			'mode' => '',
			'cf7_shortcode' => '',
			'api_key'      => '',
			'audience_id'  => '',
			'double_opt_in' => true,
		)
	);

	$settings['api_key'] = dovetail_newsletter_decrypt_api_key( (string) $settings['api_key'] );

	if ( defined( 'DOVETAIL_NEWSLETTER_API_KEY' ) ) {
		$settings['api_key'] = DOVETAIL_NEWSLETTER_API_KEY;
	}

	if ( defined( 'DOVETAIL_NEWSLETTER_AUDIENCE_ID' ) ) {
		$settings['audience_id'] = DOVETAIL_NEWSLETTER_AUDIENCE_ID;
	}

	return $settings;
}

/** Render the saved CF7 shortcode, allowing an explicit theme override. */
function dovetail_newsletter_render( $shortcode = '' ) {
	$settings = dovetail_newsletter_get_settings();
	if ( null === $shortcode || ( is_string( $shortcode ) && '' === trim( $shortcode ) ) ) {
		$shortcode = $settings['cf7_shortcode'];
	}
	$error = '';
	if ( ! is_string( $shortcode ) || ! preg_match( '/^\[contact-form-7\s+[^\[\]<>]+\]$/D', trim( $shortcode ) ) ) {
		$error = __( 'Newsletter is not configured. Add a Contact Form 7 shortcode in Settings > Dovetail Newsletter.', 'dovetail-newsletter' );
	} elseif ( ! shortcode_exists( 'contact-form-7' ) || ! class_exists( 'WPCF7_ContactForm' ) ) {
		$error = __( 'Newsletter is not configured. Activate Contact Form 7.', 'dovetail-newsletter' );
	} elseif ( ! in_array( $settings['mode'], array( 'cf7', 'mailchimp', 'both' ), true ) ) {
		$error = __( 'Newsletter is not configured. Select an integration in Dovetail Newsletter.', 'dovetail-newsletter' );
	} elseif ( in_array( $settings['mode'], array( 'mailchimp', 'both' ), true ) && ( ! preg_match( '/-us[0-9]+$/', trim( $settings['api_key'] ) ) || '' === trim( $settings['audience_id'] ) ) ) {
		$error = __( 'Newsletter is not configured. Complete the Mailchimp settings.', 'dovetail-newsletter' );
	}
	if ( $error ) {
		return '<p role="status">' . esc_html( $error ) . '</p>';
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
		return '<p role="status">' . esc_html__( 'Newsletter is not configured. Check the configured Contact Form 7 shortcode.', 'dovetail-newsletter' ) . '</p>';
	}
	return $html;
}

/** Verify that this submission belongs to a form rendered through the theme helper. */
function dovetail_newsletter_is_submission( $form ) {
	$marker = isset( $_POST['_dovetail_newsletter'] ) ? wp_unslash( $_POST['_dovetail_newsletter'] ) : '';
	return is_string( $marker ) && hash_equals( wp_hash( 'dovetail-newsletter:' . $form->id() ), $marker );
}

/** Runs only after CF7 validation, acceptance and spam checks have passed. */
function dovetail_newsletter_before_send( $form, &$abort, $submission ) {
	$settings = dovetail_newsletter_get_settings();
	if ( $abort || ! dovetail_newsletter_is_submission( $form ) || ! in_array( $settings['mode'], array( 'mailchimp', 'both' ), true ) ) {
		return;
	}
	$emails = array();
	foreach ( $form->scan_form_tags() as $tag ) {
		if ( 'email' === $tag->basetype && $tag->name ) {
			$emails[$tag->name] = $submission->get_posted_data( $tag->name );
		}
	}
	if ( 1 !== count( $emails ) || ! is_string( reset( $emails ) ) ) {
		$abort = true;
		$submission->set_response( __( 'Newsletter is not configured. The CF7 form must contain exactly one email field.', 'dovetail-newsletter' ) );
		return;
	}
	$result = dovetail_newsletter_subscribe( reset( $emails ) );
	if ( is_wp_error( $result ) ) {
		$abort = true;
		$submission->set_response( $result->get_error_message() );
		return;
	}
	$submission->add_result_props( array( 'dovetail_newsletter_message' => $result['message'] ) );
}
add_action( 'wpcf7_before_send_mail', 'dovetail_newsletter_before_send', 20, 3 );

function dovetail_newsletter_skip_mail( $skip, $form ) {
	return $skip || ( dovetail_newsletter_is_submission( $form ) && 'mailchimp' === dovetail_newsletter_get_settings()['mode'] );
}
add_filter( 'wpcf7_skip_mail', 'dovetail_newsletter_skip_mail', 20, 2 );

function dovetail_newsletter_submission_result( $result ) {
	if ( 'mail_sent' === ( $result['status'] ?? '' ) && isset( $result['dovetail_newsletter_message'] ) ) {
		$result['message'] = $result['dovetail_newsletter_message'];
	}
	if ( 'mail_failed' === ( $result['status'] ?? '' ) && isset( $result['dovetail_newsletter_message'] ) ) {
		$result['message'] = $result['dovetail_newsletter_message'] . ' ' . __( 'However, the form email could not be sent.', 'dovetail-newsletter' );
	}
	unset( $result['dovetail_newsletter_message'] );
	return $result;
}
add_filter( 'wpcf7_submission_result', 'dovetail_newsletter_submission_result' );

/**
 * Handles a newsletter subscription.
 *
 * @param string $email Validated CF7 email value.
 * @return array|WP_Error
 */
function dovetail_newsletter_subscribe( $email ) {
	$settings = dovetail_newsletter_get_settings();
	if ( ! in_array( $settings['mode'], array( 'mailchimp', 'both' ), true ) ) {
		return new WP_Error( 'dovetail_newsletter_disabled', __( 'Mailchimp integration is disabled.', 'dovetail-newsletter' ), array( 'status' => 503 ) );
	}

	$rate_key = 'dovetail_newsletter_' . md5( dovetail_newsletter_get_client_ip() );
	$attempts = (int) get_transient( $rate_key );

	if ( $attempts >= 5 ) {
		return new WP_Error(
			'dovetail_newsletter_rate_limited',
			__( 'Too many attempts. Please wait a few minutes and try again.', 'dovetail-newsletter' ),
			array( 'status' => 429 )
		);
	}

	set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

	$email    = sanitize_email( $email );
	$settings = dovetail_newsletter_get_settings();
	$api_key  = trim( (string) $settings['api_key'] );
	$list_id  = trim( (string) $settings['audience_id'] );

	if ( ! is_email( $email ) ) {
		return new WP_Error(
			'dovetail_newsletter_invalid_email',
			__( 'Please enter a valid email address.', 'dovetail-newsletter' ),
			array( 'status' => 400 )
		);
	}

	if ( '' === $api_key || '' === $list_id || ! str_contains( $api_key, '-' ) ) {
		return new WP_Error(
			'dovetail_newsletter_not_configured',
			__( 'Newsletter integration is not configured.', 'dovetail-newsletter' ),
			array( 'status' => 503 )
		);
	}

	$data_center = sanitize_key( substr( $api_key, strrpos( $api_key, '-' ) + 1 ) );
	$member_hash = md5( strtolower( $email ) );
	$status       = ! empty( $settings['double_opt_in'] ) ? 'pending' : 'subscribed';
	$url          = sprintf(
		'https://%1$s.api.mailchimp.com/3.0/lists/%2$s/members/%3$s',
		$data_center,
		rawurlencode( $list_id ),
		$member_hash
	);

	$response = wp_remote_request(
		$url,
		array(
			'method'  => 'PUT',
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'wordpress:' . $api_key ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'email_address' => $email,
					'status_if_new' => $status,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'dovetail_newsletter_connection_failed',
			__( 'We could not complete your subscription. Please try again.', 'dovetail-newsletter' ),
			array( 'status' => 502 )
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$error_message = isset( $body['title'] ) && 'Member Exists' === $body['title']
			? __( 'This email address is already subscribed.', 'dovetail-newsletter' )
			: __( 'We could not complete your subscription. Please try again.', 'dovetail-newsletter' );

		return new WP_Error(
			'dovetail_newsletter_api_error',
			$error_message,
			array( 'status' => 400 )
		);
	}

	$message = 'pending' === $status
		? __( 'Please check your email to confirm your subscription.', 'dovetail-newsletter' )
		: __( 'Thank you for subscribing.', 'dovetail-newsletter' );

	return array( 'message' => $message );
}

/**
 * Gets a minimally trusted address for rate limiting.
 *
 * @return string
 */
function dovetail_newsletter_get_client_ip() {
	return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
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
	$mode = $input['mode'] ?? '';
	if ( ! in_array( $mode, array( 'mailchimp', 'cf7', 'both' ), true ) ) {
		add_settings_error( 'dovetail_newsletter_settings', 'mode', __( 'Choose an integration first.', 'dovetail-newsletter' ) );
		return $current;
	}
	$shortcode = $input['cf7_shortcode'] ?? $current['cf7_shortcode'] ?? '';
	if ( ! is_string( $shortcode ) || ( '' !== trim( $shortcode ) && ! preg_match( '/^\[contact-form-7\s+[^\[\]<>]+\]$/D', trim( $shortcode ) ) ) ) {
		add_settings_error( 'dovetail_newsletter_settings', 'cf7_shortcode', __( 'Enter a valid Contact Form 7 shortcode, for example [contact-form-7 id="123" title="Newsletter"].', 'dovetail-newsletter' ) );
		return $current;
	}
	$shortcode = trim( sanitize_text_field( $shortcode ) );
	$mailchimp = in_array( $mode, array( 'mailchimp', 'both' ), true );
	$api_key = sanitize_text_field( $input['api_key'] ?? '' );

	if ( '' === $api_key ) {
		$api_key = $current['api_key'] ?? '';
	} elseif ( ! str_starts_with( $api_key, DOVETAIL_NEWSLETTER_ENCRYPTION_PREFIX ) ) {
		$encrypted_api_key = dovetail_newsletter_encrypt_api_key( $api_key );

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

	return array(
		'mode' => $mode,
		'cf7_shortcode' => $shortcode,
		'api_key'       => $api_key,
		'audience_id'   => sanitize_text_field( $input['audience_id'] ?? $current['audience_id'] ?? '' ),
		'double_opt_in' => $mailchimp ? ! empty( $input['double_opt_in'] ) : ( $current['double_opt_in'] ?? true ),
	);
}

/**
 * Adds the settings page.
 */
function dovetail_newsletter_admin_menu() {
	add_submenu_page(
		'dovetail-studio',
		__( 'Newsletter', 'dovetail-newsletter' ),
		__( 'Newsletter', 'dovetail-newsletter' ),
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

	$settings = dovetail_newsletter_get_settings();
	wp_enqueue_script( 'dovetail-newsletter-admin', DOVETAIL_NEWSLETTER_URL . 'assets/js/admin.js', array(), DOVETAIL_NEWSLETTER_VERSION, true );
	?>
	<div class="wrap dovetail-wrap">
		<div class="dovetail-page-header">
			<div>
				<h1><?php esc_html_e( 'Dovetail Newsletter', 'dovetail-newsletter' ); ?></h1>
				<p><?php esc_html_e( 'Configure how Contact Form 7 and Mailchimp handle newsletter subscriptions.', 'dovetail-newsletter' ); ?></p>
			</div>
			<span class="dovetail-version"><?php echo esc_html( 'v' . DOVETAIL_NEWSLETTER_VERSION ); ?></span>
		</div>

		<?php if ( ! shortcode_exists( 'contact-form-7' ) || ! class_exists( 'WPCF7_ContactForm' ) ) : ?>
			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'Dovetail Newsletter requires Contact Form 7.', 'dovetail-newsletter' ); ?></strong> <?php esc_html_e( 'Install and activate Contact Form 7 to render and process newsletter forms. The Newsletter module can remain enabled, but submissions cannot work until Contact Form 7 is active.', 'dovetail-newsletter' ); ?></p>
			</div>
		<?php endif; ?>

		<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Newsletter settings sections', 'dovetail-newsletter' ); ?>">
			<a href="#dovetail-newsletter-integration"><?php esc_html_e( 'Integration', 'dovetail-newsletter' ); ?></a>
			<a href="#dovetail-mailchimp"><?php esc_html_e( 'Mailchimp', 'dovetail-newsletter' ); ?></a>
			<a href="#dovetail-cf7"><?php esc_html_e( 'Contact Form 7', 'dovetail-newsletter' ); ?></a>
		</nav>

		<form action="options.php" method="post">
			<?php settings_fields( 'dovetail_newsletter' ); ?>

			<section class="dovetail-card" id="dovetail-newsletter-integration">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Integration', 'dovetail-newsletter' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Choose how newsletter submissions should be processed, then confirm the selection.', 'dovetail-newsletter' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dovetail-mode"><?php esc_html_e( 'Mode', 'dovetail-newsletter' ); ?></label></th>
						<td>
							<select id="dovetail-mode" name="dovetail_newsletter_settings[mode]" required>
							<?php foreach ( array( '' => 'Choose an integration', 'mailchimp' => 'Mailchimp', 'cf7' => 'Contact Form 7', 'both' => 'Both' ) as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['mode'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
							</select>
							<button type="button" class="button" id="dovetail-confirm"><?php esc_html_e( 'Confirm integration', 'dovetail-newsletter' ); ?></button>
						</td>
					</tr>
				</table>
			</section>

			<fieldset class="dovetail-card" id="dovetail-mailchimp" hidden disabled>
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Mailchimp', 'dovetail-newsletter' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Connect the site to the Mailchimp audience used for newsletter subscriptions.', 'dovetail-newsletter' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dovetail-newsletter-api-key"><?php esc_html_e( 'API key', 'dovetail-newsletter' ); ?></label></th>
						<td>
							<input id="dovetail-newsletter-api-key" class="regular-text" type="password" name="dovetail_newsletter_settings[api_key]" value="" autocomplete="new-password">
							<p class="description"><?php echo ! empty( $settings['api_key'] ) ? esc_html__( 'A key is saved. Leave blank to keep it.', 'dovetail-newsletter' ) : esc_html__( 'Enter the Mailchimp API key.', 'dovetail-newsletter' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dovetail-newsletter-audience-id"><?php esc_html_e( 'Audience ID', 'dovetail-newsletter' ); ?></label></th>
						<td><input id="dovetail-newsletter-audience-id" class="regular-text" type="text" name="dovetail_newsletter_settings[audience_id]" value="<?php echo esc_attr( $settings['audience_id'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Confirmation', 'dovetail-newsletter' ); ?></th>
						<td><label><input type="checkbox" name="dovetail_newsletter_settings[double_opt_in]" value="1" <?php checked( ! empty( $settings['double_opt_in'] ) ); ?>> <?php esc_html_e( 'Require email confirmation (recommended)', 'dovetail-newsletter' ); ?></label></td>
					</tr>
				</table>
			</fieldset>

			<fieldset class="dovetail-card" id="dovetail-cf7" hidden disabled>
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Contact Form 7', 'dovetail-newsletter' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Select the Contact Form 7 form used to collect newsletter subscriptions.', 'dovetail-newsletter' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dovetail-cf7-shortcode"><?php esc_html_e( 'CF7 shortcode', 'dovetail-newsletter' ); ?></label></th>
						<td>
							<input id="dovetail-cf7-shortcode" class="large-text" type="text" name="dovetail_newsletter_settings[cf7_shortcode]" value="<?php echo esc_attr( $settings['cf7_shortcode'] ); ?>" placeholder='[contact-form-7 id="123" title="Newsletter"]' aria-describedby="dovetail-cf7-help">
							<p class="description" id="dovetail-cf7-help"><?php esc_html_e( 'Paste the shortcode copied from Contact Form 7. This form is used for all three integration modes.', 'dovetail-newsletter' ); ?></p>
							<p><code>&lt;?php echo dovetail_newsletter_render(); ?&gt;</code></p>
							<p class="description"><?php esc_html_e( 'CF7 sends the normal form email. Mailchimp subscribes without sending the CF7 email. Both performs both actions. Mailchimp modes require exactly one CF7 email field.', 'dovetail-newsletter' ); ?></p>
							<?php if ( ! shortcode_exists( 'contact-form-7' ) ) : ?><p class="description"><strong><?php esc_html_e( 'Contact Form 7 must be active to display the newsletter form.', 'dovetail-newsletter' ); ?></strong></p><?php endif; ?>
						</td>
					</tr>
				</table>
			</fieldset>

			<div class="dovetail-save-bar">
				<?php submit_button( __( 'Save Newsletter Settings', 'dovetail-newsletter' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
	</div>
	<?php
}
