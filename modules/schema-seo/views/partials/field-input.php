<?php
/**
 * A single-line field row without a source selector.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{field: string, name: string, label: string, value: string, type: string, help: string, step: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><label for="dovetail-<?php echo esc_attr( $args['field'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label></th>
	<td>
		<input class="regular-text" id="dovetail-<?php echo esc_attr( $args['field'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" type="<?php echo esc_attr( $args['type'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>"<?php echo '' !== $args['step'] ? ' step="' . esc_attr( $args['step'] ) . '"' : ''; ?>>
		<?php if ( '' !== $args['help'] ) : ?>
			<p class="description"><?php echo esc_html( $args['help'] ); ?></p>
		<?php endif; ?>
	</td>
</tr>
