<?php
/**
 * Hide Login Path routing: the new login address, the hidden wp-login.php and wp-admin, and URL rewriting.
 *
 * @package DovetailStudio\HideLoginPath
 */

namespace DovetailStudio\HideLoginPath;

defined( 'ABSPATH' ) || exit;

/**
 * Serves wp-login.php at the saved path and sends visitors of wp-login.php,
 * wp-signup.php, wp-activate.php and wp-admin (when signed out) to the redirect.
 *
 * 1. `plugins_loaded` (late): the request is classified. A request for the
 *    login path is marked as wp-login.php in `$pagenow`, so code that checks
 *    for the login screen (captcha, security plugins) still recognises it.
 * 2. `wp_loaded`: the hidden scripts and signed-out wp-admin are redirected;
 *    the login path loads wp-login.php and stops there.
 * 3. `site_url`, `network_site_url` and `wp_redirect`: every wp-login.php URL
 *    WordPress prints or redirects to (login, logout, lost password, emails)
 *    points to the login path, with its query string kept as it was.
 *
 * Nothing changes for WP-CLI or when DOVETAIL_HIDE_LOGIN_PATH_DISABLED is true
 * in wp-config.php (the way back in after forgetting the path).
 */
final class Router {

	/** @var bool The request is for wp-login.php or another script that leads to it. */
	private $hidden_request = false;

	/** @var bool The request is for the login path. */
	private $login_request = false;

	/**
	 * Register the hooks. Each callback checks whether the login is moved, so nothing is read at load.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'detect' ), 9999 );
		add_action( 'wp_loaded', array( $this, 'route' ) );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 3 );
		add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ) );
	}

	/**
	 * Whether the module acts on this request.
	 *
	 * @return bool
	 */
	private function applies(): bool {
		return Settings::active() && ! ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * The login address, `https://example.com/my-login/` (or `/?my-login` without pretty permalinks).
	 *
	 * @param string|null $scheme URL scheme, as for home_url().
	 * @return string
	 */
	public static function login_url( $scheme = null ): string {
		return self::path_url( (string) Settings::get()['slug'], $scheme );
	}

	/**
	 * Where visitors of the hidden addresses are sent: the redirect path, or the home page when empty.
	 *
	 * @return string
	 */
	public static function redirect_url(): string {
		$slug = (string) Settings::get()['redirect_slug'];

		return '' === $slug ? home_url( '/' ) : self::path_url( $slug );
	}

	/**
	 * URL of a path on the home URL, following the permalink settings.
	 *
	 * @param string      $slug   Path.
	 * @param string|null $scheme URL scheme, as for home_url().
	 * @return string
	 */
	private static function path_url( string $slug, $scheme = null ): string {
		if ( get_option( 'permalink_structure' ) ) {
			return user_trailingslashit( home_url( '/' . $slug, $scheme ) );
		}

		return home_url( '/', $scheme ) . '?' . $slug;
	}

	/**
	 * The path of this request without the trailing slash. Only compared with the saved path.
	 *
	 * @return string
	 */
	private static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared with the sanitized login path.

		return untrailingslashit( rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) ) );
	}

	/**
	 * Entry scripts that show or lead to the login form. On multisite the sign-up and activation
	 * pages belong to the network, so only wp-login.php is hidden there.
	 *
	 * @return string[]
	 */
	private static function hidden_scripts(): array {
		return is_multisite() ? array( 'wp-login.php' ) : array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' );
	}

	/**
	 * Whether this request is for the login path.
	 *
	 * @return bool
	 */
	private static function is_login_path(): bool {
		$slug = (string) Settings::get()['slug'];

		if ( get_option( 'permalink_structure' ) ) {
			$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

			return self::request_path() === $home . '/' . $slug;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only.
		return isset( $_GET[ $slug ] ) && '' === $_GET[ $slug ];
	}

	/**
	 * Classify the request before WordPress handles it.
	 *
	 * @return void
	 */
	public function detect(): void {
		global $pagenow;

		if ( ! $this->applies() ) {
			return;
		}

		// WordPress's /login, /admin and /dashboard shortcuts redirect to the login URL and would reveal it.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		if ( ! is_admin() && in_array( (string) $pagenow, self::hidden_scripts(), true ) ) {
			$this->hidden_request = true;

			return;
		}

		if ( self::is_login_path() ) {
			$this->login_request = true;
			$pagenow             = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The login path is wp-login.php for every other check.
		}
	}

	/**
	 * What to do with this request: `login` (show wp-login.php), `dashboard` (already signed in),
	 * `hide` (send to the redirect) or an empty string (nothing).
	 *
	 * @return string
	 */
	public function decide(): string {
		global $pagenow;

		if ( ! $this->applies() ) {
			return '';
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Routing only; wp-login.php checks its own forms.
		if ( $this->hidden_request ) {
			// Password-protected posts post their form to wp-login.php?action=postpass.
			return isset( $_GET['action'] ) && 'postpass' === $_GET['action'] ? '' : 'hide';
		}

		if ( $this->login_request ) {
			$args = array_diff( array_keys( $_GET ), array( (string) Settings::get()['slug'] ) );

			return is_user_logged_in() && ! $args && empty( $_POST ) ? 'dashboard' : 'login';
		}

		// phpcs:enable

		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && 'admin-post.php' !== $pagenow ) {
			return 'hide';
		}

		return '';
	}

	/**
	 * Act on the decision once WordPress is loaded.
	 *
	 * @return void
	 */
	public function route(): void {
		switch ( $this->decide() ) {
			case 'hide':
				nocache_headers();
				wp_safe_redirect( self::redirect_url() );
				exit;
			case 'dashboard':
				wp_safe_redirect( admin_url() );
				exit;
			case 'login':
				$this->load_login();
		}
	}

	/**
	 * Run wp-login.php for the login path.
	 *
	 * @return void
	 */
	private function load_login(): void {
		// wp-login.php keeps its state in globals that its own functions read.
		global $error, $interim_login, $action, $user_login;

		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * A wp-login.php URL moved to the login path, with its query string kept as it was.
	 * The password form of protected posts keeps posting to wp-login.php.
	 *
	 * @param string      $url    URL.
	 * @param string|null $scheme URL scheme, as for home_url().
	 * @return string
	 */
	public function replace_login_url( string $url, $scheme = null ): string {
		if ( false === strpos( $url, 'wp-login.php' ) || ! $this->applies() ) {
			return $url;
		}

		if ( 'wp-login.php' !== basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
			return $url;
		}

		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );

		if ( isset( $args['action'] ) && 'postpass' === $args['action'] ) {
			return $url;
		}

		$base = self::login_url( $scheme );

		return '' === $query ? $base : $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . $query;
	}

	/**
	 * `site_url` and `network_site_url`: wp_login_url(), wp_logout_url(), the lost password and
	 * registration links, and the links in WordPress's emails.
	 *
	 * @param string      $url    URL.
	 * @param string      $path   Path requested.
	 * @param string|null $scheme URL scheme.
	 * @return string
	 */
	public function filter_site_url( $url, $path = '', $scheme = null ): string {
		return $this->replace_login_url( (string) $url, $scheme );
	}

	/**
	 * `wp_redirect`: redirects to wp-login.php, such as wp-login.php's own `?checkemail=confirm`.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public function filter_redirect( $location ): string {
		return $this->replace_login_url( (string) $location );
	}
}
