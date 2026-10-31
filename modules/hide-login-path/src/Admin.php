<?php
/**
 * Hide Login Path admin screen.
 *
 * @package DovetailStudio\HideLoginPath
 */

namespace DovetailStudio\HideLoginPath;

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

/**
 * Dovetail Studio → Hide Login Path: the current login address and the settings.
 */
final class Admin {

	const PAGE = 'dovetail-hide-login-path';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Add the page under Dovetail Studio.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		add_submenu_page(
			'dovetail-studio',
			__( 'Hide Login Path', 'dovetail-studio' ),
			__( 'Hide Login Path', 'dovetail-studio' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings option.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'dovetail_hide_login_path',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active = Settings::active();

		View::render(
			dirname( __DIR__ ),
			'admin-page',
			array(
				'settings'  => Settings::get(),
				'option'    => Settings::OPTION,
				'prefix'    => get_option( 'permalink_structure' ) ? trailingslashit( home_url( '/' ) ) : home_url( '/' ) . '?',
				'active'    => $active,
				'disabled'  => Settings::disabled(),
				'login_url' => $active ? Router::login_url() : '',
				'version'   => DOVETAIL_HIDE_LOGIN_PATH_VERSION,
			)
		);
	}
}
