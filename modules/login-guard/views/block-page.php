<?php
/**
 * Body of the block page (HTTP 403), printed by wp_die().
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{title: string, text: string, ip: string, time: string} $args Text: the custom block page text, or '' for the default message.
 */

defined( 'ABSPATH' ) || exit;
?>
<h1><?php echo esc_html( $args['title'] ); ?></h1>
<?php if ( '' !== $args['text'] ) : ?>
	<?php echo wp_kses_post( wpautop( esc_html( $args['text'] ) ) ); ?>
<?php else : ?>
	<p><?php esc_html_e( 'Your access has been limited by the site\'s security settings. If you think this is a mistake, contact the site owner.', 'dovetail-studio' ); ?></p>
<?php endif; ?>
<p><small>
<?php
/* translators: 1: visitor IP address, 2: date and time. */
echo esc_html( sprintf( __( 'Your IP address: %1$s. Time: %2$s.', 'dovetail-studio' ), $args['ip'], $args['time'] ) );
?>
</small></p>
