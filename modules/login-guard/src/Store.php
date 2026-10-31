<?php
/**
 * Login Guard storage: blocked IP addresses and recent failures.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Three custom tables, so a site under attack never fills wp_options:
 *
 * - `{prefix}dovetail_login_guard_blocks`: one row per IP address. A `lockout`
 *   stops the IP from signing in or resetting a password; a `block` keeps it
 *   off the whole site. `expires_at` 0 means permanent.
 * - `{prefix}dovetail_login_guard_failures`: one row per failed sign-in or
 *   password reset request, counted over the configured period.
 * - `{prefix}dovetail_login_guard_activity`: the activity log (sign-ins,
 *   failures, blocks, two-factor and captcha results), see Activity.
 *
 * Front-end requests only query the blocks table when an active site-wide
 * block may exist (the autoloaded `dovetail_login_guard_site_blocks_until`
 * option holds the latest expiry), so a site with no blocks pays nothing.
 */
final class Store {

	const DB_VERSION     = '2';
	const DB_OPTION      = 'dovetail_login_guard_db';
	const MARKER_OPTION  = 'dovetail_login_guard_site_blocks_until';
	const CLEANUP_HOOK   = 'dovetail_login_guard_cleanup';
	const PERMANENT_MARK = 4294967295;

	const KIND_LOCKOUT = 'lockout';
	const KIND_BLOCK   = 'block';

	/** Rows per page in the admin list. */
	const PER_PAGE = 50;

	/**
	 * Name of the blocks table.
	 *
	 * @return string
	 */
	public static function blocks_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'dovetail_login_guard_blocks';
	}

	/**
	 * Name of the failures table.
	 *
	 * @return string
	 */
	public static function failures_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'dovetail_login_guard_failures';
	}

	/**
	 * Name of the activity log table.
	 *
	 * @return string
	 */
	public static function activity_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'dovetail_login_guard_activity';
	}

	/**
	 * Create or update the tables when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( self::DB_VERSION === get_option( self::DB_OPTION ) ) {
			return;
		}

		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset  = $wpdb->get_charset_collate();
		$blocks   = self::blocks_table();
		$failures = self::failures_table();
		$activity = self::activity_table();

		dbDelta(
			"CREATE TABLE {$blocks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ip varchar(45) NOT NULL,
				kind varchar(10) NOT NULL,
				source varchar(20) NOT NULL,
				reason varchar(255) NOT NULL DEFAULT '',
				username varchar(60) NOT NULL DEFAULT '',
				created_at int(10) unsigned NOT NULL,
				expires_at int(10) unsigned NOT NULL DEFAULT 0,
				hits int(10) unsigned NOT NULL DEFAULT 0,
				last_attempt int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY ip (ip),
				KEY expires_at (expires_at)
			) {$charset};
			CREATE TABLE {$failures} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ip varchar(45) NOT NULL,
				kind varchar(10) NOT NULL,
				username varchar(60) NOT NULL DEFAULT '',
				created_at int(10) unsigned NOT NULL,
				PRIMARY KEY  (id),
				KEY ip_kind (ip,kind,created_at),
				KEY created_at (created_at)
			) {$charset};
			CREATE TABLE {$activity} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event varchar(20) NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				username varchar(60) NOT NULL DEFAULT '',
				ip varchar(45) NOT NULL DEFAULT '',
				user_agent varchar(255) NOT NULL DEFAULT '',
				detail varchar(255) NOT NULL DEFAULT '',
				created_at int(10) unsigned NOT NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY event (event,created_at)
			) {$charset};"
		);

		update_option( self::DB_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Canonical form of an IP address (IPv6 compressed and lowercase), or '' when invalid.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function normalize_ip( string $ip ): string {
		$packed = filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ? @inet_pton( trim( $ip ) ) : false; // Invalid input returns false.

		return false === $packed ? '' : (string) inet_ntop( $packed );
	}

	/**
	 * Whether a row is still in force.
	 *
	 * @param array<string,mixed> $row Block row.
	 * @return bool
	 */
	public static function is_active( array $row ): bool {
		return 0 === (int) $row['expires_at'] || (int) $row['expires_at'] > time();
	}

	/**
	 * The row of an IP address, active or expired.
	 *
	 * @param string $ip IP.
	 * @return array<string,mixed>|null
	 */
	public static function find( string $ip ): ?array {
		global $wpdb;

		$ip = self::normalize_ip( $ip );

		if ( '' === $ip ) {
			return null;
		}

		$table = self::blocks_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE ip = %s", $ip ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		return empty( $rows ) ? null : $rows[0];
	}

	/**
	 * The active block or lockout of an IP address.
	 *
	 * @param string $ip IP.
	 * @return array<string,mixed>|null
	 */
	public static function active( string $ip ): ?array {
		$row = self::find( $ip );

		return $row && self::is_active( $row ) ? $row : null;
	}

	/**
	 * Lock out or block an IP address. An existing active rule is never weakened:
	 * a block stays a block and a later expiry (or permanent) is kept.
	 *
	 * @param string $ip       IP.
	 * @param string $kind     KIND_LOCKOUT or KIND_BLOCK.
	 * @param string $source   manual, login, lostpw, invalid_username, username or blank_ua.
	 * @param string $reason   Reason shown in the admin list.
	 * @param int    $duration Seconds; 0 for permanent.
	 * @param string $username Username involved, if any.
	 * @return bool
	 */
	public static function add( string $ip, string $kind, string $source, string $reason, int $duration, string $username = '' ): bool {
		global $wpdb;

		$ip = self::normalize_ip( $ip );

		if ( '' === $ip || ! in_array( $kind, array( self::KIND_LOCKOUT, self::KIND_BLOCK ), true ) ) {
			return false;
		}

		$now     = time();
		$expires = $duration > 0 ? $now + $duration : 0;
		$table   = self::blocks_table();
		$row     = self::find( $ip );
		$data    = array(
			'kind'     => $kind,
			'source'   => sanitize_key( $source ),
			'reason'   => mb_substr( sanitize_text_field( $reason ), 0, 255 ),
			'username' => mb_substr( sanitize_user( $username ), 0, 60 ),
		);

		if ( $row && self::is_active( $row ) ) {
			// Keep the stronger rule's type and the later expiry.
			if ( self::KIND_BLOCK === $row['kind'] && self::KIND_LOCKOUT === $kind ) {
				$data = array(
					'kind'     => $row['kind'],
					'source'   => $row['source'],
					'reason'   => $row['reason'],
					'username' => $row['username'],
				);
			}

			$expires = ( 0 === (int) $row['expires_at'] || 0 === $expires ) ? 0 : max( (int) $row['expires_at'], $expires );

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
				$wpdb->prepare(
					"UPDATE {$table} SET kind = %s, source = %s, reason = %s, username = %s, expires_at = %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
					$data['kind'],
					$data['source'],
					$data['reason'],
					$data['username'],
					$expires,
					(int) $row['id']
				)
			);
		} elseif ( $row ) {
			// An expired row is reused as a new rule.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
				$wpdb->prepare(
					"UPDATE {$table} SET kind = %s, source = %s, reason = %s, username = %s, created_at = %d, expires_at = %d, hits = 0, last_attempt = 0 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
					$data['kind'],
					$data['source'],
					$data['reason'],
					$data['username'],
					$now,
					$expires,
					(int) $row['id']
				)
			);
		} else {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table.
				$wpdb->prepare(
					"INSERT INTO {$table} (ip, kind, source, reason, username, created_at, expires_at, hits, last_attempt) VALUES (%s, %s, %s, %s, %s, %d, %d, 0, 0)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
					$ip,
					$data['kind'],
					$data['source'],
					$data['reason'],
					$data['username'],
					$now,
					$expires
				)
			);
		}

		self::refresh_marker();

		/**
		 * Fires after Login Guard locks out or blocks an IP address.
		 *
		 * @param string $ip     IP address.
		 * @param string $kind   'lockout' or 'block'.
		 * @param string $source What triggered it.
		 * @param string $reason Reason.
		 */
		do_action( 'dovetail_login_guard_blocked', $ip, $kind, $source, $data['reason'] );

		return true;
	}

	/**
	 * Count a request from a blocked or locked out IP.
	 *
	 * @param int $id Row ID.
	 * @return void
	 */
	public static function record_hit( int $id ): void {
		global $wpdb;

		$table = self::blocks_table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_attempt = %d WHERE id = %d", time(), $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
	}

	/**
	 * Remove rules.
	 *
	 * @param int[] $ids Row IDs.
	 * @return int Rows removed.
	 */
	public static function delete( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		foreach ( self::ips( $ids ) as $ip ) {
			self::clear_failures( $ip );
		}

		$table   = self::blocks_table();
		$removed = (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; IDs are integers.
		self::refresh_marker();

		return $removed;
	}

	/**
	 * Remove the rule of an IP address.
	 *
	 * @param string $ip IP.
	 * @return bool Whether a rule was removed.
	 */
	public static function delete_ip( string $ip ): bool {
		$row = self::find( $ip );

		return $row ? self::delete( array( (int) $row['id'] ) ) > 0 : false;
	}

	/**
	 * IP addresses of rules.
	 *
	 * @param int[] $ids Row IDs.
	 * @return string[]
	 */
	public static function ips( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return array();
		}

		$table = self::blocks_table();
		$rows  = $wpdb->get_results( "SELECT ip FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; IDs are integers.

		return array_map( 'strval', array_column( is_array( $rows ) ? $rows : array(), 'ip' ) );
	}

	/**
	 * Make rules permanent.
	 *
	 * @param int[] $ids Row IDs.
	 * @return int Rows changed.
	 */
	public static function make_permanent( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$table   = self::blocks_table();
		$changed = (int) $wpdb->query( "UPDATE {$table} SET expires_at = 0 WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; IDs are integers.
		self::refresh_marker();

		return $changed;
	}

	/**
	 * Active rules for the admin list, newest first.
	 *
	 * @param string $search Part of the IP, username or reason.
	 * @param string $source 'all', 'automatic' or 'manual'.
	 * @param int    $page   Page number, from 1.
	 * @param int    $per_page Rows per page; 0 for all.
	 * @return array{rows: array<int,array<string,mixed>>, total: int}
	 */
	public static function query( string $search = '', string $source = 'all', int $page = 1, int $per_page = self::PER_PAGE ): array {
		global $wpdb;

		$table = self::blocks_table();
		$where = $wpdb->prepare( '(expires_at = 0 OR expires_at > %d)', time() );

		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (ip LIKE %s OR username LIKE %s OR reason LIKE %s)', $like, $like, $like );
		}

		if ( 'manual' === $source ) {
			$where .= " AND source = 'manual'";
		} elseif ( 'automatic' === $source ) {
			$where .= " AND source <> 'manual'";
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; $where is prepared above.
		$sql   = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC";

		if ( $per_page > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, ( max( 1, $page ) - 1 ) * $per_page );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Built from prepared parts above.

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Record a failed sign-in or password reset request and return how many the IP made in the period.
	 *
	 * @param string $ip       IP.
	 * @param string $kind     'login' or 'lostpw'.
	 * @param string $username Username tried.
	 * @param int    $window   Period in seconds.
	 * @return int Failures in the period, this one included.
	 */
	public static function add_failure( string $ip, string $kind, string $username, int $window ): int {
		global $wpdb;

		$ip = self::normalize_ip( $ip );

		if ( '' === $ip ) {
			return 0;
		}

		$table = self::failures_table();
		$now   = time();
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (ip, kind, username, created_at) VALUES (%s, %s, %s, %d)", $ip, $kind, mb_substr( sanitize_user( $username ), 0, 60 ), $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip = %s AND kind = %s AND created_at > %d", $ip, $kind, $now - $window ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
	}

	/**
	 * Forget the failures of an IP address.
	 *
	 * @param string $ip   IP.
	 * @param string $kind 'login', 'lostpw' or '' for both.
	 * @return void
	 */
	public static function clear_failures( string $ip, string $kind = '' ): void {
		global $wpdb;

		$table = self::failures_table();
		$sql   = $wpdb->prepare( "DELETE FROM {$table} WHERE ip = %s", self::normalize_ip( $ip ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		if ( '' !== $kind ) {
			$sql .= $wpdb->prepare( ' AND kind = %s', $kind );
		}

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
	}

	/**
	 * Daily: remove expired rules and failures older than the counting period.
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		global $wpdb;

		$now      = time();
		$blocks   = self::blocks_table();
		$failures = self::failures_table();
		$window   = max( (int) Settings::get()['failure_window'], HOUR_IN_SECONDS );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$blocks} WHERE expires_at > 0 AND expires_at <= %d", $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$failures} WHERE created_at <= %d", $now - $window ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		self::refresh_marker();
	}

	/**
	 * Store when the last active site-wide block ends (0 when there is none).
	 *
	 * @return void
	 */
	public static function refresh_marker(): void {
		global $wpdb;

		$table     = self::blocks_table();
		$permanent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE kind = %s AND expires_at = 0", self::KIND_BLOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$until     = $permanent > 0
			? self::PERMANENT_MARK
			: (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(expires_at) FROM {$table} WHERE kind = %s AND expires_at > %d", self::KIND_BLOCK, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		update_option( self::MARKER_OPTION, $until, true );
	}

	/**
	 * Whether an active site-wide block may exist, without querying the table.
	 *
	 * @return bool
	 */
	public static function may_have_site_blocks(): bool {
		return (int) get_option( self::MARKER_OPTION, 0 ) > time();
	}
}
