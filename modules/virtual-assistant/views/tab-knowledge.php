<?php
/**
 * Settings → Knowledge base: the override of knowledge.md the assistant answers from.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{override: string, text: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Knowledge base', 'dovetail-studio' ); ?></h2>
<p><?php echo wp_kses_post( __( 'The only facts the AI Assistant is allowed to answer questions from. Customize it for this installation here; until saved, the assistant uses the bundled <code>knowledge.md</code>. Restore default clears this override and goes back to <code>knowledge.md</code>.', 'dovetail-studio' ) ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_knowledge' ); ?>
	<input type="hidden" name="dt_va_knowledge" value="1">
	<textarea name="knowledge_text" rows="30" class="dovetail-code-editor"><?php echo esc_textarea( $args['text'] ); ?></textarea>
	<p class="description">
		<?php
		if ( '' !== $args['override'] ) {
			esc_html_e( 'Using the saved override.', 'dovetail-studio' );
		} else {
			esc_html_e( 'Using the default from knowledge.md.', 'dovetail-studio' );
		}
		?>
	</p>
	<p>
		<button class="button button-primary"><?php esc_html_e( 'Save knowledge base', 'dovetail-studio' ); ?></button>
		<button class="button" name="restore_default" value="1" formnovalidate><?php esc_html_e( 'Restore default', 'dovetail-studio' ); ?></button>
	</p>
</form>
