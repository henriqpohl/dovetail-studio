<?php
/**
 * A Studio notice: stays visible on Studio screens, where other notices are hidden.
 *
 * @package DovetailStudio\Core
 *
 * @var array{type: string, message: string, dismissible: bool} $args The message is HTML already escaped by the caller.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-<?php echo esc_attr( $args['type'] ); ?> dovetail-notice inline<?php echo $args['dismissible'] ? ' is-dismissible' : ''; ?>"><p><?php echo $args['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller (see AdminScreens::notice()). ?></p></div>
