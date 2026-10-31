<?php
/**
 * Login Guard settings: defaults, choices and sanitizing.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * One option row, `dovetail_login_guard_settings`, read once per request.
 *
 * The admin screen saves it from several tabs. Each form sends a `_section`
 * field and only the keys of that section are replaced, so saving one tab
 * never resets the others.
 */
final class Settings {

	const OPTION = 'dovetail_login_guard_settings';

	/** Encryption context of the captcha secret in Core\Crypto. */
	const CRYPTO_CONTEXT = 'dovetail-login-guard';

	/** Usernames bots try first. Signing in with one of them blocks the IP when no such account exists. */
	const DEFAULT_BLOCKED_USERNAMES = array( 'admin', 'administrator', 'test', 'demo', 'root', 'user', 'guest', 'wpadmin', 'webmaster', 'manager' );

	/** @var array<string,mixed>|null */
	private static $memo = null;

	/** @var mixed */
	private static $raw = null;

	/**
	 * Default values. No translated text here: this runs before `init`.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Settings tab.
			'brute_force'                   => true,
			'max_login_failures'            => 3,
			'max_lostpw_attempts'           => 2,
			'failure_window'                => 4 * HOUR_IN_SECONDS,
			'lockout_duration'              => DAY_IN_SECONDS,
			'lockout_invalid_usernames'     => true,
			'blocked_usernames'             => self::DEFAULT_BLOCKED_USERNAMES,
			'leaked_passwords'              => 'admins',
			'strong_passwords'              => 'publishers',
			'hide_login_errors'             => true,
			'prevent_admin_registration'    => true,
			'prevent_user_enumeration'      => true,
			'disable_application_passwords' => true,
			'disable_xmlrpc_auth'           => true,
			'disable_xmlrpc'                => false,
			'block_blank_ua_post'           => true,
			'block_page_text'               => '',
			'allowlist'                     => array(),
			// Two-factor tab.
			'two_factor_roles'              => array( 'administrator' => 'optional' ),
			'two_factor_grace_days'         => 7,
			'two_factor_remember_days'      => 30,
			// Captcha tab.
			'captcha_enabled'               => false,
			'captcha_provider'              => 'recaptcha',
			'captcha_site_key'              => '',
			'captcha_secret'                => '',
			'captcha_threshold'             => 5,
			'captcha_test_mode'             => false,
			'captcha_email_verify'          => true,
			// Alerts tab.
			'alert_recipients'              => array(),
			'alert_admin_login'             => true,
			'alert_admin_login_scope'       => 'every',
			'alert_blocked'                 => true,
			'alert_lockout'                 => true,
			'alert_max_per_hour'            => 10,
			'summary_enabled'               => true,
			'summary_days'                  => 1,
			'summary_skip_empty'            => true,
			'activity_enabled'              => true,
			'activity_retention_days'       => 90,
		);
	}

	/**
	 * The keys each settings form owns.
	 *
	 * @return array<string,string[]>
	 */
	public static function sections(): array {
		return array(
			'general'    => array( 'brute_force', 'max_login_failures', 'max_lostpw_attempts', 'failure_window', 'lockout_duration', 'lockout_invalid_usernames', 'blocked_usernames', 'leaked_passwords', 'strong_passwords', 'hide_login_errors', 'prevent_admin_registration', 'prevent_user_enumeration', 'disable_application_passwords', 'disable_xmlrpc_auth', 'disable_xmlrpc', 'block_blank_ua_post', 'block_page_text', 'allowlist' ),
			'two_factor' => array( 'two_factor_roles', 'two_factor_grace_days', 'two_factor_remember_days' ),
			'captcha'    => array( 'captcha_enabled', 'captcha_provider', 'captcha_site_key', 'captcha_secret', 'captcha_threshold', 'captcha_test_mode', 'captcha_email_verify' ),
			'alerts'     => array( 'alert_recipients', 'alert_admin_login', 'alert_admin_login_scope', 'alert_blocked', 'alert_lockout', 'alert_max_per_hour', 'summary_enabled', 'summary_days', 'summary_skip_empty', 'activity_enabled', 'activity_retention_days' ),
		);
	}

	/**
	 * Saved settings merged over the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );

		if ( null !== self::$memo && $saved === self::$raw ) {
			return self::$memo;
		}

		self::$raw  = $saved;
		self::$memo = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );

		return self::$memo;
	}

	/**
	 * The captcha secret in clear text. DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET in wp-config.php wins.
	 *
	 * @return string
	 */
	public static function captcha_secret(): string {
		if ( defined( 'DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET' ) ) {
			return (string) DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET;
		}

		return Crypto::decrypt( (string) self::get()['captcha_secret'], self::CRYPTO_CONTEXT );
	}

	/**
	 * Addresses that receive the alerts and summaries: the saved list, or the site admin email.
	 *
	 * @return string[]
	 */
	public static function recipients(): array {
		$list = array_values( array_filter( (array) self::get()['alert_recipients'], 'is_email' ) );

		return $list ? $list : array( (string) get_option( 'admin_email' ) );
	}

	/**
	 * Allowed failure counts.
	 *
	 * @return int[]
	 */
	public static function count_choices(): array {
		return array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 30, 50, 100 );
	}

	/**
	 * Periods over which failures are counted, in seconds => label.
	 *
	 * @return array<int,string>
	 */
	public static function window_choices(): array {
		return array(
			5 * MINUTE_IN_SECONDS  => __( '5 minutes', 'dovetail-studio' ),
			10 * MINUTE_IN_SECONDS => __( '10 minutes', 'dovetail-studio' ),
			30 * MINUTE_IN_SECONDS => __( '30 minutes', 'dovetail-studio' ),
			HOUR_IN_SECONDS        => __( '1 hour', 'dovetail-studio' ),
			2 * HOUR_IN_SECONDS    => __( '2 hours', 'dovetail-studio' ),
			4 * HOUR_IN_SECONDS    => __( '4 hours', 'dovetail-studio' ),
			6 * HOUR_IN_SECONDS    => __( '6 hours', 'dovetail-studio' ),
			12 * HOUR_IN_SECONDS   => __( '12 hours', 'dovetail-studio' ),
			DAY_IN_SECONDS         => __( '1 day', 'dovetail-studio' ),
		);
	}

	/**
	 * Lockout and block durations, in seconds => label.
	 *
	 * @return array<int,string>
	 */
	public static function duration_choices(): array {
		return array(
			5 * MINUTE_IN_SECONDS  => __( '5 minutes', 'dovetail-studio' ),
			30 * MINUTE_IN_SECONDS => __( '30 minutes', 'dovetail-studio' ),
			HOUR_IN_SECONDS        => __( '1 hour', 'dovetail-studio' ),
			6 * HOUR_IN_SECONDS    => __( '6 hours', 'dovetail-studio' ),
			12 * HOUR_IN_SECONDS   => __( '12 hours', 'dovetail-studio' ),
			DAY_IN_SECONDS         => __( '1 day', 'dovetail-studio' ),
			2 * DAY_IN_SECONDS     => __( '2 days', 'dovetail-studio' ),
			7 * DAY_IN_SECONDS     => __( '7 days', 'dovetail-studio' ),
			30 * DAY_IN_SECONDS    => __( '30 days', 'dovetail-studio' ),
			60 * DAY_IN_SECONDS    => __( '60 days', 'dovetail-studio' ),
		);
	}

	/**
	 * Who the leaked password check applies to.
	 *
	 * @return array<string,string>
	 */
	public static function leaked_choices(): array {
		return array(
			'off'    => __( 'Off', 'dovetail-studio' ),
			'admins' => __( 'For admins only', 'dovetail-studio' ),
			'all'    => __( 'For all users', 'dovetail-studio' ),
		);
	}

	/**
	 * Who the strong password rule applies to.
	 *
	 * @return array<string,string>
	 */
	public static function strong_choices(): array {
		return array(
			'off'        => __( 'Off', 'dovetail-studio' ),
			'publishers' => __( 'Force admins and publishers to use strong passwords', 'dovetail-studio' ),
			'all'        => __( 'Force all users to use strong passwords', 'dovetail-studio' ),
		);
	}

	/**
	 * Two-factor modes for a role.
	 *
	 * @return array<string,string>
	 */
	public static function two_factor_modes(): array {
		return array(
			'off'      => __( 'Off', 'dovetail-studio' ),
			'optional' => __( 'Optional', 'dovetail-studio' ),
			'required' => __( 'Required', 'dovetail-studio' ),
		);
	}

	/**
	 * Days a user has to set up a required two-factor method.
	 *
	 * @return int[]
	 */
	public static function grace_choices(): array {
		return array( 0, 1, 3, 7, 14, 30 );
	}

	/**
	 * Days a trusted device skips the two-factor code; 0 turns the option off.
	 *
	 * @return int[]
	 */
	public static function remember_choices(): array {
		return array( 0, 1, 7, 14, 30, 60, 90 );
	}

	/**
	 * Captcha providers.
	 *
	 * @return array<string,string>
	 */
	public static function captcha_providers(): array {
		return array(
			'recaptcha' => __( 'Google reCAPTCHA v3', 'dovetail-studio' ),
			'turnstile' => __( 'Cloudflare Turnstile', 'dovetail-studio' ),
		);
	}

	/**
	 * reCAPTCHA thresholds, in tenths => label.
	 *
	 * @return array<int,string>
	 */
	public static function threshold_choices(): array {
		$labels = array(
			0  => __( '0.0 (definitely a bot)', 'dovetail-studio' ),
			3  => __( '0.3 (probably a bot)', 'dovetail-studio' ),
			5  => __( '0.5 (probably a human)', 'dovetail-studio' ),
			10 => __( '1.0 (definitely a human)', 'dovetail-studio' ),
		);
		$out    = array();

		for ( $i = 0; $i <= 10; $i++ ) {
			$out[ $i ] = $labels[ $i ] ?? number_format( $i / 10, 1 );
		}

		return $out;
	}

	/**
	 * Hourly caps of alert emails; 0 means no cap.
	 *
	 * @return int[]
	 */
	public static function alert_cap_choices(): array {
		return array( 0, 1, 5, 10, 20, 50, 100 );
	}

	/**
	 * How often the summary is sent, in days.
	 *
	 * @return int[]
	 */
	public static function summary_day_choices(): array {
		return array( 1, 2, 3, 7, 14, 30 );
	}

	/**
	 * How long the activity log keeps entries, in days.
	 *
	 * @return int[]
	 */
	public static function retention_choices(): array {
		return array( 7, 30, 60, 90, 180, 365 );
	}

	/**
	 * Role slugs and names.
	 *
	 * @return array<string,string>
	 */
	public static function roles(): array {
		return array_map( 'translate_user_role', wp_roles()->get_names() );
	}

	/**
	 * Split a list typed one per line or comma-separated.
	 *
	 * @param mixed $text Text or array.
	 * @return string[]
	 */
	public static function split_list( $text ): array {
		if ( is_array( $text ) ) {
			$text = implode( "\n", array_map( 'strval', $text ) );
		}

		return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $text ) ), 'strlen' ) );
	}

	/**
	 * Whether a value is an IP address or a CIDR range.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_ip_or_range( string $value ): bool {
		$parts = explode( '/', $value, 2 );

		if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
			return false;
		}

		if ( ! isset( $parts[1] ) ) {
			return true;
		}

		$max = false !== strpos( $parts[0], ':' ) ? 128 : 32;

		return ctype_digit( $parts[1] ) && (int) $parts[1] <= $max;
	}

	/**
	 * Sanitize one settings form; the keys of the other forms keep their saved values.
	 *
	 * @param mixed $input Submitted values, with `_section` naming the form.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$sections = self::sections();
		$section  = isset( $input['_section'] ) ? sanitize_key( $input['_section'] ) : 'general';
		$section  = isset( $sections[ $section ] ) ? $section : 'general';
		$saved    = get_option( self::OPTION, array() );
		$clean    = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		$method   = 'sanitize_' . $section;

		return array_merge( $clean, array_intersect_key( self::$method( $input, $clean ), array_flip( $sections[ $section ] ) ) );
	}

	/**
	 * Booleans of a form: a checkbox that is not sent is off.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @param string[]            $keys  Checkbox keys.
	 * @return array<string,bool>
	 */
	private static function booleans( array $input, array $keys ): array {
		$out = array();

		foreach ( $keys as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		return $out;
	}

	/**
	 * A submitted integer when it is one of the allowed values, otherwise the default.
	 *
	 * @param array<string,mixed> $input   Submitted values.
	 * @param string              $key     Setting key.
	 * @param int[]               $allowed Allowed values.
	 * @return int
	 */
	private static function pick( array $input, string $key, array $allowed ): int {
		$value = isset( $input[ $key ] ) ? (int) $input[ $key ] : self::defaults()[ $key ];

		return in_array( $value, $allowed, true ) ? $value : self::defaults()[ $key ];
	}

	/**
	 * A submitted string when it is one of the allowed keys, otherwise the default.
	 *
	 * @param array<string,mixed>  $input   Submitted values.
	 * @param string               $key     Setting key.
	 * @param array<string,string> $allowed Allowed value => label.
	 * @return string
	 */
	private static function pick_key( array $input, string $key, array $allowed ): string {
		$value = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : '';

		return isset( $allowed[ $value ] ) ? $value : self::defaults()[ $key ];
	}

	/**
	 * Settings tab: brute force protection, passwords, hardening and the allowlist.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @return array<string,mixed>
	 */
	private static function sanitize_general( array $input ): array {
		$clean = self::booleans( $input, array( 'brute_force', 'lockout_invalid_usernames', 'hide_login_errors', 'prevent_admin_registration', 'prevent_user_enumeration', 'disable_application_passwords', 'disable_xmlrpc_auth', 'disable_xmlrpc', 'block_blank_ua_post' ) );

		$clean['max_login_failures']  = self::pick( $input, 'max_login_failures', self::count_choices() );
		$clean['max_lostpw_attempts'] = self::pick( $input, 'max_lostpw_attempts', self::count_choices() );
		$clean['failure_window']      = self::pick( $input, 'failure_window', array_keys( self::window_choices() ) );
		$clean['lockout_duration']    = self::pick( $input, 'lockout_duration', array_keys( self::duration_choices() ) );
		$clean['leaked_passwords']    = self::pick_key( $input, 'leaked_passwords', self::leaked_choices() );
		$clean['strong_passwords']    = self::pick_key( $input, 'strong_passwords', self::strong_choices() );

		$usernames = array();

		foreach ( self::split_list( $input['blocked_usernames'] ?? '' ) as $name ) {
			$name = strtolower( sanitize_user( $name, true ) );

			if ( '' !== $name ) {
				$usernames[] = $name;
			}
		}

		$clean['blocked_usernames'] = array_values( array_unique( $usernames ) );

		$allowlist = array();
		$invalid   = array();

		foreach ( self::split_list( $input['allowlist'] ?? '' ) as $entry ) {
			if ( self::is_ip_or_range( $entry ) ) {
				$allowlist[] = $entry;
			} else {
				$invalid[] = $entry;
			}
		}

		if ( $invalid ) {
			add_settings_error(
				self::OPTION,
				'allowlist',
				/* translators: %s: the entries that were left out. */
				sprintf( __( 'These allowlist entries are not IP addresses or CIDR ranges and were left out: %s', 'dovetail-studio' ), implode( ', ', array_map( 'sanitize_text_field', $invalid ) ) )
			);
		}

		$clean['allowlist']       = array_values( array_unique( $allowlist ) );
		$clean['block_page_text'] = sanitize_textarea_field( (string) ( $input['block_page_text'] ?? '' ) );

		return $clean;
	}

	/**
	 * Two-factor tab: mode per role, grace period and trusted devices.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @return array<string,mixed>
	 */
	private static function sanitize_two_factor( array $input ): array {
		$modes = self::two_factor_modes();
		$roles = array();
		$given = isset( $input['two_factor_roles'] ) && is_array( $input['two_factor_roles'] ) ? $input['two_factor_roles'] : array();

		foreach ( array_keys( self::roles() ) as $role ) {
			$mode = isset( $given[ $role ] ) ? sanitize_key( $given[ $role ] ) : 'off';

			if ( isset( $modes[ $mode ] ) && 'off' !== $mode ) {
				$roles[ $role ] = $mode;
			}
		}

		return array(
			'two_factor_roles'         => $roles,
			'two_factor_grace_days'    => self::pick( $input, 'two_factor_grace_days', self::grace_choices() ),
			'two_factor_remember_days' => self::pick( $input, 'two_factor_remember_days', self::remember_choices() ),
		);
	}

	/**
	 * Captcha tab. A blank secret field keeps the saved secret; a new one is stored encrypted.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @param array<string,mixed> $saved Saved settings.
	 * @return array<string,mixed>
	 */
	private static function sanitize_captcha( array $input, array $saved ): array {
		$clean = self::booleans( $input, array( 'captcha_enabled', 'captcha_test_mode', 'captcha_email_verify' ) );

		$clean['captcha_provider']  = self::pick_key( $input, 'captcha_provider', self::captcha_providers() );
		$clean['captcha_threshold'] = self::pick( $input, 'captcha_threshold', array_keys( self::threshold_choices() ) );
		$clean['captcha_site_key']  = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $input['captcha_site_key'] ?? '' ) );
		$clean['captcha_secret']    = (string) $saved['captcha_secret'];

		$secret = trim( (string) ( $input['captcha_secret'] ?? '' ) );

		if ( '' !== $secret ) {
			$encrypted = Crypto::encrypt( preg_replace( '/[^A-Za-z0-9_\-]/', '', $secret ), self::CRYPTO_CONTEXT );

			if ( is_wp_error( $encrypted ) ) {
				add_settings_error( self::OPTION, $encrypted->get_error_code(), $encrypted->get_error_message() );
			} else {
				$clean['captcha_secret'] = $encrypted;
			}
		}

		// A provider change makes the saved keys useless: the old secret belongs to the other service.
		if ( $clean['captcha_provider'] !== $saved['captcha_provider'] && '' === $secret ) {
			$clean['captcha_secret'] = '';
		}

		$has_secret = '' !== $clean['captcha_secret'] || defined( 'DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET' );

		if ( $clean['captcha_enabled'] && ( '' === $clean['captcha_site_key'] || ! $has_secret ) ) {
			$clean['captcha_enabled'] = false;
			add_settings_error( self::OPTION, 'captcha_keys', __( 'The captcha stays off until both the site key and the secret key are saved.', 'dovetail-studio' ) );
		}

		return $clean;
	}

	/**
	 * Alerts tab: recipients, alert emails, summary and the activity log.
	 *
	 * @param array<string,mixed> $input Submitted values.
	 * @return array<string,mixed>
	 */
	private static function sanitize_alerts( array $input ): array {
		$clean = self::booleans( $input, array( 'alert_admin_login', 'alert_blocked', 'alert_lockout', 'summary_enabled', 'summary_skip_empty', 'activity_enabled' ) );

		$clean['alert_admin_login_scope'] = self::pick_key(
			$input,
			'alert_admin_login_scope',
			array(
				'every'  => '',
				'new_ip' => '',
			)
		);
		$clean['alert_max_per_hour']      = self::pick( $input, 'alert_max_per_hour', self::alert_cap_choices() );
		$clean['summary_days']            = self::pick( $input, 'summary_days', self::summary_day_choices() );
		$clean['activity_retention_days'] = self::pick( $input, 'activity_retention_days', self::retention_choices() );

		$emails  = array();
		$invalid = array();

		foreach ( self::split_list( $input['alert_recipients'] ?? '' ) as $email ) {
			if ( is_email( sanitize_email( $email ) ) ) {
				$emails[] = sanitize_email( $email );
			} else {
				$invalid[] = $email;
			}
		}

		if ( $invalid ) {
			add_settings_error(
				self::OPTION,
				'alert_recipients',
				/* translators: %s: the entries that were left out. */
				sprintf( __( 'These are not valid email addresses and were left out: %s', 'dovetail-studio' ), implode( ', ', array_map( 'sanitize_text_field', $invalid ) ) )
			);
		}

		$clean['alert_recipients'] = array_values( array_unique( $emails ) );

		return $clean;
	}
}
