# Login Guard — Usage

Enable **Login Guard** in **Dovetail Studio → Modules**, then open **Dovetail Studio → Login Guard**. It works as soon as it is enabled, with the same values as a typical Wordfence setup: lock out after 3 failed sign-ins or 2 password reset requests counted over 4 hours, for 1 day. Two-factor authentication is optional for administrators, XML-RPC sign-ins are off, alerts and a daily summary go to the site's admin email, and the captcha waits for its keys.

The screen has six tabs: **Blocking**, **Activity**, **Settings**, **Two-Factor**, **Captcha** and **Alerts**. Each settings tab saves on its own.

Before removing Wordfence, open **Settings** and add your office or home IP address to the allowlist if it never changes.

## Blocking tab

- **Your IP address**: the address the site sees for you and whether it is allowlisted or blocked.
- **Block an IP address**: block an address from the whole site, permanently or for a period, with a reason.
- **Blocks**: every address locked out or blocked right now.
  - *Block type*: **Locked out** (cannot sign in or reset a password) or **Blocked** (cannot open the site); *Manual* or *Automatic*.
  - *Detail*: the IP address and the username tried.
  - *Rule added*, *Reason*, *Expiration* (or Permanent), *Block count* (requests refused since) and *Last attempt*.
  - Filter by IP, username or reason, and by automatic or manual blocks.
  - Select rows and click **Unblock** or **Make permanent**. Unblocking also forgets the address's failed attempts.
  - **Export all IPs** downloads the list as CSV.

Only blocks made by this site are listed. Addresses Imunify360 or the hosting firewall blocks on the server are managed in those tools.

## Settings tab

### Brute Force Protection

| Setting | Effect |
| --- | --- |
| Enable brute force protection | Turns every rule of this section on or off. Existing blocks stay in force. |
| Lock out after how many login failures | Failed sign-ins from one IP before it is locked out. |
| Lock out after how many forgot password attempts | Password reset requests from one IP before it is locked out. |
| Count failures over what time period | How long failures are remembered. |
| Amount of time a user is locked out | Duration of lockouts and of automatic blocks. |
| Immediately lock out invalid usernames | A sign-in with a username or email that has no account locks the IP out at once. |
| Immediately block the IP of users who try to sign in as these usernames | One per line (default `admin`, `administrator`, `test`, `demo`, `root`, `user`, `guest`, `wpadmin`, `webmaster`, `manager`). The IP is blocked from the whole site. A name that belongs to a real account is ignored and listed under the field. |

### Passwords

| Setting | Effect |
| --- | --- |
| Enforce strong passwords | Off, admins and publishers (anyone who can publish posts), or all users. Strong means at least 12 characters with three of lowercase, uppercase, numbers and symbols, without the username or email. Checked on the profile screen, for new users and on password reset. |
| Prevent the use of passwords leaked in data breaches | Off, admins only, or all users. The password is checked against Have I Been Pwned when it is set and at sign-in; only the first 5 characters of its SHA-1 hash are sent. A user whose current password is leaked must reset it by email before signing in. If the service cannot be reached the password is accepted. |

### Additional Options

| Setting | Effect |
| --- | --- |
| Disable XML-RPC authentication | On by default. Refuses every XML-RPC method that needs a sign-in, so bots cannot try many passwords in one request. Pingbacks keep working. Turn it off for the WordPress mobile app or Jetpack. |
| Disable XML-RPC completely | Off by default. Also switches off pingbacks and the X-Pingback header. |
| Don't let WordPress reveal valid users in login errors | The same message for an unknown username and a wrong password. |
| Prevent users registering 'admin' username if it doesn't exist | `admin` cannot be chosen for a new account. |
| Prevent discovery of usernames | `/?author=N` redirects visitors to the home page, `/wp/v2/users` answers 401 to visitors (signed-in users keep it for the editor), oEmbed responses lose the author, the users sitemap is removed. |
| Disable WordPress application passwords | Turns off application passwords for the REST API and XML-RPC. |
| Block IPs who send POST requests with blank User-Agent and Referer | Blocks the IP from the whole site. Requests from the server itself are never blocked. Turn it off if an external service posts to the site without these headers. |
| Custom text shown on block pages | Plain text shown on the 403 page instead of the default message. |

### Allowlist

One IP address or CIDR range per line (`203.0.113.10`, `198.51.100.0/24`, `2001:db8::/32`). Allowlisted addresses are never locked out or blocked. Code can add more with the `dovetail_login_guard_allowlist` filter.

## Activity tab

Every sign-in, failed sign-in, sign-out, lockout, block, two-factor result and captcha failure (or test-mode result), with date, username, IP address, detail and browser. Filter by event or by IP, username or detail. Entries are kept for the period set in the Alerts tab (default 90 days).

## Two-Factor tab

| Setting | Effect |
| --- | --- |
| One row per role | Off, Optional or Required. Default: Administrator optional, other roles off. |
| Grace period for required roles | Days a user of a required role has to set it up (default 7). Until then a notice is shown; afterwards WP Admin only opens their profile. |
| Allow remembering a device for | Days a browser can skip the code after the user ticks "Remember this device" (default 30; Off asks every time). |

Each user sets it up in **Users → Profile → Two-factor authentication**: scan the QR code with an authenticator app (or type the key), enter the 6-digit code and update the profile. Ten recovery codes are shown once; each signs in once. In the profile, users can create new recovery codes or turn two-factor off (unless their role requires it); administrators can reset it for another user. Accounts with two-factor on cannot sign in through XML-RPC.

## Captcha tab

| Setting | Effect |
| --- | --- |
| Enable captcha on the login and user registration pages | wp-login.php sign-in and registration, and the WooCommerce My Account forms. Stays off until both keys are saved. |
| Provider | Google reCAPTCHA v3 (invisible, gives a score) or Cloudflare Turnstile (usually invisible, passes or fails). |
| Site key / Secret key | From the reCAPTCHA admin console or the Cloudflare dashboard. The secret is stored encrypted; a blank field keeps it; changing the provider clears it. `DOVETAIL_LOGIN_GUARD_CAPTCHA_SECRET` in wp-config.php overrides it. |
| reCAPTCHA human/bot threshold score | reCAPTCHA only (default 0.5). Lower scores are treated as bots. The chart shows how many requests got each score; **Reset score statistics** clears it. |
| Email a sign-in link when the score is too low | With the right password and a low score, the user gets an email with a link that finishes the sign-in once, within 15 minutes (at most 3 emails per hour). Without it, the sign-in is refused. Registrations with a low score are always refused. |
| Run captcha in test mode | Scores and records requests (Activity tab, chart) without refusing anyone; use it to choose the threshold. |

If Google or Cloudflare cannot be reached, sign-ins go through (brute force protection still applies). Allowlisted IPs never see the captcha.

## Alerts tab

| Setting | Effect |
| --- | --- |
| Send alerts to | One email per line; empty sends to the site's administration email. |
| Alert me when someone with administrator access signs in | On by default. "Which administrator sign-ins": every sign-in, or only from an IP address that administrator has not used before. |
| Alert me when an IP address is blocked | On by default; blocks made by rules (manual blocks send nothing). |
| Alert me when someone is locked out from login | On by default. |
| Maximum alerts per hour | Default 10; alerts over the cap are dropped (the block list and activity log still have them). |
| Email a summary / How often | On by default, every 1, 2, 3, 7, 14 or 30 days: totals, active blocks and the most active IP addresses. |
| Skip the summary when nothing happened | On by default. |
| Record sign-in activity / Keep entries for | On by default, 90 days. Needed for the Activity tab and the summary. |

Alerts about site changes (new administrators, plugins, themes, settings, core and sensitive files) are in the Vulnerability Watch module.

Alert emails follow Wordfence's format: subject `[Login Guard Alert] example.com Admin Login` (or `IP Blocked`, `User Locked Out`, `Summary`), then the site, time, username, IP address, hostname, country (only behind Cloudflare, from its `CF-IPCountry` header) and browser. They are sent after the page has been delivered, so they never slow a sign-in down.

## WP-CLI

```bash
wp dovetail login-guard list [--format=table|csv|json|yaml]
wp dovetail login-guard unblock 203.0.113.10 [198.51.100.7 ...]
wp dovetail login-guard block 203.0.113.10 [--reason="Spam"] [--duration=86400]
wp dovetail login-guard clear [--yes]
wp dovetail login-guard reset-2fa <user-id|username|email>
```

## wp-config.php

```php
// Switch off every lockout and block (for example after locking yourself out). Hardening and password rules keep working.
define( 'DOVETAIL_LOGIN_GUARD_DISABLED', true );
```

## Possible Errors and How to Fix Them

### "Sign-in has been temporarily limited" for yourself
Your IP is locked out. Unblock it from another connection, with WP-CLI, or with `DOVETAIL_LOGIN_GUARD_DISABLED`, then add it to the allowlist.

### The leaked password check never refuses anything
The server cannot reach `api.pwnedpasswords.com` (outbound HTTPS blocked by the host). The check fails open by design; allow the domain in the server firewall.
