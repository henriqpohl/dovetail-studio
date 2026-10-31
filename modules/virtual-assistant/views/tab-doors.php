<?php
/**
 * Settings → Doors: the add-a-door form, the JSON editor of the initial menu and the step questions.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{saved: array<int,array<string,mixed>>, json: string, hint: array<string,mixed>, json_tools: array{format: string, sample: string}} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;
?>
<h3><?php esc_html_e( 'Add a door', 'dovetail-studio' ); ?></h3>
<p class="description"><?php esc_html_e( 'Adds one door without hand-editing JSON. Service is only used when Action is "service".', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_flow_add_door' ); ?>
	<input type="hidden" name="dt_va_flow_add_door" value="1">
	<table class="form-table">
		<tr>
			<th><label for="dt-va-door-id"><?php esc_html_e( 'Id', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-door-id" name="add_id" required></td>
		</tr>
		<tr>
			<th><label for="dt-va-door-label"><?php esc_html_e( 'Label', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-door-label" name="add_label" required></td>
		</tr>
		<tr>
			<th><label for="dt-va-door-action"><?php esc_html_e( 'Action', 'dovetail-studio' ); ?></label></th>
			<td><select id="dt-va-door-action" name="add_action"><option value="service">service</option><option value="other">other</option><option value="human">human</option></select></td>
		</tr>
		<tr>
			<th><label for="dt-va-door-service"><?php esc_html_e( 'Service', 'dovetail-studio' ); ?></label></th>
			<td>
				<input class="regular-text" id="dt-va-door-service" name="add_service" placeholder="<?php esc_attr_e( 'e.g. web', 'dovetail-studio' ); ?>" aria-describedby="dt-va-door-step-hint">
				<p class="description" id="dt-va-door-step-hint" aria-live="polite" data-dt-va-door-hint="<?php echo esc_attr( (string) wp_json_encode( $args['hint'] ) ); ?>"><?php echo wp_kses_post( __( 'The door opens the step named Service + <code>1</code>: Service <code>web</code> opens the step <code>web1</code>.', 'dovetail-studio' ) ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="dt-va-door-sub"><?php esc_html_e( 'Sub (optional)', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-door-sub" name="add_sub"></td>
		</tr>
		<tr>
			<th><label for="dt-va-door-short"><?php esc_html_e( 'Short (optional)', 'dovetail-studio' ); ?></label></th>
			<td><input class="regular-text" id="dt-va-door-short" name="add_short"></td>
		</tr>
		<tr>
			<th><label for="dt-va-door-icon"><?php esc_html_e( 'Icon (optional)', 'dovetail-studio' ); ?></label></th>
			<td><select id="dt-va-door-icon" name="add_icon"><option value=""><?php esc_html_e( '(default)', 'dovetail-studio' ); ?></option><option>logo</option><option>print</option><option>web</option><option>client</option><option>other</option><option>human</option></select></td>
		</tr>
	</table>
	<p><button class="button"><?php esc_html_e( 'Add door', 'dovetail-studio' ); ?></button></p>
</form>
<hr>

<h2><?php esc_html_e( 'Doors (initial menu)', 'dovetail-studio' ); ?></h2>
<p><?php echo wp_kses_post( __( 'The buttons visitors see when the assistant opens. Each door needs an <code>id</code>, a <code>label</code> and an <code>action</code> (<code>service</code>, <code>other</code> or <code>human</code>); <code>service</code> doors also need a <code>service</code>: a lowercase id (letters, numbers, <code>-</code> or <code>_</code>) of your choosing, for example <code>web</code> or <code>seo</code> — use whatever names fit this installation\'s services. Optional: <code>sub</code>, <code>short</code>, <code>icon</code> (<code>logo</code>, <code>print</code>, <code>web</code>, <code>client</code>, <code>other</code> or <code>human</code>; any other value falls back to a generic icon).', 'dovetail-studio' ) ); ?></p>
<p class="description"><?php echo wp_kses_post( __( 'A custom <code>service</code> needs a matching step id (<code>service</code> + <code>"1"</code>, e.g. <code>web1</code>) defined below in Step questions — otherwise picking that door just restarts the assistant.', 'dovetail-studio' ) ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_flow' ); ?>
	<input type="hidden" name="dt_va_flow" value="1">
	<textarea name="flow_doors" rows="24" class="dovetail-code-editor"><?php echo esc_textarea( $args['json'] ); ?></textarea>
	<p class="description">
		<?php
		if ( $args['saved'] ) {
			esc_html_e( 'Using the saved doors.', 'dovetail-studio' );
		} else {
			esc_html_e( 'Using the default doors.', 'dovetail-studio' );
		}
		?>
	</p>
	<p>
		<button class="button button-primary"><?php esc_html_e( 'Save doors', 'dovetail-studio' ); ?></button>
		<button class="button" name="restore_default" value="1" formnovalidate><?php esc_html_e( 'Restore default', 'dovetail-studio' ); ?></button>
		<?php View::render( DOVETAIL_VIRTUAL_ASSISTANT_PATH, 'partials/json-tools', $args['json_tools'] ); ?>
	</p>
	<p class="description"><?php esc_html_e( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' ); ?></p>
</form>
<hr>
