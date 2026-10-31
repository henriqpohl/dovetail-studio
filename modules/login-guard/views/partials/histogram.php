<?php
/**
 * Inline SVG bar chart of the reCAPTCHA scores, with a text alternative.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{scores: int[]} $args Eleven counts, for the scores 0.0 to 1.0.
 */

defined( 'ABSPATH' ) || exit;

$scores = $args['scores'];
$max    = max( 1, max( $scores ) );
$width  = 420;
$height = 160;
$left   = 40;
$bottom = 130;
$step   = ( $width - $left - 10 ) / 11;
$text   = array();

foreach ( $scores as $i => $count ) {
	$text[] = number_format( $i / 10, 1 ) . ': ' . $count;
}

/* translators: %s: list of "score: requests" pairs. */
$summary = sprintf( __( 'Requests per reCAPTCHA score: %s', 'dovetail-studio' ), implode( ', ', $text ) );
?>
<figure class="dovetail-chart">
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" width="<?php echo (int) $width; ?>" height="<?php echo (int) $height; ?>" role="img" aria-label="<?php echo esc_attr( $summary ); ?>">
		<line x1="<?php echo (int) $left; ?>" y1="<?php echo (int) $bottom; ?>" x2="<?php echo (int) ( $width - 10 ); ?>" y2="<?php echo (int) $bottom; ?>" stroke="currentColor" opacity="0.4"/>
		<text x="<?php echo (int) ( $left - 6 ); ?>" y="<?php echo (int) ( $bottom - 110 + 4 ); ?>" text-anchor="end" font-size="10"><?php echo (int) $max; ?></text>
		<text x="<?php echo (int) ( $left - 6 ); ?>" y="<?php echo (int) ( $bottom + 4 ); ?>" text-anchor="end" font-size="10">0</text>
		<?php foreach ( $scores as $i => $count ) : ?>
			<?php $bar = (int) round( ( $count / $max ) * 110 ); ?>
			<rect x="<?php echo esc_attr( (string) round( $left + $i * $step + 3, 1 ) ); ?>" y="<?php echo (int) ( $bottom - $bar ); ?>" width="<?php echo esc_attr( (string) round( $step - 6, 1 ) ); ?>" height="<?php echo (int) $bar; ?>" fill="currentColor" opacity="0.45"><title><?php echo esc_html( number_format( $i / 10, 1 ) . ': ' . $count ); ?></title></rect>
		<?php endforeach; ?>
		<?php foreach ( $scores as $i => $count ) : ?>
			<text x="<?php echo esc_attr( (string) round( $left + $i * $step + $step / 2, 1 ) ); ?>" y="<?php echo (int) ( $bottom + 16 ); ?>" text-anchor="middle" font-size="10"><?php echo esc_html( number_format( $i / 10, 1 ) ); ?></text>
		<?php endforeach; ?>
	</svg>
	<figcaption class="screen-reader-text"><?php echo esc_html( $summary ); ?></figcaption>
</figure>
