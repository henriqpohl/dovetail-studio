<?php
/**
 * Login Guard → Alerts tab: alert emails, summary and the activity log.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{dir: string, option: string, settings: array<string,mixed>, caps: array<int,string>, every: array<int,string>, keep: array<int,string>} $args
 */

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$settings = $args['settings'];
$name     = $args['option'];
$caps     = $args['caps'];
$every    = $args['every'];
$keep     = $args['keep'];

/**
 * Print a checkbox row of the settings form.
 *
 * @param string $key         Setting key.
 * @param string $label       Label.
 * @param string $description Optional help text.
 * @return void
 */
$checkbox_row = static function ( string $key, string $label, string $description = '' ) use ( $args ): void {
	View::render(
		$args['dir'],
		'partials/checkbox-row',
		array(
			'name'        => $args['option'] . '[' . $key . ']',
			'checked'     => ! empty( $args['settings'][ $key ] ),
			'label'       => $label,
			'description' => $description,
		)
	);
};

/**
 * Print a select row of the settings form.
 *
 * @param string                   $key     Setting key.
 * @param string                   $label   Label.
 * @param array<int|string,string> $choices Value => label.
 * @return void
 */
$select_row = static function ( string $key, string $label, array $choices ) use ( $args ): void {
	View::render(
		$args['dir'],
		'partials/select-row',
		array(
			'id'      => 'dovetail-lg-' . str_replace( '_', '-', $key ),
			'name'    => $args['option'] . '[' . $key . ']',
			'value'   => (string) $args['settings'][ $key ],
			'label'   => $label,
			'choices' => $choices,
		)
	);
};
?>
<form action="options.php" method="post">
	<?php settings_fields( 'dovetail_login_guard' ); ?>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_section]" value="alerts">
	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Email Alerts', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Plain-text emails with the username, IP address, hostname and, behind Cloudflare, the country.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dovetail-lg-recipients"><?php esc_html_e( 'Send alerts to', 'dovetail-studio' ); ?></label></th>
				<td>
					<textarea id="dovetail-lg-recipients" class="regular-text" rows="3" name="<?php echo esc_attr( $name ); ?>[alert_recipients]" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"><?php echo esc_textarea( implode( "\n", (array) $settings['alert_recipients'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One email address per line. Empty: the site\'s administration email address.', 'dovetail-studio' ); ?></p>
				</td>
			</tr>
			<?php
			$checkbox_row( 'alert_admin_login', __( 'Alert me when someone with administrator access signs in', 'dovetail-studio' ) );
			$select_row(
				'alert_admin_login_scope',
				__( 'Which administrator sign-ins', 'dovetail-studio' ),
				array(
					'every'  => __( 'Every sign-in', 'dovetail-studio' ),
					'new_ip' => __( 'Only from an IP address not seen before', 'dovetail-studio' ),
				)
			);
			$checkbox_row( 'alert_blocked', __( 'Alert me when an IP address is blocked', 'dovetail-studio' ), __( 'Blocks made by the rules (usernames, blank User-Agent POSTs); manual blocks send nothing.', 'dovetail-studio' ) );
			$checkbox_row( 'alert_lockout', __( 'Alert me when someone is locked out from login', 'dovetail-studio' ) );
			$select_row( 'alert_max_per_hour', __( 'Maximum alerts per hour', 'dovetail-studio' ), $caps );
			?>
		</table>
	</section>

	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Summary', 'dovetail-studio' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Totals of sign-ins, failures, lockouts and blocks, and the most active IP addresses, built from the activity log.', 'dovetail-studio' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php
			$checkbox_row( 'summary_enabled', __( 'Email a summary', 'dovetail-studio' ) );
			$select_row( 'summary_days', __( 'How often', 'dovetail-studio' ), $every );
			$checkbox_row( 'summary_skip_empty', __( 'Skip the summary when nothing happened', 'dovetail-studio' ), __( 'No failed sign-ins, lockouts, blocks, refused two-factor codes or failed captchas in the period.', 'dovetail-studio' ) );
			?>
		</table>
	</section>

	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Activity Log', 'dovetail-studio' ); ?></h2>
		</div>
		<table class="form-table" role="presentation">
			<?php
			$checkbox_row( 'activity_enabled', __( 'Record sign-in activity', 'dovetail-studio' ), __( 'Needed for the Activity tab and the summary. Stores usernames, IP addresses and browsers of sign-in attempts.', 'dovetail-studio' ) );
			$select_row( 'activity_retention_days', __( 'Keep entries for', 'dovetail-studio' ), $keep );
			?>
		</table>
	</section>
	<div class="dovetail-save-bar"><?php submit_button( __( 'Save Alert Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?></div>
</form>
