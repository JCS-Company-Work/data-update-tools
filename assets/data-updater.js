const button = document.getElementById('update-all');
const outcome = document.querySelector('.data-updater-wrapper .outcome-message');
const spinner = document.querySelector('.data-updater-wrapper .dupt-spinner');

if (button && outcome && spinner) {
  button.addEventListener('click', async () => {
    button.disabled = true;
    spinner.classList.add('is-active');
    outcome.classList.remove('is-active');
    outcome.textContent = '';

    try {
      const response = await fetch(dataUpdaterConfig.ajaxUrl, {
        method: 'POST',
        body: new URLSearchParams({
          action: dataUpdaterConfig.action,
          nonce: dataUpdaterConfig.nonce,
        }),
      });

      await window.duptRefreshStream.display(response, outcome);
    } catch (error) {
      window.duptRefreshStream.showError(outcome, error.message || 'Unable to update resources.');
    } finally {
      button.disabled = false;
      spinner.classList.remove('is-active');
    }
  });
}
