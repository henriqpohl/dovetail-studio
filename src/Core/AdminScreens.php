<?php
/**
 * Dovetail Studio admin screens: shared styles and a notice-free workspace.
 *
 * @package DovetailStudio\Core
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Knows which admin screens belong to Dovetail Studio and keeps them clean.
 *
 * A screen belongs to the Studio when it is one of the Studio's own pages or
 * is declared by an enabled module in its manifest:
 *
 *     'admin_screens' => array(
 *         'pages'      => array( 'dovetail-newsletter' ),  // admin.php?page=… slugs
 *         'post_types' => array( 'dt_enquiry' ),           // list, edit and new screens
 *     ),
 *
 * On those screens every WordPress and third-party admin notice is removed,
 * including update and security nags; they keep showing everywhere else in
 * WP Admin. Studio and module messages go through notice() /
 * settings_errors() or the `dovetail_studio_notices` action, so they stay
 * visible.
 */
final class AdminScreens {

	/** Pages owned by the Studio itself. */
	const CORE_PAGES = array(
		'dovetail-studio',
		'dovetail-studio-modules',
	);

	/** @var AdminScreens|null */
	private static $instance = null;

	/** @var string[] */
	private $pages = array();

	/** @var string[] */
	private $post_types = array();

	/** @var bool|null Null until the current screen is known. */
	private $current = null;

	/** @return AdminScreens */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the screens of the enabled modules and the admin hooks.
	 *
	 * @param array<string,array<string,mixed>> $modules Manifests of the enabled modules.
	 */
	public function boot( array $modules ) {
		$this->pages = self::CORE_PAGES;

		foreach ( $modules as $module ) {
			$screens = isset( $module['admin_screens'] ) && is_array( $module['admin_screens'] )
				? $module['admin_screens']
				: array();

			foreach ( array( 'pages', 'post_types' ) as $kind ) {
				if ( ! empty( $screens[ $kind ] ) && is_array( $screens[ $kind ] ) ) {
					$this->$kind = array_merge(
						$this->$kind,
						array_map( 'sanitize_key', $screens[ $kind ] )
					);
				}
			}
		}

		$this->pages      = array_values( array_unique( $this->pages ) );
		$this->post_types = array_values( array_unique( $this->post_types ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_head', array( $this, 'clear_notices' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Whether the current admin request is a Dovetail Studio screen.
	 *
	 * @return bool
	 */
	public function is_current() {
		if ( null !== $this->current ) {
			return $this->current;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
		$is   = '' !== $page && in_array( $page, $this->pages, true );

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $is
			&& $screen
			&& ! empty( $screen->post_type )
			&& in_array( $screen->post_type, $this->post_types, true )
			&& in_array( $screen->base, array( 'edit', 'post' ), true )
		) {
			$is = true;
		}

		/**
		 * Filters whether the current admin screen is treated as a Dovetail Studio screen.
		 *
		 * @param bool            $is     Whether it is.
		 * @param \WP_Screen|null $screen Current screen.
		 */
		$is = (bool) apply_filters( 'dovetail_studio_is_admin_screen', $is, $screen );

		// Cache once the screen is known; before that, the answer may still change.
		if ( $screen ) {
			$this->current = $is;
		}

		return $is;
	}

	/** Load the shared Dovetail admin design system and its small shared script. */
	public function enqueue_assets() {
		if ( ! $this->is_current() ) {
			return;
		}

		$script = Loader::asset( DOVETAIL_STUDIO_PATH, 'assets/admin/dovetail-admin.js' );

		wp_enqueue_style(
			'dovetail-studio-admin',
			DOVETAIL_STUDIO_URL . 'assets/admin/dovetail-admin.css',
			array(),
			DOVETAIL_STUDIO_VERSION
		);
		wp_enqueue_script(
			'dovetail-studio-admin',
			$script['url'],
			array(),
			$script['ver'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Remove every WordPress and third-party notice from Studio screens and
	 * print only the Studio's own (`dovetail_studio_notices`).
	 */
	public function clear_notices() {
		if ( ! $this->is_current() ) {
			return;
		}

		foreach ( array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' ) as $hook ) {
			remove_all_actions( $hook );
		}

		add_action( 'all_admin_notices', array( $this, 'print_studio_notices' ) );
	}

	/** Print the notices modules attach to `dovetail_studio_notices`. */
	public function print_studio_notices() {
		/**
		 * Prints Dovetail Studio notices at the top of every Studio screen.
		 * Use AdminScreens::notice() inside the callback.
		 */
		do_action( 'dovetail_studio_notices' );
	}

	/**
	 * Body class that hides notices injected outside the notice hooks (see dovetail-admin.css).
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $this->is_current() ? trim( $classes . ' dovetail-admin-page' ) : $classes;
	}

	/**
	 * Print a Studio notice. It stays visible on Studio screens, where other notices are hidden.
	 *
	 * @param string $type        success, error, warning or info.
	 * @param string $message     Message HTML, already escaped by the caller.
	 * @param bool   $dismissible Whether it can be dismissed.
	 */
	public static function notice( $type, $message, $dismissible = false ) {
		View::render(
			__DIR__,
			'notice',
			array(
				'type'        => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
				'message'     => $message,
				'dismissible' => (bool) $dismissible,
			)
		);
	}

	/**
	 * Print the Settings API messages (validation errors and "Settings saved.").
	 *
	 * Studio pages are not under Settings, so WordPress does not print them, and
	 * its own markup would be hidden on Studio screens.
	 *
	 * @param string $setting Optional setting name; all messages when empty.
	 */
	public static function settings_errors( $setting = '' ) {
		foreach ( (array) get_settings_errors( $setting ) as $error ) {
			$type = isset( $error['type'] ) ? (string) $error['type'] : 'error';
			$type = 'updated' === $type ? 'success' : $type;
			self::notice( $type, esc_html( (string) $error['message'] ), true );
		}
	}
}
