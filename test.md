# Dovetail Studio — Release test checklist

Manual tests to run on a real WordPress before a release goes to a client site. The automated suites (`php tests/run.php`, `node tests/browser/run.js`) run against a WordPress simulator; this list covers what only a real WordPress, real plugins and a real server can confirm.

Tick each box. Note the WordPress, PHP, Yoast SEO, WooCommerce and Contact Form 7 versions used at the end of the file.

## 1. Environment

- [ ] WordPress 7.0, PHP 8.0 and the newest PHP the host offers (8.3 or later).
- [ ] In `wp-config.php`: `define( 'WP_DEBUG', true );`, `define( 'WP_DEBUG_LOG', true );`, `define( 'WP_DEBUG_DISPLAY', false );`.
- [ ] Plugins installed: Yoast SEO, WooCommerce, Contact Form 7, Rank Math (inactive until section 4.4).
- [ ] An email catcher (Mailpit in Local, MailHog) or an SMTP plugin pointing to a test inbox.
- [ ] `php tests/run.php` and `node tests/browser/run.js` pass on the release branch.
- [ ] `wp-content/debug.log` is empty before starting. After every section, check it again: **no notice, warning or error from `dovetail-studio`** (this includes "_load_textdomain_just_in_time was called incorrectly").

## 2. Studio (Core)

### 2.1 Activation and screens
- [ ] Activate the plugin: no error, the **Dovetail Studio** menu appears.
- [ ] Dashboard and Modules screens open; each enabled module has an **Open** button that opens its screen.
- [ ] On every Studio and module screen, WordPress and other plugins' notices (updates, WooCommerce, Yoast) are hidden; they still show on Dashboard → Home and Plugins.
- [ ] Saving a module setting shows the "saved" notice; an invalid value shows the error notice.
- [ ] Plugins screen: the **Studio** link opens the dashboard.

### 2.2 Modules
- [ ] Disable a module and save: its menu disappears, its front-end output stops.
- [ ] Re-enable it: everything comes back with the settings kept.
- [ ] Disable the Virtual Assistant: in Tools → Site Health → Info (or WP Crontrol), the `dt_va_*` events are gone.

### 2.3 Module upload
- [ ] Upload a module ZIP (any valid module, e.g. a copy of a bundled one with a new slug): message "Module installed. It is disabled…", the module is listed as **Uploaded** and **disabled**.
- [ ] Enable it and save: it loads, its Open link works.
- [ ] Upload the same ZIP again: "Module updated successfully.", the enabled state is kept.
- [ ] Upload a ZIP without `module.php`: error, nothing installed, no `.upload-*` folder left in `wp-content/dovetail-modules/`.
- [ ] Remove the uploaded module while enabled: refused. Disable, save, remove: the folder is deleted.
- [ ] Bundled modules have no **Remove** button.
- [ ] As an Editor (or with `DISALLOW_FILE_MODS`), the upload card is hidden and the upload URL is refused.
- [ ] Change `dovetail_message` in the redirect URL to any text: the text never appears on the screen.

### 2.4 Deactivation and uninstall
- [ ] Deactivate the plugin: the `dt_va_*` cron events are removed.
- [ ] Delete the plugin without `DOVETAIL_STUDIO_DELETE_DATA`: settings and enquiries are kept.
- [ ] Reinstall, add `define( 'DOVETAIL_STUDIO_DELETE_DATA', true );`, delete: the `dovetail_*`, `dt_va_*` and `dovetail_rl_*` options and the enquiries are gone.
- [ ] (Optional) On a multisite network, network-activate, then delete: every site is cleaned.

## 3. Newsletter

- [ ] Without Contact Form 7: the settings screen warns, the theme helper prints "Newsletter is not configured…".
- [ ] Create a CF7 form with exactly one email field, paste its shortcode, print it in the theme with `<?php echo dovetail_newsletter_render(); ?>`.
- [ ] **CF7 mode:** the normal CF7 email arrives; nothing goes to Mailchimp.
- [ ] **Mailchimp mode** (test audience, double opt-in on): new email → "Please check your email…", contact *Pending* in Mailchimp, no CF7 email.
- [ ] Same email again → "Please check your email…"; a subscribed email → "already subscribed"; an unsubscribed email → can subscribe again.
- [ ] **Merge fields** `FNAME=your-name`: the name appears on the Mailchimp contact. **Tags**: the tags are on the contact.
- [ ] **Both mode:** CF7 email and Mailchimp subscription. With a wrong API key: the CF7 email **still arrives** and the visitor sees that the subscription failed.
- [ ] Six submissions in a row from the same IP: the sixth is refused ("Too many attempts").
- [ ] The API key is not readable in `wp_options` (`dovetail_newsletter_settings` holds `dovetail_enc_v1:…`).

## 4. Schema SEO

For every check, view the page source and copy the `application/ld+json` block. Paste it in the [Rich Results Test](https://search.google.com/test/rich-results) (**Code** tab) and in the [Schema Markup Validator](https://validator.schema.org/). **Expected: no errors**; warnings for optional fields are fine.

### 4.1 Without Yoast (Organization)
- [ ] Nothing configured: no business entity printed (WebSite and WebPage only).
- [ ] Type LocalBusiness with a name but no address: printed as **Organization**, and the settings screen says what is missing.
- [ ] Name, address, phone, hours, logo filled in: one graph with LocalBusiness (or the chosen subtype), WebSite (`publisher` → business), WebPage; on a post, Article + author Person.
- [ ] No `SearchAction` on the WebSite.
- [ ] **Homepage only** scope: the business entity only on the homepage.
- [ ] Breadcrumbs: on a page, a post and a category, `dovetail_studio_get_breadcrumbs()` in the theme and the BreadcrumbList show the same trail; every item but the last has a URL.

### 4.2 Without Yoast (Person)
- [ ] "This site represents: A person", person name, job title, photo, profiles: a Person (`/#/schema/person/site`) publishes the WebSite and the posts.
- [ ] No business name: no Organization in the graph.
- [ ] With a business name: Organization with `founder` → Person, Person with `worksFor` → Organization.

### 4.3 With Yoast SEO
- [ ] Yoast in **Organization** mode: one Organization (Yoast's), enriched with the module data (type, address, hours…), no second business entity, no Dovetail graph printed.
- [ ] Yoast in **Person** mode, no business name in the module: Yoast's Person unchanged, no Organization added.
- [ ] Yoast in **Person** mode with a business: Yoast's Person keeps its name and type (only `worksFor` added); a separate business entity with its address and hours and `founder` → Person.
- [ ] Yoast's company name empty and module empty: no Organization without a name in the graph.
- [ ] Breadcrumbs follow the Yoast breadcrumb settings.
- [ ] JSON-LD Preview on the settings screen loads the Yoast graph.

### 4.4 With another SEO plugin
- [ ] Deactivate Yoast, activate Rank Math: the settings screen says Rank Math publishes the schema; the page source has **no** `dovetail-schema-graph` script, only Rank Math's.
- [ ] Deactivate Rank Math again: the Dovetail graph is back.

## 5. Schema SEO WooCommerce

Run with and without Yoast SEO.

- [ ] Without WooCommerce: the settings screen warns, nothing breaks.
- [ ] Simple product: one Product in the site graph (not a separate WooCommerce script), WebPage type `ItemPage`, offer `seller` → Organization.
- [ ] Variable product (sizes or colours): `ProductGroup` with `productGroupID`, `variesBy` and one Product per variation in `hasVariant`, each with its own price and stock. **Rich Results Test: product snippet / merchant listing valid.**
- [ ] Brand, GTIN and MPN mappings: the values appear on the Product and the variants.
- [ ] Return policy on, country blank: `applicableCountry` and `returnPolicyCountry` = the store country, on the offers and on the Organization.
- [ ] Shipping details on (rate 0, transit 2–4 days): `shippingDetails` on every offer.
- [ ] Cart, checkout and My Account: `noindex, follow` in the robots meta.
- [ ] Shop with `?orderby=price`: the canonical has no query string (without Yoast/Rank Math).
- [ ] A hidden product (catalog and search visibility off) is not in `wp-sitemap.xml` (or the Yoast sitemap).
- [ ] With Rank Math active: WooCommerce's own product JSON-LD stays (no Dovetail graph).

## 6. Virtual Assistant

Settings under **VA Enquiries → Settings**. Test the front end **logged out, in a private window**: administrators skip the visitor token and the rate limits.

### 6.1 Setup and loading
- [ ] Before the public phone and email are set: the assistant is hidden on the site; a notice on the Studio screens, the Dashboard and the Plugins screen names the missing required fields and links to them; the fields are marked with *.
- [ ] Fill in one field and save: the notice names only the other one. Fill in both: the notice is gone on the same page load.
- [ ] Set phone, email, company name, opening hours: the tab appears on every page.
- [ ] Network tab of the browser on page load: only `front-tab(.min).css` and `front-loader(.min).js`; **no** `front.js`, `front.css` or `/dt-va/v1/config` until the tab is hovered or clicked (or about 3 s after load).
- [ ] The tab and the panel look right on desktop and on a phone (390 px wide); the widget uses the theme font; the page does not get a horizontal scroll and sticky headers still stick.
- [ ] Theme colours saved under Theme apply without a flash of the default colours.

### 6.2 Conversation
- [ ] With an OpenAI or Anthropic key: a question gets an answer from the knowledge base; an off-topic question is declined.
- [ ] Without a key, or with a wrong one: offline FAQ answers; no error shown to the visitor; "Save and test providers" shows the diagnostics.
- [ ] Follow a door to the form, send an enquiry **with an attachment**: confirmation in the panel with a reference `DT-YYMM-XXXXXX`.
- [ ] Ask for a call back without an email: accepted, no visitor email sent.
- [ ] Add a door with Service `web` before its step exists: the hint under Service says to add `web1`; after adding the step it says it exists.
- [ ] Save a new door and reload the site (logged out, private window): the new door shows at once, without waiting 5 minutes.
- [ ] Build the website flow from `modules/virtual-assistant/USAGE.md` (doors + steps), go through it and send: the team's email lists Site type, Pages count and Branding, the Portfolio cards show after `web3`, and the enquiry type is "A new website".
- [ ] "Talk to a person", WhatsApp and booking links open correctly.
- [ ] Reload mid-conversation: "Welcome back… Pick up where you left off?" works; the browser storage never holds the name, email or phone.

### 6.3 Emails
- [ ] Studio email: arrives at the enquiry email, **From** an address on the site's domain (default `wordpress@` the domain), **Reply-To** the visitor; contains the brief, "At a glance", the attachment and the calendar invite for call backs.
- [ ] Visitor email: From the site's domain, Reply-To the enquiry email, contains only the reference (no text the visitor typed).
- [ ] With a Sender email saved, that address is the From.
- [ ] "Mark as answered" link in the email works when logged in (login first when not).

### 6.4 Opening hours and closed dates
- [ ] Outside opening hours: the header says when the office reopens; the confirmation email says the team is away.
- [ ] Add today's date as a closed date: the assistant says closed even within hours, and names the next open day; a closure longer than a week names the date.
- [ ] Past closed dates disappear when saving; an invalid line ("next monday") is refused with a message.

### 6.5 Enquiries and personal data
- [ ] VA Enquiries list: columns Ref, Contact, Call back, Answered; the attachment downloads from the enquiry screen.
- [ ] As an Editor: VA Enquiries is not visible (admin and `/wp-json/wp/v2/dt-enquiries`).
- [ ] Direct URL of an attachment in `wp-content/uploads/dt-va/…`: **403** (Apache). **On nginx** add `location ^~ /wp-content/uploads/dt-va/ { deny all; }` and check again.
- [ ] Tools → Export Personal Data for the visitor email: the export contains the enquiry.
- [ ] Tools → Erase Personal Data: the enquiry and its attachment file are deleted.
- [ ] Delete an enquiry from the list (empty the trash): its attachment file is deleted.
- [ ] Settings → Privacy: the suggested AI Assistant text is listed, with the retention period.
- [ ] Retention set to 1 month and an enquiry dated older (edit `post_date_gmt`): after `dt_va_daily` runs (WP Crontrol → Run now) it is deleted.

### 6.6 Limits and spam
- [ ] More than 30 questions in an hour from one IP: the next ones get the built-in answer (rate limit), no error shown.
- [ ] More than 8 enquiries in an hour from one IP: refused.
- [ ] With Turnstile keys set (`DT_VA_TURNSTILE_SITE` / `DT_VA_TURNSTILE_SECRET`): the challenge shows on the form and an enquiry without it is refused.
- [ ] Behind Cloudflare: the enquiry's IP is the visitor's, not a Cloudflare address.

### 6.7 Scheduled emails
- [ ] Run `dt_va_selfcheck`, `dt_va_daily` and `dt_va_weekly` (WP Crontrol → Run now): the self-check email only when the status changes, the reminder for enquiries unanswered for two working days, the weekly digest with the funnel.

## 7. Login Guard

### 7.1 Brute force and blocking

- [ ] Your IP is on the allowlist (Settings) before you start, or `wp dovetail login-guard unblock <ip>` is at hand.
- [ ] From another IP (phone on mobile data): 3 wrong passwords lock it out; wp-login.php shows the 403 page; the Blocking tab lists it as Locked out with the reason; Unblock lets it sign in again.
- [ ] Signing in as `admin` (no such account) blocks the IP from the whole site; with a real `admin` account it only counts as a failure.
- [ ] 2 "Lost your password?" requests lock the IP out.
- [ ] Manual block, Make permanent and Export all IPs (CSV opens in a spreadsheet; a username starting with `=` is not run as a formula).
- [ ] `/?author=1` redirects to the home page for visitors; `/wp-json/wp/v2/users` answers 401 to visitors; the block editor still lists authors.
- [ ] `xmlrpc.php` with `wp.getUsersBlogs` is refused; pingbacks work unless XML-RPC is off completely.

### 7.2 Two-factor authentication

- [ ] Profile: scan the QR code with Google Authenticator and 1Password; the code turns it on and 10 recovery codes are shown once.
- [ ] Sign in: the code screen appears after the password; a wrong code is refused and counts as a failure; a right code and a recovery code sign in; "Remember this device" skips the code next time.
- [ ] WooCommerce My Account login goes through the code screen too.
- [ ] Required role: the notice shows during the grace period; afterwards WP Admin only opens the profile.
- [ ] An administrator resets another user's two-factor.

### 7.3 Captcha

- [ ] reCAPTCHA v3 with real keys in test mode: sign-ins pass, scores appear in the chart and the Activity tab.
- [ ] Test mode off, threshold 1.0: a sign-in with the right password sends the email link, which signs in once.
- [ ] Turnstile with real keys on wp-login.php, registration and WooCommerce forms; a sign-in without the widget token is refused.
- [ ] Wrong secret key: the provider error is logged; with the network to the provider blocked, sign-ins still go through.

### 7.4 Alerts and activity

- [ ] Admin sign-in email arrives in the Wordfence format (IP, hostname, browser; country behind Cloudflare).
- [ ] Block and lockout emails arrive; with "Maximum alerts per hour" at 1 only the first arrives.
- [ ] Summary: set "How often" to every day and run `wp cron event run dovetail_login_guard_summary` after changing `dovetail_login_guard_summary_last` to 2 days ago; the email lists the totals.
- [ ] Each email switched off sends nothing.

## 8. Vulnerability Watch

- [ ] Install an old plugin version with a published vulnerability (for example an outdated Contact Form 7 from the wordpress.org archive) on a test site; **Check now** lists it with severity, fixed version and CVE link, and the "Vulnerabilities Found" email arrives once.
- [ ] Update the plugin; the next Check now removes the row and sends nothing.
- [ ] Minimum severity "High" hides medium and low entries; "Include inactive" off hides inactive plugins.
- [ ] With outbound requests to `www.wpvulnerability.net` blocked, the status names the components that could not be checked.
- [ ] Site change emails arrive with the subject `[Vulnerability Watch Alert]` and "Done by" with the username and IP address.
- [ ] Creating an administrator, promoting a subscriber, activating/deleting a plugin, switching the theme and changing the admin email each send an alert and appear under Site Changes; updating two plugins at once sends one "Plugins Updated" email listing both, and nothing once "Alert me when plugins or themes are updated" is off.
- [ ] `wp cron event run dovetail_vulnerability_watch_scan`: the Integrity card says all files match; after adding `wp-includes/test.php` the next run emails it once (delete the file afterwards).
- [ ] With `WP_DEBUG_LOG` on and `wp-content/debug.log` reachable, the same cron run sends "Sensitive Files Exposed"; once the server blocks it or the file is gone, the Integrity card says no file can be downloaded.
- [ ] Upload a plugin folder by FTP and delete another plugin's folder: the next `wp cron event run dovetail_vulnerability_watch_scan` sends one "Plugins or Themes Changed Outside WordPress" email; opening Plugins in WP Admin afterwards sends "Plugins Switched Outside WordPress" for the deleted (silently deactivated) plugin. Installing a plugin in WP Admin is not reported by the next daily check.
- [ ] Login Guard's Activity tab and Alerts tab show sign-in events only (no site changes, core files or sensitive files).

## 9. Caching and performance

- [ ] With the page cache plugin used in production (WP Rocket, LiteSpeed Cache, W3 Total Cache…) on: the chat opens, answers and sends from a cached page; JS/CSS optimisation does not break the loader (exclude `front-loader` if it does).
- [ ] PageSpeed Insights / Lighthouse on the homepage, with and without the module enabled: no new render-blocking resource beyond `front-tab.css`.

## 10. Production server

- [ ] SPF, DKIM and DMARC set for the site's domain (or an SMTP plugin with an authenticated mailbox). Send a test enquiry to a Gmail and an Outlook address: both arrive in the inbox, not in spam.
- [ ] Nginx: the `uploads/dt-va` deny rule is in place.
- [ ] Turnstile keys set.
- [ ] `DOVETAIL_STUDIO_TRUSTED_PROXIES` set if the site is behind a load balancer or proxy other than Cloudflare.

## 11. Code checks

- [ ] `composer install`, then `composer lint` (PHPCS with WordPress-Extra and PHPCompatibilityWP for PHP 8.0+, rules in `phpcs.xml.dist`): no errors.
- [ ] `composer format` leaves nothing to change (the code was already formatted).
- [ ] PHPStan with WordPress stubs (`szepeviktor/phpstan-wordpress`, WooCommerce stubs) at level 5 or higher: no errors.

## 12. Release

- [ ] `composer build` (`php tools/build.php`) run; `.pot`, `i18n-js.php` and the `.min` files committed.
- [ ] Versions match: plugin header, `DOVETAIL_STUDIO_VERSION`, `readme.txt` (Stable tag, bundled versions), each `module.php`, README badges, CHANGELOG.
- [ ] "Tested up to" in `readme.txt` is the WordPress version used in section 1.
- [ ] Build the ZIP (`git archive` or `wp dist-archive`): it contains no `tests/`, `tools/`, `test.md`, `CONTRIBUTING.md`, `AGENTS.md` or `.ai/`; install it on a clean site and repeat 2.1.

## Test run

| Date | Tester | WordPress | PHP | Yoast SEO | WooCommerce | CF7 | Result |
| --- | --- | --- | --- | --- | --- | --- | --- |
| | | | | | | | |
