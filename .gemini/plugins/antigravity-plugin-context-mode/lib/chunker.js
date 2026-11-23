/**
 * Intelligent Contextual Content Chunker for Antigravity Context Mode
 *
 * Supports semantic chunking across:
 * - Programming & scripting languages (JS, TS, Python, Java, C#, C, C++, Go, Rust, Ruby, PHP, Swift, Kotlin, Scala, Shell/PowerShell, SQL)
 * - Markdown (heading hierarchies, code blocks, tables, sections)
 * - Structured configs (JSON, YAML, TOML, INI, ENV)
 * - Markup (HTML, XML, SVG)
 * - Stylesheets (CSS, SCSS, LESS)
 * - Log files & stack traces
 * - Diffs & patches
 * - Prose & plain text
 */

export const DEFAULT_SOFT_CHUNK_BYTES = 4096;
export const DEFAULT_HARD_CHUNK_BYTES = 12288;
export const DEFAULT_SNIPPET_BYTES = 2048;

/** Map of file extensions to language/format categories */
export const EXTENSION_MAP = {
  // Markdown
  ".md": "markdown",
  ".markdown": "markdown",
  ".mdx": "markdown",

  // Python
  ".py": "python",
  ".pyw": "python",
  ".ipynb": "python",

  // JavaScript / TypeScript
  ".js": "javascript",
  ".mjs": "javascript",
  ".cjs": "javascript",
  ".jsx": "javascript",
  ".ts": "typescript",
  ".tsx": "typescript",
  ".mts": "typescript",
  ".cts": "typescript",

  // Systems / OOP languages
  ".java": "java",
  ".cs": "csharp",
  ".c": "c_cpp",
  ".cpp": "c_cpp",
  ".cc": "c_cpp",
  ".cxx": "c_cpp",
  ".h": "c_cpp",
  ".hpp": "c_cpp",
  ".hxx": "c_cpp",
  ".go": "go",
  ".rs": "rust",
  ".php": "php",
  ".phtml": "php",
  ".rb": "ruby",
  ".rake": "ruby",
  ".swift": "swift",
  ".kt": "kotlin",
  ".kts": "kotlin",
  ".scala": "scala",

  // Shell & Scripts
  ".sh": "shell",
  ".bash": "shell",
  ".zsh": "shell",
  ".ksh": "shell",
  ".ps1": "powershell",
  ".psm1": "powershell",
  ".psd1": "powershell",
  ".bat": "shell",
  ".cmd": "shell",

  // Database
  ".sql": "sql",

  // Structured Configs & Data
  ".json": "json",
  ".jsonc": "json",
  ".jsonl": "jsonl",
  ".yaml": "yaml",
  ".yml": "yaml",
  ".toml": "toml_ini",
  ".ini": "toml_ini",
  ".env": "toml_ini",
  ".properties": "toml_ini",
  ".conf": "toml_ini",
  ".cfg": "toml_ini",

  // Markup & Web
  ".html": "html_xml",
  ".htm": "html_xml",
  ".xhtml": "html_xml",
  ".xml": "html_xml",
  ".svg": "html_xml",
  ".xaml": "html_xml",
  ".plist": "html_xml",
  ".vue": "html_xml",
  ".svelte": "html_xml",

  // Stylesheets
  ".css": "css",
  ".scss": "css",
  ".less": "css",
  ".sass": "css",

  // Logs & Traces
  ".log": "log",
  ".trace": "log",
  ".out": "log",
  ".err": "log",

  // Diffs
  ".diff": "diff",
  ".patch": "diff",

  // Text
  ".txt": "text",
  ".rst": "text",
  ".text": "text"
};

/**
 * Detect language or format from file path or content heuristics
 */
export function detectFormat(filePath = "", content = "") {
  if (filePath) {
    const extMatch = filePath.toLowerCase().match(/\.[a-z0-9_-]+$/);
    if (extMatch && EXTENSION_MAP[extMatch[0]]) {
      return EXTENSION_MAP[extMatch[0]];
    }
    const baseName = filePath.split(/[/\\]/).pop().toLowerCase();
    if (baseName === "dockerfile") return "shell";
    if (baseName === "gemfile") return "ruby";
    if (baseName === "rakefile") return "ruby";
    if (baseName === "makefile") return "shell";
  }

  // Content-based heuristics
  if (typeof content === "string" && content.length > 0) {
    const head = content.slice(0, 1000).trim();
    if (head.startsWith("diff --git ") || head.startsWith("--- a/")) return "diff";
    if (/^<!DOCTYPE html/i.test(head) || /^<html/i.test(head) || /^<\?xml/i.test(head)) return "html_xml";
    if (/^(#|\s*##)\s+/.test(head)) return "markdown";
    if (
      /^\s*\[?(?:INFO|WARN|ERROR|DEBUG|FATAL|TRACE)\]?/i.test(head) ||
      /^\s*\[?\d{4}[-/.]\d{2}[-/.]\d{2}/.test(head) ||
      /^\s*\[?\d{1,2}:\d{2}:\d{2}/.test(head)
    ) {
      return "log";
    }
    if (head.startsWith("{") || head.startsWith("[")) {
      try {
        JSON.parse(content);
        return "json";
      } catch {}
    }
  }

  return "text";
}

/**
 * Main chunking entry point
 * Dispatches to format-specific semantic chunkers
 */
export function chunkContent(text, options = {}) {
  if (!text) return [];

  // Backward compatibility: if options is a number, treat as maxBytes
  const opts = typeof options === "number" ? { maxBytes: options } : (options || {});
  const softLimit = opts.maxBytes || DEFAULT_SOFT_CHUNK_BYTES;
  const hardLimit = opts.hardLimit || DEFAULT_HARD_CHUNK_BYTES;
  const filePath = opts.path || "";
  const format = opts.format || detectFormat(filePath, text);

  let rawChunks = [];
  switch (format) {
    case "markdown":
      rawChunks = chunkMarkdown(text, { softLimit, hardLimit, filePath });
      break;
    case "python":
      rawChunks = chunkPython(text, { softLimit, hardLimit, filePath });
      break;
    case "javascript":
    case "typescript":
    case "java":
    case "csharp":
    case "c_cpp":
    case "go":
    case "rust":
    case "php":
    case "swift":
    case "kotlin":
    case "scala":
      rawChunks = chunkBraceCode(text, { softLimit, hardLimit, filePath, lang: format });
      break;
    case "ruby":
      rawChunks = chunkRuby(text, { softLimit, hardLimit, filePath });
      break;
    case "shell":
    case "powershell":
      rawChunks = chunkShell(text, { softLimit, hardLimit, filePath, lang: format });
      break;
    case "sql":
      rawChunks = chunkSQL(text, { softLimit, hardLimit, filePath });
      break;
    case "json":
      rawChunks = chunkJSON(text, { softLimit, hardLimit, filePath });
      break;
    case "jsonl":
      rawChunks = chunkJSONL(text, { softLimit, hardLimit, filePath });
      break;
    case "yaml":
      rawChunks = chunkYAML(text, { softLimit, hardLimit, filePath });
      break;
    case "toml_ini":
      rawChunks = chunkTOML_INI(text, { softLimit, hardLimit, filePath });
      break;
    case "html_xml":
      rawChunks = chunkHTML_XML(text, { softLimit, hardLimit, filePath });
      break;
    case "css":
      rawChunks = chunkCSS(text, { softLimit, hardLimit, filePath });
      break;
    case "log":
      rawChunks = chunkLogs(text, { softLimit, hardLimit, filePath });
      break;
    case "diff":
      rawChunks = chunkDiff(text, { softLimit, hardLimit, filePath });
      break;
    case "text":
    default:
      rawChunks = chunkParagraphs(text, { softLimit, hardLimit, filePath });
      break;
  }

  if (!rawChunks || rawChunks.length === 0) {
    return [
      {
        text,
        startLine: 1,
        endLine: text.split("\n").length,
        context: filePath || "content",
        kind: "full"
      }
    ];
  }

  // Ensure oversized chunks are subdivided so indices remain performant
  const normalized = [];
  for (const c of rawChunks) {
    const bytes = Buffer.byteLength(c.text, "utf8");
    if (bytes > hardLimit) {
      const subChunks = splitOversizedChunk(c, hardLimit);
      normalized.push(...subChunks);
    } else {
      normalized.push(c);
    }
  }

  return normalized;
}

/**
 * Subdivide an oversized chunk while preserving context
 */
function splitOversizedChunk(chunk, limit) {
  const lines = chunk.text.split("\n");
  const result = [];
  let currentLines = [];
  let currentBytes = 0;
  let chunkStartLine = chunk.startLine;
  let part = 1;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineBytes = Buffer.byteLength(line, "utf8") + 1;

    if (currentBytes + lineBytes > limit && currentLines.length > 0) {
      result.push({
        text: currentLines.join("\n"),
        startLine: chunkStartLine,
        endLine: chunkStartLine + currentLines.length - 1,
        context: `${chunk.context} [Part ${part}]`,
        kind: chunk.kind
      });
      chunkStartLine = chunkStartLine + currentLines.length;
      currentLines = [line];
      currentBytes = lineBytes;
      part++;
    } else {
      currentLines.push(line);
      currentBytes += lineBytes;
    }
  }

  if (currentLines.length > 0) {
    result.push({
      text: currentLines.join("\n"),
      startLine: chunkStartLine,
      endLine: chunkStartLine + currentLines.length - 1,
      context: part > 1 ? `${chunk.context} [Part ${part}]` : chunk.context,
      kind: chunk.kind
    });
  }

  return result;
}

// ============================================================================
// 1. MARKDOWN CHUNKER
// ============================================================================

export function chunkMarkdown(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];

  let currentLines = [];
  let currentStartLine = 1;
  let currentBytes = 0;

  let inCodeBlock = false;
  let codeDelimiter = "";
  const headerStack = [];

  function getContext() {
    if (headerStack.length === 0) return filePath || "Markdown Document";
    return headerStack.map((h) => `${"#".repeat(h.level)} ${h.title}`).join(" > ");
  }

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: getContext(),
      kind: "markdown_section"
    });
    currentLines = [];
    currentBytes = 0;
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const lineBytes = Buffer.byteLength(line, "utf8") + 1;

    // Detect fenced code block
    const fenceMatch = line.match(/^(\s*)(```+|~~~+)/);
    if (fenceMatch) {
      const delim = fenceMatch[2];
      if (!inCodeBlock) {
        inCodeBlock = true;
        codeDelimiter = delim;
      } else if (delim.startsWith(codeDelimiter.slice(0, 3))) {
        inCodeBlock = false;
        codeDelimiter = "";
      }
    }

    // Detect markdown heading (outside code blocks)
    const headingMatch = !inCodeBlock ? line.match(/^(#{1,6})\s+(.+)$/) : null;

    if (headingMatch) {
      const level = headingMatch[1].length;
      const title = headingMatch[2].trim();

      // If we have accumulated lines, flush them before starting this new heading section
      if (currentLines.length > 0) {
        flushChunk(lineNum - 1);
      }

      // Update heading stack
      while (headerStack.length > 0 && headerStack[headerStack.length - 1].level >= level) {
        headerStack.pop();
      }
      headerStack.push({ level, title });

      currentStartLine = lineNum;
      currentLines.push(line);
      currentBytes = lineBytes;
      continue;
    }

    // If accumulated content exceeds soft limit and we hit a paragraph break outside code block
    if (currentBytes + lineBytes > softLimit && line.trim() === "" && !inCodeBlock && currentLines.length > 0) {
      currentLines.push(line);
      flushChunk(lineNum);
      currentStartLine = lineNum + 1;
      continue;
    }

    if (currentLines.length === 0) {
      currentStartLine = lineNum;
    }
    currentLines.push(line);
    currentBytes += lineBytes;
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 2. PYTHON CHUNKER
// ============================================================================

export function chunkPython(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];

  let currentUnitLines = [];
  let currentStartLine = 1;

  let pendingDocLines = [];
  let pendingDocStart = -1;

  let inDef = false;
  let defIndent = 0;
  const classStack = [];

  function currentScopeContext(fnName = "") {
    const parts = classStack.map((c) => c.name);
    if (fnName) parts.push(`def ${fnName}`);
    if (parts.length === 0) return filePath || "Python Module";
    return parts.join(" > ");
  }

  let currentContext = currentScopeContext();

  function flushUnit() {
    if (currentUnitLines.length === 0) return;
    chunks.push({
      text: currentUnitLines.join("\n"),
      startLine: currentStartLine,
      endLine: currentStartLine + currentUnitLines.length - 1,
      context: currentContext,
      kind: inDef ? "code_function" : "code_module"
    });
    currentUnitLines = [];
    currentStartLine = 1;
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();
    const indent = line.search(/\S|$/);

    // Track decorators or comments immediately preceding a def/class
    if (!inDef && (trimmed.startsWith("@") || trimmed.startsWith("#"))) {
      if (pendingDocLines.length === 0) pendingDocStart = lineNum;
      pendingDocLines.push(line);
      continue;
    }

    // Detect def or class definition
    const defMatch = trimmed.match(/^(?:async\s+)?def\s+([A-Za-z0-9_]+)\b/);
    const classMatch = trimmed.match(/^class\s+([A-Za-z0-9_]+)\b/);

    // Pop any class from classStack if indent returns to <= class.indent
    if (trimmed.length > 0 && !trimmed.startsWith("#")) {
      while (classStack.length > 0 && indent <= classStack[classStack.length - 1].indent) {
        classStack.pop();
      }
    }

    if (defMatch || classMatch) {
      if (inDef) {
        flushUnit();
        inDef = false;
      } else if (currentUnitLines.length > 0 && (classMatch || Buffer.byteLength(currentUnitLines.join("\n"), "utf8") > softLimit / 2)) {
        flushUnit();
      }

      if (classMatch) {
        classStack.push({ name: `class ${classMatch[1]}`, indent });
        currentContext = currentScopeContext();
      } else if (defMatch) {
        inDef = true;
        defIndent = indent;
        currentContext = currentScopeContext(defMatch[1]);
      }

      // Prepend any decorators or comments
      if (pendingDocLines.length > 0) {
        currentStartLine = pendingDocStart;
        currentUnitLines.push(...pendingDocLines);
        pendingDocLines = [];
        pendingDocStart = -1;
      } else {
        currentStartLine = lineNum;
      }

      currentUnitLines.push(line);
      continue;
    }

    // Check if inDef has concluded
    if (inDef && trimmed.length > 0 && indent <= defIndent && !trimmed.startsWith("#")) {
      flushUnit();
      inDef = false;
      currentContext = currentScopeContext();
    }

    if (pendingDocLines.length > 0) {
      if (currentUnitLines.length === 0) currentStartLine = pendingDocStart;
      currentUnitLines.push(...pendingDocLines);
      pendingDocLines = [];
      pendingDocStart = -1;
    }

    if (currentUnitLines.length === 0) {
      currentStartLine = lineNum;
    }
    currentUnitLines.push(line);

    // If module-level code exceeds softLimit on a blank line, flush
    if (!inDef && Buffer.byteLength(currentUnitLines.join("\n"), "utf8") > softLimit && trimmed === "") {
      flushUnit();
    }
  }

  if (pendingDocLines.length > 0) {
    if (currentUnitLines.length === 0) currentStartLine = pendingDocStart;
    currentUnitLines.push(...pendingDocLines);
  }

  flushUnit();
  return chunks;
}

const CONTROL_KEYWORDS = new Set([
  "if", "for", "while", "switch", "catch", "using", "lock", "fixed", "typeof", "sizeof", "new", "return", "throw"
]);

function extractBraceFunction(trimmed) {
  const goMatch = trimmed.match(/^func\s+(?:\([^)]+\)\s+)?([A-Za-z0-9_$]+)\s*\(/);
  if (goMatch) return goMatch[1];

  const rustMatch = trimmed.match(/^(?:pub\s+)?(?:async\s+)?fn\s+([A-Za-z0-9_$]+)/);
  if (rustMatch) return rustMatch[1];

  const arrowMatch = trimmed.match(/(?:export\s+)?(?:const|let|var)\s+([A-Za-z0-9_$]+)\s*=\s*(?:async\s*)?\(/);
  if (arrowMatch) return arrowMatch[1];

  const fnMatch = trimmed.match(/(?:export\s+)?(?:default\s+)?(?:async\s+)?function(?:\s*\*)?\s*([A-Za-z0-9_$]+)?\s*\(/);
  if (fnMatch && fnMatch[1]) return fnMatch[1];

  const methodMatch = trimmed.match(/(?:^|[>\s])([A-Za-z0-9_$]+)\s*\(/);
  if (methodMatch && !CONTROL_KEYWORDS.has(methodMatch[1])) {
    return methodMatch[1];
  }
  return null;
}

export function chunkBraceCode(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "", lang = "code" } = {}) {
  const lines = text.split("\n");
  const chunks = [];

  let currentLines = [];
  let currentStartLine = 1;
  let currentBytes = 0;

  let braceDepth = 0;
  const scopeStack = [];
  let pendingComments = [];
  let pendingCommentStart = -1;

  let inBlockComment = false;

  function currentScopeContext() {
    if (scopeStack.length === 0) return filePath || `${lang} source`;
    return scopeStack.map((s) => s.header).join(" > ");
  }

  function flushChunk(endLine, customContext = null) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: customContext || currentScopeContext(),
      kind: scopeStack.length > 0 ? "code_declaration" : "code_block"
    });
    currentLines = [];
    currentBytes = 0;
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const lineBytes = Buffer.byteLength(line, "utf8") + 1;
    const trimmed = line.trim();

    // Check block comment state
    if (!inBlockComment && trimmed.startsWith("/*")) {
      inBlockComment = true;
    }

    // Collect comments and annotations when at top level or class body level
    if (
      braceDepth <= 1 &&
      (trimmed.startsWith("//") || trimmed.startsWith("/*") || inBlockComment || trimmed.startsWith("@") || trimmed.startsWith("#"))
    ) {
      if (pendingComments.length === 0) pendingCommentStart = lineNum;
      pendingComments.push(line);
      if (inBlockComment && trimmed.includes("*/")) {
        inBlockComment = false;
      }
      continue;
    }
    if (inBlockComment) {
      if (pendingComments.length === 0) pendingCommentStart = lineNum;
      pendingComments.push(line);
      if (trimmed.includes("*/")) inBlockComment = false;
      continue;
    }

    // Detect structural declaration starts
    // Namespace
    const nsMatch = trimmed.match(/^namespace\s+([A-Za-z0-9_.]+)/);

    // Class / interface / struct / trait / enum
    const classMatch = trimmed.match(
      /(?:export\s+)?(?:default\s+)?(?:abstract\s+)?(?:public\s+|private\s+|protected\s+|internal\s+|static\s+|final\s+)*(?:class|interface|struct|enum|trait|impl)\s+([A-Za-z0-9_$]+)/
    );

    // Functions & methods
    const fnName = extractBraceFunction(trimmed);

    // If starting a method or function at braceDepth <= 1, flush previous accumulated block if it's large enough
    if ((fnName || classMatch || nsMatch) && braceDepth <= 1) {
      if (currentBytes > softLimit && currentLines.length > 0) {
        flushChunk(lineNum - 1);
      }
    }

    // Attach pending comments to start of declaration
    if (pendingComments.length > 0) {
      if (currentLines.length === 0) currentStartLine = pendingCommentStart;
      currentLines.push(...pendingComments);
      for (const pl of pendingComments) currentBytes += Buffer.byteLength(pl, "utf8") + 1;
      pendingComments = [];
      pendingCommentStart = -1;
    }

    if (currentLines.length === 0) {
      currentStartLine = lineNum;
    }
    currentLines.push(line);
    currentBytes += lineBytes;

    // Track brace changes
    // Strip string literals and line comments to avoid false brace counts
    const sanitizedLine = line
      .replace(/"(?:[^"\\]|\\.)*"/g, '""')
      .replace(/'(?:[^'\\]|\\.)*'/g, "''")
      .replace(/`(?:[^`\\]|\\.)*`/g, "``")
      .replace(/\/\/.*$/, "");

    let openBraces = 0;
    let closeBraces = 0;
    for (const char of sanitizedLine) {
      if (char === "{") openBraces++;
      else if (char === "}") closeBraces++;
    }

    // If starting a new scope
    if (nsMatch && openBraces > 0) {
      scopeStack.push({ type: "namespace", name: nsMatch[1], header: `namespace ${nsMatch[1]}`, depth: braceDepth });
    } else if (classMatch && openBraces > 0) {
      scopeStack.push({ type: "class", name: classMatch[1], header: `class ${classMatch[1]}`, depth: braceDepth });
    } else if (fnName && openBraces > 0 && (!classMatch || fnName !== classMatch[1])) {
      scopeStack.push({ type: "method", name: fnName, header: `${fnName}()`, depth: braceDepth });
    }

    braceDepth += openBraces - closeBraces;

    // If brace depth returns to or below scope base depth, close scope
    while (scopeStack.length > 0 && braceDepth <= scopeStack[scopeStack.length - 1].depth) {
      const methodContext = scopeStack.map((s) => s.header).join(" > ");
      const closed = scopeStack.pop();
      // If a method just closed and fits nicely, or current buffer is above softLimit, flush
      if (closed.type === "method") {
        flushChunk(lineNum, methodContext);
        currentStartLine = lineNum + 1;
      }
    }

    // If at top level and buffer exceeds softLimit on a blank line, flush
    if (braceDepth === 0 && currentBytes > softLimit && trimmed === "") {
      flushChunk(lineNum);
      currentStartLine = lineNum + 1;
    }
  }

  if (pendingComments.length > 0) {
    if (currentLines.length === 0) currentStartLine = pendingCommentStart;
    currentLines.push(...pendingComments);
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 4. RUBY CHUNKER
// ============================================================================

export function chunkRuby(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  const scopeStack = [];
  let lastContext = filePath || "Ruby source";

  function flushChunk(endLine, customContext = null) {
    if (currentLines.length === 0) return;
    const ctx = customContext || (scopeStack.length > 0 ? scopeStack.join(" > ") : lastContext);
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: ctx,
      kind: "ruby_block"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();

    const classMatch = trimmed.match(/^(?:class|module)\s+([A-Za-z0-9_:]+)/);
    const defMatch = trimmed.match(/^def\s+([A-Za-z0-9_.]+)/);

    if (classMatch) {
      scopeStack.push(`class ${classMatch[1]}`);
      lastContext = scopeStack.join(" > ");
    } else if (defMatch) {
      scopeStack.push(`def ${defMatch[1]}`);
      lastContext = scopeStack.join(" > ");
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);

    if (trimmed === "end" && scopeStack.length > 0) {
      const closingCtx = scopeStack.join(" > ");
      scopeStack.pop();
      if (scopeStack.length <= 1 && Buffer.byteLength(currentLines.join("\n"), "utf8") > softLimit / 2) {
        flushChunk(lineNum, closingCtx);
        currentStartLine = lineNum + 1;
      }
    }
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 5. SHELL & POWERSHELL CHUNKER
// ============================================================================

export function chunkShell(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "", lang = "shell" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentContext = filePath || `${lang} script`;

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "shell_block"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();

    // Detect function declarations
    const fnMatch = trimmed.match(/^(?:function\s+)?([A-Za-z0-9_-]+)\s*\(\s*\)\s*\{?/) ||
                    trimmed.match(/^function\s+([A-Za-z0-9_-]+)\s*\{?/);

    if (fnMatch) {
      if (currentLines.length > 0 && Buffer.byteLength(currentLines.join("\n"), "utf8") > softLimit / 2) {
        flushChunk(lineNum - 1);
        currentStartLine = lineNum;
      }
      currentContext = `function ${fnMatch[1]}`;
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);

    if (trimmed === "}" || (trimmed === "" && Buffer.byteLength(currentLines.join("\n"), "utf8") > softLimit)) {
      flushChunk(lineNum);
      currentStartLine = lineNum + 1;
      currentContext = filePath || `${lang} script`;
    }
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 6. SQL CHUNKER
// ============================================================================

export function chunkSQL(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentStmt = "";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentStmt || filePath || "SQL Query",
      kind: "sql_statement"
    });
    currentLines = [];
    currentStmt = "";
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();

    if (!currentStmt && trimmed.length > 0 && !trimmed.startsWith("--") && !trimmed.startsWith("/*")) {
      const match = trimmed.match(/^(CREATE\s+(?:OR\s+REPLACE\s+)?(?:TABLE|VIEW|PROCEDURE|FUNCTION|INDEX)|SELECT|INSERT|UPDATE|DELETE|ALTER|DROP)\s+([A-Za-z0-9_."]+)?/i);
      if (match) {
        currentStmt = `${match[1].toUpperCase()} ${match[2] || ""}`.trim();
      }
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);

    if (trimmed.endsWith(";") || trimmed.toUpperCase() === "GO") {
      if (Buffer.byteLength(currentLines.join("\n"), "utf8") >= softLimit / 2 || currentStmt) {
        flushChunk(lineNum);
        currentStartLine = lineNum + 1;
      }
    }
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 7. JSON & JSONL CHUNKER
// ============================================================================

export function chunkJSON(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  try {
    const parsed = JSON.parse(text);
    const lines = text.split("\n");

    // Top-level object: chunk by top-level keys
    if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
      const keys = Object.keys(parsed);
      if (keys.length > 1) {
        const chunks = [];
        let accumulatedObj = {};
        let accumulatedKeys = [];
        let startLine = 1;

        for (let i = 0; i < keys.length; i++) {
          const key = keys[i];
          accumulatedObj[key] = parsed[key];
          accumulatedKeys.push(key);

          const jsonText = JSON.stringify(accumulatedObj, null, 2);
          const bytes = Buffer.byteLength(jsonText, "utf8");

          if (bytes >= softLimit || i === keys.length - 1) {
            // Find approximate line range in original file
            const firstKeyRegex = new RegExp(`"${accumulatedKeys[0]}"\\s*:`);
            let foundStart = 1;
            for (let l = 0; l < lines.length; l++) {
              if (firstKeyRegex.test(lines[l])) {
                foundStart = l + 1;
                break;
              }
            }

            chunks.push({
              text: jsonText,
              startLine: foundStart,
              endLine: Math.min(lines.length, foundStart + jsonText.split("\n").length),
              context: accumulatedKeys.length === 1 ? `key: "${accumulatedKeys[0]}"` : `keys: ${accumulatedKeys.join(", ")}`,
              kind: "json_property"
            });

            accumulatedObj = {};
            accumulatedKeys = [];
          }
        }
        return chunks;
      }
    }

    // Top-level array: chunk by items
    if (Array.isArray(parsed) && parsed.length > 1) {
      const chunks = [];
      let batch = [];
      let batchStartIdx = 0;

      for (let i = 0; i < parsed.length; i++) {
        batch.push(parsed[i]);
        const jsonText = JSON.stringify(batch, null, 2);
        if (Buffer.byteLength(jsonText, "utf8") >= softLimit || i === parsed.length - 1) {
          chunks.push({
            text: jsonText,
            startLine: 1,
            endLine: lines.length,
            context: `items [${batchStartIdx}..${i}]`,
            kind: "json_array_slice"
          });
          batch = [];
          batchStartIdx = i + 1;
        }
      }
      return chunks;
    }
  } catch {}

  // Fallback if parsing fails or single small object
  return chunkParagraphs(text, { softLimit, filePath });
}

export function chunkJSONL(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentBytes = 0;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const lineBytes = Buffer.byteLength(line, "utf8") + 1;

    if (currentBytes + lineBytes > softLimit && currentLines.length > 0) {
      chunks.push({
        text: currentLines.join("\n"),
        startLine: currentStartLine,
        endLine: lineNum - 1,
        context: `${filePath || "JSONL"} (records ${currentStartLine}-${lineNum - 1})`,
        kind: "jsonl_records"
      });
      currentLines = [line];
      currentStartLine = lineNum;
      currentBytes = lineBytes;
    } else {
      if (currentLines.length === 0) currentStartLine = lineNum;
      currentLines.push(line);
      currentBytes += lineBytes;
    }
  }

  if (currentLines.length > 0) {
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine: lines.length,
      context: `${filePath || "JSONL"} (records ${currentStartLine}-${lines.length})`,
      kind: "jsonl_records"
    });
  }

  return chunks;
}

// ============================================================================
// 8. YAML CHUNKER
// ============================================================================

export function chunkYAML(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentContext = filePath || "YAML Document";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "yaml_section"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;

    // Detect document separator
    if (line.startsWith("---")) {
      flushChunk(lineNum - 1);
      currentStartLine = lineNum;
      currentContext = "YAML Document";
    }

    // Detect top-level unindented key
    const keyMatch = line.match(/^([A-Za-z0-9_.-]+):\s*(.*)$/);
    if (keyMatch) {
      if (currentLines.length > 0 && Buffer.byteLength(currentLines.join("\n"), "utf8") >= softLimit / 2) {
        flushChunk(lineNum - 1);
        currentStartLine = lineNum;
      }
      currentContext = `key: "${keyMatch[1]}"`;
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 9. TOML & INI CHUNKER
// ============================================================================

export function chunkTOML_INI(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentContext = filePath || "Config";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "config_section"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();

    // Section header [section] or [[table]]
    const sectionMatch = trimmed.match(/^\[\[?([^\]]+)\]\]?/);
    if (sectionMatch) {
      flushChunk(lineNum - 1);
      currentStartLine = lineNum;
      currentContext = `[${sectionMatch[1]}]`;
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);

    if (trimmed === "" && Buffer.byteLength(currentLines.join("\n"), "utf8") > softLimit) {
      flushChunk(lineNum);
      currentStartLine = lineNum + 1;
    }
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 10. HTML & XML CHUNKER
// ============================================================================

export function chunkHTML_XML(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentContext = filePath || "HTML/XML";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "markup_element"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();

    // Major opening tags
    const tagMatch = trimmed.match(/^<([A-Za-z0-9_-]+)(?:\s+([^>]+))?>/);
    if (tagMatch) {
      const tagName = tagMatch[1].toLowerCase();
      if (["section", "article", "header", "footer", "main", "nav", "aside", "table", "form", "script", "style", "div", "component"].includes(tagName)) {
        if (currentLines.length > 0 && Buffer.byteLength(currentLines.join("\n"), "utf8") >= softLimit / 2) {
          flushChunk(lineNum - 1);
          currentStartLine = lineNum;
        }
        currentContext = `<${tagName}>`;
      }
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);

    // Closing of major section tags
    if (/<\/(?:section|article|main|table|form|script|style)>/i.test(trimmed)) {
      if (Buffer.byteLength(currentLines.join("\n"), "utf8") >= softLimit / 2) {
        flushChunk(lineNum);
        currentStartLine = lineNum + 1;
        currentContext = filePath || "HTML/XML";
      }
    }
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 11. CSS / SCSS CHUNKER
// ============================================================================

export function chunkCSS(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentContext = filePath || "Stylesheet";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "css_rules"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const trimmed = line.trim();

    // Selector / @media header
    if (trimmed.endsWith("{") && !currentLines.length) {
      currentContext = trimmed.replace(/\{$/, "").trim();
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);

    if (trimmed === "}" && Buffer.byteLength(currentLines.join("\n"), "utf8") >= softLimit / 2) {
      flushChunk(lineNum);
      currentStartLine = lineNum + 1;
      currentContext = filePath || "Stylesheet";
    }
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 12. LOGS & TRACES CHUNKER
// ============================================================================

export function chunkLogs(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, hardLimit = DEFAULT_HARD_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentBytes = 0;
  let currentContext = filePath || "Log File";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "log_event"
    });
    currentLines = [];
    currentBytes = 0;
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const lineBytes = Buffer.byteLength(line, "utf8") + 1;

    // Detect beginning of a new log entry: timestamp, log level, or bracketed tag
    const isNewLogEntry =
      /^\d{4}[-/.]\d{2}[-/.]\d{2}/.test(line) ||
      /^\[\d{4}[-/.]\d{2}[-/.]\d{2}/.test(line) ||
      /^\[?\d{1,2}:\d{2}:\d{2}/.test(line) ||
      /^\[?(?:INFO|WARN|WARNING|ERROR|FATAL|DEBUG|TRACE|CRITICAL|SEVERE|NOTICE)\]?[:\s]/i.test(line) ||
      /^\w{3}\s+\d+\s+\d{2}:\d{2}:\d{2}/.test(line);

    // If new entry encountered and we already accumulated content
    if (isNewLogEntry && currentLines.length > 0) {
      if (currentBytes >= softLimit || currentBytes >= 2048) {
        flushChunk(lineNum - 1);
        currentStartLine = lineNum;
        currentContext = line.slice(0, 100).trim();
      }
    } else if (currentLines.length === 0 && isNewLogEntry) {
      currentContext = line.slice(0, 100).trim();
    } else if (currentBytes + lineBytes > hardLimit && currentLines.length > 0) {
      // Safety ceiling for log files without timestamps
      flushChunk(lineNum - 1);
      currentStartLine = lineNum;
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);
    currentBytes += lineBytes;
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 13. DIFFS & PATCHES CHUNKER
// ============================================================================

export function chunkDiff(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentContext = filePath || "Diff";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    if (currentLines.join("").trim().length === 0) {
      currentLines = [];
      return;
    }
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: currentContext,
      kind: "diff_hunk"
    });
    currentLines = [];
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;

    if (line.startsWith("diff --git ") || line.startsWith("--- a/")) {
      flushChunk(lineNum - 1);
      currentStartLine = lineNum;
      currentContext = line.trim();
    } else if (line.startsWith("@@ ")) {
      if (currentLines.length > 0 && Buffer.byteLength(currentLines.join("\n"), "utf8") >= softLimit / 2) {
        flushChunk(lineNum - 1);
        currentStartLine = lineNum;
      }
      currentContext = line.trim();
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// 14. PROSE & PLAIN TEXT CHUNKER (FALLBACK)
// ============================================================================

export function chunkParagraphs(text, { softLimit = DEFAULT_SOFT_CHUNK_BYTES, filePath = "" } = {}) {
  const lines = text.split("\n");
  const chunks = [];
  let currentLines = [];
  let currentStartLine = 1;
  let currentBytes = 0;
  let topicSentence = "";

  function flushChunk(endLine) {
    if (currentLines.length === 0) return;
    chunks.push({
      text: currentLines.join("\n"),
      startLine: currentStartLine,
      endLine,
      context: topicSentence || filePath || "Text Section",
      kind: "paragraph_block"
    });
    currentLines = [];
    currentBytes = 0;
    topicSentence = "";
  }

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNum = i + 1;
    const lineBytes = Buffer.byteLength(line, "utf8") + 1;
    const trimmed = line.trim();

    if (!topicSentence && trimmed.length > 0) {
      topicSentence = trimmed.slice(0, 80);
    }

    if (currentBytes + lineBytes > softLimit && trimmed === "" && currentLines.length > 0) {
      flushChunk(lineNum);
      currentStartLine = lineNum + 1;
      continue;
    }

    if (currentLines.length === 0) currentStartLine = lineNum;
    currentLines.push(line);
    currentBytes += lineBytes;
  }

  flushChunk(lines.length);
  return chunks;
}

// ============================================================================
// CONTEXTUAL SNIPPET GENERATOR
// ============================================================================

/**
 * Generate an intelligent, context-expanding search snippet.
 * If the matched chunk fits within maxBytes, returns the full chunk
 * (entire method, paragraph, or markdown section).
 * If larger, expands outward to clean line boundaries and includes
 * structural context indicators.
 */
export function generateContextSnippet({
  body = "",
  queryList = [],
  context = "",
  startLine = 1,
  endLine = 1,
  maxBytes = DEFAULT_SNIPPET_BYTES
} = {}) {
  if (!body) return "";

  const totalBytes = Buffer.byteLength(body, "utf8");

  // If the complete method, paragraph, or section fits within snippet budget,
  // return the entire method intact!
  if (totalBytes <= maxBytes) {
    return body;
  }

  // Otherwise, intelligently extract a line-aligned contextual window
  const lines = body.split("\n");
  const lowerQueries = queryList
    .filter((q) => q && typeof q === "string")
    .map((q) => q.toLowerCase());

  let bestLineIdx = -1;
  let maxHits = 0;

  for (let i = 0; i < lines.length; i++) {
    const l = lines[i].toLowerCase();
    let hits = 0;
    for (const q of lowerQueries) {
      if (l.includes(q)) hits++;
    }
    if (hits > maxHits) {
      maxHits = hits;
      bestLineIdx = i;
    }
  }

  if (bestLineIdx === -1) {
    bestLineIdx = 0;
  }

  // Expand lines outward from bestLineIdx until maxBytes is reached
  let startIdx = bestLineIdx;
  let endIdx = bestLineIdx;
  let currentBytes = Buffer.byteLength(lines[bestLineIdx], "utf8") + 1;

  let canExpandUp = true;
  let canExpandDown = true;

  while ((canExpandUp || canExpandDown) && currentBytes < maxBytes - 250) {
    if (canExpandUp && startIdx > 0) {
      const prevLineBytes = Buffer.byteLength(lines[startIdx - 1], "utf8") + 1;
      if (currentBytes + prevLineBytes <= maxBytes - 250) {
        startIdx--;
        currentBytes += prevLineBytes;
      } else {
        canExpandUp = false;
      }
    } else {
      canExpandUp = false;
    }

    if (canExpandDown && endIdx < lines.length - 1) {
      const nextLineBytes = Buffer.byteLength(lines[endIdx + 1], "utf8") + 1;
      if (currentBytes + nextLineBytes <= maxBytes - 250) {
        endIdx++;
        currentBytes += nextLineBytes;
      } else {
        canExpandDown = false;
      }
    } else {
      canExpandDown = false;
    }
  }

  const snippetLines = lines.slice(startIdx, endIdx + 1);
  const parts = [];

  const fromLine = (startLine || 1) + startIdx;
  const toLine = (startLine || 1) + endIdx;
  const ctxLabel = context ? ` [${context}]` : "";

  if (startIdx > 0) {
    parts.push(`... [${startIdx} lines above omitted; showing lines ${fromLine}-${toLine}${ctxLabel}]`);
  }
  parts.push(snippetLines.join("\n"));
  if (endIdx < lines.length - 1) {
    parts.push(`... [${lines.length - 1 - endIdx} lines below omitted]`);
  }

  return parts.join("\n");
}
