<?php
/**
 * Login Guard admin screen: handles the tab actions and prepares the data of each tab.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

use DovetailStudio\Core\Http;
use DovetailStudio\Core\Mail;
use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

/**
 * Dovetail Studio → Login Guard: Blocking, Settings, Activity, Two-Factor, Captcha and
 * Alerts tabs. This class checks permissions and builds each tab's arguments; the markup
 * is in views/admin-page.php and views/tab-*.php (the select-all checkbox of the block
 * list is WordPress core's own `check-column` behaviour).
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
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$labels = array(
			'blocking'   => __( 'Blocking', 'dovetail-studio' ),
			'activity'   => __( 'Activity', 'dovetail-studio' ),
			'settings'   => __( 'Settings', 'dovetail-studio' ),
			'two-factor' => __( 'Two-Factor', 'dovetail-studio' ),
			'captcha'    => __( 'Captcha', 'dovetail-studio' ),
			'alerts'     => __( 'Alerts', 'dovetail-studio' ),
		);
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'blocking'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation.
		$tab    = isset( $labels[ $tab ] ) ? $tab : 'blocking';
		$forms  = in_array( $tab, array( 'settings', 'two-factor', 'captcha', 'alerts' ), true );
		$tabs   = array();

		foreach ( $labels as $slug => $label ) {
			$tabs[ $slug ] = array(
				'label' => $label,
				'url'   => self::url( array( 'tab' => $slug ) ),
			);
		}

		View::render(
			self::dir(),
			'admin-page',
			array(
				'dir'             => self::dir(),
				'version'         => DOVETAIL_LOGIN_GUARD_VERSION,
				'tabs'            => $tabs,
				'tab'             => $tab,
				'disabled'        => Guard::disabled(),
				'settings_errors' => $forms,
				'notice'          => $forms ? null : $this->notice(),
				'tab_args'        => $this->tab_args( $tab ),
			)
		);
	}

	/**
	 * Module folder, which holds views/.
	 *
	 * @return string
	 */
	private static function dir(): string {
		return dirname( __DIR__ );
	}

	/**
	 * The status notice of a block list action: type and message, or null.
	 *
	 * @return array{0: string, 1: string}|null
	 */
	private function notice(): ?array {
		$code     = isset( $_GET['lg_code'] ) ? sanitize_key( wp_unslash( $_GET['lg_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$count    = isset( $_GET['lg_n'] ) ? absint( $_GET['lg_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$messages = $this->messages( $count );

		return $messages[ $code ] ?? null;
	}

	/**
	 * Values of a tab's template.
	 *
	 * @param string $tab Tab slug.
	 * @return array<string,mixed>
	 */
	private function tab_args( string $tab ): array {
		switch ( $tab ) {
			case 'activity':
				return $this->activity_args();
			case 'settings':
				return $this->settings_args();
			case 'two-factor':
				return $this->two_factor_args();
			case 'captcha':
				return $this->captcha_args();
			case 'alerts':
				return $this->alerts_args();
			default:
				return $this->blocking_args();
		}
	}

	/**
	 * Values shared by the settings forms.
	 *
	 * @return array<string,mixed>
	 */
	private function form_args(): array {
		return array(
			'dir'      => self::dir(),
			'option'   => Settings::OPTION,
			'settings' => Settings::get(),
		);
	}

	/**
	 * Previous and next page URLs of a list.
	 *
	 * @param array<string,string|int> $base  Query arguments to keep.
	 * @param int                      $paged Current page.
	 * @param int                      $pages Number of pages.
	 * @return array{prev_url: string, next_url: string}
	 */
	private function page_urls( array $base, int $paged, int $pages ): array {
		return array(
			'prev_url' => $paged > 1 ? self::url( array_merge( $base, array( 'paged' => $paged - 1 ) ) ) : '',
			'next_url' => $paged < $pages ? self::url( array_merge( $base, array( 'paged' => $paged + 1 ) ) ) : '',
		);
	}

	/**
	 * Blocking tab: your IP address, the block form and the block list.
	 *
	 * @return array<string,mixed>
	 */
	private function blocking_args(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List filters.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : 'all';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$source = in_array( $source, array( 'all', 'automatic', 'manual' ), true ) ? $source : 'all';
		$result = Store::query( $search, $source, $paged );
		$pages  = max( 1, (int) ceil( $result['total'] / Store::PER_PAGE ) );
		$my_ip  = Store::normalize_ip( Http::client_ip() );

		return array_merge(
			array(
				'my_ip'       => $my_ip,
				'allowlisted' => '' !== $my_ip && Guard::is_allowlisted( $my_ip ),
				'mine'        => '' !== $my_ip && (bool) Store::active( $my_ip ),
				'durations'   => Settings::duration_choices(),
				'page'        => self::PAGE,
				'export_url'  => wp_nonce_url( admin_url( 'admin-post.php?action=dovetail_login_guard_export' ), 'dovetail_login_guard_export' ),
				'search'      => $search,
				'source'      => $source,
				'result'      => $result,
				'types'       => array(
					Store::KIND_BLOCK   => __( 'Blocked', 'dovetail-studio' ),
					Store::KIND_LOCKOUT => __( 'Locked out', 'dovetail-studio' ),
				),
				'date_format' => Mail::date_format(),
				'paged'       => $paged,
				'pages'       => $pages,
			),
			$this->page_urls(
				array(
					'tab'    => 'blocking',
					's'      => $search,
					'source' => $source,
				),
				$paged,
				$pages
			)
		);
	}

	/**
	 * Settings tab: brute force protection, passwords, hardening and the allowlist.
	 *
	 * @return array<string,mixed>
	 */
	private function settings_args(): array {
		$args = $this->form_args();

		return array_merge(
			$args,
			array(
				'counts'    => array_combine( Settings::count_choices(), array_map( 'strval', Settings::count_choices() ) ),
				'windows'   => Settings::window_choices(),
				'durations' => Settings::duration_choices(),
				'strong'    => Settings::strong_choices(),
				'leaked'    => Settings::leaked_choices(),
				'existing'  => array_values( array_filter( (array) $args['settings']['blocked_usernames'], 'username_exists' ) ),
				'my_ip'     => Store::normalize_ip( Http::client_ip() ),
			)
		);
	}

	/**
	 * Activity tab: the filterable activity log.
	 *
	 * @return array<string,mixed>
	 */
	private function activity_args(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List filters.
		$event  = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$labels   = Activity::labels();
		$event    = isset( $labels[ $event ] ) ? $event : '';
		$result   = Activity::query( $event, $search, $paged );
		$pages    = max( 1, (int) ceil( $result['total'] / Activity::PER_PAGE ) );
		$settings = Settings::get();

		return array_merge(
			array(
				'enabled'     => ! empty( $settings['activity_enabled'] ),
				'days'        => (int) $settings['activity_retention_days'],
				'page'        => self::PAGE,
				'search'      => $search,
				'event'       => $event,
				'labels'      => $labels,
				'result'      => $result,
				'date_format' => Mail::date_format(),
				'paged'       => $paged,
				'pages'       => $pages,
			),
			$this->page_urls(
				array(
					'tab'   => 'activity',
					'event' => $event,
					's'     => $search,
				),
				$paged,
				$pages
			)
		);
	}

	/**
	 * Two-Factor tab: mode per role, grace period and trusted devices.
	 *
	 * @return array<string,mixed>
	 */
	private function two_factor_args(): array {
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

		return array_merge(
			$this->form_args(),
			array(
				'modes'    => Settings::two_factor_modes(),
				'roles'    => Settings::roles(),
				'days'     => $days,
				'remember' => $remember,
			)
		);
	}

	/**
	 * Captcha tab: provider, keys, threshold, score chart and test mode.
	 *
	 * @return array<string,mixed>
	 */
	private function captcha_args(): array {
		$args = $this->form_args();

		return array_merge(
			$args,
			array(
				'has_secret' => '' !== (string) $args['settings']['captcha_secret'] || defined( 'DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET' ),
				'reset_url'  => wp_nonce_url( admin_url( 'admin-post.php?action=dovetail_login_guard_reset_scores' ), 'dovetail_login_guard_reset_scores' ),
				'providers'  => Settings::captcha_providers(),
				'thresholds' => Settings::threshold_choices(),
				'scores'     => Captcha::scores(),
			)
		);
	}

	/**
	 * Alerts tab: alert emails, summary and the activity log.
	 *
	 * @return array<string,mixed>
	 */
	private function alerts_args(): array {
		$caps  = array();
		$every = array();
		$keep  = array();

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

		return array_merge(
			$this->form_args(),
			array(
				'caps'  => $caps,
				'every' => $every,
				'keep'  => $keep,
			)
		);
	}
}
