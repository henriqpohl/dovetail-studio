# Virtual Assistant — Usage

Enable **Virtual Assistant** in **Dovetail Studio → Modules**. Open it from the Dovetail Studio shortcut or the external **VA Enquiries** menu. Configure provider, company information, knowledge, theme and assistant behaviour from its settings screens.

## Possible Errors and How to Fix Them

### Admin JSON tools do not load
Confirm `assets/js/admin-json.js` exists and the current page is `dt-va-settings`.

### Knowledge changes are ignored
Verify the saved knowledge override and `resources/knowledge.md`, then clear any relevant object/full-page cache.
