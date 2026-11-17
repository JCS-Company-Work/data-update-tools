window.duptRefreshStream = {
  async display(response, outcome) {
    const contentType = response.headers.get("content-type") || "";
    if (!response.ok || !contentType.includes("application/x-ndjson")) {
      if (contentType.includes("application/json")) {
        const result = await response.json();
        throw new Error(result.data?.message || "Unable to refresh resources.");
      }
      throw new Error(`Unable to refresh resources (HTTP ${response.status}).`);
    }

    if (!response.body) {
      throw new Error("The refresh response cannot be streamed.");
    }

    outcome.className = "outcome-message is-active";
    outcome.textContent = "";
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = "";
    let completed = false;

    const processLine = (line) => {
      if (!line.trim()) return;

      const event = JSON.parse(line);
      if (event.event === "resource_updated") {
        outcome.textContent += `Updated: ${event.resource}\n`;
      } else if (event.event === "failed") {
        throw new Error(event.message || "Resource refresh failed.");
      } else if (event.event === "completed" && event.success === true) {
        completed = true;
        outcome.textContent += "All resources refreshed successfully.\n";
        let purgeFailed = false;
        if (!event.cache_purge || typeof event.cache_purge !== "object") {
          outcome.textContent += "Cache purge results were not provided.\n";
          purgeFailed = true;
        }
        for (const [site, result] of Object.entries(event.cache_purge || {})) {
          const message = result.message || (result.success
            ? "Cache purge requested."
            : "Cache purge failed.");
          outcome.textContent += `${site}: ${message}\n`;
          if (!result.success) purgeFailed = true;
        }
        outcome.classList.add(purgeFailed ? "failure" : "success");
      }
    };

    while (true) {
      const { value, done } = await reader.read();
      buffer += decoder.decode(value, { stream: !done });
      let newline;
      while ((newline = buffer.indexOf("\n")) !== -1) {
        processLine(buffer.slice(0, newline));
        buffer = buffer.slice(newline + 1);
      }
      if (done) break;
    }

    processLine(buffer);
    if (!completed) {
      throw new Error("Resource refresh ended without a completion event.");
    }
  },

  showError(outcome, message) {
    outcome.className = "outcome-message is-active failure";
    outcome.textContent += `${outcome.textContent ? "\n" : ""}Error: ${message}`;
  },
};
