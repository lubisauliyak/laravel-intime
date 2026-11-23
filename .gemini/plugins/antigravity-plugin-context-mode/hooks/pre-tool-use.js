#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { ContextModeStore, DEFAULT_MAX_AUTO_INDEX_BYTES, resolveFilePath } from "../lib/store.js";

export const DEFAULT_MAX_READ_BYTES = 8192; // 8 KB default cutoff
export const DEFAULT_MAX_LINE_RANGE = 200; // 200 lines max un-indexed slice

export function getMaxAutoIndexBytes(sessionId = null) {
  try {
    const store = new ContextModeStore();
    const limit = store.getMaxAutoIndexBytes(sessionId);
    store.close();
    return limit;
  } catch {
    const envVal = process.env.CONTEXT_MODE_MAX_AUTO_INDEX_BYTES;
    if (envVal) {
      const parsed = parseInt(envVal, 10);
      if (!Number.isNaN(parsed)) return parsed;
    }
    return DEFAULT_MAX_AUTO_INDEX_BYTES;
  }
}

function isProtectionPaused(sessionId = null) {
  try {
    const store = new ContextModeStore();
    const paused = store.isPaused(sessionId);
    store.close();
    return paused;
  } catch {
    return false;
  }
}

export function getMaxReadBytes(sessionId = null) {
  try {
    const store = new ContextModeStore();
    const cutoff = store.getCutoff(sessionId);
    store.close();
    return cutoff;
  } catch {
    const envVal = process.env.CONTEXT_MODE_MAX_READ_BYTES;
    if (envVal) {
      const parsed = parseInt(envVal, 10);
      if (!Number.isNaN(parsed)) return parsed;
    }
    return DEFAULT_MAX_READ_BYTES;
  }
}

export function getExemptExtensions(sessionId = null) {
  try {
    const store = new ContextModeStore();
    const exts = store.getExemptExtensions(sessionId);
    store.close();
    return exts;
  } catch {
    return Array.from(BINARY_EXTENSIONS);
  }
}

export function getActiveExemptSummary(sessionId = null) {
  try {
    const store = new ContextModeStore();
    const summary = store.getExemptSummary(sessionId);
    store.close();
    return summary;
  } catch {
    return "prompt attachments in .user_uploaded/";
  }
}

export function getMaxLineRange(maxReadBytes = DEFAULT_MAX_READ_BYTES) {
  const envVal = process.env.CONTEXT_MODE_MAX_LINE_RANGE;
  if (envVal) {
    const parsed = parseInt(envVal, 10);
    if (!Number.isNaN(parsed) && parsed > 0) return parsed;
  }
  if (maxReadBytes === 0) return 0;
  return Math.max(50, Math.round(maxReadBytes / 40));
}

export function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) {
    const kb = bytes / 1024;
    return `${Number.isInteger(kb) ? kb : kb.toFixed(1)} KB`;
  }
  const mb = bytes / (1024 * 1024);
  return `${Number.isInteger(mb) ? mb : mb.toFixed(2)} MB`;
}

export function cleanStringArg(val) {
  if (val === undefined || val === null) return "";
  let str = String(val).trim();
  while (
    (str.startsWith('"') && str.endsWith('"') && str.length >= 2) ||
    (str.startsWith("'") && str.endsWith("'") && str.length >= 2)
  ) {
    str = str.slice(1, -1).trim();
  }
  return str;
}

export function cleanFilePath(val) {
  let p = cleanStringArg(val);
  if (!p) return "";
  // Strip file URI prefix
  if (p.startsWith("file:///")) {
    p = p.slice(8);
  } else if (p.startsWith("file://")) {
    p = p.slice(7);
  }
  try {
    p = decodeURIComponent(p);
  } catch {}
  // Strip trailing line anchor (e.g. #L1-L20)
  p = p.replace(/#L\d+(-L\d+)?$/i, "");
  // Normalize Windows slashes
  return path.normalize(p.trim());
}

export function parseLineArg(val) {
  if (val === undefined || val === null) return null;
  const str = cleanStringArg(val);
  if (!str || str.toLowerCase() === "null" || str.toLowerCase() === "undefined") {
    return null;
  }
  const parsed = parseInt(str, 10);
  return Number.isNaN(parsed) ? null : parsed;
}

const KNOWN_TEXT_EXTENSIONS = new Set([
  // Markdown & Documentation
  ".md", ".markdown", ".mdown", ".mkd", ".rst", ".adoc",
  // Code
  ".cs", ".csx",
  ".js", ".jsx", ".mjs", ".cjs",
  ".ts", ".tsx", ".mts", ".cts",
  ".py", ".pyw",
  ".c", ".h", ".cpp", ".hpp", ".cc", ".cxx", ".hh",
  ".rs", ".go", ".java", ".kt", ".kts", ".scala",
  ".rb", ".php", ".sh", ".bash", ".ps1", ".psm1", ".psd1",
  ".bat", ".cmd",
  // Web & Config
  ".html", ".htm", ".xhtml", ".css", ".scss", ".sass", ".less",
  ".json", ".jsonc", ".json5",
  ".xml", ".yaml", ".yml", ".toml", ".ini", ".cfg", ".conf",
  ".sql", ".graphql", ".gql",
  ".diff", ".patch", ".txt", ".text", ".log", ".csv", ".tsv"
]);

const BINARY_EXTENSIONS = new Set([
  // Documents & Office
  ".pdf", ".docx", ".doc", ".xlsx", ".xls", ".pptx", ".ppt",
  // Images
  ".png", ".jpg", ".jpeg", ".gif", ".webp", ".bmp", ".ico", ".tiff", ".tif",
  // Audio & Video
  ".mp3", ".wav", ".ogg", ".m4a", ".flac", ".aac", ".mp4", ".webm", ".mkv", ".avi", ".mov",
  // Archives & Executables
  ".zip", ".tar", ".gz", ".7z", ".rar", ".exe", ".dll", ".so", ".dylib", ".bin",
  // 3D & Models
  ".blend", ".fbx", ".obj", ".gltf", ".glb", ".wasm"
]);

function isUserAttachedFile(filePath, absPath) {
  const normalized = (absPath || filePath || "").replace(/\\/g, "/");
  return (
    normalized.includes("/.user_uploaded/") ||
    normalized.includes("/user_uploaded/") ||
    normalized.includes("/.system_generated/uploads/")
  );
}

function isBinaryFile(absPath, ext) {
  const lowerExt = (ext || "").toLowerCase();
  if (KNOWN_TEXT_EXTENSIONS.has(lowerExt)) {
    return false;
  }
  if (BINARY_EXTENSIONS.has(lowerExt)) {
    return true;
  }
  try {
    const fd = fs.openSync(absPath, "r");
    const buf = Buffer.alloc(512);
    const bytesRead = fs.readSync(fd, buf, 0, 512, 0);
    fs.closeSync(fd);
    if (bytesRead === 0) return false;

    // Check for UTF-16 LE BOM (FF FE) or BE BOM (FE FF)
    if (bytesRead >= 2 && ((buf[0] === 0xff && buf[1] === 0xfe) || (buf[0] === 0xfe && buf[1] === 0xff))) {
      return false; // Text file with UTF-16 BOM
    }
    // Check for UTF-8 BOM (EF BB BF)
    if (bytesRead >= 3 && buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf) {
      return false;
    }

    // Check for PDF magic header (%PDF)
    if (bytesRead >= 4 && buf[0] === 0x25 && buf[1] === 0x50 && buf[2] === 0x44 && buf[3] === 0x46) {
      return true;
    }

    // Check for null bytes indicating binary content
    for (let i = 0; i < bytesRead; i++) {
      if (buf[i] === 0) return true;
    }
  } catch {
    // ignore inspection error
  }
  return false;
}

export function resolveSharedSessionId(payload = {}) {
  return (
    payload.rootConversationId ||
    payload.root_conversation_id ||
    payload.parentConversationId ||
    payload.parent_conversation_id ||
    process.env.ANTIGRAVITY_ROOT_CONVERSATION_ID ||
    process.env.ANTIGRAVITY_PARENT_CONVERSATION_ID ||
    process.env.ANTIGRAVITY_CONVERSATION_ID ||
    payload.conversationId ||
    payload.conversation_id ||
    payload.sessionId ||
    payload.session_id ||
    "default"
  );
}

export function resolveAgentName(payload = {}) {
  return (
    payload.agent ||
    payload.agentName ||
    payload.agent_name ||
    payload.agentType ||
    payload.agent_type ||
    payload.role ||
    (payload.subagent ? "subagent" : "antigravity")
  );
}

function recordInterception({ sessionId, agent, cwd, toolName, path: targetPath, size, reason }) {
  try {
    const store = new ContextModeStore();
    store.recordEvent({
      sessionId: sessionId || "default",
      worktree: cwd || process.cwd(),
      agent: agent || "antigravity",
      kind: "interception",
      tool: toolName,
      key: "blocked",
      path: targetPath || null,
      ok: 0,
      exitCode: 1,
      bytes: size || 0,
      body: reason,
      sandboxBytes: size || 0,
      returnedBytes: 0
    });
    store.close();
  } catch {
    // Non-blocking: database recording failure must never crash the hook
  }
}

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

// Kept for backward compatibility
export const UNBOUNDED_COMMAND_PATTERNS = [
  /^\s*(cat|type|Get-Content|gc)\b/i,
  /^\s*git\s+log\b/i,
  /^\s*(curl|wget)\b/i
];

/**
 * Dynamically inspects shell commands against active SQLite cutoff and user exemptions
 * without relying on a static hardcoded file extension whitelist.
 */
export function inspectCommand({ commandLine, cwd, sessionId }) {
  if (!commandLine || typeof commandLine !== "string") {
    return { shouldDeny: false };
  }

  const cleanCmd = cleanStringArg(commandLine);
  const maxReadBytes = getMaxReadBytes(sessionId);
  const maxLineRange = getMaxLineRange(maxReadBytes);
  const exemptExtensions = getExemptExtensions(sessionId);

  // 1. Git log without count limiter
  if (/^\s*git\s+log\b/i.test(cleanCmd)) {
    const isLimited = /(-n\s+\d+|-\d+|--max-count|\s*\|\s*(head|Select-Object|select|more))/i.test(cleanCmd);
    if (!isLimited) {
      return {
        shouldDeny: true,
        reason:
          `[context-mode protection] 'git log' without a count limiter risks dumping thousands of commits into context. ` +
          `Please specify a count (e.g. 'git log -n 10') or run inside 'ctx_execute'.`,
        size: 32768,
        targetPath: "git log"
      };
    }
  }

  // 2. Direct curl / wget to stdout without file destination or limiter
  if (/^\s*(curl(\.exe)?|wget(\.exe)?)\b/i.test(cleanCmd)) {
    const writesToFile = /(-o\s+|-O\s+|--output\s+|>)/i.test(cleanCmd);
    const hasLimiter = /\|\s*(head|Select-Object|select|more)/i.test(cleanCmd);
    if (!writesToFile && !hasLimiter) {
      // Allow if downloading a file with an exempt extension (e.g. images, media, office docs)
      const urlMatch = cleanCmd.match(/https?:\/\/[^\s"']+/i);
      if (urlMatch) {
        try {
          const parsedUrl = new URL(urlMatch[0]);
          const ext = path.extname(parsedUrl.pathname).toLowerCase();
          if (ext && exemptExtensions.includes(ext)) {
            return { shouldDeny: false };
          }
        } catch {}
      }

      return {
        shouldDeny: true,
        reason:
          `[context-mode protection] Direct curl/wget download to stdout risks flooding the context window. ` +
          `Please use 'ctx_execute', save to disk ('-o <file>'), or limit output (e.g. '| head -n <lines>').`,
        size: 32768,
        targetPath: urlMatch ? urlMatch[0] : cleanCmd
      };
    }
  }

  // 3. Raw file dump utilities (cat, type, Get-Content, gc)
  const printMatch = cleanCmd.match(/^\s*(cat|type|Get-Content|gc)(\.exe)?\b/i);
  if (printMatch) {
    // Allow if command includes pagination flags or piping to limiters / file sinks
    const hasLimiter =
      /(-Head|-TotalCount|-First|-Tail)\s+\d+/i.test(cleanCmd) ||
      /\|\s*(head|Select-Object|select\s+(-[a-zA-Z]+\s+)*-First|more|Out-Null|Set-Content|Out-File)/i.test(cleanCmd) ||
      />\s*[^>]/i.test(cleanCmd);

    if (hasLimiter) {
      return { shouldDeny: false };
    }

    // Extract target argument
    const argMatch = cleanCmd.match(/(?:cat|type|Get-Content|gc)(?:\.exe)?(?:\s+(?:-Path|-LiteralPath))?\s+["']?([^"'\r\n;|]+)["']?/i);
    const rawTarget = argMatch && argMatch[1] ? argMatch[1].trim() : "";

    // Wildcard dump (e.g. cat *.log, Get-Content *)
    if (rawTarget.includes("*") || rawTarget.includes("?")) {
      return {
        shouldDeny: true,
        reason:
          `[context-mode protection] Wildcard file dump ('${rawTarget}') risks flooding the context window. ` +
          `Please use 'ctx_execute' or limit output to only the needed lines (<= ${maxLineRange} lines, e.g. '| Select-Object -First <lines>').`,
        size: 32768,
        targetPath: rawTarget
      };
    }

    if (rawTarget) {
      const cleanTarget = cleanFilePath(rawTarget);
      const absPath = path.isAbsolute(cleanTarget) ? cleanTarget : path.resolve(cwd, cleanTarget);

      try {
        if (fs.existsSync(absPath)) {
          const ext = path.extname(absPath).toLowerCase();
          // Dynamic exemption check from SQLite/Dashboard configuration
          if (ext && exemptExtensions.includes(ext)) {
            return { shouldDeny: false };
          }

          const stat = fs.statSync(absPath);
          // Dynamic cutoff check against active setting
          if (maxReadBytes > 0 && stat.size > maxReadBytes) {
            const maxAutoIndexBytes = getMaxAutoIndexBytes(sessionId);
            const targetPath = resolveFilePath(absPath, cwd) || cleanTarget;

            let isAutoIndexed = false;
            if (maxAutoIndexBytes > 0 && stat.size <= maxAutoIndexBytes) {
              try {
                const store = new ContextModeStore();
                const content = fs.readFileSync(absPath, "utf8");
                store.indexContent({
                  sessionId,
                  worktree: cwd,
                  agent: "antigravity",
                  kind: "file",
                  label: targetPath,
                  path: targetPath,
                  content,
                  mtime: Math.round(stat.mtimeMs)
                });
                store.close();
                isAutoIndexed = true;
              } catch {}
            }

            let reason = "";
            if (isAutoIndexed) {
              reason =
                `[context-mode protection: auto-indexed] Command attempts an un-paged dump of '${cleanTarget}' (${formatBytes(stat.size)}), ` +
                `exceeding the active ${formatBytes(maxReadBytes)} context limit. ` +
                `The file has been automatically indexed into Context Mode. ` +
                `Query it with 'ctx_search(queries: [...], path: "${targetPath}")' or limit output to only needed lines (<= ${maxLineRange} lines, e.g. -Head <lines> or '| Select-Object -First <lines>').`;
            } else {
              reason =
                `[context-mode protection] Command attempts an un-paged dump of '${cleanTarget}' (${formatBytes(stat.size)}), ` +
                `exceeding the active ${formatBytes(maxReadBytes)} context limit. ` +
                `Please use 'ctx_execute' or limit output to only needed lines (<= ${maxLineRange} lines, e.g. -Head <lines> or '| Select-Object -First <lines>').`;
            }

            return {
              shouldDeny: true,
              reason,
              size: stat.size,
              targetPath
            };
          }

          // File is small and within cutoff
          return { shouldDeny: false };
        }
      } catch {}

      // If file doesn't exist on disk or path cannot be resolved, block unpaged dump defensively
      return {
        shouldDeny: true,
        reason:
          `[context-mode protection] Command matches an un-paged file dump ('${rawTarget}') that could flood the context window. ` +
          `Please use 'ctx_execute' or limit output to only needed lines (<= ${maxLineRange} lines, e.g. '| Select-Object -First <lines>' or '| head -n <lines>').`,
        size: 32768,
        targetPath: cleanTarget || rawTarget
      };
    }
  }

  return { shouldDeny: false };
}

async function main() {
  const rawInput = await readStdin();
  if (!rawInput.trim()) {
    process.stdout.write(JSON.stringify({ decision: "allow" }) + "\n");
    return;
  }

  let payload;
  try {
    payload = JSON.parse(rawInput);
  } catch {
    process.stdout.write(JSON.stringify({ decision: "allow" }) + "\n");
    return;
  }

  const toolCall = payload.toolCall || {};
  const toolName = toolCall.name || "";
  const args = toolCall.args || {};
  const cwd = payload.cwd || process.cwd();
  const sessionId = resolveSharedSessionId(payload);
  const agentName = resolveAgentName(payload);

  // If Context Mode protection is paused, allow all tool calls through without interception
  if (isProtectionPaused(sessionId)) {
    process.stdout.write(JSON.stringify({ decision: "allow" }) + "\n");
    process.exit(0);
  }

  // 1. Guard read_file / view_file
  if (toolName === "read_file" || toolName === "view_file") {
    const rawFilePath = args.Path || args.path || args.filePath || "";
    const filePath = cleanFilePath(rawFilePath);
    const startLine = parseLineArg(args.StartLine ?? args.startLine) ?? 1;
    const endLine = parseLineArg(args.EndLine ?? args.endLine);
    const maxReadBytes = getMaxReadBytes(sessionId);
    const maxLineRange = getMaxLineRange(maxReadBytes);

    if (filePath) {
      const absPath = path.isAbsolute(filePath) ? filePath : path.resolve(cwd, filePath);
      try {
        if (fs.existsSync(absPath)) {
          const ext = path.extname(absPath).toLowerCase();
          const isUserAttachment = isUserAttachedFile(rawFilePath, absPath);
          const exemptExtensions = getExemptExtensions(sessionId);
          const isExemptExt = exemptExtensions.includes(ext);
          const isBinary = isBinaryFile(absPath, ext);

          // Prompt attachments (drag-and-drop / user-uploaded), exempt extensions, or binary files bypass cutoff
          if (isUserAttachment || isExemptExt || isBinary) {
            process.stdout.write(JSON.stringify({ decision: "allow" }) + "\n");
            return;
          }

          const stat = fs.statSync(absPath);
          const isLargeFile = maxReadBytes > 0 && stat.size > maxReadBytes;
          const isWideRange = maxLineRange > 0 && (endLine === null || (endLine - startLine) > maxLineRange);

          if (isLargeFile && isWideRange) {
            const lineInstruction = maxLineRange > 0 ? ` or read only the needed lines with StartLine/EndLine (<= ${maxLineRange} lines).` : "";
            const maxAutoIndexBytes = getMaxAutoIndexBytes(sessionId);
            const targetPath = resolveFilePath(absPath, cwd) || filePath;

            let isAutoIndexed = false;
            if (maxAutoIndexBytes > 0 && stat.size <= maxAutoIndexBytes) {
              try {
                const store = new ContextModeStore();
                const content = fs.readFileSync(absPath, "utf8");
                store.indexContent({
                  sessionId,
                  worktree: cwd,
                  agent: agentName,
                  kind: "file",
                  label: targetPath,
                  path: targetPath,
                  content,
                  mtime: Math.round(stat.mtimeMs)
                });
                store.close();
                isAutoIndexed = true;
              } catch {}
            }

            let reason = "";
            if (isAutoIndexed) {
              reason =
                `[context-mode protection: auto-indexed] File '${targetPath}' (${formatBytes(stat.size)}) ` +
                `exceeded the active ${formatBytes(maxReadBytes)} context limit. ` +
                `The file has been automatically indexed into Context Mode. ` +
                `Query it immediately using 'ctx_search(queries: [...], path: "${targetPath}")'${lineInstruction}`;
            } else {
              const ceilingNote = maxAutoIndexBytes > 0 && stat.size > maxAutoIndexBytes
                ? ` and the ${formatBytes(maxAutoIndexBytes)} auto-indexing ceiling`
                : "";
              reason =
                `[context-mode protection] File is ${formatBytes(stat.size)}, exceeding the active ${formatBytes(maxReadBytes)} context limit${ceilingNote}. ` +
                `Please use 'ctx_execute'${lineInstruction}`;
            }

            // Record interception event in SQLite store so dashboard telemetry updates immediately
            recordInterception({
              sessionId,
              agent: agentName,
              cwd,
              toolName,
              path: targetPath,
              size: stat.size,
              reason
            });

            process.stdout.write(
              JSON.stringify({
                decision: "deny",
                reason
              }) + "\n"
            );
            process.exit(0);
          }
        }
      } catch {
        // ignore stat error, allow tool to report error
      }
    }
  }

  // 2. Guard run_command
  if (toolName === "run_command") {
    const rawCommandLine = args.CommandLine || args.commandLine || args.command || "";
    const inspection = inspectCommand({
      commandLine: rawCommandLine,
      cwd,
      sessionId
    });

    if (inspection.shouldDeny) {
      recordInterception({
        sessionId,
        agent: agentName,
        cwd,
        toolName,
        path: inspection.targetPath || cleanStringArg(rawCommandLine),
        size: inspection.size || 32768,
        reason: inspection.reason
      });

      process.stdout.write(
        JSON.stringify({
          decision: "deny",
          reason: inspection.reason
        }) + "\n"
      );
      process.exit(0);
    }
  }

  // Default allow
  process.stdout.write(JSON.stringify({ decision: "allow" }) + "\n");
  process.exit(0);
}

const isMain = Boolean(
  process.argv[1] &&
    path.resolve(fileURLToPath(import.meta.url)).toLowerCase() ===
      path.resolve(process.argv[1]).toLowerCase()
);

if (isMain) {
  main().catch(() => {
    process.stdout.write(JSON.stringify({ decision: "allow" }) + "\n");
    process.exit(0);
  });
}
