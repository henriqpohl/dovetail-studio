# Dovetail Studio

Dovetail Studio is the single WordPress plugin that loads and manages Dovetail modules. Modules are never standalone plugins.

## Architecture

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

Current module namespaces:

- `DovetailStudio\SchemaSeo\Module`
- `DovetailStudio\Newsletter\Module`
- `DovetailStudio\SchemaSeoWooCommerce\Module`
- `DovetailStudio\VirtualAssistant\Module`

The core loader is `DovetailStudio\Core\Loader` in `src/Core/Loader.php`.

## Module lifecycle

The loader discovers `modules/*/module.php`, reads enabled state from `dovetail_studio_modules`, requires the manifest bootstrap, instantiates its declared `class`, and calls `boot()`. New modules default to enabled. Disabled modules remain installed but are not booted.

Dovetail Studio also supports ZIP upload/update and removal of disabled modules. Module database settings are intentionally preserved when module files are removed.

## Shared admin UI

All Dovetail admin screens use `assets/admin/dovetail-admin.css`. Module-specific CSS should only contain UI that cannot be expressed with the shared design system.

## Versioning

Dovetail Studio uses its own version. Each module also declares its own version in `module.php` and in its `Module` bootstrap where required.

## Possible Errors and How to Fix Them

### A module is listed but does not load
Confirm it is enabled and that `module.php` points to `src/Module.php` with the correct namespaced class.

### Upload reports an invalid manifest
Ensure the ZIP contains one module root with `module.php`, `README.md`, `USAGE.md`, and `src/Module.php`, and that the manifest `bootstrap` stays inside that module directory.

### A module page has missing styles
Confirm `assets/admin/dovetail-admin.css` exists and that the page slug is included by the loader admin asset logic.

### WordPress reports a PHP fatal error after an update
Restore the previous module package, run `php -l` against every PHP file, and verify namespace/class names exactly match the manifest.
