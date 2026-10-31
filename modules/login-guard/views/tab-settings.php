<?php
/**
 * Login Guard → Settings tab: brute force protection, passwords, hardening and the allowlist.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{dir: string, option: string, settings: array<string,mixed>, counts: array<int,string>, windows: array<int,string>, durations: array<int,string>, strong: array<string,string>, leaked: array<string,string>, existing: string[], my_ip: string} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$settings = $args['settings'];
$counts   = $args['counts'];
$existing = $args['existing'];
$my_ip    = $args['my_ip'];
$name     = $args['option'];

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
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_section]" value="general">

	<section class="dovetail-card" id="dovetail-lg-brute-force">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Brute Force Protection', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Failed sign-ins and password reset requests are counted per IP address. An address that goes over a limit is locked out: it cannot sign in or reset a password until the lockout ends or you unblock it.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php
			$checkbox_row( 'brute_force', __( 'Enable brute force protection', 'dovetail-studio' ), __( 'Turns every rule of this section on or off.', 'dovetail-studio' ) );
			$select_row( 'max_login_failures', __( 'Lock out after how many login failures', 'dovetail-studio' ), $counts );
			$select_row( 'max_lostpw_attempts', __( 'Lock out after how many forgot password attempts', 'dovetail-studio' ), $counts );
			$select_row( 'failure_window', __( 'Count failures over what time period', 'dovetail-studio' ), $args['windows'] );
			$select_row( 'lockout_duration', __( 'Amount of time a user is locked out', 'dovetail-studio' ), $args['durations'] );
			$checkbox_row( 'lockout_invalid_usernames', __( 'Immediately lock out invalid usernames', 'dovetail-studio' ), __( 'A sign-in with a username or email address that has no account locks the IP out at once.', 'dovetail-studio' ) );
			?>
			<tr>
				<th scope="row"><label for="dovetail-lg-blocked-usernames"><?php esc_html_e( 'Immediately block the IP of users who try to sign in as these usernames', 'dovetail-studio' ); ?></label></th>
				<td>
					<textarea id="dovetail-lg-blocked-usernames" class="regular-text" rows="5" name="<?php echo esc_attr( $name ); ?>[blocked_usernames]"><?php echo esc_textarea( implode( "\n", (array) $settings['blocked_usernames'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One username per line. The IP is blocked from the whole site for the lockout time. A username that belongs to a real account is ignored, so nobody can get your own users\' IPs blocked.', 'dovetail-studio' ); ?></p>
					<?php if ( $existing ) : ?>
						<p class="description"><strong>
						<?php
						/* translators: %s: usernames. */
						echo esc_html( sprintf( __( 'Ignored because an account uses them: %s', 'dovetail-studio' ), implode( ', ', $existing ) ) );
						?>
						</strong></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</section>

	<section class="dovetail-card" id="dovetail-lg-passwords">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Passwords', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Checked whenever a password is set: on the profile screen, for new users and on password reset. Strong means at least 12 characters with three of lowercase letters, uppercase letters, numbers and symbols, without the username or email. Leaked passwords are looked up in Have I Been Pwned without sending the password; a user whose current password is leaked must reset it before signing in.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php
			$select_row( 'strong_passwords', __( 'Enforce strong passwords', 'dovetail-studio' ), $args['strong'] );
			$select_row( 'leaked_passwords', __( 'Prevent the use of passwords leaked in data breaches', 'dovetail-studio' ), $args['leaked'] );
			?>
		</table>
	</section>

	<section class="dovetail-card" id="dovetail-lg-additional">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Additional Options', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Hardening that keeps usernames private and closes doors bots use.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php
			$checkbox_row( 'hide_login_errors', __( 'Don\'t let WordPress reveal valid users in login errors', 'dovetail-studio' ) );
			$checkbox_row( 'prevent_admin_registration', __( 'Prevent users registering \'admin\' username if it doesn\'t exist', 'dovetail-studio' ) );
			$checkbox_row( 'prevent_user_enumeration', __( 'Prevent discovery of usernames through \'/?author=N\' scans, the oEmbed API, the WordPress REST API, and WordPress XML Sitemaps', 'dovetail-studio' ) );
			$checkbox_row( 'disable_application_passwords', __( 'Disable WordPress application passwords', 'dovetail-studio' ) );
			$checkbox_row( 'disable_xmlrpc_auth', __( 'Disable XML-RPC authentication', 'dovetail-studio' ), __( 'Refuses every XML-RPC method that needs a sign-in, so bots cannot try many passwords in one request (system.multicall). Pingbacks keep working. Turn it off only for the WordPress mobile app or Jetpack.', 'dovetail-studio' ) );
			$checkbox_row( 'disable_xmlrpc', __( 'Disable XML-RPC completely', 'dovetail-studio' ), __( 'Also switches off pingbacks and the X-Pingback header.', 'dovetail-studio' ) );
			$checkbox_row( 'block_blank_ua_post', __( 'Block IPs who send POST requests with blank User-Agent and Referer', 'dovetail-studio' ), __( 'If you use external services that may send POST requests without these headers, do not use this option, as they will be blocked.', 'dovetail-studio' ) );
			?>
			<tr>
				<th scope="row"><label for="dovetail-lg-block-text"><?php esc_html_e( 'Custom text shown on block pages', 'dovetail-studio' ); ?></label></th>
				<td>
					<textarea id="dovetail-lg-block-text" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[block_page_text]"><?php echo esc_textarea( (string) $settings['block_page_text'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Plain text; line breaks are kept. Leave empty for the default message.', 'dovetail-studio' ); ?></p>
				</td>
			</tr>
		</table>
	</section>

	<section class="dovetail-card" id="dovetail-lg-allowlist">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Allowlist', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Addresses here are never locked out or blocked.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dovetail-lg-allowlist-ips"><?php esc_html_e( 'Allowlisted IP addresses', 'dovetail-studio' ); ?></label></th>
				<td>
					<textarea id="dovetail-lg-allowlist-ips" class="regular-text" rows="4" name="<?php echo esc_attr( $name ); ?>[allowlist]" placeholder="203.0.113.10&#10;198.51.100.0/24"><?php echo esc_textarea( implode( "\n", (array) $settings['allowlist'] ) ); ?></textarea>
					<p class="description">
					<?php
					/* translators: %s: the visitor's IP address. */
					echo esc_html( sprintf( __( 'One IP address or CIDR range per line. Your IP address: %s.', 'dovetail-studio' ), '' !== $my_ip ? $my_ip : __( 'unknown', 'dovetail-studio' ) ) );
					?>
					</p>
				</td>
			</tr>
		</table>
	</section>

	<div class="dovetail-save-bar">
		<?php submit_button( __( 'Save Login Guard Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?>
	</div>
</form>
