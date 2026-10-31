# Dovetail Studio

🌐 <a href="https://dovetail-consultancy.com">Dovetail Consultancy</a>

![Release](https://img.shields.io/badge/release-2.5.0-gold)
![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.2-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/php-%3E%3D8.0-777bb4?logo=php&logoColor=white)

![Schema SEO](https://img.shields.io/badge/schema_seo-1.6.1-blue)
![Schema SEO WooCommerce](https://img.shields.io/badge/schema_seo_woocommerce-1.3.0-blue)
![Newsletter](https://img.shields.io/badge/newsletter-3.4.0-blue)
![Virtual Assistant](https://img.shields.io/badge/virtual_assistant-1.33.1-blue)
![Login Guard](https://img.shields.io/badge/login_guard-1.0.0-blue)
![Vulnerability Watch](https://img.shields.io/badge/vulnerability_watch-1.0.0-blue)
![Hide Login Path](https://img.shields.io/badge/hide_login_path-1.0.0-blue)

![Yoast SEO](https://img.shields.io/badge/yoast_seo-integrated-a4286a)
![WooCommerce](https://img.shields.io/badge/woocommerce-integrated-96588a?logo=woocommerce&logoColor=white)

Dovetail Studio is the single WordPress plugin that loads and manages Dovetail modules: structured data and technical SEO, WooCommerce merchant data, newsletter forms, an AI virtual assistant, login protection, a hidden login address and vulnerability alerts. Modules are never standalone plugins; each one can be enabled, disabled, updated or removed from **Dovetail Studio → Modules**.

**Author:** <a href="https://dovetail-consultancy.com">Dovetail Consultancy</a>

## Documentation

### Plugin

- [Guide](./USAGE.md)
- [Contributing: build, tests, rules and release](./CONTRIBUTING.md)
- [Release test checklist](./test.md)
- [Changelog](./CHANGELOG.md)
- [Core loader and SEO services](./src/Core/README.md)

### Modules

| Module | Version | Purpose | Docs |
| --- | --- | --- | --- |
| Schema SEO | 1.6.1 | Organization, WebSite, WebPage, Article and breadcrumb structured data; enriches Yoast SEO or builds a single standalone graph | [README](./modules/schema-seo/README.md) · [Usage](./modules/schema-seo/USAGE.md) |
| Schema SEO WooCommerce | 1.3.0 | Product in the site graph, merchant data enrichment, WooCommerce breadcrumbs and technical SEO | [README](./modules/schema-seo-woocommerce/README.md) · [Usage](./modules/schema-seo-woocommerce/USAGE.md) |
| Newsletter | 3.4.0 | Contact Form 7 and Mailchimp integration with encrypted credentials and double opt-in | [README](./modules/newsletter/README.md) · [Usage](./modules/newsletter/USAGE.md) |
| Virtual Assistant | 1.33.1 | AI assistant with REST endpoints, knowledge base, enquiries, spam controls and front-end interface | [README](./modules/virtual-assistant/README.md) · [Usage](./modules/virtual-assistant/USAGE.md) |
| Login Guard | 1.0.0 | Brute force lockouts, IP block list with unblock, two-factor authentication, reCAPTCHA/Turnstile, sign-in alerts, sign-in activity log, password policies and hardening | [README](./modules/login-guard/README.md) · [Usage](./modules/login-guard/USAGE.md) |
| Vulnerability Watch | 1.0.0 | Daily check of core, plugins and themes against the WPVulnerability database, core file and sensitive file checks, and alerts and a log of site changes (administrators, plugins, themes, settings) | [README](./modules/vulnerability-watch/README.md) · [Usage](./modules/vulnerability-watch/USAGE.md) |
| Hide Login Path | 1.0.0 | Moves the login to an address you choose and hides wp-login.php and wp-admin from visitors (replaces WPS Hide Login) | [README](./modules/hide-login-path/README.md) · [Usage](./modules/hide-login-path/USAGE.md) |

## Requirements

- WordPress 6.2 or later and PHP 8.0 or later.
- Optional integrations: Yoast SEO, WooCommerce, Contact Form 7 and Mailchimp, depending on the modules in use.

## Architecture

```text
dovetail-studio/
├── dovetail-studio.php   # Plugin header and bootstrap
├── src/Core/
│   ├── Loader.php        # Module discovery, enabled state, admin UI, ZIP install/update/removal
│   └── Seo/              # Environment detection and the single JSON-LD graph shared by schema modules
├── assets/admin/         # Shared admin design system
└── modules/<slug>/       # One folder per module
```

Every module follows the same contract:

```text
modules/<slug>/
├── module.php
├── README.md
├── USAGE.md
├── src/
│   └── Module.php
├── assets/       # optional
├── views/        # optional
├── resources/    # optional
└── docs/         # optional
```

`module.php` is a declarative manifest. `src/Module.php` contains the namespaced module bootstrap class. Module-specific implementation files live below `src/`.

| Module | Bootstrap class |
| --- | --- |
| Schema SEO | `DovetailStudio\SchemaSeo\Module` |
| Schema SEO WooCommerce | `DovetailStudio\SchemaSeoWooCommerce\Module` |
| Newsletter | `DovetailStudio\Newsletter\Module` |
| Virtual Assistant | `DovetailStudio\VirtualAssistant\Module` |
| Login Guard | `DovetailStudio\LoginGuard\Module` |
| Vulnerability Watch | `DovetailStudio\VulnerabilityWatch\Module` |
| Hide Login Path | `DovetailStudio\HideLoginPath\Module` |

The core loader is `DovetailStudio\Core\Loader` in `src/Core/Loader.php`.

## Structured data at a glance

Schema modules never print their own `<script>`. With Yoast SEO active they enrich or add pieces inside Yoast's graph; without it they add pieces to the single graph printed by `DovetailStudio\Core\Seo\SchemaGraph`. Themes render breadcrumbs from `dovetail_studio_get_breadcrumbs()` and never print breadcrumb JSON-LD.

| Scenario | Schema graph | Breadcrumb trail |
| --- | --- | --- |
| Yoast SEO active | Yoast's, enriched by the modules | Yoast's |
| Yoast SEO inactive or its JSON-LD disabled | Dovetail Studio's | Dovetail Studio's (WooCommerce's on shop pages) |
| Rank Math, SEOPress, All in One SEO or The SEO Framework active | That plugin's; Dovetail Studio prints none | Dovetail Studio's for the theme (no BreadcrumbList from Dovetail) |

A site can represent an organization or a person. With Yoast this follows Yoast's "Site representation"; without Yoast it is the Schema SEO setting "This site represents". In Person mode a business is optional and is linked to the person (`founder` / `worksFor`).

## Naming conventions

All project-authored PHP follows PSR-4-style naming inside the `DovetailStudio` namespace:

| Item | Convention | Examples |
| --- | --- | --- |
| Class file | Exact class name in PascalCase, no underscores, one class per file | `Loader.php`, `SchemaGraph.php`, `SiteGraph.php`, `Breadcrumbs.php` |
| Class | PascalCase, matching its file name | `final class SchemaGraph` |
| Namespace | `DovetailStudio\<Area>`, PascalCase segments matching the folders below `src/` | `DovetailStudio\Core\Seo`, `DovetailStudio\SchemaSeo` |
| Procedural file (functions, manifest, runtime, views) | Lowercase kebab-case | `module.php`, `functions.php`, `runtime.php`, `public-settings.php` |
| Folder mapped to a namespace | PascalCase | `src/Core/`, `src/Core/Seo/` |
| Any other folder | Lowercase kebab-case | `modules/schema-seo/`, `src/services/`, `assets/`, `views/` |
| Global function | `dovetail_studio_*` or the module prefix, snake_case | `dovetail_studio_get_breadcrumbs()`, `dovetail_schema_seo_woocommerce_get_settings()` |
| Hook | `dovetail_studio_*` or the module prefix, snake_case | `dovetail_studio_schema_graph`, `dovetail_studio_breadcrumb_items` |
| Method and variable | snake_case | `get_items()`, `$webpage_id` |

Existing option names, hook names and other persisted identifiers keep their names even when they predate these conventions (for example `dovetail_newsletter_settings`).

> [!NOTE]
> These file names do not follow the WordPress Coding Standards `class-*.php` rule. When PHPCS runs on this plugin, exclude `WordPress.Files.FileName` (for example `<exclude name="WordPress.Files.FileName"/>` in the `WordPress` rule of `phpcs.xml.dist`).

## Module lifecycle

The loader discovers `modules/*/module.php` (bundled) and `wp-content/dovetail-modules/*/module.php` (uploaded), reads enabled state from `dovetail_studio_modules`, requires the manifest bootstrap, instantiates its declared `class`, and calls `boot()`. New modules default to enabled. Disabled modules remain installed but are not booted.

Dovetail Studio also supports ZIP upload/update and removal of disabled modules. Module database settings are intentionally preserved when module files are removed.

## Shared admin UI

All Dovetail admin screens use `assets/admin/dovetail-admin.css`. Module-specific CSS should only contain UI that cannot be expressed with the shared design system.

`DovetailStudio\Core\AdminScreens` decides which screens belong to the Studio: the Dashboard, Modules and every screen an enabled module declares in its manifest (`'admin_screens' => array( 'pages' => array( … ), 'post_types' => array( … ) )`). On those screens it loads the shared CSS and removes every WordPress and third-party admin notice, update and security nags included; they still show everywhere else in WP Admin. Studio and module messages use `AdminScreens::notice()`, `AdminScreens::settings_errors()` or the `dovetail_studio_notices` action, so they stay visible.

## Translations

Text domain `dovetail-studio`; translations go in `languages/dovetail-studio-{locale}.mo` (template: `languages/dovetail-studio.pot`). The plugin ships in English only. To add a language, copy the `.pot` to `languages/dovetail-studio-{locale}.po` (for example `dovetail-studio-pt_BR.po`), translate it and run `composer build` to compile the `.mo`. The Virtual Assistant front end is translated from the same files: its strings are listed in `modules/virtual-assistant/src/services/i18n-js.php` and sent to the browser for the site language. When a `.po` exists, `tests/translations.php` fails while any string of the `.pot` is missing from it.

## Development

Commands, when to run the build (`php tools/build.php`), tests, code standards, the essential rules and the release steps are in [CONTRIBUTING.md](./CONTRIBUTING.md). The manual release checklist is [test.md](./test.md).

## Versioning

Dovetail Studio uses its own version, recorded in the plugin header, `readme.txt` and [`CHANGELOG.md`](./CHANGELOG.md). Each module also declares its own version in `module.php` and in its `Module` bootstrap where required; bundled module versions are listed in every changelog entry.

## Possible Errors and How to Fix Them

### A module is listed but does not load
Confirm it is enabled and that `module.php` points to `src/Module.php` with the correct namespaced class.

### Upload reports an invalid manifest
Ensure the ZIP contains one module root with `module.php`, `README.md`, `USAGE.md`, and `src/Module.php`, and that the manifest `bootstrap` stays inside that module directory.

### A module page has missing styles
Confirm `assets/admin/dovetail-admin.css` exists and that the page slug is included by the loader admin asset logic.

### WordPress reports a PHP fatal error after an update
Restore the previous module package, run `php -l` against every PHP file, and verify namespace/class names exactly match the manifest.
