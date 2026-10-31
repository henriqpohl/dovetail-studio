<?php
/**
 * Enquiries → Settings: page header, section tabs and the panel of the current tab.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{version: string, tabs: array<string,array{title: string, url: string}>, current: string, panel: callable} $args
 */

defined( 'ABSPATH' ) || exit;

$current = $args['current'];
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Virtual Assistant', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Configure AI providers, company data, knowledge, conversation flow and appearance.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Virtual Assistant settings', 'dovetail-studio' ); ?>">
		<?php foreach ( $args['tabs'] as $slug => $item ) : ?>
			<a class="<?php echo $current === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $current === $slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['title'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'ai-keys' === $current ) : ?>
		<?php call_user_func( $args['panel'] ); ?>
	<?php else : ?>
		<section class="dovetail-card">
			<div class="dovetail-card-body">
				<?php call_user_func( $args['panel'] ); ?>
			</div>
		</section>
	<?php endif; ?>
</div>
