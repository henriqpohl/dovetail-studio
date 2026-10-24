<?php
/**
 * Dovetail Studio core loader.
 *
 * @package DovetailStudio
 */

namespace DovetailStudio\Core;

defined( 'ABSPATH' ) || exit;

final class Loader {

    /** @var Loader|null */
    private static $instance = null;

    /** @var array<string,array<string,mixed>> */
    private $modules = array();

    /** @return Loader */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Boot the Studio. */
    public function boot() {
        $this->modules = $this->discover_modules();
        $this->load_modules();

        add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_dovetail_studio_remove_module', array( $this, 'handle_remove_module' ) );
        add_action( 'admin_post_dovetail_studio_upload_module', array( $this, 'handle_upload_module' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( DOVETAIL_STUDIO_FILE ), array( $this, 'plugin_action_links' ) );
    }

    /**
     * Discover modules by their module.php manifest.
     *
     * @return array<string,array<string,mixed>>
     */
    private function discover_modules() {
        $modules = array();
        $files   = glob( DOVETAIL_STUDIO_PATH . 'modules/*/module.php' );

        if ( ! is_array( $files ) ) {
            return $modules;
        }

        foreach ( $files as $file ) {
            $module = require $file;

            if ( ! is_array( $module ) || empty( $module['slug'] ) || empty( $module['bootstrap'] ) ) {
                continue;
            }

            $slug = sanitize_key( $module['slug'] );
            $modules[ $slug ] = $module;
        }

        ksort( $modules );
        return $modules;
    }

    /** Return enabled state for every installed module. New modules default to enabled. */
    private function get_enabled_modules() {
        $saved   = get_option( 'dovetail_studio_modules', array() );
        $enabled = array();

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        foreach ( $this->modules as $slug => $module ) {
            $enabled[ $slug ] = array_key_exists( $slug, $saved )
                ? ! empty( $saved[ $slug ] )
                : true;
        }

        return $enabled;
    }

    /** Load enabled modules physically present in the modules directory. */
    private function load_modules() {
        $enabled = $this->get_enabled_modules();

        foreach ( $this->modules as $slug => $module ) {
            if ( empty( $enabled[ $slug ] ) ) {
                continue;
            }

            $bootstrap = (string) $module['bootstrap'];
            $class     = isset( $module['class'] ) ? ltrim( (string) $module['class'], '\\' ) : '';

            if ( ! is_readable( $bootstrap ) || '' === $class ) {
                continue;
            }

            require_once $bootstrap;

            if ( ! class_exists( $class ) ) {
                continue;
            }

            $instance = method_exists( $class, 'instance' ) ? $class::instance() : new $class();
            if ( method_exists( $instance, 'boot' ) ) {
                $instance->boot();
            }
        }
    }


    /** Load the shared Dovetail admin design system on Studio and module screens. */
    public function enqueue_admin_assets() {
        $page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.

        $studio_pages = array(
            'dovetail-studio',
            'dovetail-studio-modules',
            'local-business-schema',
            'dt-va-settings',
            'dt-va-company-notes',
            'dovetail-newsletter',
            'dovetail-schema-seo-woocommerce',
        );

        if ( ! in_array( $page, $studio_pages, true ) && 'dt_enquiry' !== $post_type ) {
            return;
        }

        wp_enqueue_style(
            'dovetail-studio-admin',
            DOVETAIL_STUDIO_URL . 'assets/admin/dovetail-admin.css',
            array(),
            DOVETAIL_STUDIO_VERSION
        );
    }

    /** Build the Dovetail Studio admin menu icon as an SVG data URI. */
    private function get_menu_icon() {
        $icon_path = DOVETAIL_STUDIO_PATH . 'assets/images/dovetail-icon.svg';

        if ( ! is_readable( $icon_path ) ) {
            return 'dashicons-admin-generic';
        }

        $svg = file_get_contents( $icon_path );

        if ( false === $svg || '' === $svg ) {
            return 'dashicons-admin-generic';
        }

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    /** Register Dovetail Studio admin pages. */
    public function register_admin_menu() {
        add_menu_page(
            'Dovetail Studio',
            'Dovetail Studio',
            'manage_options',
            'dovetail-studio',
            array( $this, 'render_dashboard' ),
            $this->get_menu_icon(),
            96
        );

        add_submenu_page(
            'dovetail-studio',
            'Modules',
            'Modules',
            'manage_options',
            'dovetail-studio-modules',
            array( $this, 'render_modules_page' )
        );

        $enabled = $this->get_enabled_modules();
        if ( isset( $this->modules['virtual-assistant'] ) && ! empty( $enabled['virtual-assistant'] ) ) {
            add_submenu_page(
                'dovetail-studio',
                'Virtual Assistant',
                'Virtual Assistant',
                'manage_options',
                'dovetail-studio-virtual-assistant',
                array( $this, 'redirect_virtual_assistant' )
            );
        }
    }

    /** Redirect the Studio shortcut to the Virtual Assistant's external admin menu. */
    public function redirect_virtual_assistant() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'dovetail-studio' ) );
        }

        wp_safe_redirect( admin_url( 'edit.php?post_type=dt_enquiry&page=dt-va-settings' ) );
        exit;
    }


    /** Register the per-module enabled state. */
    public function register_settings() {
        register_setting(
            'dovetail_studio_modules_group',
            'dovetail_studio_modules',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_module_settings' ),
                'default'           => array(),
            )
        );
    }

    /** Sanitize module toggles. */
    public function sanitize_module_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        $clean = array();

        foreach ( $this->modules as $slug => $module ) {
            $clean[ $slug ] = empty( $input[ $slug ] ) ? 0 : 1;
        }

        return $clean;
    }


    /** Return the canonical filesystem path for a module slug. */
    private function get_module_directory( $slug ) {
        $slug = sanitize_key( $slug );
        return DOVETAIL_STUDIO_PATH . 'modules/' . $slug;
    }

    /** Recursively remove a directory without following symlinks. */
    private function delete_directory( $directory ) {
        if ( ! is_dir( $directory ) || is_link( $directory ) ) {
            return false;
        }

        $items = scandir( $directory );
        if ( false === $items ) {
            return false;
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if ( is_link( $path ) || is_file( $path ) ) {
                if ( ! @unlink( $path ) ) {
                    return false;
                }
            } elseif ( is_dir( $path ) && ! $this->delete_directory( $path ) ) {
                return false;
            }
        }

        return @rmdir( $directory );
    }

    /** Recursively copy a directory without following symlinks. */
    private function copy_directory( $source, $destination ) {
        if ( ! is_dir( $source ) || is_link( $source ) ) {
            return false;
        }

        if ( ! is_dir( $destination ) && ! wp_mkdir_p( $destination ) ) {
            return false;
        }

        $items = scandir( $source );
        if ( false === $items ) {
            return false;
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to   = $destination . DIRECTORY_SEPARATOR . $item;

            if ( is_link( $from ) ) {
                return false;
            }

            if ( is_dir( $from ) ) {
                if ( ! $this->copy_directory( $from, $to ) ) {
                    return false;
                }
            } elseif ( ! copy( $from, $to ) ) {
                return false;
            }
        }

        return true;
    }

    /** Redirect back to Modules with a status notice. */
    private function redirect_modules_page( $status, $message ) {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'              => 'dovetail-studio-modules',
                    'dovetail_status'   => sanitize_key( $status ),
                    'dovetail_message'  => rawurlencode( $message ),
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /** Remove an installed module. Disabled modules only. */
    public function handle_remove_module() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to remove Dovetail Studio modules.', 'dovetail-studio' ) );
        }

        $slug = isset( $_REQUEST['module'] ) ? sanitize_key( wp_unslash( $_REQUEST['module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked immediately below.
        check_admin_referer( 'dovetail_studio_remove_module_' . $slug );

        if ( '' === $slug || ! isset( $this->modules[ $slug ] ) ) {
            $this->redirect_modules_page( 'error', 'Module not found.' );
        }

        $enabled = $this->get_enabled_modules();
        if ( ! empty( $enabled[ $slug ] ) ) {
            $this->redirect_modules_page( 'error', 'Disable the module and save your changes before removing it.' );
        }

        $directory    = $this->get_module_directory( $slug );
        $modules_root = realpath( DOVETAIL_STUDIO_PATH . 'modules' );
        $real_dir     = realpath( $directory );

        if ( false === $modules_root || false === $real_dir || 0 !== strpos( $real_dir, $modules_root . DIRECTORY_SEPARATOR ) ) {
            $this->redirect_modules_page( 'error', 'The module path could not be validated.' );
        }

        if ( ! $this->delete_directory( $real_dir ) ) {
            $this->redirect_modules_page( 'error', 'The module files could not be removed. Check filesystem permissions.' );
        }

        $saved = get_option( 'dovetail_studio_modules', array() );
        if ( is_array( $saved ) && array_key_exists( $slug, $saved ) ) {
            unset( $saved[ $slug ] );
            update_option( 'dovetail_studio_modules', $saved, false );
        }

        $this->redirect_modules_page( 'success', 'Module removed. Its WordPress database settings were preserved.' );
    }

    /** Upload or update a Dovetail Studio module ZIP. */
    public function handle_upload_module() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to install Dovetail Studio modules.', 'dovetail-studio' ) );
        }

        check_admin_referer( 'dovetail_studio_upload_module' );

        if ( ! class_exists( '\\ZipArchive' ) ) {
            $this->redirect_modules_page( 'error', 'PHP ZipArchive is required to upload modules.' );
        }

        if ( empty( $_FILES['dovetail_module_zip'] ) || ! is_array( $_FILES['dovetail_module_zip'] ) ) {
            $this->redirect_modules_page( 'error', 'Choose a module ZIP file to upload.' );
        }

        $file = $_FILES['dovetail_module_zip']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below as an uploaded ZIP.

        if ( UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            $this->redirect_modules_page( 'error', 'The module ZIP upload failed.' );
        }

        $filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
        if ( 'zip' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
            $this->redirect_modules_page( 'error', 'Only .zip module packages are accepted.' );
        }

        $modules_root = DOVETAIL_STUDIO_PATH . 'modules';
        if ( ! is_dir( $modules_root ) || ! is_writable( $modules_root ) ) {
            $this->redirect_modules_page( 'error', 'The Dovetail Studio modules directory is not writable.' );
        }

        $token       = wp_generate_password( 12, false, false );
        $staging_dir = trailingslashit( $modules_root ) . '.upload-' . strtolower( $token );

        if ( ! wp_mkdir_p( $staging_dir ) ) {
            $this->redirect_modules_page( 'error', 'Could not create the temporary module directory.' );
        }

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $file['tmp_name'] ) ) {
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'The uploaded ZIP could not be opened.' );
        }

        $top_level = array();
        $ok        = true;

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( false === $name ) {
                $ok = false;
                break;
            }

            $name = str_replace( '\\', '/', $name );
            if ( '' === $name || false !== strpos( $name, "\0" ) || '/' === $name[0] || preg_match( '#(^|/)\.\.(/|$)#', $name ) ) {
                $ok = false;
                break;
            }

            $segments = array_values( array_filter( explode( '/', trim( $name, '/' ) ), 'strlen' ) );
            if ( empty( $segments ) ) {
                continue;
            }
            $top_level[ $segments[0] ] = true;

            $target = $staging_dir . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $segments );
            if ( '/' === substr( $name, -1 ) ) {
                if ( ! wp_mkdir_p( $target ) ) {
                    $ok = false;
                    break;
                }
                continue;
            }

            $parent = dirname( $target );
            if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
                $ok = false;
                break;
            }

            $contents = $zip->getFromIndex( $i );
            if ( false === $contents || false === file_put_contents( $target, $contents ) ) {
                $ok = false;
                break;
            }
        }

        $zip->close();

        if ( ! $ok ) {
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'The ZIP contains an invalid or unsafe file path.' );
        }

        $candidate = $staging_dir;
        if ( ! is_file( $candidate . '/module.php' ) ) {
            $dirs = array_keys( $top_level );
            if ( 1 === count( $dirs ) && is_file( $staging_dir . '/' . $dirs[0] . '/module.php' ) ) {
                $candidate = $staging_dir . '/' . $dirs[0];
            }
        }

        if ( ! is_file( $candidate . '/module.php' ) ) {
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'Invalid module package: module.php was not found at the package root.' );
        }

        $module = require $candidate . '/module.php';
        if ( ! is_array( $module ) || empty( $module['slug'] ) || empty( $module['name'] ) || empty( $module['version'] ) || empty( $module['bootstrap'] ) || empty( $module['class'] ) ) {
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'Invalid module manifest.' );
        }

        $slug = sanitize_key( $module['slug'] );
        if ( '' === $slug || $slug !== $module['slug'] ) {
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'Invalid module slug.' );
        }

        $candidate_real = realpath( $candidate );
        $bootstrap_real = realpath( $module['bootstrap'] );
        if ( false === $candidate_real || false === $bootstrap_real || 0 !== strpos( $bootstrap_real, $candidate_real . DIRECTORY_SEPARATOR ) ) {
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'Invalid module bootstrap path.' );
        }

        $destination = $this->get_module_directory( $slug );
        $is_update   = is_dir( $destination );
        $backup      = '';

        if ( $is_update ) {
            $backup = trailingslashit( $modules_root ) . '.backup-' . $slug . '-' . strtolower( $token );
            if ( ! @rename( $destination, $backup ) ) {
                $this->delete_directory( $staging_dir );
                $this->redirect_modules_page( 'error', 'Could not prepare the existing module for update.' );
            }
        }

        $installed = @rename( $candidate, $destination );
        if ( ! $installed ) {
            $installed = $this->copy_directory( $candidate, $destination );
        }

        if ( ! $installed ) {
            $this->delete_directory( $destination );
            if ( $is_update && is_dir( $backup ) ) {
                @rename( $backup, $destination );
            }
            $this->delete_directory( $staging_dir );
            $this->redirect_modules_page( 'error', 'The module could not be installed. The previous version was restored when possible.' );
        }

        if ( is_dir( $backup ) ) {
            $this->delete_directory( $backup );
        }
        if ( is_dir( $staging_dir ) ) {
            $this->delete_directory( $staging_dir );
        }

        $saved = get_option( 'dovetail_studio_modules', array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }
        if ( ! array_key_exists( $slug, $saved ) ) {
            $saved[ $slug ] = 1;
            update_option( 'dovetail_studio_modules', $saved, false );
        }

        $this->redirect_modules_page(
            'success',
            $is_update ? 'Module updated successfully.' : 'Module installed successfully.'
        );
    }

    /** Render a status notice passed back from a module operation. */
    private function render_modules_notice() {
        $status  = isset( $_GET['dovetail_status'] ) ? sanitize_key( wp_unslash( $_GET['dovetail_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
        $message = isset( $_GET['dovetail_message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['dovetail_message'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.

        if ( '' === $message || ! in_array( $status, array( 'success', 'error' ), true ) ) {
            return;
        }

        $class = 'success' === $status ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
    }



    /** Main dashboard. */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $enabled = $this->get_enabled_modules();
        ?>
        <div class="wrap dovetail-wrap">
            <div class="dovetail-page-header">
                <div>
                    <h1>Dovetail Studio</h1>
                    <p>Central WordPress toolkit for Dovetail Consultancy modules.</p>
                </div>
                <span class="dovetail-version"><?php echo esc_html( 'v' . DOVETAIL_STUDIO_VERSION ); ?></span>
            </div>

            <nav class="dovetail-section-nav" aria-label="Dovetail Studio sections">
                <a class="is-active" aria-current="page" href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio' ) ); ?>">Dashboard</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio-modules' ) ); ?>">Modules</a>
            </nav>

            <div class="dovetail-module-grid">
                <?php foreach ( $this->modules as $slug => $module ) : ?>
                    <section class="dovetail-card dovetail-module-card">
                        <div class="dovetail-card-header">
                            <h2><?php echo esc_html( $module['name'] ); ?></h2>
                            <p class="description"><?php echo esc_html( $module['description'] ); ?></p>
                        </div>
                        <div class="dovetail-card-body">
                            <div class="dovetail-module-meta">
                                <span class="dovetail-badge">v<?php echo esc_html( $module['version'] ); ?></span>
                                <span class="dovetail-badge <?php echo ! empty( $enabled[ $slug ] ) ? 'is-enabled' : ''; ?>">
                                    <?php echo ! empty( $enabled[ $slug ] ) ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <?php if ( ! empty( $module['admin_url'] ) && ! empty( $enabled[ $slug ] ) ) : ?>
                                <p><a class="button button-primary" href="<?php echo esc_url( admin_url( $module['admin_url'] ) ); ?>">Open module</a></p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /** Manage installed Dovetail Studio modules. */
    public function render_modules_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $enabled = $this->get_enabled_modules();
        ?>
        <div class="wrap dovetail-wrap">
            <div class="dovetail-page-header">
                <div>
                    <h1>Dovetail Studio</h1>
                    <p>Enable, disable, install, update or remove Dovetail Studio modules.</p>
                </div>
                <span class="dovetail-version"><?php echo esc_html( 'v' . DOVETAIL_STUDIO_VERSION ); ?></span>
            </div>

            <nav class="dovetail-section-nav" aria-label="Dovetail Studio sections">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio' ) ); ?>">Dashboard</a>
                <a class="is-active" aria-current="page" href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio-modules' ) ); ?>">Modules</a>
            </nav>

            <?php $this->render_modules_notice(); ?>

            <section class="dovetail-card">
                <div class="dovetail-card-header">
                    <h2>Upload Module</h2>
                    <p class="description">Install a new Dovetail Studio module or upload a newer version of an existing module. Packages must be ZIP files containing a valid <code>module.php</code>.</p>
                </div>
                <div class="dovetail-card-body">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="dovetail_studio_upload_module">
                        <?php wp_nonce_field( 'dovetail_studio_upload_module' ); ?>
                        <input type="file" name="dovetail_module_zip" accept=".zip,application/zip" required>
                        <?php submit_button( 'Upload Module', 'secondary', 'submit', false ); ?>
                    </form>
                </div>
            </section>

            <form method="post" action="options.php">
                <?php settings_fields( 'dovetail_studio_modules_group' ); ?>

                <section class="dovetail-card">
                    <div class="dovetail-card-header">
                        <h2>Installed Modules</h2>
                        <p class="description">Disabled modules remain installed but are not loaded. A module must be disabled and saved before it can be removed.</p>
                    </div>
                    <div class="dovetail-card-body">
                        <div class="dovetail-table-scroll">
                            <table class="widefat striped dovetail-modules-table">
                                <thead>
                                    <tr>
                                        <th>Enabled</th>
                                        <th>Module</th>
                                        <th>Version</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ( empty( $this->modules ) ) : ?>
                                    <tr><td colspan="5">No modules installed.</td></tr>
                                <?php else : ?>
                                    <?php foreach ( $this->modules as $slug => $module ) : ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="dovetail_studio_modules[<?php echo esc_attr( $slug ); ?>]" value="0">
                                                <label>
                                                    <input
                                                        type="checkbox"
                                                        name="dovetail_studio_modules[<?php echo esc_attr( $slug ); ?>]"
                                                        value="1"
                                                        <?php checked( ! empty( $enabled[ $slug ] ) ); ?>
                                                    >
                                                    <span class="screen-reader-text"><?php echo esc_html( 'Enable ' . $module['name'] ); ?></span>
                                                </label>
                                            </td>
                                            <td><strong><?php echo esc_html( $module['name'] ); ?></strong></td>
                                            <td><?php echo esc_html( $module['version'] ); ?></td>
                                            <td><?php echo esc_html( $module['description'] ); ?></td>
                                            <td>
                                                <?php if ( ! empty( $enabled[ $slug ] ) ) : ?>
                                                    <?php if ( ! empty( $module['admin_url'] ) ) : ?>
                                                        <a class="button button-small" href="<?php echo esc_url( admin_url( $module['admin_url'] ) ); ?>">Open</a>
                                                    <?php endif; ?>
                                                <?php else : ?>
                                                    <?php
                                                    $remove_url = wp_nonce_url(
                                                        add_query_arg(
                                                            array(
                                                                'action' => 'dovetail_studio_remove_module',
                                                                'module' => $slug,
                                                            ),
                                                            admin_url( 'admin-post.php' )
                                                        ),
                                                        'dovetail_studio_remove_module_' . $slug
                                                    );
                                                    ?>
                                                    <a
                                                        class="button button-small button-link-delete dovetail-remove-module"
                                                        href="<?php echo esc_url( $remove_url ); ?>"
                                                        onclick="return confirm('Remove <?php echo esc_js( $module['name'] ); ?>? The module files will be deleted, but its WordPress database settings will be preserved.');"
                                                    >Remove Module</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php submit_button( 'Save Modules' ); ?>
                    </div>
                </section>
            </form>
        </div>
        <?php
    }


    /** Add shortcut from Plugins screen. */
    public function plugin_action_links( $links ) {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'admin.php?page=dovetail-studio' ) ) . '">Studio</a>'
        );

        return $links;
    }
}
