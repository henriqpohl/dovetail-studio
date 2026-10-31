<?php
/** Newsletter module bootstrap. @package DovetailStudio\Newsletter */
namespace DovetailStudio\Newsletter;

defined( 'ABSPATH' ) || exit;

final class Module {
	private static $instance = null;
	private $booted          = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// The version comes from module.php only, so it can never drift from the manifest.
		$manifest = require dirname( __DIR__ ) . '/module.php';
		defined( 'DOVETAIL_NEWSLETTER_VERSION' ) || define(
			'DOVETAIL_NEWSLETTER_VERSION',
			(string) $manifest['version']
		);
		defined( 'DOVETAIL_NEWSLETTER_PATH' ) || define(
			'DOVETAIL_NEWSLETTER_PATH',
			trailingslashit( dirname( __DIR__ ) )
		);
		defined( 'DOVETAIL_NEWSLETTER_URL' ) || define(
			'DOVETAIL_NEWSLETTER_URL',
			\DovetailStudio\Core\Loader::path_url( dirname( __DIR__ ) )
		);

		require_once __DIR__ . '/functions.php';
	}
}
