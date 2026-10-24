# Core Loader — Usage

The core loader is internal. Modules should not call loader private methods or depend on implementation details. A module only needs to expose a valid manifest and a `Module::boot()` entry point.

## Possible Errors and How to Fix Them

### Module state is unexpected after a rename
Module slugs are permanent identifiers within a release. There is no legacy-slug migration layer: rename a module only by changing its code and configuration deliberately before deployment.
