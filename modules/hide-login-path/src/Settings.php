<?php
/**
 * Hide Login Path settings.
 *
 * @package DovetailStudio\HideLoginPath
 */

namespace DovetailStudio\HideLoginPath;

defined( 'ABSPATH' ) || exit;

/**
 * One option row, `dovetail_hide_login_path_settings`, read once per request.
 */
final class Settings {

	const OPTION = 'dovetail_hide_login_path_settings';

	/** Paths bots try first or WordPress answers itself. Paths starting with `wp-` are refused too. */
	const RESERVED = array( 'admin', 'login', 'log-in', 'signin', 'sign-in', 'dashboard', 'user', 'account', 'feed', 'embed', 'trackback', 'xmlrpc', 'sitemap', 'robots', 'favicon', 'page', 'comments', 'search', 'author' );

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
			'slug'          => '',
			'redirect_slug' => '404',
		);
	}

	/**
	 * Saved settings merged over the defaults. The URL filters call this many times per request.
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
	 * Whether the login is switched off by DOVETAIL_HIDE_LOGIN_PATH_DISABLED in wp-config.php.
	 *
	 * @return bool
	 */
	public static function disabled(): bool {
		return defined( 'DOVETAIL_HIDE_LOGIN_PATH_DISABLED' ) && DOVETAIL_HIDE_LOGIN_PATH_DISABLED;
	}

	/**
	 * Whether the login is moved: a path is saved and not disabled in wp-config.php. Enabling the
	 * module is the switch; it acts as soon as a login path is saved.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		$settings = self::get();

		return ! self::disabled() && '' !== (string) $settings['slug'];
	}

	/**
	 * A typed path reduced to a slug: lowercase letters, numbers and hyphens.
	 *
	 * @param mixed $value Typed value.
	 * @return string
	 */
	public static function to_slug( $value ): string {
		return trim( sanitize_title_with_dashes( is_scalar( $value ) ? (string) $value : '', '', 'save' ), '-' );
	}

	/**
	 * Why a login path cannot be used, or an empty string when it can.
	 *
	 * @param string $slug Login path.
	 * @return string
	 */
	public static function slug_problem( string $slug ): string {
		if ( '' === $slug ) {
			return '';
		}

		$reserved = self::RESERVED;

		// WordPress's query variables (`p`, `s`, `page_id`…) change what the home page shows.
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->public_query_vars ) ) {
			$reserved = array_merge( $reserved, (array) $GLOBALS['wp']->public_query_vars );
		}

		if ( 0 === strpos( $slug, 'wp-' ) || in_array( $slug, $reserved, true ) ) {
			/* translators: %s: the login path. */
			return sprintf( __( 'The login path "%s" was not saved: WordPress uses it or bots try it first. Choose another one.', 'dovetail-studio' ), $slug );
		}

		if ( get_page_by_path( $slug, OBJECT, array_values( get_post_types( array( 'public' => true ) ) ) ) ) {
			/* translators: %s: the login path. */
			return sprintf( __( 'The login path "%s" was not saved: a page or post already has this address.', 'dovetail-studio' ), $slug );
		}

		return '';
	}

	/**
	 * Sanitize the settings form. A refused path keeps the saved one.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$saved    = self::get();
		$slug     = self::to_slug( $input['slug'] ?? '' );
		$redirect = self::to_slug( $input['redirect_slug'] ?? '' );
		$problem  = self::slug_problem( $slug );
		$clean    = array(
			'slug'          => (string) $saved['slug'],
			'redirect_slug' => (string) $saved['redirect_slug'],
		);

		if ( '' !== $problem ) {
			add_settings_error( self::OPTION, 'slug', $problem );
		} else {
			$clean['slug'] = $slug;
		}

		if ( '' !== $redirect && $redirect === $clean['slug'] ) {
			add_settings_error( self::OPTION, 'redirect_slug', __( 'The redirect was not saved: it cannot be the login path.', 'dovetail-studio' ) );
		} else {
			$clean['redirect_slug'] = $redirect;
		}

		return $clean;
	}
}
