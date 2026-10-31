<?php
/**
 * Shared alert email format.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text alert emails in one format for every module (modelled on
 * Wordfence's alerts):
 *
 *     Subject: [Login Guard Alert] example.com Admin Login
 *
 *     This email was sent from your website "Example" by the Login Guard module of Dovetail Studio at …
 *     The Login Guard URL for this site is: …
 *
 *     (the module's lines)
 *
 *     --
 *     Change or switch off these emails: …
 */
final class Mail {
	/**
	 * Send an alert.
	 *
	 * @param string   $module       Module name, e.g. "Login Guard".
	 * @param string   $title        Alert title appended to the subject, e.g. "Admin Login".
	 * @param string[] $lines        Body lines.
	 * @param string[] $recipients   Email addresses; empty sends to the site's admin email.
	 * @param string   $module_url   Admin URL of the module.
	 * @param string   $settings_url Admin URL where these emails are changed.
	 * @return bool Whether wp_mail() accepted the email.
	 */
	public static function alert( string $module, string $title, array $lines, array $recipients, string $module_url, string $settings_url ): bool {
		$site       = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$domain     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$recipients = array_values( array_filter( $recipients, 'is_email' ) );
		$header     = array(
			/* translators: 1: site name, 2: module name, 3: date and time. */
			sprintf( __( 'This email was sent from your website "%1$s" by the %2$s module of Dovetail Studio at %3$s.', 'dovetail-studio' ), $site, $module, wp_date( self::date_format() ) ),
			/* translators: 1: module name, 2: admin URL. */
			sprintf( __( 'The %1$s URL for this site is: %2$s', 'dovetail-studio' ), $module, $module_url ),
			'',
		);
		$footer     = array(
			'',
			'--',
			/* translators: %s: URL of the email settings. */
			sprintf( __( 'Change or switch off these emails: %s', 'dovetail-studio' ), $settings_url ),
		);

		return wp_mail(
			$recipients ? $recipients : array( (string) get_option( 'admin_email' ) ),
			/* translators: 1: module name, 2: site domain, 3: alert title. */
			sprintf( __( '[%1$s Alert] %2$s %3$s', 'dovetail-studio' ), $module, $domain, $title ),
			implode( "\n", array_merge( $header, $lines, $footer ) )
		);
	}

	/**
	 * The site's date and time format.
	 *
	 * @return string
	 */
	public static function date_format(): string {
		return get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
	}
}
