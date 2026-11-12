const sampleButton = document.getElementById("sample-save-json");
const sampleSelect = document.getElementById("sample-brand-select");
const sampleOutcome = document.querySelector(
  ".sample-request-update-wrapper .outcome-message",
);
const sampleSpinner = document.querySelector(
  ".sample-request-update-wrapper .dupt-spinner",
);
let sampleOutcomeTimer;

if (sampleButton && sampleSelect && sampleOutcome && sampleSpinner) {
  sampleButton.addEventListener("click", async () => {
    if (!sampleSelect.value) {
      showSampleOutcome("Please choose an option.", false);
      return;
    }

    sampleButton.disabled = true;
    sampleSpinner.classList.add("is-active");
    sampleOutcome.classList.remove("is-active");

    try {
      const response = await fetch(sampleRequestUpdateConfig.ajaxUrl, {
        method: "POST",
        body: new URLSearchParams({
          action: sampleRequestUpdateConfig.action,
          nonce: sampleRequestUpdateConfig.nonce,
          toolType: sampleSelect.value,
        }),
      });
      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Unable to update the data.");
      }

      showSampleOutcome(result.data.message, true);
    } catch (error) {
      showSampleOutcome(error.message, false);
    } finally {
      sampleButton.disabled = false;
      sampleSpinner.classList.remove("is-active");
    }
  });
}

function showSampleOutcome(message, success) {
  clearTimeout(sampleOutcomeTimer);
  sampleOutcome.className = `outcome-message is-active ${success ? "success" : "failure"}`;
  sampleOutcome.textContent = message;
  sampleOutcomeTimer = setTimeout(() => {
    sampleOutcome.classList.remove("is-active");
  }, 3000);
}
