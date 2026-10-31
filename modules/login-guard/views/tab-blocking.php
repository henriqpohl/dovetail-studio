<?php
/**
 * Login Guard → Blocking tab: your IP address, the manual block form and the block list.
 *
 * @package DovetailStudio\LoginGuard
 *
 * @var array{my_ip: string, allowlisted: bool, mine: bool, durations: array<int,string>, page: string, export_url: string, search: string, source: string, result: array{rows: array<int,array<string,mixed>>, total: int}, types: array<string,string>, date_format: string, paged: int, pages: int, prev_url: string, next_url: string} $args
 */

defined( 'ABSPATH' ) || exit;

$my_ip        = $args['my_ip'];
$mine         = $args['mine'];
$filter       = $args['search'];
$source       = $args['source'];
$result       = $args['result'];
$types        = $args['types'];
$current_page = $args['paged'];
$page_count   = $args['pages'];
?>
<section class="dovetail-card">
	<div class="dovetail-card-header">
		<h2><?php esc_html_e( 'Your IP address', 'dovetail-studio' ); ?></h2>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: IP address. */
					__( 'This site sees you as %s.', 'dovetail-studio' ),
					'' !== $my_ip ? $my_ip : __( 'unknown', 'dovetail-studio' )
				)
			);
			echo ' ';

			if ( $args['allowlisted'] ) {
				esc_html_e( 'It is on the allowlist, so it is never locked out or blocked.', 'dovetail-studio' );
			} elseif ( $mine ) {
				esc_html_e( 'It is currently on the block list.', 'dovetail-studio' );
			} else {
				esc_html_e( 'Add it to the allowlist in Settings if it never changes (an office connection, for example).', 'dovetail-studio' );
			}
			?>
		</p>
	</div>
</section>

<?php // The form is the card itself, so the shared .dovetail-card > .form-table spacing applies. ?>
<form class="dovetail-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<div class="dovetail-card-header">
		<h2><?php esc_html_e( 'Block an IP address', 'dovetail-studio' ); ?></h2>
		<p class="description"><?php esc_html_e( 'A blocked address cannot open any page of the site. Blocks made here are manual; addresses locked out or blocked by the rules in Settings are automatic.', 'dovetail-studio' ); ?></p>
	</div>
	<input type="hidden" name="action" value="dovetail_login_guard_block">
	<?php wp_nonce_field( 'dovetail_login_guard_block' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="dovetail-lg-ip"><?php esc_html_e( 'IP address', 'dovetail-studio' ); ?></label></th>
			<td><input id="dovetail-lg-ip" class="regular-text" type="text" name="ip" required placeholder="203.0.113.10"></td>
		</tr>
		<tr>
			<th scope="row"><label for="dovetail-lg-reason"><?php esc_html_e( 'Reason', 'dovetail-studio' ); ?></label></th>
			<td><input id="dovetail-lg-reason" class="regular-text" type="text" name="reason" maxlength="255"></td>
		</tr>
		<tr>
			<th scope="row"><label for="dovetail-lg-duration"><?php esc_html_e( 'Duration', 'dovetail-studio' ); ?></label></th>
			<td>
				<select id="dovetail-lg-duration" name="duration">
					<option value="0"><?php esc_html_e( 'Permanent', 'dovetail-studio' ); ?></option>
					<?php foreach ( $args['durations'] as $seconds => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $seconds ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Block IP address', 'dovetail-studio' ), 'secondary', 'submit', false ); ?>
			</td>
		</tr>
	</table>
</form>

<section class="dovetail-card">
	<div class="dovetail-card-header">
		<h2><?php esc_html_e( 'Blocks', 'dovetail-studio' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Only blocks made by this site are listed. Addresses blocked on the server (Imunify360, the hosting firewall) are managed there.', 'dovetail-studio' ); ?></p>
	</div>
	<div class="dovetail-card-body">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $args['page'] ); ?>">
			<input type="hidden" name="tab" value="blocking">
			<label class="screen-reader-text" for="dovetail-lg-search"><?php esc_html_e( 'Filter by IP, username or reason', 'dovetail-studio' ); ?></label>
			<input id="dovetail-lg-search" type="search" name="s" value="<?php echo esc_attr( $filter ); ?>" placeholder="<?php esc_attr_e( 'Filter by IP, username or reason', 'dovetail-studio' ); ?>">
			<label class="screen-reader-text" for="dovetail-lg-source"><?php esc_html_e( 'Source', 'dovetail-studio' ); ?></label>
			<select id="dovetail-lg-source" name="source">
				<?php
				foreach ( array(
					'all'       => __( 'All blocks', 'dovetail-studio' ),
					'automatic' => __( 'Automatic blocks', 'dovetail-studio' ),
					'manual'    => __( 'Manual blocks', 'dovetail-studio' ),
				) as $value => $label ) :
					?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $source, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'dovetail-studio' ), 'secondary', '', false ); ?>
			<a class="button" href="<?php echo esc_url( $args['export_url'] ); ?>"><?php esc_html_e( 'Export all IPs', 'dovetail-studio' ); ?></a>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dovetail-lg-bulk">
			<input type="hidden" name="action" value="dovetail_login_guard_bulk">
			<?php wp_nonce_field( 'dovetail_login_guard_bulk' ); ?>
			<p>
				<button type="submit" class="button" name="bulk" value="unblock"><?php esc_html_e( 'Unblock', 'dovetail-studio' ); ?></button>
				<button type="submit" class="button" name="bulk" value="permanent"><?php esc_html_e( 'Make permanent', 'dovetail-studio' ); ?></button>
			</p>
			<div class="dovetail-table-scroll">
				<table class="widefat striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><label class="screen-reader-text" for="dovetail-lg-all"><?php esc_html_e( 'Select all', 'dovetail-studio' ); ?></label><input id="dovetail-lg-all" type="checkbox"></td>
							<th scope="col"><?php esc_html_e( 'Block type', 'dovetail-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Detail', 'dovetail-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Rule added', 'dovetail-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Reason', 'dovetail-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Expiration', 'dovetail-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Block count', 'dovetail-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last attempt', 'dovetail-studio' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $result['rows'] ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No blocks are currently active.', 'dovetail-studio' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['rows'] as $row ) : ?>
							<?php $row_id = 'dovetail-lg-' . (int) $row['id']; ?>
							<tr>
								<th scope="row" class="check-column">
									<label class="screen-reader-text" for="<?php echo esc_attr( $row_id ); ?>"><?php echo esc_html( sprintf( /* translators: %s: IP address. */ __( 'Select %s', 'dovetail-studio' ), $row['ip'] ) ); ?></label>
									<input id="<?php echo esc_attr( $row_id ); ?>" type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) (int) $row['id'] ); ?>">
								</th>
								<td><?php echo esc_html( $types[ $row['kind'] ] ?? $row['kind'] ); ?><br><span class="description"><?php echo 'manual' === $row['source'] ? esc_html__( 'Manual', 'dovetail-studio' ) : esc_html__( 'Automatic', 'dovetail-studio' ); ?></span></td>
								<td><code><?php echo esc_html( $row['ip'] ); ?></code><?php echo '' !== (string) $row['username'] ? '<br><span class="description">' . esc_html( $row['username'] ) . '</span>' : ''; ?></td>
								<td><?php echo esc_html( wp_date( $args['date_format'], (int) $row['created_at'] ) ); ?></td>
								<td><?php echo esc_html( $row['reason'] ); ?></td>
								<td><?php echo 0 === (int) $row['expires_at'] ? esc_html__( 'Permanent', 'dovetail-studio' ) : esc_html( wp_date( $args['date_format'], (int) $row['expires_at'] ) ); ?></td>
								<td><?php echo esc_html( (string) (int) $row['hits'] ); ?></td>
								<td><?php echo (int) $row['last_attempt'] ? esc_html( wp_date( $args['date_format'], (int) $row['last_attempt'] ) ) : '&mdash;'; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</form>

		<?php if ( $page_count > 1 ) : ?>
			<p>
				<?php
				if ( '' !== $args['prev_url'] ) {
					echo '<a class="button" href="' . esc_url( $args['prev_url'] ) . '">' . esc_html__( 'Previous', 'dovetail-studio' ) . '</a> ';
				}

				/* translators: 1: current page, 2: number of pages, 3: number of blocks. */
				echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d blocks)', 'dovetail-studio' ), $current_page, $page_count, $result['total'] ) );

				if ( '' !== $args['next_url'] ) {
					echo ' <a class="button" href="' . esc_url( $args['next_url'] ) . '">' . esc_html__( 'Next', 'dovetail-studio' ) . '</a>';
				}
				?>
			</p>
		<?php endif; ?>
	</div>
</section>
