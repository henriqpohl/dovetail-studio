<?php
/**
 * Enquiry editor → Attachment box: the download link, or why there is none.
 *
 * @package DovetailStudio\VirtualAssistant
 *
 * @var array{url: string, name: string, removed: bool} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( '' === $args['url'] ) : ?>
	<p>
		<?php
		if ( $args['removed'] ) {
			esc_html_e( 'The file is no longer on the server.', 'dovetail-studio' );
		} else {
			esc_html_e( 'No file was attached.', 'dovetail-studio' );
		}
		?>
	</p>
<?php else : ?>
	<?php /* translators: %s: file name. */ ?>
	<p><a class="button" href="<?php echo esc_url( $args['url'] ); ?>"><?php echo esc_html( sprintf( __( 'Download %s', 'dovetail-studio' ), $args['name'] ) ); ?></a></p>
<?php endif; ?>
