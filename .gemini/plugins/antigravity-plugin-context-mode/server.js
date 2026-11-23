#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";
import readline from "node:readline";
import { fileURLToPath } from "node:url";
import { ContextModeStore } from "./lib/store.js";
import { createTools } from "./lib/tools.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
let pkgVersion = "1.0.0";
try {
  const pkg = JSON.parse(fs.readFileSync(path.join(__dirname, "package.json"), "utf8"));
  if (pkg.version) pkgVersion = pkg.version;
} catch {}

// Handle CLI subcommands (e.g. npx antigravity-plugin-context-mode install)
const cliArg = process.argv[2];
if (
  cliArg === "install" ||
  cliArg === "uninstall" ||
  cliArg === "--help" ||
  cliArg === "-h" ||
  cliArg === "--version" ||
  cliArg === "-v"
) {
  if (cliArg === "--version" || cliArg === "-v") {
    console.log(`antigravity-plugin-context-mode v${pkgVersion}`);
    process.exit(0);
  }

  if (cliArg === "--help" || cliArg === "-h") {
    console.log(`
Antigravity Context Mode CLI (v${pkgVersion})

Usage:
  npx antigravity-plugin-context-mode install [options]     Install plugin into Google Antigravity
  npx antigravity-plugin-context-mode uninstall [options]   Remove installed plugin
  npx antigravity-plugin-context-mode                      Start MCP stdio JSON-RPC server

Options:
  --global, -g     Install globally in ~/.gemini/config/plugins/ (default)
  --workspace, -w  Install in current workspace (.gemini/plugins/antigravity-plugin-context-mode)
`);
    process.exit(0);
  }

  const isWorkspace = process.argv.includes("--workspace") || process.argv.includes("-w");
  const homeDir = process.env.USERPROFILE || process.env.HOME || "";

  let targetDir = "";
  if (isWorkspace) {
    targetDir = path.join(process.cwd(), ".gemini", "plugins", "antigravity-plugin-context-mode");
  } else {
    targetDir = path.join(homeDir, ".gemini", "config", "plugins", "antigravity-plugin-context-mode");
  }

  if (cliArg === "uninstall") {
    if (fs.existsSync(targetDir)) {
      fs.rmSync(targetDir, { recursive: true, force: true });
      console.log(`\n✔ Antigravity Context Mode successfully removed from:`);
      console.log(`  ${targetDir}\n`);
    } else {
      console.log(`\nℹ Plugin not found at: ${targetDir}\n`);
    }
    process.exit(0);
  }

  if (cliArg === "install") {
    console.log(`\n📦 Installing Antigravity Context Mode v${pkgVersion}...`);
    try {
      fs.mkdirSync(targetDir, { recursive: true });

      const filesToCopy = [
        "plugin.json",
        "hooks.json",
        "mcp_config.json",
        "server.js",
        "package.json",
        "README.md",
        "LICENSE",
        "CHANGELOG.md"
      ];

      const dirsToCopy = ["lib", "hooks", "skills", "rules", "assets"];

      for (const file of filesToCopy) {
        const src = path.join(__dirname, file);
        const dest = path.join(targetDir, file);
        if (fs.existsSync(src)) {
          fs.copyFileSync(src, dest);
        }
      }

      for (const dir of dirsToCopy) {
        const srcDir = path.join(__dirname, dir);
        const destDir = path.join(targetDir, dir);
        if (fs.existsSync(srcDir)) {
          fs.cpSync(srcDir, destDir, { recursive: true });
        }
      }

      console.log(`\n✨ Installation complete!`);
      console.log(`📍 Location: ${targetDir}`);
      console.log(`🚀 Restart or open the Google Antigravity App to activate Context Mode.\n`);
    } catch (err) {
      console.error(`\n❌ Installation failed: ${err.message}\n`);
      process.exit(1);
    }
    process.exit(0);
  }
}

const store = new ContextModeStore();
let currentCwd = process.cwd();
let currentSessionId =
  process.env.ANTIGRAVITY_CONVERSATION_ID ||
  process.env.CONVERSATION_ID ||
  "default";

const tools = createTools({
  store,
  getCwd: () => currentCwd,
  getSessionId: () =>
    process.env.ANTIGRAVITY_ROOT_CONVERSATION_ID ||
    process.env.ANTIGRAVITY_PARENT_CONVERSATION_ID ||
    process.env.ANTIGRAVITY_CONVERSATION_ID ||
    process.env.CONVERSATION_ID ||
    currentSessionId ||
    "default"
});

const TOOL_DEFINITIONS = [
  {
    name: "ctx_execute",
    description:
      "Execute JavaScript, Python, PowerShell, or Shell code in a local sandbox to process large logs, filter data, or compute results without dumping raw output into the context window.",
    inputSchema: {
      type: "object",
      properties: {
        language: {
          type: "string",
          enum: ["javascript", "nodejs", "python", "shell", "powershell", "cmd"],
          description: "Runtime language"
        },
        code: { type: "string", description: "Source code snippet to execute" },
        file: { type: "string", description: "Optional script file path to execute" },
        args: { type: "array", items: { type: "string" }, description: "Optional command-line arguments" },
        intent: { type: "string", description: "One-line explanation of what this script analyzes" }
      }
    }
  },
  {
    name: "ctx_batch",
    description:
      "Execute multiple shell commands in batch, indexing all outputs and returning only matching search snippets to preserve tokens.",
    inputSchema: {
      type: "object",
      properties: {
        commands: { type: "array", items: { type: "string" }, description: "List of shell commands to execute" },
        queries: {
          type: "array",
          items: { type: "string" },
          description: "Search queries to filter and extract from the combined output"
        }
      },
      required: ["commands"]
    }
  },
  {
    name: "ctx_search",
    description:
      "Search the local SQLite FTS5 database across indexed logs, files, tool outputs, and documentation using BM25 + Trigram RRF. If path is a file, directory, or URL, it is automatically indexed Just-In-Time if unindexed or modified.",
    inputSchema: {
      type: "object",
      properties: {
        queries: { type: "array", items: { type: "string" }, description: "Search queries or tokens to match" },
        path: {
          type: "string",
          description: "Path to a file, directory, or URL to search (auto-indexed Just-In-Time if unindexed or changed). Omit to search active session."
        },
        scope: {
          type: "string",
          enum: ["session", "worktree", "all"],
          description: "Search scope when path is omitted (default: session)"
        },
        limit: { type: "integer", description: "Max number of snippets to return (default 5)" },
        offset: { type: "integer", description: "Offset for pagination" },
        reverse: {
          type: "boolean",
          description: "Search in reverse order (tail-first), prioritizing the newest lines/entries at the end of log files (default: false)"
        }
      },
      required: ["queries"]
    }
  },
  {
    name: "ctx_resume",
    description: "Retrieve current session state, active sources, indexed items, and compaction pointers.",
    inputSchema: {
      type: "object",
      properties: {
        seq: { type: "integer", description: "Optional journal event sequence to retrieve" }
      }
    }
  },
  {
    name: "ctx_stats",
    description:
      "Show context window savings report (raw bytes vs tokens returned to LLM, savings percentage, and database metrics).",
    inputSchema: {
      type: "object",
      properties: {
        reset: { type: "boolean", description: "Reset session statistics" }
      }
    }
  },
  {
    name: "ctx_doctor",
    description: "Check database health, FTS5 availability, SQLite schema, and active sandbox runtimes.",
    inputSchema: {
      type: "object",
      properties: {}
    }
  },
  {
    name: "ctx_purge",
    description: "Purge indexed sources and journal records for this session, worktree, or entire database.",
    inputSchema: {
      type: "object",
      properties: {
        scope: { type: "string", enum: ["session", "worktree", "all"], description: "Purge scope" }
      }
    }
  }
];

function sendJsonRpc(obj) {
  process.stdout.write(JSON.stringify(obj) + "\n");
}

function sendResponse(id, result) {
  sendJsonRpc({
    jsonrpc: "2.0",
    id,
    result
  });
}

function sendError(id, code, message, data) {
  sendJsonRpc({
    jsonrpc: "2.0",
    id,
    error: {
      code,
      message,
      ...(data !== undefined ? { data } : {})
    }
  });
}

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout,
  terminal: false
});

rl.on("line", async (line) => {
  const trimmed = line.trim();
  if (!trimmed) return;

  let msg;
  try {
    msg = JSON.parse(trimmed);
  } catch (err) {
    sendError(null, -32700, "Parse error", String(err));
    return;
  }

  const { id, method, params } = msg;

  // Notifications (no id)
  if (id === undefined || id === null) {
    if (method === "notifications/initialized") {
      // client initialized confirmation
    }
    return;
  }

  // Handle requests
  try {
    switch (method) {
      case "initialize": {
        if (params?.rootPath) currentCwd = params.rootPath;
        if (params?.sessionId) currentSessionId = params.sessionId;
        sendResponse(id, {
          protocolVersion: "2024-11-05",
          capabilities: {
            tools: {}
          },
          serverInfo: {
            name: "antigravity-plugin-context-mode",
            version: pkgVersion
          }
        });
        break;
      }

      case "ping": {
        sendResponse(id, {});
        break;
      }

      case "tools/list": {
        sendResponse(id, {
          tools: TOOL_DEFINITIONS
        });
        break;
      }

      case "tools/call": {
        const toolName = params?.name;
        const toolArgs = params?.arguments || {};
        const callSessionId =
          toolArgs.sessionId ||
          params?._meta?.conversationId ||
          params?._meta?.sessionId ||
          params?.sessionId;
        if (callSessionId) {
          currentSessionId = callSessionId;
        }

        if (!tools[toolName]) {
          sendError(id, -32601, `Unknown tool: ${toolName}`);
          break;
        }

        try {
          const result = await tools[toolName](toolArgs);
          sendResponse(id, {
            content: [
              {
                type: "text",
                text: typeof result === "string" ? result : JSON.stringify(result, null, 2)
              }
            ],
            isError: false
          });
        } catch (toolErr) {
          sendResponse(id, {
            content: [
              {
                type: "text",
                text: `Tool error (${toolName}): ${toolErr.message || String(toolErr)}`
              }
            ],
            isError: true
          });
        }
        break;
      }

      default: {
        sendError(id, -32601, `Method not found: ${method}`);
        break;
      }
    }
  } catch (handlerErr) {
    sendError(id, -32603, "Internal server error", String(handlerErr));
  }
});

process.on("SIGINT", () => {
  store.close();
  process.exit(0);
});

process.on("SIGTERM", () => {
  store.close();
  process.exit(0);
});
