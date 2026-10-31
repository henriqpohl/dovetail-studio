<?php
/**
 * VA Enquiries → Company notes: private notes per client, added to the studio email of a matching enquiry.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{notes: string, clients: int} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Company notes for the AI Assistant', 'dovetail-studio' ); ?></h1>
	<p><?php esc_html_e( 'Private. When an enquiry names one of these clients (company, name, email domain, project or the description), the matching section is added to the bottom of the studio email only. Nothing here is shown to visitors or sent to clients.', 'dovetail-studio' ); ?></p>
	<p>
		<?php echo wp_kses_post( __( 'Format: one <code>## Client name</code> heading per client, an optional <code>aliases: other name, domain.com, project name</code> line, then notes.', 'dovetail-studio' ) ); ?>
		<?php
		/* translators: %d: number of clients. */
		echo esc_html( sprintf( _n( '%d client on file.', '%d clients on file.', $args['clients'], 'dovetail-studio' ), $args['clients'] ) );
		?>
	</p>
	<form method="post">
		<?php wp_nonce_field( 'dt_va_company_notes' ); ?>
		<textarea name="dt_va_notes" rows="30" class="dovetail-code-editor"><?php echo esc_textarea( $args['notes'] ); ?></textarea>
		<p><button class="button button-primary"><?php esc_html_e( 'Save notes', 'dovetail-studio' ); ?></button></p>
	</form>
</div>
