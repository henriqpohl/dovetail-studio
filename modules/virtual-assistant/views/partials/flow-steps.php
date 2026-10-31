<?php
/**
 * Settings → Doors, second half: the add-a-step form and the JSON editor of the step questions.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{saved: array<string,mixed>, json: string, builtin: string[], conditions: string[], json_tools: array{format: string, sample: string}} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$saved = $args['saved'];
?>
<h3><?php esc_html_e( 'Add a step', 'dovetail-studio' ); ?></h3>
<p class="description"><?php echo wp_kses_post( __( 'Adds one step without hand-editing JSON. <code>next</code> here only accepts a plain step id (no branching); use the JSON below for a branch. <code>options</code> is comma-separated and only used when Type is "choice".', 'dovetail-studio' ) ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_flow_add_step' ); ?>
	<input type="hidden" name="dt_va_flow_add_step" value="1">
	<table class="form-table">
		<tr>
			<th><label for="dt-va-step-id"><?php esc_html_e( 'Step id', 'dovetail-studio' ); ?></label></th>
			<td>
				<input class="regular-text" id="dt-va-step-id" name="add_id" placeholder="<?php esc_attr_e( 'e.g. web1', 'dovetail-studio' ); ?>" required>
				<p class="description"><?php echo wp_kses_post( __( 'The first step of a flow is named after its door\'s Service + <code>1</code> (Service <code>web</code> → <code>web1</code>); the next steps can have any name. The order is set only by each step\'s Next step id.', 'dovetail-studio' ) ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="dt-va-step-type"><?php esc_html_e( 'Type', 'dovetail-studio' ); ?></label></th>
			<td><select id="dt-va-step-type" name="add_type"><option value="choice">choice</option><option value="text">text</option></select></td>
		</tr>
		<tr>
			<th><label for="dt-va-step-question"><?php esc_html_e( 'Question', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-step-question" name="add_question" required></td>
		</tr>
		<tr>
			<th><label for="dt-va-step-example"><?php esc_html_e( 'Example (optional)', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-step-example" name="add_example"></td>
		</tr>
		<tr>
			<th><label for="dt-va-step-answerkey"><?php esc_html_e( 'Answer key', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-step-answerkey" name="add_answerkey" placeholder="<?php esc_attr_e( 'e.g. site_type', 'dovetail-studio' ); ?>" required></td>
		</tr>
		<tr>
			<th><label for="dt-va-step-options"><?php esc_html_e( 'Options', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-step-options" name="add_options" placeholder="<?php esc_attr_e( 'Comma-separated, e.g. Brochure site, Online shop, Landing page', 'dovetail-studio' ); ?>"></td>
		</tr>
		<tr>
			<th><label for="dt-va-step-next"><?php esc_html_e( 'Next step id', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-step-next" name="add_next" placeholder="<?php esc_attr_e( 'e.g. web2', 'dovetail-studio' ); ?>" required></td>
		</tr>
	</table>
	<p><button class="button"><?php esc_html_e( 'Add step', 'dovetail-studio' ); ?></button></p>
</form>
<hr>

<h2><?php esc_html_e( 'Step questions', 'dovetail-studio' ); ?></h2>
<p><?php echo wp_kses_post( __( 'The questions asked after a door is picked, keyed by step id. Each step needs a <code>type</code> (<code>choice</code> or <code>text</code>), a <code>question</code>, an <code>answerKey</code> (where the answer is stored) and a <code>next</code> step id. <code>choice</code> also needs 1–8 <code>options</code>.', 'dovetail-studio' ) ); ?></p>
<p class="description"><?php esc_html_e( 'A step id you don\'t redefine here keeps its built-in question and behaviour, so you can override just one step at a time. Step ids already used by the assistant:', 'dovetail-studio' ); ?> <code><?php echo esc_html( implode( ', ', $args['builtin'] ) ); ?></code><?php echo wp_kses_post( __( '. <code>next</code> can target any of those even if you never define them here — the fixed pipeline (<code>work</code>/<code>money</code>/<code>timing</code>/<code>capture</code>) always collects the budget, timing and contact details itself and cannot be redefined, only referenced.', 'dovetail-studio' ) ); ?></p>
<p class="description"><?php echo wp_kses_post( __( 'Branch instead of a plain <code>next</code> id: <code>{"if": {"key": "site_type", "answerEquals": "Online shop"}, "then": "shop1", "else": "web2"}</code>. Condition keys:', 'dovetail-studio' ) ); ?> <code><?php echo esc_html( implode( ', ', $args['conditions'] ) ); ?></code>.</p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_flow_steps' ); ?>
	<input type="hidden" name="dt_va_flow_steps" value="1">
	<textarea name="flow_steps" rows="24" class="dovetail-code-editor"><?php echo esc_textarea( $args['json'] ); ?></textarea>
	<p class="description">
		<?php
		if ( $saved ) {
			/* translators: %d: number of steps. */
			echo esc_html( sprintf( _n( 'Using the saved steps (%d).', 'Using the saved steps (%d).', count( $saved ), 'dovetail-studio' ), count( $saved ) ) );
		} else {
			esc_html_e( 'Using the built-in steps; the text above is only a starting example.', 'dovetail-studio' );
		}
		?>
	</p>
	<p>
		<button class="button button-primary"><?php esc_html_e( 'Save step questions', 'dovetail-studio' ); ?></button>
		<button class="button" name="restore_default" value="1" formnovalidate><?php esc_html_e( 'Restore default', 'dovetail-studio' ); ?></button>
		<?php View::render( DOVETAIL_VIRTUAL_ASSISTANT_PATH, 'partials/json-tools', $args['json_tools'] ); ?>
	</p>
	<p class="description"><?php esc_html_e( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' ); ?></p>
</form>
