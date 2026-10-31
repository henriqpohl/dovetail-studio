<?php
/**
 * Login Guard admin screen: block list and settings.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\AdminScreens;
use DovetailStudio\Core\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Dovetail Studio → Login Guard, with two tabs (the select-all checkbox of the
 * block list is WordPress core's own `check-column` behaviour):
 *
 * - Blocking: the IP addresses locked out or blocked right now, with unblock,
 *   make permanent, export and a form to block an address by hand.
 * - Settings: brute force protection, password policies, hardening switches
 *   and the allowlist.
 */
final class Admin {

	const PAGE = 'dovetail-login-guard';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_post_dovetail_login_guard_block', array( $this, 'handle_block' ) );
		add_action( 'admin_post_dovetail_login_guard_bulk', array( $this, 'handle_bulk' ) );
		add_action( 'admin_post_dovetail_login_guard_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_dovetail_login_guard_reset_scores', array( $this, 'handle_reset_scores' ) );
	}

	/**
	 * Add the page under Dovetail Studio.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		add_submenu_page(
			'dovetail-studio',
			__( 'Login Guard', 'dovetail-studio' ),
			__( 'Login Guard', 'dovetail-studio' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings option.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'dovetail_login_guard',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * URL of the page.
	 *
	 * @param array<string,string|int> $args Extra query arguments.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Messages of the block list actions, by code. Only a code travels in the redirect URL.
	 *
	 * @param int $count Rows affected.
	 * @return array<string,array{0:string,1:string}> Code => [type, message].
	 */
	private function messages( int $count ): array {
		return array(
			'blocked'        => array( 'success', __( 'The IP address was blocked.', 'dovetail-studio' ) ),
			'invalid_ip'     => array( 'error', __( 'Enter a valid IPv4 or IPv6 address.', 'dovetail-studio' ) ),
			'own_ip'         => array( 'error', __( 'You cannot block your own IP address.', 'dovetail-studio' ) ),
			'allowlisted'    => array( 'error', __( 'This IP address is on the allowlist. Remove it from the allowlist first.', 'dovetail-studio' ) ),
			'none_selected'  => array( 'error', __( 'Select at least one block.', 'dovetail-studio' ) ),
			/* translators: %d: number of IP addresses. */
			'unblocked'      => array( 'success', sprintf( _n( '%d IP address was unblocked.', '%d IP addresses were unblocked.', $count, 'dovetail-studio' ), $count ) ),
			/* translators: %d: number of blocks. */
			'made_permanent' => array( 'success', sprintf( _n( '%d block is now permanent.', '%d blocks are now permanent.', $count, 'dovetail-studio' ), $count ) ),
		);
	}

	/**
	 * Back to the block list with a status code.
	 *
	 * @param string $code  Message code.
	 * @param int    $count Rows affected.
	 * @return void
	 */
	private function redirect( string $code, int $count = 0 ): void {
		wp_safe_redirect(
			self::url(
				array(
					'tab'     => 'blocking',
					'lg_code' => sanitize_key( $code ),
					'lg_n'    => $count,
				)
			)
		);
		exit;
	}

	/**
	 * Refuse users who cannot manage options.
	 *
	 * @return void
	 */
	private function require_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dovetail-studio' ) );
		}
	}

	/**
	 * Block an IP address by hand.
	 *
	 * @return void
	 */
	public function handle_block(): void {
		$this->require_capability();
		check_admin_referer( 'dovetail_login_guard_block' );

		$ip       = Store::normalize_ip( isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '' );
		$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 0;

		if ( '' === $ip ) {
			$this->redirect( 'invalid_ip' );
		}

		if ( Store::normalize_ip( Http::client_ip() ) === $ip ) {
			$this->redirect( 'own_ip' );
		}

		if ( Guard::is_allowlisted( $ip ) ) {
			$this->redirect( 'allowlisted' );
		}

		if ( ! isset( Settings::duration_choices()[ $duration ] ) ) {
			$duration = 0;
		}

		Store::add( $ip, Store::KIND_BLOCK, 'manual', '' !== $reason ? $reason : __( 'Manual block', 'dovetail-studio' ), $duration );
		$this->redirect( 'blocked' );
	}

	/**
	 * Unblock or make permanent the selected rows.
	 *
	 * @return void
	 */
	public function handle_bulk(): void {
		$this->require_capability();
		check_admin_referer( 'dovetail_login_guard_bulk' );

		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to integers.
		$action = isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : '';
		$ids    = array_values( array_filter( $ids ) );

		if ( ! $ids ) {
			$this->redirect( 'none_selected' );
		}

		if ( 'permanent' === $action ) {
			$this->redirect( 'made_permanent', Store::make_permanent( $ids ) );
		}

		$this->redirect( 'unblocked', Store::delete( $ids ) );
	}

	/**
	 * Download the active blocks as CSV.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$this->require_capability();
		check_admin_referer( 'dovetail_login_guard_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=login-guard-blocks-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo self::csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		exit;
	}

	/**
	 * The active blocks as CSV text.
	 *
	 * @return string
	 */
	public static function csv(): string {
		/**
		 * Neutralise a cell that a spreadsheet would run as a formula (= + - @); usernames come from attackers.
		 *
		 * @param mixed $value Cell value.
		 * @return string
		 */
		$cell = static function ( $value ) {
			$value = (string) $value;

			return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
		};

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory buffer.
		fputcsv( $handle, array( 'IP', 'Type', 'Reason', 'Username', 'Rule added', 'Expires', 'Block count', 'Last attempt' ), ',', '"', '' );

		foreach ( Store::query( '', 'all', 1, 0 )['rows'] as $row ) {
			fputcsv(
				$handle,
				array(
					$row['ip'],
					$row['kind'],
					$cell( $row['reason'] ),
					$cell( $row['username'] ),
					wp_date( 'Y-m-d H:i:s', (int) $row['created_at'] ),
					0 === (int) $row['expires_at'] ? 'permanent' : wp_date( 'Y-m-d H:i:s', (int) $row['expires_at'] ),
					(int) $row['hits'],
					(int) $row['last_attempt'] ? wp_date( 'Y-m-d H:i:s', (int) $row['last_attempt'] ) : '',
				),
				',',
				'"',
				''
			);
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory buffer.

		return $csv;
	}

	/**
	 * A timestamp in the site's date and time format.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private function date( int $timestamp ): string {
		return wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $timestamp );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'blocking'   => __( 'Blocking', 'dovetail-studio' ),
			'activity'   => __( 'Activity', 'dovetail-studio' ),
			'settings'   => __( 'Settings', 'dovetail-studio' ),
			'two-factor' => __( 'Two-Factor', 'dovetail-studio' ),
			'captcha'    => __( 'Captcha', 'dovetail-studio' ),
			'alerts'     => __( 'Alerts', 'dovetail-studio' ),
		);
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'blocking'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation.
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'blocking';
		?>
		<div class="wrap dovetail-wrap">
			<div class="dovetail-page-header">
				<div>
					<h1><?php esc_html_e( 'Login Guard', 'dovetail-studio' ); ?></h1>
					<p><?php esc_html_e( 'Brute force protection, two-factor authentication, captcha, alerts and the list of blocked IP addresses.', 'dovetail-studio' ); ?></p>
				</div>
				<span class="dovetail-version"><?php echo esc_html( 'v' . DOVETAIL_LOGIN_GUARD_VERSION ); ?></span>
			</div>

			<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Login Guard sections', 'dovetail-studio' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::url( array( 'tab' => $slug ) ) ); ?>"<?php echo $slug === $tab ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( Guard::disabled() ) {
				AdminScreens::notice( 'warning', esc_html__( 'Login Guard is switched off by DOVETAIL_LOGIN_GUARD_DISABLED in wp-config.php: nothing is locked out or blocked until that line is removed.', 'dovetail-studio' ) );
			}

			if ( in_array( $tab, array( 'settings', 'two-factor', 'captcha', 'alerts' ), true ) ) {
				// This page is not under Settings, so WordPress does not print validation errors or the saved notice by itself.
				AdminScreens::settings_errors();
			} else {
				$this->render_notice();
			}

			switch ( $tab ) {
				case 'activity':
					$this->render_activity();

					break;
				case 'settings':
					$this->render_settings();

					break;
				case 'two-factor':
					$this->render_two_factor();

					break;
				case 'captcha':
					$this->render_captcha();

					break;
				case 'alerts':
					$this->render_alerts();

					break;
				default:
					$this->render_blocking();
			}
			?>
		</div>
		<?php
	}

	/**
	 * The status notice of a block list action.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		$code     = isset( $_GET['lg_code'] ) ? sanitize_key( wp_unslash( $_GET['lg_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$count    = isset( $_GET['lg_n'] ) ? absint( $_GET['lg_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$messages = $this->messages( $count );

		if ( isset( $messages[ $code ] ) ) {
			AdminScreens::notice( $messages[ $code ][0], esc_html( $messages[ $code ][1] ), true );
		}
	}

	/**
	 * Blocking tab.
	 *
	 * @return void
	 */
	private function render_blocking(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List filters.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : 'all';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$source = in_array( $source, array( 'all', 'automatic', 'manual' ), true ) ? $source : 'all';
		$result = Store::query( $search, $source, $paged );
		$pages  = max( 1, (int) ceil( $result['total'] / Store::PER_PAGE ) );
		$my_ip  = Store::normalize_ip( Http::client_ip() );
		$mine   = '' !== $my_ip ? Store::active( $my_ip ) : null;
		$types  = array(
			Store::KIND_BLOCK   => __( 'Blocked', 'dovetail-studio' ),
			Store::KIND_LOCKOUT => __( 'Locked out', 'dovetail-studio' ),
		);
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

					if ( '' !== $my_ip && Guard::is_allowlisted( $my_ip ) ) {
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
							<?php foreach ( Settings::duration_choices() as $seconds => $label ) : ?>
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
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
					<input type="hidden" name="tab" value="blocking">
					<label class="screen-reader-text" for="dovetail-lg-search"><?php esc_html_e( 'Filter by IP, username or reason', 'dovetail-studio' ); ?></label>
					<input id="dovetail-lg-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Filter by IP, username or reason', 'dovetail-studio' ); ?>">
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
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dovetail_login_guard_export' ), 'dovetail_login_guard_export' ) ); ?>"><?php esc_html_e( 'Export all IPs', 'dovetail-studio' ); ?></a>
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
										<td><?php echo esc_html( $this->date( (int) $row['created_at'] ) ); ?></td>
										<td><?php echo esc_html( $row['reason'] ); ?></td>
										<td><?php echo 0 === (int) $row['expires_at'] ? esc_html__( 'Permanent', 'dovetail-studio' ) : esc_html( $this->date( (int) $row['expires_at'] ) ); ?></td>
										<td><?php echo esc_html( (string) (int) $row['hits'] ); ?></td>
										<td><?php echo (int) $row['last_attempt'] ? esc_html( $this->date( (int) $row['last_attempt'] ) ) : '&mdash;'; ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</form>

				<?php if ( $pages > 1 ) : ?>
					<p>
						<?php
						$base = array(
							'tab'    => 'blocking',
							's'      => $search,
							'source' => $source,
						);

						if ( $paged > 1 ) {
							echo '<a class="button" href="' . esc_url( self::url( array_merge( $base, array( 'paged' => $paged - 1 ) ) ) ) . '">' . esc_html__( 'Previous', 'dovetail-studio' ) . '</a> ';
						}

						/* translators: 1: current page, 2: number of pages, 3: number of blocks. */
						echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d blocks)', 'dovetail-studio' ), $paged, $pages, $result['total'] ) );

						if ( $paged < $pages ) {
							echo ' <a class="button" href="' . esc_url( self::url( array_merge( $base, array( 'paged' => $paged + 1 ) ) ) ) . '">' . esc_html__( 'Next', 'dovetail-studio' ) . '</a>';
						}
						?>
					</p>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * A checkbox row of the settings form.
	 *
	 * @param array<string,mixed> $settings    Settings.
	 * @param string              $key         Setting key.
	 * @param string              $label       Label.
	 * @param string              $description Optional help text.
	 * @return void
	 */
	private function checkbox_row( array $settings, string $key, string $label, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>> <?php esc_html_e( 'Enabled', 'dovetail-studio' ); ?></label>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * A select row of the settings form.
	 *
	 * @param array<string,mixed>      $settings Settings.
	 * @param string                   $key      Setting key.
	 * @param string                   $label    Label.
	 * @param array<int|string,string> $choices  Value => label.
	 * @return void
	 */
	private function select_row( array $settings, string $key, string $label, array $choices ): void {
		$id = 'dovetail-lg-' . str_replace( '_', '-', $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( Settings::OPTION . '[' . $key . ']' ); ?>">
					<?php foreach ( $choices as $value => $text ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $settings[ $key ], (string) $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private function render_settings(): void {
		$settings = Settings::get();
		$counts   = array_combine( Settings::count_choices(), array_map( 'strval', Settings::count_choices() ) );
		$existing = array_values( array_filter( (array) $settings['blocked_usernames'], 'username_exists' ) );
		$my_ip    = Store::normalize_ip( Http::client_ip() );
		$name     = Settings::OPTION;
		?>
		<form action="options.php" method="post">
			<?php settings_fields( 'dovetail_login_guard' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_section]" value="general">

			<section class="dovetail-card" id="dovetail-lg-brute-force">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Brute Force Protection', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Failed sign-ins and password reset requests are counted per IP address. An address that goes over a limit is locked out: it cannot sign in or reset a password until the lockout ends or you unblock it.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( $settings, 'brute_force', __( 'Enable brute force protection', 'dovetail-studio' ), __( 'Turns every rule of this section on or off.', 'dovetail-studio' ) );
					$this->select_row( $settings, 'max_login_failures', __( 'Lock out after how many login failures', 'dovetail-studio' ), $counts );
					$this->select_row( $settings, 'max_lostpw_attempts', __( 'Lock out after how many forgot password attempts', 'dovetail-studio' ), $counts );
					$this->select_row( $settings, 'failure_window', __( 'Count failures over what time period', 'dovetail-studio' ), Settings::window_choices() );
					$this->select_row( $settings, 'lockout_duration', __( 'Amount of time a user is locked out', 'dovetail-studio' ), Settings::duration_choices() );
					$this->checkbox_row( $settings, 'lockout_invalid_usernames', __( 'Immediately lock out invalid usernames', 'dovetail-studio' ), __( 'A sign-in with a username or email address that has no account locks the IP out at once.', 'dovetail-studio' ) );
					?>
					<tr>
						<th scope="row"><label for="dovetail-lg-blocked-usernames"><?php esc_html_e( 'Immediately block the IP of users who try to sign in as these usernames', 'dovetail-studio' ); ?></label></th>
						<td>
							<textarea id="dovetail-lg-blocked-usernames" class="regular-text" rows="5" name="<?php echo esc_attr( $name ); ?>[blocked_usernames]"><?php echo esc_textarea( implode( "\n", (array) $settings['blocked_usernames'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One username per line. The IP is blocked from the whole site for the lockout time. A username that belongs to a real account is ignored, so nobody can get your own users\' IPs blocked.', 'dovetail-studio' ); ?></p>
							<?php if ( $existing ) : ?>
								<p class="description"><strong>
								<?php
								/* translators: %s: usernames. */
								echo esc_html( sprintf( __( 'Ignored because an account uses them: %s', 'dovetail-studio' ), implode( ', ', $existing ) ) );
								?>
								</strong></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</section>

			<section class="dovetail-card" id="dovetail-lg-passwords">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Passwords', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Checked whenever a password is set: on the profile screen, for new users and on password reset. Strong means at least 12 characters with three of lowercase letters, uppercase letters, numbers and symbols, without the username or email. Leaked passwords are looked up in Have I Been Pwned without sending the password; a user whose current password is leaked must reset it before signing in.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php
					$this->select_row( $settings, 'strong_passwords', __( 'Enforce strong passwords', 'dovetail-studio' ), Settings::strong_choices() );
					$this->select_row( $settings, 'leaked_passwords', __( 'Prevent the use of passwords leaked in data breaches', 'dovetail-studio' ), Settings::leaked_choices() );
					?>
				</table>
			</section>

			<section class="dovetail-card" id="dovetail-lg-additional">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Additional Options', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Hardening that keeps usernames private and closes doors bots use.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( $settings, 'hide_login_errors', __( 'Don\'t let WordPress reveal valid users in login errors', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'prevent_admin_registration', __( 'Prevent users registering \'admin\' username if it doesn\'t exist', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'prevent_user_enumeration', __( 'Prevent discovery of usernames through \'/?author=N\' scans, the oEmbed API, the WordPress REST API, and WordPress XML Sitemaps', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'disable_application_passwords', __( 'Disable WordPress application passwords', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'disable_xmlrpc_auth', __( 'Disable XML-RPC authentication', 'dovetail-studio' ), __( 'Refuses every XML-RPC method that needs a sign-in, so bots cannot try many passwords in one request (system.multicall). Pingbacks keep working. Turn it off only for the WordPress mobile app or Jetpack.', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'disable_xmlrpc', __( 'Disable XML-RPC completely', 'dovetail-studio' ), __( 'Also switches off pingbacks and the X-Pingback header.', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'block_blank_ua_post', __( 'Block IPs who send POST requests with blank User-Agent and Referer', 'dovetail-studio' ), __( 'If you use external services that may send POST requests without these headers, do not use this option, as they will be blocked.', 'dovetail-studio' ) );
					?>
					<tr>
						<th scope="row"><label for="dovetail-lg-block-text"><?php esc_html_e( 'Custom text shown on block pages', 'dovetail-studio' ); ?></label></th>
						<td>
							<textarea id="dovetail-lg-block-text" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[block_page_text]"><?php echo esc_textarea( (string) $settings['block_page_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Plain text; line breaks are kept. Leave empty for the default message.', 'dovetail-studio' ); ?></p>
						</td>
					</tr>
				</table>
			</section>

			<section class="dovetail-card" id="dovetail-lg-allowlist">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Allowlist', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Addresses here are never locked out or blocked.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dovetail-lg-allowlist-ips"><?php esc_html_e( 'Allowlisted IP addresses', 'dovetail-studio' ); ?></label></th>
						<td>
							<textarea id="dovetail-lg-allowlist-ips" class="regular-text" rows="4" name="<?php echo esc_attr( $name ); ?>[allowlist]" placeholder="203.0.113.10&#10;198.51.100.0/24"><?php echo esc_textarea( implode( "\n", (array) $settings['allowlist'] ) ); ?></textarea>
							<p class="description">
							<?php
							/* translators: %s: the visitor's IP address. */
							echo esc_html( sprintf( __( 'One IP address or CIDR range per line. Your IP address: %s.', 'dovetail-studio' ), '' !== $my_ip ? $my_ip : __( 'unknown', 'dovetail-studio' ) ) );
							?>
							</p>
						</td>
					</tr>
				</table>
			</section>

			<div class="dovetail-save-bar">
				<?php submit_button( __( 'Save Login Guard Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Clear the reCAPTCHA score histogram.
	 *
	 * @return void
	 */
	public function handle_reset_scores(): void {
		$this->require_capability();
		check_admin_referer( 'dovetail_login_guard_reset_scores' );

		delete_option( Captcha::SCORES_OPTION );
		wp_safe_redirect( self::url( array( 'tab' => 'captcha' ) ) );
		exit;
	}

	/**
	 * Hidden fields every settings form starts with.
	 *
	 * @param string $section Settings section of the form.
	 * @return void
	 */
	private function form_start( string $section ): void {
		settings_fields( 'dovetail_login_guard' );
		echo '<input type="hidden" name="' . esc_attr( Settings::OPTION ) . '[_section]" value="' . esc_attr( $section ) . '">';
	}

	/**
	 * Save bar of a settings form.
	 *
	 * @param string $label Button label.
	 * @return void
	 */
	private function form_end( string $label ): void {
		echo '<div class="dovetail-save-bar">';
		submit_button( $label, 'primary', 'submit', false );
		echo '</div>';
	}

	/**
	 * Activity tab.
	 *
	 * @return void
	 */
	private function render_activity(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List filters.
		$event  = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$labels = Activity::labels();
		$event  = isset( $labels[ $event ] ) ? $event : '';
		$result = Activity::query( $event, $search, $paged );
		$pages  = max( 1, (int) ceil( $result['total'] / Activity::PER_PAGE ) );
		$days   = (int) Settings::get()['activity_retention_days'];
		?>
		<section class="dovetail-card">
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Activity', 'dovetail-studio' ); ?></h2>
				<p class="description">
				<?php
				echo empty( Settings::get()['activity_enabled'] )
					? esc_html__( 'The activity log is switched off in the Alerts tab; nothing new is recorded.', 'dovetail-studio' )
					/* translators: %d: number of days. */
					: esc_html( sprintf( _n( 'Sign-ins, failures, lockouts, blocks, two-factor and captcha results of the last %d day.', 'Sign-ins, failures, lockouts, blocks, two-factor and captcha results of the last %d days.', $days, 'dovetail-studio' ), $days ) );
				?>
				</p>
			</div>
			<div class="dovetail-card-body">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
					<input type="hidden" name="tab" value="activity">
					<label class="screen-reader-text" for="dovetail-lg-activity-search"><?php esc_html_e( 'Filter by IP, username or detail', 'dovetail-studio' ); ?></label>
					<input id="dovetail-lg-activity-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Filter by IP, username or detail', 'dovetail-studio' ); ?>">
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
									<td><?php echo esc_html( $this->date( (int) $row['created_at'] ) ); ?></td>
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

				<?php
				$this->render_pages(
					'activity',
					$paged,
					$pages,
					$result['total'],
					array(
						'event' => $event,
						's'     => $search,
					)
				);
				?>
			</div>
		</section>
		<?php
	}

	/**
	 * Previous / next links of a list.
	 *
	 * @param string                   $tab   Tab slug.
	 * @param int                      $paged Current page.
	 * @param int                      $pages Number of pages.
	 * @param int                      $total Number of rows.
	 * @param array<string,string|int> $args  Filter arguments to keep.
	 * @return void
	 */
	private function render_pages( string $tab, int $paged, int $pages, int $total, array $args ): void {
		if ( $pages <= 1 ) {
			return;
		}

		$base = array_merge( array( 'tab' => $tab ), $args );

		echo '<p>';

		if ( $paged > 1 ) {
			echo '<a class="button" href="' . esc_url( self::url( array_merge( $base, array( 'paged' => $paged - 1 ) ) ) ) . '">' . esc_html__( 'Previous', 'dovetail-studio' ) . '</a> ';
		}

		/* translators: 1: current page, 2: number of pages, 3: number of rows. */
		echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d entries)', 'dovetail-studio' ), $paged, $pages, $total ) );

		if ( $paged < $pages ) {
			echo ' <a class="button" href="' . esc_url( self::url( array_merge( $base, array( 'paged' => $paged + 1 ) ) ) ) . '">' . esc_html__( 'Next', 'dovetail-studio' ) . '</a>';
		}

		echo '</p>';
	}

	/**
	 * Two-Factor tab.
	 *
	 * @return void
	 */
	private function render_two_factor(): void {
		$settings = Settings::get();
		$modes    = Settings::two_factor_modes();
		$name     = Settings::OPTION;
		$days     = array();
		$remember = array();

		foreach ( Settings::grace_choices() as $value ) {
			/* translators: %d: number of days. */
			$days[ $value ] = 0 === $value ? __( 'None: required at the next sign-in', 'dovetail-studio' ) : sprintf( _n( '%d day', '%d days', $value, 'dovetail-studio' ), $value );
		}

		foreach ( Settings::remember_choices() as $value ) {
			/* translators: %d: number of days. */
			$remember[ $value ] = 0 === $value ? __( 'Off: ask for a code at every sign-in', 'dovetail-studio' ) : sprintf( _n( '%d day', '%d days', $value, 'dovetail-studio' ), $value );
		}
		?>
		<form action="options.php" method="post">
			<?php $this->form_start( 'two_factor' ); ?>
			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Two-Factor Authentication', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'After the password, users enter a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy…) or one of ten single-use recovery codes. Each user sets it up in Users → Profile. A wrong code counts as a failed sign-in.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php foreach ( Settings::roles() as $role => $role_name ) : ?>
						<?php $id = 'dovetail-lg-2fa-' . $role; ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $role_name ); ?></label></th>
							<td>
								<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name . '[two_factor_roles][' . $role . ']' ); ?>">
									<?php foreach ( $modes as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $settings['two_factor_roles'][ $role ] ?? 'off' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"></th>
						<td><p class="description"><?php esc_html_e( 'Optional: users of the role may turn it on. Required: they must set it up; until then a notice is shown, and after the grace period they can only open their profile.', 'dovetail-studio' ); ?></p></td>
					</tr>
					<?php
					$this->select_row( $settings, 'two_factor_grace_days', __( 'Grace period for required roles', 'dovetail-studio' ), $days );
					$this->select_row( $settings, 'two_factor_remember_days', __( 'Allow remembering a device for', 'dovetail-studio' ), $remember );
					?>
				</table>
			</section>
			<?php $this->form_end( __( 'Save Two-Factor Settings', 'dovetail-studio' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Inline SVG bar chart of the reCAPTCHA scores, with a text alternative.
	 *
	 * @param int[] $scores Eleven counts, 0.0 to 1.0.
	 * @return string
	 */
	private function histogram( array $scores ): string {
		$max    = max( 1, max( $scores ) );
		$width  = 420;
		$height = 160;
		$left   = 40;
		$bottom = 130;
		$step   = ( $width - $left - 10 ) / 11;
		$bars   = '';
		$labels = '';
		$text   = array();

		foreach ( $scores as $i => $count ) {
			$x       = $left + $i * $step;
			$h       = (int) round( ( $count / $max ) * 110 );
			$score   = number_format( $i / 10, 1 );
			$bars   .= '<rect x="' . round( $x + 3, 1 ) . '" y="' . ( $bottom - $h ) . '" width="' . round( $step - 6, 1 ) . '" height="' . $h . '" fill="currentColor" opacity="0.45"><title>' . esc_html( $score . ': ' . $count ) . '</title></rect>';
			$labels .= '<text x="' . round( $x + $step / 2, 1 ) . '" y="' . ( $bottom + 16 ) . '" text-anchor="middle" font-size="10">' . esc_html( $score ) . '</text>';
			$text[]  = $score . ': ' . $count;
		}

		/* translators: %s: list of "score: requests" pairs. */
		$summary = sprintf( __( 'Requests per reCAPTCHA score: %s', 'dovetail-studio' ), implode( ', ', $text ) );

		return '<figure>'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" role="img" aria-label="' . esc_attr( $summary ) . '" style="max-width:100%;height:auto">'
			. '<line x1="' . $left . '" y1="' . $bottom . '" x2="' . ( $width - 10 ) . '" y2="' . $bottom . '" stroke="currentColor" opacity="0.4"/>'
			. '<text x="' . ( $left - 6 ) . '" y="' . ( $bottom - 110 + 4 ) . '" text-anchor="end" font-size="10">' . (int) $max . '</text>'
			. '<text x="' . ( $left - 6 ) . '" y="' . ( $bottom + 4 ) . '" text-anchor="end" font-size="10">0</text>'
			. $bars . $labels
			. '</svg>'
			. '<figcaption class="screen-reader-text">' . esc_html( $summary ) . '</figcaption>'
			. '</figure>';
	}

	/**
	 * Captcha tab.
	 *
	 * @return void
	 */
	private function render_captcha(): void {
		$settings   = Settings::get();
		$name       = Settings::OPTION;
		$has_secret = '' !== (string) $settings['captcha_secret'] || defined( 'DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET' );
		$reset_url  = wp_nonce_url( admin_url( 'admin-post.php?action=dovetail_login_guard_reset_scores' ), 'dovetail_login_guard_reset_scores' );
		?>
		<form action="options.php" method="post">
			<?php $this->form_start( 'captcha' ); ?>
			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Captcha', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Protect the login and registration forms (WordPress and WooCommerce) from automated abuse.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php $this->checkbox_row( $settings, 'captcha_enabled', __( 'Enable captcha on the login and user registration pages', 'dovetail-studio' ), __( 'reCAPTCHA v3 does not make users solve puzzles or click a checkbox; the only visible part is its logo. Turnstile usually passes without interaction too. The provider\'s script loads on the login and registration forms only.', 'dovetail-studio' ) ); ?>
					<?php $this->select_row( $settings, 'captcha_provider', __( 'Provider', 'dovetail-studio' ), Settings::captcha_providers() ); ?>
					<tr>
						<th scope="row"><label for="dovetail-lg-site-key"><?php esc_html_e( 'Site key', 'dovetail-studio' ); ?></label></th>
						<td><input id="dovetail-lg-site-key" class="regular-text" type="text" name="<?php echo esc_attr( $name ); ?>[captcha_site_key]" value="<?php echo esc_attr( (string) $settings['captcha_site_key'] ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dovetail-lg-secret"><?php esc_html_e( 'Secret key', 'dovetail-studio' ); ?></label></th>
						<td>
							<input id="dovetail-lg-secret" class="regular-text" type="password" name="<?php echo esc_attr( $name ); ?>[captcha_secret]" value="" autocomplete="new-password">
							<p class="description"><?php echo $has_secret ? esc_html__( 'A secret is saved (encrypted). Leave blank to keep it.', 'dovetail-studio' ) : esc_html__( 'Stored encrypted. Changing the provider clears it.', 'dovetail-studio' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td>
							<p class="description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: 1: reCAPTCHA admin URL, 2: Cloudflare Turnstile URL. */
									__( 'Both services are free. Create reCAPTCHA v3 keys in the <a href="%1$s" target="_blank" rel="noopener noreferrer">reCAPTCHA admin console<span class="screen-reader-text"> (opens in a new tab)</span></a>, or Turnstile keys in the <a href="%2$s" target="_blank" rel="noopener noreferrer">Cloudflare dashboard<span class="screen-reader-text"> (opens in a new tab)</span></a>.', 'dovetail-studio' ),
									'https://www.google.com/recaptcha/admin/create',
									'https://dash.cloudflare.com/?to=/:account/turnstile'
								)
							);
							?>
							</p>
						</td>
					</tr>
				</table>
			</section>

			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'reCAPTCHA human/bot threshold score', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'reCAPTCHA only; Turnstile passes or fails without a score. A score equal to or higher than this value is considered human. Anything lower is treated as a bot and needs extra verification to sign in (the email link below); registrations below it are refused.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php $this->select_row( $settings, 'captcha_threshold', __( 'Threshold score', 'dovetail-studio' ), Settings::threshold_choices() ); ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Requests per score', 'dovetail-studio' ); ?></th>
						<td>
							<?php echo $this->histogram( Captcha::scores() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
							<p><a href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset score statistics', 'dovetail-studio' ); ?></a></p>
						</td>
					</tr>
					<?php
					$this->checkbox_row( $settings, 'captcha_email_verify', __( 'Email a sign-in link when the score is too low', 'dovetail-studio' ), __( 'When the password is right but the score is below the threshold, the user gets an email with a link that finishes the sign-in (once, within 15 minutes). Without it, the sign-in is refused.', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'captcha_test_mode', __( 'Run captcha in test mode', 'dovetail-studio' ), __( 'While in test mode, the captcha scores login and registration requests but does not block them. Results and scores are recorded in the Activity tab and the chart above, so you can choose a threshold.', 'dovetail-studio' ) );
					?>
				</table>
			</section>
			<?php $this->form_end( __( 'Save Captcha Settings', 'dovetail-studio' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Alerts tab.
	 *
	 * @return void
	 */
	private function render_alerts(): void {
		$settings = Settings::get();
		$name     = Settings::OPTION;
		$caps     = array();
		$every    = array();
		$keep     = array();

		foreach ( Settings::alert_cap_choices() as $value ) {
			$caps[ $value ] = 0 === $value ? __( 'No limit', 'dovetail-studio' ) : (string) $value;
		}

		foreach ( Settings::summary_day_choices() as $value ) {
			/* translators: %d: number of days. */
			$every[ $value ] = 1 === $value ? __( 'Every day', 'dovetail-studio' ) : sprintf( __( 'Every %d days', 'dovetail-studio' ), $value );
		}

		foreach ( Settings::retention_choices() as $value ) {
			/* translators: %d: number of days. */
			$keep[ $value ] = sprintf( _n( '%d day', '%d days', $value, 'dovetail-studio' ), $value );
		}
		?>
		<form action="options.php" method="post">
			<?php $this->form_start( 'alerts' ); ?>
			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Email Alerts', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Plain-text emails with the username, IP address, hostname and, behind Cloudflare, the country.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dovetail-lg-recipients"><?php esc_html_e( 'Send alerts to', 'dovetail-studio' ); ?></label></th>
						<td>
							<textarea id="dovetail-lg-recipients" class="regular-text" rows="3" name="<?php echo esc_attr( $name ); ?>[alert_recipients]" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"><?php echo esc_textarea( implode( "\n", (array) $settings['alert_recipients'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One email address per line. Empty: the site\'s administration email address.', 'dovetail-studio' ); ?></p>
						</td>
					</tr>
					<?php
					$this->checkbox_row( $settings, 'alert_admin_login', __( 'Alert me when someone with administrator access signs in', 'dovetail-studio' ) );
					$this->select_row(
						$settings,
						'alert_admin_login_scope',
						__( 'Which administrator sign-ins', 'dovetail-studio' ),
						array(
							'every'  => __( 'Every sign-in', 'dovetail-studio' ),
							'new_ip' => __( 'Only from an IP address not seen before', 'dovetail-studio' ),
						)
					);
					$this->checkbox_row( $settings, 'alert_blocked', __( 'Alert me when an IP address is blocked', 'dovetail-studio' ), __( 'Blocks made by the rules (usernames, blank User-Agent POSTs); manual blocks send nothing.', 'dovetail-studio' ) );
					$this->checkbox_row( $settings, 'alert_lockout', __( 'Alert me when someone is locked out from login', 'dovetail-studio' ) );
					$this->select_row( $settings, 'alert_max_per_hour', __( 'Maximum alerts per hour', 'dovetail-studio' ), $caps );
					?>
				</table>
			</section>

			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Summary', 'dovetail-studio' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Totals of sign-ins, failures, lockouts and blocks, and the most active IP addresses, built from the activity log.', 'dovetail-studio' ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( $settings, 'summary_enabled', __( 'Email a summary', 'dovetail-studio' ) );
					$this->select_row( $settings, 'summary_days', __( 'How often', 'dovetail-studio' ), $every );
					$this->checkbox_row( $settings, 'summary_skip_empty', __( 'Skip the summary when nothing happened', 'dovetail-studio' ), __( 'No failed sign-ins, lockouts, blocks, refused two-factor codes or failed captchas in the period.', 'dovetail-studio' ) );
					?>
				</table>
			</section>

			<section class="dovetail-card">
				<div class="dovetail-card-header">
					<h2><?php esc_html_e( 'Activity Log', 'dovetail-studio' ); ?></h2>
				</div>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( $settings, 'activity_enabled', __( 'Record sign-in activity', 'dovetail-studio' ), __( 'Needed for the Activity tab and the summary. Stores usernames, IP addresses and browsers of sign-in attempts.', 'dovetail-studio' ) );
					$this->select_row( $settings, 'activity_retention_days', __( 'Keep entries for', 'dovetail-studio' ), $keep );
					?>
				</table>
			</section>
			<?php $this->form_end( __( 'Save Alert Settings', 'dovetail-studio' ) ); ?>
		</form>
		<?php
	}
}
