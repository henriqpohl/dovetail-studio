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

## Possible Errors and How to Fix Them

### Class not found
Make sure the manifest class matches the namespace and class declared in `src/Module.php`.

### ZIP upload fails
Check ZipArchive, filesystem write access, package structure, and path validation.
