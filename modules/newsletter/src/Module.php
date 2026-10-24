<?php
/** Newsletter module bootstrap. @package DovetailStudio\Newsletter */
namespace DovetailStudio\Newsletter;

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

        defined( 'DOVETAIL_NEWSLETTER_VERSION' ) || define( 'DOVETAIL_NEWSLETTER_VERSION', '3.2.0' );
        defined( 'DOVETAIL_NEWSLETTER_PATH' ) || define( 'DOVETAIL_NEWSLETTER_PATH', trailingslashit( dirname( __DIR__ ) ) );
        defined( 'DOVETAIL_NEWSLETTER_URL' ) || define( 'DOVETAIL_NEWSLETTER_URL', DOVETAIL_STUDIO_URL . 'modules/newsletter/' );
        defined( 'DOVETAIL_NEWSLETTER_ENCRYPTION_PREFIX' ) || define( 'DOVETAIL_NEWSLETTER_ENCRYPTION_PREFIX', 'dovetail_enc_v1:' );

        require_once __DIR__ . '/functions.php';
    }
}
