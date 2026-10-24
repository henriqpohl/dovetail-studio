=== Dovetail Studio ===
Contributors: dovetailconsultancy
Tags: dovetail, toolkit, modules
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 2.1.0
License: GPLv2 or later

Central WordPress toolkit for Dovetail Consultancy modules.

== Included Modules ==

= Schema SEO =
Local Business structured data with Yoast SEO integration and standalone JSON-LD output.
Bundled version: 1.4.2.

= Schema SEO WooCommerce =
WooCommerce product structured data and merchant SEO enrichment with Yoast integration.
Bundled version: 1.0.0.

= Virtual Assistant =
AI assistant with REST endpoints, knowledge base, enquiries, spam controls and front-end interface.
Bundled version: 1.31.0.

= Newsletter =
Contact Form 7 and Mailchimp newsletter integration with encrypted API credentials and double opt-in support.
Bundled version: 3.1.0.

== Module Architecture ==

Dovetail Studio is the only WordPress plugin. Modules live only inside `modules/` and are not standalone WordPress plugins.

Every module follows the same entry-point contract: `module.php` + namespaced `src/Module.php`. Installed modules can be enabled or disabled in Dovetail Studio > Modules. Disabled modules may be removed; module database settings are preserved.

== Changelog ==

= 2.0.0 =
* Standardized all modules around `module.php` and `src/Module.php`.
* Added namespaced module bootstraps.
* Namespaced core loader as `DovetailStudio\Core\Loader`.
* Renamed WooCommerce SEO module to Schema SEO WooCommerce.
* Preserved existing options and enabled-state migration for the renamed WooCommerce module.
* Removed legacy `plugin/` wrapper directories.
