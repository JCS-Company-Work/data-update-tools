const test = require("node:test");
const assert = require("node:assert/strict");

global.window = {};
require("../assets/refresh-stream.js");

function outcome() {
  const classes = [];
  return {
    className: "",
    textContent: "",
    classList: { add: (value) => classes.push(value) },
    classes,
  };
}

test("displays resources as they arrive, then each purge result", async () => {
  let controller;
  const stream = new ReadableStream({
    start(value) { controller = value; },
  });
  const response = new Response(stream, {
    headers: { "content-type": "application/x-ndjson" },
  });
  const output = outcome();
  const displaying = window.duptRefreshStream.display(response, output);
  const encoder = new TextEncoder();

  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"combined-data/allbrands"}\n'));
  await new Promise(setImmediate);
  assert.equal(output.textContent, "Updated: combined-data/allbrands\n");

  controller.enqueue(encoder.encode('{"event":"completed","success":true,"cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"},'));
  controller.enqueue(encoder.encode('"porcelain-tiles":{"success":false,"message":"HTTP 500"}}}\n'));
  controller.close();
  await displaying;

  assert.equal(output.textContent, [
    "Updated: combined-data/allbrands",
    "All resources refreshed successfully.",
    "hub: Cache purged for Hub",
    "porcelain-tiles: HTTP 500",
    "",
  ].join("\n"));
  assert.deepEqual(output.classes, ["failure"]);
});

test("preserves progress when the refresh fails", async () => {
  const response = new Response([
    '{"event":"resource_updated","resource":"one"}',
    '{"event":"failed","message":"Refresh failed"}',
  ].join("\n"), {
    headers: { "content-type": "application/x-ndjson" },
  });
  const output = outcome();

  await assert.rejects(window.duptRefreshStream.display(response, output), /Refresh failed/);
  window.duptRefreshStream.showError(output, "Refresh failed");
  assert.match(output.textContent, /Updated: one/);
  assert.match(output.textContent, /Error: Refresh failed/);
});

test("rejects a stream that ends without completion", async () => {
  const response = new Response('{"event":"resource_updated","resource":"one"}\n', {
    headers: { "content-type": "application/x-ndjson" },
  });

  await assert.rejects(
    window.duptRefreshStream.display(response, outcome()),
    /without a completion event/,
  );
});

test("warns when the API does not provide purge results", async () => {
  const response = new Response('{"event":"completed","success":true}\n', {
    headers: { "content-type": "application/x-ndjson" },
  });
  const output = outcome();

  await window.duptRefreshStream.display(response, output);

  assert.match(output.textContent, /Cache purge results were not provided/);
  assert.deepEqual(output.classes, ["failure"]);
});
