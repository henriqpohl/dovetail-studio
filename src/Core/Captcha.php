<?php
/**
 * Shared captcha verification (Google reCAPTCHA v3 and Cloudflare Turnstile).
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a captcha token with the provider's server. Modules decide what to
 * do with the result: Login Guard lets sign-ins through when the provider
 * cannot be reached, the Virtual Assistant refuses the enquiry.
 */
final class Captcha {

	/** Verification endpoints. */
	const VERIFY_URLS = array(
		'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
		'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
	);

	/** Browser scripts. */
	const SCRIPT_URLS = array(
		'recaptcha' => 'https://www.google.com/recaptcha/api.js',
		'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
	);

	/** Fields the widgets post the token in. */
	const TOKEN_FIELDS = array(
		'recaptcha' => 'g-recaptcha-response',
		'turnstile' => 'cf-turnstile-response',
	);

	/**
	 * Verify a token.
	 *
	 * @param string $provider 'recaptcha' or 'turnstile'.
	 * @param string $secret   Secret key.
	 * @param string $token    Token posted by the browser.
	 * @param string $action   Expected reCAPTCHA action; '' to skip the check.
	 * @return array{reachable: bool, success: bool, score: float|null, errors: string[]}
	 *         `reachable` is false when the provider could not be asked (network error, HTTP error).
	 */
	public static function verify( string $provider, string $secret, string $token, string $action = '' ): array {
		$result = array(
			'reachable' => true,
			'success'   => false,
			'score'     => null,
			'errors'    => array(),
		);

		if ( ! isset( self::VERIFY_URLS[ $provider ] ) || '' === $secret ) {
			$result['errors'][] = 'not-configured';

			return $result;
		}

		if ( '' === $token ) {
			$result['errors'][] = 'missing-input-response';

			return $result;
		}

		$response = wp_remote_post(
			self::VERIFY_URLS[ $provider ],
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => Http::client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$result['reachable'] = false;
			$result['errors'][]  = 'unreachable';

			return $result;
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$json = is_array( $json ) ? $json : array();

		$result['success'] = ! empty( $json['success'] );
		$result['score']   = isset( $json['score'] ) ? (float) $json['score'] : null;
		$result['errors']  = array_map( 'strval', (array) ( $json['error-codes'] ?? array() ) );

		if ( $result['success'] && '' !== $action && isset( $json['action'] ) && $action !== $json['action'] ) {
			$result['success']  = false;
			$result['errors'][] = 'action-mismatch';
		}

		return $result;
	}

	/**
	 * URL of the provider's browser script.
	 *
	 * @param string $provider 'recaptcha' or 'turnstile'.
	 * @param string $site_key Site key (reCAPTCHA v3 loads with it).
	 * @return string
	 */
	public static function script_url( string $provider, string $site_key ): string {
		if ( 'recaptcha' === $provider ) {
			return add_query_arg( 'render', rawurlencode( $site_key ), self::SCRIPT_URLS['recaptcha'] );
		}

		return self::SCRIPT_URLS['turnstile'];
	}

	/**
	 * The token the browser posted for a provider.
	 *
	 * @param string $provider 'recaptcha' or 'turnstile'.
	 * @return string
	 */
	public static function posted_token( string $provider ): string {
		$field = self::TOKEN_FIELDS[ $provider ] ?? '';

		return '' !== $field && isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The token is the verification.
	}
}
