<?php
/**
 * Admin notice: the assistant stays off the site until the required public details are filled in.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{fields: string, link: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice dovetail-notice inline notice-warning">
	<p><strong><?php esc_html_e( 'The AI Assistant is not shown on the site yet.', 'dovetail-studio' ); ?></strong> <?php echo esc_html( $args['fields'] ); ?> <a href="<?php echo esc_url( $args['link'] ); ?>"><?php esc_html_e( 'VA Enquiries > Settings > Company', 'dovetail-studio' ); ?></a></p>
</div>
