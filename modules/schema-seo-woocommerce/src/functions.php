<?php
/**
 * Dovetail Schema SEO WooCommerce runtime.
 *
 * @package DovetailStudio\SchemaSeoWooCommerce
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'DOVETAIL_STUDIO_VERSION' ) ) {
    exit;
}

define( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_RUNTIME_VERSION', '1.1.0' );

define( 'DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION', 'dovetail_schema_seo_woocommerce_settings' );

/** Return true when WooCommerce is active. */
function dovetail_schema_seo_woocommerce_has_woocommerce() {
    return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
}

/** Return true when Yoast SEO is active. */
function dovetail_schema_seo_woocommerce_has_yoast() {
    return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
}

/** Detect the official Yoast WooCommerce SEO extension to avoid overlapping schema work. */
function dovetail_schema_seo_woocommerce_has_yoast_woocommerce() {
    if ( defined( 'WPSEO_WOO_VERSION' ) || class_exists( 'Yoast_WooCommerce_SEO' ) ) {
        return true;
    }

    $active = get_option( 'active_plugins', array() );
    if ( ! is_array( $active ) ) {
        $active = array();
    }

    $network = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
    if ( ! is_array( $network ) ) {
        $network = array();
    }

    $known = array(
        'wpseo-woocommerce/wpseo-woocommerce.php',
        'yoast-woocommerce-seo/wpseo-woocommerce.php',
    );

    foreach ( $known as $plugin ) {
        if ( in_array( $plugin, $active, true ) || isset( $network[ $plugin ] ) ) {
            return true;
        }
    }

    return false;
}

/** Default settings. */
function dovetail_schema_seo_woocommerce_defaults() {
    return array(
        'enabled'                    => 1,
        'brand_attribute'            => 'pa_brand',
        'manufacturer_attribute'     => '',
        'color_attribute'            => 'pa_color',
        'material_attribute'         => 'pa_material',
        'size_attribute'             => 'pa_size',
        'gtin_meta_key'              => '_gtin',
        'mpn_meta_key'               => '_mpn',
        'merchant_returns_enabled'   => 0,
        'return_country'             => 'IE',
        'return_days'                => 14,
        'return_method'              => 'https://schema.org/ReturnByMail',
        'return_fees'                => 'https://schema.org/FreeReturn',
    );
}

/** Saved settings. */
function dovetail_schema_seo_woocommerce_get_settings() {
    return wp_parse_args( get_option( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION, array() ), dovetail_schema_seo_woocommerce_defaults() );
}

/** Sanitize settings. */
function dovetail_schema_seo_woocommerce_sanitize_settings( $input ) {
    $input    = is_array( $input ) ? $input : array();
    $defaults = dovetail_schema_seo_woocommerce_defaults();
    $clean    = $defaults;

    $clean['enabled']                  = empty( $input['enabled'] ) ? 0 : 1;
    $clean['brand_attribute']          = sanitize_key( $input['brand_attribute'] ?? '' );
    $clean['manufacturer_attribute']   = sanitize_key( $input['manufacturer_attribute'] ?? '' );
    $clean['color_attribute']          = sanitize_key( $input['color_attribute'] ?? '' );
    $clean['material_attribute']       = sanitize_key( $input['material_attribute'] ?? '' );
    $clean['size_attribute']           = sanitize_key( $input['size_attribute'] ?? '' );
    $clean['gtin_meta_key']            = sanitize_text_field( $input['gtin_meta_key'] ?? '' );
    $clean['mpn_meta_key']             = sanitize_text_field( $input['mpn_meta_key'] ?? '' );
    $clean['merchant_returns_enabled'] = empty( $input['merchant_returns_enabled'] ) ? 0 : 1;
    $clean['return_country']           = strtoupper( substr( sanitize_text_field( $input['return_country'] ?? 'IE' ), 0, 2 ) );
    $clean['return_days']              = max( 0, min( 365, absint( $input['return_days'] ?? 14 ) ) );

    $return_methods = array(
        'https://schema.org/ReturnByMail',
        'https://schema.org/ReturnInStore',
        'https://schema.org/ReturnAtKiosk',
    );
    $return_fees = array(
        'https://schema.org/FreeReturn',
        'https://schema.org/ReturnFeesCustomerResponsibility',
        'https://schema.org/ReturnShippingFees',
    );

    $clean['return_method'] = in_array( $input['return_method'] ?? '', $return_methods, true )
        ? $input['return_method']
        : $defaults['return_method'];
    $clean['return_fees'] = in_array( $input['return_fees'] ?? '', $return_fees, true )
        ? $input['return_fees']
        : $defaults['return_fees'];

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

    if ( empty( $settings['merchant_returns_enabled'] ) ) {
        return array();
    }

    return array(
        '@type'               => 'MerchantReturnPolicy',
        'applicableCountry'   => $settings['return_country'],
        'returnPolicyCategory'=> 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays'  => (int) $settings['return_days'],
        'returnMethod'        => $settings['return_method'],
        'returnFees'          => $settings['return_fees'],
    );
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
        $data['brand'] = array( '@type' => 'Brand', 'name' => $brand );
    }

    $manufacturer = dovetail_schema_seo_woocommerce_get_attribute_value( $product, $settings['manufacturer_attribute'] );
    if ( $manufacturer && empty( $data['manufacturer'] ) ) {
        $data['manufacturer'] = array( '@type' => 'Organization', 'name' => $manufacturer );
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
            $data['productGroupID'] = $product->get_sku() ?: (string) $product->get_id();
        }

        $varies_by       = array();
        $variation_attrs = array_keys( $product->get_variation_attributes() );
        $known_variants  = array(
            'color_attribute'    => 'https://schema.org/color',
            'material_attribute' => 'https://schema.org/material',
            'size_attribute'     => 'https://schema.org/size',
        );

        foreach ( $known_variants as $setting_key => $schema_property ) {
            if ( ! empty( $settings[ $setting_key ] ) && in_array( $settings[ $setting_key ], $variation_attrs, true ) ) {
                $varies_by[] = $schema_property;
            }
        }

        if ( $varies_by && empty( $data['variesBy'] ) ) {
            $data['variesBy'] = array_values( array_unique( $varies_by ) );
        }
    }

    $policy = dovetail_schema_seo_woocommerce_return_policy();
    if ( $policy && ! empty( $data['offers'] ) ) {
        if ( isset( $data['offers']['@type'] ) ) {
            if ( empty( $data['offers']['hasMerchantReturnPolicy'] ) ) {
                $data['offers']['hasMerchantReturnPolicy'] = $policy;
            }
        } elseif ( is_array( $data['offers'] ) ) {
            foreach ( $data['offers'] as &$offer ) {
                if ( is_array( $offer ) && empty( $offer['hasMerchantReturnPolicy'] ) ) {
                    $offer['hasMerchantReturnPolicy'] = $policy;
                }
            }
            unset( $offer );
        }
    }

    return $data;
}

/** Determine whether our schema enrichment is safe to run. */
function dovetail_schema_seo_woocommerce_schema_enabled() {
    $settings = dovetail_schema_seo_woocommerce_get_settings();
    return ! empty( $settings['enabled'] ) && dovetail_schema_seo_woocommerce_has_woocommerce() && ! dovetail_schema_seo_woocommerce_has_yoast_woocommerce();
}

/** Enrich WooCommerce's native product structured data. */
function dovetail_schema_seo_woocommerce_filter_woocommerce_product_schema( $markup, $product ) {
    if ( ! dovetail_schema_seo_woocommerce_schema_enabled() ) {
        return $markup;
    }

    return dovetail_schema_seo_woocommerce_enrich_product_node( $markup, $product );
}
add_filter( 'woocommerce_structured_data_product', 'dovetail_schema_seo_woocommerce_filter_woocommerce_product_schema', 20, 2 );

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
    if ( ! dovetail_schema_seo_woocommerce_schema_enabled() || ! dovetail_schema_seo_woocommerce_has_yoast() || ! is_product() ) {
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

        if ( dovetail_schema_seo_woocommerce_schema_type_is( $node, 'Product' ) || dovetail_schema_seo_woocommerce_schema_type_is( $node, 'ProductGroup' ) ) {
            $graph[ $index ] = dovetail_schema_seo_woocommerce_enrich_product_node( $node, $product );
        }
    }

    return $graph;
}
add_filter( 'wpseo_schema_graph', 'dovetail_schema_seo_woocommerce_filter_yoast_graph', 20, 1 );

/** Return available global product attributes for select fields. */
function dovetail_schema_seo_woocommerce_attribute_options() {
    $options = array( '' => __( '— Not mapped —', 'dovetail-studio' ) );

    if ( ! dovetail_schema_seo_woocommerce_has_woocommerce() || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
        return $options;
    }

    foreach ( wc_get_attribute_taxonomies() as $attribute ) {
        $taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
        $options[ $taxonomy ] = $attribute->attribute_label . ' (' . $taxonomy . ')';
    }

    return $options;
}

/** Render a dependency warning. */
function dovetail_schema_seo_woocommerce_dependency_notice() {
    if ( dovetail_schema_seo_woocommerce_has_woocommerce() ) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Schema SEO WooCommerce requires WooCommerce.', 'dovetail-studio' ) . '</strong> ';
    echo esc_html__( 'Install and activate WooCommerce to enable product schema and merchant SEO features. The module can remain enabled, but no WooCommerce SEO hooks will run until the dependency is active.', 'dovetail-studio' );
    echo '</p></div>';
}

/** Render settings screen. */
function dovetail_schema_seo_woocommerce_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings   = dovetail_schema_seo_woocommerce_get_settings();
    $attributes = dovetail_schema_seo_woocommerce_attribute_options();
    ?>
    <div class="wrap dovetail-wrap">
        <div class="dovetail-page-header">
            <div>
                <h1><?php esc_html_e( 'Dovetail Schema SEO WooCommerce', 'dovetail-studio' ); ?></h1>
                <p><?php esc_html_e( 'Enrich WooCommerce product structured data and integrate it with Yoast SEO when available.', 'dovetail-studio' ); ?></p>
            </div>
            <span class="dovetail-version"><?php echo esc_html( 'v' . DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_RUNTIME_VERSION ); ?></span>
        </div>

        <?php dovetail_schema_seo_woocommerce_dependency_notice(); ?>

        <?php if ( dovetail_schema_seo_woocommerce_has_yoast_woocommerce() ) : ?>
            <div class="notice notice-warning"><p><strong><?php esc_html_e( 'Yoast WooCommerce SEO detected.', 'dovetail-studio' ); ?></strong> <?php esc_html_e( 'Dovetail Schema SEO WooCommerce schema enrichment is paused to avoid overlapping product schema. Disable one of the two WooCommerce SEO layers before using the Dovetail schema enhancements.', 'dovetail-studio' ); ?></p></div>
        <?php endif; ?>

        <nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Schema SEO WooCommerce sections', 'dovetail-studio' ); ?>">
            <a href="#dovetail-schema-seo-woocommerce-general"><?php esc_html_e( 'General', 'dovetail-studio' ); ?></a>
            <a href="#dovetail-schema-seo-woocommerce-attributes"><?php esc_html_e( 'Product Data', 'dovetail-studio' ); ?></a>
            <a href="#dovetail-schema-seo-woocommerce-returns"><?php esc_html_e( 'Returns', 'dovetail-studio' ); ?></a>
            <a href="#dovetail-schema-seo-woocommerce-status"><?php esc_html_e( 'Integration', 'dovetail-studio' ); ?></a>
        </nav>

        <form action="options.php" method="post">
            <?php settings_fields( 'dovetail_schema_seo_woocommerce' ); ?>

            <section class="dovetail-card" id="dovetail-schema-seo-woocommerce-general">
                <div class="dovetail-card-header">
                    <h2><?php esc_html_e( 'General', 'dovetail-studio' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Control the WooCommerce structured-data enrichment layer.', 'dovetail-studio' ); ?></p>
                </div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Product schema', 'dovetail-studio' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enrich WooCommerce product structured data', 'dovetail-studio' ); ?></label></td>
                    </tr>
                </table>
            </section>

            <section class="dovetail-card" id="dovetail-schema-seo-woocommerce-attributes">
                <div class="dovetail-card-header">
                    <h2><?php esc_html_e( 'Product Data Mapping', 'dovetail-studio' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Map WooCommerce global attributes and custom fields to Product schema properties.', 'dovetail-studio' ); ?></p>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $maps = array(
                        'brand_attribute'        => __( 'Brand', 'dovetail-studio' ),
                        'manufacturer_attribute' => __( 'Manufacturer', 'dovetail-studio' ),
                        'color_attribute'        => __( 'Color', 'dovetail-studio' ),
                        'material_attribute'     => __( 'Material', 'dovetail-studio' ),
                        'size_attribute'         => __( 'Size', 'dovetail-studio' ),
                    );
                    foreach ( $maps as $key => $label ) :
                    ?>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <select id="dovetail-schema-seo-woocommerce-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]">
                                <?php foreach ( $attributes as $value => $option_label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings[ $key ], $value ); ?>><?php echo esc_html( $option_label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-gtin"><?php esc_html_e( 'GTIN meta key', 'dovetail-studio' ); ?></label></th>
                        <td><input id="dovetail-schema-seo-woocommerce-gtin" class="regular-text" type="text" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[gtin_meta_key]" value="<?php echo esc_attr( $settings['gtin_meta_key'] ); ?>"><p class="description"><?php esc_html_e( 'Example: _gtin. GTIN-8, 12, 13 and 14 are emitted using their specific Schema.org property.', 'dovetail-studio' ); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-mpn"><?php esc_html_e( 'MPN meta key', 'dovetail-studio' ); ?></label></th>
                        <td><input id="dovetail-schema-seo-woocommerce-mpn" class="regular-text" type="text" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[mpn_meta_key]" value="<?php echo esc_attr( $settings['mpn_meta_key'] ); ?>"></td>
                    </tr>
                </table>
            </section>

            <section class="dovetail-card" id="dovetail-schema-seo-woocommerce-returns">
                <div class="dovetail-card-header">
                    <h2><?php esc_html_e( 'Merchant Return Policy', 'dovetail-studio' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Attach a return policy to product offers for merchant-listing structured data.', 'dovetail-studio' ); ?></p>
                </div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Return policy', 'dovetail-studio' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[merchant_returns_enabled]" value="1" <?php checked( ! empty( $settings['merchant_returns_enabled'] ) ); ?>> <?php esc_html_e( 'Add MerchantReturnPolicy to product offers', 'dovetail-studio' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-country"><?php esc_html_e( 'Country', 'dovetail-studio' ); ?></label></th>
                        <td><input id="dovetail-schema-seo-woocommerce-country" type="text" maxlength="2" size="4" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[return_country]" value="<?php echo esc_attr( $settings['return_country'] ); ?>"><p class="description"><?php esc_html_e( 'Two-letter country code, for example IE, GB or US.', 'dovetail-studio' ); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-days"><?php esc_html_e( 'Return window', 'dovetail-studio' ); ?></label></th>
                        <td><input id="dovetail-schema-seo-woocommerce-days" type="number" min="0" max="365" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[return_days]" value="<?php echo esc_attr( $settings['return_days'] ); ?>"> <?php esc_html_e( 'days', 'dovetail-studio' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-method"><?php esc_html_e( 'Return method', 'dovetail-studio' ); ?></label></th>
                        <td><select id="dovetail-schema-seo-woocommerce-method" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[return_method]"><option value="https://schema.org/ReturnByMail" <?php selected( $settings['return_method'], 'https://schema.org/ReturnByMail' ); ?>>Return by mail</option><option value="https://schema.org/ReturnInStore" <?php selected( $settings['return_method'], 'https://schema.org/ReturnInStore' ); ?>>Return in store</option><option value="https://schema.org/ReturnAtKiosk" <?php selected( $settings['return_method'], 'https://schema.org/ReturnAtKiosk' ); ?>>Return at kiosk</option></select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dovetail-schema-seo-woocommerce-fees"><?php esc_html_e( 'Return fees', 'dovetail-studio' ); ?></label></th>
                        <td><select id="dovetail-schema-seo-woocommerce-fees" name="<?php echo esc_attr( DOVETAIL_SCHEMA_SEO_WOOCOMMERCE_OPTION ); ?>[return_fees]"><option value="https://schema.org/FreeReturn" <?php selected( $settings['return_fees'], 'https://schema.org/FreeReturn' ); ?>>Free return</option><option value="https://schema.org/ReturnFeesCustomerResponsibility" <?php selected( $settings['return_fees'], 'https://schema.org/ReturnFeesCustomerResponsibility' ); ?>>Customer responsibility</option><option value="https://schema.org/ReturnShippingFees" <?php selected( $settings['return_fees'], 'https://schema.org/ReturnShippingFees' ); ?>>Return shipping fees</option></select></td>
                    </tr>
                </table>
            </section>

            <section class="dovetail-card" id="dovetail-schema-seo-woocommerce-status">
                <div class="dovetail-card-header">
                    <h2><?php esc_html_e( 'Integration Status', 'dovetail-studio' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Current SEO/schema environment detected by the module.', 'dovetail-studio' ); ?></p>
                </div>
                <div class="dovetail-card-body">
                    <p><strong>WooCommerce:</strong> <?php echo dovetail_schema_seo_woocommerce_has_woocommerce() ? '<span class="dovetail-badge is-enabled">Active</span>' : '<span class="dovetail-badge">Missing / inactive</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></p>
                    <p><strong>Yoast SEO:</strong> <?php echo dovetail_schema_seo_woocommerce_has_yoast() ? '<span class="dovetail-badge is-enabled">Active</span>' : '<span class="dovetail-badge">Not detected</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></p>
                    <p><strong>Yoast WooCommerce SEO:</strong> <?php echo dovetail_schema_seo_woocommerce_has_yoast_woocommerce() ? '<span class="dovetail-badge is-enabled">Active</span>' : '<span class="dovetail-badge">Not detected</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></p>
                    <p class="description"><?php esc_html_e( 'With Yoast SEO, Dovetail enriches Product/ProductGroup nodes in the Yoast graph. Without Yoast, it enriches WooCommerce native Product structured data.', 'dovetail-studio' ); ?></p>
                </div>
            </section>

            <div class="dovetail-save-bar">
                <?php submit_button( __( 'Save Schema SEO WooCommerce Settings', 'dovetail-studio' ), 'primary', 'submit', false ); ?>
            </div>
        </form>
    </div>
    <?php
}
