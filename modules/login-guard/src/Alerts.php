<?php
/**
 * Login Guard email alerts and summaries.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text emails in the style of Wordfence's alerts:
 *
 * - an administrator signed in (every time, or only from a new IP address);
 * - an IP address was blocked, or locked out of signing in;
 * - a summary every N days.
 *
 * Every email can be switched off in the Alerts tab. Alerts are capped per
 * hour so an attack cannot flood the inbox, and they are sent after the
 * response has gone to the browser, so the reverse DNS lookup never slows a
 * sign-in down.
 */
final class Alerts {

	const SUMMARY_HOOK   = 'dovetail_login_guard_summary';
	const SUMMARY_OPTION = 'dovetail_login_guard_summary_last';
	const QUOTA_OPTION   = 'dovetail_login_guard_alert_quota';
	const KNOWN_IPS_META = 'dovetail_lg_known_ips';

	/** @var array<int,array{subject:string,lines:string[],ip:string}> Emails waiting for the end of the request. */
	private $queue = array();

	/** @var Alerts|null */
	private static $instance = null;

	/**
	 * The shared instance, so every part of the module uses one queue and one hourly cap.
	 *
	 * @return Alerts
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
		add_action( 'dovetail_login_guard_signed_in', array( $this, 'on_signed_in' ) );
		add_action( 'dovetail_login_guard_blocked', array( $this, 'on_blocked' ), 10, 4 );
		add_action( self::SUMMARY_HOOK, array( $this, 'maybe_send_summary' ) );
		add_action( 'init', array( $this, 'schedule_summary' ) );
	}

	/**
	 * Check once a day whether a summary is due.
	 *
	 * @return void
	 */
	public function schedule_summary(): void {
		if ( ! wp_next_scheduled( self::SUMMARY_HOOK ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::SUMMARY_HOOK );
		}
	}

	/**
	 * An administrator signed in.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public function on_signed_in( $user ): void {
		$settings = Settings::get();

		if ( ! $user instanceof \WP_User || empty( $settings['alert_admin_login'] ) || ! user_can( $user, 'manage_options' ) ) {
			return;
		}

		$ip    = Store::normalize_ip( Http::client_ip() );
		$known = array_values( array_filter( (array) get_user_meta( (int) $user->ID, self::KNOWN_IPS_META, true ), 'is_string' ) );
		$seen  = in_array( $ip, $known, true );

		// Remember the last 20 addresses of each administrator.
		$known = array_slice( array_values( array_unique( array_merge( array( $ip ), $known ) ) ), 0, 20 );
		update_user_meta( (int) $user->ID, self::KNOWN_IPS_META, $known );

		if ( 'new_ip' === $settings['alert_admin_login_scope'] && $seen ) {
			return;
		}

		$this->notify(
			__( 'Admin Login', 'dovetail-studio' ),
			array(
				/* translators: %s: username. */
				sprintf( __( 'A user with username "%s" who has administrator access signed in to your WordPress site.', 'dovetail-studio' ), $user->user_login ),
			),
			$ip
		);
	}

	/**
	 * An IP address was locked out or blocked by a rule (manual blocks send nothing).
	 *
	 * @param string $ip     IP address.
	 * @param string $kind   'lockout' or 'block'.
	 * @param string $source What triggered it.
	 * @param string $reason Reason.
	 * @return void
	 */
	public function on_blocked( $ip, $kind, $source, $reason ): void {
		$settings = Settings::get();
		$lockout  = Store::KIND_LOCKOUT === $kind;

		if ( 'manual' === $source || empty( $settings[ $lockout ? 'alert_lockout' : 'alert_blocked' ] ) ) {
			return;
		}

		$row     = Store::find( (string) $ip );
		$expires = $row && 0 === (int) $row['expires_at']
			? __( 'never (permanent)', 'dovetail-studio' )
			: wp_date( $this->date_format(), $row ? (int) $row['expires_at'] : time() );
		$lines   = array(
			$lockout
				? __( 'An IP address was locked out of signing in to your WordPress site.', 'dovetail-studio' )
				: __( 'An IP address was blocked from your WordPress site.', 'dovetail-studio' ),
			/* translators: %s: reason. */
			sprintf( __( 'Reason: %s', 'dovetail-studio' ), $reason ),
		);

		if ( $row && '' !== (string) $row['username'] ) {
			/* translators: %s: username. */
			$lines[] = sprintf( __( 'Username tried: %s', 'dovetail-studio' ), $row['username'] );
		}

		/* translators: %s: date and time, or "never". */
		$lines[] = sprintf( __( 'Block ends: %s', 'dovetail-studio' ), $expires );
		/* translators: %s: URL of the block list. */
		$lines[] = sprintf( __( 'To unblock it, open: %s', 'dovetail-studio' ), Admin::url( array( 'tab' => 'blocking' ) ) );

		$this->notify( $lockout ? __( 'User Locked Out', 'dovetail-studio' ) : __( 'IP Blocked', 'dovetail-studio' ), $lines, (string) $ip );
	}

	/**
	 * Add an alert to the queue, within the hourly cap. It is sent when the request ends.
	 *
	 * @param string   $title Alert title, e.g. "Admin Login".
	 * @param string[] $lines What happened.
	 * @param string   $ip    IP address the alert is about; '' when there is none (scheduled checks).
	 * @return void
	 */
	public function notify( string $title, array $lines, string $ip ): void {
		if ( ! $this->take_quota() ) {
			return;
		}

		if ( ! $this->queue ) {
			// After WordPress flushed its output buffers (priority 1), so finishing the request loses nothing.
			add_action( 'shutdown', array( $this, 'flush' ), 1000 );
		}

		$this->queue[] = array(
			'subject' => $title,
			'lines'   => $lines,
			'ip'      => $ip,
		);
	}

	/**
	 * Count one alert against the hourly cap.
	 *
	 * @return bool Whether it may be sent.
	 */
	private function take_quota(): bool {
		$cap  = (int) Settings::get()['alert_max_per_hour'];
		$hour = (int) floor( time() / HOUR_IN_SECONDS );
		$used = (array) get_option( self::QUOTA_OPTION, array() );
		$sent = ( (int) ( $used['hour'] ?? 0 ) === $hour ) ? (int) ( $used['sent'] ?? 0 ) : 0;

		if ( $cap > 0 && $sent >= $cap ) {
			return false;
		}

		update_option(
			self::QUOTA_OPTION,
			array(
				'hour' => $hour,
				'sent' => $sent + 1,
			),
			false
		);

		return true;
	}

	/**
	 * Send the queued alerts once the response is complete.
	 *
	 * @return void
	 */
	public function flush(): void {
		$queue       = $this->queue;
		$this->queue = array();

		if ( ! $queue ) {
			return;
		}

		// Let the browser go before the DNS lookups and the mail transport.
		if ( function_exists( 'fastcgi_finish_request' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			fastcgi_finish_request();
		}

		foreach ( $queue as $alert ) {
			$this->send( $alert['subject'], array_merge( $alert['lines'], $this->visitor_lines( $alert['ip'] ) ) );
		}
	}

	/**
	 * IP, hostname, location (behind Cloudflare only) and browser of an alert.
	 *
	 * @param string $ip IP address.
	 * @return string[]
	 */
	private function visitor_lines( string $ip ): array {
		if ( '' === $ip ) {
			return array();
		}

		$host  = '' !== $ip ? (string) gethostbyaddr( $ip ) : '';
		$lines = array(
			/* translators: %s: IP address. */
			sprintf( __( 'User IP: %s', 'dovetail-studio' ), $ip ),
			/* translators: %s: hostname. */
			sprintf( __( 'User hostname: %s', 'dovetail-studio' ), '' !== $host ? $host : $ip ),
		);

		$country = $this->country( $ip );

		if ( '' !== $country ) {
			/* translators: %s: country. */
			$lines[] = sprintf( __( 'User location: %s', 'dovetail-studio' ), $country );
		}

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( '' !== $agent && Store::normalize_ip( Http::client_ip() ) === $ip ) {
			/* translators: %s: browser user agent. */
			$lines[] = sprintf( __( 'Browser: %s', 'dovetail-studio' ), $agent );
		}

		return $lines;
	}

	/**
	 * Country of the current visitor, as reported by Cloudflare (CF-IPCountry). Without Cloudflare the
	 * site has no geolocation data, so the line is left out.
	 *
	 * @param string $ip IP address the alert is about.
	 * @return string
	 */
	private function country( string $ip ): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$code   = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) ) : '';

		if ( ! preg_match( '/^[A-Z]{2}$/', $code ) || 'XX' === $code || 'T1' === $code
			|| ! Http::is_cloudflare( $remote )
			|| Store::normalize_ip( Http::client_ip() ) !== $ip
		) {
			return '';
		}

		$name = class_exists( '\Locale' ) ? (string) \Locale::getDisplayRegion( '-' . $code, 'en' ) : '';

		return '' !== $name && $name !== $code ? $name : $code;
	}

	/**
	 * Daily check: send the summary when the chosen number of days has passed.
	 *
	 * @return void
	 */
	public function maybe_send_summary(): void {
		$settings = Settings::get();
		$last     = (int) get_option( self::SUMMARY_OPTION, 0 );
		$now      = time();

		if ( empty( $settings['summary_enabled'] ) ) {
			return;
		}

		// First run: start counting from now.
		if ( 0 === $last ) {
			update_option( self::SUMMARY_OPTION, $now, false );

			return;
		}

		// A few minutes of slack, as WP-Cron rarely runs at the exact second.
		if ( $now - $last < (int) $settings['summary_days'] * DAY_IN_SECONDS - 15 * MINUTE_IN_SECONDS ) {
			return;
		}

		update_option( self::SUMMARY_OPTION, $now, false );

		$data    = Activity::summary( $last );
		$counts  = $data['counts'];
		$watched = array( Activity::LOGIN_FAILED, Activity::LOCKOUT, Activity::BLOCK, Activity::CAPTCHA_FAILED, Activity::TWOFA_FAILED );
		$events  = array_sum( array_intersect_key( $counts, array_flip( $watched ) ) );

		if ( 0 === $events && ! empty( $settings['summary_skip_empty'] ) ) {
			return;
		}

		$labels = Activity::labels();
		$lines  = array(
			/* translators: 1: start date, 2: end date. */
			sprintf( __( 'Login Guard activity from %1$s to %2$s:', 'dovetail-studio' ), wp_date( $this->date_format(), $last ), wp_date( $this->date_format(), $now ) ),
			'',
		);

		foreach ( array_merge( array( Activity::LOGIN_SUCCESS ), $watched ) as $event ) {
			// Two-factor and captcha failures are listed only when they happened; sign-in totals always.
			if ( $counts[ $event ] > 0 || in_array( $event, array( Activity::LOGIN_SUCCESS, Activity::LOGIN_FAILED, Activity::LOCKOUT, Activity::BLOCK ), true ) ) {
				$lines[] = sprintf( '%s: %d', $labels[ $event ], $counts[ $event ] );
			}
		}

		/* translators: %d: number of active blocks. */
		$lines[] = sprintf( __( 'Blocks active now: %d', 'dovetail-studio' ), Store::query( '', 'all', 1, 1 )['total'] );

		if ( $data['top_ips'] ) {
			$lines[] = '';
			$lines[] = __( 'Most active IP addresses (failed sign-ins, lockouts and blocks):', 'dovetail-studio' );

			foreach ( $data['top_ips'] as $ip => $count ) {
				$lines[] = sprintf( '- %s: %d', $ip, $count );
			}
		}

		if ( empty( $settings['activity_enabled'] ) ) {
			$lines[] = '';
			$lines[] = __( 'The activity log is switched off, so these counts are empty. Switch it on in the Alerts tab.', 'dovetail-studio' );
		}

		$lines[] = '';
		/* translators: %s: URL of the activity log. */
		$lines[] = sprintf( __( 'Full activity log: %s', 'dovetail-studio' ), Admin::url( array( 'tab' => 'activity' ) ) );

		$this->send( __( 'Summary', 'dovetail-studio' ), $lines );
	}

	/**
	 * Send one email with the standard header and footer.
	 *
	 * @param string   $title Title appended to the subject.
	 * @param string[] $lines Body lines.
	 * @return bool
	 */
	private function send( string $title, array $lines ): bool {
		return \DovetailStudio\Core\Mail::alert(
			__( 'Login Guard', 'dovetail-studio' ),
			$title,
			$lines,
			Settings::recipients(),
			Admin::url(),
			Admin::url( array( 'tab' => 'alerts' ) )
		);
	}

	/**
	 * The site's date and time format.
	 *
	 * @return string
	 */
	private function date_format(): string {
		return \DovetailStudio\Core\Mail::date_format();
	}
}
