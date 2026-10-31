<?php
/**
 * Warning shown while WooCommerce is not active.
 *
 * @package DovetailStudio\SchemaSeoWooCommerce
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice dovetail-notice inline notice-warning">
	<p>
		<strong><?php esc_html_e( 'Schema SEO WooCommerce requires WooCommerce.', 'dovetail-studio' ); ?></strong>
		<?php esc_html_e( 'Install and activate WooCommerce to enable product schema and merchant SEO features. The module can remain enabled, but no WooCommerce SEO hooks will run until the dependency is active.', 'dovetail-studio' ); ?>
	</p>
</div>
