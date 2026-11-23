# Antigravity Context Mode

> **Stop context bloat. Save 90%+ of your prompt tokens. Keep your agent fast, focused, and sharp.**

[![npm version](https://img.shields.io/npm/v/antigravity-plugin-context-mode.svg)](https://www.npmjs.com/package/antigravity-plugin-context-mode)
[![Live Simulator](https://img.shields.io/badge/Live%20Simulator-domush.github.io-10b981?style=flat&logo=github)](https://domush.github.io/antigravity-plugin-context-mode/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Zero Runtime Dependencies](https://img.shields.io/badge/dependencies-0-brightgreen.svg)]()
[![Antigravity App Compatible](https://img.shields.io/badge/Antigravity%20App-Native%20Plugin-6366f1.svg)]()

**Antigravity Context Mode** is a free, local-first plugin built specifically for the **Google Antigravity App**. It prevents terminal commands, build logs, and multi-megabyte source files from flooding your conversation history — saving thousands of tokens per turn and stopping "agent amnesia" in its tracks.

> 🌐 **Interactive Live Simulator & Documentation:** [domush.github.io/antigravity-plugin-context-mode](https://domush.github.io/antigravity-plugin-context-mode/)

<img width="715" height="632" alt="image" src="https://github.com/user-attachments/assets/952b84a0-3e71-4bd6-99ae-e604532c1c7f" />

---

## Quick Install (10 Seconds)

Install directly into Google Antigravity using `npx`:

```bash
# Global install (available across all Antigravity chats)
npx antigravity-plugin-context-mode install

# Or per-project install (scoped to current workspace only)
npx antigravity-plugin-context-mode install --workspace
```

Restart or open the **Google Antigravity App**, and Context Mode is active immediately.

---

## The Problem: Context Window Bloat

When an AI coding agent executes tools, standard workflows dump everything directly into your conversation context:

* Running `Get-Content Logs/Editor.log` dumps **65 KB (~16,000 tokens)** into your prompt.
* Running test suites, compiler outputs, or git diffs dumps **100+ KB** of repetitive text.
* Reading large files or documentation dumps **thousands of lines** of irrelevant boilerplate.

### Why this hurts:
1. **Agent Amnesia**: Important system instructions, user constraints, and earlier architectural decisions get drowned out.
2. **Slower Responses**: Generation times climb as the context window fills up with raw text.
3. **Burned Quota**: You hit model context ceilings and rate limits after only a few turns.

---

## How It Works

```text
┌────────────────────────────────────────────────────────┐
│ 1. Agent attempts to read a large file or command      │
│    (e.g. 5,000-line build log or 40 KB source file)    │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼
┌────────────────────────────────────────────────────────┐
│ 2. Pre-Tool Lifecycle Hook Intercepts Unbounded Dumps  │
│    • Files under 8 KB pass through normally            │
│    • Un-paged dumps over 8 KB are safely intercepted   │
│    • Images, docs, and chat attachments are exempted   │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼
┌────────────────────────────────────────────────────────┐
│ 3. Sandboxed Execution ("Thinking in Code")            │
│    • Agent filters data locally using Node/Python      │
│    • Indexes text into local SQLite FTS5 BM25 database │
│    • Returns only the exact answer or error to chat!   │
└────────────────────────────────────────────────────────┘
```

---

## Proven Results

Tested live on real-world build logs and source files in the Antigravity App:

| Operation | Without Context Mode | With Context Mode | Savings |
| :--- | :---: | :---: | :---: |
| **Parsing 665-line Unity Editor.log** | 65,242 B (~16,300 tokens) | **550 B (~137 tokens)** | **99.2% saved** |
| **Full File Read on 40 KB component** | 40,960 B (~10,240 tokens) | Intercepted & paged to 50 lines (**~800 tokens**) | **92.1% saved** |
| **Searching Compiler Warnings** | Entire log dumped into chat | Top 2 ranked FTS5 snippets (**~350 tokens**) | **97.8% saved** |

---

## Key Features

* 🛡️ **Automated Interception**: Blocks unbounded file dumps and commands before they bloat your context window.
* 🧠 **Intelligent Language-Aware Chunking (v2.0)**: Intelligently chunks source code (JS, TS, Python, Java, C#, Go, Rust, Ruby, PHP, C/C++, Shell, SQL), Markdown, JSON, YAML, TOML, HTML/XML, and logs. Never slices methods, headings, or stack traces mid-block.
* 🎯 **Context-Expanding Search Results**: `ctx_search` returns complete source code methods, paragraphs, or markdown sections intact, along with `startLine`, `endLine`, and enclosing scope metadata.
* ⚡ **Thinking in Code**: Filters multi-thousand-line outputs in 50 ms via local sandboxed scripts.
* 🔍 **Local SQLite FTS5 Search**: Indexes files and logs locally on your machine with BM25 keyword matching and trigram search.
* 📊 **Built-In Sidecar Dashboard**: Real-time stats pane inside Antigravity showing tokens kept out of context.
* 🔒 **100% Local & Private**: All indexed data stays on your machine in `~/.context-mode/context.db`. Zero network calls.
* 📦 **Zero Dependencies**: Pure native Node.js (`node:sqlite`). No native builds, no `node-gyp`.

---

## Available MCP Tools

Once installed, Context Mode provides 7 specialized tools for your agent:

| Tool | What It Does |
| :--- | :--- |
| **`ctx_execute`** | Runs sandboxed Node.js, Python, or PowerShell scripts to inspect files and extract answers without context dumps. |
| **`ctx_search`** | Searches files, directories, or URLs with Just-In-Time auto-indexing using local SQLite FTS5 BM25 + Trigram RRF. |
| **`ctx_batch`** | Runs multiple terminal commands in batch and indexes the combined output. |
| **`ctx_stats`** | Displays a formatted context savings report and token metrics. |
| **`ctx_resume`** | Returns recent indexed sources and state pointers after context compactions. |
| **`ctx_doctor`** | Checks SQLite database health, WAL mode, and platform runtimes. |
| **`ctx_purge`** | Clears stored sources and events from SQLite. |

---

## Managing the Plugin

### Enable / Disable Anytime
Open the **Google Antigravity App** &rarr; **Customizations** &rarr; **Plugins**, and toggle **Context Mode** on or off with one click.

### Adjust Cutoffs
You can adjust the file read cutoff directly in the sidecar dashboard dropdown (`2 KB`, `4 KB`, `8 KB`, `16 KB`, `32 KB`, `64 KB`, or `none`).

### Uninstall
To remove the plugin from your system:

```bash
# Remove global plugin
npx antigravity-plugin-context-mode uninstall

# Remove per-project plugin
npx antigravity-plugin-context-mode uninstall --workspace
```

---

## License

MIT © [Phillip Webber](https://github.com/Domush)
