# Schema SEO — Usage

Enable the module in **Dovetail Studio → Modules**, then open **Dovetail Studio → Schema SEO**. Configure organization/local-business fields, save, and use the JSON-LD preview to inspect output.

## Possible Errors and How to Fix Them

### Preview cannot load
Verify the public homepage is reachable from WordPress itself and that security/cache layers are not blocking `wp_safe_remote_get()`.
