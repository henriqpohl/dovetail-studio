<?php
/** Virtual Assistant module bootstrap. @package DovetailStudio\VirtualAssistant */
namespace DovetailStudio\VirtualAssistant;

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

        defined( 'DOVETAIL_VIRTUAL_ASSISTANT_VERSION' ) || define( 'DOVETAIL_VIRTUAL_ASSISTANT_VERSION', '1.31.1' );
        defined( 'DOVETAIL_VIRTUAL_ASSISTANT_PATH' ) || define( 'DOVETAIL_VIRTUAL_ASSISTANT_PATH', trailingslashit( dirname( __DIR__ ) ) );
        defined( 'DOVETAIL_VIRTUAL_ASSISTANT_URL' ) || define( 'DOVETAIL_VIRTUAL_ASSISTANT_URL', DOVETAIL_STUDIO_URL . 'modules/virtual-assistant/' );

        require_once __DIR__ . '/runtime.php';
    }
}
