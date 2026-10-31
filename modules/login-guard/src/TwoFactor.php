<?php
/**
 * Login Guard two-factor authentication (authenticator app codes).
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Crypto;
use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

/**
 * Time-based one-time codes (RFC 6238: SHA-1, 6 digits, 30 seconds), as used
 * by Google Authenticator, 1Password, Authy and similar apps, plus ten
 * single-use recovery codes.
 *
 * Flow: the password is checked as usual; for a user with two-factor on, the
 * session WordPress just created is cleared at `wp_login` (priority 5) and
 * the browser is sent to `wp-login.php?action=dovetail_2fa` for the code. A
 * wrong code counts as a failed sign-in, so brute force protection applies.
 *
 * Each role is Off, Optional or Required (Two-factor tab). Users set it up in
 * their profile; a required user who has not done so is sent to the profile
 * after the grace period.
 */
final class TwoFactor {

	const SECRET_META   = 'dovetail_lg_2fa_secret';
	const PENDING_META  = 'dovetail_lg_2fa_pending';
	const RECOVERY_META = 'dovetail_lg_2fa_recovery';
	const LAST_META     = 'dovetail_lg_2fa_last';
	const DEVICES_META  = 'dovetail_lg_2fa_devices';
	const LOGIN_META    = 'dovetail_lg_2fa_login';
	const SINCE_META    = 'dovetail_lg_2fa_required_since';
	const DEVICE_COOKIE = 'dovetail_lg_device';
	const ACTION        = 'dovetail_2fa';

	/** Seconds the code screen stays valid after the password. */
	const LOGIN_TTL = 600;

	/** Number of recovery codes. */
	const RECOVERY_COUNT = 10;

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_login', array( $this, 'intercept' ), 5, 2 );
		add_action( 'login_form_' . self::ACTION, array( $this, 'handle_login_form' ) );
		add_filter( 'authenticate', array( $this, 'block_xmlrpc' ), 50 );
		add_action( 'show_user_profile', array( $this, 'render_profile' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile' ) );
		add_action( 'user_profile_update_errors', array( $this, 'save_profile' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'enforce_required' ) );
		add_action( 'admin_notices', array( $this, 'required_notice' ) );
		add_action( 'dovetail_studio_notices', array( $this, 'required_notice' ) );
	}

	// ------------------------------------------------------------------ codes

	/**
	 * Encode bytes as base32 (RFC 4648, no padding), the format authenticator apps expect.
	 *
	 * @param string $bytes Raw bytes.
	 * @return string
	 */
	public static function base32_encode( string $bytes ): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits     = '';
		$out      = '';

		foreach ( str_split( $bytes ) as $char ) {
			$bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}

		foreach ( str_split( $bits, 5 ) as $chunk ) {
			$out .= $alphabet[ bindec( str_pad( $chunk, 5, '0' ) ) ];
		}

		return $out;
	}

	/**
	 * Decode base32 (RFC 4648, padding and spaces ignored).
	 *
	 * @param string $text Base32 text.
	 * @return string Raw bytes.
	 */
	public static function base32_decode( string $text ): string {
		$text = strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $text ) );
		$bits = '';
		$out  = '';

		foreach ( str_split( $text ) as $char ) {
			$bits .= str_pad( decbin( (int) strpos( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char ) ), 5, '0', STR_PAD_LEFT );
		}

		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$out .= chr( bindec( $byte ) );
			}
		}

		return $out;
	}

	/**
	 * The 6-digit code of a secret for a 30-second time step.
	 *
	 * @param string $secret Base32 secret.
	 * @param int    $step   Time step (Unix time / 30).
	 * @return string
	 */
	public static function code( string $secret, int $step ): string {
		$hash   = hash_hmac( 'sha1', pack( 'N2', 0, $step ), self::base32_decode( $secret ), true );
		$offset = ord( $hash[19] ) & 0x0F;
		$value  = ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 )
			| ( ord( $hash[ $offset + 1 ] ) << 16 )
			| ( ord( $hash[ $offset + 2 ] ) << 8 )
			| ord( $hash[ $offset + 3 ] );

		return str_pad( (string) ( $value % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Check a code against a secret, allowing one step of clock drift either way.
	 *
	 * @param string $secret    Base32 secret.
	 * @param string $code      Code typed by the user.
	 * @param int    $last_step Last step accepted for this user; a code is never accepted twice.
	 * @return int The matching step, or 0.
	 */
	public static function match( string $secret, string $code, int $last_step = 0 ): int {
		$code = preg_replace( '/\D/', '', $code );

		if ( 6 !== strlen( $code ) || '' === $secret ) {
			return 0;
		}

		$now = (int) floor( time() / 30 );

		for ( $step = $now - 1; $step <= $now + 1; $step++ ) {
			if ( $step > $last_step && hash_equals( self::code( $secret, $step ), $code ) ) {
				return $step;
			}
		}

		return 0;
	}

	/**
	 * A new random secret (160 bits).
	 *
	 * @return string Base32.
	 */
	public static function new_secret(): string {
		return self::base32_encode( random_bytes( 20 ) );
	}

	/**
	 * Hash of a recovery code, independent of case, spaces and dashes.
	 *
	 * @param string $code Recovery code.
	 * @return string
	 */
	private static function recovery_hash( string $code ): string {
		return hash_hmac( 'sha256', strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $code ) ), wp_salt( 'auth' ) . '|dovetail-lg-recovery' );
	}

	/**
	 * Create new recovery codes for a user, replacing the old ones.
	 *
	 * @param int $user_id User ID.
	 * @return string[] The codes in clear text, shown once.
	 */
	public static function new_recovery_codes( int $user_id ): array {
		$codes  = array();
		$hashes = array();

		for ( $i = 0; $i < self::RECOVERY_COUNT; $i++ ) {
			$raw      = substr( self::base32_encode( random_bytes( 7 ) ), 0, 10 );
			$codes[]  = substr( $raw, 0, 5 ) . '-' . substr( $raw, 5 );
			$hashes[] = self::recovery_hash( $raw );
		}

		update_user_meta( $user_id, self::RECOVERY_META, $hashes );

		return $codes;
	}

	/**
	 * Use a recovery code: true once, then the code is gone.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Code typed by the user.
	 * @return bool
	 */
	public static function use_recovery_code( int $user_id, string $code ): bool {
		$hashes = array_values( (array) get_user_meta( $user_id, self::RECOVERY_META, true ) );
		$hash   = self::recovery_hash( $code );

		foreach ( $hashes as $i => $stored ) {
			if ( is_string( $stored ) && hash_equals( $stored, $hash ) ) {
				unset( $hashes[ $i ] );
				update_user_meta( $user_id, self::RECOVERY_META, array_values( $hashes ) );

				return true;
			}
		}

		return false;
	}

	/**
	 * Recovery codes a user has left.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function recovery_left( int $user_id ): int {
		return count( array_filter( (array) get_user_meta( $user_id, self::RECOVERY_META, true ) ) );
	}

	// ------------------------------------------------------------------ user state

	/**
	 * Two-factor mode of a user: the strictest mode among their roles.
	 *
	 * @param \WP_User $user User.
	 * @return string 'off', 'optional' or 'required'.
	 */
	public static function mode_for( $user ): string {
		$modes = (array) Settings::get()['two_factor_roles'];
		$mode  = 'off';

		foreach ( (array) ( $user->roles ?? array() ) as $role ) {
			$role_mode = (string) ( $modes[ $role ] ?? 'off' );

			if ( 'required' === $role_mode ) {
				return 'required';
			}

			if ( 'optional' === $role_mode ) {
				$mode = 'optional';
			}
		}

		return $mode;
	}

	/**
	 * The user's secret in clear text, or '' when two-factor is off for them.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function secret( int $user_id ): string {
		return Crypto::decrypt( (string) get_user_meta( $user_id, self::SECRET_META, true ), Settings::CRYPTO_CONTEXT );
	}

	/**
	 * Whether a user has two-factor authentication set up.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_active( int $user_id ): bool {
		return '' !== self::secret( $user_id );
	}

	/**
	 * Check a code typed at sign-in: an app code first, then a recovery code.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Code.
	 * @return bool
	 */
	public static function verify( int $user_id, string $code ): bool {
		$step = self::match( self::secret( $user_id ), $code, (int) get_user_meta( $user_id, self::LAST_META, true ) );

		if ( $step > 0 ) {
			update_user_meta( $user_id, self::LAST_META, $step );

			return true;
		}

		return strlen( preg_replace( '/[^A-Za-z0-9]/', '', $code ) ) === 10 && self::use_recovery_code( $user_id, $code );
	}

	/**
	 * Turn two-factor off for a user and forget their trusted devices and recovery codes.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function reset( int $user_id ): void {
		foreach ( array( self::SECRET_META, self::PENDING_META, self::RECOVERY_META, self::LAST_META, self::DEVICES_META, self::LOGIN_META ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
	}

	/**
	 * Whether this browser is a trusted device of the user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function trusted_device( int $user_id ): bool {
		$cookie = isset( $_COOKIE[ self::DEVICE_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE_COOKIE ] ) ) : '';
		$parts  = explode( ':', $cookie, 2 );

		if ( (int) Settings::get()['two_factor_remember_days'] <= 0 || 2 !== count( $parts ) || (int) $parts[0] !== $user_id ) {
			return false;
		}

		$devices = (array) get_user_meta( $user_id, self::DEVICES_META, true );
		$hash    = wp_hash( $parts[1] . '|dovetail-lg-device' );

		return isset( $devices[ $hash ] ) && (int) $devices[ $hash ] > time();
	}

	/**
	 * Remember this browser for the configured number of days.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function trust_device( int $user_id ): void {
		$days = (int) Settings::get()['two_factor_remember_days'];

		if ( $days <= 0 ) {
			return;
		}

		$token   = wp_generate_password( 32, false, false );
		$expires = time() + $days * DAY_IN_SECONDS;
		$devices = array_filter(
			(array) get_user_meta( $user_id, self::DEVICES_META, true ),
			/**
			 * Keep devices whose trust has not expired.
			 *
			 * @param mixed $until Expiry time.
			 * @return bool
			 */
			static function ( $until ) {
				return (int) $until > time();
			}
		);

		$devices[ wp_hash( $token . '|dovetail-lg-device' ) ] = $expires;
		update_user_meta( $user_id, self::DEVICES_META, array_slice( $devices, -10, null, true ) );

		if ( ! headers_sent() ) {
			setcookie(
				self::DEVICE_COOKIE,
				$user_id . ':' . $token,
				array(
					'expires'  => $expires,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	// ------------------------------------------------------------------ sign-in

	/**
	 * After the password: stop the sign-in of a user with two-factor on and ask for the code.
	 *
	 * @param string   $login Username.
	 * @param \WP_User $user  User.
	 * @return void
	 */
	public function intercept( $login, $user = null ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checks whether a password was typed.
		$typed = ! empty( $_POST['pwd'] ) || ! empty( $_POST['password'] );

		// Without a typed password the session was renewed from an existing cookie, which already passed the code.
		if ( ! $typed || ! $user instanceof \WP_User || ! self::is_active( (int) $user->ID ) || $this->trusted_device( (int) $user->ID ) ) {
			return;
		}

		wp_clear_auth_cookie();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core verified the sign-in; these only carry the user's choices on.
		$remember = ! empty( $_POST['rememberme'] );
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		wp_safe_redirect( self::begin( $user, $remember, $redirect ) );
		exit;
	}

	/**
	 * Start the code step for a user whose password (or emailed link) was accepted.
	 *
	 * @param \WP_User $user     User.
	 * @param bool     $remember Whether the session should be remembered.
	 * @param string   $redirect Where to go after the code.
	 * @return string URL of the code screen.
	 */
	public static function begin( $user, bool $remember, string $redirect ): string {
		$token = wp_generate_password( 32, false, false );

		update_user_meta(
			(int) $user->ID,
			self::LOGIN_META,
			array(
				'hash'     => wp_hash( $token . '|dovetail-lg-login' ),
				'expires'  => time() + self::LOGIN_TTL,
				'remember' => $remember,
				'redirect' => $redirect,
			)
		);

		return self::form_url( (int) $user->ID, $token );
	}

	/**
	 * XML-RPC has no place for the code, so users with two-factor on cannot sign in through it.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public function block_xmlrpc( $user ) {
		if ( $user instanceof \WP_User && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && self::is_active( (int) $user->ID ) ) {
			return new \WP_Error( 'dovetail_login_guard_2fa_xmlrpc', __( 'Accounts with two-factor authentication cannot sign in through XML-RPC.', 'dovetail-studio' ) );
		}

		return $user;
	}

	/**
	 * URL of the code screen.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Sign-in token.
	 * @return string
	 */
	public static function form_url( int $user_id, string $token ): string {
		return add_query_arg(
			array(
				'action' => self::ACTION,
				'uid'    => $user_id,
				'token'  => $token,
			),
			wp_login_url()
		);
	}

	/**
	 * The pending sign-in of a user, when the token matches and has not expired.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token.
	 * @return array<string,mixed>|null
	 */
	public static function pending_login( int $user_id, string $token ): ?array {
		$data = get_user_meta( $user_id, self::LOGIN_META, true );

		if ( ! is_array( $data ) || empty( $data['hash'] ) || (int) $data['expires'] < time()
			|| ! hash_equals( (string) $data['hash'], wp_hash( $token . '|dovetail-lg-login' ) )
		) {
			return null;
		}

		return $data;
	}

	/**
	 * wp-login.php?action=dovetail_2fa: show the code screen (GET) or check the code (POST).
	 *
	 * @return void
	 */
	public function handle_login_form(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- The sign-in token is the request's secret.
		$user_id = isset( $_REQUEST['uid'] ) ? absint( $_REQUEST['uid'] ) : 0;
		$token   = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		$code    = isset( $_POST['dovetail_lg_code'] ) ? sanitize_text_field( wp_unslash( $_POST['dovetail_lg_code'] ) ) : '';
		$trust   = ! empty( $_POST['dovetail_lg_trust'] );
		$posted  = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
		// phpcs:enable WordPress.Security.NonceVerification
		$user  = $user_id ? get_userdata( $user_id ) : false;
		$login = $user ? self::pending_login( $user_id, $token ) : null;

		if ( ! $user || ! $login ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$error = '';

		if ( $posted ) {
			$result = $this->complete( $user, $login, $code, $trust );

			if ( ! is_wp_error( $result ) ) {
				wp_safe_redirect( $result );
				exit;
			}

			$error = $result->get_error_message();
		}

		$this->render_form( $user_id, $token, $error );
		exit;
	}

	/**
	 * Check the code and finish the sign-in.
	 *
	 * @param \WP_User            $user  User.
	 * @param array<string,mixed> $login Pending sign-in.
	 * @param string              $code  Code typed.
	 * @param bool                $trust Whether to remember this device.
	 * @return string|\WP_Error Where to go next, or why the code was refused.
	 */
	public function complete( $user, array $login, string $code, bool $trust ) {
		if ( ! self::verify( (int) $user->ID, $code ) ) {
			Activity::log( Activity::TWOFA_FAILED, (string) $user->user_login, (int) $user->ID );

			/** This action is documented in wp-includes/pluggable.php. */
			do_action( 'wp_login_failed', (string) $user->user_login, new \WP_Error( 'dovetail_login_guard_2fa_invalid', '' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core action.

			return new \WP_Error( 'dovetail_login_guard_2fa_invalid', __( 'That code is not valid. Check that the time on your phone is correct and try again.', 'dovetail-studio' ) );
		}

		delete_user_meta( (int) $user->ID, self::LOGIN_META );

		if ( $trust ) {
			$this->trust_device( (int) $user->ID );
		}

		wp_set_auth_cookie( (int) $user->ID, ! empty( $login['remember'] ) );
		wp_set_current_user( (int) $user->ID );
		Activity::log( Activity::TWOFA_SUCCESS, (string) $user->user_login, (int) $user->ID );

		/** This action is documented in Guard::signed_in(). */
		do_action( 'dovetail_login_guard_signed_in', $user );

		$requested = (string) ( $login['redirect'] ?? '' );
		$redirect  = (string) apply_filters( 'login_redirect', '' !== $requested ? $requested : admin_url(), $requested, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.

		return wp_validate_redirect( $redirect, admin_url() );
	}

	/**
	 * The code screen, in the wp-login.php layout.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Sign-in token.
	 * @param string $error   Error message.
	 * @return void
	 */
	private function render_form( int $user_id, string $token, string $error ): void {
		$errors = new \WP_Error();

		if ( '' !== $error ) {
			$errors->add( 'dovetail_login_guard_2fa', esc_html( $error ) );
		}

		login_header( __( 'Two-factor authentication', 'dovetail-studio' ), '', $errors );
		View::render(
			dirname( __DIR__ ),
			'two-factor-form',
			array(
				'action'        => self::form_url( $user_id, $token ),
				'remember_days' => (int) Settings::get()['two_factor_remember_days'],
			)
		);
		login_footer( 'dovetail-lg-code' );
	}

	// ------------------------------------------------------------------ required

	/**
	 * When the user's role requires two-factor and they have not set it up: the end of the grace period.
	 *
	 * @param \WP_User $user User.
	 * @return int Unix time, or 0 when nothing is required.
	 */
	public static function deadline( $user ): int {
		if ( ! $user instanceof \WP_User || ! $user->ID || 'required' !== self::mode_for( $user ) || self::is_active( (int) $user->ID ) ) {
			return 0;
		}

		$since = (int) get_user_meta( (int) $user->ID, self::SINCE_META, true );

		if ( 0 === $since ) {
			$since = time();
			update_user_meta( (int) $user->ID, self::SINCE_META, $since );
		}

		return $since + (int) Settings::get()['two_factor_grace_days'] * DAY_IN_SECONDS;
	}

	/**
	 * After the grace period, keep the user on their profile until two-factor is set up.
	 *
	 * @return void
	 */
	public function enforce_required(): void {
		$deadline = self::deadline( wp_get_current_user() );

		if ( 0 === $deadline || $deadline > time() || wp_doing_ajax() || 'profile.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'profile.php#dovetail-lg-2fa' ) );
		exit;
	}

	/**
	 * Notice while a required set-up is pending.
	 *
	 * @return void
	 */
	public function required_notice(): void {
		static $printed = false;

		$deadline = self::deadline( wp_get_current_user() );

		if ( $printed || 0 === $deadline ) {
			return;
		}

		$printed = true;
		$link    = '<a href="' . esc_url( admin_url( 'profile.php#dovetail-lg-2fa' ) ) . '">' . esc_html__( 'Set it up in your profile', 'dovetail-studio' ) . '</a>';
		$text    = $deadline > time()
			/* translators: %s: date. */
			? sprintf( esc_html__( 'Your role requires two-factor authentication. Set it up before %s or you will only be able to open your profile.', 'dovetail-studio' ), esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ), $deadline ) ) )
			: esc_html__( 'Your role requires two-factor authentication. Set it up to use the rest of WP Admin.', 'dovetail-studio' );

		\DovetailStudio\Core\AdminScreens::notice( 'warning', $text . ' ' . $link );
	}

	// ------------------------------------------------------------------ profile

	/**
	 * otpauth:// URI for the authenticator app.
	 *
	 * @param \WP_User $user   User.
	 * @param string   $secret Base32 secret.
	 * @return string
	 */
	public static function otpauth_uri( $user, string $secret ): string {
		$issuer = mb_substr( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), 0, 30 );
		$issuer = '' !== $issuer ? $issuer : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$label  = rawurlencode( $issuer ) . ':' . rawurlencode( mb_substr( (string) $user->user_login, 0, 40 ) );

		return 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode( $issuer );
	}

	/**
	 * Recovery codes generated in the last save, shown once.
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	private function take_fresh_codes( int $user_id ): array {
		$stored = get_transient( 'dovetail_lg_2fa_codes_' . $user_id );

		if ( false === $stored ) {
			return array();
		}

		delete_transient( 'dovetail_lg_2fa_codes_' . $user_id );
		$codes = json_decode( Crypto::decrypt( (string) $stored, Settings::CRYPTO_CONTEXT ), true );

		return is_array( $codes ) ? array_map( 'strval', $codes ) : array();
	}

	/**
	 * Keep new recovery codes for the next page load, encrypted, for 10 minutes.
	 *
	 * @param int      $user_id User ID.
	 * @param string[] $codes   Codes.
	 * @return void
	 */
	private function keep_fresh_codes( int $user_id, array $codes ): void {
		$encrypted = Crypto::encrypt( (string) wp_json_encode( $codes ), Settings::CRYPTO_CONTEXT );

		if ( ! is_wp_error( $encrypted ) ) {
			set_transient( 'dovetail_lg_2fa_codes_' . $user_id, $encrypted, 10 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * The two-factor section of the profile screen.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public function render_profile( $user ): void {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$user_id = (int) $user->ID;
		$own     = get_current_user_id() === $user_id;
		$active  = self::is_active( $user_id );
		$mode    = self::mode_for( $user );

		if ( ! $active && 'off' === $mode ) {
			return;
		}

		$pending = '';
		$qr      = '';

		// A user setting it up sees a new secret, kept until the code from the app confirms it.
		if ( ! $active && $own ) {
			$pending = Crypto::decrypt( (string) get_user_meta( $user_id, self::PENDING_META, true ), Settings::CRYPTO_CONTEXT );

			if ( '' === $pending ) {
				$pending   = self::new_secret();
				$encrypted = Crypto::encrypt( $pending, Settings::CRYPTO_CONTEXT );

				if ( ! is_wp_error( $encrypted ) ) {
					update_user_meta( $user_id, self::PENDING_META, $encrypted );
				}
			}

			try {
				$qr = QrCode::svg( self::otpauth_uri( $user, $pending ), __( 'QR code to scan with your authenticator app', 'dovetail-studio' ), 4 );
			} catch ( \InvalidArgumentException $e ) {
				$qr = '';
			}
		}

		View::render(
			dirname( __DIR__ ),
			'two-factor-profile',
			array(
				'own'           => $own,
				'active'        => $active,
				'mode'          => $mode,
				'codes'         => $own ? $this->take_fresh_codes( $user_id ) : array(),
				'recovery_left' => $active ? self::recovery_left( $user_id ) : 0,
				'can_reset'     => current_user_can( 'edit_user', $user_id ),
				'pending'       => $pending,
				'qr'            => $qr,
				'deadline'      => ! $active && $own ? self::deadline( $user ) : 0,
				'date_format'   => (string) get_option( 'date_format', 'Y-m-d' ),
			)
		);
	}

	/**
	 * Save the two-factor section of the profile screen (WordPress checked the nonce and edit_user).
	 *
	 * @param \WP_Error $errors Errors.
	 * @param bool      $update Whether an existing user is saved.
	 * @param object    $user   User being saved.
	 * @return void
	 */
	public function save_profile( $errors, $update, $user ): void {
		$user_id = (int) ( $user->ID ?? 0 );

		if ( ! $update || ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by WordPress before user_profile_update_errors.
		$own  = get_current_user_id() === $user_id;
		$code = isset( $_POST['dovetail_lg_setup_code'] ) ? sanitize_text_field( wp_unslash( $_POST['dovetail_lg_setup_code'] ) ) : '';

		if ( ! $own ) {
			if ( ! empty( $_POST['dovetail_lg_reset'] ) ) {
				self::reset( $user_id );
			}

			return;
		}

		if ( self::is_active( $user_id ) ) {
			if ( ! empty( $_POST['dovetail_lg_disable'] ) && 'required' !== self::mode_for( get_userdata( $user_id ) ) ) {
				self::reset( $user_id );
			} elseif ( ! empty( $_POST['dovetail_lg_regenerate'] ) ) {
				$this->keep_fresh_codes( $user_id, self::new_recovery_codes( $user_id ) );
			}

			return;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $code ) {
			return;
		}

		$pending = Crypto::decrypt( (string) get_user_meta( $user_id, self::PENDING_META, true ), Settings::CRYPTO_CONTEXT );
		$step    = self::match( $pending, $code );

		if ( 0 === $step ) {
			if ( $errors instanceof \WP_Error ) {
				$errors->add( 'dovetail_lg_2fa_code', __( 'Two-factor authentication: the code from the app is not valid. Check the time on your phone and try again.', 'dovetail-studio' ), array( 'form-field' => 'dovetail_lg_setup_code' ) );
			}

			return;
		}

		update_user_meta( $user_id, self::SECRET_META, get_user_meta( $user_id, self::PENDING_META, true ) );
		update_user_meta( $user_id, self::LAST_META, $step );
		delete_user_meta( $user_id, self::PENDING_META );
		delete_user_meta( $user_id, self::SINCE_META );
		$this->keep_fresh_codes( $user_id, self::new_recovery_codes( $user_id ) );
	}
}
