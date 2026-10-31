<?php
/**
 * Preview of the JSON-LD published on the homepage, with load and copy buttons (assets/js/admin.js).
 *
 * @package DovetailStudio\SchemaSeo
 */

defined( 'ABSPATH' ) || exit;
?>
<tr class="dovetail-json-row">
	<td colspan="2">
		<div class="dovetail-json-actions">
			<button class="button button-secondary" id="dovetail-load-json" type="button"><?php esc_html_e( 'Load current JSON', 'dovetail-studio' ); ?></button>
			<button class="button" id="dovetail-copy-json" type="button" disabled><?php esc_html_e( 'Copy JSON', 'dovetail-studio' ); ?></button>
			<span class="dovetail-json-status" id="dovetail-json-status" role="status" aria-live="polite"></span>
		</div>
		<textarea class="large-text code dovetail-json-preview" id="dovetail-json-output" rows="20" readonly spellcheck="false" placeholder="<?php esc_attr_e( 'Click “Load current JSON” to retrieve the published Schema.', 'dovetail-studio' ); ?>"></textarea>
		<p class="description"><?php esc_html_e( 'This is the public homepage output, not a simulation. Save pending changes first. With Yoast active, the complete enriched @graph is shown; without Yoast, the module’s native JSON-LD is shown.', 'dovetail-studio' ); ?></p>
	</td>
</tr>
