<?php
/**
 * Settings → Company: day, time and recipient of the weekly digest email.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{days: string[], day: string, time: string, recipient: string, to: string} $args
 */

defined( 'ABSPATH' ) || exit;

$labels = array(
	'monday'    => __( 'Monday', 'dovetail-studio' ),
	'tuesday'   => __( 'Tuesday', 'dovetail-studio' ),
	'wednesday' => __( 'Wednesday', 'dovetail-studio' ),
	'thursday'  => __( 'Thursday', 'dovetail-studio' ),
	'friday'    => __( 'Friday', 'dovetail-studio' ),
	'saturday'  => __( 'Saturday', 'dovetail-studio' ),
	'sunday'    => __( 'Sunday', 'dovetail-studio' ),
);
?>
<h2><?php esc_html_e( 'Weekly digest', 'dovetail-studio' ); ?></h2>
<p><?php esc_html_e( 'Schedule for the weekly summary email of enquiries and the assistant funnel. Default: Monday 08:00 in the site time zone (Settings > General), sent to the enquiry email above.', 'dovetail-studio' ); ?></p>
<form method="post">
	<?php wp_nonce_field( 'dt_va_digest' ); ?>
	<input type="hidden" name="dt_va_digest" value="1">
	<table class="form-table">
		<tr>
			<th><label for="dt-va-digest-day"><?php esc_html_e( 'Day', 'dovetail-studio' ); ?></label></th>
			<td>
				<select id="dt-va-digest-day" name="digest_day">
					<?php foreach ( $args['days'] as $day ) : ?>
						<option value="<?php echo esc_attr( $day ); ?>" <?php selected( $args['day'], $day ); ?>><?php echo esc_html( $labels[ $day ] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="dt-va-digest-time"><?php esc_html_e( 'Time', 'dovetail-studio' ); ?></label></th>
			<td>
				<input type="time" id="dt-va-digest-time" name="digest_time" value="<?php echo esc_attr( $args['time'] ); ?>">
				<p class="description"><?php esc_html_e( 'Site time zone (Settings > General), 24-hour.', 'dovetail-studio' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="dt-va-digest-recipient"><?php esc_html_e( 'Recipient (optional)', 'dovetail-studio' ); ?></label></th>
			<td>
				<input class="regular-text" id="dt-va-digest-recipient" name="digest_recipient" type="email" value="<?php echo esc_attr( $args['recipient'] ); ?>" placeholder="<?php echo esc_attr( $args['to'] ); ?>">
				<p class="description"><?php esc_html_e( 'Leave blank to send the digest to the enquiry email above.', 'dovetail-studio' ); ?></p>
			</td>
		</tr>
	</table>
	<p><button class="button button-primary"><?php esc_html_e( 'Save digest schedule', 'dovetail-studio' ); ?></button></p>
</form>
