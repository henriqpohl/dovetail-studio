<?php
/**
 * Dovetail Studio → Dashboard: a card per installed module.
 *
 * @package DovetailStudio\Core
 *
 * @var array{version: string, modules: array<int,array{name: string, description: string, version: string, enabled: bool, open_url: string}>} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Dovetail Studio', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Central WordPress toolkit for Dovetail Consultancy modules.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Dovetail Studio sections', 'dovetail-studio' ); ?>">
		<a class="is-active" aria-current="page" href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio' ) ); ?>"><?php esc_html_e( 'Dashboard', 'dovetail-studio' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=dovetail-studio-modules' ) ); ?>"><?php esc_html_e( 'Modules', 'dovetail-studio' ); ?></a>
	</nav>

	<div class="dovetail-module-grid">
		<?php foreach ( $args['modules'] as $module ) : ?>
			<section class="dovetail-card dovetail-module-card">
				<div class="dovetail-card-header">
					<h2><?php echo esc_html( $module['name'] ); ?></h2>
					<p class="description"><?php echo esc_html( $module['description'] ); ?></p>
				</div>
				<div class="dovetail-card-body">
					<div class="dovetail-module-meta">
						<span class="dovetail-badge">v<?php echo esc_html( $module['version'] ); ?></span>
						<span class="dovetail-badge <?php echo $module['enabled'] ? 'is-enabled' : ''; ?>">
							<?php
							echo $module['enabled']
								? esc_html__( 'Enabled', 'dovetail-studio' )
								: esc_html__( 'Disabled', 'dovetail-studio' );
							?>
						</span>
					</div>
					<?php if ( '' !== $module['open_url'] ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( $module['open_url'] ); ?>"><?php esc_html_e( 'Open module', 'dovetail-studio' ); ?></a></p>
					<?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
</div>

