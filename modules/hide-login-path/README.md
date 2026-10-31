# Hide Login Path

Namespace: `DovetailStudio\HideLoginPath\Module`

Hide Login Path moves the WordPress login to an address you choose (for example `https://example.com/my-door/`). `wp-login.php`, `wp-signup.php`, `wp-activate.php` and, for visitors who are signed out, `wp-admin` send the visitor to a redirect path instead (by default `/404/`, the theme's "not found" page). It replaces the WPS Hide Login plugin. Enabling the module in Dovetail Studio → Modules is the switch: it acts as soon as a login path is saved. Disabling the module brings `wp-login.php` back.

It hides the login from bots that scan for it; it does not replace brute force protection. Use it with Login Guard, which keeps counting failures and showing the captcha on the new address.

## Structure

| File | Role |
| --- | --- |
| `src/Module.php` | Constants, loading |
| `src/Settings.php` | Defaults, path validation and sanitizing of `dovetail_hide_login_path_settings` |
| `src/Router.php` | Request detection, redirects, loading `wp-login.php` at the login path, URL rewriting |
| `src/Admin.php` | Dovetail Studio → Hide Login Path: status and settings |

No scripts or styles: the admin screen uses the shared Studio design system.

## Data

- Option `dovetail_hide_login_path_settings`: `slug` (the login path; empty by default, which leaves the login at `wp-login.php`) and `redirect_slug` (default `404`; empty means the home page).

Uninstalling Dovetail Studio with `DOVETAIL_STUDIO_DELETE_DATA` removes the option.

## How a request is handled

1. `plugins_loaded` (priority 9999): WordPress's `/login`, `/admin` and `/dashboard` shortcuts (`wp_redirect_admin_locations`) are removed, because they redirect to the login URL. A request whose entry script is `wp-login.php` (also `wp-signup.php` and `wp-activate.php` on single sites) is marked hidden. A request for the login path (`/my-door/`, or `/?my-door` with plain permalinks) is marked as the login and `$pagenow` becomes `wp-login.php`, so code that looks for the login screen (Login Guard's captcha, other security plugins) still recognises it.
2. `wp_loaded`: hidden requests and signed-out `wp-admin` requests (except `admin-ajax.php` and `admin-post.php`) are redirected (302, no cache) to the redirect path. `wp-login.php?action=postpass`, the form of password-protected posts, still works. The login path runs `wp-login.php` and stops; a signed-in user who opens it without parameters goes to the dashboard.
3. `site_url`, `network_site_url` and `wp_redirect`: every URL whose path ends in `wp-login.php` is moved to the login path, keeping its query string exactly as it was. This covers `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()`, `wp_registration_url()`, the forms inside `wp-login.php` and the links in WordPress's emails (password reset, new user, privacy requests).

Nothing changes for WP-CLI. Multisite: only `wp-login.php` is hidden; the network's sign-up and activation pages keep working.

## Settings validation

- The path is reduced to lowercase letters, numbers and hyphens (`sanitize_title_with_dashes()`).
- Refused: paths starting with `wp-`, words bots try or WordPress answers (`admin`, `login`, `dashboard`, `feed`…), WordPress's public query variables, and the address of an existing public page or post. A refused path keeps the saved one.
- The redirect path cannot be the login path.
- An empty path leaves the login at `wp-login.php`.

## Possible Errors and How to Fix Them

### You forgot the login path
Add `define( 'DOVETAIL_HIDE_LOGIN_PATH_DISABLED', true );` to `wp-config.php`, sign in at `wp-login.php`, look up the path in Dovetail Studio → Hide Login Path and remove the line. With WP-CLI: `wp option get dovetail_hide_login_path_settings`.

### The login path shows a page or a 404 instead of the login form
A page cache serves that address. Exclude the login path from the cache (the server cache, Cloudflare or the cache plugin), as for `wp-login.php`.

### Another plugin's link still goes to wp-login.php
It builds the URL by hand instead of with `wp_login_url()` or `site_url()`. Visitors who follow it land on the redirect path; replace the link with the new address.

### Running it together with WPS Hide Login
Do not: both move the login. Turn WPS Hide Login off, enable Hide Login Path and save the same path.
