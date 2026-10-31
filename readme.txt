=== Dovetail Studio ===
Contributors: dovetailconsultancy
Tags: dovetail, toolkit, modules
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.5.0
License: GPLv2 or later

Central WordPress toolkit for Dovetail Consultancy modules.

== Included Modules ==

= Schema SEO =
Organization, WebSite, WebPage, Article and breadcrumb structured data with Yoast SEO integration and a single standalone graph.
Bundled version: 1.6.1.

= Schema SEO WooCommerce =
WooCommerce Product in the site graph, merchant SEO enrichment, breadcrumbs and technical SEO with Yoast integration.
Bundled version: 1.3.0.

= Virtual Assistant =
AI assistant with REST endpoints, knowledge base, enquiries, spam controls and front-end interface.
Bundled version: 1.33.1.

= Newsletter =
Contact Form 7 and Mailchimp newsletter integration with encrypted API credentials and double opt-in support.
Bundled version: 3.4.0.

= Vulnerability Watch =
Daily check of WordPress core, plugins and themes against the WPVulnerability database, with email alerts and the versions that fix each vulnerability; WordPress core file and sensitive file checks; alerts and a log of new administrators and plugin, theme and setting changes.
Bundled version: 1.0.0.

= Login Guard =
Brute force protection for the login, a block list of IP addresses with unblock, two-factor authentication, reCAPTCHA v3 or Cloudflare Turnstile, sign-in email alerts and summaries, a sign-in activity log, strong and breached password checks and hardening.
Bundled version: 1.0.0.

= Hide Login Path =
Moves the login to an address you choose; wp-login.php and wp-admin send visitors who are signed out to a page of your choice.
Bundled version: 1.0.0.

== Module Architecture ==

Dovetail Studio is the only WordPress plugin. Bundled modules live inside `modules/`; modules uploaded from Dovetail Studio > Modules are stored in `wp-content/dovetail-modules/` so plugin updates keep them. Modules are not standalone WordPress plugins.

Every module follows the same entry-point contract: `module.php` + namespaced `src/Module.php`. Installed modules can be enabled or disabled in Dovetail Studio > Modules. Disabled modules may be removed; module database settings are preserved.

== Changelog ==

= 2.5.0 =
* Login Guard 1.0.0 (new): brute force lockouts, IP block list with unblock, two-factor authentication, reCAPTCHA v3 or Cloudflare Turnstile, sign-in alerts and summaries, sign-in activity log, password policies and hardening.
* Vulnerability Watch 1.0.0 (new): daily check against the WPVulnerability database, WordPress core file and sensitive file checks, and alerts and a log of new administrators and plugin, theme and setting changes.
* Hide Login Path 1.0.0 (new): moves the login to a path you choose and hides wp-login.php and wp-admin from visitors (replaces WPS Hide Login).
* Virtual Assistant 1.33.1: Turnstile check through the shared Core\Captcha helper; the Dovetail Studio shortcut is registered by the module.
* Schema SEO 1.6.1: unused code removed.
* Core: shared Mail and Captcha helpers, Loader::asset() serves minified assets, no module-specific code in Core.

= 2.4.0 =
* Schema SEO 1.6.0: with Yoast SEO in Person mode the Person is kept and the business is a separate, linked entity; no nameless Organization; Person mode without Yoast; no second graph next to Rank Math, SEOPress, All in One SEO or The SEO Framework.
* Schema SEO WooCommerce 1.3.0: shipping details, organization return policy, store country by default.
* Virtual Assistant 1.33.0: emails sent from the site's domain, personal data export/erase and retention, closed dates, lighter page load (tab styles only), prompt caching, neutral wording.
* Newsletter 3.4.0: merge fields and tags; "Both" mode keeps the form email when Mailchimp fails.
* Core: atomic rate limits, uploaded modules start disabled, multisite uninstall.

= 2.3.0 =
* Security: Virtual Assistant endpoints use the real visitor IP (forwarding headers only behind Cloudflare or DOVETAIL_STUDIO_TRUSTED_PROXIES), a signed visitor token, site-wide hourly caps, a confirmation email without visitor text, private attachments and admin-only enquiries. The /front hot-patch endpoint, which could overwrite the module's PHP over the API, was removed.
* Core: shared Crypto (AES-256-GCM) and Http (client IP, rate limits) helpers; uploaded modules live in wp-content/dovetail-modules and need the install_plugins capability; cron events are cleared on module disable and plugin deactivation; optional data removal on uninstall with DOVETAIL_STUDIO_DELETE_DATA.
* Newsletter 3.3.0, Schema SEO 1.5.1, Schema SEO WooCommerce 1.2.1: save and validation notices are shown; single text domain `dovetail-studio`; versions read from module.php.
* Virtual Assistant 1.32.0: neutral bundled defaults with no business data, AI keys encrypted, weekly stats clean-up fixed, lossless event counters.

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
