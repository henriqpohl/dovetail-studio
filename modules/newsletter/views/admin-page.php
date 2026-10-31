<?php
/**
 * Dovetail Studio → Newsletter: integration, Mailchimp and Contact Form 7 settings.
 *
 * @package DovetailStudio\Newsletter
 *
 * @var array{version: string, settings: array<string,mixed>, has_cf7: bool, has_cf7_shortcode: bool} $args
 */

use DovetailStudio\Core\AdminScreens;

defined( 'ABSPATH' ) || exit;

$settings = $args['settings'];
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Dovetail Newsletter', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Configure how Contact Form 7 and Mailchimp handle newsletter subscriptions.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<?php if ( ! $args['has_cf7'] ) : ?>
		<div class="notice dovetail-notice inline notice-warning">
			<p><strong><?php esc_html_e( 'Dovetail Newsletter requires Contact Form 7.', 'dovetail-studio' ); ?></strong> <?php esc_html_e( 'Install and activate Contact Form 7 to render and process newsletter forms. The Newsletter module can remain enabled, but submissions cannot work until Contact Form 7 is active.', 'dovetail-studio' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	// This page is not under Settings, so WordPress does not print validation errors or the saved notice by itself.
	// options.php adds "Settings saved." itself when no validation error was raised.
	AdminScreens::settings_errors();
	?>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Newsletter settings sections', 'dovetail-studio' ); ?>">
		<a href="#dovetail-newsletter-integration"><?php esc_html_e( 'Integration', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-mailchimp"><?php esc_html_e( 'Mailchimp', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-cf7"><?php esc_html_e( 'Contact Form 7', 'dovetail-studio' ); ?></a>
	</nav>

	<form action="options.php" method="post">
		<?php settings_fields( 'dovetail_newsletter' ); ?>

		<section class="dovetail-card" id="dovetail-newsletter-integration">
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Integration', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose how newsletter submissions should be processed, then confirm the selection.', 'dovetail-studio' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dovetail-mode"><?php esc_html_e( 'Mode', 'dovetail-studio' ); ?></label></th>
					<td>
						<select id="dovetail-mode" name="dovetail_newsletter_settings[mode]" required>
						<?php
						foreach ( array(
							''          => __( 'Choose an integration', 'dovetail-studio' ),
							'mailchimp' => 'Mailchimp',
							'cf7'       => 'Contact Form 7',
							'both'      => __( 'Both', 'dovetail-studio' ),
						) as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['mode'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
						</select>
						<button type="button" class="button" id="dovetail-confirm"><?php esc_html_e( 'Confirm integration', 'dovetail-studio' ); ?></button>
					</td>
				</tr>
			</table>
		</section>

		<fieldset class="dovetail-card" id="dovetail-mailchimp" hidden disabled>
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Mailchimp', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Connect the site to the Mailchimp audience used for newsletter subscriptions.', 'dovetail-studio' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dovetail-newsletter-api-key"><?php esc_html_e( 'API key', 'dovetail-studio' ); ?></label></th>
					<td>
						<input id="dovetail-newsletter-api-key" class="regular-text" type="password" name="dovetail_newsletter_settings[api_key]" value="" autocomplete="new-password">
						<p class="description"><?php echo ! empty( $settings['api_key'] ) ? esc_html__( 'A key is saved. Leave blank to keep it.', 'dovetail-studio' ) : esc_html__( 'Enter the Mailchimp API key.', 'dovetail-studio' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-newsletter-audience-id"><?php esc_html_e( 'Audience ID', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-newsletter-audience-id" class="regular-text" type="text" name="dovetail_newsletter_settings[audience_id]" value="<?php echo esc_attr( $settings['audience_id'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Confirmation', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="dovetail_newsletter_settings[double_opt_in]" value="1" <?php checked( ! empty( $settings['double_opt_in'] ) ); ?>> <?php esc_html_e( 'Require email confirmation (recommended)', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-newsletter-merge-fields"><?php esc_html_e( 'Merge fields', 'dovetail-studio' ); ?></label></th>
					<td><textarea id="dovetail-newsletter-merge-fields" class="regular-text" rows="3" name="dovetail_newsletter_settings[merge_fields]" placeholder="FNAME=your-name"><?php echo esc_textarea( $settings['merge_fields'] ); ?></textarea><p class="description"><?php esc_html_e( 'Optional. One per line: Mailchimp merge tag = Contact Form 7 field name, for example FNAME=your-name or PHONE=your-phone.', 'dovetail-studio' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-newsletter-tags"><?php esc_html_e( 'Tags', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-newsletter-tags" class="regular-text" type="text" name="dovetail_newsletter_settings[tags]" value="<?php echo esc_attr( $settings['tags'] ); ?>"><p class="description"><?php esc_html_e( 'Optional. Comma-separated Mailchimp tags added to every new subscriber, for example Website, Footer form.', 'dovetail-studio' ); ?></p></td>
				</tr>
			</table>
		</fieldset>

		<fieldset class="dovetail-card" id="dovetail-cf7" hidden disabled>
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Contact Form 7', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Select the Contact Form 7 form used to collect newsletter subscriptions.', 'dovetail-studio' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dovetail-cf7-shortcode"><?php esc_html_e( 'CF7 shortcode', 'dovetail-studio' ); ?></label></th>
					<td>
						<input id="dovetail-cf7-shortcode" class="large-text" type="text" name="dovetail_newsletter_settings[cf7_shortcode]" value="<?php echo esc_attr( $settings['cf7_shortcode'] ); ?>" placeholder='[contact-form-7 id="123" title="Newsletter"]' aria-describedby="dovetail-cf7-help">
						<p class="description" id="dovetail-cf7-help"><?php esc_html_e( 'Paste the shortcode copied from Contact Form 7. This form is used for all three integration modes.', 'dovetail-studio' ); ?></p>
						<p><code>&lt;?php echo dovetail_newsletter_render(); ?&gt;</code></p>
						<p class="description"><?php esc_html_e( 'CF7 sends the normal form email. Mailchimp subscribes without sending the CF7 email. Both performs both actions, and the form email is still sent if the subscription fails. Mailchimp modes require exactly one CF7 email field.', 'dovetail-studio' ); ?></p>
						<?php if ( ! $args['has_cf7_shortcode'] ) : ?>
							<p class="description"><strong><?php esc_html_e( 'Contact Form 7 must be active to display the newsletter form.', 'dovetail-studio' ); ?></strong></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</fieldset>

		<div class="dovetail-save-bar">
			<?php
			submit_button(
				__( 'Save Newsletter Settings', 'dovetail-studio' ),
				'primary',
				'submit',
				false
			);
			?>
		</div>
	</form>
</div>
