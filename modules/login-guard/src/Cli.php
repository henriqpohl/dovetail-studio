<?php
/**
 * Login Guard WP-CLI commands.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Manage the Login Guard block list and two-factor resets from the command
 * line, for example after locking yourself out:
 *
 *     wp dovetail login-guard unblock 203.0.113.10
 */
final class Cli {
	/**
	 * List the active blocks and lockouts.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int,string>    $args       Positional arguments.
	 * @param array<string,string> $assoc_args Options.
	 * @return void
	 */
	public function list( $args, $assoc_args ): void {
		$rows = array();

		foreach ( Store::query( '', 'all', 1, 0 )['rows'] as $row ) {
			$rows[] = array(
				'ip'       => $row['ip'],
				'type'     => $row['kind'],
				'source'   => $row['source'],
				'reason'   => $row['reason'],
				'username' => $row['username'],
				'expires'  => 0 === (int) $row['expires_at'] ? 'permanent' : wp_date( 'Y-m-d H:i:s', (int) $row['expires_at'] ),
				'hits'     => (int) $row['hits'],
			);
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'ip', 'type', 'source', 'reason', 'username', 'expires', 'hits' ) );
	}

	/**
	 * Unblock IP addresses.
	 *
	 * ## OPTIONS
	 *
	 * <ip>...
	 * : One or more IP addresses.
	 *
	 * @param array<int,string> $args IP addresses.
	 * @return void
	 */
	public function unblock( $args ): void {
		foreach ( $args as $ip ) {
			if ( Store::delete_ip( $ip ) ) {
				\WP_CLI::success( "Unblocked {$ip}." );
			} else {
				Store::clear_failures( $ip );
				\WP_CLI::warning( "{$ip} was not blocked; its failed attempts were cleared." );
			}
		}
	}

	/**
	 * Block an IP address from the whole site.
	 *
	 * ## OPTIONS
	 *
	 * <ip>
	 * : IP address.
	 *
	 * [--reason=<reason>]
	 * : Reason shown in the block list.
	 *
	 * [--duration=<seconds>]
	 * : Seconds until the block ends; 0 for permanent.
	 * ---
	 * default: 0
	 * ---
	 *
	 * @param array<int,string>    $args       IP address.
	 * @param array<string,string> $assoc_args Options.
	 * @return void
	 */
	public function block( $args, $assoc_args ): void {
		$ip = Store::normalize_ip( (string) $args[0] );

		if ( '' === $ip ) {
			\WP_CLI::error( 'Enter a valid IPv4 or IPv6 address.' );
		}

		Store::add( $ip, Store::KIND_BLOCK, 'manual', (string) ( $assoc_args['reason'] ?? 'Manual block' ), absint( $assoc_args['duration'] ?? 0 ) );
		\WP_CLI::success( "Blocked {$ip}." );
	}

	/**
	 * Remove every block and lockout.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * @param array<int,string>    $args       Positional arguments.
	 * @param array<string,string> $assoc_args Options.
	 * @return void
	 */
	public function clear( $args, $assoc_args ): void {
		\WP_CLI::confirm( 'Remove every Login Guard block and lockout?', $assoc_args );

		$ids = array_map( 'intval', array_column( Store::query( '', 'all', 1, 0 )['rows'], 'id' ) );
		\WP_CLI::success( sprintf( 'Removed %d blocks.', Store::delete( $ids ) ) );
	}

	/**
	 * Turn two-factor authentication off for a user who lost their phone and recovery codes.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, username or email address.
	 *
	 * @subcommand reset-2fa
	 *
	 * @param array<int,string> $args User.
	 * @return void
	 */
	public function reset_2fa( $args ): void {
		$value = (string) $args[0];
		$user  = is_numeric( $value ) ? get_user_by( 'id', (int) $value ) : ( get_user_by( 'login', $value ) ? get_user_by( 'login', $value ) : get_user_by( 'email', $value ) );

		if ( ! $user ) {
			\WP_CLI::error( "User {$value} not found." );
		}

		TwoFactor::reset( (int) $user->ID );
		\WP_CLI::success( "Two-factor authentication reset for {$user->user_login}." );
	}
}
