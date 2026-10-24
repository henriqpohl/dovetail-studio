# Schema SEO WooCommerce — Usage

Enable **Schema SEO WooCommerce** in **Dovetail Studio → Modules**, then open **Dovetail Studio → Schema SEO WooCommerce**. Review the **General** switches (Product graph, Breadcrumbs, Utility pages, Shop canonical, Sitemap), map WooCommerce attributes for brand, manufacturer, color, material and size, configure GTIN/MPN meta keys, and optionally configure the merchant return policy.

Enable **Schema SEO** as well to link the Product to the WebPage and Organization when Yoast SEO is not active.

## Possible Errors and How to Fix Them

### Product values are missing from schema
Confirm the configured WooCommerce attribute/meta key actually contains data on the product or its parent variable product.

### The shop shows two breadcrumb trails
The theme still prints `woocommerce_breadcrumb()`. Replace it with the theme breadcrumbs component, which reads `dovetail_studio_get_breadcrumbs()`.
