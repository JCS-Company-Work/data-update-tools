const button = document.getElementById("save-json");
const select = document.getElementById("brand-select");
const outcome = document.querySelector(
  ".json-updater-wrapper .outcome-message",
);
const spinner = document.querySelector(".json-updater-wrapper .dupt-spinner");
let outcomeTimer;

if (button && select && outcome && spinner) {
  button.addEventListener("click", async () => {
    button.disabled = true;
    spinner.classList.add("is-active");
    outcome.classList.remove("is-active");

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
      spinner.classList.remove("is-active");
    }
  });
}

function showOutcome(message, success) {
  clearTimeout(outcomeTimer);
  outcome.className = `outcome-message is-active ${success ? "success" : "failure"}`;
  outcome.textContent = message;
  outcomeTimer = setTimeout(() => {
    outcome.classList.remove("is-active");
  }, 3000);
}
