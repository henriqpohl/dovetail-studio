<?php
/**
 * Opening hours: two periods per day of the week.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{days: array<int,array{label: string, name: string, row: array<string,mixed>}>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><?php esc_html_e( 'Opening hours', 'dovetail-studio' ); ?></th>
	<td>
		<div class="dovetail-hours">
			<?php foreach ( $args['days'] as $day ) : ?>
				<?php $row = $day['row']; ?>
				<div class="dovetail-hours-row">
					<label class="dovetail-day"><input type="checkbox" name="<?php echo esc_attr( $day['name'] . '[enabled]' ); ?>" value="1" <?php checked( 1, $row['enabled'] ); ?>> <?php echo esc_html( $day['label'] ); ?></label>
					<div class="dovetail-time-period">
						<span class="dovetail-hours-period"><?php esc_html_e( 'Period 1', 'dovetail-studio' ); ?></span>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Opens', 'dovetail-studio' ); ?></span><input type="time" name="<?php echo esc_attr( $day['name'] . '[opens]' ); ?>" value="<?php echo esc_attr( $row['opens'] ); ?>"></label>
						<span aria-hidden="true">–</span>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Closes', 'dovetail-studio' ); ?></span><input type="time" name="<?php echo esc_attr( $day['name'] . '[closes]' ); ?>" value="<?php echo esc_attr( $row['closes'] ); ?>"></label>
					</div>
					<div class="dovetail-time-period">
						<span class="dovetail-hours-period"><?php esc_html_e( 'Period 2', 'dovetail-studio' ); ?></span>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Second opening time', 'dovetail-studio' ); ?></span><input type="time" name="<?php echo esc_attr( $day['name'] . '[opens_2]' ); ?>" value="<?php echo esc_attr( $row['opens_2'] ); ?>"></label>
						<span aria-hidden="true">–</span>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Second closing time', 'dovetail-studio' ); ?></span><input type="time" name="<?php echo esc_attr( $day['name'] . '[closes_2]' ); ?>" value="<?php echo esc_attr( $row['closes_2'] ); ?>"></label>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</td>
</tr>
