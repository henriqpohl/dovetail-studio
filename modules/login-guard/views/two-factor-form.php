<?php
/**
 * The two-factor code screen, between login_header() and login_footer() of wp-login.php.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{action: string, remember_days: int} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<form name="dovetail-lg-2fa" id="loginform" action="<?php echo esc_url( $args['action'] ); ?>" method="post">
	<p>
		<label for="dovetail-lg-code"><?php esc_html_e( 'Authentication code', 'dovetail-studio' ); ?></label>
		<input type="text" name="dovetail_lg_code" id="dovetail-lg-code" class="input" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required aria-describedby="dovetail-lg-code-help">
	</p>
	<p class="description" id="dovetail-lg-code-help"><?php esc_html_e( 'Enter the 6-digit code from your authenticator app, or one of your recovery codes.', 'dovetail-studio' ); ?></p>

	<?php if ( $args['remember_days'] > 0 ) : ?>
		<p class="forgetmenot">
			<input type="checkbox" name="dovetail_lg_trust" id="dovetail-lg-trust" value="1">
			<label for="dovetail-lg-trust">
			<?php
			/* translators: %d: number of days. */
			echo esc_html( sprintf( _n( 'Remember this device for %d day', 'Remember this device for %d days', $args['remember_days'], 'dovetail-studio' ), $args['remember_days'] ) );
			?>
			</label>
		</p>
	<?php endif; ?>

	<p class="submit">
		<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Verify', 'dovetail-studio' ); ?>">
	</p>
</form>
