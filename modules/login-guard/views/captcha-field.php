<?php
/**
 * The captcha field of the sign-in and registration forms.
 *
 * Turnstile renders itself into its element and adds the cf-turnstile-response
 * field. reCAPTCHA v3 has no widget: assets/js/recaptcha.js fills the hidden
 * field with a token.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{provider: string, site_key: string, action: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( 'turnstile' === $args['provider'] ) : ?>
	<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $args['site_key'] ); ?>" data-action="<?php echo esc_attr( $args['action'] ); ?>" data-size="flexible"></div>
<?php else : ?>
	<input type="hidden" name="g-recaptcha-response" value="" data-dovetail-lg-recaptcha="<?php echo esc_attr( $args['action'] ); ?>" data-sitekey="<?php echo esc_attr( $args['site_key'] ); ?>">
<?php endif; ?>
