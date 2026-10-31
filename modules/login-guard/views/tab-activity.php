<?php
/**
 * Login Guard → Activity tab: the filterable activity log.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{enabled: bool, days: int, page: string, search: string, event: string, labels: array<string,string>, result: array{rows: array<int,array<string,mixed>>, total: int}, date_format: string, paged: int, pages: int, prev_url: string, next_url: string} $args
 */

defined( 'ABSPATH' ) || exit;

$days   = $args['days'];
$filter = $args['search'];
$event  = $args['event'];
$labels = $args['labels'];
$result = $args['result'];
?>
<section class="dovetail-card">
	<div class="dovetail-card-header">
		<h2><?php esc_html_e( 'Activity', 'dovetail-studio' ); ?></h2>
		<p class="description">
		<?php
		echo empty( $args['enabled'] )
			? esc_html__( 'The activity log is switched off in the Alerts tab; nothing new is recorded.', 'dovetail-studio' )
			/* translators: %d: number of days. */
			: esc_html( sprintf( _n( 'Sign-ins, failures, lockouts, blocks, two-factor and captcha results of the last %d day.', 'Sign-ins, failures, lockouts, blocks, two-factor and captcha results of the last %d days.', $days, 'dovetail-studio' ), $days ) );
		?>
		</p>
	</div>
	<div class="dovetail-card-body">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $args['page'] ); ?>">
			<input type="hidden" name="tab" value="activity">
			<label class="screen-reader-text" for="dovetail-lg-activity-search"><?php esc_html_e( 'Filter by IP, username or detail', 'dovetail-studio' ); ?></label>
			<input id="dovetail-lg-activity-search" type="search" name="s" value="<?php echo esc_attr( $filter ); ?>" placeholder="<?php esc_attr_e( 'Filter by IP, username or detail', 'dovetail-studio' ); ?>">
			<label class="screen-reader-text" for="dovetail-lg-event"><?php esc_html_e( 'Event', 'dovetail-studio' ); ?></label>
			<select id="dovetail-lg-event" name="event">
				<option value=""><?php esc_html_e( 'All events', 'dovetail-studio' ); ?></option>
				<?php foreach ( $labels as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $event, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'dovetail-studio' ), 'secondary', '', false ); ?>
		</form>

		<div class="dovetail-table-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'dovetail-studio' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Event', 'dovetail-studio' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Username', 'dovetail-studio' ); ?></th>
						<th scope="col"><?php esc_html_e( 'IP address', 'dovetail-studio' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'dovetail-studio' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Browser', 'dovetail-studio' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $result['rows'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No activity recorded yet.', 'dovetail-studio' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $result['rows'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( $args['date_format'], (int) $row['created_at'] ) ); ?></td>
							<td><?php echo esc_html( $labels[ $row['event'] ] ?? $row['event'] ); ?></td>
							<td><?php echo '' !== (string) $row['username'] ? esc_html( $row['username'] ) : '&mdash;'; ?></td>
							<td><code><?php echo esc_html( $row['ip'] ); ?></code></td>
							<td><?php echo esc_html( $row['detail'] ); ?></td>
							<td><span class="description"><?php echo esc_html( mb_substr( (string) $row['user_agent'], 0, 80 ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $args['pages'] > 1 ) : ?>
			<p>
			<?php
			if ( '' !== $args['prev_url'] ) {
				echo '<a class="button" href="' . esc_url( $args['prev_url'] ) . '">' . esc_html__( 'Previous', 'dovetail-studio' ) . '</a> ';
			}

			/* translators: 1: current page, 2: number of pages, 3: number of rows. */
			echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d entries)', 'dovetail-studio' ), $args['paged'], $args['pages'], $result['total'] ) );

			if ( '' !== $args['next_url'] ) {
				echo ' <a class="button" href="' . esc_url( $args['next_url'] ) . '">' . esc_html__( 'Next', 'dovetail-studio' ) . '</a>';
			}
			?>
			</p>
		<?php endif; ?>
	</div>
</section>
