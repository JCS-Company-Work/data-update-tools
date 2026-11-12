const pfButton = document.getElementById("product-finder-save-json");
const pfSelect = document.getElementById("product-finder-brand-select");
const pfOutcome = document.querySelector(
  ".product-finder-update-wrapper .outcome-message",
);
const pfSpinner = document.querySelector(
  ".product-finder-update-wrapper .dupt-spinner",
);
let pfOutcomeTimer;

if (pfButton && pfSelect && pfOutcome && pfSpinner) {
  pfButton.addEventListener("click", async () => {
    pfButton.disabled = true;
    pfSpinner.classList.add("is-active");
    pfOutcome.classList.remove("is-active");

    try {
      const response = await fetch(productFinderUpdateConfig.ajaxUrl, {
        method: "POST",
        body: new URLSearchParams({
          action: productFinderUpdateConfig.action,
          nonce: productFinderUpdateConfig.nonce,
          brand: pfSelect.value,
        }),
      });
      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Unable to update the data.");
      }

      showPfOutcome(result.data.message, true);
    } catch (error) {
      showPfOutcome(error.message, false);
    } finally {
      pfButton.disabled = false;
      pfSpinner.classList.remove("is-active");
    }
  });
}

function showPfOutcome(message, success) {
  clearTimeout(pfOutcomeTimer);
  pfOutcome.className = `outcome-message is-active ${success ? "success" : "failure"}`;
  pfOutcome.textContent = message;
  pfOutcomeTimer = setTimeout(() => {
    pfOutcome.classList.remove("is-active");
  }, 3000);
}
