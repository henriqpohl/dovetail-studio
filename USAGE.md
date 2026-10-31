# Dovetail Studio — Usage

## Manage modules

Open **Dovetail Studio → Modules**. Use the checkbox to enable or disable an installed module. Save before removing a module. Disabled modules may be removed without deleting their WordPress options.

## Install or update a module

Upload a ZIP from **Dovetail Studio → Modules → Upload Module**. A valid package must follow the standard structure and provide a namespaced `Module` class through its manifest.

Uploaded modules are stored in `wp-content/dovetail-modules/` (override with the `DOVETAIL_STUDIO_MODULES_DIR` constant), outside the plugin folder, so updating Dovetail Studio never deletes them. An uploaded module replaces a bundled module with the same slug. Uploading and removing need the `install_plugins` capability and are unavailable when `DISALLOW_FILE_MODS` is set. Only uploaded modules can be removed; bundled ones can be disabled.

Modules can declare `'cron_hooks' => array( ... )` in `module.php`; those events are cleared when the module is disabled or removed and when the plugin is deactivated.

## Shared Core helpers

- `\DovetailStudio\Core\Crypto::encrypt( $secret, $context )` / `decrypt( $stored, $context )`: AES-256-GCM keyed from the WordPress salts. Changing `AUTH_KEY` or `SECURE_AUTH_KEY` makes saved secrets unreadable; save them again.
- `\DovetailStudio\Core\Http::client_ip()`: visitor IP. Forwarding headers are trusted only from Cloudflare edges or the addresses in `DOVETAIL_STUDIO_TRUSTED_PROXIES` (comma-separated IPs or CIDR, e.g. a load balancer).
- `\DovetailStudio\Core\Http::rate_limit( $bucket, $limit, $window, $key )`: fixed-window limit per IP, or site-wide with `$key = 'global'`.
- `\DovetailStudio\Core\Mail::alert( $module, $title, $lines, $recipients, $module_url, $settings_url )`: plain-text alert email in the shared format (`[Module Alert] example.com Title`, a header with the site, module and time, and a footer linking to the email settings).
- `\DovetailStudio\Core\Captcha::verify( $provider, $secret, $token, $action )`: checks a Google reCAPTCHA v3 (`recaptcha`) or Cloudflare Turnstile (`turnstile`) token; returns `reachable`, `success`, `score` and `errors`, and the module decides what to do when the provider cannot be reached. `script_url()` and `posted_token()` give the browser script and the posted token.

## Uninstall

Deleting the plugin clears its scheduled events and keeps all data. To also delete all module data (settings, enquiries, statistics, Login Guard tables and logs, Vulnerability Watch state and site change log), define `DOVETAIL_STUDIO_DELETE_DATA` as `true` in `wp-config.php` before deleting. Uploaded modules and enquiry attachments are never deleted automatically.

Example manifest:

```php
return array(
    'slug'        => 'example',
    'name'        => 'Example',
    'version'     => '1.0.0',
    'bootstrap'   => __DIR__ . '/src/Module.php',
    'class'       => 'DovetailStudio\\Example\\Module',
    'admin_url'   => 'admin.php?page=dovetail-example',
    // Optional:
    'admin_screens' => array( 'pages' => array( 'dovetail-example' ) ), // styled by the Studio, free of other notices
    'cron_hooks'    => array( 'dovetail_example_daily' ),                // cleared when the module is disabled or removed
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
