# Contributing to Dovetail Studio

How to work on the plugin: the commands, when to run them, the rules every change follows and the release steps. What the plugin does and how it is built is in the [README](./README.md); how to use it is in the [guide](./USAGE.md).

## Requirements

- PHP 8.0 or later with the `pdo_sqlite`, `openssl`, `zip` and `mbstring` extensions.
- Composer, for the code standards (development only).
- Node and Playwright, only for the browser tests of the Virtual Assistant.

There is no npm build: the plugin runs from the files in the repository.

## Commands

```bash
composer install     # PHP_CodeSniffer, WPCS and PHPCompatibility (development only)
composer build       # php tools/build.php: translations template and minified assets
composer test        # php tests/run.php: PHP test suite (no WordPress needed)
composer lint        # report coding standard problems (phpcs.xml.dist)
composer format      # fix what can be fixed automatically (phpcbf)
node tests/browser/run.js   # browser tests of the Virtual Assistant
```

`php tests/run.php login-guard` runs a single test file (`tests/login-guard.php`).

## When to run the build

`php tools/build.php` (or `composer build`) runs **in the repository, before committing**. Sites never run it, and `tools/` is not in the plugin ZIP.

Run it when you changed:

1. **A file with a `.min` copy**, listed in `$files` in `tools/build.php`: the Virtual Assistant's `front.js`, `front-loader.js`, `front.css` and `front-tab.css`, and Login Guard's `recaptcha.js`.
2. **Any text of the plugin**: a `__()`, `_e()`, `esc_html__()`… created, changed or removed. The build rewrites `languages/dovetail-studio.pot` and the Virtual Assistant's `i18n-js.php`.
3. **A translation** (`languages/*.po`): the build compiles its `.mo`.

Then commit the generated files with your change. Not needed for PHP changes without text changes, admin CSS or documentation. When in doubt, run it: with nothing changed it rewrites the same files.

A forgotten build breaks nothing on a site: `Core\Loader::asset()` serves the readable file while its `.min` copy is older, and untranslated new texts show in English.

## Every change

1. Make the change, following the rules below.
2. `composer build` if one of the cases above applies.
3. `composer test` and `composer lint`: both pass.
4. Add tests for new behaviour in `tests/` (one file per module or feature, see `tests/lib/harness.php`).
5. Update the module's `README.md` and `USAGE.md` and add an entry under `## [Unreleased]` in [`CHANGELOG.md`](./CHANGELOG.md).

## Essential rules

### Modules

- One folder per module in `modules/<slug>/`, always with `module.php` (manifest), `README.md`, `USAGE.md` and `src/Module.php` (namespaced bootstrap with `boot()`). Focused classes go in `src/`; optional `assets/`, `views/`, `resources/`, `docs/`.
- Modules are independent: a module never calls another module's code. Code two modules need goes in `src/Core/` (`Http`, `Crypto`, `Captcha`, `Mail`, `Loader::asset()`…). Look there before writing a helper.
- Declare admin pages in `admin_screens` and cron hooks in `cron_hooks` in `module.php`, so the Studio styles the screens and clears the events.
- A module's files may not run anything when loaded: register hooks in `boot()` and do the work in the callbacks.

### PHP

- WordPress Coding Standards (WordPress-Extra), PHP 8.0 compatible. Class files are named after the class (`Guard.php`); other PHP files are kebab-case.
- Prefixes: `dovetail_studio_*` or the module prefix (`dovetail_login_guard_*`) for functions, hooks, options and tables; `DovetailStudio\<Module>` namespaces.
- English PHPDoc on every function, method and callback.
- Security on every entry point: capability checks, nonces for forms and `admin-post` actions, sanitized input, escaped output, prepared SQL. Secrets are stored with `Core\Crypto`; the visitor IP comes from `Core\Http::client_ip()`.

### Templates

- HTML lives only in `views/`: the module's `views/` or `src/Core/views/`, one kebab-case file per screen, tab or partial (`views/partials/…`). Classes and functions check permissions, handle the request and pass the prepared data to the template.
- Render with `Core\View::render( $module_dir, 'name', $args )` (or `View::capture()` for a string). The template reads only `$args`, documented with `@var array{...} $args`, and escapes all output.
- Short fragments built for another API (a `wp_die()` message, a JSON-LD script, a mail body) may stay in PHP.

### Front end

- Plain JavaScript files in the module's `assets/js/` with English JSDoc; no TypeScript (there is no build) and no JavaScript inline in PHP. Load scripts with `wp_enqueue_script()` and `Core\Loader::asset()`, only on the screens that need them, and add them to `$files` in `tools/build.php`.
- Admin screens use the shared design system in `assets/admin/dovetail-admin.css`; add module CSS only for what the shared classes cannot express. No `style` attributes or `<style>` tags in templates, and colours, shadows and font sizes only from its `--dovetail-*` tokens (add a new one there first). Font sizes are `rem` tokens; titles use the fluid `clamp()` tokens.
- For a confirmation before a link or button runs, add `data-dovetail-confirm="Message"`; `assets/admin/dovetail-admin.js` handles it on every Studio screen. Pass data to a script through `data-*` attributes, `wp_add_inline_script()` or `wp_localize_script()`, never a `<script>` in a template.
- Accessible markup: labels for every field, keyboard access, text alternatives for images and charts.

### Texts and data

- Everything in English, with the `dovetail-studio` text domain. No text is translated before `init`.
- No fallback or legacy-compatibility code: the plugin is treated as new.
- Data a module stores is listed in its README and removed by `uninstall.php` when `DOVETAIL_STUDIO_DELETE_DATA` is set.

## Release

1. Update the versions: plugin header and `DOVETAIL_STUDIO_VERSION` in `dovetail-studio.php`, `Stable tag` and bundled versions in `readme.txt`, each changed `module.php`, the README badges and module table.
2. Rename `## [Unreleased]` in `CHANGELOG.md` to the version and date, and add the entry to `readme.txt`.
3. `composer build`, `composer test`, `composer lint`.
4. Go through the manual checklist in [`test.md`](./test.md) on a real WordPress.
5. Build the ZIP (`wp dist-archive` or `git archive`); `.distignore` leaves out `tests/`, `tools/`, `test.md`, this file and the development configuration.

## AI assistants

`AGENTS.md` holds the rules AI coding assistants follow (generated from `.ai/`, plus the project section at the end of the file). Change the generated part only through `.ai/` and `node .ai/install.mjs`; keep the project section in line with this file.
