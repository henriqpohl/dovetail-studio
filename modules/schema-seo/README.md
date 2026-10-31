# Schema SEO

Namespace: `DovetailStudio\SchemaSeo\Module`

Schema SEO provides the site's business entity (Organization/LocalBusiness), the site graph (WebSite, WebPage, Article, author Person) and the breadcrumb trail. It follows one rule:

- **Yoast SEO active and printing its graph:** Yoast owns the graph. The module only enriches Yoast's Organization (or adds one to Yoast's graph) and reads Yoast's breadcrumb trail for the theme. Yoast prints WebSite, WebPage, Article and BreadcrumbList.
- **No Yoast graph** (Yoast inactive, or its JSON-LD output disabled): the module adds every piece to the single Dovetail Studio graph printed by `DovetailStudio\Core\Seo\SchemaGraph`, linked by `@id` (`/#organization`, `/#/schema/person/site`, `/#website`, page URL, `#breadcrumb`, `#primaryimage`, `#article`).
- **Another SEO plugin** (Rank Math, SEOPress, All in One SEO, The SEO Framework): that plugin owns the schema and no Dovetail Studio graph is printed. If its schema output is switched off, return an empty string from the `dovetail_studio_other_seo_plugin` filter.

## Organization or Person

- **Yoast in Organization mode:** Yoast's Organization is enriched with the module values.
- **Yoast in Person mode:** Yoast's site Person (typed `Person` + `Organization`) is never changed beyond a `worksFor` link. When a business name is set, the business is added as its own entity (`/#organization`) with `founder` pointing to the Person.
- **Without Yoast:** "This site represents" chooses Organization or Person. In Person mode the module prints the Person (name, job title, description, photo, profiles) as the publisher of the site; a business is optional and linked the same way.

The module never prints its own `<script>`.

## Structure

- `src/Module.php` boots the module.
- `src/Schema.php` builds and enriches the Organization entity (option `dovetail_schema_seo`). No entity is printed until a name is set; LocalBusiness types need an address and are printed as Organization until one is set.
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
| `dovetail_studio_schema_site_person` | filter | The Person the site represents (Person mode, no Yoast). |
| `dovetail_studio_other_seo_plugin` | filter | Name of the other SEO plugin that owns the schema; empty lets the Dovetail graph print. |

## Possible Errors and How to Fix Them

### Schema does not appear
Confirm the module is enabled and its Schema SEO setting is enabled. With Rank Math, SEOPress, All in One SEO or The SEO Framework active, the module prints nothing on purpose (the status box on the settings screen says so). Clear full-page caches before retesting.

### A personal site shows no Person
With Yoast SEO, set Yoast to represent a Person (Settings > Site representation). Without Yoast, choose "This site represents: A person" and fill in the person name.

### Yoast and native module markup appear together
Check that Yoast is producing a usable graph and no third-party filter is disabling or replacing Yoast JSON-LD. The Dovetail Studio graph is printed only when Yoast did not build or print its graph.

### The breadcrumb trail differs from Yoast's settings
With Yoast active the trail comes from Yoast; review Yoast's breadcrumb settings and any `wpseo_breadcrumb_links` or `dovetail_studio_breadcrumb_items` callbacks.

### Breadcrumbs are missing in the theme
Confirm "Breadcrumbs" is enabled in the module settings and that the page is not the front page.
