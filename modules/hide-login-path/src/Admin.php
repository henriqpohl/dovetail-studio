<?php
/**
 * Hide Login Path admin screen.
 *
 * @package DovetailStudio\HideLoginPath
 */

namespace DovetailStudio\HideLoginPath;

use DovetailStudio\Core\AdminScreens;

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

		$settings = Settings::get();
		$name     = Settings::OPTION;
		$prefix   = get_option( 'permalink_structure' ) ? trailingslashit( home_url( '/' ) ) : home_url( '/' ) . '?';
		?>
		<div class="wrap dovetail-wrap">
			<div class="dovetail-page-header">
				<div>
					<h1><?php esc_html_e( 'Hide Login Path', 'dovetail-studio' ); ?></h1>
					<p><?php esc_html_e( 'Move the login to an address only you know, so bots looking for wp-login.php and wp-admin find nothing.', 'dovetail-studio' ); ?></p>
				</div>
				<span class="dovetail-version"><?php echo esc_html( 'v' . DOVETAIL_HIDE_LOGIN_PATH_VERSION ); ?></span>
			</div>

			<?php
			AdminScreens::settings_errors();

			if ( Settings::disabled() ) {
				AdminScreens::notice( 'warning', esc_html__( 'Hide Login Path is switched off by DOVETAIL_HIDE_LOGIN_PATH_DISABLED in wp-config.php: the login is at wp-login.php until that line is removed.', 'dovetail-studio' ) );
			}

			?>

			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Status', 'dovetail-studio' ); ?></h2>
					<p class="description">
					<?php

					if ( Settings::active() ) {
						$url = Router::login_url();

						echo wp_kses_post(
							sprintf(
								/* translators: %s: the login address as a link. */
								__( 'The login is at %s. Bookmark it: wp-login.php, wp-signup.php and wp-admin no longer open for visitors who are signed out.', 'dovetail-studio' ),
								'<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $url ) . '</strong></a>'
							)
						);
					} else {
						esc_html_e( 'Not active yet: save a login path below. Until then the login is at the WordPress default, wp-login.php.', 'dovetail-studio' );
					}

					?>
					</p>
				</div>
			</section>

			<form action="options.php" method="post">
				<?php settings_fields( 'dovetail_hide_login_path' ); ?>
				<section class="dovetail-card">
					<div class="dovetail-card-header">
						<h2><?php esc_html_e( 'Settings', 'dovetail-studio' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Links, forms and emails from WordPress (lost password, new user, sign out) use the new address automatically.', 'dovetail-studio' ); ?></p>
					</div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dovetail-hlp-slug"><?php esc_html_e( 'Login path', 'dovetail-studio' ); ?></label></th>
							<td>
								<code><?php echo esc_html( $prefix ); ?></code>
								<input id="dovetail-hlp-slug" class="regular-text" type="text" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( (string) $settings['slug'] ); ?>" autocomplete="off" spellcheck="false" aria-describedby="dovetail-hlp-slug-help">
								<p class="description" id="dovetail-hlp-slug-help"><?php esc_html_e( 'Lowercase letters, numbers and hyphens. Avoid words bots try (login, admin, dashboard) and the address of an existing page. Empty: the login is at wp-login.php.', 'dovetail-studio' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dovetail-hlp-redirect"><?php esc_html_e( 'Redirect to', 'dovetail-studio' ); ?></label></th>
							<td>
								<code><?php echo esc_html( $prefix ); ?></code>
								<input id="dovetail-hlp-redirect" class="regular-text" type="text" name="<?php echo esc_attr( $name ); ?>[redirect_slug]" value="<?php echo esc_attr( (string) $settings['redirect_slug'] ); ?>" autocomplete="off" spellcheck="false" aria-describedby="dovetail-hlp-redirect-help">
								<p class="description" id="dovetail-hlp-redirect-help"><?php esc_html_e( 'Where visitors of wp-login.php and wp-admin are sent. The default, 404, is an address with no page, so the theme shows its "not found" page. Empty: the home page.', 'dovetail-studio' ); ?></p>
							</td>
						</tr>
					</table>
				</section>
				<div class="dovetail-save-bar">
					<?php submit_button( __( 'Save Hide Login Path Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
