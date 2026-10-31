<?php
/**
 * Enquiries screens: reminder of the settings sections whose company setup is not reviewed yet.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{links: array<int,array{url: string, title: string}>} $args
 */

defined( 'ABSPATH' ) || exit;

$links = array();

foreach ( $args['links'] as $item ) {
	$links[] = '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>';
}
?>
<div class="notice dovetail-notice inline notice-warning">
	<p><strong><?php esc_html_e( 'DT AI Assistant:', 'dovetail-studio' ); ?></strong> <?php esc_html_e( 'company setup needs review:', 'dovetail-studio' ); ?> <?php echo wp_kses_post( implode( ', ', $links ) ); ?><?php esc_html_e( '. Configure and review these sections, or explicitly accept an available default option. This reminder does not block the assistant.', 'dovetail-studio' ); ?></p>
</div>
