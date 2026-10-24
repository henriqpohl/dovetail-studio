# Newsletter

Namespace: `DovetailStudio\Newsletter\Module`

The Newsletter module renders a configured Contact Form 7 newsletter form and supports CF7 email handling, Mailchimp subscription, or both. Mailchimp credentials are encrypted before database storage.

## Structure

`src/Module.php` defines module constants and loads `src/functions.php`. Admin JavaScript lives in `assets/js/admin.js`.

## Dependency

Contact Form 7 is required for all operating modes. The settings screen displays a warning when CF7 is not installed or active.

## Possible Errors and How to Fix Them

### Newsletter form does not render
Install/activate Contact Form 7 and verify the saved CF7 shortcode points to an existing form.

### Mailchimp subscription fails
Verify API key, data-center suffix, audience ID, network access, and Mailchimp permissions.
