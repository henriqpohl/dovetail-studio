<?php
/**
 * The two-factor section of the profile screen.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{own: bool, active: bool, mode: string, codes: string[], recovery_left: int, can_reset: bool, pending: string, qr: string, deadline: int, date_format: string} $args
 */

defined( 'ABSPATH' ) || exit;

$own       = $args['own'];
$active    = $args['active'];
$role_mode = $args['mode'];
$codes     = $args['codes'];
$pending   = $args['pending'];
$qr        = $args['qr'];
$deadline  = $args['deadline'];
?>
<h2 id="dovetail-lg-2fa"><?php esc_html_e( 'Two-factor authentication', 'dovetail-studio' ); ?></h2>
<table class="form-table" role="presentation">
	<?php if ( $codes ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Recovery codes', 'dovetail-studio' ); ?></th>
			<td>
				<p><strong><?php esc_html_e( 'Save these codes somewhere safe now; they are shown only once. Each one signs you in once if you lose your phone.', 'dovetail-studio' ); ?></strong></p>
				<ol>
					<?php foreach ( $codes as $code ) : ?>
						<li><code><?php echo esc_html( $code ); ?></code></li>
					<?php endforeach; ?>
				</ol>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( $active ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'dovetail-studio' ); ?></th>
			<td>
				<p>
				<?php
				/* translators: %d: number of recovery codes left. */
				echo esc_html( sprintf( _n( 'Active. %d recovery code left.', 'Active. %d recovery codes left.', $args['recovery_left'], 'dovetail-studio' ), $args['recovery_left'] ) );
				?>
				</p>
			</td>
		</tr>

		<?php if ( $own ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Recovery codes', 'dovetail-studio' ); ?></th>
				<td><label><input type="checkbox" name="dovetail_lg_regenerate" value="1"> <?php esc_html_e( 'Create new recovery codes (the old ones stop working)', 'dovetail-studio' ); ?></label></td>
			</tr>

			<?php if ( 'required' !== $role_mode ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Turn off', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="dovetail_lg_disable" value="1"> <?php esc_html_e( 'Turn off two-factor authentication', 'dovetail-studio' ); ?></label></td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Turn off', 'dovetail-studio' ); ?></th>
					<td><p class="description"><?php esc_html_e( 'Your role requires two-factor authentication. An administrator can reset it if you lose your phone and your recovery codes.', 'dovetail-studio' ); ?></p></td>
				</tr>
			<?php endif; ?>
		<?php elseif ( $args['can_reset'] ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reset', 'dovetail-studio' ); ?></th>
				<td>
					<label><input type="checkbox" name="dovetail_lg_reset" value="1"> <?php esc_html_e( 'Reset two-factor authentication for this user', 'dovetail-studio' ); ?></label>
					<p class="description"><?php esc_html_e( 'For a user who lost their phone and recovery codes. They sign in with the password only and set it up again.', 'dovetail-studio' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
	<?php elseif ( ! $own ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'dovetail-studio' ); ?></th>
			<td><p><?php echo 'required' === $role_mode ? esc_html__( 'Not set up yet (required for this user\'s role).', 'dovetail-studio' ) : esc_html__( 'Not set up.', 'dovetail-studio' ); ?></p></td>
		</tr>
	<?php else : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Set up', 'dovetail-studio' ); ?></th>
			<td>
				<p><?php esc_html_e( 'Scan this QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy…), then type the 6-digit code it shows and update your profile.', 'dovetail-studio' ); ?></p>
				<?php if ( '' !== $qr ) : ?>
					<p><?php echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by QrCode from escaped parts. ?></p>
				<?php endif; ?>
				<p>
					<?php esc_html_e( 'Or enter this key in the app:', 'dovetail-studio' ); ?>
					<code><?php echo esc_html( implode( ' ', str_split( $pending, 4 ) ) ); ?></code>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dovetail-lg-setup-code"><?php esc_html_e( 'Code from the app', 'dovetail-studio' ); ?></label></th>
			<td>
				<input type="text" id="dovetail-lg-setup-code" name="dovetail_lg_setup_code" class="regular-text" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9 ]*" aria-describedby="dovetail-lg-setup-help">
				<p class="description" id="dovetail-lg-setup-help">
				<?php
				echo esc_html(
					$deadline
						/* translators: %s: date. */
						? sprintf( __( 'Required for your role. Set it up before %s.', 'dovetail-studio' ), wp_date( $args['date_format'], $deadline ) )
						: __( 'Optional. Leave empty to keep signing in with your password only.', 'dovetail-studio' )
				);
				?>
				</p>
			</td>
		</tr>
	<?php endif; ?>
</table>
