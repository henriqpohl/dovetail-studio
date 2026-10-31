# Virtual Assistant

Namespace: `DovetailStudio\VirtualAssistant\Module`

The Virtual Assistant provides its REST endpoints, knowledge base, enquiry workflow, admin settings, front-end panel, provider integration and anti-spam/rate-limit logic.

## Structure

`src/Module.php` boots `src/runtime.php`. Service files are under `src/services/`, front-end/admin assets under `assets/`, markup under `views/` (`front.html` for the widget; the settings page, one template per tab, the company notes page and partials for the admin screens), and the bundled knowledge source under `resources/`. Extended technical notes remain under `docs/`.

The Virtual Assistant keeps its own external **VA Enquiries** admin menu. Inside Dovetail Studio there is only one **Virtual Assistant** shortcut that redirects to its settings page.

## Possible Errors and How to Fix Them

### Assistant UI does not appear
The assistant stays hidden until the required public fields — **Phone — display** and **Public email** — are set under **VA Enquiries → Settings → Company**. Until then a notice on the Studio screens, the Dashboard and the Plugins screen names the fields still missing. Then confirm the module is enabled and that `views/front.html`, `assets/css/front-tab.css`, `assets/css/front.css` and `assets/js/front.js` are readable. The loader settings (`window.DT_VA_LOADER`) are printed with `wp_add_inline_script()` just before `assets/js/front-loader.js` and marked `data-no-optimize`, `data-no-defer` and `data-cfasync="false"`; if an optimiser still delays them, exclude them from its JavaScript optimisation. Clear page/cache optimiser caches.

### AI answers fail
Check the configured provider credentials and provider settings. Review REST responses from `/wp-json/dt-va/v1/ask`.

## Security and configuration constants (wp-config.php)

| Constant | Default | Purpose |
| --- | --- | --- |
| `DT_VA_ASK_PER_HOUR` / `DT_VA_SUBMIT_PER_HOUR` | 30 / 8 | Requests per visitor IP per hour. |
| `DT_VA_TOKEN_PER_HOUR` | 60 | Visitor tokens per IP per hour (one per visit). Raise it for clients whose visitors share one office IP. |
| `DT_VA_ASK_DEADLINE` | 15 | Seconds one question may spend across all AI providers before the built-in answer is used. |
| `DT_VA_ASK_GLOBAL_PER_HOUR` / `DT_VA_SUBMIT_GLOBAL_PER_HOUR` | 300 / 60 | Site-wide hourly caps: the ceiling on AI spend and on outgoing enquiry mail. `0` disables. |
| `DOVETAIL_STUDIO_TRUSTED_PROXIES` | — | Your own reverse proxies (IPs/CIDR) whose `X-Forwarded-For` / `X-Real-IP` is believed. Behind Cloudflare only `CF-Connecting-IP` is read, without configuration. |
| `DT_VA_TURNSTILE_SITE` / `DT_VA_TURNSTILE_SECRET` | blank | Cloudflare Turnstile on the enquiry form. Recommended on production. |
| `DT_VA_FROM` | `wordpress@` the site domain | Address every email is sent from (Settings → Company → Sender email). |
| `DT_VA_RETENTION_MONTHS` | 24 | Months an enquiry is kept before it is deleted with its attachment. `0` keeps them. |

`/ask`, `/submit` and `/event` require a token from `GET /wp-json/dt-va/v1/token` (signed, bound to the visitor IP, valid two hours; `/submit` refuses tokens younger than 3 seconds). `front.js` handles it.

The visitor's confirmation email contains only the reference and contact details, never text the visitor typed. The filter `dt_va_client_confirmation_body` changes it (empty string sends none).

Attachments are saved under `uploads/dt-va/` with random names; the folder gets an `.htaccess` deny rule and files are downloaded from the enquiry screen. **On nginx**, add `location ^~ /wp-content/uploads/dt-va/ { deny all; }`.

Enquiries are visible only to administrators (`manage_options`), in WP Admin and the REST API.

## Email and personal data

- Every email is sent **from an address on the site's own domain** (default `wordpress@` the domain), so it passes SPF/DKIM/DMARC; the enquiry email (or the visitor) is the Reply-To. Use a real mailbox on the domain, or an SMTP plugin, for the best delivery.
- Enquiries hold personal data: they appear in **Tools → Export Personal Data / Erase Personal Data**, are deleted after the retention period (Settings → Company → Privacy), and their attachment goes with them. A suggested text is added to **Settings → Privacy** for the privacy policy.

## Loading, hours and language

- Every page gets only the tab, a 1 KB loader and the tab styles (`front-tab.css`, about 4 KB). The loader fetches `GET /wp-json/dt-va/v1/config`, the panel styles (`front.css`) and `front.js` on the first hover, focus or click on the tab, or a few seconds after the page has loaded, so the nudge and the "resume your brief" reminder keep working. The widget uses the theme's font.
- Opening hours (Settings → Company → Opening hours), the closed dates listed there (holidays, one date or range per line) and the site time zone (Settings → General) drive the assistant, the enquiry emails, the call-back invite and the reminders.
- The rules and the knowledge base are sent as a fixed prompt prefix, with the visitor's chosen service last, so OpenAI and Anthropic can answer the repeated part from their prompt cache (cheaper and faster once the knowledge base is long).
- All texts, the visitor emails and the studio emails follow the site language. The AI is told to answer in the visitor's language.
- Enquiry references look like `DT-2610-K7Q3XM`.
