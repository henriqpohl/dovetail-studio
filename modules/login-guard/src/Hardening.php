<?php
/**
 * Login Guard hardening: login error messages, user enumeration, registrations and application passwords.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Small WordPress hardening switches, each one a setting.
 */
final class Hardening {
	/**
	 * Register the hooks. Each callback checks its own switch, so nothing is read at load.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'authenticate', array( $this, 'generic_login_error' ), 99 );
		add_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );
		add_action( 'template_redirect', array( $this, 'block_author_scan' ), 5 );
		add_filter( 'rest_pre_dispatch', array( $this, 'block_rest_users' ), 10, 3 );
		add_filter( 'oembed_response_data', array( $this, 'strip_oembed_author' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'remove_users_sitemap' ), 10, 2 );
		add_filter( 'wp_is_application_passwords_available', array( $this, 'application_passwords_available' ) );
		add_filter( 'xmlrpc_enabled', array( $this, 'xmlrpc_auth_enabled' ) );
		add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
	}

	/**
	 * XML-RPC methods that need a sign-in (posting, editing, and wp.getUsersBlogs, which bots use to try
	 * many passwords in one request through system.multicall). The `xmlrpc_enabled` filter only covers those.
	 *
	 * @param bool $enabled Whether they are enabled.
	 * @return bool
	 */
	public function xmlrpc_auth_enabled( $enabled ): bool {
		return ( $this->on( 'disable_xmlrpc_auth' ) || $this->on( 'disable_xmlrpc' ) ) ? false : (bool) $enabled;
	}

	/**
	 * Switch XML-RPC off completely, pingbacks included.
	 *
	 * @param array<string,mixed> $methods XML-RPC methods.
	 * @return array<string,mixed>
	 */
	public function xmlrpc_methods( $methods ): array {
		return $this->on( 'disable_xmlrpc' ) ? array() : (array) $methods;
	}

	/**
	 * With XML-RPC off, stop advertising its pingback endpoint.
	 *
	 * @param array<string,string> $headers Response headers.
	 * @return array<string,string>
	 */
	public function remove_pingback_header( $headers ): array {
		$headers = (array) $headers;

		if ( $this->on( 'disable_xmlrpc' ) ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
	}

	/**
	 * Whether a hardening switch is on.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private function on( string $key ): bool {
		return ! empty( Settings::get()[ $key ] );
	}

	/**
	 * Application passwords are switched off when the setting says so.
	 *
	 * @param bool $available Whether they are available.
	 * @return bool
	 */
	public function application_passwords_available( $available ): bool {
		return $this->on( 'disable_application_passwords' ) ? false : (bool) $available;
	}

	/**
	 * Same message for an unknown username and a wrong password. The error code is kept,
	 * so brute force protection still knows which one it was.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public function generic_login_error( $user ) {
		if ( ! $user instanceof \WP_Error || ! $this->on( 'hide_login_errors' ) ) {
			return $user;
		}

		$code = (string) $user->get_error_code();

		if ( ! in_array( $code, array( 'invalid_username', 'invalid_email', 'incorrect_password' ), true ) ) {
			return $user;
		}

		return new \WP_Error(
			$code,
			'<strong>' . esc_html__( 'Error:', 'dovetail-studio' ) . '</strong> ' . esc_html__( 'The username, email address or password is incorrect.', 'dovetail-studio' )
		);
	}

	/**
	 * Nobody can register the username "admin" while no such account exists.
	 *
	 * @param string[] $logins Disallowed usernames.
	 * @return string[]
	 */
	public function illegal_user_logins( $logins ): array {
		$logins = (array) $logins;

		if ( $this->on( 'prevent_admin_registration' ) && ! username_exists( 'admin' ) ) {
			$logins[] = 'admin';
		}

		return array_values( array_unique( $logins ) );
	}

	/**
	 * `/?author=N` scans reveal usernames through the redirect to the author archive.
	 *
	 * @return void
	 */
	public function block_author_scan(): void {
		if ( ! $this->on( 'prevent_user_enumeration' ) || is_admin() || is_user_logged_in() || ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of a public query var.
			return;
		}

		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}

	/**
	 * The REST API lists users (and their usernames as slugs) to visitors; signed-in users keep it,
	 * since the block editor needs it.
	 *
	 * @param mixed            $result  Response so far.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function block_rest_users( $result, $server, $request ) {
		if ( null !== $result || ! $this->on( 'prevent_user_enumeration' ) || is_user_logged_in() || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}

		if ( preg_match( '#^/wp/v2/users(/|$)#', (string) $request->get_route() ) ) {
			return new \WP_Error(
				'rest_user_cannot_view',
				__( 'Sorry, you are not allowed to list users.', 'dovetail-studio' ),
				array( 'status' => 401 )
			);
		}

		return $result;
	}

	/**
	 * oEmbed responses name the post author and link to the author archive.
	 *
	 * @param array<string,mixed> $data Response data.
	 * @return array<string,mixed>
	 */
	public function strip_oembed_author( $data ) {
		if ( is_array( $data ) && $this->on( 'prevent_user_enumeration' ) ) {
			unset( $data['author_name'], $data['author_url'] );
		}

		return $data;
	}

	/**
	 * The users sitemap lists every author archive URL.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                      $name     Provider name.
	 * @return \WP_Sitemaps_Provider|false
	 */
	public function remove_users_sitemap( $provider, $name ) {
		return 'users' === $name && $this->on( 'prevent_user_enumeration' ) ? false : $provider;
	}
}
