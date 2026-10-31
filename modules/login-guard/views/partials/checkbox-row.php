<?php
/**
 * A checkbox row of a settings form.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{name: string, checked: bool, label: string, description: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><?php echo esc_html( $args['label'] ); ?></th>
	<td>
		<label><input type="checkbox" name="<?php echo esc_attr( $args['name'] ); ?>" value="1" <?php checked( $args['checked'] ); ?>> <?php esc_html_e( 'Enabled', 'dovetail-studio' ); ?></label>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
	</td>
</tr>
