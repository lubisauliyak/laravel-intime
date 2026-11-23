# Context Mode Behavioral Standards

When `antigravity-plugin-context-mode` is active, agents MUST adhere to context budgeting laws while understanding active limits:

1. **Active Read Cutoff vs LLM Context Window**:
   - The active cutoff limit (default 8 KB per file/command, or as configured per session) is a **single-operation tool read/command cutoff** enforced by `PreToolUse` to prevent accidental token bloat.
   - It is **NOT your LLM context window**. Your LLM context window is vastly larger. Do NOT assume your context window is 8 KB.
   - Files or command outputs within the active cutoff limit can always be read directly.

2. **Intra-File & Directory Searches (Prioritize `ctx_search`)**:
   - **Always prioritize `ctx_search(queries: [...], path: "path/to/target")`** whenever you need to search for specific functions, symbols, error patterns, or text inside any file or directory.
   - `ctx_search` automatically indexes and updates files Just-In-Time based on file modification timestamps—you do NOT need to read files into context first or run any manual indexing.
   - **Log & Chronological Files**: When searching logs, traces, or events, pass `reverse: true` (e.g. `ctx_search(queries: ["error"], path: "Logs/app.log", reverse: true)`) to inspect the newest lines and most recent errors at the tail of the file first.
   - **Line Slices**: Read files directly with `StartLine` and `EndLine` only when you already know the specific lines needed (within the active line limit).
   - **Script Filtering**: Or inspect/filter with short scripts via `ctx_execute`.

3. **Zero Raw Log Dumps**: Never run unbounded terminal commands (`cat`, `Get-Content`, `type`, unpaged `git log`, direct `curl`/`wget` to stdout). Pipe to limiters for only the specific lines needed (e.g. `| Select-Object -First <lines>` or `| head -n <lines>`) or run via `ctx_execute`.

4. **Token Verification & Active Status**:
   - The active cutoff limit and currently active exempt file types are dynamically announced in the session status message and on configuration changes.
   - To inspect live token savings, the active cutoff, and live exemption status at any time, run `ctx_stats` or `ctx_doctor`.
