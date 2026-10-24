<?php
/**
 * Schema generation and Yoast SEO integration.
 *
 * @package LocalBusinessSchema
 */

namespace DovetailStudio\SchemaSeo;

use DovetailStudio\Core\Seo\Environment;
use DovetailStudio\Core\Seo\SchemaGraph;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/** Option name. */
	const OPTION_NAME = 'lbs_options';

	/** Organization types which do not imply a physical LocalBusiness. */
	const ORGANIZATION_ONLY_TYPES = array(
		'Organization',
		'Corporation',
		'EducationalOrganization',
		'GovernmentOrganization',
		'MedicalOrganization',
		'NGO',
		'NewsMediaOrganization',
		'OnlineBusiness',
		'OnlineStore',
		'SportsOrganization',
	);

	/** Fields which can prefer a Yoast value. */
	const SOURCE_FIELDS = array(
		'name',
		'alternate_name',
		'url',
		'logo',
		'image',
		'description',
		'telephone',
		'email',
		'legal_name',
		'same_as',
		'vat_id',
		'tax_id',
		'slogan',
		'number_of_employees',
		'contact_points',
		'iso6523_code',
		'global_location_number',
		'lei_code',
		'naics',
		'duns',
	);

	/** Register frontend hooks. */
	public function register_hooks() {
		add_action( 'wp', array( __CLASS__, 'normalize_json_float_precision' ), 0 );
		add_filter( 'wpseo_schema_organization', array( $this, 'filter_yoast_organization' ), 20, 2 );
		add_filter( 'wpseo_schema_graph', array( $this, 'filter_yoast_graph' ), 20, 2 );
		add_filter( SchemaGraph::FILTER, array( $this, 'add_standalone_pieces' ), 10 );
	}

	/** Prevent PHP float artifacts from leaking into frontend JSON-LD numbers. */
	public static function normalize_json_float_precision() {
		SchemaGraph::normalize_json_float_precision();
	}

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public static function is_yoast_active() {
		return Environment::has_yoast();
	}

	/**
	 * Enrich Yoast's Organization graph piece.
	 *
	 * @param array  $data    Organization data.
	 * @param object $context Yoast schema context.
	 * @return array
	 */
	public function filter_yoast_organization( $data, $context = null ) {
		if ( ! $this->should_output() || ! is_array( $data ) ) {
			return $data;
		}

		self::normalize_json_float_precision();
		return $this->merge_schema( $data );
	}

	/**
	 * Final graph safeguard. This supports Yoast versions or extensions which
	 * create an Organization-derived entity without applying the Organization filter.
	 *
	 * @param array  $graph   Complete Yoast graph.
	 * @param object $context Yoast schema context.
	 * @return array
	 */
	public function filter_yoast_graph( $graph, $context = null ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		self::normalize_json_float_precision();

		if ( ! $this->should_output() ) {
			return $graph;
		}

		$organization_found = false;
		foreach ( $graph as $index => $piece ) {
			if ( is_array( $piece ) && $this->is_organization_piece( $piece ) ) {
				$graph[ $index ] = $this->merge_schema( $piece );
				$organization_found = true;
				break;
			}
		}

		if ( ! $organization_found ) {
			$graph[] = $this->merge_schema( $this->base_schema( false ) );
		}

		return $graph;
	}

	/**
	 * Add the Organization entity to the shared standalone graph printed by the
	 * Dovetail Studio core when Yoast does not provide a graph.
	 *
	 * @param array $pieces Standalone graph pieces.
	 * @return array
	 */
	public function add_standalone_pieces( $pieces ) {
		$pieces = is_array( $pieces ) ? $pieces : array();

		if ( ! $this->should_output() ) {
			return $pieces;
		}

		self::normalize_json_float_precision();
		$schema = $this->merge_schema( $this->base_schema( false ) );

		// Only @id and @type: nothing configured yet, so no entity is printed.
		if ( count( $schema ) <= 2 ) {
			return $pieces;
		}

		$pieces[] = $schema;
		return $pieces;
	}

	/** Build the stable identity used when Yoast has no Organization. */
	private function base_schema( $include_context ) {
		$schema = array(
			'@id' => untrailingslashit( home_url( '/' ) ) . '/#organization',
		);

		if ( $include_context ) {
			$schema = array_merge( array( '@context' => 'https://schema.org' ), $schema );
		}

		return $schema;
	}

	/**
	 * Build or enrich an Organization entity.
	 *
	 * @param array $data Existing Schema data.
	 * @return array
	 */
	public function merge_schema( array $data ) {
		$options = self::get_options();
		$values  = $this->plugin_values( $options );

		$data['@type'] = $this->merge_types( isset( $data['@type'] ) ? $data['@type'] : array(), $options['schema_type'] );

		$property_map = array(
			'name'           => 'name',
			'alternate_name' => 'alternateName',
			'url'            => 'url',
			'logo'           => 'logo',
			'image'          => 'image',
			'description'    => 'description',
			'telephone'      => 'telephone',
			'email'          => 'email',
			'legal_name'     => 'legalName',
			'same_as'        => 'sameAs',
			'vat_id'         => 'vatID',
			'tax_id'         => 'taxID',
			'slogan'         => 'slogan',
			'number_of_employees' => 'numberOfEmployees',
			'contact_points'  => 'contactPoint',
			'iso6523_code'    => 'iso6523Code',
			'global_location_number' => 'globalLocationNumber',
			'lei_code'        => 'leiCode',
			'naics'           => 'naics',
			'duns'            => 'duns',
		);

		foreach ( $property_map as $field => $property ) {
			$data = $this->apply_sourced_value( $data, $property, $values[ $field ], $this->source_for( $options, $field ) );
		}

		$plugin_only = array(
			'foundingDate'  => $values['founding_date'],
			'address'       => $values['address'],
			'areaServed'    => $values['area_served'],
			'knowsLanguage' => $values['languages'],
			'identifier'    => $values['identifiers'],
		);

		if ( $this->is_local_business_type( $options['schema_type'] ) ) {
			$plugin_only['geo']                            = $values['geo'];
			$plugin_only['priceRange']                     = $values['price_range'];
			$plugin_only['openingHoursSpecification']      = $values['opening_hours'];
			$plugin_only['specialOpeningHoursSpecification'] = $values['special_hours'];
			$plugin_only['currenciesAccepted']             = $values['currencies_accepted'];
			$plugin_only['paymentAccepted']                = $values['payment_accepted'];
		}

		foreach ( $plugin_only as $property => $value ) {
			if ( ! $this->is_empty( $value ) ) {
				$data[ $property ] = $value;
			} elseif ( isset( $data[ $property ] ) && $this->is_empty( $data[ $property ] ) ) {
				unset( $data[ $property ] );
			}
		}

		$locations = $values['locations'];
		if ( ! empty( $data['@id'] ) && is_string( $data['@id'] ) ) {
			foreach ( $locations as $index => $location ) {
				$locations[ $index ]['branchOf'] = array( '@id' => $data['@id'] );
			}
		}

		$fallback_properties = array(
			'hasMap'             => $values['has_map'],
			'hasOfferCatalog'    => $values['offer_catalog'],
			'subOrganization'    => $locations,
			'founder'            => $values['founders'],
			'parentOrganization' => $values['parent_organization'],
		);

		foreach ( $fallback_properties as $property => $value ) {
			$data = $this->apply_sourced_value( $data, $property, $value, 'automatic' );
		}

		$data = $this->apply_type_specific_values( $data, $options, $values );

		return $this->remove_empty_values( $data );
	}

	/**
	 * Get plugin values represented as Schema properties.
	 *
	 * @param array $options Saved options.
	 * @return array
	 */
	private function plugin_values( array $options ) {
		$url = $options['url'];
		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		return array(
			'name'           => $options['name'],
			'alternate_name' => $options['alternate_name'],
			'url'            => $url,
			'logo'           => $options['logo'],
			'image'          => $options['image'],
			'description'    => $options['description'],
			'telephone'      => $options['telephone'],
			'email'          => $options['email'],
			'legal_name'     => $options['legal_name'],
			'same_as'        => self::parse_url_list( $options['same_as'] ),
			'vat_id'         => $options['vat_id'],
			'tax_id'         => $options['tax_id'],
			'slogan'         => $options['slogan'],
			'number_of_employees' => '' === $options['number_of_employees'] ? array() : array(
				'@type' => 'QuantitativeValue',
				'value' => (int) $options['number_of_employees'],
			),
			'contact_points'  => self::parse_contact_points( $options['contact_points'] ),
			'iso6523_code'    => $options['iso6523_code'],
			'global_location_number' => $options['global_location_number'],
			'lei_code'        => $options['lei_code'],
			'naics'           => $options['naics'],
			'duns'            => $options['duns'],
			'founding_date'  => $options['founding_date'],
			'price_range'    => $options['price_range'],
			'address'        => $this->build_address( $options ),
			'geo'            => $this->build_geo( $options ),
			'area_served'    => self::parse_areas( $options['area_served'] ),
			'languages'      => self::parse_languages( $options['languages'] ),
			'opening_hours'  => self::build_opening_hours( $options['opening_hours'] ),
			'special_hours'  => self::parse_special_hours( $options['special_hours'] ),
			'identifiers'    => self::parse_identifiers( $options['identifiers'] ),
			'has_map'        => $options['has_map'],
			'currencies_accepted' => implode( ', ', self::parse_currency_list( $options['currencies_accepted'] ) ),
			'payment_accepted' => implode( ', ', self::parse_simple_list( $options['payment_accepted'] ) ),
			'offer_catalog'  => self::parse_services( $options['services'] ),
			'locations'      => $this->parse_locations( $options['locations'], $options['location_hours'], $options['schema_type'] ),
			'founders'       => self::parse_people( $options['founders'] ),
			'parent_organization' => $this->build_parent_organization( $options ),
			'serves_cuisine' => self::parse_simple_list( $options['serves_cuisine'] ),
			'menu'            => $options['menu'],
			'accepts_reservations' => self::parse_boolean_url_text( $options['accepts_reservations'] ),
			'checkin_time'    => $options['checkin_time'],
			'checkout_time'   => $options['checkout_time'],
			'star_rating'     => '' === $options['star_rating'] ? array() : array( '@type' => 'Rating', 'ratingValue' => (float) $options['star_rating'] ),
			'amenities'       => self::parse_amenities( $options['amenities'] ),
			'medical_specialties' => self::parse_simple_list( $options['medical_specialties'] ),
		);
	}

	/** Add properties which only apply to selected business families. */
	private function apply_type_specific_values( array $data, array $options, array $values ) {
		$type = $options['schema_type'];

		if ( in_array( $type, array( 'Restaurant', 'CafeOrCoffeeShop', 'BarOrPub', 'Bakery' ), true ) ) {
			$data = $this->apply_sourced_value( $data, 'servesCuisine', $values['serves_cuisine'], 'automatic' );
			$data = $this->apply_sourced_value( $data, 'menu', $values['menu'], 'automatic' );
			$data = $this->apply_sourced_value( $data, 'acceptsReservations', $values['accepts_reservations'], 'automatic' );
		}

		if ( 'Hotel' === $type ) {
			$data = $this->apply_sourced_value( $data, 'checkinTime', $values['checkin_time'], 'automatic' );
			$data = $this->apply_sourced_value( $data, 'checkoutTime', $values['checkout_time'], 'automatic' );
			$data = $this->apply_sourced_value( $data, 'starRating', $values['star_rating'], 'automatic' );
			$data = $this->apply_sourced_value( $data, 'amenityFeature', $values['amenities'], 'automatic' );
		}

		if ( in_array( $type, array( 'MedicalClinic', 'Dentist' ), true ) ) {
			$data = $this->apply_sourced_value( $data, 'medicalSpecialty', $values['medical_specialties'], 'automatic' );
		}

		return $data;
	}

	/** Whether the selected type represents a physical/local business. */
	private function is_local_business_type( $type ) {
		return ! in_array( $type, self::ORGANIZATION_ONLY_TYPES, true );
	}

	/** Apply automatic or explicit plugin precedence. */
	private function apply_sourced_value( array $data, $property, $plugin_value, $source ) {
		if ( 'plugin' === $source ) {
			if ( $this->is_empty( $plugin_value ) ) {
				unset( $data[ $property ] );
			} else {
				$data[ $property ] = $plugin_value;
			}
			return $data;
		}

		if ( ! isset( $data[ $property ] ) || $this->is_empty( $data[ $property ] ) ) {
			if ( ! $this->is_empty( $plugin_value ) ) {
				$data[ $property ] = $plugin_value;
			} else {
				unset( $data[ $property ] );
			}
		}

		return $data;
	}

	/** Get configured source for a field. */
	private function source_for( array $options, $field ) {
		return isset( $options['sources'][ $field ] ) && 'plugin' === $options['sources'][ $field ] ? 'plugin' : 'automatic';
	}

	/** Build PostalAddress, omitting empty parts. */
	private function build_address( array $options ) {
		$address = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $options['street_address'],
			'addressLocality' => $options['address_locality'],
			'addressRegion'   => $options['address_region'],
			'postalCode'      => $options['postal_code'],
			'addressCountry'  => $options['address_country'],
		);

		$address = $this->remove_empty_values( $address );
		return count( $address ) > 1 ? $address : array();
	}

	/** Build GeoCoordinates only when both coordinates exist. */
	private function build_geo( array $options ) {
		if ( '' === $options['latitude'] || '' === $options['longitude'] ) {
			return array();
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $options['latitude'],
			'longitude' => (float) $options['longitude'],
		);
	}

	/** Build an optional parent Organization reference. */
	private function build_parent_organization( array $options ) {
		$parent = array(
			'@type' => 'Organization',
			'name'  => $options['parent_organization_name'],
			'url'   => $options['parent_organization_url'],
		);

		$parent = $this->remove_empty_values( $parent );
		return count( $parent ) > 1 ? $parent : array();
	}

	/** Parse one branch per line into nested LocalBusiness entities. */
	private function parse_locations( $value, $hours_value, $business_type ) {
		$output    = array();
		$parent_id = untrailingslashit( home_url( '/' ) ) . '/#organization';
		$lines     = preg_split( '/[\r\n]+/', (string) $value );
		$hours     = self::parse_location_hours( $hours_value );

		foreach ( $lines as $line ) {
			$parts = array_pad( array_map( 'trim', explode( '|', $line, 10 ) ), 10, '' );
			if ( '' === $parts[0] ) {
				continue;
			}

			$location_type = in_array( $business_type, self::ORGANIZATION_ONLY_TYPES, true ) ? 'LocalBusiness' : $business_type;
			$location_id   = untrailingslashit( home_url( '/' ) ) . '/#location-' . substr( md5( strtolower( $parts[0] . '|' . $parts[1] . '|' . $parts[4] ) ), 0, 12 );
			$country       = 1 === preg_match( '/^[A-Za-z]{2}$/', $parts[5] ) ? strtoupper( $parts[5] ) : '';
			$address       = $this->remove_empty_values(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $parts[1],
					'addressLocality' => $parts[2],
					'addressRegion'   => $parts[3],
					'postalCode'      => $parts[4],
					'addressCountry'  => $country,
				)
			);

			$location = array(
				'@type'    => $location_type,
				'@id'      => $location_id,
				'name'     => $parts[0],
				'address'  => count( $address ) > 1 ? $address : array(),
				'telephone' => $parts[8],
				'url'      => esc_url_raw( $parts[9] ),
				'branchOf' => array( '@id' => $parent_id ),
			);

			$hours_key = strtolower( $parts[0] );
			if ( ! empty( $hours[ $hours_key ] ) ) {
				$location['openingHoursSpecification'] = $hours[ $hours_key ];
			}

			if ( is_numeric( $parts[6] ) && is_numeric( $parts[7] ) && (float) $parts[6] >= -90 && (float) $parts[6] <= 90 && (float) $parts[7] >= -180 && (float) $parts[7] <= 180 ) {
				$location['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $parts[6],
					'longitude' => (float) $parts[7],
				);
			}

			$output[] = $this->remove_empty_values( $location );
		}

		return $output;
	}

	/** Ensure a single Organization-derived type list. */
	private function merge_types( $existing, $business_type ) {
		$types = is_array( $existing ) ? $existing : array( $existing );
		array_unshift( $types, 'Organization' );

		if ( 'Organization' !== $business_type ) {
			$types[] = $business_type;
		}

		$types = array_values( array_unique( array_filter( $types, 'is_string' ) ) );
		return count( $types ) === 1 ? $types[0] : $types;
	}

	/** Whether a Yoast graph piece represents an organization. */
	private function is_organization_piece( array $piece ) {
		$types         = isset( $piece['@type'] ) ? (array) $piece['@type'] : array();
		$id            = isset( $piece['@id'] ) && is_string( $piece['@id'] ) ? $piece['@id'] : '';
		$options       = self::get_options();
		$business_type = $options['schema_type'];

		return in_array( 'Organization', $types, true )
			|| in_array( 'LocalBusiness', $types, true )
			|| in_array( $business_type, $types, true )
			|| 1 === preg_match( '~#(?:/schema/)?organization(?:/|$)~i', $id );
	}

	/** Respect enable and display-scope settings. */
	private function should_output() {
		$options = self::get_options();
		if ( empty( $options['enabled'] ) ) {
			return false;
		}

		return 'homepage' !== $options['display_scope'] || is_front_page();
	}

	/** Remove empty values recursively without removing numeric zero. */
	private function remove_empty_values( array $data ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = $this->remove_empty_values( $value );
				$data[ $key ] = $value;
			}

			if ( $this->is_empty( $data[ $key ] ) ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}

	/** Check blank scalar or empty array. */
	private function is_empty( $value ) {
		return null === $value || '' === $value || ( is_array( $value ) && array() === $value );
	}

	/** Get defaults merged with stored options. */
	public static function get_options() {
		$saved = get_option( self::OPTION_NAME, array() );
		return self::merge_options( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/** Recursive option merge which preserves the expected shape. */
	private static function merge_options( array $saved, array $defaults ) {
		$result = $defaults;
		foreach ( $defaults as $key => $default ) {
			if ( array_key_exists( $key, $saved ) ) {
				$result[ $key ] = is_array( $default ) && is_array( $saved[ $key ] )
					? self::merge_options( $saved[ $key ], $default )
					: $saved[ $key ];
			}
		}
		return $result;
	}

	/** Default option values. */
	public static function defaults() {
		$sources = array();
		foreach ( self::SOURCE_FIELDS as $field ) {
			$sources[ $field ] = 'automatic';
		}

		$hours = array();
		foreach ( self::days() as $day => $label ) {
			$hours[ $day ] = array(
				'enabled'  => 0,
				'opens'    => '09:00',
				'closes'   => '17:00',
				'opens_2'  => '',
				'closes_2' => '',
			);
		}

		return array(
			'enabled'          => 1,
			'display_scope'    => 'sitewide',
			'site_graph_enabled'     => 1,
			'breadcrumbs_enabled'    => 1,
			'breadcrumbs_home_label' => '',
			'schema_type'      => 'LocalBusiness',
			'name'             => '',
			'alternate_name'   => '',
			'legal_name'       => '',
			'description'      => '',
			'founding_date'    => '',
			'url'              => '',
			'logo'             => '',
			'image'            => '',
			'telephone'        => '',
			'email'            => '',
			'price_range'      => '',
			'street_address'   => '',
			'address_locality' => '',
			'address_region'   => '',
			'postal_code'      => '',
			'address_country'  => '',
			'latitude'         => '',
			'longitude'        => '',
			'area_served'      => '',
			'languages'        => '',
			'same_as'          => '',
			'vat_id'           => '',
			'tax_id'           => '',
			'slogan'           => '',
			'number_of_employees' => '',
			'contact_points'    => '',
			'has_map'           => '',
			'currencies_accepted' => '',
			'payment_accepted'  => '',
			'special_hours'     => '',
			'services'          => '',
			'locations'         => '',
			'location_hours'    => '',
			'founders'          => '',
			'parent_organization_name' => '',
			'parent_organization_url' => '',
			'iso6523_code'      => '',
			'global_location_number' => '',
			'lei_code'          => '',
			'naics'             => '',
			'duns'              => '',
			'serves_cuisine'    => '',
			'menu'              => '',
			'accepts_reservations' => '',
			'checkin_time'      => '',
			'checkout_time'     => '',
			'star_rating'       => '',
			'amenities'         => '',
			'medical_specialties' => '',
			'identifiers'      => '',
			'opening_hours'    => $hours,
			'sources'          => $sources,
		);
	}

	/** Supported days. */
	public static function days() {
		return array(
			'Monday'    => __( 'Monday', 'local-business-schema' ),
			'Tuesday'   => __( 'Tuesday', 'local-business-schema' ),
			'Wednesday' => __( 'Wednesday', 'local-business-schema' ),
			'Thursday'  => __( 'Thursday', 'local-business-schema' ),
			'Friday'    => __( 'Friday', 'local-business-schema' ),
			'Saturday'  => __( 'Saturday', 'local-business-schema' ),
			'Sunday'    => __( 'Sunday', 'local-business-schema' ),
		);
	}

	/** Parse comma/newline list. */
	public static function parse_simple_list( $value ) {
		$items = preg_split( '/[\r\n,]+/', (string) $value );
		$items = array_map( 'trim', $items );
		return array_values( array_unique( array_filter( $items ) ) );
	}

	/** Parse and validate one URL per line. */
	public static function parse_url_list( $value ) {
		$urls = self::parse_simple_list( $value );
		return array_values( array_filter( array_map( 'esc_url_raw', $urls ) ) );
	}

	/** Parse and normalize BCP 47-like language tags. */
	public static function parse_languages( $value ) {
		$languages = self::parse_simple_list( $value );
		return array_values(
			array_filter(
				$languages,
				function ( $language ) {
					return 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language );
				}
			)
		);
	}

	/** Parse ISO 4217 currency codes. */
	public static function parse_currency_list( $value ) {
		$currencies = array_map( 'strtoupper', self::parse_simple_list( $value ) );
		return array_values(
			array_unique(
				array_filter(
					$currencies,
					function ( $currency ) {
						return 1 === preg_match( '/^[A-Z]{3}$/', $currency );
					}
				)
			)
		);
	}

	/** Parse `Type|Telephone|Email|Languages|Areas` contact points. */
	public static function parse_contact_points( $value ) {
		$output = array();
		$lines  = preg_split( '/[\r\n]+/', (string) $value );

		foreach ( $lines as $line ) {
			$parts = array_pad( array_map( 'trim', explode( '|', $line, 5 ) ), 5, '' );
			if ( '' === $parts[0] && '' === $parts[1] && '' === $parts[2] ) {
				continue;
			}

			$contact = array(
				'@type'             => 'ContactPoint',
				'contactType'        => $parts[0],
				'telephone'          => $parts[1],
				'email'              => filter_var( $parts[2], FILTER_VALIDATE_EMAIL ) ? $parts[2] : '',
				'availableLanguage'  => self::parse_languages( $parts[3] ),
				'areaServed'         => self::parse_simple_list( $parts[4] ),
			);

			$output[] = array_filter(
				$contact,
				function ( $item ) {
					return '' !== $item && array() !== $item;
				}
			);
		}

		return $output;
	}

	/** Parse `Start|End|Opens|Closes` exceptional hours. */
	public static function parse_special_hours( $value ) {
		$output = array();
		$lines  = preg_split( '/[\r\n]+/', (string) $value );

		foreach ( $lines as $line ) {
			$parts = array_pad( array_map( 'trim', explode( '|', $line, 4 ) ), 4, '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $parts[0] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $parts[1] ) ) {
				continue;
			}
			if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $parts[2] ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $parts[3] ) ) {
				continue;
			}

			$output[] = array(
				'@type'       => 'OpeningHoursSpecification',
				'validFrom'   => $parts[0],
				'validThrough' => $parts[1],
				'opens'       => $parts[2],
				'closes'      => $parts[3],
			);
		}

		return $output;
	}

	/** Parse `Location|Days|Opens|Closes` hours grouped by location name. */
	public static function parse_location_hours( $value ) {
		$output       = array();
		$allowed_days = array_keys( self::days() );
		$lines        = preg_split( '/[\r\n]+/', (string) $value );

		foreach ( $lines as $line ) {
			$parts = array_pad( array_map( 'trim', explode( '|', $line, 4 ) ), 4, '' );
			$days  = array_values( array_intersect( self::parse_simple_list( $parts[1] ), $allowed_days ) );
			if ( '' === $parts[0] || ! $days || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $parts[2] ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $parts[3] ) ) {
				continue;
			}

			$key = strtolower( $parts[0] );
			if ( ! isset( $output[ $key ] ) ) {
				$output[ $key ] = array();
			}
			$output[ $key ][] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $days,
				'opens'     => $parts[2],
				'closes'    => $parts[3],
			);
		}

		return $output;
	}

	/** Parse service lines into an OfferCatalog. */
	public static function parse_services( $value ) {
		$offers = array();
		$lines  = preg_split( '/[\r\n]+/', (string) $value );

		foreach ( $lines as $line ) {
			$parts = array_pad( array_map( 'trim', explode( '|', $line, 3 ) ), 3, '' );
			if ( '' === $parts[0] ) {
				continue;
			}

			$service = array_filter(
				array(
					'@type'       => 'Service',
					'name'        => $parts[0],
					'url'         => esc_url_raw( $parts[1] ),
					'description' => $parts[2],
				)
			);
			$offers[] = array( '@type' => 'Offer', 'itemOffered' => $service );
		}

		if ( ! $offers ) {
			return array();
		}

		return array(
			'@type'           => 'OfferCatalog',
			'itemListElement' => $offers,
		);
	}

	/** Parse `Name|URL` people. */
	public static function parse_people( $value ) {
		$output = array();
		$lines  = preg_split( '/[\r\n]+/', (string) $value );
		foreach ( $lines as $line ) {
			$parts = array_pad( array_map( 'trim', explode( '|', $line, 2 ) ), 2, '' );
			if ( '' !== $parts[0] ) {
				$output[] = array_filter( array( '@type' => 'Person', 'name' => $parts[0], 'url' => esc_url_raw( $parts[1] ) ) );
			}
		}
		return $output;
	}

	/** Convert common boolean strings or keep a valid URL/text value. */
	public static function parse_boolean_url_text( $value ) {
		$value = trim( (string) $value );
		if ( 'true' === strtolower( $value ) ) {
			return true;
		}
		if ( 'false' === strtolower( $value ) ) {
			return false;
		}
		return $value;
	}

	/** Parse amenity names into LocationFeatureSpecification objects. */
	public static function parse_amenities( $value ) {
		$output = array();
		foreach ( self::parse_simple_list( $value ) as $amenity ) {
			$output[] = array( '@type' => 'LocationFeatureSpecification', 'name' => $amenity, 'value' => true );
		}
		return $output;
	}

	/** Parse `Type|Name` service areas. */
	public static function parse_areas( $value ) {
		$areas   = array();
		$allowed = array( 'AdministrativeArea', 'City', 'Country', 'Place', 'State' );
		$lines   = preg_split( '/[\r\n]+/', (string) $value );

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 1 === count( $parts ) ) {
				$type = 'AdministrativeArea';
				$name = $parts[0];
			} else {
				$type = in_array( $parts[0], $allowed, true ) ? $parts[0] : 'AdministrativeArea';
				$name = $parts[1];
			}

			if ( '' !== $name ) {
				$areas[] = array( '@type' => $type, 'name' => $name );
			}
		}

		return $areas;
	}

	/** Build opening-hour specifications. */
	public static function build_opening_hours( $hours ) {
		$output = array();
		if ( ! is_array( $hours ) ) {
			return $output;
		}

		foreach ( self::days() as $day => $label ) {
			$row = isset( $hours[ $day ] ) && is_array( $hours[ $day ] ) ? $hours[ $day ] : array();
			if ( empty( $row['enabled'] ) || empty( $row['opens'] ) || empty( $row['closes'] ) ) {
				continue;
			}

			$output[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $day,
				'opens'     => $row['opens'],
				'closes'    => $row['closes'],
			);

			if ( ! empty( $row['opens_2'] ) && ! empty( $row['closes_2'] ) ) {
				$output[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day,
					'opens'     => $row['opens_2'],
					'closes'    => $row['closes_2'],
				);
			}
		}

		return $output;
	}

	/** Parse `Name|Value` identifiers into PropertyValue nodes. */
	public static function parse_identifiers( $value ) {
		$output = array();
		$lines  = preg_split( '/[\r\n]+/', (string) $value );

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
				$output[] = array(
					'@type' => 'PropertyValue',
					'name'  => $parts[0],
					'value' => $parts[1],
				);
			}
		}

		return $output;
	}
}
