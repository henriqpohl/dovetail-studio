<?php
/**
 * Import, export and sample download buttons for a JSON editor; assets/js/admin-json.js wires them up.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{format: string, sample: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="dt-va-json-tools" data-format="<?php echo esc_html( $args['format'] ); ?>" data-sample="<?php echo esc_textarea( $args['sample'] ); ?>">
	<button type="button" class="button" data-json-action="import"><?php esc_html_e( 'Import', 'dovetail-studio' ); ?></button>
	<button type="button" class="button" data-json-action="export"><?php esc_html_e( 'Export', 'dovetail-studio' ); ?></button>
	<button type="button" class="button" data-json-action="sample"><?php esc_html_e( 'Download a Sample', 'dovetail-studio' ); ?></button>
	<input type="file" accept=".json,application/json" hidden><span role="status" aria-live="polite" class="dt-va-json-status"></span>
</span>
