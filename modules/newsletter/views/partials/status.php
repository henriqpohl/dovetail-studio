<?php
/**
 * Front-end status message shown instead of the newsletter form when it is not configured.
 *
 * @package DovetailStudio\Newsletter
 *
 * @var array{message: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<p role="status"><?php echo esc_html( $args['message'] ); ?></p>
