<?php
/**
 * Settings → Offline FAQ: the add-an-entry form and the JSON editor of the offline answers.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{saved: array<int,array<string,mixed>>, json: string, json_tools: array{format: string, sample: string}} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$saved = $args['saved'];
?>
<h3><?php esc_html_e( 'Add an entry', 'dovetail-studio' ); ?></h3>
<p class="description"><?php esc_html_e( 'Adds one FAQ entry without hand-editing JSON. Keywords are comma-separated plain words/phrases (no regex).', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_faq_add' ); ?>
	<input type="hidden" name="dt_va_faq_add" value="1">
	<table class="form-table">
		<tr>
			<th><label for="dt-va-faq-id"><?php esc_html_e( 'Id', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-faq-id" name="add_id" placeholder="<?php esc_attr_e( 'e.g. turnaround', 'dovetail-studio' ); ?>" required></td>
		</tr>
		<tr>
			<th><label for="dt-va-faq-keywords"><?php esc_html_e( 'Keywords', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-faq-keywords" name="add_keywords" placeholder="<?php esc_attr_e( 'Comma-separated, e.g. how long, turnaround, timeline', 'dovetail-studio' ); ?>" required></td>
		</tr>
		<tr>
			<th><label for="dt-va-faq-answer"><?php esc_html_e( 'Answer', 'dovetail-studio' ); ?></label></th>
			<td><textarea class="large-text" id="dt-va-faq-answer" name="add_answer" rows="3" required></textarea></td>
		</tr>
	</table>
	<p><button class="button"><?php esc_html_e( 'Add entry', 'dovetail-studio' ); ?></button></p>
</form>
<hr>

<h2><?php esc_html_e( 'Offline FAQ', 'dovetail-studio' ); ?></h2>
<p><?php echo wp_kses_post( __( 'Answers shown when the AI provider is unavailable or not configured, and as a quick check before sending a short question to the AI. Each entry needs an <code>id</code>, at least one plain <code>keyword</code> (no regex; words or short phrases only) and an <code>answer</code>. <code>{{COMPANY_NAME}}</code> in the answer is replaced with the configured company name. First matching entry, in list order, wins.', 'dovetail-studio' ) ); ?></p>
<p class="description"><?php esc_html_e( 'Saving here replaces the built-in offline FAQ entirely for this installation. Restore default removes the override and goes back to the built-in list.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_faq' ); ?>
	<input type="hidden" name="dt_va_faq" value="1">
	<textarea name="faq_entries" rows="24" class="dovetail-code-editor"><?php echo esc_textarea( $args['json'] ); ?></textarea>
	<p class="description">
		<?php
		if ( $saved ) {
			/* translators: %d: number of entries. */
			echo esc_html( sprintf( _n( 'Using the saved offline FAQ (%d entry).', 'Using the saved offline FAQ (%d entries).', count( $saved ), 'dovetail-studio' ), count( $saved ) ) );
		} else {
			esc_html_e( 'Using the built-in offline FAQ; the text above is only a starting example.', 'dovetail-studio' );
		}
		?>
	</p>
	<p>
		<button class="button button-primary"><?php esc_html_e( 'Save offline FAQ', 'dovetail-studio' ); ?></button>
		<button class="button" name="restore_default" value="1" formnovalidate><?php esc_html_e( 'Restore default', 'dovetail-studio' ); ?></button>
		<?php View::render( DOVETAIL_VIRTUAL_ASSISTANT_PATH, 'partials/json-tools', $args['json_tools'] ); ?>
	</p>
	<p class="description"><?php esc_html_e( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' ); ?></p>
</form>
