<?php
/**
 * A select row.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{field: string, name: string, label: string, value: string, choices: array<string,string>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><label for="dovetail-<?php echo esc_attr( $args['field'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label></th>
	<td>
		<select id="dovetail-<?php echo esc_attr( $args['field'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>">
			<?php foreach ( $args['choices'] as $choice_value => $choice_label ) : ?>
				<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $args['value'], $choice_value ); ?>><?php echo esc_html( $choice_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
