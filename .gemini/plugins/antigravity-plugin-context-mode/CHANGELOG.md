# Changelog

All notable changes to `antigravity-plugin-context-mode` will be documented in this file.

## [2.0.0] - 2026-11-21

### Major Breakthrough: Intelligent Semantic Chunking & Context-Expanding Search

- **Multi-Language Semantic Chunking Engine (`lib/chunker.js`)**: Replaced arbitrary newline byte-bucket slicing with an intelligent, syntax- and format-aware chunking engine that preserves logical code structures and document sections.
  - **Programming & Scripting Languages**:
    - **JavaScript & TypeScript / JSX / TSX**: Preserves class declarations, method boundaries, arrow functions, async declarations, JSDoc blocks, and decorators without mid-function fracturing.
    - **Python**: Indentation-aware block parser that keeps complete classes, methods, functions, docstrings, and decorator stacks intact.
    - **Systems & OOP Languages (Java, C#, C, C++, Go, Rust, PHP, Swift, Kotlin, Scala)**: Tracks brace depth, namespaces, annotations, struct/impl blocks, and function headers.
    - **Shell & PowerShell**: Preserves function declarations, filter blocks, and parameter definitions.
    - **SQL**: Statement-level chunking for DDL and queries (`CREATE TABLE`, `CREATE PROCEDURE`, `SELECT`, `ALTER`, etc.).
  - **Markdown Documents**: Hierarchical section chunking that tracks H1–H6 header breadcrumbs (e.g. `# Architecture > ## Storage Engine > ### SQLite Schema`) and keeps fenced code blocks, tables, and list structures intact.
  - **Structured Data & Configurations**:
    - **JSON & JSONC / JSONL**: Chunks by top-level keys or array slices, preserving valid JSON structures and property contexts (`key: "scripts"`).
    - **YAML**: Chunks by document dividers (`---`) and top-level key hierarchies.
    - **TOML & INI / Config / ENV**: Keeps section blocks (`[section]`) and associated key-value pairs intact.
  - **Web & Markup**: Tag-aware chunking for HTML, XHTML, XML, SVG, and stylesheets (CSS, SCSS, LESS).
  - **Log Files & Stack Traces**: Intelligent event boundary detection grouping timestamps, log levels (`[INFO]`, `[ERROR]`, etc.), and multi-line exception stack traces (Java, Node, Python `at ...`, `Caused by:`) together in a single chunk.
  - **Diffs & Patches**: Hunk-level chunking preserving unified diff headers and patch chunks.
  - **Prose & Plain Text**: Paragraph-aware chunking snapping to double newlines and sentence boundaries.
- **Intelligent Contextual Snippet Expansion**:
  - When a matched chunk (method, markdown section, paragraph, or log event) fits within the generous snippet budget (default 2,048 bytes), `ctx_search` returns the **entire complete method / section / block intact** without arbitrary word or character truncation.
  - For oversized chunks, snippets expand outward to clean line boundaries and prepend structural context headers with exact line range markers (e.g. `... [lines above omitted; showing lines 45-80 [class Service > method compute()]]`).
- **Enhanced Search Result Metadata**:
  - `ctx_search` hits now include `startLine`, `endLine`, `context` (e.g. `class FlightComputer > calculateOrbitalVelocity()`), `title`, and `rrfScore` for immediate structural orientation.
- **SQLite Schema v4**:
  - Upgraded SQLite virtual tables `chunks` and `chunks_tri` to schema v4 with unindexed `start_line`, `end_line`, and `context` columns for high-performance zero-overhead metadata storage.
  - Seamless automatic migration from existing v2 and v3 databases.
- **Comprehensive Unit & Integration Test Suite (`tests/test_chunker.js`)**: Added 15 new tests covering format detection, syntax chunking across all languages, snippet expansion, and end-to-end SQLite search. All 46 tests pass cleanly.

## [1.5.0] - 2026-11-21

### Added
- **SQLite Schema v3 (`path` as Primary Key Everywhere)**: Migrated database schema to v3, promoting `path` to the primary key on `sources(path TEXT PRIMARY KEY)` and foreign reference on `chunks(path UNINDEXED)` / `chunks_tri(path UNINDEXED)`. Seamless automatic migration from existing v2 databases preserves database integrity while eliminating all legacy surrogate ID generators.
- **Just-In-Time On-Demand Indexing (`ctx_search`)**: Refactored `ctx_search` to support `path` for individual files, directory paths, and remote URLs. Unindexed or modified files are automatically checked against file modification timestamps (`mtime`) and indexed Just-In-Time before searching, returning instant, fresh hits in a single call.
- **Reverse Search for Log Files (`reverse: true`)**: Added optional `reverse` parameter to `ctx_search` to sort search hits by chunk order in reverse (`b.ord - a.ord`). Prioritizes the latest events, errors, and fatal crashes at the end of large log files first.
- **Snippet Centering on Search Hits**: Window search snippets around the matched query term position rather than slicing from offset 0, ensuring matching context is visible even in large chunks.
- **Intra-File Search Priority in Agent Standards (`rules/AGENTS.md`)**: Instructs agents to always prioritize `ctx_search(queries: [...], path: "path/to/target")` for all intra-file and directory searches without manual indexing or pre-reading into context.
- **Live Active Cutoff & Exemption Injections (`SessionStart` & `PreInvocation`)**: Injected system messages dynamically report the active single-operation read cutoff and enumerate all active exempt file categories and custom extensions. Explicitly clarifies to LLM agents that the cutoff is a single-read tool threshold, not the model's context window.
- **Mid-Session Dynamic Configuration Tracking (`PreInvocation`)**: Added `hooks/pre-invocation.js` registered under `PreInvocation` in `hooks.json`. Automatically detects when cutoff limits or file exemptions are modified mid-session (via dashboard or API) and immediately informs the agent on the subsequent turn while remaining completely quiet when unchanged.
- **Automatic Indexing Redirection on Interception (`PreToolUse`)**: Instead of simply rejecting large `read_file` operations or un-paged file dump commands (`cat`, `Get-Content`, `type`), `PreToolUse` now automatically reads and indexes the target file into SQLite FTS5 (up to a configurable 50 MB ceiling) and returns `[context-mode protection: auto-indexed]` with the intuitive workspace-relative path and immediate search instructions (`ctx_search(queries: [...], path: "...")`).
- **Auto-Index Limit Dashboard Control & API**: Added a Max Auto-Index dropdown selector (`1 MB`, `5 MB`, `10 MB`, `50 MB`, `100 MB`, `Off`) directly adjacent to the Cutoff dropdown in the sidecar dashboard. Added `/api/auto-index-limit` (`GET` and `POST`) endpoint in `server.mjs`.
- **Configurable Auto-Index Limits Cascade**: Configurable via `CONTEXT_MODE_MAX_AUTO_INDEX_BYTES` env variable, session overrides, or SQLite global setting (`store.getMaxAutoIndexBytes()` and `store.setMaxAutoIndexBytes()`).
- **Idempotent File Re-indexing**: Re-indexing an existing file path cleans up prior chunk entries before writing new ones, preventing chunk duplication when files are edited and re-indexed.
- **Active Limit & Exemption Diagnostics in MCP Tools**: Enhanced `ctx_stats` and `ctx_doctor` to return active cutoff bytes, formatted cutoff strings, and active exempt categories/extensions.

### Removed
- **Retired `ctx_index` Tool**: Removed `ctx_index` from MCP tool definitions, server schemas, rules, and skills. Because `ctx_search` automatically indexes files, directories, and URLs on-demand, manual indexing is completely obsolete.
- **Eliminated `sourceId` Parameter**: Replaced internal database identifier `sourceId` across all tool schemas, denial messages, and agent instructions with standard `path`.
- **Stripped `mcp_context-mode_` Prefix from Denial Messages**: Simplified all guidance messages so tool calls are referred to cleanly as `ctx_search` and `ctx_execute`.

### Fixed
- **Dynamic Maximum Line Ranges**: Replaced arbitrary hardcoded line numbers (`-First 50`, `head -n 50`) in denial messages and rules with dynamic maximum limits (`getMaxLineRange(maxReadBytes)`) and explicit instructions to read only the lines needed.
- **Zero Redundant Instructions**: Stripped repetitive tutorial text, hardcoded exemption catalogs, and false guarantees from rules, session-start notices, and tool denial reasons.
- **Debunked 8 KB Context Window Hallucination in Agent Rules (`rules/AGENTS.md`)**: Updated behavioral guidelines to explicitly distinguish between the tool-level read cutoff and the model's actual context window, and instructed agents that exempt files bypass cutoff protection.
- **Dynamic PreToolUse Denial Messages**: Refactored denial messages in `hooks/pre-tool-use.js` to dynamically generate the live active exempt categories and custom extensions from SQLite rather than displaying a static hardcoded note.
- **Double-Counting Prevention in Statistics**: Updated `getStats()` in `lib/store.js` to ensure auto-indexed files logged through `read_file` or `run_command` interceptions are not double-counted between `events` and `sources`.
- **Safe ES Module Hook Imports**: Added `isMain` execution guards to `hooks/pre-tool-use.js` and `hooks/session-start.js` to prevent unintended top-level execution when importing helper utilities across hooks and test suites.

## [1.3.2] - 2026-11-17

### Documentation
- **User-Friendly Documentation**: Streamlined `README.md` to focus on end-user benefits, 1-click `npx` installation (global & workspace modes), real-world token savings benchmarks, and interactive sidecar controls.
- **Synchronized Manifest Version**: Updated `plugin.json` version to match release tag.

## [1.3.1] - 2026-11-16

### Added
- **1-Click CLI Installer (`npx antigravity-plugin-context-mode install`)**: Added automated CLI installation handler to `server.js`. Running `npx antigravity-plugin-context-mode install` automatically detects the user's OS and copies plugin manifests, hooks, skills, and MCP tools directly into `~/.gemini/config/plugins/antigravity-plugin-context-mode` (or workspace-scoped via `--workspace`).
- **CLI Uninstaller (`npx antigravity-plugin-context-mode uninstall`)**: Easily remove the installed plugin from the Antigravity discovery directory with clean terminal confirmation.
- **CLI Flags (`--help`, `-h`, `--version`, `-v`)**: Quick terminal reference for command options while maintaining 100% backward compatibility with MCP stdio JSON-RPC protocol execution.

## [1.3.0] - 2026-11-15

### Added
- **Subagent & Primary Agent Unified Lifecycle Hooks**: `PreToolUse` now evaluates all lifecycle checks (un-paged file reads, unbounded command dumps, active cutoffs, and file category exemptions) identically across both primary agents and subagents.
- **Shared Conversation Dashboard for Subagents**: Interceptions and metrics generated by subagents now resolve to the root/parent conversation ID (`resolveSharedSessionId`), feeding directly into the active conversation dashboard in real-time. Added dedicated subagent badges in the recent activity feed to indicate which subagent triggered the event.
- **Session Lifecycle Overrides Reset**: Added `clearSessionOverrides()` to `ContextModeStore`. Wired into the `SessionStart` hook and server process initialization so that when a new application session begins (app restart), transient session overrides are cleared and the session starts fresh with the Lifetime default.
- **Direct Indexing Telemetry (`ctx_index`)**: `ctx_index` now records an event in the `events` journal upon successful indexing, immediately updating dashboard call counts and crediting kept-out tokens.

### Fixed
- **Lifetime Cutoff Propagation to New Sessions and Conversations**: Changing the cutoff in Lifetime scope (`"all"`, `"lifetime"`, or `null`) now updates `cutoff:global` in SQLite and clears any transient session-level (`cutoff:session:app`) and fallback default (`cutoff:session:default`) overrides. New sessions and new conversations automatically inherit the new Lifetime cutoff as their default.
- **Accurate Context Saved Calculations (Eliminated Double-Counting)**: Refactored `getStats()` to prevent execution outputs from being counted twice across `events` (`sandbox_bytes`) and `sources` (`bytes_total`). Raw, returned, and kept-out bytes are now computed on a per-event basis with `MAX(sandbox_bytes, bytes, returned_bytes)`, ensuring passthrough commands with full returns do not penalize the savings of other tools.
- **MCP Server Root Session Binding**: `server.js` and `createTools` now bind session ID resolution to `process.env.ANTIGRAVITY_ROOT_CONVERSATION_ID` and `process.env.ANTIGRAVITY_PARENT_CONVERSATION_ID`, ensuring MCP tool calls from subagents share indexed chunks and search states with the primary conversation.

## [1.2.1] - 2026-11-15

### Fixed
- **CI Test Database Contention (`CONTEXT_MODE_DB_PATH`)**: Updated `getDefaultDbPath()` in `lib/store.js` to respect `process.env.CONTEXT_MODE_DB_PATH`, and configured `tests/test_mcp.js` and `tests/test_hooks.js` to run in dedicated isolated temporary directories. Prevents database lock contention (`SQLITE_BUSY`) during concurrent test execution on multi-core CI runners.
- **SQLite Busy Timeout**: Added `PRAGMA busy_timeout = 5000;` to database initialization with fallback handling to ensure robust concurrency when multiple processes interact with SQLite in WAL mode.
- **Category Exemption Group Toggles**: Replaced the long list of individual file exemption chips in the dashboard with clean, one-click category pills (`Images`, `Documents`, `3D Models`, `Music`, `Video`, `Archives`, and `Source Code`). All binary categories default to enabled, with Source Code disabled by default. Added optimistic UI state switching in `toggleCategory()` for instant feedback upon click.
- **Streamlined Custom File Types**: Restricted the dashboard's exemption tags container to only display user-added custom file types (e.g. `.csv`, `.parquet`, `.log`) with quick removal buttons (`×`).
- **Status Banner Cleanup**: Replaced `"Live Telemetry"` with **`"Showing stats for: <SCOPE>"`** and removed distracting animations (`animate-ping`, `animate-pulse`) for steady, non-blinking visual indicators.
- **Dynamic Upper-Right Version Badge**: Displays the active plugin version badge (`v1.2.1`) in the top-right header cluster of the dashboard, populated dynamically from `package.json` via `/api/stats`.
- **Documentation & Formatting Polishing**: Removed all LaTeX math syntax from `README.md`, replacing it with standard Unicode (`≤ 50 lines`, `≤ 200 lines`, `≤ 800 lines`), and consolidated redundant configuration tables.

## [1.2.0] - 2026-11-15

### Fixed
- **Quoted Path Stripping in `PreToolUse`**: Added path argument sanitization to strip enclosing quotes (`"..."`, `'...'`), decode URI schemes (`file:///`), and trim line anchor fragments (`#L...`), preventing `fs.existsSync` failures on Windows when tool calls pass quoted file paths.
- **Interception Journaling & Telemetry**: Added event journaling on all blocked `read_file` and `run_command` tool calls directly into SQLite (`events` table). Interceptions now immediately update `tokensPreserved`, `keptOutBytes`, `reductionRate`, and the Prompt Payload Comparison bar in the sidecar dashboard.
- **Dynamic Command Inspection (No Hardcoded Extension Whitelist)**: Refactored `run_command` in `PreToolUse` (`inspectCommand`) to eliminate hardcoded extension regexes completely. Commands invoking `cat`, `type`, `Get-Content`, or `gc` now dynamically extract target paths, check them against the active SQLite cutoff, and respect user-configured exemptions. Safe small files ($\le$ cutoff) and bounded commands (`-Head`, `| Select-Object -First`) are allowed, while wildcard and unpaged dumps are blocked.
- **UTF-16 & Known Text Exemption Safeguard**: Added `KNOWN_TEXT_EXTENSIONS` check and UTF-16/UTF-8 BOM header inspection to prevent text and code files (such as `.cs` and `.md`) containing null bytes from being misidentified as binary files and bypassing size cutoffs.
- **Live Interception Feed in Dashboard**: Updated the "Recent Indexed Sources & Interceptions" dashboard panel to display blocked calls (`🛡️ Blocked <tool>: <path>`) with exact saved token metrics alongside indexed FTS5 sources.
- **Renamed 'Sandbox Cap' to 'Preview Limit'**: Replaced the confusing "Sandbox Cap" label with **`PREVIEW LIMIT`**, clearly indicating the maximum preview slice of command and script output returned into the chat.
- **Clickable 'Active' / 'Paused' Toggle**: Turned the status badge into an interactive toggle button. Users can click to switch Context Mode between **Active** (pulsing green dot) and **Paused** (amber dot). When paused, the `PreToolUse` hook allows all tool calls through without size checks or command interception, persisting the paused state across active scopes in SQLite.
- **Fixed Feed Width Truncation**: Removed the hardcoded `max-w-[210px]` container constraint and arbitrary string slice in the dashboard feed, replacing it with responsive `min-w-0 flex-1` flex sizing and clean path formatting (`formatItemPath`) so file and command paths scale dynamically to the full width of the dashboard panel.
- **Reformatted Metric Rows & Sidebar Layout**: Redesigned the 4 performance metrics into sleek, compact horizontal key-value rows inside a single card (labels on the left, values right-aligned) with subtle hairline dividers. This replaces the tall vertical 12-line card stack in normal sidebars with an information-dense, space-efficient ~140px card that naturally scales to 2 columns on wide screens without wrapping or wasting space.
- **Unified Lifecycle Gates & System Health (Moved to Bottom)**: Combined `Active Lifecycle Gates` and `Engine & Storage Health` into a single cohesive reference card with an inline `HEALTHY` badge, streamlined gate checkmarks, and compact engine specs. Moved this card to the very bottom of the dashboard so actively changing interactive panels (metrics, exemptions, search, and the live interception feed) remain prominently visible at the top.
- **Dual-Color Single Progress Bar**: Consolidated the separate raw dump and sandboxed slice progress bars into a single, compact dual-color progress bar placed directly beneath the Live Telemetry status banner. It visually depicts % of context saved in emerald green (`#00e599`) and prompt payload in rose (`#f43f5e`), with live `% saved` text. Removed the redundant middle comparison card and sub-labels since the metric cards directly below inform the user of all exact figures.
- **User-Friendly Dashboard Terminology**: Replaced cryptic architecture jargon (such as `"SQLite FTS5 Reciprocal Rank Fusion & Sandbox Execution"`, `"Porter BM25 + Trigram RRF"`, and `"Tokens diverted from LLM"`) with clear, value-focused explanations highlighting token savings, context reduction, and keeping LLMs smart, focused, and less forgetful.
- **Context-Focused Language & Removal of Log Fixation**: Changed card sub-labels from confusing "prompt bloat" phrasing to **`"Context reduced by <size>"`** and **`"Context window preserved"`**, clarifying that user prompts are never deleted or truncated. Removed all narrow fixations on "logs" throughout tooltips, input placeholders, and lifecycle gates to accurately represent all large files, code, and terminal outputs.

## [1.1.0] - 2026-11-14

### Added
- **3-Way Telemetry Scope Toggle**: Added seamless switching between **Conversation** (active chat UUID), **Session** (current application run since app restart), and **Lifetime** (all-time cumulative database metrics).
- **Interactive Cutoff Dropdown**: Scoped cutoff selector (`2 KB`, `4 KB`, `8 KB`, `16 KB`, `32 KB`, `64 KB`, `none`) with persistent SQLite storage and fallback hierarchy.
- **Dynamic Line Slice Gating**: `PreToolUse` line limit scales dynamically with the cutoff setting (e.g. 2 KB: $\le$50 lines, 4 KB: $\le$100 lines, 8 KB: $\le$200 lines, 16 KB: $\le$400 lines, 32 KB: $\le$800 lines, 64 KB: $\le$1600 lines, none: unrestricted).
- **Dynamic Sandbox Preview Cap**: `ctx_execute` returned preview text automatically scales to double the active cutoff setting.
- **Default MS Office Exemptions**: Added `.docx`, `.doc`, `.xlsx`, `.xls`, `.pptx`, `.ppt` alongside `.pdf`, images, and audio/video files to default exemptions.
- **Clickable Recent Sources**: Click any indexed source in the dashboard to immediately open and view its raw chunks in the Search Inspector.
- **Exemptions Management Panel**: Live tag editor to add/remove custom file extensions with persistence note and default reset.
- **Tooltips on Dashboard Metrics**: Added rich context tooltips across all metric cards, lifecycle gates, and SQLite health indicators.
- **Automated LRU Database Pruning**: Capped stored sources (50 per session, 200 total) to prevent unbounded SQLite growth over long-term use.

### Fixed
- Fixed sidecar token authentication and added dual `GET` & `POST` endpoint support for `/api/cutoff`, `/api/exemptions`, and `/api/purge` to prevent 401/405 errors inside aux pane webviews.
- Fixed MCP tool server defaulting to generic session instead of binding to `process.env.ANTIGRAVITY_CONVERSATION_ID`.
- Fixed dashboard metrics crashing on string formatted numbers by adding explicit numeric coercion.

## [1.0.2] - 2026-11-14

### Fixed
- Fixed npm publish script

## [1.0.1] - 2026-11-14

### Added
- Added screenshot of sidecar dashboard to README.md

## [1.0.0] - 2026-11-14

### Added
- **Native Antigravity App Plugin Bundle**: Complete manifest and customization suite (`plugin.json`, `hooks.json`, `mcp_config.json`).
- **Stdio MCP Server**: Zero-dependency JSON-RPC server implementing the 8 core context tools (`ctx_execute`, `ctx_batch`, `ctx_index`, `ctx_search`, `ctx_resume`, `ctx_stats`, `ctx_doctor`, `ctx_purge`).
- **SQLite FTS5 Storage Engine**: Native database layer using Node.js `DatabaseSync` (`node:sqlite`) with WAL mode, chunked source storage, and session journaling.
- **Hybrid Search & Ranking**: Combined Porter BM25 stemming and Trigram full-text search merged via Reciprocal Rank Fusion ($RRF_{k=60}$).
- **Antigravity Lifecycle Hooks**:
  - `PreToolUse`: Automated gating that blocks unbounded log dump commands (`cat`, `Get-Content` on raw `.log`) and un-paged file reads over 8 KB.
  - `SessionStart`: Injects session state continuity and active context protection advice.
- **Configurable Cutoffs via Environment Variables**:
  - `CONTEXT_MODE_MAX_READ_BYTES`: Configurable ceiling for un-paged file reads (defaults to 8 KB / 8,192 bytes).
  - `CONTEXT_MODE_MAX_LINE_RANGE`: Maximum allowed line range slice without pagination rejection (defaults to 200 lines).
- **Auxiliary Pane Sidecar Web UI**: Dedicated sidecar dashboard (`sidecars/dashboard/`):
  - `sidecar.json` configured for `SIDECAR_UI_ENTRYPOINT_AUX_PANE`.
  - `server.mjs` backend powered by `sidecar_sdk` with fallback standalone HTTP server.
  - `index.html` real-time telemetry dashboard featuring reduction rate metrics, volume compression visualization, an interactive FTS5 search inspector, recent sources, and session purge controls.
- **Agent Skill & Behavioral Rules**: Bundled `skills/context-mode/SKILL.md` (Thinking in Code) and `rules/AGENTS.md` (context budgeting laws).
- **Test Suite**: Native `node:test` test harness covering storage, execution sandbox, tools, hooks, and MCP JSON-RPC protocol with 100% pass rate.
- **CI/CD Automation**: Automated `publish.yml` GitHub Actions workflow for npm and GitHub Releases.
