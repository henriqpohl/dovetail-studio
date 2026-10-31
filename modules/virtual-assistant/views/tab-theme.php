<?php
/**
 * Settings → Theme: the colors of the public chat.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{theme: array<string,string>, fields: array<string,array{label: string}>} $args
 */

defined( 'ABSPATH' ) || exit;

$theme = $args['theme'];
?>
<h2><?php esc_html_e( 'Theme', 'dovetail-studio' ); ?></h2>
<p><?php esc_html_e( 'Customize the assistant colors. Changes appear on the public chat after saving.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_theme' ); ?>
	<input type="hidden" name="dt_va_theme" value="1">
	<table class="form-table">
		<?php foreach ( $args['fields'] as $key => $meta ) : ?>
			<tr>
				<th><label for="dt-va-theme-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta['label'] ); ?></label></th>
				<td><input id="dt-va-theme-<?php echo esc_attr( $key ); ?>" name="theme[<?php echo esc_attr( $key ); ?>]" type="color" value="<?php echo esc_attr( $theme[ $key ] ); ?>"> <code><?php echo esc_html( $theme[ $key ] ); ?></code></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<p>
		<button class="button button-primary"><?php esc_html_e( 'Save theme', 'dovetail-studio' ); ?></button>
		<button class="button" name="restore_theme" value="1" formnovalidate><?php esc_html_e( 'Restore default theme', 'dovetail-studio' ); ?></button>
	</p>
</form>
