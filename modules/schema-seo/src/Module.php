<?php
/** Schema SEO module bootstrap. @package DovetailStudio\SchemaSeo */
namespace DovetailStudio\SchemaSeo;

defined( 'ABSPATH' ) || exit;

final class Module {
    private static $instance = null;
    private $booted = false;

    public static function instance(): self {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    public function boot(): void {
        if ( $this->booted ) { return; }
        $this->booted = true;

        defined( 'LBS_VERSION' ) || define( 'LBS_VERSION', '1.4.2' );
        defined( 'LBS_FILE' ) || define( 'LBS_FILE', __FILE__ );
        defined( 'LBS_PATH' ) || define( 'LBS_PATH', trailingslashit( dirname( __DIR__ ) ) );
        defined( 'LBS_URL' ) || define( 'LBS_URL', DOVETAIL_STUDIO_URL . 'modules/schema-seo/' );

        require_once __DIR__ . '/Schema.php';
        require_once __DIR__ . '/Admin.php';

        ( new Admin() )->register_hooks();
        ( new Schema() )->register_hooks();
    }
}
