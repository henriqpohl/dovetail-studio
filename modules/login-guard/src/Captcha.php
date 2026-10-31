<?php
/**
 * Login Guard captcha on the login and registration forms.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Captcha as CoreCaptcha;
use DovetailStudio\Core\Http;
use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

/**
 * Google reCAPTCHA v3 or Cloudflare Turnstile on wp-login.php (sign-in and
 * registration) and the WooCommerce My Account forms.
 *
 * - The token is checked after the password, so XML-RPC, the REST API and
 *   sign-ins from other plugins' forms are left alone (they have no widget).
 * - reCAPTCHA scores below the threshold, with a correct password, get an
 *   email with a one-time link that finishes the sign-in (when enabled), as
 *   Wordfence does; other failures are refused and count as failed sign-ins.
 * - Test mode records results and scores without refusing anyone.
 * - When the provider cannot be reached the sign-in goes through; brute force
 *   protection still applies.
 */
final class Captcha {

	const SCORES_OPTION   = 'dovetail_login_guard_captcha_scores';
	const VERIFY_META     = 'dovetail_lg_captcha_verify';
	const VERIFY_ACTION   = 'dovetail_verify';
	const VERIFY_TTL      = 15 * MINUTE_IN_SECONDS;
	const SCRIPT_HANDLE   = 'dovetail-login-guard-recaptcha';
	const PROVIDER_HANDLE = 'dovetail-login-guard-captcha-api';

	/** @var array<string,array<string,mixed>> Verification results of this request, by action. */
	private $results = array();

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'login_form', array( $this, 'render_login' ) );
		add_action( 'register_form', array( $this, 'render_register' ) );
		add_action( 'woocommerce_login_form', array( $this, 'render_login' ) );
		add_action( 'woocommerce_register_form', array( $this, 'render_register' ) );
		add_filter( 'authenticate', array( $this, 'check_login' ), 21 );
		add_filter( 'registration_errors', array( $this, 'check_registration' ), 10, 1 );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'check_registration' ), 10, 1 );
		add_action( 'login_form_' . self::VERIFY_ACTION, array( $this, 'handle_verify_link' ) );
	}

	/**
	 * Whether the captcha is on and has its keys.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$settings = Settings::get();

		return ! empty( $settings['captcha_enabled'] ) && '' !== (string) $settings['captcha_site_key'] && '' !== Settings::captcha_secret();
	}

	/**
	 * Whether the visitor skips the captcha (allowlisted IP).
	 *
	 * @return bool
	 */
	private function exempt(): bool {
		$ip = Store::normalize_ip( Http::client_ip() );

		return '' !== $ip && Guard::is_allowlisted( $ip );
	}

	/**
	 * Widget on the sign-in forms.
	 *
	 * @return void
	 */
	public function render_login(): void {
		$this->render( 'login' );
	}

	/**
	 * Widget on the registration forms.
	 *
	 * @return void
	 */
	public function render_register(): void {
		$this->render( 'register' );
	}

	/**
	 * Print the widget and load the provider's script.
	 *
	 * @param string $action 'login' or 'register'.
	 * @return void
	 */
	private function render( string $action ): void {
		if ( ! self::enabled() || $this->exempt() ) {
			return;
		}

		$settings = Settings::get();
		$provider = (string) $settings['captcha_provider'];
		$site_key = (string) $settings['captcha_site_key'];

		wp_enqueue_script( self::PROVIDER_HANDLE, CoreCaptcha::script_url( $provider, $site_key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Provider script, versioned by the provider.

		// reCAPTCHA v3 has no widget: assets/js/recaptcha.js fills the field with a token.
		if ( 'turnstile' !== $provider ) {
			$asset = \DovetailStudio\Core\Loader::asset( dirname( __DIR__ ), 'assets/js/recaptcha.js' );

			wp_enqueue_script( self::SCRIPT_HANDLE, $asset['url'], array( self::PROVIDER_HANDLE ), $asset['ver'], true );
		}

		View::render(
			dirname( __DIR__ ),
			'captcha-field',
			array(
				'provider' => $provider,
				'site_key' => $site_key,
				'action'   => $action,
			)
		);
	}

	/**
	 * Whether this request is a form that shows the widget: wp-login.php or a WooCommerce My Account form.
	 *
	 * @param string $action 'login' or 'register'.
	 * @return bool
	 */
	private function is_form_request( string $action ): bool {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		$woo_nonce = 'login' === $action ? 'woocommerce-login-nonce' : 'woocommerce-register-nonce';

		return 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) || isset( $_POST[ $woo_nonce ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its nonce; this only detects the form.
	}

	/**
	 * Verify the posted token once per request and record the score.
	 *
	 * @param string $action 'login' or 'register'.
	 * @return array{reachable: bool, success: bool, score: float|null, errors: string[]}
	 */
	public function result( string $action ): array {
		if ( isset( $this->results[ $action ] ) ) {
			return $this->results[ $action ];
		}

		$settings = Settings::get();
		$provider = (string) $settings['captcha_provider'];
		$result   = CoreCaptcha::verify( $provider, Settings::captcha_secret(), CoreCaptcha::posted_token( $provider ), 'recaptcha' === $provider ? $action : '' );

		if ( null !== $result['score'] ) {
			self::record_score( (float) $result['score'] );
		}

		$this->results[ $action ] = $result;

		return $result;
	}

	/**
	 * What to do with a result: 'pass', 'verify' (low reCAPTCHA score) or 'fail'.
	 *
	 * @param array<string,mixed> $result   Verification result.
	 * @param string              $username Username, for the log.
	 * @return string
	 */
	private function decide( array $result, string $username ): string {
		$settings = Settings::get();

		if ( ! $result['reachable'] ) {
			return 'pass';
		}

		$score  = null === $result['score'] ? '' : number_format( (float) $result['score'], 1 );
		$low    = $result['success'] && null !== $result['score'] && (float) $result['score'] * 10 < (int) $settings['captcha_threshold'];
		$failed = ! $result['success'] || $low;

		if ( ! empty( $settings['captcha_test_mode'] ) ) {
			Activity::log( Activity::CAPTCHA_TEST, $username, 0, trim( sprintf( '%1$s %2$s', $failed ? 'failed' : 'passed', $score ) ) );

			return 'pass';
		}

		if ( ! $failed ) {
			return 'pass';
		}

		Activity::log( Activity::CAPTCHA_FAILED, $username, 0, trim( implode( ', ', (array) $result['errors'] ) . ' ' . $score ) );

		return $low ? 'verify' : 'fail';
	}

	/**
	 * Sign-in: check the token once the password has been accepted.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public function check_login( $user ) {
		if ( ! $user instanceof \WP_User || ! self::enabled() || $this->exempt() || ! $this->is_form_request( 'login' ) ) {
			return $user;
		}

		$decision = $this->decide( $this->result( 'login' ), (string) $user->user_login );

		if ( 'pass' === $decision ) {
			return $user;
		}

		if ( 'verify' === $decision && ! empty( Settings::get()['captcha_email_verify'] ) && $this->send_verify_link( $user ) ) {
			return new \WP_Error( 'dovetail_login_guard_captcha_verify', __( 'We could not confirm that you are a person, so we emailed you a link to finish signing in. It works once, within 15 minutes.', 'dovetail-studio' ) );
		}

		return new \WP_Error( 'dovetail_login_guard_captcha', __( 'The security check failed. Reload the page and try again.', 'dovetail-studio' ) );
	}

	/**
	 * Registration (wp-login.php and WooCommerce): refuse failed checks; there is no account to email yet.
	 *
	 * @param \WP_Error $errors Errors so far.
	 * @return \WP_Error
	 */
	public function check_registration( $errors ) {
		if ( ! $errors instanceof \WP_Error || ! self::enabled() || $this->exempt() || ! $this->is_form_request( 'register' ) ) {
			return $errors;
		}

		if ( 'pass' !== $this->decide( $this->result( 'register' ), '' ) ) {
			$errors->add( 'dovetail_login_guard_captcha', __( 'The security check failed. Reload the page and try again.', 'dovetail-studio' ) );
		}

		return $errors;
	}

	/**
	 * Count a reCAPTCHA score in the 0.0–1.0 histogram of the Captcha tab.
	 *
	 * @param float $score Score.
	 * @return void
	 */
	public static function record_score( float $score ): void {
		$scores = self::scores();
		$bucket = (int) round( max( 0, min( 1, $score ) ) * 10 );

		++$scores[ $bucket ];
		update_option( self::SCORES_OPTION, $scores, false );
	}

	/**
	 * Requests per score, 0.0 to 1.0.
	 *
	 * @return int[] Eleven counts.
	 */
	public static function scores(): array {
		$saved  = (array) get_option( self::SCORES_OPTION, array() );
		$scores = array();

		for ( $i = 0; $i <= 10; $i++ ) {
			$scores[ $i ] = (int) ( $saved[ $i ] ?? 0 );
		}

		return $scores;
	}

	/**
	 * Email a one-time link that finishes the sign-in. At most 3 per user per hour.
	 *
	 * @param \WP_User $user User whose password was accepted.
	 * @return bool Whether the email was sent.
	 */
	private function send_verify_link( $user ): bool {
		if ( ! Http::rate_limit( 'lg_verify', 3, HOUR_IN_SECONDS, 'user-' . (int) $user->ID ) ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Carried on from the sign-in form core verified.
		$remember = ! empty( $_POST['rememberme'] );
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		$token = wp_generate_password( 32, false, false );
		$link  = add_query_arg(
			array(
				'action' => self::VERIFY_ACTION,
				'uid'    => (int) $user->ID,
				'token'  => $token,
			),
			wp_login_url()
		);

		update_user_meta(
			(int) $user->ID,
			self::VERIFY_META,
			array(
				'hash'     => wp_hash( $token . '|dovetail-lg-verify' ),
				'expires'  => time() + self::VERIFY_TTL,
				'remember' => $remember,
				'redirect' => $redirect,
			)
		);

		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$body = array(
			/* translators: 1: site name, 2: username, 3: IP address. */
			sprintf( __( 'Someone signed in to "%1$s" as %2$s from the IP address %3$s, but the security check could not confirm that it was a person.', 'dovetail-studio' ), $site, $user->user_login, Store::normalize_ip( Http::client_ip() ) ),
			'',
			__( 'If it was you, open this link within 15 minutes to finish signing in:', 'dovetail-studio' ),
			$link,
			'',
			__( 'If it was not you, someone knows your password: change it as soon as possible.', 'dovetail-studio' ),
		);

		return wp_mail(
			(string) $user->user_email,
			/* translators: %s: site name. */
			sprintf( __( '[%s] Confirm your sign-in', 'dovetail-studio' ), $site ),
			implode( "\n", $body )
		);
	}

	/**
	 * The emailed link: finish the sign-in (through the two-factor step when the user has it).
	 *
	 * @return void
	 */
	public function handle_verify_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The token is the request's secret.
		$user_id = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_safe_redirect( $this->complete_verify( $user_id, $token ) );
		exit;
	}

	/**
	 * Check a verification link and sign the user in.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token from the link.
	 * @return string Where to send the browser.
	 */
	public function complete_verify( int $user_id, string $token ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;
		$data = $user ? get_user_meta( $user_id, self::VERIFY_META, true ) : null;

		if ( ! $user || ! is_array( $data ) || (int) ( $data['expires'] ?? 0 ) < time()
			|| ! hash_equals( (string) ( $data['hash'] ?? '' ), wp_hash( $token . '|dovetail-lg-verify' ) )
		) {
			return wp_login_url();
		}

		delete_user_meta( $user_id, self::VERIFY_META );

		$remember = ! empty( $data['remember'] );
		$redirect = (string) ( $data['redirect'] ?? '' );

		if ( TwoFactor::is_active( $user_id ) ) {
			return TwoFactor::begin( $user, $remember, $redirect );
		}

		wp_set_auth_cookie( $user_id, $remember );
		wp_set_current_user( $user_id );

		/** This action is documented in Guard::signed_in(). */
		do_action( 'dovetail_login_guard_signed_in', $user );

		return wp_validate_redirect( (string) apply_filters( 'login_redirect', '' !== $redirect ? $redirect : admin_url(), $redirect, $user ), admin_url() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
	}
}
