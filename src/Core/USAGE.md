# Core Loader — Usage

The core loader is internal. Modules should not call loader private methods or depend on implementation details. A module only needs to expose a valid manifest and a `Module::boot()` entry point.

## Rendering a template

Put the markup in the module's `views/` folder and render it from the class or function that prepared the data:

```php
use DovetailStudio\Core\View;

View::render(
	dirname( __DIR__ ),      // The module root, which holds views/.
	'admin-page',            // views/admin-page.php
	array(
		'settings' => Settings::get(),
		'version'  => DOVETAIL_EXAMPLE_VERSION,
	)
);

$html = View::capture( dirname( __DIR__ ), 'partials/badge', array( 'active' => true ) );
```

The template starts with `defined( 'ABSPATH' ) || exit;`, documents its arguments and reads them from `$args`:

```php
<?php
/**
 * @var array{settings: array<string,mixed>, version: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<h1><?php echo esc_html( 'v' . $args['version'] ); ?></h1>
```

## Possible Errors and How to Fix Them

### Module state is unexpected after a rename
Module slugs are permanent identifiers within a release. There is no legacy-slug migration layer: rename a module only by changing its code and configuration deliberately before deployment.
