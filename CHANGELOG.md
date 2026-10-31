# Changelog

All notable changes to Dovetail Studio and its bundled modules should be documented in this file.

The format is based on Keep a Changelog and follows Semantic Versioning where possible. Module versions are listed in each entry; every module also declares its own version in `module.php`.

## [2.6.0] - 2026-10-31

### Changed

- Core:
  - New `DovetailStudio\Core\View` helper: `View::render( $dir, $name, $args )` and `View::capture()` include `views/<name>.php` in its own scope, refuse names outside `views/` and report a missing template with `_doing_it_wrong()`.
  - The Dashboard and Modules screens moved to `src/Core/views/`; `Loader` prepares the module rows and the notice.
  - `AdminScreens::notice()` renders `src/Core/views/notice.php`; the markup is unchanged.
  - New `assets/admin/dovetail-admin.js`, loaded with the shared CSS on every Studio screen: links and buttons with `data-dovetail-confirm` ask before they run. The Modules screen uses it instead of an inline `onclick`.
  - `assets/admin/dovetail-admin.css`:
    - Design tokens for every colour, shadow and font size (neutrals, primary, success, warning, danger, three shadows, `rem` interface sizes and fluid titles). They are also set on `.dovetail-admin-page`, so module list screens can use them. No rule writes a colour or a `px` font size any more; near-identical greys, greens and secondary text colours were merged into one token each.
    - Titles are fluid: the page title goes from 22px on a 375px screen to 26px from 1200px, and card titles from 16px to 18px. Desktop sizes are unchanged.
    - New shared classes `dovetail-code-editor`, `dovetail-inline-check`, `dovetail-required`, `dovetail-field-missing`, `dovetail-text-success`, `dovetail-text-danger` and `dovetail-chart`.
- Login Guard:
  - The admin screen and its six tabs, the two-factor code screen and profile section, the captcha field and the block page moved to `views/`; `Admin` only checks permissions and builds each tab's data.
  - The reCAPTCHA score chart sizes itself with `dovetail-chart` instead of an inline `style`.
- Vulnerability Watch:
  - The admin screen, its checkbox rows and the site changes table moved to `views/`.
- Hide Login Path:
  - The admin screen moved to `views/admin-page.php`.
- Schema SEO:
  - The settings screen and its sections, fields, opening hours, JSON-LD preview and integration summary moved to `views/`.
- Schema SEO WooCommerce:
  - The settings screen, status badge and missing WooCommerce notice moved to `views/`.
- Newsletter:
  - The settings screen and the front-end "not configured" message moved to `views/`.
- Virtual Assistant:
  - The settings page and its eight tabs, the company notes page, the attachment box, the enquiry list columns, the setup review and the admin notices moved to `views/`; the service functions only handle the forms and prepare the data.
  - No inline JavaScript or CSS left in the admin templates: the door hint under **Add a door → Service** is `assets/js/admin-doors.js` (Doors tab only; it was a `<script>` between two table rows), and the code editors, required fields, enquiry list columns and Collapse Avatar checkbox use the shared Studio classes. The "Call back: Yes" column now uses the admin red (`#d63638`) instead of `#E2231A`, and "Answered: Yes" the Studio success green (`#116329`) instead of `#1a7f37`.
  - Front end: the loader settings (`window.DT_VA_LOADER`) are added with `wp_add_inline_script()` before `front-loader.js`, keeping `data-no-optimize`, `data-no-defer` and `data-cfasync="false"` through `wp_inline_script_attributes`; the saved theme colours are added after `front-tab.css` with `wp_add_inline_style()`. Both used to be printed directly in `wp_head`.
  - `admin-json.js` is loaded through `dt_va_asset()`, and `admin-json.js`, `admin-doors.js` and `dovetail-admin.js` are minified by `tools/build.php`.
- Plugin:
  - `CONTRIBUTING.md`, `README.md` and the Core docs state that HTML lives only in `views/`; the per-file PHPCS exclusions for HTML templates were removed.
- Tests:
  - `tests/core-view.php` covers `Core\View` (rendering, scope isolation, refused names, missing templates); the simulator gained `_doing_it_wrong()`.
  - Tests for the shared admin script and assets, the CSS design tokens (no colour or `px` font size outside them), the notice markup, the Doors-only script, the loader settings attributes and the theme colours; the simulator gained `wp_add_inline_style()`.

## [2.5.0] - 2026-10-31

Bundled modules: Newsletter 3.4.0, Schema SEO 1.6.1, Schema SEO WooCommerce 1.3.0, Virtual Assistant 1.33.1, Login Guard 1.0.0, Vulnerability Watch 1.0.0, Hide Login Path 1.0.0.

### Added

- Login Guard 1.0.0: brute force protection for the WordPress login (lockouts after failed sign-ins and password reset requests counted per IP over a period, immediate lockout of unknown usernames, immediate site-wide block of IPs that try usernames such as `admin` when no such account exists), a block list in Dovetail Studio → Login Guard (filter, unblock, make permanent, CSV export, manual blocks), strong passwords for admins and publishers, the Have I Been Pwned check for admins (k-anonymity, fails open), generic login errors, no `admin` registration, user enumeration hardening (`?author=N`, REST users, oEmbed, users sitemap), application passwords off, blocking of POSTs without User-Agent and Referer, custom block page text, an allowlist, WP-CLI commands and the `DOVETAIL_LOGIN_GUARD_DISABLED` switch. Blocks and failures live in two custom tables; pages only look up blocks while a site-wide block is active.
- Login Guard: two-factor authentication with authenticator apps (TOTP, QR code drawn on the server, ten recovery codes, "remember this device", Off/Optional/Required per role with a grace period, administrator reset, `wp dovetail login-guard reset-2fa`).
- Login Guard: Google reCAPTCHA v3 or Cloudflare Turnstile on the login and registration forms (WordPress and WooCommerce), with the reCAPTCHA threshold, a score chart, test mode and an emailed one-time sign-in link for low scores; the secret is stored encrypted.
- Login Guard: email alerts in Wordfence's format for administrator sign-ins (every time or only from a new IP), blocks and lockouts, with recipients, an hourly cap and a summary every 1–30 days; every email can be switched off.
- Login Guard: activity log (Activity tab) of sign-ins, failures, sign-outs, lockouts, blocks, two-factor and captcha results, kept 90 days by default.
- Login Guard: XML-RPC authentication off by default, and an option to switch XML-RPC off completely.
- Vulnerability Watch 1.0.0: daily check of WordPress core, plugins and themes (inactive ones included by default) against the free WPVulnerability database, with a list of the vulnerabilities affecting the installed versions (severity, CVSS score, fixed version, references), Check now, a minimum severity, emails for new vulnerabilities and reminders every N days; only slugs and the WordPress version are sent, answers are cached for 12 hours.
- Vulnerability Watch: site change alerts, listed under Site Changes (latest 200 entries) and emailed to the Vulnerability Watch recipients (at most 20 per hour) (each group can be switched off): new administrators and users given administrator access, plugins and themes installed, activated, deactivated, switched or deleted, plugin and theme updates (one email per update run, with their own switch), changes of the admin email, site address, registration and default role, and a daily check of the WordPress core files against the official checksums from api.wordpress.org (changed, missing and unknown PHP files, emailed once per set of findings), and a daily check for sensitive files a browser can download (debug and error logs, `.env`, `wp-config.php` copies, `.git`, phpinfo files, database dumps and backup archives), and alerts for plugins and themes changed outside WordPress (added, removed or with a new version by FTP or on the server, from a daily snapshot; plugins switched on or off without WordPress's actions, at once).
- Hide Login Path 1.0.0: moves the login to a path you choose (replaces WPS Hide Login); it acts once the module is enabled and a login path is saved. `wp-login.php`, `wp-signup.php`, `wp-activate.php` and signed-out `wp-admin` redirect to a configurable path (default `/404/`); login, logout, lost password and registration links and WordPress's emails use the new address; the password form of protected posts keeps working; guessable paths, `wp-` paths and existing page addresses are refused; `DOVETAIL_HIDE_LOGIN_PATH_DISABLED` in `wp-config.php` brings `wp-login.php` back.
- Core: `DovetailStudio\Core\Mail::alert()` sends alert emails in one format for every module (Login Guard and Vulnerability Watch).
- Core: `DovetailStudio\Core\Captcha` verifies reCAPTCHA and Turnstile tokens for every module.
- Core: `uninstall.php` drops the Login Guard tables, options and user meta when `DOVETAIL_STUDIO_DELETE_DATA` is set.
- Tests: `tests/login-guard.php`, `tests/login-guard-extras.php`, `tests/vulnerability-watch.php`, `tests/vulnerability-watch-changes.php` and `tests/hide-login-path.php`; the simulator gained `dbDelta()`, users, roles and user meta, sign-in helpers, `wp_remote_get()`, `wp_add_inline_script()` and `wpautop()`.

### Changed

- Core: no module-specific code left; the unused `.dovetail-page-header__actions` style was removed.
- Virtual Assistant 1.33.1: the Dovetail Studio → Virtual Assistant shortcut is registered by the module instead of the Core loader.
- Schema SEO 1.6.1: the unhooked `Admin::add_settings_link()` was removed.
- Virtual Assistant 1.33.1: the Turnstile check of the enquiry form uses `Core\Captcha`; its behaviour (refused when Cloudflare cannot be reached) and the `DT_VA_TURNSTILE_*` constants are unchanged.
- Core: `Loader::asset()` returns a module asset's URL and version, preferring the `.min` file built by `tools/build.php`; the Virtual Assistant's `dt_va_asset()` uses it, and Login Guard's `assets/js/recaptcha.js` is built and served the same way.

## [2.4.0] - 2026-10-25

Bundled modules: Newsletter 3.4.0, Schema SEO 1.6.0, Schema SEO WooCommerce 1.3.0, Virtual Assistant 1.33.0.

### Fixed

- Schema SEO with Yoast SEO in Person mode: Yoast's site Person (`Person` + `Organization`) is no longer taken for the business and filled with its address, phone and hours. The Person is kept as Yoast built it, and a configured business is added as a separate entity linked both ways (`founder` / `worksFor`).
- Schema SEO with Yoast SEO: no Organization without a name is added to Yoast's graph when no business name is set.
- Schema SEO: BreadcrumbList leaves out parent items without a URL (Google needs a URL on every item but the last); profile URL lists keep only full http(s) URLs.
- Core: the module's JSON float precision is restored after the graph is printed, so other plugins keep the host setting.
- Virtual Assistant: the answers to Step questions set in the admin now reach the brief and the team's email (labelled by their answer key), and a door with its own service is named in the enquiry, its subject and the digest; before, only the built-in answers were listed.
- Virtual Assistant: the `/config` URL carries a version that changes whenever an assistant setting is saved, so a new door, step or text shows on the next page load instead of after the 5-minute browser cache.
- Virtual Assistant: AI Keys shows “Primary provider” / “Secondary provider” and “OpenAI” (was “Openai”); its labels and the daily-check status are translatable.
- Core: the upload card and its error message name the modules directory actually configured (`DOVETAIL_STUDIO_MODULES_DIR` or the filter) instead of a fixed `wp-content/dovetail-modules`.
- Virtual Assistant: scheduled events are registered on `init`, so no text is translated before `init` (WordPress 6.7+ warning).

### Added

- Schema SEO: Person mode without Yoast SEO (“This site represents: a person”): name, job title, description, photo and profiles; the Person publishes the site, and an optional business is linked to it.
- Core and Schema SEO: with Rank Math, SEOPress, All in One SEO or The SEO Framework active, Dovetail Studio prints no second JSON-LD graph (`Environment::other_seo_plugin()`, filter `dovetail_studio_other_seo_plugin`); the WooCommerce module then leaves WooCommerce's product and breadcrumb JSON-LD alone.
- Schema SEO WooCommerce: flat shipping details (`OfferShippingDetails`) on every offer; the return policy is also published on the Organization and carries `returnPolicyCountry`; the country defaults to the WooCommerce store country.
- Virtual Assistant: personal data tools — enquiries in Tools > Export/Erase Personal Data, a retention period (default 24 months, `DT_VA_RETENTION_MONTHS`), attachments deleted with their enquiry, and a suggested privacy policy text.
- Virtual Assistant: closed dates (holidays) in the opening hours, used by the server and the front end.
- Virtual Assistant: “Sender email” setting (`DT_VA_FROM`).
- Virtual Assistant: while the assistant is hidden, a notice names the required fields still empty (Phone — display, Public email) with a link to them, on the Studio screens and the WordPress Dashboard and Plugins screens; the fields are marked as required. Public company details are saved before the notices are printed, so the notice goes away on the save itself.
- Newsletter: Mailchimp merge fields from CF7 fields and tags for new subscribers.
- Tests for each change (Yoast Person and Organization modes, other SEO plugins, Person mode, privacy tools, closed dates, rate limits, translations before `init`).

### Changed

- Virtual Assistant: every email is sent from an address on the site's domain (default `wordpress@` the domain) with the enquiry email as Reply-To, so mail passes SPF/DKIM/DMARC instead of being sent “from” a Gmail or Outlook address.
- Virtual Assistant: only the tab styles (`front-tab.css`, ~4 KB) load with the page; the panel styles load with `front.js`. Theme-specific CSS (site-menu pill, `html, body { overflow-x: hidden }` on phones, a hard-coded font) removed; the widget uses the theme font.
- Virtual Assistant: the knowledge base and rules form a fixed prompt prefix and the selected service goes last, so OpenAI and Anthropic (`cache_control`) can serve the repeated part from their prompt cache; provider diagnostics are written only when the outcome changes.
- Virtual Assistant: `/event` requires the visitor token (kept for the browser tab's session).
- Virtual Assistant: admin examples in English for a marketing and design agency (`web1`, `site_type`, `turnaround` …); the module docs (`docs/`) rewritten in English; a detailed `USAGE.md` for every settings tab; the Add a door form shows which step a door opens (Service + `1`) and whether it exists, and the Add a step form explains naming and order, with a complete flow example; the obsolete `plan.md` removed.
- Virtual Assistant and Schema SEO: neutral English wording and examples (“call” instead of “ring”, American English spelling by default, no Irish examples); the company and sender names default to the site title.
- Newsletter: in “Both” mode a Mailchimp failure no longer stops the Contact Form 7 email; the visitor is told the subscription failed.
- Core: rate limits are counted atomically (object cache, or one option row per window named with its expiry) instead of a transient read and rewritten per hit.
- Core: an uploaded module starts disabled; module messages travel as codes, not text, in the redirect URL.
- Core: `uninstall.php` reads the cron hooks from the module manifests and cleans every site of a multisite network.
- Settings read once per request in Newsletter, Schema SEO and Schema SEO WooCommerce; remaining admin texts translatable.
- Core: the admin menu icon no longer flashes black when a page loads; it starts in the admin menu gray that WordPress then adapts to the color scheme.
- `readme.txt`: “Tested up to: 7.0”. Cloudflare ranges checked against cloudflare.com/ips.

### Removed

- Schema SEO: the WebSite `SearchAction` (Google retired the sitelinks search box).

## [2.3.0] - 2026-10-24

Bundled modules: Newsletter 3.3.0, Schema SEO 1.5.1, Schema SEO WooCommerce 1.2.1, Virtual Assistant 1.32.0.

### Security

- Virtual Assistant: removed the `POST /wp-json/dt-va/v1/front` hot-patch endpoint, which let an administrator credential overwrite the module's PHP and front-end files over the API; `/ask` and `/submit` use the real client IP, a signed visitor token and site-wide hourly caps; the visitor confirmation email no longer echoes visitor text; attachments get random names, a deny rule and an admin-only download; enquiries are restricted to administrators.
- Core: module upload and removal require `install_plugins` and respect `DISALLOW_FILE_MODS`.

### Added

- Core: `DovetailStudio\Core\Crypto` (shared AES-256-GCM, used by Newsletter and Virtual Assistant) and `DovetailStudio\Core\Http` (client IP behind trusted proxies, rate limits).
- Core: uploaded modules are stored in `wp-content/dovetail-modules/` (`DOVETAIL_STUDIO_MODULES_DIR`), outside the plugin folder; `Loader::path_url()` for module asset URLs.
- Core: manifest key `cron_hooks`, cleared on module disable/remove and plugin deactivation; `uninstall.php` with opt-in data removal (`DOVETAIL_STUDIO_DELETE_DATA`).
- `.distignore`, `.gitattributes` and `.gitignore` to keep development files out of packages.
- Core: `DovetailStudio\Core\AdminScreens` and the manifest key `admin_screens`. Every Studio screen (Dashboard, Modules and the declared module screens) gets the shared CSS and hides WordPress and third-party notices, update and security nags included; Studio notices go through `AdminScreens::notice()` / `dovetail_studio_notices`. Replaces the Schema SEO screen's own notice removal and the Loader's fixed list of pages.
- Translations: `languages/dovetail-studio.pot`, `load_plugin_textdomain()`, every Virtual Assistant text (admin, emails, front end) and the Studio screens (menus, dashboard, modules page, upload and removal messages, module names and descriptions) translatable. Source language English; no bundled translations.
- `tools/build.php` (front-end string list, `.pot`, `.mo` from each `.po`, minified assets) and the test suite: `php tests/run.php`, `node tests/browser/run.js`.

### Changed

- Every module reads its version from `module.php`; single text domain `dovetail-studio`.
- Newsletter uses the Core Crypto and Http helpers.
- Virtual Assistant: neutral bundled defaults (no business data in the code, knowledge base template, enquiries to `admin_email` until set); AI keys encrypted; atomic event counters; cached public defaults.
- Virtual Assistant: only a small loader on every page; configuration and `front.js` load on first interaction or when the page is idle (`GET /dt-va/v1/config`); minified assets.
- Virtual Assistant: opening hours and the site time zone used everywhere (server, emails, invite, front end); the AI answers in the visitor's language; references `DT-YYMM-XXXXXX`; questions capped at 15 s (`DT_VA_ASK_DEADLINE`); own token limit (`DT_VA_TOKEN_PER_HOUR`); contact details no longer kept in the browser; the theme-specific Google rating strip removed.
- Core: behind Cloudflare only `CF-Connecting-IP` is read; `X-Forwarded-For` / `X-Real-IP` only from `DOVETAIL_STUDIO_TRUSTED_PROXIES`.
- Schema SEO: option `dovetail_schema_seo`, page `dovetail-schema-seo`, constants `DOVETAIL_SCHEMA_SEO_*` (were `lbs_options`, `local-business-schema`, `LBS_*`); no business entity until a name is set; LocalBusiness types only with an address (Organization otherwise); options cached per request.
- Schema SEO WooCommerce: variable products are a `ProductGroup` with one `Product` per variation (`hasVariant`), each with its own offer.

### Fixed

- Newsletter, Schema SEO and Schema SEO WooCommerce settings screens now show validation errors and the saved notice.
- Virtual Assistant: weekly statistics older than eight weeks are deleted again.
- Newsletter manifest version matched the code (3.1.0 vs 3.2.0).
- Newsletter: someone who had unsubscribed can subscribe again (Mailchimp contact status is read first); "already subscribed" is reported correctly.
- Virtual Assistant: the call-back form no longer sends a placeholder email address; the contact card reads correctly without a city.

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
