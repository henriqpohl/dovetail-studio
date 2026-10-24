# Dovetail Studio Core Loader

`DovetailStudio\Core\Loader` is the central orchestrator. It discovers module manifests, stores enabled state, boots module classes, registers the Dovetail Studio admin UI, and handles module ZIP install/update/removal.

## Contract

A module manifest must provide `slug`, `name`, `version`, `bootstrap`, and `class`. `bootstrap` must point to the module's `src/Module.php`; `class` must identify the namespaced bootstrap class.

## Boot sequence

1. Discover manifests.
2. Resolve enabled state.
3. Require each enabled bootstrap.
4. Instantiate the declared class.
5. Call `boot()`.
6. Register Studio admin pages and module-management handlers.

## SEO services

`src/Core/Seo/` holds infrastructure shared by schema modules and loaded before any module:

- `Environment` detects Yoast SEO, WooCommerce and Yoast WooCommerce SEO, so every module agrees on the environment.
- `SchemaGraph` prints one JSON-LD `@graph` in `wp_head` (priority 99) when Yoast SEO did not build or print its graph. Modules add pieces through the `dovetail_studio_schema_graph` filter and never print their own script. It also exposes `SchemaGraph::id()` (Yoast-compatible `@id` values), `SchemaGraph::current_url()`, `SchemaGraph::has_piece()` and `yoast_handles_graph()`.

## Possible Errors and How to Fix Them

### Class not found
Make sure the manifest class matches the namespace and class declared in `src/Module.php`.

### ZIP upload fails
Check ZipArchive, filesystem write access, package structure, and path validation.

### Two JSON-LD graphs appear
Yoast SEO printed its graph and a module printed its own script. Modules must add pieces to `dovetail_studio_schema_graph` (standalone) or `wpseo_schema_graph` (Yoast) only.
