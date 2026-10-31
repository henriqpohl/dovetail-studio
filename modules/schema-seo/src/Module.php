<?php
/** Schema SEO module bootstrap. @package DovetailStudio\SchemaSeo */
namespace DovetailStudio\SchemaSeo;

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
		defined( 'DOVETAIL_SCHEMA_SEO_VERSION' ) || define(
			'DOVETAIL_SCHEMA_SEO_VERSION',
			(string) $manifest['version']
		);
		defined( 'DOVETAIL_SCHEMA_SEO_FILE' ) || define( 'DOVETAIL_SCHEMA_SEO_FILE', __FILE__ );
		defined( 'DOVETAIL_SCHEMA_SEO_PATH' ) || define(
			'DOVETAIL_SCHEMA_SEO_PATH',
			trailingslashit( dirname( __DIR__ ) )
		);
		defined( 'DOVETAIL_SCHEMA_SEO_URL' ) || define(
			'DOVETAIL_SCHEMA_SEO_URL',
			\DovetailStudio\Core\Loader::path_url( dirname( __DIR__ ) )
		);

		require_once __DIR__ . '/Schema.php';
		require_once __DIR__ . '/SiteGraph.php';
		require_once __DIR__ . '/Breadcrumbs.php';
		require_once __DIR__ . '/Admin.php';
		require_once __DIR__ . '/functions.php';

		( new Admin() )->register_hooks();
		( new Schema() )->register_hooks();
		( new SiteGraph() )->register_hooks();
		Breadcrumbs::instance()->register_hooks();
	}
}
