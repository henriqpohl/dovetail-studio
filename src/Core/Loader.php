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

		// Styles and the notice-free workspace on the Studio's screens and the enabled modules' screens.
		AdminScreens::instance()->boot(
			array_intersect_key( $this->modules, array_filter( $this->get_enabled_modules() ) )
		);

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 5 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_dovetail_studio_remove_module', array( $this, 'handle_remove_module' ) );
		add_action( 'admin_post_dovetail_studio_upload_module', array( $this, 'handle_upload_module' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( DOVETAIL_STUDIO_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Directory for modules installed by ZIP upload.
	 *
	 * It lives outside the plugin folder, so updating Dovetail Studio (which
	 * replaces the plugin folder) never deletes uploaded modules.
	 *
	 * @return string Absolute path without trailing slash.
	 */
	public static function external_modules_dir() {
		$dir = defined( 'DOVETAIL_STUDIO_MODULES_DIR' )
			? (string) DOVETAIL_STUDIO_MODULES_DIR
			: WP_CONTENT_DIR . '/dovetail-modules';

		/**
		 * Filters the directory for uploaded Dovetail Studio modules.
		 *
		 * @param string $dir Absolute path.
		 */
		return untrailingslashit( (string) apply_filters( 'dovetail_studio_modules_dir', $dir ) );
	}

	/**
	 * The uploaded modules directory as shown to administrators: relative to the WordPress root when inside it.
	 *
	 * @return string
	 */
	public static function external_modules_dir_label() {
		$dir  = wp_normalize_path( self::external_modules_dir() );
		$root = wp_normalize_path( untrailingslashit( ABSPATH ) );

		return 0 === strpos( $dir . '/', $root . '/' ) ? ltrim( substr( $dir, strlen( $root ) ), '/' ) : $dir;
	}

	/**
	 * Public URL of a directory inside the plugin or wp-content.
	 *
	 * Modules use this instead of DOVETAIL_STUDIO_URL so they keep working
	 * when installed in the external modules directory.
	 *
	 * @param string $dir Absolute directory path.
	 * @return string URL with trailing slash.
	 */
	public static function path_url( $dir ) {
		$dir     = wp_normalize_path( untrailingslashit( (string) $dir ) );
		$plugin  = wp_normalize_path( untrailingslashit( DOVETAIL_STUDIO_PATH ) );
		$content = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );

		if ( 0 === strpos( $dir . '/', $plugin . '/' ) ) {
			return trailingslashit( DOVETAIL_STUDIO_URL . ltrim( substr( $dir, strlen( $plugin ) ), '/' ) );
		}

		if ( 0 === strpos( $dir . '/', $content . '/' ) ) {
			return trailingslashit( content_url( substr( $dir, strlen( $content ) ) ) );
		}

		return trailingslashit( DOVETAIL_STUDIO_URL );
	}

	/**
	 * URL and version of a module asset, preferring the `.min` file built by tools/build.php
	 * when it is not older than its source (and SCRIPT_DEBUG is off).
	 *
	 * @param string $dir      Absolute module directory.
	 * @param string $relative Asset path inside it, e.g. 'assets/js/front.js'.
	 * @return array{url: string, ver: string}
	 */
	public static function asset( $dir, $relative ) {
		$root = trailingslashit( (string) $dir );
		$min  = preg_replace( '/\.(js|css)$/', '.min.$1', (string) $relative );
		$use  = ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG )
			&& is_readable( $root . $min )
			&& filemtime( $root . $min ) >= filemtime( $root . $relative )
			? $min
			: (string) $relative;

		return array(
			'url' => self::path_url( $root ) . $use,
			'ver' => is_readable( $root . $use ) ? (string) filemtime( $root . $use ) : DOVETAIL_STUDIO_VERSION,
		);
	}

	/**
	 * Whether the current user may install, update or remove module files.
	 *
	 * @return bool
	 */
	public function can_manage_module_files() {
		return current_user_can( 'install_plugins' ) && wp_is_file_mod_allowed( 'dovetail_studio_modules' );
	}

	/**
	 * Discover modules by their module.php manifest.
	 *
	 * Bundled modules come from the plugin's modules/ folder; uploaded ones
	 * from the external directory. An uploaded module replaces a bundled
	 * module with the same slug, so a newer version can be installed
	 * without editing the plugin.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function discover_modules() {
		$modules = array();
		$roots   = array(
			'bundled'  => DOVETAIL_STUDIO_PATH . 'modules',
			'external' => self::external_modules_dir(),
		);

		foreach ( $roots as $source => $root ) {
			$files = glob( trailingslashit( $root ) . '*/module.php' );

			if ( ! is_array( $files ) ) {
				continue;
			}

			foreach ( $files as $file ) {
				$module = require $file;

				if ( ! is_array( $module ) || empty( $module['slug'] ) || empty( $module['bootstrap'] ) ) {
					continue;
				}

				$slug              = sanitize_key( $module['slug'] );
				$module['_dir']    = dirname( $file );
				$module['_source'] = $source;
				$modules[ $slug ]  = $module;
			}
		}

		ksort( $modules );

		return $modules;
	}

	/**
	 * Cron hooks declared by a module manifest ('cron_hooks').
	 *
	 * @param array<string,mixed> $module Manifest.
	 * @return string[]
	 */
	private static function module_cron_hooks( array $module ) {
		return isset( $module['cron_hooks'] ) && is_array( $module['cron_hooks'] )
			? array_filter( array_map( 'strval', $module['cron_hooks'] ) )
			: array();
	}

	/**
	 * Clear the scheduled events of a module.
	 *
	 * @param array<string,mixed> $module Manifest.
	 */
	private static function clear_module_crons( array $module ) {
		foreach ( self::module_cron_hooks( $module ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/** Plugin deactivation: stop every module's scheduled events. */
	public static function deactivate() {
		$loader = self::instance();

		if ( empty( $loader->modules ) ) {
			$loader->modules = $loader->discover_modules();
		}

		foreach ( $loader->modules as $module ) {
			self::clear_module_crons( $module );
		}
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

		// WordPress paints the icon black first and recolors it with svg-painter.js once the page has loaded,
		// which makes it flash. Starting from the menu icon gray of the default admin color scheme, the first
		// paint already matches; svg-painter still adapts it to other color schemes and to hover/current states.
		$svg = preg_replace( '/fill="#0{3}(?:0{3})?"/i', 'fill="#a7aaad"', $svg );

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Menu icon data URI, as add_menu_page() expects.
	}

	/** Register Dovetail Studio admin pages. */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Dovetail Studio', 'dovetail-studio' ),
			__( 'Dovetail Studio', 'dovetail-studio' ),
			'manage_options',
			'dovetail-studio',
			array( $this, 'render_dashboard' ),
			$this->get_menu_icon(),
			96
		);

		add_submenu_page(
			'dovetail-studio',
			__( 'Modules', 'dovetail-studio' ),
			__( 'Modules', 'dovetail-studio' ),
			'manage_options',
			'dovetail-studio-modules',
			array( $this, 'render_modules_page' )
		);
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

		$before = $this->get_enabled_modules();

		foreach ( $this->modules as $slug => $module ) {
			$clean[ $slug ] = empty( $input[ $slug ] ) ? 0 : 1;

			// A module that is switched off no longer runs, so its scheduled events must not stay behind.
			if ( ! $clean[ $slug ] && ! empty( $before[ $slug ] ) ) {
				self::clear_module_crons( $module );
			}
		}

		return $clean;
	}

	/** Return the install path of an uploaded module slug. */
	private function get_module_directory( $slug ) {
		$slug = sanitize_key( $slug );

		return self::external_modules_dir() . '/' . $slug;
	}

	/** Create the external modules directory and block direct listing. */
	private function ensure_external_dir() {
		$root = self::external_modules_dir();

		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return false;
		}

		if ( ! file_exists( $root . '/index.php' ) ) {
			@file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		return is_writable( $root );
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

	/**
	 * Messages of the module operations, by code. Only a code travels in the redirect URL,
	 * so a crafted link cannot put its own text on the Studio screen.
	 *
	 * @return array<string,string>
	 */
	private function module_messages() {
		return array(
			'not_found'             => __( 'Module not found.', 'dovetail-studio' ),
			'bundled'               => __( 'Bundled modules are part of the plugin and come back with every update. Disable the module instead.', 'dovetail-studio' ),
			'disable_first'         => __( 'Disable the module and save your changes before removing it.', 'dovetail-studio' ),
			'bad_path'              => __( 'The module path could not be validated.', 'dovetail-studio' ),
			'remove_failed'         => __( 'The module files could not be removed. Check filesystem permissions.', 'dovetail-studio' ),
			'removed'               => __( 'Module removed. Its WordPress database settings were preserved.', 'dovetail-studio' ),
			'no_zip_ext'            => __( 'PHP ZipArchive is required to upload modules.', 'dovetail-studio' ),
			'no_file'               => __( 'Choose a module ZIP file to upload.', 'dovetail-studio' ),
			'upload_failed'         => __( 'The module ZIP upload failed.', 'dovetail-studio' ),
			'not_zip'               => __( 'Only .zip module packages are accepted.', 'dovetail-studio' ),
			/* translators: %s: modules directory, e.g. wp-content/dovetail-modules. */
			'dir_not_writable'      => sprintf( __( 'The Dovetail Studio modules directory (%s) could not be created or is not writable.', 'dovetail-studio' ), self::external_modules_dir_label() ),
			'no_temp'               => __( 'Could not create the temporary module directory.', 'dovetail-studio' ),
			'zip_unreadable'        => __( 'The uploaded ZIP could not be opened.', 'dovetail-studio' ),
			'unsafe_path'           => __( 'The ZIP contains an invalid or unsafe file path.', 'dovetail-studio' ),
			'no_manifest'           => __( 'Invalid module package: module.php was not found at the package root.', 'dovetail-studio' ),
			'bad_manifest'          => __( 'Invalid module manifest.', 'dovetail-studio' ),
			'bad_slug'              => __( 'Invalid module slug.', 'dovetail-studio' ),
			'bad_bootstrap'         => __( 'Invalid module bootstrap path.', 'dovetail-studio' ),
			'update_prepare_failed' => __( 'Could not prepare the existing module for update.', 'dovetail-studio' ),
			'install_failed'        => __( 'The module could not be installed. The previous version was restored when possible.', 'dovetail-studio' ),
			'updated'               => __( 'Module updated successfully.', 'dovetail-studio' ),
			'installed'             => __( 'Module installed. It is disabled: enable it below and save when you are ready.', 'dovetail-studio' ),
		);
	}

	/** Redirect back to Modules with a status code. */
	private function redirect_modules_page( $status, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'dovetail-studio-modules',
					'dovetail_status' => sanitize_key( $status ),
					'dovetail_code'   => sanitize_key( $code ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Remove an installed module. Disabled modules only. */
	public function handle_remove_module() {
		if ( ! $this->can_manage_module_files() ) {
			wp_die(
				esc_html__( 'You are not allowed to remove Dovetail Studio modules.', 'dovetail-studio' )
			);
		}

		$slug = isset( $_REQUEST['module'] ) ? sanitize_key( wp_unslash( $_REQUEST['module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked immediately below.
		check_admin_referer( 'dovetail_studio_remove_module_' . $slug );

		if ( '' === $slug || ! isset( $this->modules[ $slug ] ) ) {
			$this->redirect_modules_page( 'error', 'not_found' );
		}

		if ( 'external' !== $this->modules[ $slug ]['_source'] ) {
			$this->redirect_modules_page( 'error', 'bundled' );
		}

		$enabled = $this->get_enabled_modules();

		if ( ! empty( $enabled[ $slug ] ) ) {
			$this->redirect_modules_page( 'error', 'disable_first' );
		}

		$directory    = $this->get_module_directory( $slug );
		$modules_root = realpath( self::external_modules_dir() );
		$real_dir     = realpath( $directory );

		if ( false === $modules_root
			|| false === $real_dir
			|| 0 !== strpos( $real_dir, $modules_root . DIRECTORY_SEPARATOR )
		) {
			$this->redirect_modules_page( 'error', 'bad_path' );
		}

		if ( ! $this->delete_directory( $real_dir ) ) {
			$this->redirect_modules_page( 'error', 'remove_failed' );
		}

		self::clear_module_crons( $this->modules[ $slug ] );

		// A bundled module with the same slug takes over again, so keep its enabled state.
		$saved = get_option( 'dovetail_studio_modules', array() );

		if ( is_array( $saved )
			&& array_key_exists( $slug, $saved )
			&& ! is_dir( DOVETAIL_STUDIO_PATH . 'modules/' . $slug )
		) {
			unset( $saved[ $slug ] );
			update_option( 'dovetail_studio_modules', $saved, false );
		}

		$this->redirect_modules_page( 'success', 'removed' );
	}

	/** Upload or update a Dovetail Studio module ZIP. */
	public function handle_upload_module() {
		if ( ! $this->can_manage_module_files() ) {
			wp_die(
				esc_html__( 'You are not allowed to install Dovetail Studio modules.', 'dovetail-studio' )
			);
		}

		check_admin_referer( 'dovetail_studio_upload_module' );

		if ( ! class_exists( '\\ZipArchive' ) ) {
			$this->redirect_modules_page( 'error', 'no_zip_ext' );
		}

		if ( empty( $_FILES['dovetail_module_zip'] ) || ! is_array( $_FILES['dovetail_module_zip'] ) ) {
			$this->redirect_modules_page( 'error', 'no_file' );
		}

		$file = $_FILES['dovetail_module_zip']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below as an uploaded ZIP.

		if ( UPLOAD_ERR_OK !== (int) $file['error']
			|| empty( $file['tmp_name'] )
			|| ! is_uploaded_file( $file['tmp_name'] )
		) {
			$this->redirect_modules_page( 'error', 'upload_failed' );
		}

		$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';

		if ( 'zip' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			$this->redirect_modules_page( 'error', 'not_zip' );
		}

		$modules_root = self::external_modules_dir();

		if ( ! $this->ensure_external_dir() ) {
			$this->redirect_modules_page( 'error', 'dir_not_writable' );
		}

		$token       = wp_generate_password( 12, false, false );
		$staging_dir = trailingslashit( $modules_root ) . '.upload-' . strtolower( $token );

		if ( ! wp_mkdir_p( $staging_dir ) ) {
			$this->redirect_modules_page( 'error', 'no_temp' );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			$this->delete_directory( $staging_dir );
			$this->redirect_modules_page( 'error', 'zip_unreadable' );
		}

		$top_level = array();
		$ok        = true;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$name = $zip->getNameIndex( $i );

			if ( false === $name ) {
				$ok = false;

				break;
			}

			$name = str_replace( '\\', '/', $name );

			if ( '' === $name
				|| false !== strpos( $name, "\0" )
				|| '/' === $name[0]
				|| preg_match( '#(^|/)\.\.(/|$)#', $name )
			) {
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
			$this->redirect_modules_page( 'error', 'unsafe_path' );
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
			$this->redirect_modules_page( 'error', 'no_manifest' );
		}

		$module = require $candidate . '/module.php';

		if ( ! is_array( $module )
			|| empty( $module['slug'] )
			|| empty( $module['name'] )
			|| empty( $module['version'] )
			|| empty( $module['bootstrap'] )
			|| empty( $module['class'] )
		) {
			$this->delete_directory( $staging_dir );
			$this->redirect_modules_page( 'error', 'bad_manifest' );
		}

		$slug = sanitize_key( $module['slug'] );

		if ( '' === $slug || $slug !== $module['slug'] ) {
			$this->delete_directory( $staging_dir );
			$this->redirect_modules_page( 'error', 'bad_slug' );
		}

		$candidate_real = realpath( $candidate );
		$bootstrap_real = realpath( $module['bootstrap'] );

		if ( false === $candidate_real
			|| false === $bootstrap_real
			|| 0 !== strpos( $bootstrap_real, $candidate_real . DIRECTORY_SEPARATOR )
		) {
			$this->delete_directory( $staging_dir );
			$this->redirect_modules_page( 'error', 'bad_bootstrap' );
		}

		$destination = $this->get_module_directory( $slug );
		$is_update   = is_dir( $destination );
		$backup      = '';

		if ( $is_update ) {
			$backup = trailingslashit( $modules_root ) . '.backup-' . $slug . '-' . strtolower( $token );

			if ( ! @rename( $destination, $backup ) ) {
				$this->delete_directory( $staging_dir );
				$this->redirect_modules_page( 'error', 'update_prepare_failed' );
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
			$this->redirect_modules_page( 'error', 'install_failed' );
		}

		if ( is_dir( $backup ) ) {
			$this->delete_directory( $backup );
		}

		if ( is_dir( $staging_dir ) ) {
			$this->delete_directory( $staging_dir );
		}

		// A new module starts disabled: if its code has a fatal error, the site keeps working and
		// the module can be removed. An update (or a replacement of a bundled module) keeps its state.
		$saved = get_option( 'dovetail_studio_modules', array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		if ( ! array_key_exists( $slug, $saved ) && ! isset( $this->modules[ $slug ] ) ) {
			$saved[ $slug ] = 0;
			update_option( 'dovetail_studio_modules', $saved, false );
		}

		$this->redirect_modules_page( 'success', $is_update ? 'updated' : 'installed' );
	}

	/**
	 * The status notice of a module operation: type and message, or null.
	 *
	 * @return array{0: string, 1: string}|null
	 */
	private function modules_notice() {
		$status   = isset( $_GET['dovetail_status'] ) ? sanitize_key( wp_unslash( $_GET['dovetail_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$code     = isset( $_GET['dovetail_code'] ) ? sanitize_key( wp_unslash( $_GET['dovetail_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$messages = $this->module_messages();

		if ( ! isset( $messages[ $code ] ) || ! in_array( $status, array( 'success', 'error' ), true ) ) {
			return null;
		}

		return array( $status, $messages[ $code ] );
	}

	/**
	 * A manifest's name or description in the admin language. Bundled module texts are in
	 * languages/dovetail-studio.pot (tools/build.php reads the manifests); others show as written.
	 *
	 * @param array<string,mixed> $module Manifest.
	 * @param string              $field  'name' or 'description'.
	 * @return string
	 */
	private function module_text( array $module, $field ) {
		$text = isset( $module[ $field ] ) ? (string) $module[ $field ] : '';

		return '' === $text ? '' : __( $text, 'dovetail-studio' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Manifest texts, extracted by tools/build.php.
	}

	/**
	 * The installed modules for the Studio screens: texts in the admin language, state and links.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function module_rows() {
		$enabled = $this->get_enabled_modules();
		$rows    = array();

		foreach ( $this->modules as $slug => $module ) {
			$on       = ! empty( $enabled[ $slug ] );
			$uploaded = 'external' === $module['_source'];

			$rows[] = array(
				'slug'        => $slug,
				'name'        => $this->module_text( $module, 'name' ),
				'description' => $this->module_text( $module, 'description' ),
				'version'     => (string) $module['version'],
				'enabled'     => $on,
				'uploaded'    => $uploaded,
				'open_url'    => $on && ! empty( $module['admin_url'] ) ? admin_url( $module['admin_url'] ) : '',
				'remove_url'  => ! $on && $uploaded && $this->can_manage_module_files()
					? wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'dovetail_studio_remove_module',
								'module' => $slug,
							),
							admin_url( 'admin-post.php' )
						),
						'dovetail_studio_remove_module_' . $slug
					)
					: '',
			);
		}

		return $rows;
	}

	/** Main dashboard. */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::render(
			__DIR__,
			'dashboard',
			array(
				'version' => DOVETAIL_STUDIO_VERSION,
				'modules' => $this->module_rows(),
			)
		);
	}

	/** Manage installed Dovetail Studio modules. */
	public function render_modules_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::render(
			__DIR__,
			'modules',
			array(
				'version'     => DOVETAIL_STUDIO_VERSION,
				'notice'      => $this->modules_notice(),
				'can_upload'  => $this->can_manage_module_files(),
				'modules_dir' => self::external_modules_dir_label(),
				'modules'     => $this->module_rows(),
			)
		);
	}

	/** Add shortcut from Plugins screen. */
	public function plugin_action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=dovetail-studio' ) ) . '">' . esc_html__( 'Studio', 'dovetail-studio' ) . '</a>'
		);

		return $links;
	}
}
