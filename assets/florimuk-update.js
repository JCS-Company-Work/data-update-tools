(() => {
  const button = document.getElementById("florimuk-save-json");
  const select = document.getElementById("florimuk-update-select");
  const outcome = document.querySelector(".florimuk-update-wrapper .outcome-message");
  const spinner = document.querySelector(".florimuk-update-wrapper .dupt-spinner");
  let outcomeTimer;

  if (!button || !select || !outcome || !spinner) return;

  button.addEventListener("click", async () => {
    button.disabled = true;
    spinner.classList.add("is-active");
    outcome.classList.remove("is-active");

    try {
      const response = await fetch(florimukUpdateConfig.ajaxUrl, {
        method: "POST",
        body: new URLSearchParams({
          action: florimukUpdateConfig.action,
          nonce: florimukUpdateConfig.nonce,
          updateType: select.value,
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

  function showOutcome(message, success) {
    clearTimeout(outcomeTimer);
    outcome.className = `outcome-message is-active ${success ? "success" : "failure"}`;
    outcome.textContent = message;
    outcomeTimer = setTimeout(() => {
      outcome.classList.remove("is-active");
    }, 3000);
  }
})();
