/* Imports update the editor only. Save remains the explicit persistence action. */
(function () {
  "use strict";
  document.querySelectorAll(".dt-va-json-tools").forEach(function (tools) {
    var form = tools.closest("form");
    var editor = form.querySelector('textarea[name="' + tools.dataset.format + '"]');
    var fileInput = tools.querySelector('input[type="file"]');
    var status = tools.querySelector('[role="status"]');
    var busy = false;
    function message(text) { status.textContent = " " + text; }
    function download(text, suffix) {
      var url = URL.createObjectURL(new Blob([text + "\n"], { type: "application/json;charset=utf-8" }));
      var link = document.createElement("a");
      link.href = url;
      link.download = "dt-va-" + tools.dataset.format.replace(/_/g, "-") + suffix + ".json";
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }
    async function validate(text) {
      var body = new URLSearchParams({ action: "dt_va_json_validate", format: tools.dataset.format,
        nonce: form.querySelector('[name="_wpnonce"]').value, json: text });
      var response = await fetch(ajaxurl, { method: "POST", credentials: "same-origin", body: body });
      var result;
      try { result = await response.json(); } catch (_) { throw new Error("Validation failed. Reload the page and try again."); }
      if (!response.ok || !result.success) throw new Error(result.data && result.data.message || "Validation failed. Reload the page and try again.");
      return result.data.json;
    }
    async function run(action, file) {
      if (busy) return;
      busy = true;
      var original = editor.value;
      tools.querySelectorAll("button").forEach(function (button) { button.disabled = true; });
      message("Validating…");
      try {
        if (file && file.size > 1048576) throw new Error("Choose a JSON file up to 1 MB.");
        var text = file ? await file.text() : original;
        var validated = await validate(text);
        if (action === "import") {
          if (editor.value !== original) throw new Error("The editor changed during import. Import again to replace it.");
          editor.value = validated;
          editor.dispatchEvent(new Event("input", { bubbles: true }));
          message("Imported and validated. Review the content, then click Save to apply it.");
        } else {
          download(validated, "");
          message("Exported the validated editor content. Saved settings were not changed.");
        }
      } catch (error) { message(error.message || "Unable to read or validate this file. Try again."); }
      finally {
        busy = false;
        fileInput.value = "";
        tools.querySelectorAll("button").forEach(function (button) { button.disabled = false; });
      }
    }
    // Prevent Save/Restore from racing a pending file read or server validation.
    form.addEventListener("submit", function (event) {
      if (busy) { event.preventDefault(); message("Please wait for validation to finish before saving or restoring."); }
    });
    tools.addEventListener("click", function (event) {
      var button = event.target.closest("button[data-json-action]");
      if (!button || busy) return;
      var action = button.dataset.jsonAction;
      if (action === "import") fileInput.click();
      else if (action === "sample") {
        download(tools.dataset.sample, "-sample");
        message("Sample downloaded. Edit it with your own content before importing.");
      } else run("export");
    });
    fileInput.addEventListener("change", function () {
      if (fileInput.files.length) run("import", fileInput.files[0]);
    });
  });
})();
