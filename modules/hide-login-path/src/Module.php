<?php
/** Hide Login Path module bootstrap. @package DovetailStudio\HideLoginPath */
namespace DovetailStudio\HideLoginPath;

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
		defined( 'DOVETAIL_HIDE_LOGIN_PATH_VERSION' ) || define(
			'DOVETAIL_HIDE_LOGIN_PATH_VERSION',
			(string) $manifest['version']
		);

		require_once __DIR__ . '/Settings.php';
		require_once __DIR__ . '/Router.php';
		require_once __DIR__ . '/Admin.php';

		( new Router() )->register_hooks();
		( new Admin() )->register_hooks();
	}
}
