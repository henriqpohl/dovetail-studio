# How the module works

```mermaid
flowchart TD
    subgraph Admin["WP Admin: VA Enquiries > Settings"]
        A1[AI Keys]
        A2[Company]
        A3[AI Assistant]
        A4[Knowledge base]
        A5[Doors / Step questions]
        A6[Offline FAQ]
        A7[Portfolio]
        A8[Theme]
    end

    Admin -->|saved in wp_options| Config["GET /wp-json/dt-va/v1/config"]
    Page["Every page: tab, front-tab.css, front-loader.js"] -->|hover, click or idle| Config
    Config --> Front["front.js + front.css: chat panel"]

    Front -->|door / step clicks| Flow["Scripted flow: doors -> steps -> work/money/timing/capture"]
    Front -->|free text, isQuestion| Ask["POST /wp-json/dt-va/v1/ask"]
    Ask --> Provider["dt_va_provider_answer: OpenAI / Anthropic, Knowledge base in the system prompt"]
    Provider -. failure, no key or timeout .-> Offline["offlineAnswer(): saved FAQ or built-in table"]

    Flow --> Submit["POST /wp-json/dt-va/v1/submit"]
    Submit --> Enquiry["dt_enquiry (private post) + email to the team + confirmation to the visitor + call-back ICS"]

    Cron1["Daily 07:30: self-check"] --> Provider
    Cron2["Daily: reminders and retention clean-up"] --> Enquiry
    Cron3["Weekly: digest"] --> Enquiry

    Front -->|analytics| Event["POST /wp-json/dt-va/v1/event"]
    Event --> Stats["Atomic counters folded into weekly dt_va_stats_* options, used by the digest"]
```

- **Admin:** each tab saves options in the database (`dt_va_*`); nothing requires editing code.
- **Loading:** every page gets only the tab, `front-tab.css` and `front-loader.js`. The loader fetches `/config` (doors, steps, FAQ, theme, public details, translations), `front.css` and `front.js` on the first interaction with the tab or once the page is idle.
- **Flow (doors / steps):** runs in the browser, with no network call, until `capture` sends `/submit`.
- **Ask:** only when the free text looks like a question; the AI answers only from the Knowledge base in the system prompt.
- **Submit:** always works (with or without AI): it creates the VA Enquiries record and sends the emails from an address on the site's domain.
- **Tokens:** `/ask`, `/submit` and `/event` need a visitor token from `GET /token` (signed, bound to the IP).
- **Scheduled events:** the daily self-check emails when the AI stops (or starts) answering; the daily run sends reminders for unanswered enquiries and deletes enquiries past the retention period; the weekly digest summarises enquiries, the funnel and the doors used.
