# Saving: Knowledge base, Doors and Offline FAQ

All three follow the same pattern: a form saves a WordPress option after validation; at runtime the front end or the system prompt uses that option when it exists, otherwise the built-in default.

### Knowledge base (`src/services/knowledge.php`)

```mermaid
flowchart TD
    KB1["Textarea (free text)"] --> KB2["update_option dt_va_knowledge_override"]
    KB2 --> KB3["dt_va_knowledge_base(): override, otherwise resources/knowledge.md, otherwise the built-in template"]
    KB3 --> KB4["dt_va_system_prompt(): fixed part of the AI prompt"]
```

### Doors and Step questions (`src/services/flow.php`)

```mermaid
flowchart TD
    D1["Add a door / Add a step (simple form)"] --> D2["candidate = current list + the new entry"]
    D3["JSON textarea (bulk edit)"] --> D2b["candidate = the full JSON"]
    D2 --> D4["dt_va_flow_doors_validate() / dt_va_flow_steps_validate()"]
    D2b --> D4
    D4 -->|ok| D5["update_option dt_va_flow_doors / dt_va_flow_steps"]
    D4 -->|error| D6["Error notice, nothing saved"]
    D5 --> D7["front.js via /config (flowConfig.doors, flowConfig.steps)"]
```

### Offline FAQ (`src/services/faq.php`)

```mermaid
flowchart TD
    F1["Add an entry (simple form)"] --> F2["candidate = current entries + the new one"]
    F3["JSON textarea (bulk edit)"] --> F2b["candidate = the full JSON"]
    F2 --> F4["dt_va_faq_validate()"]
    F2b --> F4
    F4 -->|ok| F5["update_option dt_va_faq_entries"]
    F4 -->|error| F6["Error notice, nothing saved"]
    F5 --> F7["offlineAnswer() in front.js via /config (faqConfig)"]
```

- **Common pattern:** the "Add …" form and the JSON textarea go through the same validation function, so neither path accepts what the other would refuse.
- **Restore default:** deletes the option (`delete_option`) and goes back to the built-in default; no file is touched.
- **Safe by design:** Doors validate `action` / `service` by format (never executable free text); Offline FAQ keywords are plain words or phrases, never regular expressions (no ReDoS); the Knowledge base is free text that only feeds the system prompt and is never executed.
