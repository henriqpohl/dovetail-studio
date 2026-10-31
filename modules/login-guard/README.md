# Login Guard

Namespace: `DovetailStudio\LoginGuard\Module`

Login Guard protects the WordPress login inside WordPress: brute force lockouts, an IP block list in WP Admin, two-factor authentication, reCAPTCHA v3 or Cloudflare Turnstile, email alerts and summaries, an activity log, password policies and hardening (user enumeration, XML-RPC, application passwords). It replaces Wordfence's Brute Force Protection, Blocking, Login Security (2FA, reCAPTCHA) and email alerts on servers where the firewall and malware scanning already run at server level (Imunify360, the hosting WAF). It is not a web application firewall and does not scan files.

## Structure

| File | Role |
| --- | --- |
| `src/Module.php` | Constants, loading, cron events, WP-CLI command |
| `src/Settings.php` | Defaults, choices and per-tab sanitizing of `dovetail_login_guard_settings` |
| `src/Store.php` | The custom tables; blocked IP addresses and recent failures |
| `src/Guard.php` | Counting failures, lockouts, blocks, the block page, `dovetail_login_guard_signed_in` |
| `src/Hardening.php` | Login error messages, user enumeration, `admin` registration, application passwords, XML-RPC |
| `src/Passwords.php` | Strong passwords and the Have I Been Pwned check |
| `src/Activity.php` | Activity log: writing, listing, summary totals, retention |
| `src/Alerts.php` | Admin sign-in, block and lockout emails, hourly cap, summary every N days |
| `src/TwoFactor.php` | TOTP codes, recovery codes, trusted devices, the code screen, profile section, required roles |
| `src/QrCode.php` | QR code encoder (SVG) for the two-factor set-up, so the secret never leaves the server |
| `src/Captcha.php` | reCAPTCHA v3 / Turnstile on the login and registration forms, score histogram, email verification link |
| `src/Admin.php` | Dovetail Studio → Login Guard: Blocking, Activity, Settings, Two-Factor, Captcha and Alerts tabs |
| `src/Cli.php` | `wp dovetail login-guard list|block|unblock|clear|reset-2fa` |
| `assets/js/recaptcha.js` | reCAPTCHA v3 token for the login and registration forms |
| `views/admin-page.php`, `views/tab-*.php` | The admin screen and one template per tab |
| `views/two-factor-form.php`, `views/two-factor-profile.php` | The code screen and the profile section |
| `views/captcha-field.php`, `views/block-page.php` | The captcha field of the login forms and the block page message |
| `views/partials/` | Checkbox and select rows, the score histogram (SVG) |

Captcha tokens are verified by the shared `DovetailStudio\Core\Captcha` helper (also used by the Virtual Assistant). The admin screen uses the shared Studio design system and WordPress core's select-all checkbox; the only scripts the module adds load on the login and registration forms when the captcha is on: the provider's, and for reCAPTCHA v3 `assets/js/recaptcha.js`, which fetches and refreshes the token (minified by `tools/build.php`; `Core\Loader::asset()` serves the `.min.js`).

## Data

- Options: `dovetail_login_guard_settings` (settings; the captcha secret is encrypted with `Core\Crypto`), `dovetail_login_guard_db` (schema version), `dovetail_login_guard_site_blocks_until` (autoloaded; when the last site-wide block ends), `dovetail_login_guard_alert_quota` (alerts sent this hour), `dovetail_login_guard_summary_last` (last summary), and `dovetail_login_guard_captcha_scores` (score histogram).
- Table `{prefix}dovetail_login_guard_blocks`: one row per IP address. `kind` is `lockout` (no sign-in or password reset) or `block` (no access to the site); `source` is `manual`, `login`, `lostpw`, `invalid_username`, `username` or `blank_ua`; `expires_at` 0 means permanent.
- Table `{prefix}dovetail_login_guard_failures`: failed sign-ins and password reset requests, kept for the counting period.
- Table `{prefix}dovetail_login_guard_activity`: the activity log (`login_success`, `login_failed`, `logout`, `lockout`, `block`, `twofa_success`, `twofa_failed`, `captcha_failed`, `captcha_test`), kept for the retention period.
- User meta `dovetail_lg_*`: two-factor secret (encrypted), recovery code hashes, last accepted time step, trusted devices (hashed tokens), pending sign-in and captcha verification tokens (hashed), the start of a required set-up, and each administrator's last 20 IP addresses (for "alert only from a new IP").
- Cron hooks: `dovetail_login_guard_cleanup` (daily: expired blocks, old failures, old activity) and `dovetail_login_guard_summary` (daily check; sends the summary every N days).

Uninstalling Dovetail Studio with `DOVETAIL_STUDIO_DELETE_DATA` drops the three tables, the options and the user meta.

## How a sign-in is checked

1. `init` (priority 1): a POST without User-Agent and Referer blocks the IP (when enabled); a blocked IP gets the block page (HTTP 403).
2. `login_init`: a locked out or blocked IP gets the block page instead of wp-login.php.
3. `authenticate`: Have I Been Pwned (98), generic error messages (99), captcha (21, on wp-login.php and WooCommerce forms only), XML-RPC refused for two-factor users (50), locked out IPs refused (100).
4. `wp_login_failed`: blocked usernames, unknown usernames and the failure limit (Guard); the activity log.
5. `wp_login` (priority 5): users with two-factor on lose the new session and go to `wp-login.php?action=dovetail_2fa`; a wrong code is a failed sign-in. Otherwise (priority 20) `dovetail_login_guard_signed_in` fires, which clears failures, logs the sign-in and sends the admin alert. After a correct code TwoFactor fires it itself.
6. `lostpassword_post`: locked out IPs are refused; every request is counted.

WP-CLI, WP-Cron and allowlisted addresses are never checked. The visitor IP comes from `Core\Http::client_ip()`, so forwarding headers are only believed from Cloudflare or `DOVETAIL_STUDIO_TRUSTED_PROXIES`.

## Hooks

- Filter `dovetail_login_guard_allowlist` (`string[]` addresses or CIDR ranges): addresses never locked out, blocked or shown the captcha.
- Action `dovetail_login_guard_blocked` (`$ip`, `$kind`, `$source`, `$reason`): after an IP is locked out or blocked.
- Action `dovetail_login_guard_signed_in` (`WP_User $user`): once a user is fully signed in, after the two-factor step when there is one.

## Possible Errors and How to Fix Them

### You locked yourself out
Run `wp dovetail login-guard unblock <your IP>`, or add `define( 'DOVETAIL_LOGIN_GUARD_DISABLED', true );` to `wp-config.php`, sign in, unblock your IP in Dovetail Studio → Login Guard, add it to the allowlist and remove the line.

### A user lost the phone with the authenticator app
They can sign in with a recovery code. Without one, an administrator ticks "Reset two-factor authentication for this user" on the user's profile, or runs `wp dovetail login-guard reset-2fa <user>`.

### Two-factor codes are always refused
The phone's clock is off by more than 30 seconds. Turn on automatic date and time on the phone.

### The captcha refuses everyone
Check that the site key and secret belong to the chosen provider and that the site's domain is listed for the keys. Turn on test mode, watch the Activity tab, then lower the reCAPTCHA threshold if real users score low.

### Alerts or summaries do not arrive
WordPress's own `wp_mail()` sends them: check the site's mail setup (SMTP plugin, SPF/DKIM). The summary needs WP-Cron and the activity log.

### Every visitor gets the same IP, or blocks hit everyone
The site is behind a proxy or load balancer that is not trusted. Add its addresses to `DOVETAIL_STUDIO_TRUSTED_PROXIES` (Cloudflare is trusted automatically).

### A webhook or integration stopped working
It probably sends POST requests without User-Agent and Referer. Unblock its IP, add it to the allowlist or turn off "Block IPs who send POST requests with blank User-Agent and Referer".

### Users are locked out together with Wordfence
Do not run Wordfence and Login Guard at the same time. Before removing Wordfence, remove its firewall's extended protection (Firewall → Remove Extended Protection), copy its allowlisted IPs, and ask users of Wordfence two-factor to set it up again in their profile.
