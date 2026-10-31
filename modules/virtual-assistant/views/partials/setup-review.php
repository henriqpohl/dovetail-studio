<?php
/**
 * Company setup review of one settings section: its state, or the button that confirms it.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{section: string, state: array{reviewed: bool, eligible: bool, reason: string, hash: string, button: string}, error: string} $args
 */

defined( 'ABSPATH' ) || exit;

$state = $args['state'];
?>
<h3><?php esc_html_e( 'Company setup review', 'dovetail-studio' ); ?></h3>

<?php if ( '' !== $args['error'] ) : ?>
	<p role="alert"><?php echo esc_html( $args['error'] ); ?></p>
<?php endif; ?>

<?php if ( $state['reviewed'] ) : ?>
	<p><?php esc_html_e( 'Reviewed for the current configuration. Saving changes or restoring defaults requires a new review.', 'dovetail-studio' ); ?></p>
<?php elseif ( ! $state['eligible'] ) : ?>
	<p><?php echo esc_html( $state['reason'] ); ?></p>
<?php else : ?>
	<p><?php esc_html_e( 'Save any edits first. This confirmation applies to the currently saved configuration, not unsaved editor changes.', 'dovetail-studio' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'dt_va_setup_review' ); ?>
		<input type="hidden" name="setup_hash" value="<?php echo esc_html( $state['hash'] ); ?>"><button class="button" name="dt_va_setup_review" value="<?php echo esc_html( $args['section'] ); ?>"><?php echo esc_html( $state['button'] ); ?></button>
	</form>
<?php endif; ?>
