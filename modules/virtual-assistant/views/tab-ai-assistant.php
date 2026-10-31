<?php
/**
 * Settings → AI Assistant: the assistant's name, the ballpark price and the spelling locale.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{name: string, external: bool, ballpark: bool, locale: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'AI Assistant', 'dovetail-studio' ); ?></h2>
<p><?php esc_html_e( 'Give the assistant a name if you\'d like. When set, the greeting reads “Hello. I\'m [Name] the AI Assistant at …” instead of the default “Hello. I\'m the AI Assistant at …”.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_ai_assistant' ); ?>
	<input type="hidden" name="dt_va_ai_assistant" value="1">
	<table class="form-table">
		<tr>
			<th><label for="dt-va-ai-assistant-name"><?php esc_html_e( 'AI Assistant name', 'dovetail-studio' ); ?></label></th>
			<td>
				<input class="regular-text" id="dt-va-ai-assistant-name" name="ai_assistant_name" type="text" maxlength="60" value="<?php echo esc_attr( $args['name'] ); ?>"<?php echo $args['external'] ? ' readonly' : ''; ?>>
				<p class="description">
					<?php
					if ( $args['external'] ) {
						esc_html_e( 'Defined by DT_VA_AI_ASSISTANT_NAME in wp-config.php. Edit it there, or remove the definition to use this field.', 'dovetail-studio' );
					} else {
						esc_html_e( 'Leave blank to use the default greeting.', 'dovetail-studio' );
					}
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="dt-va-ai-assistant-ballpark"><?php esc_html_e( 'Offer a ballpark price', 'dovetail-studio' ); ?></label></th>
			<td>
				<label><input id="dt-va-ai-assistant-ballpark" type="checkbox" name="ai_assistant_ballpark" value="1" <?php checked( $args['ballpark'] ); ?>> <?php esc_html_e( 'Allow a rough price range when the knowledge base has one and the client asks about cost', 'dovetail-studio' ); ?></label>
				<p class="description"><?php esc_html_e( 'Always ends with "a fixed quote follows once the team has seen your brief", whichever way this is set. The assistant never gives a firm price either way.', 'dovetail-studio' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="dt-va-ai-assistant-locale"><?php esc_html_e( 'Spelling and dates', 'dovetail-studio' ); ?></label></th>
			<td>
				<select id="dt-va-ai-assistant-locale" name="ai_assistant_locale">
					<option value="en_us" <?php selected( $args['locale'], 'en_us' ); ?>><?php esc_html_e( 'American English (color, specialize; month/day/year)', 'dovetail-studio' ); ?></option>
					<option value="en_gb" <?php selected( $args['locale'], 'en_gb' ); ?>><?php esc_html_e( 'British and Irish English (colour, specialise; day/month/year)', 'dovetail-studio' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Applies to the AI answering layer only, not the built-in offline FAQ.', 'dovetail-studio' ); ?></p>
			</td>
		</tr>
	</table>
	<p><button class="button button-primary"><?php esc_html_e( 'Save AI Assistant settings', 'dovetail-studio' ); ?></button></p>
</form>
