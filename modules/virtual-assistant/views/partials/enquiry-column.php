<?php
/**
 * VA Enquiries list: one custom column of an enquiry (ref, contact, answered or call back).
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{column: string, ref: string, email: string, phone: string, answered: string, answered_url: string, callback: bool, time: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( 'ref' === $args['column'] ) : ?>
	<?php echo esc_html( $args['ref'] ); ?>
<?php elseif ( 'contact' === $args['column'] ) : ?>
	<?php echo esc_html( $args['email'] ); ?><br><?php echo esc_html( $args['phone'] ); ?>
<?php elseif ( 'answered' === $args['column'] ) : ?>
	<?php if ( '' !== $args['answered'] ) : ?>
		<span class="dovetail-text-success"><?php esc_html_e( 'Yes,', 'dovetail-studio' ); ?> <?php echo esc_html( $args['answered'] ); ?></span><br><a href="<?php echo esc_url( $args['answered_url'] ); ?>"><?php esc_html_e( 'Undo', 'dovetail-studio' ); ?></a>
	<?php else : ?>
		<a href="<?php echo esc_url( $args['answered_url'] ); ?>"><?php esc_html_e( 'Mark as answered', 'dovetail-studio' ); ?></a>
	<?php endif; ?>
<?php elseif ( 'callback' === $args['column'] ) : ?>
	<?php if ( $args['callback'] ) : ?>
		<strong class="dovetail-text-danger"><?php esc_html_e( 'Yes', 'dovetail-studio' ); ?></strong> <?php echo esc_html( $args['time'] ); ?>
	<?php else : ?>
		<?php esc_html_e( 'No', 'dovetail-studio' ); ?>
	<?php endif; ?>
<?php endif; ?>
