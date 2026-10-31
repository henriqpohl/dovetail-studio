<?php
/**
 * Dovetail Schema SEO WooCommerce runtime.
 *
 * @package DovetailStudio\SchemaSeoWooCommerce
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'DOVETAIL_STUDIO_VERSION' ) ) {
	exit;
}

define( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION', 'dovetail_schema_seo_woocommerce_settings' );

/** Return true when WooCommerce is active. */
function dovetail_schema_seo_woocommerce_has_woocommerce() {
	return \DovetailStudio\Core\Seo\Environment::has_woocommerce();
}

/** Return true when Yoast SEO is active. */
function dovetail_schema_seo_woocommerce_has_yoast() {
	return \DovetailStudio\Core\Seo\Environment::has_yoast();
}

/** Detect the official Yoast WooCommerce SEO extension to avoid overlapping schema work. */
function dovetail_schema_seo_woocommerce_has_yoast_woocommerce() {
	return \DovetailStudio\Core\Seo\Environment::has_yoast_woocommerce();
}

/** Default settings. */
function dovetail_schema_seo_woocommerce_defaults() {
	return array(
		'enabled'                  => 1,
		'product_graph'            => 1,
		'breadcrumbs'              => 1,
		'noindex_utility_pages'    => 1,
		'canonical_shop_archives'  => 1,
		'sitemap_exclude_hidden'   => 1,
		'brand_attribute'          => 'pa_brand',
		'manufacturer_attribute'   => '',
		'color_attribute'          => 'pa_color',
		'material_attribute'       => 'pa_material',
		'size_attribute'           => 'pa_size',
		'gtin_meta_key'            => '_gtin',
		'mpn_meta_key'             => '_mpn',
		'merchant_returns_enabled' => 0,
		'return_country'           => '',
		'return_days'              => 14,
		'return_method'            => 'https://schema.org/ReturnByMail',
		'return_fees'              => 'https://schema.org/FreeReturn',
		'shipping_enabled'         => 0,
		'shipping_country'         => '',
		'shipping_rate'            => '',
		'handling_min'             => 0,
		'handling_max'             => 1,
		'transit_min'              => 1,
		'transit_max'              => 5,
	);
}

/**
 * A two-letter country code, or the WooCommerce store country when the value is blank.
 *
 * @param string $value Saved country code.
 * @return string Upper-case ISO 3166-1 alpha-2 code, or empty.
 */
function dovetail_schema_seo_woocommerce_country( $value ) {
	$value = strtoupper( trim( (string) $value ) );

	if ( '' === $value
		&& function_exists( 'WC' )
		&& WC()
		&& isset( WC()->countries )
		&& is_object( WC()->countries )
	) {
		$value = strtoupper( (string) WC()->countries->get_base_country() );
	}

	return 1 === preg_match( '/^[A-Z]{2}$/', $value ) ? $value : '';
}

/** Saved settings. */
function dovetail_schema_seo_woocommerce_get_settings() {
	// Read for every product node and hook: merged with the defaults once while the saved option is unchanged.
	static $memo = null, $raw = null;
	$saved       = get_option( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION, array() );

	if ( null === $memo || $saved !== $raw ) {
		$raw  = $saved;
		$memo = wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			dovetail_schema_seo_woocommerce_defaults()
		);
	}

	return $memo;
}

/** Sanitize settings. */
function dovetail_schema_seo_woocommerce_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = dovetail_schema_seo_woocommerce_defaults();
	$clean    = $defaults;

	$clean['enabled']                  = empty( $input['enabled'] ) ? 0 : 1;
	$clean['product_graph']            = empty( $input['product_graph'] ) ? 0 : 1;
	$clean['breadcrumbs']              = empty( $input['breadcrumbs'] ) ? 0 : 1;
	$clean['noindex_utility_pages']    = empty( $input['noindex_utility_pages'] ) ? 0 : 1;
	$clean['canonical_shop_archives']  = empty( $input['canonical_shop_archives'] ) ? 0 : 1;
	$clean['sitemap_exclude_hidden']   = empty( $input['sitemap_exclude_hidden'] ) ? 0 : 1;
	$clean['brand_attribute']          = sanitize_key( $input['brand_attribute'] ?? '' );
	$clean['manufacturer_attribute']   = sanitize_key( $input['manufacturer_attribute'] ?? '' );
	$clean['color_attribute']          = sanitize_key( $input['color_attribute'] ?? '' );
	$clean['material_attribute']       = sanitize_key( $input['material_attribute'] ?? '' );
	$clean['size_attribute']           = sanitize_key( $input['size_attribute'] ?? '' );
	$clean['gtin_meta_key']            = sanitize_text_field( $input['gtin_meta_key'] ?? '' );
	$clean['mpn_meta_key']             = sanitize_text_field( $input['mpn_meta_key'] ?? '' );
	$clean['merchant_returns_enabled'] = empty( $input['merchant_returns_enabled'] ) ? 0 : 1;
	$clean['return_country']           = preg_match(
		'/^[A-Za-z]{2}$/',
		trim( (string) ( $input['return_country'] ?? '' ) )
	)
		? strtoupper( trim( $input['return_country'] ) )
		: '';
	$clean['return_days']              = max( 0, min( 365, absint( $input['return_days'] ?? 14 ) ) );

	$return_methods = array(
		'https://schema.org/ReturnByMail',
		'https://schema.org/ReturnInStore',
		'https://schema.org/ReturnAtKiosk',
	);
	$return_fees    = array(
		'https://schema.org/FreeReturn',
		'https://schema.org/ReturnFeesCustomerResponsibility',
		'https://schema.org/ReturnShippingFees',
	);

	$clean['return_method'] = in_array( $input['return_method'] ?? '', $return_methods, true )
		? $input['return_method']
		: $defaults['return_method'];
	$clean['return_fees']   = in_array( $input['return_fees'] ?? '', $return_fees, true )
		? $input['return_fees']
		: $defaults['return_fees'];

	$clean['shipping_enabled'] = empty( $input['shipping_enabled'] ) ? 0 : 1;
	$clean['shipping_country'] = preg_match(
		'/^[A-Za-z]{2}$/',
		trim( (string) ( $input['shipping_country'] ?? '' ) )
	)
		? strtoupper( trim( $input['shipping_country'] ) )
		: '';
	$rate                      = str_replace( ',', '.', trim( (string) ( $input['shipping_rate'] ?? '' ) ) );
	$clean['shipping_rate']    = is_numeric( $rate ) && (float) $rate >= 0
		? (string) round( (float) $rate, 2 )
		: '';

	foreach ( array( 'handling', 'transit' ) as $time ) {
		$min                     = max( 0, min( 60, absint( $input[ $time . '_min' ] ?? $defaults[ $time . '_min' ] ) ) );
		$max                     = max( 0, min( 60, absint( $input[ $time . '_max' ] ?? $defaults[ $time . '_max' ] ) ) );
		$clean[ $time . '_min' ] = min( $min, $max );
		$clean[ $time . '_max' ] = max( $min, $max );
	}

	return $clean;
}

/** Register module settings. */
function dovetail_schema_seo_woocommerce_register_settings() {
	register_setting(
		'dovetail_schema_seo_woocommerce',
		DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'dovetail_schema_seo_woocommerce_sanitize_settings',
			'default'           => dovetail_schema_seo_woocommerce_defaults(),
		)
	);
}

add_action( 'admin_init', 'dovetail_schema_seo_woocommerce_register_settings' );

/** Register the module under Dovetail Studio. */
function dovetail_schema_seo_woocommerce_admin_menu() {
	add_submenu_page(
		'dovetail-studio',
		__( 'Schema SEO WooCommerce', 'dovetail-studio' ),
		__( 'Schema SEO WooCommerce', 'dovetail-studio' ),
		'manage_options',
		'dovetail-schema-seo-woocommerce',
		'dovetail_schema_seo_woocommerce_render_settings_page'
	);
}

add_action( 'admin_menu', 'dovetail_schema_seo_woocommerce_admin_menu', 20 );

/** Get a product attribute value as a readable string. */
function dovetail_schema_seo_woocommerce_get_attribute_value( $product, $attribute ) {
	if ( ! $product || '' === $attribute ) {
		return '';
	}

	$value = $product->get_attribute( $attribute );

	return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
}

/** Read a custom product identifier from product or parent meta. */
function dovetail_schema_seo_woocommerce_get_meta_identifier( $product, $meta_key ) {
	if ( ! $product || '' === $meta_key ) {
		return '';
	}

	$value = (string) $product->get_meta( $meta_key, true );

	if ( '' === trim( $value ) && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$value = (string) get_post_meta( $product->get_parent_id(), $meta_key, true );
	}

	return trim( wp_strip_all_tags( $value ) );
}

/** Build a MerchantReturnPolicy node from saved settings. */
function dovetail_schema_seo_woocommerce_return_policy() {
	$settings = dovetail_schema_seo_woocommerce_get_settings();

	$country = dovetail_schema_seo_woocommerce_country( $settings['return_country'] );

	// Google needs the country the policy applies to; without one no policy is printed.
	if ( empty( $settings['merchant_returns_enabled'] ) || '' === $country ) {
		return array();
	}

	return array(
		'@type'                => 'MerchantReturnPolicy',
		'applicableCountry'    => $country,
		'returnPolicyCountry'  => $country,
		'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'   => (int) $settings['return_days'],
		'returnMethod'         => $settings['return_method'],
		'returnFees'           => $settings['return_fees'],
	);
}

/**
 * OfferShippingDetails from the saved flat shipping settings, or an empty array.
 *
 * @return array
 */
function dovetail_schema_seo_woocommerce_shipping_details() {
	$settings = dovetail_schema_seo_woocommerce_get_settings();
	$country  = dovetail_schema_seo_woocommerce_country( $settings['shipping_country'] );
	$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';

	if ( empty( $settings['shipping_enabled'] )
		|| '' === $country
		|| '' === (string) $settings['shipping_rate']
		|| '' === $currency
	) {
		return array();
	}

	$days = static function ( $min, $max ) {
		return array(
			'@type'    => 'QuantitativeValue',
			'minValue' => (int) $min,
			'maxValue' => (int) $max,
			'unitCode' => 'DAY',
		);
	};

	return array(
		'@type'               => 'OfferShippingDetails',
		'shippingRate'        => array(
			'@type'    => 'MonetaryAmount',
			'value'    => (float) $settings['shipping_rate'],
			'currency' => $currency,
		),
		'shippingDestination' => array(
			'@type'          => 'DefinedRegion',
			'addressCountry' => $country,
		),
		'deliveryTime'        => array(
			'@type'        => 'ShippingDeliveryTime',
			'handlingTime' => $days( $settings['handling_min'], $settings['handling_max'] ),
			'transitTime'  => $days( $settings['transit_min'], $settings['transit_max'] ),
		),
	);
}

/**
 * Add the return policy and shipping details to one offer, keeping values already set.
 *
 * @param array $offer    Offer.
 * @param array $policy   MerchantReturnPolicy, or empty.
 * @param array $shipping OfferShippingDetails, or empty.
 * @return array
 */
function dovetail_schema_seo_woocommerce_offer_terms( array $offer, array $policy, array $shipping ) {
	if ( $policy && empty( $offer['hasMerchantReturnPolicy'] ) ) {
		$offer['hasMerchantReturnPolicy'] = $policy;
	}

	if ( $shipping && empty( $offer['shippingDetails'] ) ) {
		$offer['shippingDetails'] = $shipping;
	}

	return $offer;
}

/** Add GTIN using the most specific Schema.org property when possible. */
function dovetail_schema_seo_woocommerce_add_gtin( &$data, $gtin ) {
	$digits = preg_replace( '/\D+/', '', (string) $gtin );

	if ( '' === $digits ) {
		return;
	}

	$property = 'gtin';

	if ( in_array( strlen( $digits ), array( 8, 12, 13, 14 ), true ) ) {
		$property = 'gtin' . strlen( $digits );
	}

	if ( empty( $data[ $property ] ) ) {
		$data[ $property ] = $digits;
	}
}

/** Enrich one Product/ProductGroup schema node. */
function dovetail_schema_seo_woocommerce_enrich_product_node( $data, $product ) {
	if ( ! is_array( $data ) || ! $product ) {
		return $data;
	}

	$settings = dovetail_schema_seo_woocommerce_get_settings();

	$brand = dovetail_schema_seo_woocommerce_get_attribute_value( $product, $settings['brand_attribute'] );

	if ( $brand && empty( $data['brand'] ) ) {
		$data['brand'] = array(
			'@type' => 'Brand',
			'name'  => $brand,
		);
	}

	$manufacturer = dovetail_schema_seo_woocommerce_get_attribute_value(
		$product,
		$settings['manufacturer_attribute']
	);

	if ( $manufacturer && empty( $data['manufacturer'] ) ) {
		$data['manufacturer'] = array(
			'@type' => 'Organization',
			'name'  => $manufacturer,
		);
	}

	foreach ( array( 'color', 'material', 'size' ) as $property ) {
		$attribute = $settings[ $property . '_attribute' ];
		$value     = dovetail_schema_seo_woocommerce_get_attribute_value( $product, $attribute );

		if ( $value && empty( $data[ $property ] ) ) {
			$data[ $property ] = $value;
		}
	}

	$gtin = dovetail_schema_seo_woocommerce_get_meta_identifier( $product, $settings['gtin_meta_key'] );

	if ( $gtin ) {
		dovetail_schema_seo_woocommerce_add_gtin( $data, $gtin );
	}

	$mpn = dovetail_schema_seo_woocommerce_get_meta_identifier( $product, $settings['mpn_meta_key'] );

	if ( $mpn && empty( $data['mpn'] ) ) {
		$data['mpn'] = $mpn;
	}

	if ( $product->is_type( 'variable' ) ) {
		if ( empty( $data['productGroupID'] ) ) {
			$sku                    = (string) $product->get_sku();
			$data['productGroupID'] = '' !== $sku ? $sku : (string) $product->get_id();
		}

		$varies_by       = array();
		$variation_attrs = array_keys( $product->get_variation_attributes() );
		$known_variants  = array(
			'color_attribute'    => 'https://schema.org/color',
			'material_attribute' => 'https://schema.org/material',
			'size_attribute'     => 'https://schema.org/size',
		);

		foreach ( $known_variants as $setting_key => $schema_property ) {
			if ( ! empty( $settings[ $setting_key ] )
				&& in_array( $settings[ $setting_key ], $variation_attrs, true )
			) {
				$varies_by[] = $schema_property;
			}
		}

		if ( $varies_by && empty( $data['variesBy'] ) ) {
			$data['variesBy'] = array_values( array_unique( $varies_by ) );
		}

		// Google reads productGroupID, variesBy and hasVariant only on a ProductGroup,
		// with the price on each variant. WooCommerce prints a Product with an AggregateOffer.
		$variants = empty( $data['hasVariant'] )
			? dovetail_schema_seo_woocommerce_variants( $product, $data, $settings )
			: $data['hasVariant'];

		if ( $variants ) {
			$data['@type']      = 'ProductGroup';
			$data['hasVariant'] = $variants;
			unset( $data['offers'], $data['sku'] );
		}
	}

	$policy   = dovetail_schema_seo_woocommerce_return_policy();
	$shipping = dovetail_schema_seo_woocommerce_shipping_details();

	if ( ( $policy || $shipping ) && ! empty( $data['offers'] ) && is_array( $data['offers'] ) ) {
		if ( isset( $data['offers']['@type'] ) ) {
			$data['offers'] = dovetail_schema_seo_woocommerce_offer_terms(
				$data['offers'],
				$policy,
				$shipping
			);
		} else {
			foreach ( $data['offers'] as &$offer ) {
				if ( is_array( $offer ) ) {
					$offer = dovetail_schema_seo_woocommerce_offer_terms( $offer, $policy, $shipping );
				}
			}

			unset( $offer );
		}
	}

	return $data;
}

/**
 * Variant Product nodes of a variable product, for its ProductGroup.
 *
 * @param WC_Product_Variable $product  Variable product.
 * @param array               $group    ProductGroup data (for productGroupID and the shared offer fields).
 * @param array               $settings Module settings.
 * @return array[] Variant nodes; empty when none is purchasable.
 */
function dovetail_schema_seo_woocommerce_variants( $product, array $group, array $settings ) {
	$variants = array();
	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
	$policy   = dovetail_schema_seo_woocommerce_return_policy();
	$shipping = dovetail_schema_seo_woocommerce_shipping_details();
	$shared   = isset( $group['offers'] ) && is_array( $group['offers'] ) ? $group['offers'] : array();
	$shared   = isset( $shared['@type'] )
		? $shared
		: ( isset( $shared[0] ) && is_array( $shared[0] ) ? $shared[0] : array() );

	/**
	 * Filters the maximum number of variants listed in a ProductGroup.
	 *
	 * @param int        $max     Maximum. Default 50.
	 * @param WC_Product $product Variable product.
	 */
	$max = (int) apply_filters( 'dovetail_schema_seo_woocommerce_max_variants', 50, $product );

	foreach ( array_slice( $product->get_children(), 0, max( 1, $max ) ) as $child_id ) {
		$variation = wc_get_product( $child_id );

		if ( ! $variation || ! $variation->is_purchasable() || '' === (string) $variation->get_price() ) {
			continue;
		}

		$node = array(
			'@type' => 'Product',
			'@id'   => $variation->get_permalink() . '#product-' . $variation->get_id(),
			'name'  => wp_strip_all_tags( $variation->get_name() ),
			'url'   => $variation->get_permalink(),
		);

		if ( ! empty( $group['productGroupID'] ) ) {
			$node['inProductGroupWithID'] = $group['productGroupID'];
		}

		if ( $variation->get_sku() ) {
			$node['sku'] = $variation->get_sku();
		}

		$image_id = $variation->get_image_id();
		$image    = $image_id ? wp_get_attachment_url( $image_id ) : '';

		if ( $image ) {
			$node['image'] = $image;
		}

		foreach ( array( 'color', 'material', 'size' ) as $property ) {
			$attribute = $settings[ $property . '_attribute' ];
			$value     = '' !== $attribute ? $variation->get_attribute( $attribute ) : '';

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$node[ $property ] = trim( wp_strip_all_tags( $value ) );
			}
		}

		$gtin = dovetail_schema_seo_woocommerce_get_meta_identifier( $variation, $settings['gtin_meta_key'] );

		if ( $gtin ) {
			dovetail_schema_seo_woocommerce_add_gtin( $node, $gtin );
		}

		$stock = $variation->get_stock_status();
		$offer = array(
			'@type'         => 'Offer',
			'price'         => wc_format_decimal( $variation->get_price(), wc_get_price_decimals() ),
			'priceCurrency' => $currency,
			'availability'  => 'outofstock' === $stock ? 'https://schema.org/OutOfStock' : ( 'onbackorder' === $stock ? 'https://schema.org/BackOrder' : 'https://schema.org/InStock' ),
			'url'           => $variation->get_permalink(),
		);

		// Shared commercial terms WooCommerce already set on the parent offer (validity, shipping, seller).
		foreach ( array( 'priceValidUntil', 'shippingDetails', 'seller', 'itemCondition' ) as $key ) {
			if ( ! empty( $shared[ $key ] ) ) {
				$offer[ $key ] = $shared[ $key ];
			}
		}

		$node['offers'] = dovetail_schema_seo_woocommerce_offer_terms( $offer, $policy, $shipping );

		/**
		 * Filters one variant node of a ProductGroup.
		 *
		 * @param array                $node      Variant Product node.
		 * @param WC_Product_Variation $variation Variation.
		 */
		$variants[] = (array) apply_filters(
			'dovetail_schema_seo_woocommerce_variant_node',
			$node,
			$variation
		);
	}

	return $variants;
}

/** Determine whether our schema enrichment is safe to run. */
function dovetail_schema_seo_woocommerce_schema_enabled() {
	$settings = dovetail_schema_seo_woocommerce_get_settings();

	return ! empty( $settings['enabled'] )
		&& dovetail_schema_seo_woocommerce_has_woocommerce()
		&& ! dovetail_schema_seo_woocommerce_has_yoast_woocommerce();
}

/** Enrich WooCommerce's native product structured data. */
function dovetail_schema_seo_woocommerce_filter_woocommerce_product_schema( $markup, $product ) {
	if ( ! dovetail_schema_seo_woocommerce_schema_enabled() ) {
		return $markup;
	}

	return dovetail_schema_seo_woocommerce_enrich_product_node( $markup, $product );
}

add_filter(
	'woocommerce_structured_data_product',
	'dovetail_schema_seo_woocommerce_filter_woocommerce_product_schema',
	20,
	2
);

/** Check if a schema node contains a given @type. */
function dovetail_schema_seo_woocommerce_schema_type_is( $node, $type ) {
	if ( empty( $node['@type'] ) ) {
		return false;
	}

	$types = (array) $node['@type'];

	return in_array( $type, $types, true );
}

/** Enrich Product/ProductGroup nodes generated by Yoast SEO. */
function dovetail_schema_seo_woocommerce_filter_yoast_graph( $graph ) {
	if ( ! dovetail_schema_seo_woocommerce_schema_enabled()
		|| ! dovetail_schema_seo_woocommerce_has_yoast()
		|| ! is_product()
	) {
		return $graph;
	}

	$product = wc_get_product( get_queried_object_id() );

	if ( ! $product || ! is_array( $graph ) ) {
		return $graph;
	}

	foreach ( $graph as $index => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		if ( dovetail_schema_seo_woocommerce_schema_type_is( $node, 'Product' )
			|| dovetail_schema_seo_woocommerce_schema_type_is( $node, 'ProductGroup' )
		) {
			$graph[ $index ] = dovetail_schema_seo_woocommerce_enrich_product_node( $node, $product );
		}
	}

	return $graph;
}

add_filter( 'wpseo_schema_graph', 'dovetail_schema_seo_woocommerce_filter_yoast_graph', 20, 1 );

/*
 * ------------------------------------------------------------------------
 * PRODUCT IN THE SITE GRAPH
 * ------------------------------------------------------------------------
 *
 * WooCommerce prints product JSON-LD in its own script in the footer. When
 * "Product graph" is enabled, the Product is built in the head with
 * WooCommerce's own generator (and the enrichment above), placed in the
 * single site graph (Yoast's, or the Dovetail Studio standalone graph), and
 * WooCommerce's separate product, review and website output is suppressed.
 */

/**
 * Whether the Product should be placed in the site graph. Never while another
 * SEO plugin (Rank Math, SEOPress …) owns the schema: no site graph of ours is
 * printed then, so WooCommerce keeps its own product JSON-LD.
 */
function dovetail_schema_seo_woocommerce_product_graph_enabled() {
	$settings = dovetail_schema_seo_woocommerce_get_settings();

	return dovetail_schema_seo_woocommerce_schema_enabled()
		&& ! empty( $settings['product_graph'] )
		&& '' === \DovetailStudio\Core\Seo\Environment::other_seo_plugin();
}

/**
 * Store or read the last product markup generated by WooCommerce.
 *
 * @param array|null $markup Markup to store, or null to read.
 * @return array Last captured markup.
 */
function dovetail_schema_seo_woocommerce_captured_product_markup( $markup = null ) {
	static $captured = array();

	if ( is_array( $markup ) && ! empty( $markup['@type'] ) ) {
		$captured = $markup;
	}

	return $captured;
}

/**
 * Capture WooCommerce's (already enriched) product markup and keep it out of
 * WooCommerce's own JSON-LD, which ignores data without a `@type`.
 *
 * @param array $markup Product markup.
 * @return array Empty array when the product is placed in the site graph.
 */
function dovetail_schema_seo_woocommerce_hold_product_markup( $markup ) {
	if ( ! dovetail_schema_seo_woocommerce_product_graph_enabled() ) {
		return $markup;
	}

	dovetail_schema_seo_woocommerce_captured_product_markup( $markup );

	return array();
}

add_filter(
	'woocommerce_structured_data_product',
	'dovetail_schema_seo_woocommerce_hold_product_markup',
	PHP_INT_MAX,
	1
);

/**
 * Suppress WooCommerce's separate review and website JSON-LD while the
 * Product (which already carries its reviews) lives in the site graph.
 *
 * @param array $markup Review or WebSite markup.
 * @return array
 */
function dovetail_schema_seo_woocommerce_suppress_duplicate_markup( $markup ) {
	return dovetail_schema_seo_woocommerce_product_graph_enabled() ? array() : $markup;
}

add_filter(
	'woocommerce_structured_data_review',
	'dovetail_schema_seo_woocommerce_suppress_duplicate_markup',
	PHP_INT_MAX,
	1
);
add_filter(
	'woocommerce_structured_data_website',
	'dovetail_schema_seo_woocommerce_suppress_duplicate_markup',
	PHP_INT_MAX,
	1
);

/**
 * Build the Product node of the current product page for the site graph.
 *
 * @param string $webpage_id      WebPage `@id`, or empty when the graph has none.
 * @param string $organization_id Organization `@id`, or empty when the graph has none.
 * @return array Product node, or an empty array.
 */
function dovetail_schema_seo_woocommerce_get_product_node( $webpage_id, $organization_id ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'WC' ) ) {
		return array();
	}

	$product = wc_get_product( get_queried_object_id() );

	if ( ! $product ) {
		return array();
	}

	$markup = dovetail_schema_seo_woocommerce_captured_product_markup();

	if ( ! $markup ) {
		$structured_data = isset( WC()->structured_data ) ? WC()->structured_data : null;

		if ( ! is_object( $structured_data )
			|| ! method_exists( $structured_data, 'generate_product_data' )
		) {
			return array();
		}

		$structured_data->generate_product_data( $product );
		$markup = dovetail_schema_seo_woocommerce_captured_product_markup();
	}

	if ( ! $markup ) {
		return array();
	}

	unset( $markup['@context'] );

	$base_id       = '' !== $webpage_id ? $webpage_id : (string) get_permalink( $product->get_id() );
	$markup['@id'] = $base_id . '#product';

	if ( '' !== $webpage_id ) {
		$markup['mainEntityOfPage'] = array( '@id' => $webpage_id );
	}

	if ( '' !== $organization_id && ! empty( $markup['hasVariant'] ) && is_array( $markup['hasVariant'] ) ) {
		foreach ( $markup['hasVariant'] as &$variant ) {
			if ( is_array( $variant ) && ! empty( $variant['offers'] ) && is_array( $variant['offers'] ) ) {
				$variant['offers']['seller'] = array( '@id' => $organization_id );
			}
		}

		unset( $variant );
	}

	if ( '' !== $organization_id && ! empty( $markup['offers'] ) && is_array( $markup['offers'] ) ) {
		if ( isset( $markup['offers']['@type'] ) ) {
			$markup['offers']['seller'] = array( '@id' => $organization_id );
		} else {
			foreach ( $markup['offers'] as &$offer ) {
				if ( is_array( $offer ) ) {
					$offer['seller'] = array( '@id' => $organization_id );
				}
			}

			unset( $offer );
		}
	}

	/**
	 * Filters the Product node placed in the site graph.
	 *
	 * @param array      $markup  Product node.
	 * @param WC_Product $product Product.
	 */
	return (array) apply_filters( 'dovetail_schema_seo_woocommerce_product_node', $markup, $product );
}

/**
 * WebPage type of WooCommerce pages: ItemPage on products, CheckoutPage on checkout.
 *
 * @return string Empty when the type should not change.
 */
function dovetail_schema_seo_woocommerce_page_type() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		return 'ItemPage';
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return 'CheckoutPage';
	}

	return '';
}

/**
 * Replace the generic `WebPage` type with a more specific page type.
 *
 * @param string|array $type      Current type.
 * @param string       $page_type Specific page type.
 * @return string|array
 */
function dovetail_schema_seo_woocommerce_replace_webpage_type( $type, $page_type ) {
	if ( 'WebPage' === $type ) {
		return $page_type;
	}

	if ( is_array( $type ) ) {
		return array_values( array_unique( array_map( fn( $item ) => 'WebPage' === $item ? $page_type : $item, $type ) ) );
	}

	return $type;
}

/** Whether a graph node is a WebPage-derived page node. */
function dovetail_schema_seo_woocommerce_is_page_node( $node ) {
	$page_types = array(
		'WebPage',
		'ItemPage',
		'CheckoutPage',
		'CollectionPage',
		'ProfilePage',
		'SearchResultsPage',
		'AboutPage',
		'ContactPage',
		'FAQPage',
		'QAPage',
	);

	foreach ( $page_types as $page_type ) {
		if ( dovetail_schema_seo_woocommerce_schema_type_is( $node, $page_type ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Place the Product in Yoast's graph and set the specific WebPage type.
 *
 * @param array $graph Yoast graph.
 * @return array
 */
function dovetail_schema_seo_woocommerce_add_to_yoast_graph( $graph ) {
	if ( ! is_array( $graph ) || ! dovetail_schema_seo_woocommerce_product_graph_enabled() ) {
		return $graph;
	}

	$webpage_index   = null;
	$organization_id = '';
	$has_product     = false;

	foreach ( $graph as $index => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		$id = isset( $node['@id'] ) && is_string( $node['@id'] ) ? $node['@id'] : '';

		if ( null === $webpage_index && dovetail_schema_seo_woocommerce_is_page_node( $node ) ) {
			$webpage_index = $index;
		}

		if ( '' === $organization_id
			&& '' !== $id
			&& ( 1 === preg_match( '~#organization$~', $id ) || dovetail_schema_seo_woocommerce_schema_type_is( $node, 'Organization' ) )
		) {
			$organization_id = $id;
		}

		if ( dovetail_schema_seo_woocommerce_schema_type_is( $node, 'Product' )
			|| dovetail_schema_seo_woocommerce_schema_type_is( $node, 'ProductGroup' )
		) {
			$has_product = true;
		}
	}

	$page_type = dovetail_schema_seo_woocommerce_page_type();

	if ( '' !== $page_type && null !== $webpage_index ) {
		$graph[ $webpage_index ]['@type'] = dovetail_schema_seo_woocommerce_replace_webpage_type(
			$graph[ $webpage_index ]['@type'],
			$page_type
		);
	}

	if ( ! $has_product ) {
		$webpage_id = null !== $webpage_index && isset( $graph[ $webpage_index ]['@id'] )
			? (string) $graph[ $webpage_index ]['@id']
			: '';
		$product    = dovetail_schema_seo_woocommerce_get_product_node( $webpage_id, $organization_id );

		if ( $product ) {
			$graph[] = $product;
		}
	}

	return $graph;
}

add_filter( 'wpseo_schema_graph', 'dovetail_schema_seo_woocommerce_add_to_yoast_graph', 30, 1 );

/**
 * Set ItemPage/CheckoutPage on the standalone WebPage piece.
 *
 * @param string|array $type WebPage type.
 * @return string|array
 */
function dovetail_schema_seo_woocommerce_standalone_webpage_type( $type ) {
	if ( ! dovetail_schema_seo_woocommerce_product_graph_enabled() ) {
		return $type;
	}

	$page_type = dovetail_schema_seo_woocommerce_page_type();

	return '' !== $page_type
		? dovetail_schema_seo_woocommerce_replace_webpage_type( $type, $page_type )
		: $type;
}

add_filter(
	'dovetail_studio_schema_webpage_type',
	'dovetail_schema_seo_woocommerce_standalone_webpage_type'
);

/**
 * Place the Product in the Dovetail Studio standalone graph (no Yoast graph).
 *
 * @param array $pieces Standalone graph pieces.
 * @return array
 */
function dovetail_schema_seo_woocommerce_add_to_standalone_graph( $pieces ) {
	$pieces = is_array( $pieces ) ? $pieces : array();

	if ( ! dovetail_schema_seo_woocommerce_product_graph_enabled() ) {
		return $pieces;
	}

	$webpage_id      = \DovetailStudio\Core\Seo\SchemaGraph::id( 'webpage' );
	$organization_id = \DovetailStudio\Core\Seo\SchemaGraph::id( 'organization' );

	$product = dovetail_schema_seo_woocommerce_get_product_node(
		\DovetailStudio\Core\Seo\SchemaGraph::has_piece( $pieces, $webpage_id ) ? $webpage_id : '',
		\DovetailStudio\Core\Seo\SchemaGraph::has_piece( $pieces, $organization_id ) ? $organization_id : ''
	);

	if ( $product ) {
		$pieces[] = $product;
	}

	return $pieces;
}

add_filter( 'dovetail_studio_schema_graph', 'dovetail_schema_seo_woocommerce_add_to_standalone_graph', 30 );

/**
 * Add the store return policy to the Organization of a graph (Google reads an
 * organization-level policy for every product that has none of its own).
 *
 * @param array $graph Graph pieces.
 * @return array
 */
function dovetail_schema_seo_woocommerce_add_organization_policy( $graph ) {
	if ( ! is_array( $graph ) || ! dovetail_schema_seo_woocommerce_schema_enabled() ) {
		return $graph;
	}

	$policy = dovetail_schema_seo_woocommerce_return_policy();

	if ( ! $policy ) {
		return $graph;
	}

	$organization_id = \DovetailStudio\Core\Seo\SchemaGraph::id( 'organization' );

	foreach ( $graph as $index => $node ) {
		if ( is_array( $node )
			&& isset( $node['@id'] )
			&& is_string( $node['@id'] )
			&& ( $organization_id === $node['@id'] || 1 === preg_match( '~#organization$~', $node['@id'] ) )
			&& empty( $node['hasMerchantReturnPolicy'] )
		) {
			$graph[ $index ]['hasMerchantReturnPolicy'] = $policy;

			break;
		}
	}

	return $graph;
}

add_filter( 'wpseo_schema_graph', 'dovetail_schema_seo_woocommerce_add_organization_policy', 40, 1 );
add_filter( 'dovetail_studio_schema_graph', 'dovetail_schema_seo_woocommerce_add_organization_policy', 40 );

/*
 * ------------------------------------------------------------------------
 * BREADCRUMBS
 * ------------------------------------------------------------------------
 *
 * The breadcrumb schema comes only from Yoast SEO or the Dovetail Studio
 * graph, so WooCommerce's own BreadcrumbList is removed. Without Yoast, shop
 * pages use WooCommerce's own trail builder (shop, product categories, My
 * Account endpoints) through the Schema SEO breadcrumb provider filter.
 */

/** Whether the WooCommerce breadcrumb integration is enabled. */
function dovetail_schema_seo_woocommerce_breadcrumbs_enabled() {
	$settings = dovetail_schema_seo_woocommerce_get_settings();

	return ! empty( $settings['breadcrumbs'] )
		&& dovetail_schema_seo_woocommerce_has_woocommerce()
		&& ! dovetail_schema_seo_woocommerce_has_yoast_woocommerce()
		&& '' === \DovetailStudio\Core\Seo\Environment::other_seo_plugin();
}

/**
 * Remove WooCommerce's separate BreadcrumbList JSON-LD.
 *
 * @param array $markup BreadcrumbList markup.
 * @return array
 */
function dovetail_schema_seo_woocommerce_remove_breadcrumb_markup( $markup ) {
	return dovetail_schema_seo_woocommerce_breadcrumbs_enabled() ? array() : $markup;
}

add_filter(
	'woocommerce_structured_data_breadcrumblist',
	'dovetail_schema_seo_woocommerce_remove_breadcrumb_markup',
	PHP_INT_MAX,
	1
);

/** Whether the current request is a WooCommerce page. */
function dovetail_schema_seo_woocommerce_is_woocommerce_page() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}

	return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
}

/**
 * Provide WooCommerce's trail to the Schema SEO breadcrumb builder on shop pages.
 *
 * @param array|null $trail Trail from an earlier provider, or null.
 * @param array      $home  Home item (`label`, `url`).
 * @return array|null
 */
function dovetail_schema_seo_woocommerce_breadcrumb_trail( $trail, $home ) {
	if ( null !== $trail
		|| ! dovetail_schema_seo_woocommerce_breadcrumbs_enabled()
		|| ! class_exists( 'WC_Breadcrumb' )
		|| ! dovetail_schema_seo_woocommerce_is_woocommerce_page()
	) {
		return $trail;
	}

	$breadcrumb = new WC_Breadcrumb();
	$breadcrumb->add_crumb( $home['label'], apply_filters( 'woocommerce_breadcrumb_home_url', $home['url'] ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.

	$items = array();

	foreach ( (array) $breadcrumb->generate() as $crumb ) {
		$items[] = array(
			'label' => isset( $crumb[0] ) ? $crumb[0] : '',
			'url'   => isset( $crumb[1] ) ? $crumb[1] : '',
		);
	}

	if ( count( $items ) > 1 ) {
		$items[ count( $items ) - 1 ]['url'] = \DovetailStudio\Core\Seo\SchemaGraph::current_url();
	}

	return $items;
}

add_filter( 'dovetail_studio_breadcrumb_trail', 'dovetail_schema_seo_woocommerce_breadcrumb_trail', 10, 2 );

/*
 * ------------------------------------------------------------------------
 * TECHNICAL SEO
 * ------------------------------------------------------------------------
 */

/** Whether the current request is the cart, checkout or My Account page. */
function dovetail_schema_seo_woocommerce_is_utility_page() {
	return function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
}

/** Whether a setting of the technical SEO group applies. */
function dovetail_schema_seo_woocommerce_technical_setting( $key ) {
	$settings = dovetail_schema_seo_woocommerce_get_settings();

	return ! empty( $settings[ $key ] )
		&& dovetail_schema_seo_woocommerce_has_woocommerce()
		&& ! dovetail_schema_seo_woocommerce_has_yoast_woocommerce();
}

/**
 * Keep cart, checkout and My Account out of search results (WordPress robots API).
 *
 * @param array $robots Robots directives.
 * @return array
 */
function dovetail_schema_seo_woocommerce_wp_robots( $robots ) {
	if ( dovetail_schema_seo_woocommerce_technical_setting( 'noindex_utility_pages' )
		&& dovetail_schema_seo_woocommerce_is_utility_page()
	) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}

	return $robots;
}

add_filter( 'wp_robots', 'dovetail_schema_seo_woocommerce_wp_robots', 20 );

/**
 * Keep cart, checkout and My Account out of search results (Yoast robots).
 *
 * @param array $robots Yoast robots directives.
 * @return array
 */
function dovetail_schema_seo_woocommerce_yoast_robots( $robots ) {
	if ( is_array( $robots )
		&& dovetail_schema_seo_woocommerce_technical_setting( 'noindex_utility_pages' )
		&& dovetail_schema_seo_woocommerce_is_utility_page()
	) {
		$robots['index'] = 'noindex';
	}

	return $robots;
}

add_filter( 'wpseo_robots_array', 'dovetail_schema_seo_woocommerce_yoast_robots' );

/**
 * Print a canonical URL without filter, sorting or tracking arguments on the
 * shop and product taxonomy archives when no SEO plugin prints one.
 */
function dovetail_schema_seo_woocommerce_print_shop_canonical() {
	if ( ! dovetail_schema_seo_woocommerce_technical_setting( 'canonical_shop_archives' )
		|| ! function_exists( 'is_shop' )
	) {
		return;
	}

	$seo_plugin_active = dovetail_schema_seo_woocommerce_has_yoast()
		|| '' !== \DovetailStudio\Core\Seo\Environment::other_seo_plugin();

	/**
	 * Filters whether the module prints the shop archive canonical URL.
	 *
	 * @param bool $print Whether to print it. Default true when no SEO plugin is active.
	 */
	if ( ! apply_filters( 'dovetail_schema_seo_woocommerce_print_canonical', ! $seo_plugin_active ) ) {
		return;
	}

	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	echo '<link rel="canonical" href="'
		. esc_url( \DovetailStudio\Core\Seo\SchemaGraph::current_url() ) . '" />' . "\n";
}

add_action( 'wp_head', 'dovetail_schema_seo_woocommerce_print_shop_canonical', 5 );

/**
 * Hidden products (catalog and search visibility both off) for sitemap exclusion.
 *
 * @return int[] Product IDs.
 */
function dovetail_schema_seo_woocommerce_hidden_product_ids() {
	$ids = get_transient( 'dovetail_schema_seo_woocommerce_hidden_products' );

	if ( is_array( $ids ) ) {
		return $ids;
	}

	$ids = get_posts(
		array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'fields'           => 'ids',
			'posts_per_page'   => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only, capped.
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Cached for 12 hours and invalidated on product changes.
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'slug',
					'terms'    => array( 'exclude-from-catalog' ),
				),
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'slug',
					'terms'    => array( 'exclude-from-search' ),
				),
			),
		)
	);

	$ids = array_map( 'absint', is_array( $ids ) ? $ids : array() );
	set_transient( 'dovetail_schema_seo_woocommerce_hidden_products', $ids, 12 * HOUR_IN_SECONDS );

	return $ids;
}

/** Clear the hidden product cache when a product changes. */
function dovetail_schema_seo_woocommerce_flush_hidden_products() {
	delete_transient( 'dovetail_schema_seo_woocommerce_hidden_products' );
}

add_action( 'save_post_product', 'dovetail_schema_seo_woocommerce_flush_hidden_products' );
add_action( 'woocommerce_product_set_visibility', 'dovetail_schema_seo_woocommerce_flush_hidden_products' );

/**
 * Exclude hidden products from the WordPress core sitemap.
 *
 * @param array  $args      WP_Query arguments.
 * @param string $post_type Post type.
 * @return array
 */
function dovetail_schema_seo_woocommerce_core_sitemap_args( $args, $post_type ) {
	if ( 'product' !== $post_type
		|| ! dovetail_schema_seo_woocommerce_technical_setting( 'sitemap_exclude_hidden' )
	) {
		return $args;
	}

	$hidden = dovetail_schema_seo_woocommerce_hidden_product_ids();

	if ( $hidden ) {
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $hidden ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Bounded, cached ID list.
	}

	return $args;
}

add_filter( 'wp_sitemaps_posts_query_args', 'dovetail_schema_seo_woocommerce_core_sitemap_args', 10, 2 );

/**
 * Exclude hidden products from the Yoast SEO sitemap.
 *
 * @param int[] $ids Excluded post IDs.
 * @return int[]
 */
function dovetail_schema_seo_woocommerce_yoast_sitemap_exclusions( $ids ) {
	$ids = is_array( $ids ) ? $ids : array();

	if ( ! dovetail_schema_seo_woocommerce_technical_setting( 'sitemap_exclude_hidden' ) ) {
		return $ids;
	}

	return array_values(
		array_unique( array_merge( $ids, dovetail_schema_seo_woocommerce_hidden_product_ids() ) )
	);
}

add_filter(
	'wpseo_exclude_from_sitemap_by_post_ids',
	'dovetail_schema_seo_woocommerce_yoast_sitemap_exclusions'
);

/** Return available global product attributes for select fields. */
function dovetail_schema_seo_woocommerce_attribute_options() {
	$options = array( '' => __( '— Not mapped —', 'dovetail-studio' ) );

	if ( ! dovetail_schema_seo_woocommerce_has_woocommerce()
		|| ! function_exists( 'wc_get_attribute_taxonomies' )
	) {
		return $options;
	}

	foreach ( wc_get_attribute_taxonomies() as $attribute ) {
		$taxonomy             = wc_attribute_taxonomy_name( $attribute->attribute_name );
		$options[ $taxonomy ] = $attribute->attribute_label . ' (' . $taxonomy . ')';
	}

	return $options;
}

/** Render settings screen. */
function dovetail_schema_seo_woocommerce_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	\DovetailStudio\Core\View::render(
		dirname( __DIR__ ),
		'admin-page',
		array(
			'dir'                   => dirname( __DIR__ ),
			'version'               => DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_VERSION,
			'option'                => DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION,
			'settings'              => dovetail_schema_seo_woocommerce_get_settings(),
			'attributes'            => dovetail_schema_seo_woocommerce_attribute_options(),
			'has_woocommerce'       => dovetail_schema_seo_woocommerce_has_woocommerce(),
			'has_yoast'             => dovetail_schema_seo_woocommerce_has_yoast(),
			'has_yoast_woocommerce' => dovetail_schema_seo_woocommerce_has_yoast_woocommerce(),
			'other_seo'             => \DovetailStudio\Core\Seo\Environment::other_seo_plugin(),
			'currency'              => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		)
	);
}
