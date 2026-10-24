=== Dovetail Studio ===
Contributors: dovetailconsultancy
Tags: dovetail, toolkit, modules
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 2.2.0
License: GPLv2 or later

Central WordPress toolkit for Dovetail Consultancy modules.

== Included Modules ==

= Schema SEO =
Organization, WebSite, WebPage, Article and breadcrumb structured data with Yoast SEO integration and a single standalone graph.
Bundled version: 1.5.0.

= Schema SEO WooCommerce =
WooCommerce Product in the site graph, merchant SEO enrichment, breadcrumbs and technical SEO with Yoast integration.
Bundled version: 1.2.0.

= Virtual Assistant =
AI assistant with REST endpoints, knowledge base, enquiries, spam controls and front-end interface.
Bundled version: 1.31.1.

= Newsletter =
Contact Form 7 and Mailchimp newsletter integration with encrypted API credentials and double opt-in support.
Bundled version: 3.1.0.

== Module Architecture ==

Dovetail Studio is the only WordPress plugin. Modules live only inside `modules/` and are not standalone WordPress plugins.

Every module follows the same entry-point contract: `module.php` + namespaced `src/Module.php`. Installed modules can be enabled or disabled in Dovetail Studio > Modules. Disabled modules may be removed; module database settings are preserved.

== Changelog ==

= 2.2.0 =
* Added the shared `DovetailStudio\Core\Seo` services: environment detection and a single standalone JSON-LD graph.
* Schema SEO 1.5.0: Organization joins the shared graph; added WebSite, WebPage, Article and author Person without Yoast SEO; added the breadcrumb trail API `dovetail_studio_get_breadcrumbs()` and BreadcrumbList.
* Schema SEO WooCommerce 1.2.0: Product placed in the Yoast or Dovetail Studio graph with ItemPage/CheckoutPage and Organization seller; WooCommerce breadcrumb, review and website JSON-LD suppressed; WooCommerce trail without Yoast; noindex for cart/checkout/My Account, shop canonical and sitemap exclusion of hidden products.
* Virtual Assistant 1.31.1: renamed `src/Services/` to `src/services/`.

= 2.0.0 =
* Standardized all modules around `module.php` and `src/Module.php`.
* Added namespaced module bootstraps.
* Namespaced core loader as `DovetailStudio\Core\Loader`.
* Renamed WooCommerce SEO module to Schema SEO WooCommerce.
* Preserved existing options and enabled-state migration for the renamed WooCommerce module.
* Removed legacy `plugin/` wrapper directories.
