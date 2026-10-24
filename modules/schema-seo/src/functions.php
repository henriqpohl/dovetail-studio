<?php
/**
 * Public functions of the Schema SEO module for themes and other modules.
 *
 * @package DovetailStudio\SchemaSeo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dovetail_studio_get_breadcrumbs' ) ) {
	/**
	 * Return the breadcrumb trail of the current request, current page last.
	 *
	 * The trail is Yoast SEO's when Yoast owns the schema graph and the
	 * plugin's otherwise, so it always matches the BreadcrumbList printed on
	 * the page. Themes render it; they never print breadcrumb JSON-LD.
	 *
	 * @return array<int, array{label: string, url: string}> Empty when breadcrumbs are disabled or not applicable.
	 */
	function dovetail_studio_get_breadcrumbs(): array {
		return \DovetailStudio\SchemaSeo\Breadcrumbs::instance()->get_items();
	}
}
