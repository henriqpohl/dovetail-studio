<?php
/**
 * WordPress admin settings screen.
 *
 * @package LocalBusinessSchema
 */

namespace DovetailStudio\SchemaSeo;

defined( 'ABSPATH' ) || exit;

final class Admin {

	/** Settings page hook suffix. */
	private $page_hook = '';

	/** Register admin hooks. */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_init', array( $this, 'suppress_admin_notices' ), PHP_INT_MAX );
		add_action( 'admin_head', array( $this, 'suppress_admin_notices' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_lbs_schema_preview', array( $this, 'ajax_schema_preview' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/** Add Schema SEO under Dovetail Studio. */
	public function add_settings_page() {
		$this->page_hook = add_submenu_page(
			'dovetail-studio',
			__( 'Schema SEO', 'local-business-schema' ),
			__( 'Schema SEO', 'local-business-schema' ),
			'manage_options',
			'local-business-schema',
			array( $this, 'render_page' )
		);
	}

	/** Remove WordPress and third-party notices only from this plugin screen. */
	public function suppress_admin_notices() {
		if ( ! $this->is_settings_page() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/** Add a page-specific body class for notices injected outside standard hooks. */
	public function admin_body_class( $classes ) {
		return $this->is_settings_page() ? trim( $classes . ' dovetail-admin-page' ) : $classes;
	}

	/** Whether the current request is the Local Business Schema settings page. */
	private function is_settings_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
		if ( 'local-business-schema' === $page ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && $this->page_hook && $this->page_hook === $screen->id;
	}

	/** Register the single structured option. */
	public function register_setting() {
		register_setting(
			'lbs_settings_group',
			Schema::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => Schema::defaults(),
			)
		);
	}

	/** Load media picker and page-only assets. */
	public function enqueue_assets( $hook_suffix ) {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'lbs-admin', LBS_URL . 'assets/js/admin.js', array( 'jquery' ), LBS_VERSION, true );
		wp_localize_script(
			'lbs-admin',
			'lbsAdmin',
			array(
				'mediaTitle'          => __( 'Select image', 'local-business-schema' ),
				'mediaButton'         => __( 'Use this image', 'local-business-schema' ),
				'previewUrl'          => home_url( '/' ),
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'previewNonce'        => wp_create_nonce( 'lbs_schema_preview' ),
				'previewLoading'      => __( 'Loading JSON-LD from the current homepage…', 'local-business-schema' ),
				'previewReady'        => __( 'Current JSON-LD loaded and formatted.', 'local-business-schema' ),
				'previewError'        => __( 'Could not find valid Local Business or Yoast JSON-LD on the homepage.', 'local-business-schema' ),
				'previewCopied'       => __( 'JSON copied to the clipboard.', 'local-business-schema' ),
				'previewCopyError'    => __( 'Could not copy automatically. Select the JSON and copy it manually.', 'local-business-schema' ),
				'previewUnsaved'      => __( 'Save your changes before refreshing the JSON preview.', 'local-business-schema' ),
			)
		);
	}

	/** Return the current public JSON-LD when a direct browser request is unavailable. */
	public function ajax_schema_preview() {
		check_ajax_referer( 'lbs_schema_preview', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to view this preview.', 'local-business-schema' ) ), 403 );
		}

		$preview_url = add_query_arg( '_lbs_schema_preview', time(), home_url( '/' ) );
		$response    = wp_safe_remote_get(
			$preview_url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'The homepage could not be loaded for the JSON-LD preview.', 'local-business-schema' ) ), 502 );
		}

		$json = $this->extract_schema_json( wp_remote_retrieve_body( $response ) );
		if ( '' === $json ) {
			wp_send_json_error( array( 'message' => __( 'No valid Local Business or Yoast JSON-LD was found on the homepage.', 'local-business-schema' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'json' => $json,
				'url'  => home_url( '/' ),
			)
		);
	}

	/** Extract and format the preferred Schema script from a frontend HTML document. */
	private function extract_schema_json( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}

		preg_match_all( '~<script\\b([^>]*)>(.*?)</script\\s*>~is', $html, $matches, PREG_SET_ORDER );
		$best       = array();
		$best_score = 0;

		foreach ( $matches as $match ) {
			$attributes = $match[1];
			if ( ! preg_match( '~\\btype\\s*=\\s*(["\\\'])application/ld\\+json\\1~i', $attributes ) ) {
				continue;
			}

			$body    = trim( $match[2] );
			$decoded = json_decode( $body, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				$decoded = json_decode( html_entity_decode( $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
			}
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				continue;
			}

			$score = isset( $decoded['@graph'] ) ? 1 : 0;
			if ( preg_match( '~\\bclass\\s*=\\s*(["\\\'])[^"\\\']*local-business-schema[^"\\\']*\\1~i', $attributes ) ) {
				$score = 2;
			}
			if ( preg_match( '~\\bclass\\s*=\\s*(["\\\'])[^"\\\']*yoast-schema-graph[^"\\\']*\\1~i', $attributes ) ) {
				$score = 3;
			}

			if ( $score > $best_score ) {
				$best       = $decoded;
				$best_score = $score;
			}
		}

		Schema::normalize_json_float_precision();
		return $best ? wp_json_encode( $best, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
	}

	/** Add a plugin-list shortcut. */
	public function add_settings_link( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=local-business-schema' ) ) . '">' . esc_html__( 'Settings', 'local-business-schema' ) . '</a>'
		);
		return $links;
	}

	/** Sanitize every supported option and discard unknown keys. */
	public function sanitize_options( $input ) {
		$defaults = Schema::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$output['enabled']       = empty( $input['enabled'] ) ? 0 : 1;
		$output['display_scope'] = isset( $input['display_scope'] ) && 'homepage' === $input['display_scope'] ? 'homepage' : 'sitewide';
		$output['schema_type']   = $this->sanitize_schema_type( isset( $input['schema_type'] ) ? $input['schema_type'] : '' );
		$output['site_graph_enabled']     = empty( $input['site_graph_enabled'] ) ? 0 : 1;
		$output['breadcrumbs_enabled']    = empty( $input['breadcrumbs_enabled'] ) ? 0 : 1;
		$output['breadcrumbs_home_label'] = isset( $input['breadcrumbs_home_label'] ) ? sanitize_text_field( $input['breadcrumbs_home_label'] ) : '';

		$text_fields = array(
			'name', 'alternate_name', 'legal_name', 'telephone', 'price_range',
			'street_address', 'address_locality', 'address_region', 'postal_code',
			'address_country', 'vat_id', 'tax_id', 'slogan', 'parent_organization_name',
			'iso6523_code', 'global_location_number', 'lei_code', 'naics', 'duns',
			'payment_accepted', 'accepts_reservations',
		);
		foreach ( $text_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		}

		$output['description']   = isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '';
		$output['area_served']   = isset( $input['area_served'] ) ? sanitize_textarea_field( $input['area_served'] ) : '';
		$output['languages']     = isset( $input['languages'] ) ? sanitize_textarea_field( $input['languages'] ) : '';
		$output['identifiers']   = isset( $input['identifiers'] ) ? sanitize_textarea_field( $input['identifiers'] ) : '';
		$output['contact_points'] = isset( $input['contact_points'] ) ? sanitize_textarea_field( $input['contact_points'] ) : '';
		$output['special_hours'] = isset( $input['special_hours'] ) ? sanitize_textarea_field( $input['special_hours'] ) : '';
		$output['services']      = isset( $input['services'] ) ? sanitize_textarea_field( $input['services'] ) : '';
		$output['locations']     = isset( $input['locations'] ) ? sanitize_textarea_field( $input['locations'] ) : '';
		$output['location_hours'] = isset( $input['location_hours'] ) ? sanitize_textarea_field( $input['location_hours'] ) : '';
		$output['founders']      = isset( $input['founders'] ) ? sanitize_textarea_field( $input['founders'] ) : '';
		$output['serves_cuisine'] = isset( $input['serves_cuisine'] ) ? sanitize_textarea_field( $input['serves_cuisine'] ) : '';
		$output['amenities']     = isset( $input['amenities'] ) ? sanitize_textarea_field( $input['amenities'] ) : '';
		$output['medical_specialties'] = isset( $input['medical_specialties'] ) ? sanitize_textarea_field( $input['medical_specialties'] ) : '';
		$output['email']         = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		$output['founding_date'] = isset( $input['founding_date'] ) ? $this->sanitize_date( $input['founding_date'] ) : '';
		$output['number_of_employees'] = isset( $input['number_of_employees'] ) && '' !== $input['number_of_employees'] ? (string) absint( $input['number_of_employees'] ) : '';
		$output['star_rating'] = $this->sanitize_rating( isset( $input['star_rating'] ) ? $input['star_rating'] : '' );
		$output['checkin_time'] = $this->sanitize_time( isset( $input['checkin_time'] ) ? $input['checkin_time'] : '' );
		$output['checkout_time'] = $this->sanitize_time( isset( $input['checkout_time'] ) ? $input['checkout_time'] : '' );

		foreach ( array( 'url', 'logo', 'image', 'has_map', 'menu', 'parent_organization_url' ) as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? esc_url_raw( $input[ $field ] ) : '';
		}

		$output['same_as']  = implode( "\n", Schema::parse_url_list( isset( $input['same_as'] ) ? $input['same_as'] : '' ) );
		$output['languages'] = implode( ', ', Schema::parse_languages( isset( $input['languages'] ) ? $input['languages'] : '' ) );
		$output['currencies_accepted'] = implode( ', ', Schema::parse_currency_list( isset( $input['currencies_accepted'] ) ? $input['currencies_accepted'] : '' ) );
		$output['address_country'] = preg_match( '/^[A-Za-z]{2}$/', $output['address_country'] ) ? strtoupper( $output['address_country'] ) : '';
		$output['latitude'] = $this->sanitize_coordinate( isset( $input['latitude'] ) ? $input['latitude'] : '', -90, 90 );
		$output['longitude'] = $this->sanitize_coordinate( isset( $input['longitude'] ) ? $input['longitude'] : '', -180, 180 );

		$input_sources = isset( $input['sources'] ) && is_array( $input['sources'] ) ? $input['sources'] : array();
		foreach ( Schema::SOURCE_FIELDS as $field ) {
			$output['sources'][ $field ] = isset( $input_sources[ $field ] ) && 'plugin' === $input_sources[ $field ] ? 'plugin' : 'automatic';
		}

		$input_hours = isset( $input['opening_hours'] ) && is_array( $input['opening_hours'] ) ? $input['opening_hours'] : array();
		foreach ( Schema::days() as $day => $label ) {
			$row = isset( $input_hours[ $day ] ) && is_array( $input_hours[ $day ] ) ? $input_hours[ $day ] : array();
			$output['opening_hours'][ $day ] = array(
				'enabled' => empty( $row['enabled'] ) ? 0 : 1,
				'opens'   => $this->sanitize_time( isset( $row['opens'] ) ? $row['opens'] : '' ),
				'closes'  => $this->sanitize_time( isset( $row['closes'] ) ? $row['closes'] : '' ),
				'opens_2' => $this->sanitize_time( isset( $row['opens_2'] ) ? $row['opens_2'] : '' ),
				'closes_2' => $this->sanitize_time( isset( $row['closes_2'] ) ? $row['closes_2'] : '' ),
			);
		}

		return $output;
	}

	/** Render settings page. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = Schema::get_options();
		$yoast   = $this->get_recognized_yoast_values();
		$yoast_is_person = $this->yoast_represents_person();
		?>
		<div class="wrap dovetail-wrap">
			<div class="dovetail-page-header">
				<div>
					<h1><?php esc_html_e( 'Local Business Schema', 'local-business-schema' ); ?></h1>
					<p><?php esc_html_e( 'Manage business structured data and its integration with Yoast SEO.', 'local-business-schema' ); ?></p>
				</div>
				<span class="dovetail-version"><?php echo esc_html( 'v' . LBS_VERSION ); ?></span>
			</div>

			<div class="dovetail-status <?php echo $yoast_is_person ? 'is-warning' : ( Schema::is_yoast_active() ? 'is-active' : 'is-native' ); ?>">
				<span class="dovetail-status-dot" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Integration status', 'local-business-schema' ); ?></strong>
					<p>
						<?php if ( $yoast_is_person ) : ?>
							<?php esc_html_e( 'Yoast SEO represents this site as a Person. The Person will be preserved and a separate business entity will be added to the same graph.', 'local-business-schema' ); ?>
						<?php elseif ( Schema::is_yoast_active() ) : ?>
							<?php esc_html_e( 'Yoast SEO detected. The existing Organization entity will be enriched; no second entity is printed.', 'local-business-schema' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Yoast SEO not detected. The plugin prints a single JSON-LD graph in wp_head with the business entity, the site graph and the breadcrumb trail.', 'local-business-schema' ); ?>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'local-business-schema' ); ?>">
				<a href="#dovetail-general"><?php esc_html_e( 'General', 'local-business-schema' ); ?></a>
				<a href="#dovetail-contact"><?php esc_html_e( 'Contact', 'local-business-schema' ); ?></a>
				<a href="#dovetail-address"><?php esc_html_e( 'Address', 'local-business-schema' ); ?></a>
				<a href="#dovetail-business"><?php esc_html_e( 'Business', 'local-business-schema' ); ?></a>
				<a href="#dovetail-type-specific"><?php esc_html_e( 'Type-specific', 'local-business-schema' ); ?></a>
				<a href="#dovetail-profiles"><?php esc_html_e( 'Profiles', 'local-business-schema' ); ?></a>
				<a href="#dovetail-identifiers"><?php esc_html_e( 'Identifiers', 'local-business-schema' ); ?></a>
				<a href="#dovetail-site-graph"><?php esc_html_e( 'Site Graph & Breadcrumbs', 'local-business-schema' ); ?></a>
				<a href="#dovetail-json-preview"><?php esc_html_e( 'JSON-LD Preview', 'local-business-schema' ); ?></a>
				<a href="#dovetail-integration"><?php esc_html_e( 'Integration', 'local-business-schema' ); ?></a>
			</nav>

			<form action="options.php" method="post">
				<?php settings_fields( 'lbs_settings_group' ); ?>

				<?php $this->section_start( __( 'General', 'local-business-schema' ), __( 'Identity, business type, and output rules.', 'local-business-schema' ), 'general' ); ?>
				<?php $this->checkbox( 'enabled', __( 'Enable integration', 'local-business-schema' ), $options['enabled'], __( 'Generate Schema or enrich Yoast SEO output.', 'local-business-schema' ) ); ?>
				<?php $this->select( 'display_scope', __( 'Display scope', 'local-business-schema' ), $options['display_scope'], array( 'sitewide' => __( 'Entire site', 'local-business-schema' ), 'homepage' => __( 'Homepage only', 'local-business-schema' ) ) ); ?>
				<?php $this->select( 'schema_type', __( 'Business type', 'local-business-schema' ), $options['schema_type'], self::schema_types() ); ?>
				<?php $this->text( 'name', __( 'Business name', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'alternate_name', __( 'Alternate name', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'legal_name', __( 'Legal name', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'slogan', __( 'Slogan', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'number_of_employees', __( 'Number of employees', 'local-business-schema' ), $options, $yoast, true, 'number' ); ?>
				<?php $this->textarea( 'description', __( 'Description', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->basic_input( 'founding_date', __( 'Founding date', 'local-business-schema' ), $options['founding_date'], 'date' ); ?>
				<?php $this->basic_textarea( 'founders', __( 'Founders', 'local-business-schema' ), $options['founders'], __( 'One per line as Name|URL. URL is optional.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'parent_organization_name', __( 'Parent organization name', 'local-business-schema' ), $options['parent_organization_name'] ); ?>
				<?php $this->basic_input( 'parent_organization_url', __( 'Parent organization URL', 'local-business-schema' ), $options['parent_organization_url'], 'url' ); ?>
				<?php $this->text( 'url', __( 'Website URL', 'local-business-schema' ), $options, $yoast, true, 'url', __( 'Defaults to the WordPress home URL.', 'local-business-schema' ) ); ?>
				<?php $this->media_field( 'logo', __( 'Logo', 'local-business-schema' ), $options, $yoast ); ?>
				<?php $this->media_field( 'image', __( 'Business image', 'local-business-schema' ), $options, $yoast ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Contact', 'local-business-schema' ), '', 'contact' ); ?>
				<?php $this->text( 'telephone', __( 'Telephone', 'local-business-schema' ), $options, $yoast, true, 'tel' ); ?>
				<?php $this->text( 'email', __( 'Email', 'local-business-schema' ), $options, $yoast, true, 'email' ); ?>
				<?php $this->textarea( 'contact_points', __( 'Contact points', 'local-business-schema' ), $options, $yoast, true, __( 'One per line: Type|Telephone|Email|Languages|Areas. Example: customer service|+353...|help@example.ie|en,pt-BR|Ireland.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Address', 'local-business-schema' ), __( 'Address and coordinates come from this plugin.', 'local-business-schema' ), 'address' ); ?>
				<?php $this->basic_input( 'street_address', __( 'Street address', 'local-business-schema' ), $options['street_address'] ); ?>
				<?php $this->basic_input( 'address_locality', __( 'City / locality', 'local-business-schema' ), $options['address_locality'] ); ?>
				<?php $this->basic_input( 'address_region', __( 'Region / county', 'local-business-schema' ), $options['address_region'] ); ?>
				<?php $this->basic_input( 'postal_code', __( 'Postal code', 'local-business-schema' ), $options['postal_code'] ); ?>
				<?php $this->basic_input( 'address_country', __( 'Country code', 'local-business-schema' ), $options['address_country'], 'text', __( 'Two-letter ISO code, for example IE.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'latitude', __( 'Latitude', 'local-business-schema' ), $options['latitude'], 'number', '', 'any' ); ?>
				<?php $this->basic_input( 'longitude', __( 'Longitude', 'local-business-schema' ), $options['longitude'], 'number', '', 'any' ); ?>
				<?php $this->basic_input( 'has_map', __( 'Map URL', 'local-business-schema' ), $options['has_map'], 'url', __( 'Public Google Maps or other map URL.', 'local-business-schema' ) ); ?>
				<?php $this->basic_textarea( 'locations', __( 'Additional locations', 'local-business-schema' ), $options['locations'], __( 'One per line: Name|Street|City|Region|Postal code|Country|Latitude|Longitude|Telephone|URL.', 'local-business-schema' ) ); ?>
				<?php $this->basic_textarea( 'location_hours', __( 'Additional location hours', 'local-business-schema' ), $options['location_hours'], __( 'One per line: Exact location name|Days|Opens|Closes. Example: Dublin Office|Monday,Tuesday|09:00|17:00.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Business', 'local-business-schema' ), '', 'business' ); ?>
				<?php $this->basic_input( 'price_range', __( 'Price range', 'local-business-schema' ), $options['price_range'], 'text', __( 'Examples: €€, $20–$100.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'currencies_accepted', __( 'Currencies accepted', 'local-business-schema' ), $options['currencies_accepted'], 'text', __( 'ISO 4217 codes separated by commas, for example EUR, GBP.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'payment_accepted', __( 'Payment methods', 'local-business-schema' ), $options['payment_accepted'], 'text', __( 'Comma-separated, for example Cash, Credit Card, Bank Transfer.', 'local-business-schema' ) ); ?>
				<?php $this->basic_textarea( 'area_served', __( 'Areas served', 'local-business-schema' ), $options['area_served'], __( 'One per line. Use Type|Name, for example Country|Ireland or AdministrativeArea|County Louth.', 'local-business-schema' ) ); ?>
				<?php $this->basic_textarea( 'languages', __( 'Languages', 'local-business-schema' ), $options['languages'], __( 'Comma-separated BCP 47 codes, for example en, pt-BR, lt.', 'local-business-schema' ) ); ?>
				<?php $this->basic_textarea( 'services', __( 'Services', 'local-business-schema' ), $options['services'], __( 'One per line as Name|URL|Description. URL and description are optional.', 'local-business-schema' ) ); ?>
				<?php $this->opening_hours( $options['opening_hours'] ); ?>
				<?php $this->basic_textarea( 'special_hours', __( 'Special opening hours', 'local-business-schema' ), $options['special_hours'], __( 'One per line: Start date|End date|Opens|Closes. Use 00:00|00:00 for closed dates.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Type-specific fields', 'local-business-schema' ), __( 'Only properties compatible with the selected business type are emitted.', 'local-business-schema' ), 'type-specific' ); ?>
				<?php $this->basic_textarea( 'serves_cuisine', __( 'Cuisine types', 'local-business-schema' ), $options['serves_cuisine'], __( 'Restaurant, cafe, bar, or bakery only. Comma-separated.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'menu', __( 'Menu URL', 'local-business-schema' ), $options['menu'], 'url', __( 'Food establishments only.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'accepts_reservations', __( 'Accepts reservations', 'local-business-schema' ), $options['accepts_reservations'], 'text', __( 'Food establishments only. Use true, false, or a reservation URL.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'checkin_time', __( 'Check-in time', 'local-business-schema' ), $options['checkin_time'], 'time', __( 'Hotel only.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'checkout_time', __( 'Check-out time', 'local-business-schema' ), $options['checkout_time'], 'time', __( 'Hotel only.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'star_rating', __( 'Star rating', 'local-business-schema' ), $options['star_rating'], 'number', __( 'Hotel only. Value from 1 to 5.', 'local-business-schema' ), '0.1' ); ?>
				<?php $this->basic_textarea( 'amenities', __( 'Amenities', 'local-business-schema' ), $options['amenities'], __( 'Hotel only. Comma-separated.', 'local-business-schema' ) ); ?>
				<?php $this->basic_textarea( 'medical_specialties', __( 'Medical specialties', 'local-business-schema' ), $options['medical_specialties'], __( 'MedicalClinic or Dentist only. Comma-separated Schema.org specialty names.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Profiles', 'local-business-schema' ), '', 'profiles' ); ?>
				<?php $this->textarea( 'same_as', __( 'Social profiles', 'local-business-schema' ), $options, $yoast, true, __( 'One full URL per line. In Schema.org, sameAs refers to an external URL that unequivocally identifies the same entity. Official examples include Wikipedia, Wikidata, and an official website. Yoast also describes these as URLs for social media profiles or other authoritative profiles of the organization.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Identifiers', 'local-business-schema' ), '', 'identifiers' ); ?>
				<?php $this->text( 'vat_id', __( 'VAT ID', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'tax_id', __( 'Tax ID', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'iso6523_code', __( 'ISO 6523 code', 'local-business-schema' ), $options, $yoast, true, 'text', __( 'Example: 0199:LEI-CODE.', 'local-business-schema' ) ); ?>
				<?php $this->text( 'global_location_number', __( 'Global Location Number', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'lei_code', __( 'LEI code', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'naics', __( 'NAICS', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->text( 'duns', __( 'DUNS', 'local-business-schema' ), $options, $yoast, true ); ?>
				<?php $this->basic_textarea( 'identifiers', __( 'Other identifiers', 'local-business-schema' ), $options['identifiers'], __( 'One per line as Name|Value, for example CRO|123456.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'JSON-LD Preview', 'local-business-schema' ), __( 'Load the JSON-LD currently published on the homepage, including the Yoast graph enriched by this plugin.', 'local-business-schema' ), 'json-preview' ); ?>
				<?php $this->json_preview(); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Site Graph & Breadcrumbs', 'local-business-schema' ), __( 'With Yoast SEO active, Yoast provides WebSite, WebPage, Article and BreadcrumbList and the breadcrumb trail follows the Yoast breadcrumb settings. Without Yoast SEO, this module adds them to a single JSON-LD graph together with the business entity.', 'local-business-schema' ), 'site-graph' ); ?>
				<?php $this->checkbox( 'site_graph_enabled', __( 'Site graph', 'local-business-schema' ), $options['site_graph_enabled'], __( 'Add WebSite, WebPage, Article and author entities when Yoast SEO is not providing the graph.', 'local-business-schema' ) ); ?>
				<?php $this->checkbox( 'breadcrumbs_enabled', __( 'Breadcrumbs', 'local-business-schema' ), $options['breadcrumbs_enabled'], __( 'Provide the breadcrumb trail to the theme through dovetail_studio_get_breadcrumbs() and add BreadcrumbList when Yoast SEO is not providing the graph.', 'local-business-schema' ) ); ?>
				<?php $this->basic_input( 'breadcrumbs_home_label', __( 'Home label', 'local-business-schema' ), $options['breadcrumbs_home_label'], 'text', __( 'First breadcrumb text when the plugin builds the trail. Leave empty to use "Home". With Yoast SEO, set it in the Yoast breadcrumb settings instead.', 'local-business-schema' ) ); ?>
				<?php $this->section_end(); ?>

				<?php $this->section_start( __( 'Integration', 'local-business-schema' ), __( 'Automatic keeps an existing Yoast property and uses the module value only when that property is missing. Use plugin value explicitly replaces that property; a blank plugin value omits it. Fields without a source selector are controlled directly by this plugin.', 'local-business-schema' ), 'integration' ); ?>
				<?php $this->integration_summary( $options, $yoast ); ?>
				<?php $this->section_end(); ?>

				<div class="dovetail-save-bar">
					<?php submit_button(); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/** Section wrapper. */
	private function section_start( $title, $description = '', $id = '' ) {
		$section_id = '' !== $id ? ' id="dovetail-' . esc_attr( $id ) . '"' : '';
		echo '<section class="dovetail-card"' . $section_id . '><div class="dovetail-card-header"><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</div><table class="form-table" role="presentation"><tbody>';
	}

	/** Close section wrapper. */
	private function section_end() {
		echo '</tbody></table></section>';
	}

	/** Current frontend JSON-LD preview and copy controls. */
	private function json_preview() {
		echo '<tr class="dovetail-json-row"><td colspan="2">';
		echo '<div class="dovetail-json-actions">';
		echo '<button class="button button-secondary" id="dovetail-load-json" type="button">' . esc_html__( 'Load current JSON', 'local-business-schema' ) . '</button>';
		echo '<button class="button" id="dovetail-copy-json" type="button" disabled>' . esc_html__( 'Copy JSON', 'local-business-schema' ) . '</button>';
		echo '<span class="dovetail-json-status" id="dovetail-json-status" role="status" aria-live="polite"></span>';
		echo '</div>';
		echo '<textarea class="large-text code dovetail-json-preview" id="dovetail-json-output" rows="20" readonly spellcheck="false" placeholder="' . esc_attr__( 'Click “Load current JSON” to retrieve the published Schema.', 'local-business-schema' ) . '"></textarea>';
		echo '<p class="description">' . esc_html__( 'This is the public homepage output, not a simulation. Save pending changes first. With Yoast active, the complete enriched @graph is shown; without Yoast, the module’s native JSON-LD is shown.', 'local-business-schema' ) . '</p>';
		echo '</td></tr>';
	}

	/** Basic input row. */
	private function basic_input( $field, $label, $value, $type = 'text', $help = '', $step = '' ) {
		$name = Schema::OPTION_NAME . '[' . $field . ']';
		echo '<tr><th scope="row"><label for="dovetail-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input class="regular-text" id="dovetail-' . esc_attr( $field ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"';
		if ( '' !== $step ) {
			echo ' step="' . esc_attr( $step ) . '"';
		}
		echo '>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/** Sourced single-line field. */
	private function text( $field, $label, array $options, array $yoast, $with_source = false, $type = 'text', $help = '' ) {
		$this->basic_sourced_row_start( $field, $label );
		echo '<input class="regular-text" id="dovetail-' . esc_attr( $field ) . '" name="' . esc_attr( Schema::OPTION_NAME . '[' . $field . ']' ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $options[ $field ] ) . '">';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		if ( $with_source ) {
			$this->source_control( $field, $options, $yoast );
		}
		echo '</td></tr>';
	}

	/** Sourced textarea field. */
	private function textarea( $field, $label, array $options, array $yoast, $with_source = false, $help = '' ) {
		$this->basic_sourced_row_start( $field, $label );
		echo '<textarea class="large-text" rows="4" id="dovetail-' . esc_attr( $field ) . '" name="' . esc_attr( Schema::OPTION_NAME . '[' . $field . ']' ) . '">' . esc_textarea( $options[ $field ] ) . '</textarea>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		if ( $with_source ) {
			$this->source_control( $field, $options, $yoast );
		}
		echo '</td></tr>';
	}

	/** Basic textarea without source selector. */
	private function basic_textarea( $field, $label, $value, $help = '' ) {
		$this->basic_sourced_row_start( $field, $label );
		echo '<textarea class="large-text" rows="4" id="dovetail-' . esc_attr( $field ) . '" name="' . esc_attr( Schema::OPTION_NAME . '[' . $field . ']' ) . '">' . esc_textarea( $value ) . '</textarea>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/** Media URL with WordPress picker. */
	private function media_field( $field, $label, array $options, array $yoast ) {
		$this->basic_sourced_row_start( $field, $label );
		echo '<div class="dovetail-media"><input class="regular-text" id="dovetail-' . esc_attr( $field ) . '" name="' . esc_attr( Schema::OPTION_NAME . '[' . $field . ']' ) . '" type="url" value="' . esc_attr( $options[ $field ] ) . '"> ';
		echo '<button class="button dovetail-select-media" data-target="dovetail-' . esc_attr( $field ) . '" type="button">' . esc_html__( 'Select image', 'local-business-schema' ) . '</button></div>';
		$this->source_control( $field, $options, $yoast );
		echo '</td></tr>';
	}

	/** Checkbox row. */
	private function checkbox( $field, $label, $value, $help = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label><input name="' . esc_attr( Schema::OPTION_NAME . '[' . $field . ']' ) . '" type="checkbox" value="1" ' . checked( 1, $value, false ) . '> ' . esc_html( $help ) . '</label>';
		echo '</td></tr>';
	}

	/** Select row. */
	private function select( $field, $label, $value, array $choices ) {
		echo '<tr><th scope="row"><label for="dovetail-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="dovetail-' . esc_attr( $field ) . '" name="' . esc_attr( Schema::OPTION_NAME . '[' . $field . ']' ) . '">';
		foreach ( $choices as $choice_value => $choice_label ) {
			echo '<option value="' . esc_attr( $choice_value ) . '" ' . selected( $value, $choice_value, false ) . '>' . esc_html( $choice_label ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/** Opening hours matrix. */
	private function opening_hours( array $hours ) {
		echo '<tr><th scope="row">' . esc_html__( 'Opening hours', 'local-business-schema' ) . '</th><td><div class="dovetail-hours">';
		foreach ( Schema::days() as $day => $label ) {
			$row  = isset( $hours[ $day ] ) ? $hours[ $day ] : array( 'enabled' => 0, 'opens' => '09:00', 'closes' => '17:00', 'opens_2' => '', 'closes_2' => '' );
			$base = Schema::OPTION_NAME . '[opening_hours][' . $day . ']';
			echo '<div class="dovetail-hours-row">';
			echo '<label class="dovetail-day"><input type="checkbox" name="' . esc_attr( $base . '[enabled]' ) . '" value="1" ' . checked( 1, $row['enabled'], false ) . '> ' . esc_html( $label ) . '</label>';
			echo '<div class="dovetail-time-period">';
			echo '<span class="dovetail-hours-period">' . esc_html__( 'Period 1', 'local-business-schema' ) . '</span>';
			echo '<label><span class="screen-reader-text">' . esc_html__( 'Opens', 'local-business-schema' ) . '</span><input type="time" name="' . esc_attr( $base . '[opens]' ) . '" value="' . esc_attr( $row['opens'] ) . '"></label>';
			echo '<span aria-hidden="true">–</span>';
			echo '<label><span class="screen-reader-text">' . esc_html__( 'Closes', 'local-business-schema' ) . '</span><input type="time" name="' . esc_attr( $base . '[closes]' ) . '" value="' . esc_attr( $row['closes'] ) . '"></label>';
			echo '</div><div class="dovetail-time-period">';
			echo '<span class="dovetail-hours-period">' . esc_html__( 'Period 2', 'local-business-schema' ) . '</span>';
			echo '<label><span class="screen-reader-text">' . esc_html__( 'Second opening time', 'local-business-schema' ) . '</span><input type="time" name="' . esc_attr( $base . '[opens_2]' ) . '" value="' . esc_attr( $row['opens_2'] ) . '"></label>';
			echo '<span aria-hidden="true">–</span>';
			echo '<label><span class="screen-reader-text">' . esc_html__( 'Second closing time', 'local-business-schema' ) . '</span><input type="time" name="' . esc_attr( $base . '[closes_2]' ) . '" value="' . esc_attr( $row['closes_2'] ) . '"></label>';
			echo '</div></div>';
		}
		echo '</div></td></tr>';
	}

	/** Source selector and recognized Yoast value. */
	private function source_control( $field, array $options, array $yoast ) {
		$value = isset( $options['sources'][ $field ] ) ? $options['sources'][ $field ] : 'automatic';
		$name  = Schema::OPTION_NAME . '[sources][' . $field . ']';
		echo '<div class="dovetail-source"><label>' . esc_html__( 'Source:', 'local-business-schema' ) . ' <select name="' . esc_attr( $name ) . '">';
		echo '<option value="automatic" ' . selected( $value, 'automatic', false ) . '>' . esc_html__( 'Automatic — prefer Yoast', 'local-business-schema' ) . '</option>';
		echo '<option value="plugin" ' . selected( $value, 'plugin', false ) . '>' . esc_html__( 'Use plugin value', 'local-business-schema' ) . '</option>';
		echo '</select></label>';
		if ( ! empty( $yoast[ $field ] ) ) {
			$display = is_array( $yoast[ $field ] ) ? implode( ', ', $yoast[ $field ] ) : $yoast[ $field ];
			echo '<p class="description"><strong>' . esc_html__( 'Recognized Yoast value:', 'local-business-schema' ) . '</strong> ' . esc_html( $display ) . '</p>';
		}
		echo '</div>';
	}

	/** Begin a normal table row. */
	private function basic_sourced_row_start( $field, $label ) {
		echo '<tr><th scope="row"><label for="dovetail-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';
	}

	/** Integration overview. */
	private function integration_summary( array $options, array $yoast ) {
		$sourced_labels = array(
			'name' => __( 'Name', 'local-business-schema' ), 'alternate_name' => __( 'Alternate name', 'local-business-schema' ),
			'url' => __( 'URL', 'local-business-schema' ), 'logo' => __( 'Logo', 'local-business-schema' ),
			'image' => __( 'Image', 'local-business-schema' ), 'description' => __( 'Description', 'local-business-schema' ),
			'telephone' => __( 'Telephone', 'local-business-schema' ), 'email' => __( 'Email', 'local-business-schema' ),
			'legal_name' => __( 'Legal name', 'local-business-schema' ), 'same_as' => __( 'Social profiles', 'local-business-schema' ),
			'vat_id' => __( 'VAT ID', 'local-business-schema' ), 'tax_id' => __( 'Tax ID', 'local-business-schema' ),
			'slogan' => __( 'Slogan', 'local-business-schema' ), 'number_of_employees' => __( 'Number of employees', 'local-business-schema' ),
			'contact_points' => __( 'Contact points', 'local-business-schema' ), 'iso6523_code' => __( 'ISO 6523 code', 'local-business-schema' ),
			'global_location_number' => __( 'Global Location Number', 'local-business-schema' ), 'lei_code' => __( 'LEI code', 'local-business-schema' ),
			'naics' => __( 'NAICS', 'local-business-schema' ), 'duns' => __( 'DUNS', 'local-business-schema' ),
		);
		$plugin_labels = array(
			'enabled' => __( 'Integration enabled', 'local-business-schema' ),
			'display_scope' => __( 'Display scope', 'local-business-schema' ),
			'schema_type' => __( 'Business type', 'local-business-schema' ),
			'founding_date' => __( 'Founding date', 'local-business-schema' ),
			'founders' => __( 'Founders', 'local-business-schema' ),
			'parent_organization_name' => __( 'Parent organization name', 'local-business-schema' ),
			'parent_organization_url' => __( 'Parent organization URL', 'local-business-schema' ),
			'street_address' => __( 'Street address', 'local-business-schema' ),
			'address_locality' => __( 'City / locality', 'local-business-schema' ),
			'address_region' => __( 'Region / county', 'local-business-schema' ),
			'postal_code' => __( 'Postal code', 'local-business-schema' ),
			'address_country' => __( 'Country code', 'local-business-schema' ),
			'latitude' => __( 'Latitude', 'local-business-schema' ),
			'longitude' => __( 'Longitude', 'local-business-schema' ),
			'has_map' => __( 'Map URL', 'local-business-schema' ),
			'locations' => __( 'Additional locations', 'local-business-schema' ),
			'location_hours' => __( 'Additional location hours', 'local-business-schema' ),
			'price_range' => __( 'Price range', 'local-business-schema' ),
			'currencies_accepted' => __( 'Currencies accepted', 'local-business-schema' ),
			'payment_accepted' => __( 'Payment methods', 'local-business-schema' ),
			'area_served' => __( 'Areas served', 'local-business-schema' ),
			'languages' => __( 'Languages', 'local-business-schema' ),
			'services' => __( 'Services', 'local-business-schema' ),
			'opening_hours' => __( 'Opening hours', 'local-business-schema' ),
			'special_hours' => __( 'Special opening hours', 'local-business-schema' ),
			'serves_cuisine' => __( 'Cuisine types', 'local-business-schema' ),
			'menu' => __( 'Menu URL', 'local-business-schema' ),
			'accepts_reservations' => __( 'Accepts reservations', 'local-business-schema' ),
			'checkin_time' => __( 'Check-in time', 'local-business-schema' ),
			'checkout_time' => __( 'Check-out time', 'local-business-schema' ),
			'star_rating' => __( 'Star rating', 'local-business-schema' ),
			'amenities' => __( 'Amenities', 'local-business-schema' ),
			'medical_specialties' => __( 'Medical specialties', 'local-business-schema' ),
			'identifiers' => __( 'Other identifiers', 'local-business-schema' ),
		);

		echo '<tr class="dovetail-integration-row"><td colspan="2"><div class="dovetail-table-scroll"><table class="widefat striped dovetail-integration"><thead><tr><th>' . esc_html__( 'Information', 'local-business-schema' ) . '</th><th>' . esc_html__( 'Configured priority', 'local-business-schema' ) . '</th><th>' . esc_html__( 'Yoast value recognized in admin', 'local-business-schema' ) . '</th></tr></thead><tbody>';
		echo '<tr class="dovetail-integration-group"><th colspan="3">' . esc_html__( 'Fields shared with Yoast SEO', 'local-business-schema' ) . '</th></tr>';
		foreach ( $sourced_labels as $field => $label ) {
			$source = isset( $options['sources'][ $field ] ) && 'plugin' === $options['sources'][ $field ] ? __( 'Plugin', 'local-business-schema' ) : __( 'Automatic — Yoast, then plugin', 'local-business-schema' );
			$found  = empty( $yoast[ $field ] ) ? __( 'Not detected', 'local-business-schema' ) : ( is_array( $yoast[ $field ] ) ? implode( ', ', $yoast[ $field ] ) : $yoast[ $field ] );
			echo '<tr><td>' . esc_html( $label ) . '</td><td><span class="dovetail-origin-badge is-shared">' . esc_html( $source ) . '</span></td><td>' . esc_html( $found ) . '</td></tr>';
		}
		echo '<tr class="dovetail-integration-group"><th colspan="3">' . esc_html__( 'Fields managed by this plugin', 'local-business-schema' ) . '</th></tr>';
		foreach ( $plugin_labels as $label ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td colspan="2"><span class="dovetail-origin-badge is-plugin">' . esc_html__( 'Controlled directly by plugin', 'local-business-schema' ) . '</span></td></tr>';
		}
		echo '</tbody></table></div><p class="description">' . esc_html__( 'Runtime values already present in Yoast’s generated Organization graph remain authoritative in Automatic mode, even when this admin summary cannot read them.', 'local-business-schema' ) . '</p></td></tr>';
	}

	/** Best-effort display of public Yoast settings; runtime merge uses graph values. */
	private function get_recognized_yoast_values() {
		$values = array();
		if ( ! Schema::is_yoast_active() ) {
			return $values;
		}

		$titles = get_option( 'wpseo_titles', array() );
		$social = get_option( 'wpseo_social', array() );
		$titles = is_array( $titles ) ? $titles : array();
		$social = is_array( $social ) ? $social : array();

		if ( ! empty( $titles['company_name'] ) ) {
			$values['name'] = $titles['company_name'];
		}
		if ( ! empty( $titles['company_logo'] ) ) {
			$values['logo'] = $titles['company_logo'];
		} elseif ( ! empty( $titles['company_logo_id'] ) ) {
			$logo = wp_get_attachment_url( (int) $titles['company_logo_id'] );
			if ( $logo ) {
				$values['logo'] = $logo;
			}
		}

		$profiles = array();
		foreach ( array( 'facebook_site', 'instagram_url', 'linkedin_url', 'youtube_url', 'pinterest_url', 'wikipedia_url', 'myspace_url' ) as $key ) {
			if ( ! empty( $social[ $key ] ) && filter_var( $social[ $key ], FILTER_VALIDATE_URL ) ) {
				$profiles[] = $social[ $key ];
			}
		}
		if ( $profiles ) {
			$values['same_as'] = array_values( array_unique( $profiles ) );
		}

		return $values;
	}

	/** Whether Yoast is explicitly configured to represent the site as a person. */
	private function yoast_represents_person() {
		if ( ! Schema::is_yoast_active() ) {
			return false;
		}

		$titles = get_option( 'wpseo_titles', array() );
		return is_array( $titles ) && isset( $titles['company_or_person'] ) && 'person' === strtolower( (string) $titles['company_or_person'] );
	}

	/** Sanitize a supported Schema.org type. */
	private function sanitize_schema_type( $type ) {
		$type  = sanitize_text_field( $type );
		$types = self::schema_types();
		return isset( $types[ $type ] ) ? $type : 'LocalBusiness';
	}

	/** Validate ISO-style date accepted by Schema.org. */
	private function sanitize_date( $date ) {
		$date = sanitize_text_field( $date );
		return preg_match( '/^\d{4}(?:-\d{2}(?:-\d{2})?)?$/', $date ) ? $date : '';
	}

	/** Validate latitude or longitude. */
	private function sanitize_coordinate( $value, $min, $max ) {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		$number = (float) $value;
		return $number >= $min && $number <= $max ? (string) $number : '';
	}

	/** Validate 24-hour time. */
	private function sanitize_time( $time ) {
		$time = sanitize_text_field( $time );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
	}

	/** Validate a 1–5 rating. */
	private function sanitize_rating( $rating ) {
		$rating = str_replace( ',', '.', trim( (string) $rating ) );
		if ( '' === $rating || ! is_numeric( $rating ) ) {
			return '';
		}

		$number = (float) $rating;
		return $number >= 1 && $number <= 5 ? (string) $number : '';
	}

	/** Curated Organization and LocalBusiness types. */
	public static function schema_types() {
		return array(
			'Organization'             => 'Organization',
			'Corporation'              => 'Corporation',
			'EducationalOrganization'  => 'EducationalOrganization',
			'GovernmentOrganization'   => 'GovernmentOrganization',
			'MedicalOrganization'      => 'MedicalOrganization',
			'NGO'                      => 'NGO',
			'NewsMediaOrganization'    => 'NewsMediaOrganization',
			'OnlineBusiness'           => 'OnlineBusiness',
			'OnlineStore'              => 'OnlineStore',
			'SportsOrganization'       => 'SportsOrganization',
			'LocalBusiness'             => 'LocalBusiness',
			'LegalService'              => 'LegalService',
			'AccountingService'         => 'AccountingService',
			'Restaurant'                => 'Restaurant',
			'CafeOrCoffeeShop'          => 'CafeOrCoffeeShop',
			'BarOrPub'                  => 'BarOrPub',
			'Bakery'                    => 'Bakery',
			'Dentist'                   => 'Dentist',
			'MedicalClinic'             => 'MedicalClinic',
			'Pharmacy'                  => 'Pharmacy',
			'RealEstateAgent'           => 'RealEstateAgent',
			'FinancialService'          => 'FinancialService',
			'InsuranceAgency'           => 'InsuranceAgency',
			'AutoRepair'                => 'AutoRepair',
			'AutomotiveBusiness'        => 'AutomotiveBusiness',
			'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness',
			'Plumber'                   => 'Plumber',
			'Electrician'               => 'Electrician',
			'Locksmith'                 => 'Locksmith',
			'Store'                     => 'Store',
			'Hotel'                     => 'Hotel',
			'HealthAndBeautyBusiness'   => 'HealthAndBeautyBusiness',
			'HairSalon'                 => 'HairSalon',
			'SportsActivityLocation'    => 'SportsActivityLocation',
			'ChildCare'                 => 'ChildCare',
			'EmploymentAgency'          => 'EmploymentAgency',
			'TravelAgency'              => 'TravelAgency',
		);
	}
}
