<?php
/**
 * Dovetail Studio → Modules: upload form and the installed modules table.
 *
 * @package DovetailStudio\Core
 *
 * @var array{version: string, notice: array{0: string, 1: string}|null, can_upload: bool, modules_dir: string, modules: array<int,array{slug: string, name: string, description: string, version: string, enabled: bool, uploaded: bool, open_url: string, remove_url: string}>} $args
 */

use DovetailStudio\Core\AdminScreens;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Dovetail Studio', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Enable, disable, install, update or remove Dovetail Studio modules.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Dovetail Studio sections', 'dovetail-studio' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio' ) ); ?>"><?php esc_html_e( 'Dashboard', 'dovetail-studio' ); ?></a>
		<a class="is-active" aria-current="page" href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio-modules' ) ); ?>"><?php esc_html_e( 'Modules', 'dovetail-studio' ); ?></a>
	</nav>

	<?php
	if ( $args['notice'] ) {
		AdminScreens::notice( $args['notice'][0], esc_html( $args['notice'][1] ), true );
	}
	?>
	<?php AdminScreens::settings_errors(); ?>

	<?php if ( $args['can_upload'] ) : ?>
	<section class="dovetail-card">
		<div class="dovetail-card-header">
			<h2><?php esc_html_e( 'Upload Module', 'dovetail-studio' ); ?></h2>
			<p class="description">
			<?php
			/* translators: %s: modules directory, e.g. wp-content/dovetail-modules. */
			echo wp_kses_post( sprintf( __( 'Install a new Dovetail Studio module or upload a newer version of an existing module. Packages must be ZIP files containing a valid <code>module.php</code>. Uploaded modules are stored in <code>%s</code>, so plugin updates keep them.', 'dovetail-studio' ), esc_html( $args['modules_dir'] ) ) );
			?>
			</p>
		</div>
		<div class="dovetail-card-body">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="dovetail_studio_upload_module">
				<?php wp_nonce_field( 'dovetail_studio_upload_module' ); ?>
				<input type="file" name="dovetail_module_zip" accept=".zip,application/zip" required>
				<?php
				submit_button(
					__( 'Upload Module', 'dovetail-studio' ),
					'secondary',
					'submit',
					false
				);
				?>
			</form>
		</div>
	</section>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'dovetail_studio_modules_group' ); ?>

		<section class="dovetail-card">
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Installed Modules', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Disabled modules remain installed but are not loaded. A module must be disabled and saved before it can be removed.', 'dovetail-studio' ); ?></p>
			</div>
			<div class="dovetail-card-body">
				<div class="dovetail-table-scroll">
					<table class="widefat striped dovetail-modules-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Enabled', 'dovetail-studio' ); ?></th>
								<th><?php esc_html_e( 'Module', 'dovetail-studio' ); ?></th>
								<th><?php esc_html_e( 'Version', 'dovetail-studio' ); ?></th>
								<th><?php esc_html_e( 'Source', 'dovetail-studio' ); ?></th>
								<th><?php esc_html_e( 'Description', 'dovetail-studio' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'dovetail-studio' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if ( empty( $args['modules'] ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No modules installed.', 'dovetail-studio' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $args['modules'] as $module ) : ?>
								<tr>
									<td>
										<input type="hidden" name="dovetail_studio_modules[<?php echo esc_attr( $module['slug'] ); ?>]" value="0">
										<label>
											<input
												type="checkbox"
												name="dovetail_studio_modules[<?php echo esc_attr( $module['slug'] ); ?>]"
												value="1"
												<?php checked( $module['enabled'] ); ?>
											>
											<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: module name. */ __( 'Enable %s', 'dovetail-studio' ), $module['name'] ) ); ?></span>
										</label>
									</td>
									<td><strong><?php echo esc_html( $module['name'] ); ?></strong></td>
									<td><?php echo esc_html( $module['version'] ); ?></td>
									<td><?php echo $module['uploaded'] ? esc_html__( 'Uploaded', 'dovetail-studio' ) : esc_html__( 'Bundled', 'dovetail-studio' ); ?></td>
									<td><?php echo esc_html( $module['description'] ); ?></td>
									<td>
										<?php if ( $module['enabled'] ) : ?>
											<?php if ( '' !== $module['open_url'] ) : ?>
												<a class="button button-small" href="<?php echo esc_url( $module['open_url'] ); ?>"><?php esc_html_e( 'Open', 'dovetail-studio' ); ?></a>
											<?php endif; ?>
										<?php elseif ( '' !== $module['remove_url'] ) : ?>
											<a
												class="button button-small button-link-delete dovetail-remove-module"
												href="<?php echo esc_url( $module['remove_url'] ); ?>"
												data-dovetail-confirm="<?php echo esc_attr( sprintf( /* translators: %s: module name. */ __( 'Remove %s? The module files will be deleted, but its WordPress database settings will be preserved.', 'dovetail-studio' ), $module['name'] ) ); ?>"
											><?php esc_html_e( 'Remove Module', 'dovetail-studio' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
				<?php submit_button( __( 'Save Modules', 'dovetail-studio' ) ); ?>
			</div>
		</section>
	</form>
</div>

