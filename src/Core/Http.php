<?php
/**
 * Shared visitor IP detection and rate limiting.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Client IP and rate limits for public endpoints.
 *
 * Forwarding headers (CF-Connecting-IP, X-Forwarded-For, X-Real-IP) are only
 * believed when the direct peer (REMOTE_ADDR) is a trusted proxy: a Cloudflare
 * edge, or an address/CIDR listed in DOVETAIL_STUDIO_TRUSTED_PROXIES
 * (comma-separated, e.g. a load balancer). Anyone else can write those headers,
 * so trusting them unconditionally would let a script pick a new "IP" per
 * request and walk straight past every rate limit.
 */
final class Http {

	/**
	 * Cloudflare edge ranges (https://www.cloudflare.com/ips/), checked against that page on 25 October 2026.
	 * Cloudflare rarely changes them; add new ones with the dovetail_studio_trusted_proxies filter until
	 * the plugin is updated.
	 */
	const CLOUDFLARE_RANGES = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * The visitor IP for rate limiting and logging.
	 *
	 * @return string Valid IP, or 'unknown'.
	 */
	public static function client_ip(): string {
		static $ip = null;

		if ( null !== $ip ) {
			return $ip;
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with filter_var below.
		$ip     = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';

		if ( 'unknown' !== $ip && self::is_trusted_proxy( $ip ) ) {
			$forwarded = self::forwarded_ip( $ip );

			if ( '' !== $forwarded ) {
				$ip = $forwarded;
			}
		}

		/**
		 * Filters the visitor IP used by Dovetail Studio modules.
		 *
		 * @param string $ip     Detected IP.
		 * @param string $remote Direct peer (REMOTE_ADDR).
		 */
		$ip = (string) apply_filters( 'dovetail_studio_client_ip', $ip, $remote );

		return $ip;
	}

	/**
	 * Counts one hit in a fixed window and reports whether the limit still allows it.
	 *
	 * The count is incremented atomically, so simultaneous requests cannot slip
	 * past the limit by reading the same old value: with a persistent object
	 * cache (Redis, Memcached) through wp_cache_incr(), otherwise in one
	 * non-autoloaded option row per window (INSERT … ON DUPLICATE KEY UPDATE).
	 * The row name carries its expiry, so expired rows are removed in bulk.
	 *
	 * @param string $bucket Bucket name, e.g. 'va_ask'.
	 * @param int    $limit  Allowed hits per window. 0 or less disables the limit.
	 * @param int    $window Window length in seconds.
	 * @param string $key    Who is counted. Defaults to the client IP; pass 'global' for a site-wide cap.
	 * @return bool True when the hit is allowed.
	 */
	public static function rate_limit( string $bucket, int $limit, int $window = HOUR_IN_SECONDS, string $key = '' ): bool {
		if ( $limit <= 0 ) {
			return true;
		}

		$window  = max( 1, $window );
		$who     = '' === $key ? self::client_ip() : $key;
		$now     = time();
		$start   = $now - ( $now % $window );
		$expires = $start + $window;
		$id      = substr( md5( $bucket . '|' . $who . '|' . $start ), 0, 20 );

		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $id, 0, 'dovetail_rl', $expires - $now );
			$count = wp_cache_incr( $id, 1, 'dovetail_rl' );

			if ( false !== $count ) {
				return (int) $count <= $limit;
			}
		}

		global $wpdb;
		$name = 'dovetail_rl_' . $expires . '_' . $id;
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = option_value + 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic counter.
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counter just written.

		// Now and then, drop the rows of windows that have ended.
		if ( 1 === wp_rand( 1, 50 ) ) {
			self::purge_rate_limits();
		}

		return $count <= $limit;
	}

	/**
	 * Deletes the counter rows of rate-limit windows that have ended.
	 *
	 * @return void
	 */
	public static function purge_rate_limits(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s", $wpdb->esc_like( 'dovetail_rl_' ) . '%', 'dovetail_rl_' . time() . '_' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk clean-up of expired counters.
	}

	/**
	 * Whether an address is a trusted reverse proxy.
	 *
	 * @param string $ip Direct peer.
	 * @return bool
	 */
	public static function is_trusted_proxy( string $ip ): bool {
		$ranges = self::CLOUDFLARE_RANGES;

		if ( defined( 'DOVETAIL_STUDIO_TRUSTED_PROXIES' )
			&& '' !== (string) DOVETAIL_STUDIO_TRUSTED_PROXIES
		) {
			$ranges = array_merge(
				$ranges,
				array_filter( array_map( 'trim', explode( ',', (string) DOVETAIL_STUDIO_TRUSTED_PROXIES ) ) )
			);
		}

		/**
		 * Filters the trusted proxy addresses and CIDR ranges.
		 *
		 * @param string[] $ranges Addresses or CIDR ranges.
		 */
		$ranges = (array) apply_filters( 'dovetail_studio_trusted_proxies', $ranges );

		foreach ( $ranges as $range ) {
			if ( is_string( $range ) && self::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an IP is inside an address or CIDR range (IPv4 or IPv6).
	 *
	 * @param string $ip    IP.
	 * @param string $range Address or CIDR.
	 * @return bool
	 */
	public static function ip_in_range( string $ip, string $range ): bool {
		$parts  = explode( '/', $range, 2 );
		$subnet = @inet_pton( $parts[0] ); // Invalid input returns false.
		$addr   = @inet_pton( $ip ); // Invalid input returns false.

		if ( false === $subnet || false === $addr || strlen( $subnet ) !== strlen( $addr ) ) {
			return false;
		}

		$max  = strlen( $addr ) * 8;
		$bits = isset( $parts[1] ) ? (int) $parts[1] : $max;

		if ( $bits < 0 || $bits > $max ) {
			return false;
		}

		$bytes = intdiv( $bits, 8 );

		if ( $bytes > 0 && 0 !== substr_compare( $addr, $subnet, 0, $bytes ) ) {
			return false;
		}

		$rest = $bits % 8;

		if ( 0 === $rest ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;

		return ( ord( $addr[ $bytes ] ) & $mask ) === ( ord( $subnet[ $bytes ] ) & $mask );
	}

	/**
	 * Whether an address is a Cloudflare edge.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_cloudflare( string $ip ): bool {
		foreach ( self::CLOUDFLARE_RANGES as $range ) {
			if ( self::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The client address reported by the trusted proxy.
	 *
	 * Cloudflare always sets CF-Connecting-IP and overwrites any value the
	 * visitor sent, so behind Cloudflare that header is the only one read.
	 * X-Real-IP and X-Forwarded-For are read only from the proxies listed in
	 * DOVETAIL_STUDIO_TRUSTED_PROXIES, where the site owner controls them.
	 *
	 * @param string $peer Direct peer (REMOTE_ADDR), already known to be trusted.
	 * @return string Valid IP or empty string.
	 */
	private static function forwarded_ip( string $peer ): string {
		if ( self::is_cloudflare( $peer ) ) {
			$value = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with filter_var.

			return filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
		}

		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$value = trim( (string) wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with filter_var.

			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				return $value;
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Walk right to left and return the first hop that is not itself a trusted proxy.
			$hops = array_reverse( array_map( 'trim', explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each hop validated with filter_var.

			foreach ( $hops as $hop ) {
				if ( ! filter_var( $hop, FILTER_VALIDATE_IP ) ) {
					break;
				}

				if ( ! self::is_trusted_proxy( $hop ) ) {
					return $hop;
				}
			}
		}

		return '';
	}
}
