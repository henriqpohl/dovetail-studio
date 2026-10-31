<?php
/**
 * Dovetail Studio → Login Guard: page header, section navigation, notices and the current tab.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{dir: string, version: string, tabs: array<string,array{label: string, url: string}>, tab: string, disabled: bool, settings_errors: bool, notice: array{0: string, 1: string}|null, tab_args: array<string,mixed>} $args
 */

use DovetailStudio\Core\AdminScreens;
use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Login Guard', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Brute force protection, two-factor authentication, captcha, alerts and the list of blocked IP addresses.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Login Guard sections', 'dovetail-studio' ); ?>">
		<?php foreach ( $args['tabs'] as $slug => $section ) : ?>
			<a href="<?php echo esc_url( $section['url'] ); ?>"<?php echo $slug === $args['tab'] ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php
	if ( $args['disabled'] ) {
		AdminScreens::notice( 'warning', esc_html__( 'Login Guard is switched off by DOVETAIL_LOGIN_GUARD_DISABLED in wp-config.php: nothing is locked out or blocked until that line is removed.', 'dovetail-studio' ) );
	}

	if ( $args['settings_errors'] ) {
		// This page is not under Settings, so WordPress does not print validation errors or the saved notice by itself.
		AdminScreens::settings_errors();
	} elseif ( $args['notice'] ) {
		AdminScreens::notice( $args['notice'][0], esc_html( $args['notice'][1] ), true );
	}

	View::render( $args['dir'], 'tab-' . $args['tab'], $args['tab_args'] );
	?>
</div>
