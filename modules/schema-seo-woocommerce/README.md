# Schema SEO WooCommerce

Namespace: `DovetailStudio\SchemaSeoWooCommerce\Module`

Schema SEO WooCommerce enriches WooCommerce product structured data with mapped product attributes, GTIN/MPN, variable-product metadata, and optional merchant return policy data, and integrates it into the single site graph:

- **Yoast SEO active:** the enriched Product is added to Yoast's graph with `mainEntityOfPage` pointing to the WebPage, which becomes `ItemPage` (`CheckoutPage` on checkout); offers reference the Organization as `seller`.
- **No Yoast graph:** the same Product is added to the Dovetail Studio standalone graph (`DovetailStudio\Core\Seo\SchemaGraph`), linked to the Schema SEO WebPage and Organization when that module is active.
- WooCommerce's separate product, review, website and breadcrumb JSON-LD is suppressed, so each page has one graph and one BreadcrumbList.
- **Rank Math, SEOPress, All in One SEO or The SEO Framework active:** that plugin owns the schema, so Product graph and Breadcrumbs stay off and WooCommerce keeps its own product and breadcrumb JSON-LD (still enriched with the mapped attributes, return policy and shipping).

## Returns and shipping

- **Merchant return policy:** added to every offer and to the Organization (Google reads an organization-level policy for products without their own). The country defaults to the store country (WooCommerce > Settings > General).
- **Shipping:** an optional flat rate with handling and transit days (`OfferShippingDetails`) added to every offer. Leave it off when rates vary by product or zone.

It pauses overlapping enrichment when the official Yoast WooCommerce SEO extension is detected.

## Breadcrumbs

WooCommerce's BreadcrumbList is removed. Without Yoast SEO, shop pages (shop, product, product taxonomies, cart, checkout, My Account) use WooCommerce's own `WC_Breadcrumb` trail through the Schema SEO `dovetail_studio_breadcrumb_trail` filter. With Yoast SEO the trail comes from Yoast.

## Technical SEO

Each setting can be switched off individually: noindex, follow on cart/checkout/My Account (WordPress robots and Yoast robots); a canonical URL without filter and sorting parameters on shop and product taxonomy archives when no SEO plugin prints one; hidden products excluded from the WordPress and Yoast SEO sitemaps.

## Structure

`src/Module.php` defines the constants and loads `src/functions.php` (settings, graph integration, breadcrumbs, technical SEO). The settings screen markup is in `views/admin-page.php`, with the status badge and the missing WooCommerce notice in `views/partials/`.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `dovetail_schema_seo_woocommerce_product_node` | filter | Product node placed in the site graph (`$node`, `$product`). |
| `dovetail_schema_seo_woocommerce_print_canonical` | filter | Whether the module prints the shop archive canonical URL. |

## Dependency

WooCommerce is required. The module displays a warning when WooCommerce is missing or inactive.

## Settings

Saved in the option `dovetail_schema_seo_woocommerce_settings`. The SEO settings default to enabled; return policy and shipping default to off.

## Possible Errors and How to Fix Them

### WooCommerce warning is visible
Install and activate WooCommerce.

### Schema enrichment is paused
Check whether the official Yoast WooCommerce SEO extension is active. Use one overlapping product-schema enrichment layer at a time.

### Product is missing from the graph
Confirm **Product schema** and **Product graph** are enabled and the page is a single product. A custom single-product template must still let WooCommerce build product data (`WC()->structured_data`).

### WooCommerce still prints its own JSON-LD
Another plugin may re-add WooCommerce structured data, or **Product graph** is disabled. Product data generated outside the page request (emails) is not affected.
