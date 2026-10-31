<?php
/**
 * Integration overview: where each value comes from.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{shared: array<int,array{label: string, source: string, found: string}>, plugin: string[]} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr class="dovetail-integration-row">
	<td colspan="2">
		<div class="dovetail-table-scroll">
			<table class="widefat striped dovetail-integration">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Information', 'dovetail-studio' ); ?></th>
						<th><?php esc_html_e( 'Configured priority', 'dovetail-studio' ); ?></th>
						<th><?php esc_html_e( 'Yoast value recognized in admin', 'dovetail-studio' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="dovetail-integration-group"><th colspan="3"><?php esc_html_e( 'Fields shared with Yoast SEO', 'dovetail-studio' ); ?></th></tr>
					<?php foreach ( $args['shared'] as $row ) : ?>
						<tr><td><?php echo esc_html( $row['label'] ); ?></td><td><span class="dovetail-origin-badge is-shared"><?php echo esc_html( $row['source'] ); ?></span></td><td><?php echo esc_html( $row['found'] ); ?></td></tr>
					<?php endforeach; ?>
					<tr class="dovetail-integration-group"><th colspan="3"><?php esc_html_e( 'Fields managed by this plugin', 'dovetail-studio' ); ?></th></tr>
					<?php foreach ( $args['plugin'] as $label ) : ?>
						<tr><td><?php echo esc_html( $label ); ?></td><td colspan="2"><span class="dovetail-origin-badge is-plugin"><?php esc_html_e( 'Controlled directly by plugin', 'dovetail-studio' ); ?></span></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="description"><?php esc_html_e( 'Runtime values already present in Yoast’s generated Organization graph remain authoritative in Automatic mode, even when this admin summary cannot read them.', 'dovetail-studio' ); ?></p>
	</td>
</tr>
