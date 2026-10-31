<?php
/**
 * Status badge of the integration card: "Active", or the given text when off.
 *
 * @package DovetailStudio\SchemaSeoWooCommerce
 *
 * @var array{active: bool, off_label: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $args['active'] ) : ?>
	<span class="dovetail-badge is-enabled"><?php esc_html_e( 'Active', 'dovetail-studio' ); ?></span>
<?php else : ?>
	<span class="dovetail-badge"><?php echo esc_html( $args['off_label'] ); ?></span>
<?php endif; ?>
