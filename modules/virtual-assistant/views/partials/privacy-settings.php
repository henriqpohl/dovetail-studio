<?php
/**
 * Settings → Company: how long enquiries are kept.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{external: bool, months: int} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Privacy', 'dovetail-studio' ); ?></h2>
<p><?php esc_html_e( 'Enquiries hold personal data. They appear in Tools > Export Personal Data and Tools > Erase Personal Data, deleting an enquiry also deletes its attachment, and a suggested text is added to Settings > Privacy for your privacy policy.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_privacy' ); ?>
	<input type="hidden" name="dt_va_privacy" value="1">
	<table class="form-table">
		<tr>
			<th><label for="dt-va-retention"><?php esc_html_e( 'Keep enquiries for', 'dovetail-studio' ); ?></label></th>
			<td>
				<input type="number" min="0" max="120" id="dt-va-retention" name="retention_months" value="<?php echo esc_attr( $args['months'] ); ?>"<?php echo $args['external'] ? ' readonly' : ''; ?>> <?php esc_html_e( 'months', 'dovetail-studio' ); ?>
				<p class="description">
					<?php if ( $args['external'] ) : ?>
						<?php esc_html_e( 'Defined by', 'dovetail-studio' ); ?> DT_VA_RETENTION_MONTHS <?php esc_html_e( 'in wp-config.php. Edit it there, or remove the definition to use this field.', 'dovetail-studio' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Older enquiries and their attachments are deleted every day. 0 keeps them until you delete them.', 'dovetail-studio' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
	</table>
	<?php if ( ! $args['external'] ) : ?>
		<p><button class="button button-primary"><?php esc_html_e( 'Save privacy settings', 'dovetail-studio' ); ?></button></p>
	<?php endif; ?>
</form>
