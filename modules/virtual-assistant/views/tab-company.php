<?php
/**
 * Settings → Company, first section: the company name, avatar and email identity.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{fields: array<string,array{label: string, constant: string, external: bool, value: string, help: string}>, collapse_avatar: bool, default_sender: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Company', 'dovetail-studio' ); ?></h2>
<p><?php esc_html_e( 'Configure the public company name and the email identity used by the assistant.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_company' ); ?>
	<input type="hidden" name="dt_va_company" value="1">
	<table class="form-table">
		<?php foreach ( $args['fields'] as $field => $meta ) : ?>
			<?php if ( 'avatar' === $field ) : ?>
				<tr>
					<th><label for="dt-va-company-avatar"><?php esc_html_e( 'Avatar', 'dovetail-studio' ); ?></label></th>
					<td>
						<input class="regular-text" id="dt-va-company-avatar" name="avatar" type="text" maxlength="2" value="<?php echo esc_attr( $meta['value'] ); ?>"<?php echo $meta['external'] ? ' readonly' : ''; ?>>
						<p class="description">
							<?php if ( $meta['external'] ) : ?>
								<?php esc_html_e( 'Defined by', 'dovetail-studio' ); ?> <?php echo esc_html( $meta['constant'] ); ?> <?php esc_html_e( 'in wp-config.php. Edit it there, or remove the definition to use this field.', 'dovetail-studio' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Up to 2 characters, shown in the tab and panel header. Leave blank to use the default, DT.', 'dovetail-studio' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="dt-va-company-collapse-avatar"><?php esc_html_e( 'Collapse Avatar', 'dovetail-studio' ); ?></label></th>
					<td>
						<label class="dovetail-inline-check"><input type="checkbox" id="dt-va-company-collapse-avatar" name="collapse_avatar" value="1"<?php checked( $args['collapse_avatar'] ); ?>> <?php esc_html_e( 'Hide the avatar in the tab shown before the assistant is opened', 'dovetail-studio' ); ?></label>
						<p class="description"><?php esc_html_e( 'The avatar still appears inside the open assistant panel; this only affects the closed tab.', 'dovetail-studio' ); ?></p>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th><label for="dt-va-company-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $meta['label'] ); ?></label></th>
					<td>
						<input class="regular-text" id="dt-va-company-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" type="<?php echo in_array( $field, array( 'to', 'from' ), true ) ? 'email' : 'text'; ?>" value="<?php echo esc_attr( $meta['value'] ); ?>"<?php echo 'from' === $field ? ' placeholder="' . esc_attr( $args['default_sender'] ) . '"' : ' required'; ?><?php echo $meta['external'] ? ' readonly' : ''; ?>>
						<p class="description">
							<?php if ( $meta['external'] ) : ?>
								<?php esc_html_e( 'Defined by', 'dovetail-studio' ); ?> <?php echo esc_html( $meta['constant'] ); ?> <?php esc_html_e( 'in wp-config.php. Edit it there, or remove the definition to use this field.', 'dovetail-studio' ); ?>
							<?php else : ?>
								<?php echo esc_html( $meta['help'] ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</table>
	<p><button class="button button-primary"><?php esc_html_e( 'Save company settings', 'dovetail-studio' ); ?></button></p>
</form>
