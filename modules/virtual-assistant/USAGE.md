# Virtual Assistant — Usage

Enable **Virtual Assistant** in **Dovetail Studio → Modules**, then open **VA Enquiries → Settings** (or the **Virtual Assistant** shortcut in the Dovetail Studio menu). The settings have eight tabs:

| Tab | What it sets |
| --- | --- |
| [AI Keys](#ai-keys) | The AI providers (OpenAI, Anthropic), their keys and models |
| [Company](#company) | Who the assistant speaks for, the public contact details, hours, the weekly digest and privacy |
| [AI Assistant](#ai-assistant) | The assistant's name, price ballparks and spelling |
| [Knowledge base](#knowledge-base) | The only facts the AI may answer from |
| [Doors](#doors-and-step-questions) | The opening menu and the guided questions (Step questions) |
| [Offline FAQ](#offline-faq) | Answers used without the AI |
| [Portfolio](#portfolio) | Projects shown as cards during an enquiry |
| [Theme](#theme) | The chat colours |

The examples below use **Dovetail Consultancy**, a marketing and design agency that does logos and branding, websites, print, design and artwork, digital marketing and SEO. The contact details, prices, dates and projects in them are illustrations, not real data: replace them with the real business.

## Quick start

1. **Company:** fill in **Phone — display** and **Public email**. They are required: the assistant stays hidden on the site until both are set, and a notice says which one is missing.
2. **Company:** set the **Enquiry email** (where enquiries go), the opening hours and any closed dates.
3. **Knowledge base:** replace the template with facts about the business.
4. **AI Keys:** add an OpenAI or Anthropic key and a model, then **Save and test providers**. Without a key the assistant still works; it answers from the Offline FAQ and passes every other question to the team.
5. **Doors:** keep the default menu or build your own flow.
6. Open the site in a private window and try it.

Until each of Knowledge base, Doors, Offline FAQ and Portfolio has been reviewed, a "company setup needs review" reminder is shown in the admin. Each tab has a **Confirm reviewed for this company** button (or "Confirm use of the default …" when you keep the default). The reminder never blocks the assistant.

---

## AI Keys

The AI only answers questions the visitor types. The guided menu (doors and steps) never uses it.

| Field | Use |
| --- | --- |
| **Primary provider** | The provider asked first: OpenAI or Anthropic. |
| **Secondary provider** | The provider asked only if the first fails, or None. |
| **OpenAI API key** / **Anthropic API key** | Paste the key and save. It is stored encrypted; only its first characters are shown afterwards. Leave the field blank to keep the saved key; tick **Clear saved key** to remove it. |
| **OpenAI model** / **Anthropic model** | The model ID (not the key), for example one from the list under the field or the official catalog linked there. Blank disables that provider. |
| **Timeout per provider (seconds)** | 3 to 12. Each question has 15 seconds in total across both providers (`DT_VA_ASK_DEADLINE`); after that the visitor gets the offline answer. |
| **Maximum output tokens** | Length limit of an answer (64 to 4096). 700 is plenty for the short answers the assistant gives. |

**Save and test providers** sends a test question and shows which provider answered, with timings, under **Last provider diagnostics**. **Last daily check** is the automatic test run every day at 07:30; when the AI stops (or starts again) answering, the enquiry email is told.

Keys and models can also live outside the database, which is preferred on production:

```php
// wp-config.php
define( 'DT_VA_OPENAI_KEY', 'sk-…' );
define( 'DT_VA_OPENAI_MODEL', 'your-model-id' );
define( 'DT_VA_ANTHROPIC_KEY', 'sk-ant-…' );
define( 'DT_VA_ANTHROPIC_MODEL', 'your-model-id' );
```

Environment variables with the same names work too. A key defined there is shown as "Configured externally" and cannot be edited in the admin.

---

## Company

### Company settings

| Field | Use | Example |
| --- | --- | --- |
| **Company name** | Used in the greeting, the replies and the emails. Defaults to the site title. | `Dovetail Consultancy` |
| **Avatar** | Up to 2 characters in the tab and the panel header. | `DT` |
| **Collapse Avatar** | Hide the avatar on the closed tab. | |
| **Enquiry email** | Receives every enquiry and the automatic emails, and is the Reply-To of the visitor's confirmation. | `studio@dovetail-consultancy.example` |
| **Sender name** | Name shown in the From of every email. Defaults to the site title. | `Dovetail Consultancy` |
| **Sender email** | Address every email is sent from. Use an address on the site's own domain so the emails pass SPF/DKIM and do not land in spam. Blank sends from `wordpress@` the domain. | `hello@dovetail-consultancy.example` |

### Public company details

What visitors see and the AI quotes. Fields marked **\*** are required for the assistant to appear.

| Field | Use | Example |
| --- | --- | --- |
| **Company Type** | What visitors are told to call the place ("the Studio is closed right now"). | `Studio` |
| **Company Address** | Shown in "Talk to a person". Blank hides it. | `4 Mill Yard, Springfield` |
| **Company City** | Used in "The team in Springfield takes the calls themselves." | `Springfield` |
| **Public email \*** | Shown in the assistant. Not where enquiries go. | `hello@dovetail-consultancy.example` |
| **Phone — display \*** | The number as visitors read it. | `(555) 010-0100` |
| **Phone — dial link** | The number for click-to-call, with the country code. | `+15550100100` |
| **WhatsApp number** | Digits only, with the country code, no `+`. Blank hides WhatsApp. | `15550100100` |
| **WhatsApp — display** | The WhatsApp number as visitors read it. | `(555) 010-0100` |
| **Privacy policy URL** | Linked under the enquiry form. | `/privacy-policy/` |
| **Portfolio URL** | Link to the work or case studies page. | `/our-work/` |
| **Response time** | The promise in the confirmation, after "come back to you". | `within one working day` |
| **Opening hours** | Opens and closes as decimal hours in the site time zone (Settings → General): `17.5` is 17:30. Tick the open days. | `9` to `17.5`, Mon–Fri |
| **Closed dates** | Holidays and closures: one date or range per line. Past dates are dropped on save. | `2026-12-24 to 2026-12-26` |
| **Booking URL** | Calendly or another booking link, offered as "Pick a time in the diary". | `https://calendly.com/dovetail-consultancy/discovery-call` |
| **Google rating** / **Google review count** / **Google Maps profile URL** | Entered by hand; shown under the greeting. Blank hides them. | `4.9`, `86` |
| **Resume conversation — hours** | How long an unfinished conversation can be picked up again in the same browser. `0` turns it off. | `24` |

Each field has **Restore default**, which resets only that field.

The opening hours decide what the assistant says: "the Studio is open now" or "reopens at 9:00 am tomorrow", the time of the call-back calendar invite, and the two working days after which an unanswered enquiry triggers a reminder.

### Weekly digest

The day, time and recipient of the weekly summary: enquiries, call backs, the funnel (opened, door, described, budget, timing, form, sent) and how answers were rated. Blank recipient uses the enquiry email.

### Privacy

**Keep enquiries for … months** (default 24; `0` keeps them). Older enquiries and their attachments are deleted every day. Enquiries also appear in **Tools → Export Personal Data** and **Tools → Erase Personal Data**, and a suggested text is added to **Settings → Privacy** for the privacy policy.

---

## AI Assistant

| Field | Use | Example |
| --- | --- | --- |
| **AI Assistant name** | Optional. With a name the greeting reads "Hello. I'm **Ada** the AI Assistant at Dovetail Consultancy." | `Ada` |
| **Offer a ballpark price** | Lets the AI give a rough range when the Knowledge base has one and the visitor asks about cost. It always adds that a fixed quote follows once the team has seen the brief, and never gives a firm price. | |
| **Spelling and dates** | American English (color, month/day/year) or British and Irish English (colour, day/month/year). Applies to the AI's answers. The AI always replies in the language the visitor writes in. | |

---

## Knowledge base

The only facts the AI is allowed to use. Anything not written here, the AI says it doesn't know and that the team will cover it. Write plain sentences; headings in capitals help. Keep it true.

Example:

```text
ABOUT THE COMPANY
Dovetail Consultancy is a marketing and design agency in Springfield, founded in 2012, with a team of eight designers, developers and marketers.
Clients can visit the studio at 4 Mill Yard, Monday to Friday, 9:00 to 17:30, by appointment.
Phone (555) 010-0100, email hello@dovetail-consultancy.example.

SERVICES
Logo and brand identity: logo, colour palette, typography and brand guidelines. Usually 3 to 4 weeks, with two rounds of changes.
Website design and development: WordPress websites and online shops, designed, built, hosted and maintained. A brochure site takes 6 to 8 weeks.
Print, design and artwork: brochures, flyers, signage, packaging and print-ready artwork. The studio manages the printing and delivery.
Digital marketing and SEO: search engine optimisation, Google Ads, social media campaigns and monthly reports.
Motion design: short animations and videos for social media and presentations.

HOW WE WORK
Every project starts with a free discovery call to understand the brief. A written proposal with a fixed price follows.
A 50% deposit starts the work; the rest is due on delivery. The client owns the final files.
Clients send their logo, photos and texts at the start; the studio can also write the copy.

PRICES
A logo starts at $900. A brochure website starts at $3,500. SEO starts at $600 a month. Every quote is fixed after the discovery call.

WHAT THE ASSISTANT MUST NOT DO
Give a firm price or a firm date. Promise search rankings. Invent a client, award, fact or figure. Claim to be a person.
```

The "PRICES" section is only used when **Offer a ballpark price** is on. **Restore default** goes back to the bundled template (`resources/knowledge.md`).

---

## Doors and Step questions

The **Doors** tab sets the guided part of the conversation, which runs without the AI:

- **Doors** are the buttons of the opening menu.
- **Step questions** are the questions asked after a door is picked.

```
Assistant: "Hello. I'm the AI Assistant at Dovetail Consultancy. What are you looking at?"
   [A new website]  [Logo & branding]  [I'm already a client]  [I'd rather talk to a person]   ← doors

Visitor picks [A new website]

Assistant: "What kind of website do you need?"               ← step web1
   [Brochure site] [Online shop] [Landing page] [Not sure yet]

Assistant: "About how many pages?"                            ← step web2
   [1–5] [6–15] [More than 15] [Not sure yet]

Assistant: "Do you already have a logo and brand guidelines?" ← step web3
   [Yes] [A logo only] [No, we need branding too]

Up to three similar projects from the Portfolio              ← built-in step work
Budget question → deadline question                           ← built-in steps money, timing
Contact form (name, email, phone, file, call back) → sent     ← built-in step capture
```

### Doors

| Field | Use |
| --- | --- |
| **Id** | A unique name for the door, for your own reference. |
| **Label** | The button text. |
| **Action** | `service` starts a flow of Step questions. `other` opens "Something else" (the visitor describes what they need). `human` opens "Talk to a person" (phone, WhatsApp, booking, call back). |
| **Service** | Only for `service` doors: the name of the flow. **The door opens the step called Service + `1`**: Service `web` opens the step `web1`. Under the field, the form shows which step the door will open and whether it exists yet. |
| **Sub** / **Short** | Optional second line under the label / shorter label for small screens. |
| **Icon** | Optional: `logo`, `print`, `web`, `client`, `other` or `human`. |

1 to 12 doors. Without saved doors the assistant uses the default four (Enquire about a product or service, I'm already a client, Something else, I'd rather talk to a person). **Add door** keeps the doors already in the list (the default four the first time) and adds the new one at the end; to keep only your own doors, edit the JSON below the form and **Save doors**.

### Step questions

| Field | Use |
| --- | --- |
| **Step id** | The step's name. The first step of a flow must be the door's Service + `1`; the others can have any name. |
| **Type** | `choice`: the visitor taps one of the Options. `text`: the visitor types the answer. |
| **Question** | What the assistant asks. |
| **Example (optional)** | A line shown under the question (for example: "the spring campaign flyer"). |
| **Answer key** | Where the answer is stored. It appears with that name in the brief the team receives (`site_type` → "Site type"). |
| **Options** | For `choice`: 1 to 8 options, comma-separated in the form. |
| **Next step id** | The step that comes after. **The order of the conversation is only this chain of `next`**: not the numbers in the names and not the order the steps were added. |

`next` can also point to the built-in steps:

| Step | What it does |
| --- | --- |
| `describe` | "Tell me a little about the business and who it's for." (free text) |
| `work` | Shows up to three projects from the Portfolio, then goes to `money` |
| `money` | The budget question, then `timing` |
| `timing` | The deadline question, then `capture` |
| `capture` | The contact form; sending it creates the enquiry and the emails |
| `other` / `human` | "Something else" / "Talk to a person" |

A step id you define with the same name as a built-in one (for example `describe` or `enquiry1`) replaces its question; `work`, `money`, `timing` and `capture` cannot be replaced, only pointed to.

### Example: building the website flow

**With the forms.** When a step is saved, its `next` must already exist, so add the steps **from the last to the first**:

1. **Add a step** — branding:
   - Step id: `web3`
   - Type: `choice`
   - Question: `Do you already have a logo and brand guidelines?`
   - Answer key: `branding`
   - Options: `Yes, A logo only, No, we need branding too`
   - Next step id: `work`
2. **Add a step** — size:
   - Step id: `web2`
   - Type: `choice`
   - Question: `About how many pages?`
   - Answer key: `pages_count`
   - Options: `1–5, 6–15, More than 15, Not sure yet`
   - Next step id: `web3`
3. **Add a step** — the kind of site:
   - Step id: `web1`
   - Type: `choice`
   - Question: `What kind of website do you need?`
   - Answer key: `site_type`
   - Options: `Brochure site, Online shop, Landing page, Not sure yet`
   - Next step id: `web2`
4. **Add a door:**
   - Id: `web`
   - Label: `A new website`
   - Sub: `Design, build and hosting`
   - Action: `service`
   - Service: `web`
   - Icon: `web`

Add more doors the same way for the other services: Service `logo` with a step `logo1`, `print` with `print1`, `seo` with `seo1`.

**With the JSON.** Paste the steps into the Step questions box and **Save step questions** (here the order does not matter, everything is checked together):

```json
{
    "web1": {
        "type": "choice",
        "question": "What kind of website do you need?",
        "answerKey": "site_type",
        "options": ["Brochure site", "Online shop", "Landing page", "Not sure yet"],
        "next": "web2"
    },
    "web2": {
        "type": "choice",
        "question": "About how many pages?",
        "answerKey": "pages_count",
        "options": ["1–5", "6–15", "More than 15", "Not sure yet"],
        "next": "web3"
    },
    "web3": {
        "type": "choice",
        "question": "Do you already have a logo and brand guidelines?",
        "answerKey": "branding",
        "options": ["Yes", "A logo only", "No, we need branding too"],
        "next": "work"
    },
    "logo1": {
        "type": "choice",
        "question": "Is this a new brand or a refresh of an existing one?",
        "answerKey": "brand_stage",
        "options": ["A new brand", "A refresh", "Not sure yet"],
        "next": "describe"
    },
    "seo1": {
        "type": "choice",
        "question": "What would you like help with?",
        "answerKey": "marketing",
        "options": ["SEO", "Google Ads", "Social media", "All of it"],
        "next": "money"
    }
}
```

And the doors into the Doors box, then **Save doors**:

```json
[
    { "id": "web", "label": "A new website", "sub": "Design, build and hosting", "icon": "web", "action": "service", "service": "web" },
    { "id": "logo", "label": "Logo & branding", "sub": "A new identity or a refresh", "icon": "logo", "action": "service", "service": "logo" },
    { "id": "seo", "label": "Digital marketing & SEO", "sub": "Be found and get more enquiries", "icon": "other", "action": "service", "service": "seo" },
    { "id": "client", "label": "I'm already a client", "sub": "Changes, updates or an account query", "icon": "client", "action": "service", "service": "client" },
    { "id": "human", "label": "I'd rather talk to a person", "sub": "Call us or ask for a call back", "icon": "human", "action": "human" }
]
```

The team receives the answers in the brief, for example:

```text
Enquiry: A new website
Timing: Within a month
Budget: around $5,000
Site type: Online shop
Pages count: 6–15
Branding: A logo only
```

### Branches: a different path for one answer

In the JSON a `next` can depend on an answer. Online shop visitors get one more question, the others go straight on:

```json
"web1": {
    "type": "choice",
    "question": "What kind of website do you need?",
    "answerKey": "site_type",
    "options": ["Brochure site", "Online shop", "Landing page", "Not sure yet"],
    "next": { "if": { "key": "site_type", "answerEquals": "Online shop" }, "then": "shop1", "else": "web2" }
},
"shop1": {
    "type": "text",
    "question": "About how many products will the shop sell?",
    "example": "For example: 40 products, some with sizes and colours",
    "answerKey": "products",
    "next": "web2"
}
```

Conditions: `answerEquals` (exact answer), `answerContains` (a list of words, any of them in the answer) and `answerMissing` (no answer). Branches can be nested up to 5 levels. The **Add a step** form only takes a plain `next`; branches are written in the JSON.

### Checks

- A `service` door without its first step (Service `web` without `web1`) is reported on the tab ("Missing step for service…"); picking it would just restart the assistant.
- A `next` pointing to a step that does not exist is refused when saving.
- A step nothing points to is saved but never asked.
- Saved changes show on the next page load. A conversation already in progress keeps its place: press the restart button (↻) at the top of the chat to see the new menu. With a page cache plugin, clear its cache after saving.

---

## Offline FAQ

Answers used when the AI is not available (no key, an error, a timeout) and, for short questions, before the AI is asked. The first entry whose keyword appears in the question wins, in list order.

| Field | Use | Example |
| --- | --- | --- |
| **Id** | A unique name. | `turnaround` |
| **Keywords** | Plain words or short phrases, comma-separated (no regular expressions). | `how long, turnaround, timeline` |
| **Answer** | Up to 1200 characters. `{{COMPANY_NAME}}` is replaced with the company name. | `A logo usually takes 3 to 4 weeks and a brochure website 6 to 8 weeks. The {{COMPANY_NAME}} team confirms the dates in the proposal.` |

More agency examples, as JSON:

```json
[
    { "id": "turnaround", "keywords": ["how long", "turnaround", "timeline"], "answer": "A logo usually takes 3 to 4 weeks and a brochure website 6 to 8 weeks. The {{COMPANY_NAME}} team confirms the dates in the proposal." },
    { "id": "files", "keywords": ["file formats", "source files", "own the files"], "answer": "You receive the final files in every format you need (AI, EPS, PDF, PNG, SVG) and you own them." },
    { "id": "printing", "keywords": ["printing", "print run", "delivery"], "answer": "{{COMPANY_NAME}} prepares print-ready artwork and can manage the printing and delivery for you." },
    { "id": "hosting", "keywords": ["hosting", "maintenance", "updates"], "answer": "{{COMPANY_NAME}} can host your website and keep WordPress and its plugins up to date with a monthly care plan." }
]
```

Saving replaces the built-in FAQ entirely; **Restore default** brings it back. **Import** / **Export** load and download the list as a JSON file; **Download a Sample** gives a template.

---

## Portfolio

Projects shown as cards by the built-in `work` step (in the example flow, after `web3`). Projects whose keywords match what the visitor wrote come first; up to three cards. With no project saved, the step is skipped.

| Field (JSON key) | Use | Example |
| --- | --- | --- |
| **Title** (`t`) | Required, up to 60 characters. | `Brand identity for a craft brewery` |
| **Description** (`s`) | Required, up to 140 characters. | `Logo, labels and packaging for a new range of craft beers.` |
| **Outcome** (`o`) | Up to 80 characters. | `Stocked in 40 shops in the first year` |
| **Project URL** (`url`) | `https://…` or a path on the site. | `/our-work/craft-brewery/` |
| **Image URL** (`img`) | `https://…` only. | `https://dovetail-consultancy.example/wp-content/uploads/brewery.jpg` |
| **Sector** (`sec`) | Up to 60 characters. | `Food & drink` |
| **Industry keywords** (`ind`) | Plain words separated by spaces, matched against what the visitor writes. | `brewery beer drinks packaging label` |

```json
[
    {
        "t": "Brand identity for a craft brewery",
        "s": "Logo, labels and packaging for a new range of craft beers.",
        "o": "Stocked in 40 shops in the first year",
        "url": "/our-work/craft-brewery/",
        "img": "",
        "sec": "Food & drink",
        "ind": "brewery beer drinks packaging label"
    },
    {
        "t": "Online shop for a fashion boutique",
        "s": "WooCommerce shop with 300 products, photography and launch campaign.",
        "o": "Online sales up 60% in six months",
        "url": "/our-work/fashion-boutique/",
        "img": "",
        "sec": "Retail",
        "ind": "fashion clothing shop ecommerce boutique"
    },
    {
        "t": "SEO for a dental clinic",
        "s": "Local SEO, Google Business Profile and monthly content.",
        "o": "First page on Google for 12 local searches",
        "url": "/our-work/dental-clinic-seo/",
        "img": "",
        "sec": "Healthcare",
        "ind": "dental dentist clinic health local seo"
    }
]
```

1 to 30 projects. Replace or remove the sample project before confirming the review.

---

## Theme

The chat colours. Each one is a colour picker; changes appear on the site after saving, without a flash of the default colours. **Restore default theme** goes back to the original palette.

| Colour | Where |
| --- | --- |
| **Accent** / **Accent hover** | The tab, the send button, links and highlights |
| **Panel background** | The chat window |
| **Header and form background** | The header, the door cards and the form |
| **Assistant message background** | The assistant's bubbles |
| **Input background** | The text field and form fields |
| **Primary text** / **Secondary text** | Main text / hints and small print |
| **Visitor message background** / **Visitor message text** | The visitor's bubbles |
| **Borders** | Lines and outlines |

The chat uses the theme's font.

---

## Possible Errors and How to Fix Them

### The assistant does not appear on the site
Fill in **Phone — display** and **Public email** (Company tab); the notice in the admin names the field still missing. Then check that the theme calls `wp_head()` and `wp_footer()`, and clear any page cache.

### A new door or step does not show
Press the restart button (↻) in the chat: a conversation in progress keeps its place. With a page cache plugin, clear its cache so the page loads the new configuration.

### AI answers fail
**AI Keys → Save and test providers** and read **Last provider diagnostics** (`http_401`: wrong key; `http_404`: wrong model ID; `network`: the server cannot reach the provider).

### Emails go to spam
Set a **Sender email** on the site's domain and configure SPF and DKIM for it, or use an SMTP plugin.

### Admin JSON tools do not load
Confirm `assets/js/admin-json.js` exists and the current page is `dt-va-settings`.

### Knowledge changes are ignored
Check the saved Knowledge base (or `resources/knowledge.md` when none is saved), then clear any object or page cache.
