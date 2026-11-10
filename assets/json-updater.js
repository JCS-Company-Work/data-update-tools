const button = document.getElementById("save-json");
const select = document.getElementById("brand-select");
const outcome = document.querySelector(
  ".json-updater-wrapper .outcome-message",
);

if (button && select && outcome) {
  button.addEventListener("click", async () => {
    button.disabled = true;

    try {
      const response = await fetch(jsonUpdaterConfig.ajaxUrl, {
        method: "POST",
        body: new URLSearchParams({
          action: jsonUpdaterConfig.action,
          nonce: jsonUpdaterConfig.nonce,
          toolType: select.value,
        }),
      });
      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Unable to update the data.");
      }

      showOutcome(result.data.message, true);
    } catch (error) {
      showOutcome(error.message, false);
    } finally {
      button.disabled = false;
    }
  });
}

function showOutcome(message, success) {
  outcome.hidden = false;
  outcome.className = `${success ? "success" : "failure"}`;
  outcome.textContent = message;
}
