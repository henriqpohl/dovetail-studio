<?php
/**
 * Login Guard → Two-Factor tab: mode per role, grace period and trusted devices.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{dir: string, option: string, settings: array<string,mixed>, modes: array<string,string>, roles: array<string,string>, days: array<int,string>, remember: array<int,string>} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$settings = $args['settings'];
$modes    = $args['modes'];
$name     = $args['option'];
$days     = $args['days'];
$remember = $args['remember'];

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
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_section]" value="two_factor">
	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Two-Factor Authentication', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'After the password, users enter a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy…) or one of ten single-use recovery codes. Each user sets it up in Users → Profile. A wrong code counts as a failed sign-in.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php foreach ( $args['roles'] as $role_slug => $role_name ) : ?>
				<?php $field_id = 'dovetail-lg-2fa-' . $role_slug; ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $role_name ); ?></label></th>
					<td>
						<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name . '[two_factor_roles][' . $role_slug . ']' ); ?>">
							<?php foreach ( $modes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $settings['two_factor_roles'][ $role_slug ] ?? 'off' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"></th>
				<td><p class="description"><?php esc_html_e( 'Optional: users of the role may turn it on. Required: they must set it up; until then a notice is shown, and after the grace period they can only open their profile.', 'dovetail-studio' ); ?></p></td>
			</tr>
			<?php
			$select_row( 'two_factor_grace_days', __( 'Grace period for required roles', 'dovetail-studio' ), $days );
			$select_row( 'two_factor_remember_days', __( 'Allow remembering a device for', 'dovetail-studio' ), $remember );
			?>
		</table>
	</section>
	<div class="dovetail-save-bar"><?php submit_button( __( 'Save Two-Factor Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?></div>
</form>
