---
name: context-mode
description: Protects the Antigravity agent context window by keeping massive tool outputs, logs, web pages, and bulk datasets out of context. Uses local SQLite FTS5 search (Porter BM25 + Trigram RRF) and sandboxed code execution (Thinking in Code). Use when analyzing large logs, inspecting multi-thousand line files, running bulk commands, or analyzing API data.
---

# Antigravity Context Mode ("Thinking in Code")

**Context Mode** keeps large tool outputs, command results, and file dumps out of your context window.
Instead of dumping megabytes of text into the conversation, Context Mode indexes data into a local SQLite FTS5 database (`~/.context-mode/context.db`) and executes short sandboxed scripts that return only the exact answer or filtered snippets.

---

## Active Read Cutoff vs LLM Context Window

Context Mode enforces a single-operation read/command cutoff (default 8 KB, configurable per session or globally).

* **Tool-Level Read Cutoff**: This threshold applies strictly to individual `read_file` calls or terminal dump commands (`cat`, `Get-Content`, `type`) on non-exempt text/code files to protect against accidental bloat.
* **NOT the LLM Context Window**: The model retains its full, large context window capacity. Do **not** confuse the tool-level read cutoff (e.g. 8 KB) with the model's context window.
* **Dynamic Active Exemptions**: Active file category exemptions and custom extensions are dynamically loaded from SQLite and injected into your session by the `SessionStart` and `PreInvocation` hooks. Files matching active exemptions bypass cutoff protection. Use `ctx_stats` or `ctx_doctor` to check live exemptions at any time.
* **Intra-File & Directory Searches (Prioritize `ctx_search`)**: Whenever you need to locate symbols, methods, config values, or error patterns in any file or directory, use `ctx_search(queries: [...], path: "path/to/target")`. If the file is unindexed or has been modified on disk, `ctx_search` automatically indexes or updates it on-demand before searching.

---

## When to Use Context Mode

| Task | Traditional Tool (High Bloat) | Context Mode Solution (Zero Bloat) |
| :--- | :--- | :--- |
| **Searching code, logs, or docs** | Full un-paged `read_file` | `ctx_search(queries: ["..."], path: "path/to/target")` (JIT auto-indexed) |
| **Analyzing build or server logs** | `run_command("Get-Content Logs/Editor.log")` | `ctx_execute(code: "...", intent: "Find compile errors in Editor.log")` |
| **Processing API JSON responses** | Fetching and dumping 5,000 JSON items | `ctx_execute(language: "javascript", code: "fetch(...)")` |
| **Running multi-command batches** | Running 10 commands in separate tool calls | `ctx_batch(commands: [...], queries: [...])` |
| **Session recovery & token stats** | Re-reading entire past conversation | `ctx_resume()` / `ctx_stats()` |

---

## Tools Reference

### 1. `ctx_execute` (Thinking in Code)
Executes a short script in a local sandbox (Node.js, Python, PowerShell, or Shell) with an execution cap and timeout. The full output is automatically indexed into SQLite FTS5, and only the compact result (or a preview if large) is returned to the context window.

```json
{
  "language": "javascript",
  "code": "const fs = require('fs'); const lines = fs.readFileSync('Logs/Editor.log', 'utf8').split('\\n'); const errors = lines.filter(l => l.includes('error CS')); console.log(JSON.stringify(errors, null, 2));",
  "intent": "Filter C# compiler errors from Editor.log"
}
```

### 2. `ctx_search` (On-Demand / JIT Search)
Queries indexed chunks using Reciprocal Rank Fusion (Porter BM25 stemming + Trigram matching). Files are chunked intelligently by format (preserving entire functions, markdown sections, JSON/YAML keys, and log traces intact). Hits return complete enclosing methods/sections whenever possible, along with `startLine`, `endLine`, and enclosing `context` metadata.

If `path` is specified (file, directory, or URL), Context Mode checks if the target is unindexed or modified, automatically indexes it Just-In-Time, and scopes the search.

For log files, pass `"reverse": true` to prioritize tail-first inspection (latest errors and timestamps first):

```json
{
  "queries": ["FATAL", "Exception"],
  "path": "Logs/Editor.log",
  "reverse": true,
  "limit": 5
}
```

### 3. `ctx_batch`
Executes multiple shell commands in batch. The combined outputs are indexed and filtered by your queries:
```json
{
  "commands": [
    "git status -s",
    "git log -n 5 --oneline",
    "npm test"
  ],
  "queries": ["failing", "error"]
}
```

### 5. `ctx_stats`
Generates a token savings and status report showing active cutoff, exempt categories, raw bytes handled, and estimated tokens kept out of the model's context window:
```json
{}
```

### 6. `ctx_doctor`
Checks SQLite database health, active cutoff limits, exempt categories, FTS5 availability, and active runtimes.

---

## Best Practices

1. **Check Active Limits & Exemptions**: At session start or anytime via `ctx_stats` or `ctx_doctor`, verify the active cutoff limit and active exemptions.
2. **Read Exempt Media Directly**: Multimodal files (images, audio, video, PDFs, Office files, 3D models) are exempt from cutoff limits and will never be blocked by `read_file`.
3. **Write focused extraction scripts**: In `ctx_execute`, print only the final data structures or count summaries rather than raw loops.
4. **Never dump full logs**: If a text log file is > 100 KB, never call un-paged `read_file` or bare `Get-Content`. Route it through `ctx_execute` or `ctx_index`.
5. **Use Trigram for identifiers**: `ctx_search` matches code symbols, GUIDs, and file paths via trigram indexing even if English stemming does not match.
