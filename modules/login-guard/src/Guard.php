<?php
/**
 * Login Guard enforcement: brute force lockouts and IP blocks.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Counts failed sign-ins and password reset requests per IP address, locks out
 * IPs that go over the limits and keeps blocked IPs off the site.
 *
 * A lockout stops sign-in and password resets (wp-login.php, XML-RPC and any
 * form that signs in through wp_signon(), such as WooCommerce's). A block
 * stops every front-end and admin request from that IP.
 *
 * Nothing is enforced for WP-CLI, WP-Cron, allowlisted IPs or when
 * DOVETAIL_LOGIN_GUARD_DISABLED is true in wp-config.php (the way back in
 * after locking yourself out).
 */
final class Guard {

	/** Error codes Login Guard itself returns; they never count as failures. */
	const OWN_ERRORS = array( 'dovetail_login_guard_locked', 'dovetail_login_guard_leaked', 'dovetail_login_guard_captcha_verify' );

	/** @var Guard|null */
	private static $instance = null;

	/**
	 * The shared instance.
	 *
	 * @return Guard
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( self::disabled() ) {
			return;
		}

		// After init 0, so the block page can be translated.
		add_action( 'init', array( $this, 'enforce_site' ), 1 );
		add_action( 'login_init', array( $this, 'enforce_login' ), 1 );
		add_filter( 'authenticate', array( $this, 'authenticate' ), 100 );
		add_action( 'wp_login_failed', array( $this, 'login_failed' ), 10, 2 );
		add_action( 'wp_login', array( $this, 'signed_in' ), 20, 2 );
		add_action( 'dovetail_login_guard_signed_in', array( $this, 'login_succeeded' ) );
		add_action( 'lostpassword_post', array( $this, 'lostpassword' ), 10, 2 );
	}

	/**
	 * Whether enforcement is switched off in wp-config.php.
	 *
	 * @return bool
	 */
	public static function disabled(): bool {
		return defined( 'DOVETAIL_LOGIN_GUARD_DISABLED' ) && DOVETAIL_LOGIN_GUARD_DISABLED;
	}

	/**
	 * Whether an IP address is on the allowlist.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_allowlisted( string $ip ): bool {
		/**
		 * Filters the IP addresses and CIDR ranges Login Guard never locks out or blocks.
		 *
		 * @param string[] $allowlist Addresses or ranges from the settings.
		 */
		$allowlist = (array) apply_filters( 'dovetail_login_guard_allowlist', (array) Settings::get()['allowlist'] );

		foreach ( $allowlist as $range ) {
			if ( is_string( $range ) && Http::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The current visitor's IP when rules may apply to it, or '' when the request is exempt.
	 *
	 * @return string
	 */
	private function subject_ip(): string {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return '';
		}

		$ip = Store::normalize_ip( Http::client_ip() );

		return ( '' === $ip || self::is_allowlisted( $ip ) ) ? '' : $ip;
	}

	/**
	 * Every request: block POSTs without User-Agent and Referer, then keep blocked IPs out.
	 *
	 * @return void
	 */
	public function enforce_site(): void {
		$ip = $this->subject_ip();

		if ( '' === $ip ) {
			return;
		}

		if ( $this->is_blank_post( $ip ) ) {
			Store::add(
				$ip,
				Store::KIND_BLOCK,
				'blank_ua',
				__( 'POST request with blank User-Agent and Referer', 'dovetail-studio' ),
				(int) Settings::get()['lockout_duration']
			);
		}

		if ( ! Store::may_have_site_blocks() ) {
			return;
		}

		$row = Store::active( $ip );

		if ( $row && Store::KIND_BLOCK === $row['kind'] ) {
			Store::record_hit( (int) $row['id'] );
			$this->deny( $ip, __( 'Your access to this site has been limited', 'dovetail-studio' ) );
		}
	}

	/**
	 * Whether the request is a POST without User-Agent and Referer from another machine.
	 *
	 * @param string $ip Visitor IP.
	 * @return bool
	 */
	private function is_blank_post( string $ip ): bool {
		if ( empty( Settings::get()['block_blank_ua_post'] )
			|| ! isset( $_SERVER['REQUEST_METHOD'] )
			|| 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			|| ! empty( $_SERVER['HTTP_USER_AGENT'] )
			|| ! empty( $_SERVER['HTTP_REFERER'] )
		) {
			return false;
		}

		// The server calling itself (loopback requests) is never blocked.
		$server = isset( $_SERVER['SERVER_ADDR'] ) ? Store::normalize_ip( sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) ) : '';

		return $ip !== $server && '127.0.0.1' !== $ip && '::1' !== $ip;
	}

	/**
	 * wp-login.php: locked out and blocked IPs see the block page instead of the form.
	 *
	 * @return void
	 */
	public function enforce_login(): void {
		$ip  = $this->subject_ip();
		$row = '' === $ip ? null : Store::active( $ip );

		if ( $row ) {
			Store::record_hit( (int) $row['id'] );
			$this->deny( $ip, __( 'Sign-in has been temporarily limited', 'dovetail-studio' ) );
		}
	}

	/**
	 * Sign-ins outside wp-login.php (XML-RPC, wp_signon() forms): refuse locked out IPs.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public function authenticate( $user ) {
		$ip  = $this->subject_ip();
		$row = '' === $ip ? null : Store::active( $ip );

		if ( ! $row ) {
			return $user;
		}

		Store::record_hit( (int) $row['id'] );

		return new \WP_Error( 'dovetail_login_guard_locked', self::locked_message() );
	}

	/**
	 * Count a failed sign-in and lock out or block the IP when a rule is broken.
	 *
	 * @param string         $username Username or email tried.
	 * @param \WP_Error|null $error    Why it failed.
	 * @return void
	 */
	public function login_failed( $username, $error = null ): void {
		$settings = Settings::get();
		$code     = $error instanceof \WP_Error ? (string) $error->get_error_code() : '';
		$ip       = $this->subject_ip();

		if ( '' === $ip || empty( $settings['brute_force'] ) || in_array( $code, self::OWN_ERRORS, true ) ) {
			return;
		}

		$username = strtolower( trim( (string) $username ) );
		$duration = (int) $settings['lockout_duration'];

		// Usernames bots try: block the IP right away, unless a real account has that name.
		if ( '' !== $username
			&& in_array( $username, (array) $settings['blocked_usernames'], true )
			&& ! username_exists( $username )
		) {
			Store::add(
				$ip,
				Store::KIND_BLOCK,
				'username',
				/* translators: %s: username. */
				sprintf( __( 'Tried to sign in as "%s"', 'dovetail-studio' ), $username ),
				$duration,
				$username
			);

			return;
		}

		if ( ! empty( $settings['lockout_invalid_usernames'] ) && in_array( $code, array( 'invalid_username', 'invalid_email' ), true ) ) {
			Store::add(
				$ip,
				Store::KIND_LOCKOUT,
				'invalid_username',
				/* translators: %s: username. */
				sprintf( __( 'Used an invalid username "%s"', 'dovetail-studio' ), $username ),
				$duration,
				$username
			);

			return;
		}

		$max      = (int) $settings['max_login_failures'];
		$failures = Store::add_failure( $ip, 'login', $username, (int) $settings['failure_window'] );

		if ( $failures >= $max ) {
			Store::add(
				$ip,
				Store::KIND_LOCKOUT,
				'login',
				/* translators: %d: number of failed sign-ins. */
				sprintf( _n( 'Exceeded the maximum number of login failures (%d)', 'Exceeded the maximum number of login failures (%d)', $max, 'dovetail-studio' ), $max ),
				$duration,
				$username
			);
			Store::clear_failures( $ip, 'login' );
		}
	}

	/**
	 * Announce a completed sign-in. Users with two-factor authentication never reach this point from
	 * `wp_login` (TwoFactor stops the request at priority 5); TwoFactor fires the action itself after the code.
	 *
	 * @param string   $login Username.
	 * @param \WP_User $user  User.
	 * @return void
	 */
	public function signed_in( $login, $user = null ): void {
		if ( $user instanceof \WP_User ) {
			/**
			 * Fires once a user is fully signed in, after the two-factor step when there is one.
			 *
			 * @param \WP_User $user User.
			 */
			do_action( 'dovetail_login_guard_signed_in', $user );
		}
	}

	/**
	 * A successful sign-in clears the IP's failed attempts.
	 *
	 * @return void
	 */
	public function login_succeeded(): void {
		$ip = Store::normalize_ip( Http::client_ip() );

		if ( '' !== $ip ) {
			Store::clear_failures( $ip, 'login' );
		}
	}

	/**
	 * Password reset requests: refuse locked out IPs and count every request.
	 *
	 * @param \WP_Error           $errors    Errors so far.
	 * @param \WP_User|false|null $user_data Account found, if any.
	 * @return void
	 */
	public function lostpassword( $errors, $user_data = null ): void {
		$settings = Settings::get();
		$ip       = $this->subject_ip();

		if ( '' === $ip ) {
			return;
		}

		$row = Store::active( $ip );

		if ( $row ) {
			Store::record_hit( (int) $row['id'] );

			if ( $errors instanceof \WP_Error ) {
				$errors->add( 'dovetail_login_guard_locked', self::locked_message() );
			}

			return;
		}

		if ( empty( $settings['brute_force'] ) ) {
			return;
		}

		$login    = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core's lost password form has no nonce; only read for the log.
		$login    = $user_data instanceof \WP_User ? (string) $user_data->user_login : $login;
		$max      = (int) $settings['max_lostpw_attempts'];
		$attempts = Store::add_failure( $ip, 'lostpw', $login, (int) $settings['failure_window'] );

		if ( $attempts >= $max ) {
			Store::add(
				$ip,
				Store::KIND_LOCKOUT,
				'lostpw',
				/* translators: %d: number of password reset requests. */
				sprintf( _n( 'Exceeded the maximum number of password reset requests (%d)', 'Exceeded the maximum number of password reset requests (%d)', $max, 'dovetail-studio' ), $max ),
				(int) $settings['lockout_duration'],
				$login
			);
			Store::clear_failures( $ip, 'lostpw' );
		}
	}

	/**
	 * Message for a locked out sign-in or password reset.
	 *
	 * @return string
	 */
	public static function locked_message(): string {
		return __( 'Sign-in from your IP address has been temporarily limited for security reasons. Please try again later.', 'dovetail-studio' );
	}

	/**
	 * Stop the request with the block page (HTTP 403).
	 *
	 * @param string $ip    Visitor IP.
	 * @param string $title Page title.
	 * @return void
	 */
	private function deny( string $ip, string $title ): void {
		$text = trim( (string) Settings::get()['block_page_text'] );
		$body = '' !== $text
			? wpautop( esc_html( $text ) )
			: '<p>' . esc_html__( 'Your access has been limited by the site\'s security settings. If you think this is a mistake, contact the site owner.', 'dovetail-studio' ) . '</p>';

		$body = '<h1>' . esc_html( $title ) . '</h1>' . $body
			/* translators: 1: visitor IP address, 2: date and time. */
			. '<p><small>' . esc_html( sprintf( __( 'Your IP address: %1$s. Time: %2$s.', 'dovetail-studio' ), $ip, wp_date( 'Y-m-d H:i:s T' ) ) ) . '</small></p>';

		nocache_headers();
		wp_die( $body, esc_html( $title ), array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	}
}
