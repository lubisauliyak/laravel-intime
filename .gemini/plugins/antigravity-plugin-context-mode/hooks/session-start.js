#!/usr/bin/env node
import path from "node:path";
import { fileURLToPath } from "node:url";
import { ContextModeStore, formatExemptSummary } from "../lib/store.js";
import { resolveSharedSessionId, formatBytes, getMaxLineRange } from "./pre-tool-use.js";

function readStdin() {
  return new Promise((resolve) => {
    let data = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      data += chunk;
    });
    process.stdin.on("end", () => {
      resolve(data);
    });
  });
}

export function buildStatusMessage({
  cutoff = 8192,
  exemptCategories = [],
  exemptExtensions = [],
  customExtensions = [],
  isUpdate = false
} = {}) {
  const cutoffFormatted =
    cutoff === 0
      ? "Disabled (unlimited)"
      : `${formatBytes(cutoff)} (${cutoff.toLocaleString()} bytes)`;

  const exemptSummary = formatExemptSummary({
    activeCats: exemptCategories,
    customs: customExtensions
  });

  const header = isUpdate
    ? "[context-mode config update]"
    : "[context-mode active]";

  const lines = [
    header,
    `• Read cutoff: ${cutoffFormatted}`,
    `• Exempt file types: ${exemptSummary || "None"}`
  ];

  return lines.join("\n");
}

async function main() {
  const rawInput = await readStdin();
  let payload = {};
  try {
    if (rawInput && rawInput.trim()) {
      payload = JSON.parse(rawInput);
    }
  } catch {}

  const sessionId = resolveSharedSessionId(payload);
  let cutoff = 8192;
  let exemptCats = [];
  let exemptExts = [];
  let customExts = [];

  try {
    const store = new ContextModeStore();
    store.clearSessionOverrides();
    cutoff = store.getCutoff(sessionId);
    exemptCats = store.getExemptCategories(sessionId);
    exemptExts = store.getExemptExtensions(sessionId);
    customExts = store.getCustomExemptExtensions(sessionId);

    // Save informed configuration signature for this session so PreInvocation stays in sync
    const sig = `${cutoff}:${exemptExts.slice().sort().join(",")}`;
    store.setMeta(`informed_config:session:${sessionId}`, sig);
    store.close();
  } catch {}

  const message = buildStatusMessage({
    cutoff,
    exemptCategories: exemptCats,
    exemptExtensions: exemptExts,
    customExtensions: customExts,
    isUpdate: false
  });

  const response = {
    injectSteps: [
      {
        systemMessage: {
          systemMessage: message
        }
      }
    ]
  };

  process.stdout.write(JSON.stringify(response) + "\n");
}

const isMain = Boolean(
  process.argv[1] &&
    path.resolve(fileURLToPath(import.meta.url)).toLowerCase() ===
      path.resolve(process.argv[1]).toLowerCase()
);

if (isMain) {
  main().catch(() => {
    process.stdout.write(JSON.stringify({}) + "\n");
  });
}
