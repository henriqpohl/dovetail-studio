<?php
/**
 * Source selector of a field shared with Yoast SEO, and the Yoast value recognized in the admin.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{name: string, value: string, yoast: string} $args Yoast: the recognized value, or '' when none.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="dovetail-source">
	<label><?php esc_html_e( 'Source:', 'dovetail-studio' ); ?> <select name="<?php echo esc_attr( $args['name'] ); ?>">
		<option value="automatic" <?php selected( $args['value'], 'automatic' ); ?>><?php esc_html_e( 'Automatic — prefer Yoast', 'dovetail-studio' ); ?></option>
		<option value="plugin" <?php selected( $args['value'], 'plugin' ); ?>><?php esc_html_e( 'Use plugin value', 'dovetail-studio' ); ?></option>
	</select></label>
	<?php if ( '' !== $args['yoast'] ) : ?>
		<p class="description"><strong><?php esc_html_e( 'Recognized Yoast value:', 'dovetail-studio' ); ?></strong> <?php echo esc_html( $args['yoast'] ); ?></p>
	<?php endif; ?>
</div>
