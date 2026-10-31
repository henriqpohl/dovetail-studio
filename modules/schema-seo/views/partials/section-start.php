<?php
/**
 * Opens a settings card: header and the start of its form table. Closed by partials/section-end.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{title: string, description: string, id: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="dovetail-card"<?php echo '' !== $args['id'] ? ' id="dovetail-' . esc_attr( $args['id'] ) . '"' : ''; ?>>
	<div class="dovetail-card-header">
		<h2><?php echo esc_html( $args['title'] ); ?></h2>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
	</div>
	<table class="form-table" role="presentation"><tbody>
