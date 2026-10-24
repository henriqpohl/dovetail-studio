# Virtual Assistant

Namespace: `DovetailStudio\VirtualAssistant\Module`

The Virtual Assistant provides its REST endpoints, knowledge base, enquiry workflow, admin settings, front-end panel, provider integration and anti-spam/rate-limit logic.

## Structure

`src/Module.php` boots `src/runtime.php`. Service files are under `src/services/`, front-end/admin assets under `assets/`, markup under `views/`, and the bundled knowledge source under `resources/`. Extended technical notes remain under `docs/`.

The Virtual Assistant keeps its own external **VA Enquiries** admin menu. Inside Dovetail Studio there is only one **Virtual Assistant** shortcut that redirects to its settings page.

## Possible Errors and How to Fix Them

### Assistant UI does not appear
Confirm the module is enabled and that `views/front.html`, `assets/css/front.css`, and `assets/js/front.js` are readable. Clear page/cache optimiser caches.

### AI answers fail
Check the configured provider credentials and provider settings. Review REST responses from `/wp-json/dt-va/v1/ask`.
