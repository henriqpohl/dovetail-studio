<?php
/**
 * Login Guard activity log.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Who signed in, failed, was locked out or blocked, how two-factor and
 * captcha checks went, kept in `{prefix}dovetail_login_guard_activity` for
 * the configured number of days. The summary email is built from it.
 */
final class Activity {

	const LOGIN_SUCCESS  = 'login_success';
	const LOGIN_FAILED   = 'login_failed';
	const LOGOUT         = 'logout';
	const LOCKOUT        = 'lockout';
	const BLOCK          = 'block';
	const TWOFA_SUCCESS  = 'twofa_success';
	const TWOFA_FAILED   = 'twofa_failed';
	const CAPTCHA_FAILED = 'captcha_failed';
	const CAPTCHA_TEST   = 'captcha_test';

	/** Rows per page in the admin list. */
	const PER_PAGE = 50;

	/**
	 * Register the hooks that write the log.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'dovetail_login_guard_signed_in', array( $this, 'on_signed_in' ) );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 20, 2 );
		add_action( 'wp_logout', array( $this, 'on_logout' ) );
		add_action( 'dovetail_login_guard_blocked', array( $this, 'on_blocked' ), 10, 4 );
		add_action( Store::CLEANUP_HOOK, array( self::class, 'cleanup' ) );
	}

	/**
	 * Labels of the events, for the admin list and emails.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			self::LOGIN_SUCCESS  => __( 'Signed in', 'dovetail-studio' ),
			self::LOGIN_FAILED   => __( 'Failed sign-in', 'dovetail-studio' ),
			self::LOGOUT         => __( 'Signed out', 'dovetail-studio' ),
			self::LOCKOUT        => __( 'Locked out', 'dovetail-studio' ),
			self::BLOCK          => __( 'Blocked', 'dovetail-studio' ),
			self::TWOFA_SUCCESS  => __( 'Two-factor code accepted', 'dovetail-studio' ),
			self::TWOFA_FAILED   => __( 'Two-factor code refused', 'dovetail-studio' ),
			self::CAPTCHA_FAILED => __( 'Captcha failed', 'dovetail-studio' ),
			self::CAPTCHA_TEST   => __( 'Captcha (test mode)', 'dovetail-studio' ),
		);
	}

	/**
	 * Write one entry.
	 *
	 * @param string $event    One of the event constants.
	 * @param string $username Username involved.
	 * @param int    $user_id  User ID, 0 when unknown.
	 * @param string $detail   Short detail (reason, error, score).
	 * @param string $ip       IP address; the visitor's when empty.
	 * @return void
	 */
	public static function log( string $event, string $username = '', int $user_id = 0, string $detail = '', string $ip = '' ): void {
		global $wpdb;

		if ( empty( Settings::get()['activity_enabled'] ) ) {
			return;
		}

		$ip    = Store::normalize_ip( '' !== $ip ? $ip : Http::client_ip() );
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$table = Store::activity_table();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
			$wpdb->prepare(
				"INSERT INTO {$table} (event, user_id, username, ip, user_agent, detail, created_at) VALUES (%s, %d, %s, %s, %s, %s, %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				sanitize_key( $event ),
				$user_id,
				mb_substr( sanitize_user( $username ), 0, 60 ),
				$ip,
				mb_substr( $agent, 0, 255 ),
				mb_substr( sanitize_text_field( $detail ), 0, 255 ),
				time()
			)
		);
	}

	/**
	 * A completed sign-in (after the two-factor step when there is one).
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public function on_signed_in( $user ): void {
		if ( $user instanceof \WP_User ) {
			self::log( self::LOGIN_SUCCESS, (string) $user->user_login, (int) $user->ID );
		}
	}

	/**
	 * A failed sign-in.
	 *
	 * @param string         $username Username tried.
	 * @param \WP_Error|null $error    Why it failed.
	 * @return void
	 */
	public function on_login_failed( $username, $error = null ): void {
		$code = $error instanceof \WP_Error ? (string) $error->get_error_code() : '';

		// Locked out requests are counted on the block list; a wrong two-factor code is logged as such.
		if ( ! in_array( $code, array( 'dovetail_login_guard_locked', 'dovetail_login_guard_2fa_invalid', 'dovetail_login_guard_captcha_verify' ), true ) ) {
			self::log( self::LOGIN_FAILED, (string) $username, (int) username_exists( (string) $username ), $code );
		}
	}

	/**
	 * A sign-out.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_logout( $user_id = 0 ): void {
		$user = get_userdata( (int) $user_id );

		self::log( self::LOGOUT, $user ? (string) $user->user_login : '', (int) $user_id );
	}

	/**
	 * A lockout or block.
	 *
	 * @param string $ip     IP address.
	 * @param string $kind   'lockout' or 'block'.
	 * @param string $source What triggered it.
	 * @param string $reason Reason.
	 * @return void
	 */
	public function on_blocked( $ip, $kind, $source, $reason ): void {
		self::log( Store::KIND_BLOCK === $kind ? self::BLOCK : self::LOCKOUT, '', 0, (string) $reason, (string) $ip );
	}

	/**
	 * Entries for the admin list, newest first.
	 *
	 * @param string $event    Event constant, or '' for all.
	 * @param string $search   Part of the IP, username or detail.
	 * @param int    $page     Page number, from 1.
	 * @param int    $per_page Rows per page.
	 * @return array{rows: array<int,array<string,mixed>>, total: int}
	 */
	public static function query( string $event = '', string $search = '', int $page = 1, int $per_page = self::PER_PAGE ): array {
		global $wpdb;

		$table = Store::activity_table();
		$where = '1 = 1';

		if ( '' !== $event ) {
			$where .= $wpdb->prepare( ' AND event = %s', $event );
		}

		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (ip LIKE %s OR username LIKE %s OR detail LIKE %s)', $like, $like, $like );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; $where is prepared above.
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, ( max( 1, $page ) - 1 ) * $per_page ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; prepared parts.

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Totals since a moment, for the summary email.
	 *
	 * @param int $since Unix time.
	 * @return array{counts: array<string,int>, top_ips: array<string,int>}
	 */
	public static function summary( int $since ): array {
		global $wpdb;

		$table  = Store::activity_table();
		$counts = array_fill_keys( array_keys( self::labels() ), 0 );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT event, COUNT(*) AS n FROM {$table} WHERE created_at > %d GROUP BY event", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		foreach ( (array) $rows as $row ) {
			$counts[ $row['event'] ] = (int) $row['n'];
		}

		$top  = array();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ip, COUNT(*) AS n FROM {$table} WHERE created_at > %d AND event IN (%s, %s, %s) GROUP BY ip ORDER BY n DESC LIMIT 5", $since, self::LOGIN_FAILED, self::LOCKOUT, self::BLOCK ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		foreach ( (array) $rows as $row ) {
			$top[ (string) $row['ip'] ] = (int) $row['n'];
		}

		return array(
			'counts'  => $counts,
			'top_ips' => $top,
		);
	}

	/**
	 * Daily: remove entries older than the retention period.
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		global $wpdb;

		$table = Store::activity_table();
		$days  = max( 1, (int) Settings::get()['activity_retention_days'] );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at <= %d", time() - $days * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
	}
}
