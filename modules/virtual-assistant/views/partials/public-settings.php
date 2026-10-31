<?php
/**
 * Settings → Company: the public company details shown by the assistant, each with a restore button.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{rows: array<string,array{type: string, custom: bool, value: mixed, label: string, help: string, required: bool, missing: bool, closed: string}>, weekdays: array<int,string>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Public company details', 'dovetail-studio' ); ?></h2>
<p><?php esc_html_e( 'Edit a value and save to apply it. Optional blank values remain blank. Restore default resets only that field immediately; save other edits first. Google ratings are entered manually; no API key is needed.', 'dovetail-studio' ); ?></p>
<p><span class="dovetail-required" aria-hidden="true">*</span> <?php esc_html_e( 'Required: the assistant stays hidden on the site until these fields are filled in.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_public' ); ?>
	<input type="hidden" name="dt_va_public" value="1">
	<table class="form-table">
		<?php foreach ( $args['rows'] as $key => $row ) : ?>
			<?php
			$input_id = 'dt-va-public-' . $key;
			$value    = $row['value'];
			?>
			<tr id="<?php echo esc_attr( $input_id . '-row' ); ?>"<?php echo $row['missing'] ? ' class="dovetail-field-missing"' : ''; ?>>
				<th>
					<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $row['label'] ); ?><?php echo $row['required'] ? ' <span class="dovetail-required" aria-hidden="true">*</span><span class="screen-reader-text"> ' . esc_html__( '(required)', 'dovetail-studio' ) . '</span>' : ''; ?></label>
				</th>
				<td>
					<?php if ( 'hours' === $row['type'] ) : ?>
						<?php
						foreach ( array(
							'open'  => __( 'Opens', 'dovetail-studio' ),
							'close' => __( 'Closes', 'dovetail-studio' ),
						) as $part => $part_label ) :
							?>
							<label><?php echo esc_html( $part_label ); ?> <input id="<?php echo esc_attr( 'open' === $part ? $input_id : $input_id . '-close' ); ?>" aria-describedby="<?php echo esc_attr( $input_id . '-help' ); ?>" type="number" step="0.25" min="0" max="24" name="public[OPENING_HOURS][<?php echo esc_attr( $part ); ?>]" value="<?php echo esc_attr( $value[ $part ] ); ?>"></label>
						<?php endforeach; ?>
						<p>
							<?php foreach ( $args['weekdays'] as $day => $day_label ) : ?>
								<label><input type="checkbox" name="public[OPENING_HOURS][days][]" value="<?php echo esc_attr( $day ); ?>" <?php checked( in_array( $day, $value['days'], true ) ); ?>><?php echo esc_html( $day_label ); ?></label>
							<?php endforeach; ?>
						</p>
						<p>
							<label for="<?php echo esc_attr( $input_id . '-closed' ); ?>"><?php esc_html_e( 'Closed dates', 'dovetail-studio' ); ?></label><br>
							<textarea id="<?php echo esc_attr( $input_id . '-closed' ); ?>" name="public[OPENING_HOURS][closed]" rows="4" cols="40" placeholder="2026-12-24 to 2026-12-26"><?php echo esc_textarea( $row['closed'] ); ?></textarea><br>
							<span class="description"><?php esc_html_e( 'Holidays and other days the office is closed: one date (YYYY-MM-DD) or range (YYYY-MM-DD to YYYY-MM-DD) per line. Past dates are dropped when saving.', 'dovetail-studio' ); ?></span>
						</p>
					<?php elseif ( 'company_type' === $row['type'] ) : ?>
						<input id="<?php echo esc_attr( $input_id ); ?>" aria-describedby="<?php echo esc_attr( $input_id . '-help' ); ?>" class="regular-text" type="text" list="dt-va-company-type-list" maxlength="40" name="public[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
						<datalist id="dt-va-company-type-list"><option value="Office"><option value="Studio"><option value="Restaurant"><option value="Store"></datalist>
					<?php else : ?>
						<input id="<?php echo esc_attr( $input_id ); ?>" aria-describedby="<?php echo esc_attr( $input_id . '-help' ); ?>" class="regular-text" type="text" name="public[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
					<?php endif; ?>
					<button class="button" name="restore_default" value="<?php echo esc_attr( $key ); ?>" formnovalidate><?php esc_html_e( 'Restore default', 'dovetail-studio' ); ?></button>
					<p class="description" id="<?php echo esc_attr( $input_id . '-help' ); ?>"><?php echo esc_html( $row['help'] ); ?></p>
					<p class="description"><code><?php echo esc_html( $key ); ?></code> — <?php echo $row['custom'] ? esc_html__( 'Saved value', 'dovetail-studio' ) : esc_html__( 'Using HTML default', 'dovetail-studio' ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<button class="button button-primary"><?php esc_html_e( 'Save public details', 'dovetail-studio' ); ?></button>
</form>
