#!/usr/bin/env node
import path from "node:path";
import { fileURLToPath } from "node:url";
import { ContextModeStore } from "../lib/store.js";
import { resolveSharedSessionId } from "./pre-tool-use.js";
import { buildStatusMessage } from "./session-start.js";

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

async function main() {
  const rawInput = await readStdin();
  let payload = {};
  try {
    if (rawInput && rawInput.trim()) {
      payload = JSON.parse(rawInput);
    }
  } catch {}

  const sessionId = resolveSharedSessionId(payload);

  try {
    const store = new ContextModeStore();
    const cutoff = store.getCutoff(sessionId);
    const exemptCats = store.getExemptCategories(sessionId);
    const exemptExts = store.getExemptExtensions(sessionId);
    const customExts = store.getCustomExemptExtensions(sessionId);

    const sig = `${cutoff}:${exemptExts.slice().sort().join(",")}`;
    const lastInformed = store.getMeta(`informed_config:session:${sessionId}`);

    if (lastInformed !== sig) {
      store.setMeta(`informed_config:session:${sessionId}`, sig);
      store.close();

      const message = buildStatusMessage({
        cutoff,
        exemptCategories: exemptCats,
        exemptExtensions: exemptExts,
        customExtensions: customExts,
        isUpdate: Boolean(lastInformed)
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
      return;
    }

    store.close();
  } catch {}

  process.stdout.write(JSON.stringify({}) + "\n");
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
