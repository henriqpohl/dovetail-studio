<?php
/**
 * A select row of a settings form.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{id: string, name: string, value: string, label: string, choices: array<int|string,string>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><label for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label></th>
	<td>
		<select id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>">
			<?php foreach ( $args['choices'] as $value => $text ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $args['value'], (string) $value ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
