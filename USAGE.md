# Dovetail Studio — Usage

## Manage modules

Open **Dovetail Studio → Modules**. Use the checkbox to enable or disable an installed module. Save before removing a module. Disabled modules may be removed without deleting their WordPress options.

## Install or update a module

Upload a ZIP from **Dovetail Studio → Modules → Upload Module**. A valid package must follow the standard structure and provide a namespaced `Module` class through its manifest.

Example manifest:

```php
return array(
    'slug'        => 'example',
    'name'        => 'Example',
    'version'     => '1.0.0',
    'bootstrap'   => __DIR__ . '/src/Module.php',
    'class'       => 'DovetailStudio\\Example\\Module',
    'admin_url'   => 'admin.php?page=dovetail-example',
);
```

## Develop a new module

Start with the same files every time: `module.php`, `README.md`, `USAGE.md`, and `src/Module.php`. Put business logic in focused files under `src/`, public assets under `assets/`, templates under `views/`, static data under `resources/`, and extended documentation under `docs/`.

The `Module` class is the only bootstrap entry point. Do not add a WordPress `Plugin Name` header to modules and do not make modules executable independently from Dovetail Studio.

## Possible Errors and How to Fix Them

### New module does not appear
Verify its folder is directly under `modules/` and contains `module.php`.

### Module appears but Open does nothing
Check the manifest `admin_url` and confirm the module registers that admin page when `boot()` runs.

### Module update fails
Confirm filesystem permissions, PHP ZipArchive availability, and that the ZIP has no unsafe paths or symlinks.
