# Schema SEO

Namespace: `DovetailStudio\SchemaSeo\Module`

Schema SEO provides the site's business entity (Organization/LocalBusiness), the site graph (WebSite, WebPage, Article, author Person) and the breadcrumb trail. It follows one rule:

- **Yoast SEO active and printing its graph:** Yoast owns the graph. The module only enriches Yoast's Organization (or adds one to Yoast's graph) and reads Yoast's breadcrumb trail for the theme. Yoast prints WebSite, WebPage, Article and BreadcrumbList.
- **No Yoast graph** (Yoast inactive, or its JSON-LD output disabled): the module adds every piece to the single Dovetail Studio graph printed by `DovetailStudio\Core\Seo\SchemaGraph`, linked by `@id` (`/#organization`, `/#website`, page URL, `#breadcrumb`, `#primaryimage`, `#article`).

The module never prints its own `<script>`.

## Structure

- `src/Module.php` boots the module.
- `src/Schema.php` builds and enriches the Organization entity (existing option names are preserved for upgrade compatibility).
- `src/SiteGraph.php` adds WebSite, WebPage (`CollectionPage`, `SearchResultsPage`, `ProfilePage` by context), the primary image, Article and the author Person to the standalone graph.
- `src/Breadcrumbs.php` resolves the breadcrumb trail and adds BreadcrumbList to the standalone graph.
- `src/functions.php` exposes `dovetail_studio_get_breadcrumbs()` for themes.
- `src/Admin.php` renders the settings screen. Module JavaScript lives in `assets/js/admin.js`.

## Breadcrumb trail

`dovetail_studio_get_breadcrumbs()` returns `array{label: string, url: string}[]`, current page last; it is empty on the front page, on 404 pages and when breadcrumbs are disabled. With Yoast owning the graph the trail is Yoast's (its breadcrumb settings apply, enabled or not); otherwise the module builds it from page ancestors, the blog page, the post type archive, the deepest term of the main taxonomy, term ancestors, search, dates and pagination. Themes render the trail and never print breadcrumb JSON-LD.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `dovetail_studio_breadcrumb_items` | filter | Change the final trail (`$items`, `$source`). With Yoast it runs inside `wpseo_breadcrumb_links`, so the change reaches Yoast's BreadcrumbList too. |
| `dovetail_studio_breadcrumb_trail` | filter | Provide a complete trail (`null` or items, `$home`); used by Schema SEO WooCommerce on shop pages. |
| `dovetail_studio_breadcrumb_taxonomy` | filter | Taxonomy used for a post's trail. |
| `dovetail_studio_breadcrumb_primary_term` | filter | Term used for a post's trail. |
| `dovetail_studio_schema_website` | filter | Standalone WebSite piece. |
| `dovetail_studio_schema_webpage` | filter | Standalone WebPage piece. |
| `dovetail_studio_schema_webpage_type` | filter | Standalone WebPage type (for example `ItemPage`). |
| `dovetail_studio_schema_article` | filter | Standalone Article piece. |
| `dovetail_studio_schema_article_post_types` | filter | Post types described as Article. Default `post`. |
| `dovetail_studio_schema_person` | filter | Standalone author Person piece. |

## Possible Errors and How to Fix Them

### Schema does not appear
Confirm the module is enabled and its Schema SEO setting is enabled. Clear full-page caches before retesting.

### Yoast and native module markup appear together
Check that Yoast is producing a usable graph and no third-party filter is disabling or replacing Yoast JSON-LD. The Dovetail Studio graph is printed only when Yoast did not build or print its graph.

### The breadcrumb trail differs from Yoast's settings
With Yoast active the trail comes from Yoast; review Yoast's breadcrumb settings and any `wpseo_breadcrumb_links` or `dovetail_studio_breadcrumb_items` callbacks.

### Breadcrumbs are missing in the theme
Confirm "Breadcrumbs" is enabled in the module settings and that the page is not the front page.
