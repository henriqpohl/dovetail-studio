<?php
/**
 * A textarea row, with the Yoast source selector when it is shared with Yoast SEO.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{dir: string, field: string, name: string, label: string, value: string, help: string, source: array<string,mixed>|null} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><label for="dovetail-<?php echo esc_attr( $args['field'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label></th>
	<td>
		<textarea class="large-text" rows="4" id="dovetail-<?php echo esc_attr( $args['field'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>"><?php echo esc_textarea( $args['value'] ); ?></textarea>
		<?php if ( '' !== $args['help'] ) : ?>
			<p class="description"><?php echo esc_html( $args['help'] ); ?></p>
		<?php endif; ?>
		<?php
		if ( null !== $args['source'] ) {
			View::render( $args['dir'], 'partials/source-control', $args['source'] );
		}
		?>
	</td>
</tr>
