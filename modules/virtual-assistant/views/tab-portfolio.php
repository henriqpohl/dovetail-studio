<?php
/**
 * Settings → Portfolio: the add-a-project form and the JSON editor of the case studies.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{saved: array<int,array<string,mixed>>, json: string, starter: string, json_tools: array{format: string, sample: string}} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$saved = $args['saved'];
?>
<h3><?php esc_html_e( 'Add an entry', 'dovetail-studio' ); ?></h3>
<p class="description"><?php esc_html_e( 'Add a real project. Keywords are space-separated plain words (no regex). Example projects are never added automatically.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_case_studies_add' ); ?>
	<input type="hidden" name="dt_va_case_studies_add" value="1">
	<table class="form-table">
		<?php
		foreach ( array(
			't'   => __( 'Title', 'dovetail-studio' ),
			's'   => __( 'Description', 'dovetail-studio' ),
			'o'   => __( 'Outcome', 'dovetail-studio' ),
			'url' => __( 'Project URL', 'dovetail-studio' ),
			'img' => __( 'Image URL (http/https)', 'dovetail-studio' ),
			'sec' => __( 'Sector', 'dovetail-studio' ),
			'ind' => __( 'Industry keywords', 'dovetail-studio' ),
		) as $key => $label ) :
			?>
			<tr>
				<th><label for="dt-va-case-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td><input class="regular-text" id="dt-va-case-<?php echo esc_attr( $key ); ?>" name="add_<?php echo esc_attr( $key ); ?>"<?php echo in_array( $key, array( 't', 's' ), true ) ? ' required' : ''; ?>></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<p><button class="button"><?php esc_html_e( 'Add entry', 'dovetail-studio' ); ?></button></p>
</form>
<hr>

<h2><?php esc_html_e( 'Portfolio', 'dovetail-studio' ); ?></h2>
<p><?php echo wp_kses_post( __( 'Provide 1–30 projects with a title (<code>t</code>, max 60 characters) and description (<code>s</code>, max 140). Optional fields: outcome (<code>o</code>, max 80), project URL (<code>url</code>, http(s) or /path), image (<code>img</code>, http(s) only), sector (<code>sec</code>, max 60) and plain industry keywords (<code>ind</code>, max 200). Matching projects appear first, followed by other saved projects, up to three cards.', 'dovetail-studio' ) ); ?></p>
<p><?php esc_html_e( 'Saving replaces the built-in portfolio entirely. Restore default removes the override; the default portfolio is empty.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_case_studies' ); ?>
	<input type="hidden" name="dt_va_case_studies" value="1">
	<textarea aria-label="<?php esc_attr_e( 'Portfolio JSON', 'dovetail-studio' ); ?>" name="case_studies_entries" rows="24" class="dovetail-code-editor" placeholder="<?php echo esc_textarea( $args['starter'] ); ?>"><?php echo esc_textarea( $args['json'] ); ?></textarea>
	<p class="description">
		<?php
		if ( $saved ) {
			/* translators: %d: number of entries. */
			echo esc_html( sprintf( _n( 'Using the saved portfolio (%d entry).', 'Using the saved portfolio (%d entries).', count( $saved ), 'dovetail-studio' ), count( $saved ) ) );
		} else {
			esc_html_e( 'The placeholder is only a starting example, not your real portfolio. No projects are published by default.', 'dovetail-studio' );
		}
		?>
	</p>
	<p>
		<button class="button button-primary"><?php esc_html_e( 'Save portfolio', 'dovetail-studio' ); ?></button>
		<button class="button" name="restore_default" value="1" formnovalidate><?php esc_html_e( 'Restore default', 'dovetail-studio' ); ?></button>
		<?php View::render( DOVETAIL_VIRTUAL_ASSISTANT_PATH, 'partials/json-tools', $args['json_tools'] ); ?>
	</p>
	<p class="description"><?php esc_html_e( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' ); ?></p>
</form>
