<?php
/**
 * Settings → AI Keys: provider order, keys, models, limits, the daily check and the last diagnostics.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{notice: string, config: array<string,mixed>, providers: array<int,array<string,mixed>>, daily: array<string,mixed>, diagnostics: string|false} $args
 */

use DovetailStudio\Core\AdminScreens;

defined( 'ABSPATH' ) || exit;

$c              = $args['config'];
$daily          = $args['daily'];
$provider_names = array(
	'none'      => __( 'None', 'dovetail-studio' ),
	'openai'    => 'OpenAI',
	'anthropic' => __( 'Anthropic', 'dovetail-studio' ),
);
?>
<section class="dovetail-card">
	<div class="dovetail-card-header">
		<h2><?php esc_html_e( 'AI Keys', 'dovetail-studio' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Configure a key and model for each provider. Unconfigured providers are skipped. Offline answers remain available.', 'dovetail-studio' ); ?></p>
	</div>
	<div class="dovetail-card-body">
		<?php
		if ( $args['notice'] ) {
			AdminScreens::notice( 'info', esc_html( $args['notice'] ) );
		}
		?>

		<form method="post">
			<?php wp_nonce_field( 'dt_va_settings' ); ?>
			<input type="hidden" name="dt_va_settings" value="1">
			<table class="form-table">
				<?php
				foreach ( array(
					'primary'   => __( 'Primary provider', 'dovetail-studio' ),
					'secondary' => __( 'Secondary provider', 'dovetail-studio' ),
				) as $field => $field_label ) :
					?>
					<tr>
						<th><?php echo esc_html( $field_label ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $field ); ?>">
								<?php foreach ( 'primary' === $field ? array( 'openai', 'anthropic' ) : array( 'none', 'openai', 'anthropic' ) as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $c[ $field ], $value ); ?>><?php echo esc_html( $provider_names[ $value ] ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( 'secondary' === $field ) : ?>
								<p class="description"><?php esc_html_e( 'Asked only when the primary provider fails.', 'dovetail-studio' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php foreach ( $args['providers'] as $provider ) : ?>
					<?php $p = $provider['id']; ?>
					<tr>
						<th>
							<?php
							/* translators: %s: provider name. */
							echo esc_html( sprintf( __( '%s API key', 'dovetail-studio' ), $provider['label'] ) );
							?>
						</th>
						<td>
							<p>
								<?php echo $provider['configured'] ? esc_html__( 'Configured', 'dovetail-studio' ) : esc_html__( 'Not configured', 'dovetail-studio' ); ?>
								<?php echo $provider['external'] ? esc_html__( 'externally (wp-config.php or environment).', 'dovetail-studio' ) : esc_html__( 'in admin settings.', 'dovetail-studio' ); ?>
							</p>

							<?php if ( $provider['external'] ) : ?>
								<?php /* translators: %s: provider name. */ ?>
								<input type="text" class="regular-text code" aria-label="<?php echo esc_attr( sprintf( __( '%s saved key prefix', 'dovetail-studio' ), $provider['label'] ) ); ?>" value="<?php echo esc_attr( $provider['preview'] ); ?>" disabled>
								<p class="description"><?php esc_html_e( 'Edit the key in wp-config.php or the environment. Only its prefix is displayed here.', 'dovetail-studio' ); ?></p>
							<?php else : ?>
								<?php /* translators: %s: provider name. */ ?>
								<input type="password" class="regular-text code" autocomplete="new-password" aria-label="<?php echo esc_attr( sprintf( __( '%s API key', 'dovetail-studio' ), $provider['label'] ) ); ?>" name="<?php echo esc_attr( $p ); ?>_key" value="" placeholder="<?php echo esc_attr( $provider['preview'] ); ?>">
								<label><input type="checkbox" name="<?php echo esc_attr( $p ); ?>_clear" value="1"> <?php esc_html_e( 'Clear saved key', 'dovetail-studio' ); ?></label>
								<p class="description"><?php esc_html_e( 'Only the saved key prefix is shown. Leave blank to keep it; paste a new key to replace it. Saved in the WordPress database. Prefer environment or wp-config.php.', 'dovetail-studio' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>
							<?php
							/* translators: %s: provider name. */
							echo esc_html( sprintf( __( '%s model', 'dovetail-studio' ), $provider['label'] ) );
							?>
						</th>
						<td>
							<input class="regular-text" name="<?php echo esc_attr( $p ); ?>_model" value="<?php echo esc_attr( $provider['model'] ); ?>"<?php echo null !== $provider['external_model'] ? ' readonly' : ''; ?>>
							<p class="description"><?php esc_html_e( 'Enter a model ID, not an API key. Blank disables this provider.', 'dovetail-studio' ); ?></p>
							<p class="description"><strong><?php esc_html_e( 'Model IDs (reference checked 28 Sep 2026):', 'dovetail-studio' ); ?></strong></p>
							<ul>
								<?php if ( null !== $provider['external_model'] ) : ?>
									<li>
										<strong><?php esc_html_e( 'Configured via', 'dovetail-studio' ); ?> <?php echo esc_html( $provider['external_model']['source'] ); ?>.</strong>
										<?php esc_html_e( 'Edit DT_VA_', 'dovetail-studio' ); ?><?php echo esc_html( strtoupper( $p ) ); ?><?php esc_html_e( '_MODEL there.', 'dovetail-studio' ); ?>
										<?php
										if ( '' === $provider['model'] ) {
											esc_html_e( 'Invalid external model ID: provider disabled.', 'dovetail-studio' );
										}
										?>
									</li>
								<?php endif; ?>

								<?php foreach ( $provider['models'] as $model_name => $model_id ) : ?>
									<li><?php echo esc_html( $model_name ); ?>: <code><?php echo esc_html( $model_id ); ?></code></li>
								<?php endforeach; ?>
							</ul>
							<p class="description"><a href="<?php echo esc_url( $provider['catalog'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Official model catalog', 'dovetail-studio' ); ?></a><?php esc_html_e( '. Availability depends on your API account. Reasoning models may need a larger output-token budget and more time. Use Save and test providers to verify your choice.', 'dovetail-studio' ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php
				foreach ( array(
					'timeout'    => array( __( 'Timeout per provider (seconds)', 'dovetail-studio' ), 3, 12 ),
					'max_tokens' => array( __( 'Maximum output tokens', 'dovetail-studio' ), 64, 4096 ),
				) as $field => $meta ) :
					?>
					<tr>
						<th><?php echo esc_html( $meta[0] ); ?></th>
						<td><input type="number" name="<?php echo esc_attr( $field ); ?>" min="<?php echo esc_attr( $meta[1] ); ?>" max="<?php echo esc_attr( $meta[2] ); ?>" value="<?php echo esc_attr( $c[ $field ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>

				<tr>
					<th><?php esc_html_e( 'Last daily check', 'dovetail-studio' ); ?></th>
					<td>
						<?php if ( ! empty( $daily['checked'] ) ) : ?>
							<?php
							$daily_status = 'ok' === ( $daily['status'] ?? '' )
								? __( 'working', 'dovetail-studio' )
								: ( 'nokey' === ( $daily['status'] ?? '' ) ? __( 'not configured', 'dovetail-studio' ) : __( 'not answering', 'dovetail-studio' ) );
							echo esc_html( wp_date( 'j M Y H:i', (int) $daily['checked'] ) . ': ' . $daily_status );
							?>

							<?php if ( ! empty( $daily['detail'] ) ) : ?>
								<p class="description"><?php echo esc_html( $daily['detail'] ); ?></p>
							<?php endif; ?>
						<?php else : ?>
							<?php esc_html_e( 'Not yet run.', 'dovetail-studio' ); ?>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Time shown in the WordPress site timezone. This is the automatic daily check, not the manual provider test.', 'dovetail-studio' ); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button class="button button-primary"><?php esc_html_e( 'Save', 'dovetail-studio' ); ?></button>
				<button class="button" name="dt_va_test" value="1"><?php esc_html_e( 'Save and test providers', 'dovetail-studio' ); ?></button>
			</p>
		</form>

		<h2><?php esc_html_e( 'Last provider diagnostics', 'dovetail-studio' ); ?></h2>
		<pre><?php echo esc_html( (string) $args['diagnostics'] ); ?></pre>
	</div>
</section>
