<?php
/**
 * Login Guard → Captcha tab: provider, keys, threshold, score chart and test mode.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{dir: string, option: string, settings: array<string,mixed>, has_secret: bool, reset_url: string, providers: array<string,string>, thresholds: array<int,string>, scores: int[]} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$settings   = $args['settings'];
$name       = $args['option'];
$has_secret = $args['has_secret'];
$reset_url  = $args['reset_url'];

/**
 * Print a checkbox row of the settings form.
 *
 * @param string $key         Setting key.
 * @param string $label       Label.
 * @param string $description Optional help text.
 * @return void
 */
$checkbox_row = static function ( string $key, string $label, string $description = '' ) use ( $args ): void {
	View::render(
		$args['dir'],
		'partials/checkbox-row',
		array(
			'name'        => $args['option'] . '[' . $key . ']',
			'checked'     => ! empty( $args['settings'][ $key ] ),
			'label'       => $label,
			'description' => $description,
		)
	);
};

/**
 * Print a select row of the settings form.
 *
 * @param string                   $key     Setting key.
 * @param string                   $label   Label.
 * @param array<int|string,string> $choices Value => label.
 * @return void
 */
$select_row = static function ( string $key, string $label, array $choices ) use ( $args ): void {
	View::render(
		$args['dir'],
		'partials/select-row',
		array(
			'id'      => 'dovetail-lg-' . str_replace( '_', '-', $key ),
			'name'    => $args['option'] . '[' . $key . ']',
			'value'   => (string) $args['settings'][ $key ],
			'label'   => $label,
			'choices' => $choices,
		)
	);
};
?>
<form action="options.php" method="post">
	<?php settings_fields( 'dovetail_login_guard' ); ?>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_section]" value="captcha">
	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Captcha', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Protect the login and registration forms (WordPress and WooCommerce) from automated abuse.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php $checkbox_row( 'captcha_enabled', __( 'Enable captcha on the login and user registration pages', 'dovetail-studio' ), __( 'reCAPTCHA v3 does not make users solve puzzles or click a checkbox; the only visible part is its logo. Turnstile usually passes without interaction too. The provider\'s script loads on the login and registration forms only.', 'dovetail-studio' ) ); ?>
			<?php $select_row( 'captcha_provider', __( 'Provider', 'dovetail-studio' ), $args['providers'] ); ?>
			<tr>
				<th scope="row"><label for="dovetail-lg-site-key"><?php esc_html_e( 'Site key', 'dovetail-studio' ); ?></label></th>
				<td><input id="dovetail-lg-site-key" class="regular-text" type="text" name="<?php echo esc_attr( $name ); ?>[captcha_site_key]" value="<?php echo esc_attr( (string) $settings['captcha_site_key'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dovetail-lg-secret"><?php esc_html_e( 'Secret key', 'dovetail-studio' ); ?></label></th>
				<td>
					<input id="dovetail-lg-secret" class="regular-text" type="password" name="<?php echo esc_attr( $name ); ?>[captcha_secret]" value="" autocomplete="new-password">
					<p class="description"><?php echo $has_secret ? esc_html__( 'A secret is saved (encrypted). Leave blank to keep it.', 'dovetail-studio' ) : esc_html__( 'Stored encrypted. Changing the provider clears it.', 'dovetail-studio' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"></th>
				<td>
					<p class="description">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: reCAPTCHA admin URL, 2: Cloudflare Turnstile URL. */
							__( 'Both services are free. Create reCAPTCHA v3 keys in the <a href="%1$s" target="_blank" rel="noopener noreferrer">reCAPTCHA admin console<span class="screen-reader-text"> (opens in a new tab)</span></a>, or Turnstile keys in the <a href="%2$s" target="_blank" rel="noopener noreferrer">Cloudflare dashboard<span class="screen-reader-text"> (opens in a new tab)</span></a>.', 'dovetail-studio' ),
							'https://www.google.com/recaptcha/admin/create',
							'https://dash.cloudflare.com/?to=/:account/turnstile'
						)
					);
					?>
					</p>
				</td>
			</tr>
		</table>
	</section>

	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'reCAPTCHA human/bot threshold score', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'reCAPTCHA only; Turnstile passes or fails without a score. A score equal to or higher than this value is considered human. Anything lower is treated as a bot and needs extra verification to sign in (the email link below); registrations below it are refused.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php $select_row( 'captcha_threshold', __( 'Threshold score', 'dovetail-studio' ), $args['thresholds'] ); ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Requests per score', 'dovetail-studio' ); ?></th>
				<td>
					<?php View::render( $args['dir'], 'partials/histogram', array( 'scores' => $args['scores'] ) ); ?>
					<p><a href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset score statistics', 'dovetail-studio' ); ?></a></p>
				</td>
			</tr>
			<?php
			$checkbox_row( 'captcha_email_verify', __( 'Email a sign-in link when the score is too low', 'dovetail-studio' ), __( 'When the password is right but the score is below the threshold, the user gets an email with a link that finishes the sign-in (once, within 15 minutes). Without it, the sign-in is refused.', 'dovetail-studio' ) );
			$checkbox_row( 'captcha_test_mode', __( 'Run captcha in test mode', 'dovetail-studio' ), __( 'While in test mode, the captcha scores login and registration requests but does not block them. Results and scores are recorded in the Activity tab and the chart above, so you can choose a threshold.', 'dovetail-studio' ) );
			?>
		</table>
	</section>
	<div class="dovetail-save-bar"><?php submit_button( __( 'Save Captcha Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?></div>
</form>
