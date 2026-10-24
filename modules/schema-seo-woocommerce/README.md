# Schema SEO WooCommerce

Namespace: `DovetailStudio\SchemaSeoWooCommerce\Module`

Schema SEO WooCommerce enriches WooCommerce product structured data with mapped product attributes, GTIN/MPN, variable-product metadata, and optional merchant return policy data. It cooperates with Yoast SEO and pauses overlapping enrichment when the official Yoast WooCommerce SEO extension is detected.

## Dependency

WooCommerce is required. The module displays a warning when WooCommerce is missing or inactive.

## Compatibility

The historical option key `dovetail_schema_seo_woocommerce_settings` is preserved so existing configuration survives the module rename from WooCommerce SEO to Schema SEO WooCommerce.

## Possible Errors and How to Fix Them

### WooCommerce warning is visible
Install and activate WooCommerce.

### Schema enrichment is paused
Check whether the official Yoast WooCommerce SEO extension is active. Use one overlapping product-schema enrichment layer at a time.
