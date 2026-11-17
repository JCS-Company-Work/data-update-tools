const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

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
  assert.equal(output.textContent, "Combined Data\n✓ allbrands\n");

  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"samples-tool/pt"}\n'));
  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"combined-data/allpending"}\n'));
  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"product-finder/tailormade/filter-panel"}\n'));

  const purge = '"cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"},"porcelain-tiles":{"success":false,"message":"HTTP 500"}}';
  controller.enqueue(encoder.encode(`{"event":"cache_purge",${purge}}\n`));
  controller.enqueue(encoder.encode(`{"event":"completed","success":true,${purge}}\n`));
  controller.close();
  await displaying;

  assert.equal(output.textContent, [
    "All resources refreshed successfully.",
    "",
    "Combined Data",
    "✓ allbrands",
    "✓ allpending",
    "",
    "Samples",
    "✓ pt",
    "",
    "Product Finder",
    "✓ tailormade/filter-panel",
    "",
    "Cache Purges",
    "✓ hub: Cache purged for Hub",
    "✕ porcelain-tiles: HTTP 500",
    "",
  ].join("\n"));
  assert.deepEqual(output.classes, ["failure"]);
});

test("staggered display keeps batched updates and purges in order", async () => {
  let now = 1000;
  const timers = [];
  const context = {
    window: {},
    TextDecoder,
    Date: { now: () => now },
    setTimeout: (callback, delay) => timers.push({ callback, delay }),
  };
  vm.runInNewContext(fs.readFileSync(require.resolve("../assets/refresh-stream.js"), "utf8"), context);

  const response = new Response([
    '{"event":"resource_updated","resource":"combined-data/allbrands"}',
    '{"event":"resource_updated","resource":"combined-data/allpending"}',
    '{"event":"resource_updated","resource":"samples-tool/pt"}',
    '{"event":"cache_purge","cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"}}}',
    '{"event":"completed","success":true,"cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"}}}',
  ].join("\n"), {
    headers: { "content-type": "application/x-ndjson" },
  });
  const output = outcome();
  const displaying = context.window.duptRefreshStream.display(response, output);

  await new Promise(setImmediate);
  assert.equal(output.textContent, "Combined Data\n✓ allbrands\n");
  assert.equal(timers[0].delay, 200);
  now += 200;
  timers.shift().callback();

  await new Promise(setImmediate);
  assert.equal(output.textContent, "Combined Data\n✓ allbrands\n✓ allpending\n");
  assert.equal(timers[0].delay, 200);
  now += 200;
  timers.shift().callback();
  await displaying;

  assert.match(output.textContent, /Combined Data\n✓ allbrands\n✓ allpending\n\nSamples\n✓ pt\n\nCache Purges\n✓ hub/);
});

test("does not delay updates that already arrive more than 200 ms apart", async () => {
  let now = 1000;
  const timers = [];
  const context = {
    window: {},
    TextDecoder,
    Date: { now: () => now },
    setTimeout: (callback, delay) => timers.push({ callback, delay }),
  };
  vm.runInNewContext(fs.readFileSync(require.resolve("../assets/refresh-stream.js"), "utf8"), context);

  let controller;
  const response = new Response(new ReadableStream({
    start(value) { controller = value; },
  }), {
    headers: { "content-type": "application/x-ndjson" },
  });
  const output = outcome();
  const displaying = context.window.duptRefreshStream.display(response, output);
  const encoder = new TextEncoder();
  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"combined-data/allbrands"}\n'));
  await new Promise(setImmediate);
  now += 250;
  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"combined-data/allpending"}\n'));
  await new Promise(setImmediate);

  assert.equal(output.textContent, "Combined Data\n✓ allbrands\n✓ allpending\n");
  assert.equal(timers.length, 0);
  controller.enqueue(encoder.encode('{"event":"completed","success":true,"cache_purge":{"hub":{"success":true}}}\n'));
  controller.close();
  await displaying;
});

test("preserves progress when the refresh fails", async () => {
  const response = new Response([
    '{"event":"resource_updated","resource":"combined-data/allbrands"}',
    '{"event":"resource_failed","resource":"combined-data/allpending"}',
    '{"event":"cache_purge","cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"}}}',
    '{"event":"failed","message":"Refresh failed"}',
  ].join("\n"), {
    headers: { "content-type": "application/x-ndjson" },
  });
  const output = outcome();

  await assert.rejects(window.duptRefreshStream.display(response, output), /Refresh failed/);
  window.duptRefreshStream.showError(output, "Refresh failed");
  assert.match(output.textContent, /Combined Data\n✓ allbrands\n✕ allpending/);
  assert.match(output.textContent, /Cache Purges\n✓ hub: Cache purged for Hub/);
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

test("the consolidated updater displays live progress and purge messages", async () => {
  let controller;
  const response = new Response(new ReadableStream({
    start(value) { controller = value; },
  }), {
    headers: { "content-type": "application/x-ndjson" },
  });
  let click;
  const button = {
    disabled: false,
    addEventListener: (name, handler) => { click = handler; },
  };
  const output = outcome();
  output.classList.remove = () => {};
  const spinner = { classList: { add() {}, remove() {} } };
  const context = {
    document: {
      getElementById: () => button,
      querySelector: (selector) => selector.includes("outcome-message") ? output : spinner,
    },
    dataUpdaterConfig: { ajaxUrl: "/admin-ajax.php", action: "data_updater_trigger", nonce: "test" },
    fetch: async () => response,
    URLSearchParams,
    window,
  };
  vm.runInNewContext(fs.readFileSync(require.resolve("../assets/data-updater.js"), "utf8"), context);

  const updating = click();
  const encoder = new TextEncoder();
  controller.enqueue(encoder.encode('{"event":"resource_updated","resource":"combined-data/allbrands"}\n'));
  await new Promise(setImmediate);
  assert.equal(button.disabled, true);
  assert.equal(output.textContent, "Combined Data\n✓ allbrands\n");

  controller.enqueue(encoder.encode('{"event":"completed","success":true,"cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"}}}\n'));
  controller.close();
  await updating;

  assert.match(output.textContent, /Combined Data\n✓ allbrands/);
  assert.match(output.textContent, /Cache Purges\n✓ hub: Cache purged for Hub/);
  assert.equal(button.disabled, false);
});
