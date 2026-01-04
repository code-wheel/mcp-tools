import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { Client } from "@modelcontextprotocol/sdk/client";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";

function parseArgs(argv) {
  const options = {
    drupalRoot: "drupal",
    scope: "read",
    uid: "1",
  };

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg === "--drupal-root" && argv[i + 1]) {
      options.drupalRoot = argv[++i];
      continue;
    }
    if (arg === "--scope" && argv[i + 1]) {
      options.scope = argv[++i];
      continue;
    }
    if (arg === "--uid" && argv[i + 1]) {
      options.uid = argv[++i];
      continue;
    }
    if (arg === "-h" || arg === "--help") {
      options.help = true;
      continue;
    }
  }

  return options;
}

function usage() {
  return [
    "Usage: node scripts/mcp_js_sdk_compat.mjs [--drupal-root <path>] [--uid <uid>] [--scope <scopes>]",
    "",
    "Example:",
    "  node scripts/mcp_js_sdk_compat.mjs --drupal-root drupal --uid 1 --scope read",
  ].join("\n");
}

const options = parseArgs(process.argv.slice(2));
if (options.help) {
  process.stdout.write(usage() + "\n");
  process.exit(0);
}

const drupalRoot = path.resolve(process.cwd(), options.drupalRoot);
const drush = path.join(drupalRoot, "vendor", "bin", "drush");
if (!fs.existsSync(drush)) {
  throw new Error(`Drush not found at: ${drush}`);
}

const transport = new StdioClientTransport({
  command: drush,
  args: ["mcp-tools:serve", "--quiet", `--uid=${options.uid}`, `--scope=${options.scope}`],
  cwd: drupalRoot,
  env: { ...process.env, SYMFONY_DEPRECATIONS_HELPER: "disabled" },
  stderr: "pipe",
});

transport.stderr?.on("data", (chunk) => {
  process.stderr.write(chunk);
});

const client = new Client({
  name: "mcp_tools_js_sdk_smoke",
  version: "0.0.0",
});

await client.connect(transport);

const tools = await client.listTools();
const toolNames = new Set(tools.tools.map((t) => t.name));
if (!toolNames.has("mcp_tools_get_site_status")) {
  throw new Error("Expected tool mcp_tools_get_site_status to be available");
}

const result = await client.callTool({ name: "mcp_tools_get_site_status", arguments: {} });
if (result.isError) {
  throw new Error(`mcp_tools_get_site_status returned error: ${JSON.stringify(result)}`);
}

await client.close();

process.stdout.write(
  JSON.stringify(
    {
      ok: true,
      toolCount: tools.tools.length,
    },
    null,
    2,
  ) + "\n",
);

