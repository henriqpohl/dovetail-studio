# Schema SEO — Usage

Enable the module in **Dovetail Studio → Modules**, then open **Dovetail Studio → Schema SEO**. Configure organization/local-business fields, review **Site Graph & Breadcrumbs**, save, and use the JSON-LD preview to inspect output.

## Breadcrumbs in a theme

Render the trail and let the plugin (or Yoast SEO) print the structured data:

```php
$breadcrumbs = function_exists( 'dovetail_studio_get_breadcrumbs' ) ? dovetail_studio_get_breadcrumbs() : array();
```

Each item is `array( 'label' => 'About', 'url' => 'https://example.com/about/' )`; the last item is the current page. Do not print breadcrumb JSON-LD from the theme.

Customize the trail in code (applies to the visible trail and the schema, with or without Yoast):

```php
add_filter(
	'dovetail_studio_breadcrumb_items',
	function ( array $items ): array {
		$items[0]['label'] = 'Start';
		return $items;
	}
);
```

## Possible Errors and How to Fix Them

### Preview cannot load
Verify the public homepage is reachable from WordPress itself and that security/cache layers are not blocking `wp_safe_remote_get()`.

### Two BreadcrumbList entities appear
Another plugin or the theme prints breadcrumb JSON-LD. With WooCommerce, enable **Breadcrumbs** in Schema SEO WooCommerce to remove WooCommerce's own BreadcrumbList.
