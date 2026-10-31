<?php
/** Login Guard module bootstrap. @package DovetailStudio\LoginGuard */
namespace DovetailStudio\LoginGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the module files and registers their hooks.
 */
final class Module {

	/** @var Module|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/**
	 * The module instance.
	 *
	 * @return Module
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define the constants, load the files and register the hooks. Called once by the Studio loader.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// The version comes from module.php only, so it can never drift from the manifest.
		$manifest = require dirname( __DIR__ ) . '/module.php';
		defined( 'DOVETAIL_LOGIN_GUARD_VERSION' ) || define(
			'DOVETAIL_LOGIN_GUARD_VERSION',
			(string) $manifest['version']
		);
		defined( 'DOVETAIL_LOGIN_GUARD_PATH' ) || define(
			'DOVETAIL_LOGIN_GUARD_PATH',
			trailingslashit( dirname( __DIR__ ) )
		);
		defined( 'DOVETAIL_LOGIN_GUARD_URL' ) || define(
			'DOVETAIL_LOGIN_GUARD_URL',
			\DovetailStudio\Core\Loader::path_url( dirname( __DIR__ ) )
		);

		require_once __DIR__ . '/Settings.php';
		require_once __DIR__ . '/Store.php';
		require_once __DIR__ . '/Guard.php';
		require_once __DIR__ . '/Hardening.php';
		require_once __DIR__ . '/Passwords.php';
		require_once __DIR__ . '/Activity.php';
		require_once __DIR__ . '/Alerts.php';
		require_once __DIR__ . '/QrCode.php';
		require_once __DIR__ . '/TwoFactor.php';
		require_once __DIR__ . '/Captcha.php';
		require_once __DIR__ . '/Admin.php';

		add_action( 'plugins_loaded', array( Store::class, 'maybe_install' ) );
		Guard::instance()->register_hooks();
		( new Hardening() )->register_hooks();
		( new Passwords() )->register_hooks();
		( new Activity() )->register_hooks();
		Alerts::instance()->register_hooks();
		( new TwoFactor() )->register_hooks();
		( new Captcha() )->register_hooks();
		( new Admin() )->register_hooks();

		add_action( Store::CLEANUP_HOOK, array( Store::class, 'cleanup' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/Cli.php';
			\WP_CLI::add_command( 'dovetail login-guard', Cli::class );
		}
	}

	/**
	 * Schedule the daily removal of expired blocks and old failure records.
	 *
	 * @return void
	 */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( Store::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Store::CLEANUP_HOOK );
		}
	}
}
