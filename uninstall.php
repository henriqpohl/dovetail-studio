<?php
/**
 * Dovetail Studio uninstall.
 *
 * Data is kept by default: deleting the plugin from the Plugins screen only
 * removes its files and scheduled events. To also delete every setting,
 * enquiry, statistic and Login Guard table, add this line to wp-config.php before deleting:
 *
 *     define( 'DOVETAIL_STUDIO_DELETE_DATA', true );
 *
 * Uploaded modules in wp-content/dovetail-modules and enquiry attachments in
 * wp-content/uploads/dt-va are never deleted automatically. On a multisite
 * network every site is cleaned.
 *
 * @package DovetailStudio
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Cron hooks declared by the module manifests ('cron_hooks'), bundled and uploaded.
$dovetail_studio_cron_hooks = static function () {
	$dirs  = array(
		__DIR__ . '/modules',
		defined( 'DOVETAIL_STUDIO_MODULES_DIR' )
			? (string) DOVETAIL_STUDIO_MODULES_DIR
			: WP_CONTENT_DIR . '/dovetail-modules',
	);
	$hooks = array();

	foreach ( $dirs as $dir ) {
		foreach ( (array) glob( rtrim( $dir, '/' ) . '/*/module.php' ) as $file ) {
			$manifest = include $file;

			if ( is_array( $manifest )
				&& ! empty( $manifest['cron_hooks'] )
				&& is_array( $manifest['cron_hooks'] )
			) {
				$hooks = array_merge( $hooks, array_map( 'strval', $manifest['cron_hooks'] ) );
			}
		}
	}

	return array_values( array_unique( $hooks ) );
};

// Removes the plugin's data from the current site.
$dovetail_studio_uninstall_site = static function ( array $cron_hooks ) {
	global $wpdb;

	// Scheduled events always go: their callbacks no longer exist.
	foreach ( $cron_hooks as $dovetail_hook ) {
		wp_clear_scheduled_hook( $dovetail_hook );
	}

	if ( ! defined( 'DOVETAIL_STUDIO_DELETE_DATA' ) || true !== DOVETAIL_STUDIO_DELETE_DATA ) {
		return;
	}

	// Enquiries (Virtual Assistant). wp_delete_post also removes their meta.
	$dovetail_enquiries = get_posts(
		array(
			'post_type'      => 'dt_enquiry',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	foreach ( $dovetail_enquiries as $dovetail_id ) {
		wp_delete_post( (int) $dovetail_id, true );
	}

	// Named options.
	foreach ( array( 'dovetail_studio_modules', 'dovetail_newsletter_settings', 'dovetail_schema_seo', 'dovetail_schema_seo_woocommerce_settings', 'dovetail_vulnerability_watch_settings', 'dovetail_vulnerability_watch_state', 'dovetail_vulnerability_watch_core_state', 'dovetail_vulnerability_watch_exposed_state', 'dovetail_vulnerability_watch_inventory', 'dovetail_vulnerability_watch_log', 'dovetail_vulnerability_watch_alert_quota', 'dovetail_hide_login_path_settings' ) as $dovetail_option ) {
		delete_option( $dovetail_option );
	}

	// Prefixed options and transients (Virtual Assistant settings and stats, Login Guard settings, rate-limit counters, caches).
	$dovetail_like = array(
		$wpdb->esc_like( 'dt_va_' ) . '%',
		$wpdb->esc_like( 'dovetail_rl_' ) . '%',
		$wpdb->esc_like( 'dovetail_login_guard_' ) . '%',
		$wpdb->esc_like( '_transient_dt_va_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_dt_va_' ) . '%',
		$wpdb->esc_like( '_transient_dovetail_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_dovetail_' ) . '%',
	);

	foreach ( $dovetail_like as $dovetail_pattern ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $dovetail_pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off uninstall cleanup.
	}

	// Login Guard two-factor secrets, recovery codes, trusted devices and pending verifications.
	if ( ! empty( $wpdb->usermeta ) ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'dovetail_lg_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off uninstall cleanup.
	}

	// Login Guard tables (blocked IP addresses, failed sign-ins and the activity log).
	foreach ( array( 'dovetail_login_guard_blocks', 'dovetail_login_guard_failures', 'dovetail_login_guard_activity' ) as $dovetail_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$dovetail_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off uninstall cleanup; table names come from the fixed list above.
	}
};

$dovetail_cron_hooks = $dovetail_studio_cron_hooks();

// On a network, every site has its own options and enquiries.
if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $dovetail_site ) {
		switch_to_blog( (int) $dovetail_site );
		$dovetail_studio_uninstall_site( $dovetail_cron_hooks );
		restore_current_blog();
	}
} else {
	$dovetail_studio_uninstall_site( $dovetail_cron_hooks );
}
