<?php
/**
 * An image URL row with the WordPress media picker, with the Yoast source selector when it is shared with Yoast SEO.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{dir: string, field: string, name: string, label: string, value: string, source: array<string,mixed>|null} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><label for="dovetail-<?php echo esc_attr( $args['field'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label></th>
	<td>
		<div class="dovetail-media">
			<input class="regular-text" id="dovetail-<?php echo esc_attr( $args['field'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" type="url" value="<?php echo esc_attr( $args['value'] ); ?>">
			<button class="button dovetail-select-media" data-target="dovetail-<?php echo esc_attr( $args['field'] ); ?>" type="button"><?php esc_html_e( 'Select image', 'dovetail-studio' ); ?></button>
		</div>
		<?php
		if ( null !== $args['source'] ) {
			View::render( $args['dir'], 'partials/source-control', $args['source'] );
		}
		?>
	</td>
</tr>
