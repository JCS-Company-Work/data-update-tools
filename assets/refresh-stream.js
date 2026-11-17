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
    let purgeShown = false;
    let purgeFailed = false;
    let lastResourceShownAt = null;
    const groups = new Map();
    const headings = {
      "combined-data": "Combined Data",
      "florimuk": "Florim UK",
      "samples-tool": "Samples",
      "product-finder": "Product Finder",
    };

    const showResource = (resource, success) => {
      const [prefix, ...parts] = resource.split("/");
      const heading = headings[prefix] || "Other Resources";
      if (!groups.has(heading)) {
        groups.set(heading, []);
      }
      groups.get(heading).push(`${success ? "✓" : "✕"} ${parts.length ? parts.join("/") : resource}`);
      outcome.textContent = Array.from(groups, ([name, lines]) =>
        `${name}\n${lines.join("\n")}\n`
      ).join("\n");
    };

    const showPurges = (results) => {
      if (purgeShown) return;
      purgeShown = true;
      outcome.textContent += `${outcome.textContent ? "\n" : ""}Cache Purges\n`;
      if (!results || typeof results !== "object" || Object.keys(results).length === 0) {
        outcome.textContent += "✕ Cache purge results were not provided.\n";
        purgeFailed = true;
        return;
      }
      for (const [site, result] of Object.entries(results)) {
        const message = result.message || (result.success
          ? "Cache purge requested."
          : "Cache purge failed.");
        outcome.textContent += `${result.success ? "✓" : "✕"} ${site}: ${message}\n`;
        if (!result.success) purgeFailed = true;
      }
    };

    const processLine = async (line) => {
      if (!line.trim()) return;

      const event = JSON.parse(line);
      if (event.event === "resource_updated" || event.event === "resource_failed") {
        if (lastResourceShownAt !== null) {
          const delay = 200 - (Date.now() - lastResourceShownAt);
          if (delay > 0) await new Promise((resolve) => setTimeout(resolve, delay));
        }
        showResource(event.resource, event.event === "resource_updated");
        lastResourceShownAt = Date.now();
      } else if (event.event === "cache_purge") {
        showPurges(event.cache_purge);
      } else if (event.event === "failed") {
        throw new Error(event.message || "Resource refresh failed.");
      } else if (event.event === "completed" && event.success === true) {
        completed = true;
        showPurges(event.cache_purge);
        outcome.textContent = `All resources refreshed successfully.\n\n${outcome.textContent}`;
        outcome.classList.add(purgeFailed ? "failure" : "success");
      }
    };

    while (true) {
      const { value, done } = await reader.read();
      buffer += decoder.decode(value, { stream: !done });
      let newline;
      while ((newline = buffer.indexOf("\n")) !== -1) {
        await processLine(buffer.slice(0, newline));
        buffer = buffer.slice(newline + 1);
      }
      if (done) break;
    }

    await processLine(buffer);
    if (!completed) {
      throw new Error("Resource refresh ended without a completion event.");
    }
  },

  showError(outcome, message) {
    outcome.className = "outcome-message is-active failure";
    outcome.textContent += `${outcome.textContent ? "\n" : ""}Error: ${message}`;
  },
};
