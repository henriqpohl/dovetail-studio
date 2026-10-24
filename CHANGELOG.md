# Changelog

All notable changes to Dovetail Studio and its bundled modules should be documented in this file.

The format is based on Keep a Changelog and follows Semantic Versioning where possible. Module versions are listed in each entry; every module also declares its own version in `module.php`.

## [2.2.0] - 2026-10-24

Bundled modules: Schema SEO 1.5.0, Schema SEO WooCommerce 1.2.0, Virtual Assistant 1.31.1.

### Added

- Core:
  - Shared SEO services in `src/Core/Seo/`: `Environment` detects Yoast SEO, WooCommerce and Yoast WooCommerce SEO for every module, and `SchemaGraph` prints a single JSON-LD `@graph` in `wp_head` when Yoast SEO did not build or print its graph.
  - `dovetail_studio_schema_graph` filter for modules to add pieces to the standalone graph, with Yoast-compatible `@id` helpers (`SchemaGraph::id()`, `SchemaGraph::current_url()`, `SchemaGraph::has_piece()`).
- Schema SEO:
  - WebSite, WebPage (`CollectionPage`, `SearchResultsPage`, `ProfilePage` by context), primary image, `Article` and author `Person` pieces in `src/SiteGraph.php` when Yoast SEO does not provide the graph.
  - Breadcrumb trail in `src/Breadcrumbs.php` and the public `dovetail_studio_get_breadcrumbs()` function. With Yoast SEO the trail is Yoast's; without it the module builds it and adds `BreadcrumbList` to the standalone graph.
  - Breadcrumb filters `dovetail_studio_breadcrumb_items` (also applied to Yoast through `wpseo_breadcrumb_links`), `dovetail_studio_breadcrumb_trail`, `dovetail_studio_breadcrumb_taxonomy` and `dovetail_studio_breadcrumb_primary_term`.
  - Piece filters `dovetail_studio_schema_website`, `dovetail_studio_schema_webpage`, `dovetail_studio_schema_webpage_type`, `dovetail_studio_schema_article`, `dovetail_studio_schema_article_post_types` and `dovetail_studio_schema_person`.
  - **Site Graph & Breadcrumbs** settings (`site_graph_enabled`, `breadcrumbs_enabled`, `breadcrumbs_home_label`).
- Schema SEO WooCommerce:
  - Product placed in the Yoast SEO graph or the standalone graph, built with WooCommerce's own generator and the existing enrichment, with `mainEntityOfPage`, `ItemPage`/`CheckoutPage` page types and offers linked to the Organization as `seller`.
  - WooCommerce breadcrumb trail (`WC_Breadcrumb`) for shop pages when Yoast SEO is not active.
  - Technical SEO settings: `noindex, follow` on cart, checkout and My Account; a canonical URL without filter parameters on shop and product taxonomy archives when no SEO plugin prints one; sitemap exclusion of hidden products (WordPress and Yoast SEO sitemaps).
  - `dovetail_schema_seo_woocommerce_product_node` and `dovetail_schema_seo_woocommerce_print_canonical` filters.

### Changed

- Core:
  - Standalone JSON-LD is encoded with `JSON_HEX_TAG`.
- Schema SEO:
  - The Organization entity is added to the shared graph instead of printing its own `<script>`; `display_scope` applies to the Organization only.
  - Uses the shared `Environment` detection; `Schema::is_yoast_active()` remains available and delegates to it.
- Schema SEO WooCommerce:
  - WooCommerce's separate product, review, website and `BreadcrumbList` JSON-LD is suppressed so each page has one graph and one `BreadcrumbList`.
  - Uses the shared `Environment` detection; the previous detection functions remain available and delegate to it.
- Virtual Assistant:
  - Renamed `src/Services/` to `src/services/`, following the lowercase convention for folders that do not map to a namespace.

### Documentation

- Plugin:
  - Rewrite `README.md` with release and module badges, a module table with links to each module's documentation, requirements, an architecture overview and a structured-data summary.
  - Document file, class, namespace, function and hook naming conventions in `README.md`.
  - Document the shared SEO services in `src/Core/README.md`.
- Schema SEO and Schema SEO WooCommerce:
  - Document the new behaviour, hooks and settings in each module's `README.md` and `USAGE.md`.

## [2.0.0]

### Changed

- Standardized all modules around `module.php` and `src/Module.php`.
- Added namespaced module bootstraps.
- Namespaced the core loader as `DovetailStudio\Core\Loader`.
- Renamed the WooCommerce SEO module to Schema SEO WooCommerce.
- Preserved existing options and enabled-state migration for the renamed WooCommerce module.
- Removed legacy `plugin/` wrapper directories.
