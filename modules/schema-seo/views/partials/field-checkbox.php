<?php
/**
 * A checkbox row.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{name: string, label: string, value: mixed, help: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><?php echo esc_html( $args['label'] ); ?></th>
	<td><label><input name="<?php echo esc_attr( $args['name'] ); ?>" type="checkbox" value="1" <?php checked( 1, $args['value'] ); ?>> <?php echo esc_html( $args['help'] ); ?></label></td>
</tr>
