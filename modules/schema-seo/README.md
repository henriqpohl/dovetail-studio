# Schema SEO

Namespace: `DovetailStudio\SchemaSeo\Module`

Schema SEO enriches Local Business/Organization structured data. It integrates with Yoast SEO when available and can output its own JSON-LD when Yoast does not provide the relevant graph.

## Structure

`src/Module.php` boots `src/Schema.php` and `src/Admin.php`. Module JavaScript lives in `assets/js/admin.js`. Existing option names are preserved for upgrade compatibility.

## Possible Errors and How to Fix Them

### Schema does not appear
Confirm the module is enabled and its Schema SEO setting is enabled. Clear full-page caches before retesting.

### Yoast and native module markup appear together
Check that Yoast is producing a usable Organization graph and no third-party filter is disabling or replacing Yoast JSON-LD.
