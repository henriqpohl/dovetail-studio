<?php
/**
 * WordPress admin settings screen.
 *
 * @package LocalBusinessSchema
 */

namespace DovetailStudio\SchemaSeo;

use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

final class Admin {

	/** Settings page hook suffix. */
	private $page_hook = '';

	/** Register admin hooks. */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_dovetail_schema_seo_preview', array( $this, 'ajax_schema_preview' ) );
	}

	/** Add Schema SEO under Dovetail Studio. */
	public function add_settings_page() {
		$this->page_hook = add_submenu_page(
			'dovetail-studio',
			__( 'Schema SEO', 'dovetail-studio' ),
			__( 'Schema SEO', 'dovetail-studio' ),
			'manage_options',
			'dovetail-schema-seo',
			array( $this, 'render_page' )
		);
	}

	/** Register the single structured option. */
	public function register_setting() {
		register_setting(
			'dovetail_schema_seo_group',
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
		wp_enqueue_script(
			'dovetail-schema-seo-admin',
			DOVETAIL_SCHEMA_SEO_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			DOVETAIL_SCHEMA_SEO_VERSION,
			true
		);
		wp_localize_script(
			'dovetail-schema-seo-admin',
			'dovetailSchemaSeoAdmin',
			array(
				'mediaTitle'       => __( 'Select image', 'dovetail-studio' ),
				'mediaButton'      => __( 'Use this image', 'dovetail-studio' ),
				'previewUrl'       => home_url( '/' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'previewNonce'     => wp_create_nonce( 'dovetail_schema_seo_preview' ),
				'previewLoading'   => __( 'Loading JSON-LD from the current homepage…', 'dovetail-studio' ),
				'previewReady'     => __( 'Current JSON-LD loaded and formatted.', 'dovetail-studio' ),
				'previewError'     => __( 'Could not find valid Local Business or Yoast JSON-LD on the homepage.', 'dovetail-studio' ),
				'previewCopied'    => __( 'JSON copied to the clipboard.', 'dovetail-studio' ),
				'previewCopyError' => __( 'Could not copy automatically. Select the JSON and copy it manually.', 'dovetail-studio' ),
				'previewUnsaved'   => __( 'Save your changes before refreshing the JSON preview.', 'dovetail-studio' ),
			)
		);
	}

	/** Return the current public JSON-LD when a direct browser request is unavailable. */
	public function ajax_schema_preview() {
		check_ajax_referer( 'dovetail_schema_seo_preview', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to view this preview.', 'dovetail-studio' ) ),
				403
			);
		}

		$preview_url = add_query_arg( '_dovetail_schema_preview', time(), home_url( '/' ) );
		$response    = wp_safe_remote_get(
			$preview_url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The homepage could not be loaded for the JSON-LD preview.', 'dovetail-studio' ),
				),
				502
			);
		}

		$json = $this->extract_schema_json( wp_remote_retrieve_body( $response ) );

		if ( '' === $json ) {
			wp_send_json_error(
				array(
					'message' => __( 'No valid Local Business or Yoast JSON-LD was found on the homepage.', 'dovetail-studio' ),
				),
				404
			);
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

			if ( preg_match( '~\\bclass\\s*=\\s*(["\\\'])[^"\\\']*dovetail-schema-graph[^"\\\']*\\1~i', $attributes ) ) {
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

		$precision = \DovetailStudio\Core\Seo\SchemaGraph::normalize_json_float_precision();
		$json      = $best
			? (string) wp_json_encode(
				$best,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
			: '';
		\DovetailStudio\Core\Seo\SchemaGraph::restore_json_float_precision( $precision );

		return $json;
	}

	/** Sanitize every supported option and discard unknown keys. */
	public function sanitize_options( $input ) {
		$defaults = Schema::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$output['enabled']                = empty( $input['enabled'] ) ? 0 : 1;
		$output['display_scope']          = isset( $input['display_scope'] ) && 'homepage' === $input['display_scope']
			? 'homepage'
			: 'sitewide';
		$output['schema_type']            = $this->sanitize_schema_type(
			isset( $input['schema_type'] ) ? $input['schema_type'] : ''
		);
		$output['site_graph_enabled']     = empty( $input['site_graph_enabled'] ) ? 0 : 1;
		$output['breadcrumbs_enabled']    = empty( $input['breadcrumbs_enabled'] ) ? 0 : 1;
		$output['breadcrumbs_home_label'] = isset( $input['breadcrumbs_home_label'] )
			? sanitize_text_field( $input['breadcrumbs_home_label'] )
			: '';
		$output['entity']                 = isset( $input['entity'] ) && 'person' === $input['entity']
			? 'person'
			: 'organization';
		$output['person_name']            = isset( $input['person_name'] )
			? sanitize_text_field( $input['person_name'] )
			: '';
		$output['person_job_title']       = isset( $input['person_job_title'] )
			? sanitize_text_field( $input['person_job_title'] )
			: '';
		$output['person_description']     = isset( $input['person_description'] )
			? sanitize_textarea_field( $input['person_description'] )
			: '';
		$output['person_image']           = isset( $input['person_image'] )
			? esc_url_raw( $input['person_image'] )
			: '';
		$output['person_same_as']         = implode(
			"\n",
			Schema::parse_url_list( isset( $input['person_same_as'] ) ? $input['person_same_as'] : '' )
		);

		$text_fields = array(
			'name',
			'alternate_name',
			'legal_name',
			'telephone',
			'price_range',
			'street_address',
			'address_locality',
			'address_region',
			'postal_code',
			'address_country',
			'vat_id',
			'tax_id',
			'slogan',
			'parent_organization_name',
			'iso6523_code',
			'global_location_number',
			'lei_code',
			'naics',
			'duns',
			'payment_accepted',
			'accepts_reservations',
		);

		foreach ( $text_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		}

		$output['description']         = isset( $input['description'] )
			? sanitize_textarea_field( $input['description'] )
			: '';
		$output['area_served']         = isset( $input['area_served'] )
			? sanitize_textarea_field( $input['area_served'] )
			: '';
		$output['languages']           = isset( $input['languages'] )
			? sanitize_textarea_field( $input['languages'] )
			: '';
		$output['identifiers']         = isset( $input['identifiers'] )
			? sanitize_textarea_field( $input['identifiers'] )
			: '';
		$output['contact_points']      = isset( $input['contact_points'] )
			? sanitize_textarea_field( $input['contact_points'] )
			: '';
		$output['special_hours']       = isset( $input['special_hours'] )
			? sanitize_textarea_field( $input['special_hours'] )
			: '';
		$output['services']            = isset( $input['services'] )
			? sanitize_textarea_field( $input['services'] )
			: '';
		$output['locations']           = isset( $input['locations'] )
			? sanitize_textarea_field( $input['locations'] )
			: '';
		$output['location_hours']      = isset( $input['location_hours'] )
			? sanitize_textarea_field( $input['location_hours'] )
			: '';
		$output['founders']            = isset( $input['founders'] )
			? sanitize_textarea_field( $input['founders'] )
			: '';
		$output['serves_cuisine']      = isset( $input['serves_cuisine'] )
			? sanitize_textarea_field( $input['serves_cuisine'] )
			: '';
		$output['amenities']           = isset( $input['amenities'] )
			? sanitize_textarea_field( $input['amenities'] )
			: '';
		$output['medical_specialties'] = isset( $input['medical_specialties'] )
			? sanitize_textarea_field( $input['medical_specialties'] )
			: '';
		$output['email']               = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		$output['founding_date']       = isset( $input['founding_date'] )
			? $this->sanitize_date( $input['founding_date'] )
			: '';
		$output['number_of_employees'] = isset( $input['number_of_employees'] ) && '' !== $input['number_of_employees']
			? (string) absint( $input['number_of_employees'] )
			: '';
		$output['star_rating']         = $this->sanitize_rating(
			isset( $input['star_rating'] ) ? $input['star_rating'] : ''
		);
		$output['checkin_time']        = $this->sanitize_time(
			isset( $input['checkin_time'] ) ? $input['checkin_time'] : ''
		);
		$output['checkout_time']       = $this->sanitize_time(
			isset( $input['checkout_time'] ) ? $input['checkout_time'] : ''
		);

		foreach ( array( 'url', 'logo', 'image', 'has_map', 'menu', 'parent_organization_url' ) as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? esc_url_raw( $input[ $field ] ) : '';
		}

		$output['same_as']             = implode(
			"\n",
			Schema::parse_url_list( isset( $input['same_as'] ) ? $input['same_as'] : '' )
		);
		$output['languages']           = implode(
			', ',
			Schema::parse_languages( isset( $input['languages'] ) ? $input['languages'] : '' )
		);
		$output['currencies_accepted'] = implode(
			', ',
			Schema::parse_currency_list(
				isset( $input['currencies_accepted'] ) ? $input['currencies_accepted'] : ''
			)
		);
		$output['address_country']     = preg_match( '/^[A-Za-z]{2}$/', $output['address_country'] )
			? strtoupper( $output['address_country'] )
			: '';
		$output['latitude']            = $this->sanitize_coordinate(
			isset( $input['latitude'] ) ? $input['latitude'] : '',
			-90,
			90
		);
		$output['longitude']           = $this->sanitize_coordinate(
			isset( $input['longitude'] ) ? $input['longitude'] : '',
			-180,
			180
		);

		$input_sources = isset( $input['sources'] ) && is_array( $input['sources'] )
			? $input['sources']
			: array();

		foreach ( Schema::SOURCE_FIELDS as $field ) {
			$output['sources'][ $field ] = isset( $input_sources[ $field ] ) && 'plugin' === $input_sources[ $field ]
				? 'plugin'
				: 'automatic';
		}

		$input_hours = isset( $input['opening_hours'] ) && is_array( $input['opening_hours'] )
			? $input['opening_hours']
			: array();

		foreach ( Schema::days() as $day => $label ) {
			$row                             = isset( $input_hours[ $day ] ) && is_array( $input_hours[ $day ] )
				? $input_hours[ $day ]
				: array();
			$output['opening_hours'][ $day ] = array(
				'enabled'  => empty( $row['enabled'] ) ? 0 : 1,
				'opens'    => $this->sanitize_time( isset( $row['opens'] ) ? $row['opens'] : '' ),
				'closes'   => $this->sanitize_time( isset( $row['closes'] ) ? $row['closes'] : '' ),
				'opens_2'  => $this->sanitize_time( isset( $row['opens_2'] ) ? $row['opens_2'] : '' ),
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

		$options         = Schema::get_options();
		$yoast_active    = Schema::is_yoast_active();
		$yoast_is_person = $this->yoast_represents_person();
		$missing         = ( new Schema() )->missing_required_fields();

		if ( $yoast_active ) {
			// With Yoast SEO the name comes from Yoast's Organization, and the Person mode is set in Yoast.
			$missing = $yoast_is_person
				? array()
				: array_values(
					array_diff(
						$missing,
						array( __( 'Name', 'dovetail-studio' ), __( 'Person name', 'dovetail-studio' ) )
					)
				);
		}

		$yoast = $this->get_recognized_yoast_values();

		View::render(
			dirname( __DIR__ ),
			'admin-page',
			array(
				'dir'             => dirname( __DIR__ ),
				'version'         => DOVETAIL_SCHEMA_SEO_VERSION,
				'option'          => Schema::OPTION_NAME,
				'options'         => $options,
				'yoast'           => $yoast,
				'yoast_active'    => $yoast_active,
				'yoast_is_person' => $yoast_is_person,
				'other_seo'       => \DovetailStudio\Core\Seo\Environment::other_seo_plugin(),
				'missing'         => $missing && ! empty( $options['enabled'] ) ? $missing : array(),
				'schema_types'    => self::schema_types(),
				'hours'           => $this->opening_hours( (array) $options['opening_hours'] ),
				'integration'     => $this->integration_summary( $options, $yoast ),
			)
		);
	}

	/**
	 * Opening hours rows for the template: label, field name and saved values per day.
	 *
	 * @param array<string,mixed> $hours Saved opening hours.
	 * @return array<int,array<string,mixed>>
	 */
	private function opening_hours( array $hours ) {
		$days = array();

		foreach ( Schema::days() as $day => $label ) {
			$days[] = array(
				'label' => $label,
				'name'  => Schema::OPTION_NAME . '[opening_hours][' . $day . ']',
				'row'   => isset( $hours[ $day ] ) ? $hours[ $day ] : array(
					'enabled'  => 0,
					'opens'    => '09:00',
					'closes'   => '17:00',
					'opens_2'  => '',
					'closes_2' => '',
				),
			);
		}

		return $days;
	}

	/**
	 * Integration overview for the template: shared fields with their source, and plugin-only fields.
	 *
	 * @param array<string,mixed> $options Saved options.
	 * @param array<string,mixed> $yoast   Recognized Yoast values.
	 * @return array{shared: array<int,array<string,string>>, plugin: string[]}
	 */
	private function integration_summary( array $options, array $yoast ) {
		$sourced_labels = array(
			'name'                   => __( 'Name', 'dovetail-studio' ),
			'alternate_name'         => __( 'Alternate name', 'dovetail-studio' ),
			'url'                    => __( 'URL', 'dovetail-studio' ),
			'logo'                   => __( 'Logo', 'dovetail-studio' ),
			'image'                  => __( 'Image', 'dovetail-studio' ),
			'description'            => __( 'Description', 'dovetail-studio' ),
			'telephone'              => __( 'Telephone', 'dovetail-studio' ),
			'email'                  => __( 'Email', 'dovetail-studio' ),
			'legal_name'             => __( 'Legal name', 'dovetail-studio' ),
			'same_as'                => __( 'Social profiles', 'dovetail-studio' ),
			'vat_id'                 => __( 'VAT ID', 'dovetail-studio' ),
			'tax_id'                 => __( 'Tax ID', 'dovetail-studio' ),
			'slogan'                 => __( 'Slogan', 'dovetail-studio' ),
			'number_of_employees'    => __( 'Number of employees', 'dovetail-studio' ),
			'contact_points'         => __( 'Contact points', 'dovetail-studio' ),
			'iso6523_code'           => __( 'ISO 6523 code', 'dovetail-studio' ),
			'global_location_number' => __( 'Global Location Number', 'dovetail-studio' ),
			'lei_code'               => __( 'LEI code', 'dovetail-studio' ),
			'naics'                  => __( 'NAICS', 'dovetail-studio' ),
			'duns'                   => __( 'DUNS', 'dovetail-studio' ),
		);
		$plugin_labels  = array(
			'enabled'                  => __( 'Integration enabled', 'dovetail-studio' ),
			'display_scope'            => __( 'Display scope', 'dovetail-studio' ),
			'schema_type'              => __( 'Business type', 'dovetail-studio' ),
			'founding_date'            => __( 'Founding date', 'dovetail-studio' ),
			'founders'                 => __( 'Founders', 'dovetail-studio' ),
			'parent_organization_name' => __( 'Parent organization name', 'dovetail-studio' ),
			'parent_organization_url'  => __( 'Parent organization URL', 'dovetail-studio' ),
			'street_address'           => __( 'Street address', 'dovetail-studio' ),
			'address_locality'         => __( 'City / locality', 'dovetail-studio' ),
			'address_region'           => __( 'Region / county', 'dovetail-studio' ),
			'postal_code'              => __( 'Postal code', 'dovetail-studio' ),
			'address_country'          => __( 'Country code', 'dovetail-studio' ),
			'latitude'                 => __( 'Latitude', 'dovetail-studio' ),
			'longitude'                => __( 'Longitude', 'dovetail-studio' ),
			'has_map'                  => __( 'Map URL', 'dovetail-studio' ),
			'locations'                => __( 'Additional locations', 'dovetail-studio' ),
			'location_hours'           => __( 'Additional location hours', 'dovetail-studio' ),
			'price_range'              => __( 'Price range', 'dovetail-studio' ),
			'currencies_accepted'      => __( 'Currencies accepted', 'dovetail-studio' ),
			'payment_accepted'         => __( 'Payment methods', 'dovetail-studio' ),
			'area_served'              => __( 'Areas served', 'dovetail-studio' ),
			'languages'                => __( 'Languages', 'dovetail-studio' ),
			'services'                 => __( 'Services', 'dovetail-studio' ),
			'opening_hours'            => __( 'Opening hours', 'dovetail-studio' ),
			'special_hours'            => __( 'Special opening hours', 'dovetail-studio' ),
			'serves_cuisine'           => __( 'Cuisine types', 'dovetail-studio' ),
			'menu'                     => __( 'Menu URL', 'dovetail-studio' ),
			'accepts_reservations'     => __( 'Accepts reservations', 'dovetail-studio' ),
			'checkin_time'             => __( 'Check-in time', 'dovetail-studio' ),
			'checkout_time'            => __( 'Check-out time', 'dovetail-studio' ),
			'star_rating'              => __( 'Star rating', 'dovetail-studio' ),
			'amenities'                => __( 'Amenities', 'dovetail-studio' ),
			'medical_specialties'      => __( 'Medical specialties', 'dovetail-studio' ),
			'identifiers'              => __( 'Other identifiers', 'dovetail-studio' ),
		);

		$shared = array();

		foreach ( $sourced_labels as $field => $label ) {
			$shared[] = array(
				'label'  => $label,
				'source' => isset( $options['sources'][ $field ] ) && 'plugin' === $options['sources'][ $field ]
					? __( 'Plugin', 'dovetail-studio' )
					: __( 'Automatic — Yoast, then plugin', 'dovetail-studio' ),
				'found'  => empty( $yoast[ $field ] )
					? __( 'Not detected', 'dovetail-studio' )
					: ( is_array( $yoast[ $field ] ) ? implode( ', ', $yoast[ $field ] ) : $yoast[ $field ] ),
			);
		}

		return array(
			'shared' => $shared,
			'plugin' => array_values( $plugin_labels ),
		);
	}

	/** Best-effort display of public Yoast settings; runtime merge uses graph values. */
	private function get_recognized_yoast_values() {
		$values = array();

		// In Person mode Yoast's values describe the person, not the business, so none are offered.
		if ( ! Schema::is_yoast_active() || $this->yoast_represents_person() ) {
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

		// Yoast SEO keeps Facebook and X apart and every other profile in other_social_urls.
		$profiles = array();

		if ( ! empty( $social['facebook_site'] )
			&& filter_var( $social['facebook_site'], FILTER_VALIDATE_URL )
		) {
			$profiles[] = $social['facebook_site'];
		}

		if ( ! empty( $social['twitter_site'] ) && is_string( $social['twitter_site'] ) ) {
			$handle     = ltrim( trim( $social['twitter_site'] ), '@' );
			$profiles[] = filter_var( $handle, FILTER_VALIDATE_URL )
				? $handle
				: 'https://x.com/' . rawurlencode( $handle );
		}

		foreach ( isset( $social['other_social_urls'] ) && is_array( $social['other_social_urls'] ) ? $social['other_social_urls'] : array() as $url ) {
			if ( is_string( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$profiles[] = $url;
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

		return is_array( $titles ) && isset( $titles['company_or_person'] ) && 'person' === strtolower(
			(string) $titles['company_or_person']
		);
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
			'Organization'                => 'Organization',
			'Corporation'                 => 'Corporation',
			'EducationalOrganization'     => 'EducationalOrganization',
			'GovernmentOrganization'      => 'GovernmentOrganization',
			'MedicalOrganization'         => 'MedicalOrganization',
			'NGO'                         => 'NGO',
			'NewsMediaOrganization'       => 'NewsMediaOrganization',
			'OnlineBusiness'              => 'OnlineBusiness',
			'OnlineStore'                 => 'OnlineStore',
			'SportsOrganization'          => 'SportsOrganization',
			'LocalBusiness'               => 'LocalBusiness',
			'LegalService'                => 'LegalService',
			'AccountingService'           => 'AccountingService',
			'Restaurant'                  => 'Restaurant',
			'CafeOrCoffeeShop'            => 'CafeOrCoffeeShop',
			'BarOrPub'                    => 'BarOrPub',
			'Bakery'                      => 'Bakery',
			'Dentist'                     => 'Dentist',
			'MedicalClinic'               => 'MedicalClinic',
			'Pharmacy'                    => 'Pharmacy',
			'RealEstateAgent'             => 'RealEstateAgent',
			'FinancialService'            => 'FinancialService',
			'InsuranceAgency'             => 'InsuranceAgency',
			'AutoRepair'                  => 'AutoRepair',
			'AutomotiveBusiness'          => 'AutomotiveBusiness',
			'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness',
			'Plumber'                     => 'Plumber',
			'Electrician'                 => 'Electrician',
			'Locksmith'                   => 'Locksmith',
			'Store'                       => 'Store',
			'Hotel'                       => 'Hotel',
			'HealthAndBeautyBusiness'     => 'HealthAndBeautyBusiness',
			'HairSalon'                   => 'HairSalon',
			'SportsActivityLocation'      => 'SportsActivityLocation',
			'ChildCare'                   => 'ChildCare',
			'EmploymentAgency'            => 'EmploymentAgency',
			'TravelAgency'                => 'TravelAgency',
		);
	}
}
