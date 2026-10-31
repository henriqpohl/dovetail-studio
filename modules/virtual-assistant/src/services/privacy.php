<?php

/**
 * Personal data in enquiries: WordPress export and erase tools (Tools > Export/Erase Personal Data),
 * a retention period, attachment clean-up and the suggested privacy policy text.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Months an enquiry is kept before it is deleted with its attachment. 0 keeps enquiries until they are deleted by hand. */
function dt_va_retention_months() {
	if ( defined( 'DT_VA_RETENTION_MONTHS' ) ) {
		return max( 0, (int) DT_VA_RETENTION_MONTHS );
	}

	return max( 0, (int) get_option( 'dt_va_retention_months', 24 ) );
}

/** Enquiry IDs sent with an email address, newest first. */
function dt_va_enquiries_by_email( $email, $page = 1, $per_page = 50 ) {
	$email = sanitize_email( (string) $email );

	if ( '' === $email ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => 'dt_enquiry',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'posts_per_page' => $per_page,
			'paged'          => max( 1, (int) $page ),
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'   => 'email',
					'value' => $email,
				),
			),
		)
	);
}

/** Exporter: every enquiry the address sent, with what the visitor gave. */
function dt_va_privacy_export( $email, $page = 1 ) {
	$ids    = dt_va_enquiries_by_email( $email, $page );
	$fields = array(
		'ref'           => __( 'Reference', 'dovetail-studio' ),
		'name'          => __( 'Name', 'dovetail-studio' ),
		'email'         => __( 'Email', 'dovetail-studio' ),
		'phone'         => __( 'Phone', 'dovetail-studio' ),
		'company'       => __( 'Company', 'dovetail-studio' ),
		'link'          => __( 'Link', 'dovetail-studio' ),
		'time'          => __( 'Best time', 'dovetail-studio' ),
		'page'          => __( 'Page', 'dovetail-studio' ),
		'ip'            => __( 'IP address', 'dovetail-studio' ),
		'file_original' => __( 'File', 'dovetail-studio' ),
	);
	$items  = array();

	foreach ( $ids as $id ) {
		$data = array(
			array(
				'name'  => __( 'Date', 'dovetail-studio' ),
				'value' => get_the_date( '', $id ) . ' ' . get_the_time( '', $id ),
			),
		);

		foreach ( $fields as $key => $label ) {
			$value = (string) get_post_meta( $id, $key, true );

			if ( '' !== $value ) {
				$data[] = array(
					'name'  => $label,
					'value' => $value,
				);
			}
		}

		$data[]  = array(
			'name'  => __( 'Message', 'dovetail-studio' ),
			'value' => (string) get_post_field( 'post_content', $id ),
		);
		$items[] = array(
			'group_id'    => 'dt-va-enquiries',
			'group_label' => __( 'AI Assistant enquiries', 'dovetail-studio' ),
			'item_id'     => 'dt-va-enquiry-' . $id,
			'data'        => $data,
		);
	}

	return array(
		'data' => $items,
		'done' => count( $ids ) < 50,
	);
}

/** Eraser: deletes the enquiries (and their attachments) sent with the address. */
function dt_va_privacy_erase( $email ) {
	// WordPress passes a page number too; always read page 1, since each pass deletes what the previous one found.
	$ids = dt_va_enquiries_by_email( $email, 1 );

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}

	return array(
		'items_removed'  => count( $ids ),
		'items_retained' => false,
		'messages'       => array(),
		'done'           => count( $ids ) < 50,
	);
}

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['dt-va-enquiries'] = array(
			'exporter_friendly_name' => __( 'AI Assistant enquiries', 'dovetail-studio' ),
			'callback'               => 'dt_va_privacy_export',
		);

		return $exporters;
	}
);
add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['dt-va-enquiries'] = array(
			'eraser_friendly_name' => __( 'AI Assistant enquiries', 'dovetail-studio' ),
			'callback'             => 'dt_va_privacy_erase',
		);

		return $erasers;
	}
);

/* An enquiry's attachment goes with it, however it is deleted (admin, eraser, retention). */
add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( 'dt_enquiry' !== get_post_type( $post_id ) ) {
			return;
		}

		$path = dt_va_attachment_path( $post_id );

		if ( '' !== $path ) {
			@unlink( $path );
		}
	}
);

/** Daily: delete enquiries older than the retention period, 100 at a time. */
function dt_va_purge_old_enquiries() {
	$months = dt_va_retention_months();

	if ( $months <= 0 ) {
		return 0;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'dt_enquiry',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) ),
					'column' => 'post_date_gmt',
				),
			),
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}

	return count( $ids );
}

add_action( 'dt_va_daily', 'dt_va_purge_old_enquiries' );

add_action(
	'admin_init',
	function () {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$months = dt_va_retention_months();
		$text   = '<p>' . esc_html__( 'When you use the AI Assistant on this site, the questions you type are sent to our AI provider (OpenAI or Anthropic) to generate an answer; they are not stored on this site. If you send an enquiry, we keep the details you give (name, email, phone, company, your message, any file you attach), the conversation with the assistant, the page you were on and your IP address, to reply to you and to prevent abuse.', 'dovetail-studio' ) . '</p>'
		. '<p>' . ( $months > 0
			/* translators: %d: number of months. */
			? esc_html( sprintf( _n( 'Enquiries are deleted automatically %d month after they are sent.', 'Enquiries are deleted automatically %d months after they are sent.', $months, 'dovetail-studio' ), $months ) )
			: esc_html__( 'Enquiries are kept until we delete them.', 'dovetail-studio' ) )
		. ' '
			. esc_html__( 'You can ask us for a copy of this data or for it to be deleted.', 'dovetail-studio' )
			. '</p>';
		wp_add_privacy_policy_content( __( 'AI Assistant', 'dovetail-studio' ), wp_kses_post( $text ) );
	}
);

/** Settings block under Settings > Company. */
function dt_va_privacy_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$external = defined( 'DT_VA_RETENTION_MONTHS' );

	if ( ! $external && isset( $_POST['dt_va_privacy'] ) && check_admin_referer( 'dt_va_privacy' ) ) {
		update_option(
			'dt_va_retention_months',
			max( 0, min( 120, absint( $_POST['retention_months'] ?? 24 ) ) ),
			false
		);
		\DovetailStudio\Core\AdminScreens::notice(
			'success',
			esc_html__( 'Privacy settings saved.', 'dovetail-studio' )
		);
	}

	\DovetailStudio\Core\View::render(
		DOVETAIL_VIRTUAL_ASSISTANT_PATH,
		'partials/privacy-settings',
		array(
			'external' => $external,
			'months'   => dt_va_retention_months(),
		)
	);
}
