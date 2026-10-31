# Hide Login Path — Usage

Enable **Hide Login Path** in **Dovetail Studio → Modules**, then open **Dovetail Studio → Hide Login Path**. The module is active once enabled: as soon as you save a login path, the login moves there. Until a path is saved, the login stays at `wp-login.php`. Disabling the module brings `wp-login.php` back.

## Status

The current login address, as a link, or "Not active yet" while no login path is saved. Bookmark the address before signing out.

## Settings

| Setting | Effect |
| --- | --- |
| Login path | The new address after the site URL, for example `my-door` for `https://example.com/my-door/`. Lowercase letters, numbers and hyphens. Paths starting with `wp-`, common words such as `login`, `admin` or `dashboard`, and the address of an existing page are refused. Once saved, the login opens only there; `wp-login.php`, `wp-signup.php`, `wp-activate.php` and `wp-admin` (for visitors who are signed out) send visitors to the redirect path. Empty: the login is at `wp-login.php`. |
| Redirect to | Where visitors of the hidden addresses go. The default, `404`, is an address with no page, so the theme shows its "not found" page. Empty sends them to the home page. It cannot be the login path. |

Links, forms and emails from WordPress (sign in, sign out, lost password, registration, password reset and new user emails) use the new address automatically. Signed-in users keep using `wp-admin` as before.

Moving from WPS Hide Login: turn WPS Hide Login off, enable Hide Login Path and save the same path here, so bookmarks keep working.

## wp-config.php

```php
// Bring wp-login.php back, for example after forgetting the login path. The settings are kept.
define( 'DOVETAIL_HIDE_LOGIN_PATH_DISABLED', true );
```

## Possible Errors and How to Fix Them

### "The login path … was not saved"
The path is a word bots try, starts with `wp-`, is used by WordPress, or belongs to an existing page or post. Choose another one.

### The login path shows a page or a 404
A page cache serves that address. Exclude it from the cache, as `wp-login.php` is.
