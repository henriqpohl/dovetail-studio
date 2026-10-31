<?php
/**
 * Dovetail Studio → Schema SEO WooCommerce: settings and integration status.
 *
 * @package DovetailStudio\SchemaSeoWooCommerce
 *
 * @var array{dir: string, version: string, option: string, settings: array<string,mixed>, attributes: array<string,string>, has_woocommerce: bool, has_yoast: bool, has_yoast_woocommerce: bool, other_seo: string, currency: string} $args
 */

use DovetailStudio\Core\AdminScreens;
use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$dir        = $args['dir'];
$option     = $args['option'];
$settings   = $args['settings'];
$attributes = $args['attributes'];
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Dovetail Schema SEO WooCommerce', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Enrich WooCommerce product structured data and integrate it with Yoast SEO when available.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<?php AdminScreens::settings_errors(); ?>

	<?php if ( ! $args['has_woocommerce'] ) : ?>
		<?php View::render( $args['dir'], 'partials/dependency-notice' ); ?>
	<?php endif; ?>

	<?php if ( $args['has_yoast_woocommerce'] ) : ?>
		<div class="notice dovetail-notice inline notice-warning"><p><strong><?php esc_html_e( 'Yoast WooCommerce SEO detected.', 'dovetail-studio' ); ?></strong> <?php esc_html_e( 'Dovetail Schema SEO WooCommerce schema enrichment is paused to avoid overlapping product schema. Disable one of the two WooCommerce SEO layers before using the Dovetail schema enhancements.', 'dovetail-studio' ); ?></p></div>
	<?php endif; ?>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Schema SEO WooCommerce sections', 'dovetail-studio' ); ?>">
		<a href="#dovetail-schema-seo-woocommerce-general"><?php esc_html_e( 'General', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-schema-seo-woocommerce-attributes"><?php esc_html_e( 'Product Data', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-schema-seo-woocommerce-returns"><?php esc_html_e( 'Returns', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-schema-seo-woocommerce-shipping"><?php esc_html_e( 'Shipping', 'dovetail-studio' ); ?></a>
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
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enrich WooCommerce product structured data', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Product graph', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[product_graph]" value="1" <?php checked( ! empty( $settings['product_graph'] ) ); ?>> <?php esc_html_e( 'Place the Product in the single site graph (Yoast SEO or Dovetail Studio), set ItemPage/CheckoutPage, link the seller to the Organization, and stop WooCommerce\'s separate product, review and website JSON-LD.', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Breadcrumbs', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[breadcrumbs]" value="1" <?php checked( ! empty( $settings['breadcrumbs'] ) ); ?>> <?php esc_html_e( 'Remove WooCommerce\'s BreadcrumbList and, without Yoast SEO, use the WooCommerce trail (shop, categories, My Account) for the Dovetail Studio breadcrumbs.', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Utility pages', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[noindex_utility_pages]" value="1" <?php checked( ! empty( $settings['noindex_utility_pages'] ) ); ?>> <?php esc_html_e( 'Add noindex, follow to the cart, checkout and My Account pages.', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Shop canonical', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[canonical_shop_archives]" value="1" <?php checked( ! empty( $settings['canonical_shop_archives'] ) ); ?>> <?php esc_html_e( 'Print a canonical URL without filter and sorting parameters on the shop and product taxonomy archives when no SEO plugin prints one.', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sitemap', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[sitemap_exclude_hidden]" value="1" <?php checked( ! empty( $settings['sitemap_exclude_hidden'] ) ); ?>> <?php esc_html_e( 'Exclude hidden products (catalog and search visibility off) from the WordPress and Yoast SEO sitemaps.', 'dovetail-studio' ); ?></label></td>
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
						<select id="dovetail-schema-seo-woocommerce-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $key ); ?>]">
							<?php foreach ( $attributes as $value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings[ $key ], $value ); ?>><?php echo esc_html( $option_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-gtin"><?php esc_html_e( 'GTIN meta key', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-schema-seo-woocommerce-gtin" class="regular-text" type="text" name="<?php echo esc_attr( $option ); ?>[gtin_meta_key]" value="<?php echo esc_attr( $settings['gtin_meta_key'] ); ?>"><p class="description"><?php esc_html_e( 'Example: _gtin. GTIN-8, 12, 13 and 14 are emitted using their specific Schema.org property.', 'dovetail-studio' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-mpn"><?php esc_html_e( 'MPN meta key', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-schema-seo-woocommerce-mpn" class="regular-text" type="text" name="<?php echo esc_attr( $option ); ?>[mpn_meta_key]" value="<?php echo esc_attr( $settings['mpn_meta_key'] ); ?>"></td>
				</tr>
			</table>
		</section>

		<section class="dovetail-card" id="dovetail-schema-seo-woocommerce-returns">
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Merchant Return Policy', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Attach a return policy to product offers and to the Organization for merchant-listing structured data.', 'dovetail-studio' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Return policy', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[merchant_returns_enabled]" value="1" <?php checked( ! empty( $settings['merchant_returns_enabled'] ) ); ?>> <?php esc_html_e( 'Add MerchantReturnPolicy to product offers', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-country"><?php esc_html_e( 'Country', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-schema-seo-woocommerce-country" type="text" maxlength="2" size="4" name="<?php echo esc_attr( $option ); ?>[return_country]" value="<?php echo esc_attr( $settings['return_country'] ); ?>"><p class="description"><?php esc_html_e( 'Two-letter country code, for example US, GB or BR. Leave blank to use the store country (WooCommerce > Settings > General).', 'dovetail-studio' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-days"><?php esc_html_e( 'Return window', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-schema-seo-woocommerce-days" type="number" min="0" max="365" name="<?php echo esc_attr( $option ); ?>[return_days]" value="<?php echo esc_attr( $settings['return_days'] ); ?>"> <?php esc_html_e( 'days', 'dovetail-studio' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-method"><?php esc_html_e( 'Return method', 'dovetail-studio' ); ?></label></th>
					<td><select id="dovetail-schema-seo-woocommerce-method" name="<?php echo esc_attr( $option ); ?>[return_method]"><option value="https://schema.org/ReturnByMail" <?php selected( $settings['return_method'], 'https://schema.org/ReturnByMail' ); ?>><?php esc_html_e( 'Return by mail', 'dovetail-studio' ); ?></option><option value="https://schema.org/ReturnInStore" <?php selected( $settings['return_method'], 'https://schema.org/ReturnInStore' ); ?>><?php esc_html_e( 'Return in store', 'dovetail-studio' ); ?></option><option value="https://schema.org/ReturnAtKiosk" <?php selected( $settings['return_method'], 'https://schema.org/ReturnAtKiosk' ); ?>><?php esc_html_e( 'Return at kiosk', 'dovetail-studio' ); ?></option></select></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-fees"><?php esc_html_e( 'Return fees', 'dovetail-studio' ); ?></label></th>
					<td><select id="dovetail-schema-seo-woocommerce-fees" name="<?php echo esc_attr( $option ); ?>[return_fees]"><option value="https://schema.org/FreeReturn" <?php selected( $settings['return_fees'], 'https://schema.org/FreeReturn' ); ?>><?php esc_html_e( 'Free return', 'dovetail-studio' ); ?></option><option value="https://schema.org/ReturnFeesCustomerResponsibility" <?php selected( $settings['return_fees'], 'https://schema.org/ReturnFeesCustomerResponsibility' ); ?>><?php esc_html_e( 'Customer responsibility', 'dovetail-studio' ); ?></option><option value="https://schema.org/ReturnShippingFees" <?php selected( $settings['return_fees'], 'https://schema.org/ReturnShippingFees' ); ?>><?php esc_html_e( 'Return shipping fees', 'dovetail-studio' ); ?></option></select></td>
				</tr>
			</table>
		</section>

		<section class="dovetail-card" id="dovetail-schema-seo-woocommerce-shipping">
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Shipping', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A flat shipping rate and delivery time added to every product offer (OfferShippingDetails). Leave off when rates vary by product or zone.', 'dovetail-studio' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Shipping details', 'dovetail-studio' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[shipping_enabled]" value="1" <?php checked( ! empty( $settings['shipping_enabled'] ) ); ?>> <?php esc_html_e( 'Add shipping details to product offers', 'dovetail-studio' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-shipping-country"><?php esc_html_e( 'Country', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-schema-seo-woocommerce-shipping-country" type="text" maxlength="2" size="4" name="<?php echo esc_attr( $option ); ?>[shipping_country]" value="<?php echo esc_attr( $settings['shipping_country'] ); ?>"><p class="description"><?php esc_html_e( 'Destination country. Leave blank to use the store country.', 'dovetail-studio' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="dovetail-schema-seo-woocommerce-shipping-rate"><?php esc_html_e( 'Shipping rate', 'dovetail-studio' ); ?></label></th>
					<td><input id="dovetail-schema-seo-woocommerce-shipping-rate" type="number" min="0" step="0.01" name="<?php echo esc_attr( $option ); ?>[shipping_rate]" value="<?php echo esc_attr( $settings['shipping_rate'] ); ?>"> <?php echo esc_html( $args['currency'] ); ?><p class="description"><?php esc_html_e( 'Use 0 for free shipping.', 'dovetail-studio' ); ?></p></td>
				</tr>
				<?php
				foreach ( array(
					'handling' => __( 'Handling time', 'dovetail-studio' ),
					'transit'  => __( 'Transit time', 'dovetail-studio' ),
				) as $time => $time_label ) :
					?>
				<tr>
					<th scope="row"><?php echo esc_html( $time_label ); ?></th>
					<td>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Minimum', 'dovetail-studio' ); ?></span><input type="number" min="0" max="60" name="<?php echo esc_attr( $option . '[' . $time . '_min]' ); ?>" value="<?php echo esc_attr( $settings[ $time . '_min' ] ); ?>"></label>
						<span aria-hidden="true">–</span>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Maximum', 'dovetail-studio' ); ?></span><input type="number" min="0" max="60" name="<?php echo esc_attr( $option . '[' . $time . '_max]' ); ?>" value="<?php echo esc_attr( $settings[ $time . '_max' ] ); ?>"></label>
						<?php esc_html_e( 'days', 'dovetail-studio' ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</section>

		<section class="dovetail-card" id="dovetail-schema-seo-woocommerce-status">
			<div class="dovetail-card-header">
				<h2><?php esc_html_e( 'Integration Status', 'dovetail-studio' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Current SEO/schema environment detected by the module.', 'dovetail-studio' ); ?></p>
			</div>
			<div class="dovetail-card-body">
				<?php
				$other_seo = $args['other_seo'];
				$badge     = static function ( bool $active, string $off_label ) use ( $dir ): void {
					View::render(
						$dir,
						'partials/badge',
						array(
							'active'    => $active,
							'off_label' => $off_label,
						)
					);
				};
				?>
				<p><strong>WooCommerce:</strong> <?php $badge( $args['has_woocommerce'], __( 'Missing / inactive', 'dovetail-studio' ) ); ?></p>
				<p><strong>Yoast SEO:</strong> <?php $badge( $args['has_yoast'], __( 'Not detected', 'dovetail-studio' ) ); ?></p>
				<p><strong>Yoast WooCommerce SEO:</strong> <?php $badge( $args['has_yoast_woocommerce'], __( 'Not detected', 'dovetail-studio' ) ); ?></p>
				<?php if ( '' !== $other_seo ) : ?>
					<p><strong><?php echo esc_html( $other_seo ); ?>:</strong> <?php $badge( true, '' ); ?> <?php esc_html_e( 'It publishes the schema and breadcrumbs, so Product graph and Breadcrumbs stay off and WooCommerce keeps its own product JSON-LD.', 'dovetail-studio' ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'With Yoast SEO, the enriched Product is placed in the Yoast graph. Without Yoast SEO, it is placed in the single Dovetail Studio graph. With Product graph off, WooCommerce keeps printing its own enriched product JSON-LD.', 'dovetail-studio' ); ?></p>
			</div>
		</section>

		<div class="dovetail-save-bar">
			<?php
			submit_button(
				__( 'Save Schema SEO WooCommerce Settings', 'dovetail-studio' ),
				'primary',
				'submit',
				false
			);
			?>
		</div>
	</form>
</div>
