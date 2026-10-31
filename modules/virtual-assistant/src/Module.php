<?php
/** Virtual Assistant module bootstrap. @package DovetailStudio\VirtualAssistant */
namespace DovetailStudio\VirtualAssistant;

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
		defined( 'DOVETAIL_VIRTUAL_ASSISTANT_VERSION' ) || define(
			'DOVETAIL_VIRTUAL_ASSISTANT_VERSION',
			(string) $manifest['version']
		);
		defined( 'DOVETAIL_VIRTUAL_ASSISTANT_PATH' ) || define(
			'DOVETAIL_VIRTUAL_ASSISTANT_PATH',
			trailingslashit( dirname( __DIR__ ) )
		);
		defined( 'DOVETAIL_VIRTUAL_ASSISTANT_URL' ) || define(
			'DOVETAIL_VIRTUAL_ASSISTANT_URL',
			\DovetailStudio\Core\Loader::path_url( dirname( __DIR__ ) )
		);

		require_once __DIR__ . '/runtime.php';
	}
}
