<?php
/**
 * Login Guard password policies: strong passwords and passwords leaked in data breaches.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Strong password rule when a password is set (profile, new user, password reset),
 * and the Have I Been Pwned check when a password is set or used to sign in.
 *
 * The breach check sends only the first 5 characters of the password's SHA-1
 * hash (k-anonymity range API); the password never leaves the server. When the
 * service cannot be reached the password is accepted, so an outage never locks
 * anyone out.
 */
final class Passwords {

	const PWNED_API = 'https://api.pwnedpasswords.com/range/';

	/** Minimum length of a strong password. */
	const MIN_LENGTH = 12;

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'user_profile_update_errors', array( $this, 'profile_errors' ), 10, 3 );
		add_action( 'validate_password_reset', array( $this, 'reset_errors' ), 10, 2 );
		add_filter( 'authenticate', array( $this, 'refuse_leaked_on_login' ), 98, 3 );
	}

	/**
	 * Roles a user has or is getting.
	 *
	 * @param object $user WP_User or the user object of a profile save.
	 * @return string[]
	 */
	private function roles( $user ): array {
		$roles = array();

		if ( ! empty( $user->role ) ) {
			$roles[] = (string) $user->role;
		}

		if ( ! empty( $user->roles ) ) {
			$roles = array_merge( $roles, (array) $user->roles );
		}

		if ( ! empty( $user->ID ) ) {
			$existing = get_userdata( (int) $user->ID );

			if ( $existing && ! empty( $existing->roles ) ) {
				$roles = array_merge( $roles, (array) $existing->roles );
			}
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Whether any of the roles has a capability.
	 *
	 * @param string[] $roles Role slugs.
	 * @param string   $cap   Capability.
	 * @return bool
	 */
	private function roles_can( array $roles, string $cap ): bool {
		foreach ( $roles as $slug ) {
			$role = get_role( $slug );

			if ( $role && $role->has_cap( $cap ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a setting ('off', 'admins'/'publishers' or 'all') covers the user.
	 *
	 * @param string $scope Setting value.
	 * @param object $user  User.
	 * @return bool
	 */
	private function applies( string $scope, $user ): bool {
		if ( 'all' === $scope ) {
			return true;
		}

		if ( 'admins' === $scope ) {
			return $this->roles_can( $this->roles( $user ), 'manage_options' );
		}

		if ( 'publishers' === $scope ) {
			return $this->roles_can( $this->roles( $user ), 'publish_posts' );
		}

		return false;
	}

	/**
	 * Why a password is not strong, or '' when it is.
	 *
	 * @param string $password Password.
	 * @param string $login    Username.
	 * @param string $email    Email address.
	 * @return string
	 */
	public static function weakness( string $password, string $login = '', string $email = '' ): string {
		$classes = (int) preg_match( '/[a-z]/', $password )
			+ (int) preg_match( '/[A-Z]/', $password )
			+ (int) preg_match( '/[0-9]/', $password )
			+ (int) preg_match( '/[^a-zA-Z0-9]/', $password );

		if ( mb_strlen( $password ) < self::MIN_LENGTH || $classes < 3 ) {
			/* translators: %d: minimum number of characters. */
			return sprintf( __( 'Please choose a stronger password: at least %d characters, using at least three of these: lowercase letters, uppercase letters, numbers and symbols.', 'dovetail-studio' ), self::MIN_LENGTH );
		}

		$lower = strtolower( $password );

		foreach ( array( $login, strstr( $email, '@', true ) ) as $part ) {
			$part = strtolower( (string) $part );

			if ( strlen( $part ) >= 3 && false !== strpos( $lower, $part ) ) {
				return __( 'Please choose a stronger password: it must not contain your username or email address.', 'dovetail-studio' );
			}
		}

		return '';
	}

	/**
	 * Whether a password appears in a known data breach.
	 *
	 * @param string $password Password.
	 * @return bool False also when the service cannot be reached.
	 */
	public static function is_leaked( string $password ): bool {
		if ( '' === $password ) {
			return false;
		}

		$hash     = strtoupper( sha1( $password ) );
		$response = wp_remote_get(
			self::PWNED_API . substr( $hash, 0, 5 ),
			array(
				'timeout' => 5,
				'headers' => array( 'Add-Padding' => 'true' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$suffix = substr( $hash, 5 );

		foreach ( preg_split( '/\r?\n/', (string) wp_remote_retrieve_body( $response ) ) as $line ) {
			$parts = explode( ':', trim( $line ) );

			if ( 2 === count( $parts ) && $parts[0] === $suffix && (int) $parts[1] > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a password being set; adds errors to $errors.
	 *
	 * @param \WP_Error $errors   Errors.
	 * @param string    $password New password.
	 * @param object    $user     User.
	 * @return void
	 */
	private function check_new_password( $errors, string $password, $user ): void {
		if ( '' === $password || ! $errors instanceof \WP_Error ) {
			return;
		}

		$settings = Settings::get();

		if ( $this->applies( (string) $settings['strong_passwords'], $user ) ) {
			$weakness = self::weakness( $password, (string) ( $user->user_login ?? '' ), (string) ( $user->user_email ?? '' ) );

			if ( '' !== $weakness ) {
				$errors->add( 'dovetail_login_guard_weak_password', $weakness, array( 'form-field' => 'pass1' ) );

				return;
			}
		}

		if ( $this->applies( (string) $settings['leaked_passwords'], $user ) && self::is_leaked( $password ) ) {
			$errors->add(
				'dovetail_login_guard_leaked_password',
				__( 'This password appears in a known data breach. Please choose a different password.', 'dovetail-studio' ),
				array( 'form-field' => 'pass1' )
			);
		}
	}

	/**
	 * New user and profile saves.
	 *
	 * @param \WP_Error $errors Errors.
	 * @param bool      $update Whether an existing user is updated.
	 * @param object    $user   User being saved; user_pass holds the new password when one is set.
	 * @return void
	 */
	public function profile_errors( $errors, $update, $user ): void {
		if ( is_object( $user ) && ! empty( $user->user_pass ) ) {
			$this->check_new_password( $errors, (string) $user->user_pass, $user );
		}
	}

	/**
	 * Password reset form.
	 *
	 * @param \WP_Error         $errors Errors.
	 * @param \WP_User|\WP_Error $user  User whose password is reset.
	 * @return void
	 */
	public function reset_errors( $errors, $user ): void {
		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Core verified the reset key; passwords are not sanitized.

		if ( $user instanceof \WP_User ) {
			$this->check_new_password( $errors, $password, $user );
		}
	}

	/**
	 * A correct password found in a data breach is refused at sign-in; the user resets it by email.
	 *
	 * @param \WP_User|\WP_Error|null $user     Result so far.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public function refuse_leaked_on_login( $user, $username = '', $password = '' ) {
		if ( ! $user instanceof \WP_User
			|| ! $this->applies( (string) Settings::get()['leaked_passwords'], $user )
			|| ! self::is_leaked( (string) $password )
		) {
			return $user;
		}

		return new \WP_Error(
			'dovetail_login_guard_leaked',
			sprintf(
				/* translators: %s: lost password URL. */
				__( 'Your password appears in a known data breach, so it can no longer be used to sign in. <a href="%s">Reset your password</a> to choose a new one.', 'dovetail-studio' ),
				esc_url( wp_lostpassword_url() )
			)
		);
	}
}
