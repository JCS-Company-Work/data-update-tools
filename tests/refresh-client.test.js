const test = require("node:test");
const assert = require("node:assert/strict");
const http = require("node:http");
const path = require("node:path");
const { spawn } = require("node:child_process");

test("the shared API client forwards progress before the response completes", { timeout: 10000 }, async () => {
  let responseComplete = false;
  const server = http.createServer((request, response) => {
    assert.equal(request.method, "PUT");
    response.writeHead(200, { "content-type": "application/x-ndjson" });
    response.write('{"event":"resource_updated","resource":"combined-data/');
    response.write('allbrands"}\n');
    setTimeout(() => {
      responseComplete = true;
      response.end('{"event":"completed","success":true,"cache_purge":{"hub":{"success":true,"message":"Cache purged for Hub"}}}\n');
    }, 500);
  });
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));

  const clientPath = path.resolve(__dirname, "../../../../office/shared/ResourcesApi.php");
  const php = [
    'require $argv[1];',
    '(new ResourcesApi())->refreshAllResources(function ($event) {',
    'echo json_encode($event)."\\n"; flush();',
    '});',
  ].join(" ");
  const child = spawn("php", ["-r", php, clientPath], {
    env: {
      ...process.env,
      RESOURCE_API_TOKEN: "test-token",
      XDEBUG_MODE: "off",
      http_proxy: `http://127.0.0.1:${server.address().port}`,
      NO_PROXY: "",
      no_proxy: "",
    },
  });
  let output = "";
  let errors = "";
  let progressBeforeCompletion = false;
  child.stdout.on("data", (chunk) => {
    output += chunk;
    if (output.includes("resource_updated") && !responseComplete) {
      progressBeforeCompletion = true;
    }
  });
  child.stderr.on("data", (chunk) => { errors += chunk; });

  try {
    const code = await new Promise((resolve, reject) => {
      child.on("error", reject);
      child.on("close", resolve);
    });

    assert.equal(code, 0, errors);
    assert.equal(progressBeforeCompletion, true);
    assert.deepEqual(output.trim().split("\n").map(JSON.parse), [
      { event: "resource_updated", resource: "combined-data/allbrands" },
      { event: "completed", success: true, cache_purge: { hub: { success: true, message: "Cache purged for Hub" } } },
    ]);
  } finally {
    if (child.exitCode === null) child.kill();
    await new Promise((resolve) => server.close(resolve));
  }
});
