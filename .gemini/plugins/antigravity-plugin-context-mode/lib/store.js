import fs from "node:fs";
import path from "node:path";
import os from "node:os";
import { DatabaseSync } from "node:sqlite";
import {
  chunkContent as semanticChunkContent,
  generateContextSnippet,
  DEFAULT_SNIPPET_BYTES,
  DEFAULT_SOFT_CHUNK_BYTES
} from "./chunker.js";

export const MAX_CHUNK_BYTES = DEFAULT_SOFT_CHUNK_BYTES;
export const SNIPPET_MAX_BYTES = DEFAULT_SNIPPET_BYTES;
export const RRF_K = 60;
export const STATE_BUDGET = 2048;

export const FILE_CATEGORIES = {
  images: {
    id: "images",
    name: "Images",
    extensions: [".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg", ".bmp", ".ico", ".tiff", ".psd"]
  },
  documents: {
    id: "documents",
    name: "Documents",
    extensions: [".pdf", ".docx", ".doc", ".xlsx", ".xls", ".pptx", ".ppt", ".odt", ".rtf"]
  },
  code: {
    id: "code",
    name: "Source Code",
    extensions: [".js", ".mjs", ".cjs", ".ts", ".tsx", ".jsx", ".py", ".rs", ".go", ".java", ".c", ".cpp", ".h", ".cs", ".html", ".css", ".json", ".yaml", ".yml", ".sql", ".sh", ".ps1"]
  },
  models3d: {
    id: "models3d",
    name: "3D Models",
    extensions: [".blend", ".fbx", ".obj", ".gltf", ".glb", ".stl", ".dae", ".3ds", ".step", ".iges"]
  },
  music: {
    id: "music",
    name: "Music",
    extensions: [".mp3", ".wav", ".ogg", ".flac", ".aac", ".m4a", ".wma", ".midi"]
  },
  video: {
    id: "video",
    name: "Video",
    extensions: [".mp4", ".mov", ".avi", ".mkv", ".webm", ".flv", ".wmv", ".m4v"]
  },
  archives: {
    id: "archives",
    name: "Archives",
    extensions: [".zip", ".tar", ".gz", ".7z", ".rar", ".wasm"]
  }
};

export const DEFAULT_ACTIVE_CATEGORIES = ["images", "documents", "models3d", "music", "video", "archives"];

export const DEFAULT_EXEMPT_EXTENSIONS = Array.from(
  new Set(
    DEFAULT_ACTIVE_CATEGORIES.flatMap((catId) => FILE_CATEGORIES[catId]?.extensions || [])
  )
);

/** Format active categories and custom extensions into a human-readable summary */
export function formatExemptSummary({ activeCats = [], customs = [] } = {}) {
  const categoryLabels = {
    images: "Images (.png, .jpg, etc.)",
    documents: "Documents & Office (.pdf, .docx, etc.)",
    code: "Source Code (.js, .py, etc.)",
    models3d: "3D Models (.blend, .obj, etc.)",
    music: "Audio (.mp3, .wav, etc.)",
    video: "Video (.mp4, .mkv, etc.)",
    archives: "Archives & Binaries (.zip, .tar, etc.)"
  };

  const labels = activeCats
    .map((c) => categoryLabels[c] || (FILE_CATEGORIES[c] ? `${FILE_CATEGORIES[c].name}` : c))
    .filter(Boolean);

  const parts = [];
  if (labels.length > 0) {
    parts.push(labels.join(", "));
  } else {
    parts.push("No categories");
  }

  if (customs && customs.length > 0) {
    parts.push(`Custom (${customs.join(", ")})`);
  }

  parts.push("prompt attachments in .user_uploaded/");
  return parts.join(", ");
}

/** Default max file size (50 MB) automatically indexed on cutoff block */
export const DEFAULT_MAX_AUTO_INDEX_BYTES = 50 * 1024 * 1024; // 52,428,800 bytes

/** Resolve intuitive file path: workspace-relative when inside project, normalized absolute when outside */
export function resolveFilePath(filePath, cwd = process.cwd()) {
  if (!filePath) return null;
  const absPath = path.isAbsolute(filePath) ? path.normalize(filePath) : path.resolve(cwd, filePath);
  const normCwd = path.normalize(cwd);

  const rel = path.relative(normCwd, absPath);
  if (!rel.startsWith("..") && !path.isAbsolute(rel)) {
    return rel.replace(/\\/g, "/");
  }
  return absPath.replace(/\\/g, "/");
}

export const resolveFileSourceId = resolveFilePath;

/** Default path to context database (~/.context-mode/context.db) */
export function getDefaultDbPath() {
  if (process.env.CONTEXT_MODE_DB_PATH && process.env.CONTEXT_MODE_DB_PATH.trim()) {
    return process.env.CONTEXT_MODE_DB_PATH.trim();
  }
  const dir = path.join(os.homedir(), ".context-mode");
  return path.join(dir, "context.db");
}

/** Sanitize an input query string for safe FTS5 queries */
export function sanitizeFtsQuery(query) {
  if (!query || typeof query !== "string") return "";
  const cleaned = query.trim().replace(/[\x00-\x1f]/g, " ");
  const tokens = cleaned.match(/[a-zA-Z0-9_\u0080-\uffff]+/g);
  if (!tokens || tokens.length === 0) return "";
  return tokens.map((t) => `"${t.replace(/"/g, '""')}"`).join(" ");
}

/** Sanitize query specifically for trigram index (must have at least 3 characters) */
export function sanitizeTrigramQuery(query) {
  if (!query || typeof query !== "string") return "";
  const tokens = query.match(/[a-zA-Z0-9_\u0080-\uffff]{3,}/g);
  if (!tokens || tokens.length === 0) return "";
  return tokens.map((t) => `"${t.replace(/"/g, '""')}"`).join(" ");
}

export class ContextModeStore {
  constructor(optionsOrDbPath) {
    if (typeof optionsOrDbPath === "string" && optionsOrDbPath.trim()) {
      this.dbPath = optionsOrDbPath.trim();
    } else if (optionsOrDbPath && typeof optionsOrDbPath.dbPath === "string" && optionsOrDbPath.dbPath.trim()) {
      this.dbPath = optionsOrDbPath.dbPath.trim();
    } else {
      this.dbPath = getDefaultDbPath();
    }
    this.db = null;
  }

  /** Open or initialize SQLite database */
  open() {
    if (this.db) return this.db;
    const dbDir = path.dirname(this.dbPath);
    if (!fs.existsSync(dbDir)) {
      fs.mkdirSync(dbDir, { recursive: true });
    }
    this.db = new DatabaseSync(this.dbPath);
    this.initSchema();
    return this.db;
  }

  close() {
    if (this.db) {
      try {
        this.db.close();
      } catch {
        // ignore close error
      }
      this.db = null;
    }
  }

  /** Initialize tables, FTS5 virtual tables, and indexes */
  initSchema() {
    if (!this.db) return;
    try {
      this.db.exec("PRAGMA busy_timeout = 5000;");
      this.db.exec("PRAGMA journal_mode = WAL;");
      this.db.exec("PRAGMA synchronous = NORMAL;");
    } catch {
      // Fallback if PRAGMA configuration is restricted
    }

    // Standard tables
    this.db.exec(`
      CREATE TABLE IF NOT EXISTS meta (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS sources (
        path TEXT PRIMARY KEY,
        session_id TEXT NOT NULL,
        worktree TEXT NOT NULL,
        agent TEXT NOT NULL,
        kind TEXT NOT NULL,
        label TEXT NOT NULL,
        bytes_total INTEGER NOT NULL DEFAULT 0,
        chunk_count INTEGER NOT NULL DEFAULT 0,
        created_ts INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        accessed_ts INTEGER NOT NULL,
        mtime INTEGER DEFAULT 0
      );

      CREATE INDEX IF NOT EXISTS idx_sources_session ON sources(session_id);
      CREATE INDEX IF NOT EXISTS idx_sources_worktree ON sources(worktree);

      CREATE TABLE IF NOT EXISTS events (
        seq INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        worktree TEXT NOT NULL,
        agent TEXT NOT NULL,
        kind TEXT NOT NULL,
        tool TEXT,
        key TEXT,
        path TEXT,
        ok INTEGER NOT NULL DEFAULT 1,
        exit_code INTEGER,
        bytes INTEGER NOT NULL DEFAULT 0,
        body TEXT,
        turn INTEGER NOT NULL DEFAULT 0,
        sandbox_bytes INTEGER NOT NULL DEFAULT 0,
        returned_bytes INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        accessed_at TEXT
      );

      CREATE INDEX IF NOT EXISTS idx_events_session ON events(session_id);
      CREATE INDEX IF NOT EXISTS idx_events_turn ON events(session_id, turn);
    `);

    // Virtual FTS5 tables (schema v4)
    this.db.exec(`
      CREATE VIRTUAL TABLE IF NOT EXISTS chunks USING fts5(
        path UNINDEXED,
        session_id UNINDEXED,
        worktree UNINDEXED,
        agent UNINDEXED,
        ord UNINDEXED,
        start_line UNINDEXED,
        end_line UNINDEXED,
        context UNINDEXED,
        body,
        title,
        tokenize = 'porter unicode61 remove_diacritics 2'
      );

      CREATE VIRTUAL TABLE IF NOT EXISTS chunks_tri USING fts5(
        path UNINDEXED,
        session_id UNINDEXED,
        ord UNINDEXED,
        start_line UNINDEXED,
        end_line UNINDEXED,
        context UNINDEXED,
        body,
        tokenize = 'trigram'
      );
    `);

    // Schema migration check: upgrade existing v2/v3 databases to v4
    try {
      const sourceCols = this.db.prepare("PRAGMA table_info(sources)").all().map((c) => c.name);
      if (sourceCols.includes("id") && !sourceCols.includes("path")) {
        this.db.exec("ALTER TABLE sources RENAME COLUMN id TO path;");
      }
      if (!sourceCols.includes("mtime")) {
        this.db.exec("ALTER TABLE sources ADD COLUMN mtime INTEGER DEFAULT 0;");
      }

      let needsChunksUpgrade = false;
      try {
        const chunkCols = this.db.prepare("PRAGMA table_info(chunks)").all().map((c) => c.name);
        if (!chunkCols.includes("start_line") || !chunkCols.includes("context") || !chunkCols.includes("path")) {
          needsChunksUpgrade = true;
        }
      } catch {
        needsChunksUpgrade = true;
      }

      if (needsChunksUpgrade) {
        this.db.exec("DROP TABLE IF EXISTS chunks;");
        this.db.exec("DROP TABLE IF EXISTS chunks_tri;");
        this.db.exec(`
          CREATE VIRTUAL TABLE IF NOT EXISTS chunks USING fts5(
            path UNINDEXED,
            session_id UNINDEXED,
            worktree UNINDEXED,
            agent UNINDEXED,
            ord UNINDEXED,
            start_line UNINDEXED,
            end_line UNINDEXED,
            context UNINDEXED,
            body,
            title,
            tokenize = 'porter unicode61 remove_diacritics 2'
          );

          CREATE VIRTUAL TABLE IF NOT EXISTS chunks_tri USING fts5(
            path UNINDEXED,
            session_id UNINDEXED,
            ord UNINDEXED,
            start_line UNINDEXED,
            end_line UNINDEXED,
            context UNINDEXED,
            body,
            tokenize = 'trigram'
          );
        `);
        this.db.exec("DELETE FROM sources;");
      }
    } catch {
      // Best-effort migration
    }

    const versionRow = this.db.prepare("SELECT value FROM meta WHERE key = 'schema_version'").get();
    if (!versionRow) {
      this.db.prepare("INSERT INTO meta(key, value) VALUES ('schema_version', '4')").run();
    } else {
      this.db.prepare("UPDATE meta SET value = '4' WHERE key = 'schema_version'").run();
    }
  }

  /** Split content into chunks using intelligent semantic chunker */
  chunkContent(text, options = {}) {
    if (!text) return [];
    const opts = typeof options === "number" ? { maxBytes: options } : (options || {});
    return semanticChunkContent(text, opts);
  }

  /** Index a text, file output, or tool response into SQLite FTS5 */
  indexContent({
    sessionId = null,
    worktree = null,
    agent = "antigravity",
    kind = "content",
    label = null,
    content,
    path: filePath = null,
    sourceId = null,
    mtime = 0
  }) {
    this.open();
    const sid = sessionId || "default";
    const wt = worktree || process.cwd();
    const ag = agent || "antigravity";
    const k = kind || "content";
    const rawText = typeof content === "string" ? content : String(content || "");
    const totalBytes = Buffer.byteLength(rawText, "utf8");

    const targetPath = filePath || sourceId || label || "output";
    const lbl = label || targetPath;
    const now = Date.now();
    const isoDate = new Date(now).toISOString();

    const chunks = this.chunkContent(rawText, { path: targetPath, kind: k });
    const chunkCount = chunks.length;

    const insertSource = this.db.prepare(`
      INSERT OR REPLACE INTO sources (
        path, session_id, worktree, agent, kind, label, bytes_total, chunk_count, created_ts, created_at, accessed_ts, mtime
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);

    const insertChunk = this.db.prepare(`
      INSERT INTO chunks (path, session_id, worktree, agent, ord, start_line, end_line, context, body, title)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);

    const insertChunkTri = this.db.prepare(`
      INSERT INTO chunks_tri (path, session_id, ord, start_line, end_line, context, body)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `);

    insertSource.run(targetPath, sid, wt, ag, k, lbl, totalBytes, chunkCount, now, isoDate, now, mtime || 0);

    // Clean up any existing chunks for this path to ensure idempotent re-indexing
    this.db.prepare("DELETE FROM chunks WHERE path = ?").run(targetPath);
    this.db.prepare("DELETE FROM chunks_tri WHERE path = ?").run(targetPath);

    for (let ord = 0; ord < chunkCount; ord++) {
      const item = chunks[ord];
      const chunkText = typeof item === "string" ? item : (item.text || "");
      const startLine = (typeof item === "object" && item.startLine != null) ? item.startLine : 1;
      const endLine = (typeof item === "object" && item.endLine != null) ? item.endLine : (chunkText.split("\n").length);
      const context = (typeof item === "object" && item.context) ? item.context : lbl;
      const chunkTitle = context && context !== lbl ? `${lbl} > ${context}` : lbl;

      insertChunk.run(targetPath, sid, wt, ag, ord, startLine, endLine, context, chunkText, chunkTitle);
      insertChunkTri.run(targetPath, sid, ord, startLine, endLine, context, chunkText);
    }

    this.pruneStaleSources();

    return {
      path: targetPath,
      bytesTotal: totalBytes,
      chunkCount,
      label: lbl,
      chunks
    };
  }

  /**
   * Ensures a file, directory, or URL is indexed and fresh in SQLite based on file mtime.
   * - If URL: indexes content if not already present.
   * - If File: checks mtime & size; if unindexed or modified, re-indexes Just-In-Time.
   * - If Directory: scans non-exempt files, indexes any unindexed or modified files.
   */
  ensurePathIndexed(targetPath, { cwd = process.cwd(), sessionId = null, agent = "antigravity" } = {}) {
    if (!targetPath || typeof targetPath !== "string") return null;
    this.open();

    // 1. Remote URL
    if (/^https?:\/\//i.test(targetPath)) {
      const existing = this.db.prepare("SELECT path FROM sources WHERE path = ?").get(targetPath);
      return { path: targetPath, isDir: false, isUrl: true, exists: Boolean(existing) };
    }

    // 2. Local File or Directory
    const normCwd = cwd || process.cwd();
    const absPath = path.isAbsolute(targetPath) ? path.normalize(targetPath) : path.resolve(normCwd, targetPath);
    if (!fs.existsSync(absPath)) {
      return { path: targetPath, isDir: false, exists: false };
    }

    let stat;
    try {
      stat = fs.statSync(absPath);
    } catch {
      return { path: targetPath, isDir: false, exists: false };
    }

    // 2a. Single File
    if (stat.isFile()) {
      const canonicalPath = this.resolveFilePath(absPath, normCwd);
      const currentMtime = Math.round(stat.mtimeMs);

      const existing = this.db.prepare("SELECT path, mtime, bytes_total FROM sources WHERE path = ?").get(canonicalPath);
      if (existing && existing.mtime === currentMtime && existing.bytes_total === stat.size) {
        return { path: canonicalPath, isDir: false, exists: true, wasReindexed: false };
      }

      try {
        const content = fs.readFileSync(absPath, "utf8");
        this.indexContent({
          sessionId,
          worktree: normCwd,
          agent,
          kind: "file",
          label: canonicalPath,
          path: canonicalPath,
          content,
          mtime: currentMtime
        });
        return { path: canonicalPath, isDir: false, exists: true, wasReindexed: true };
      } catch {
        return { path: canonicalPath, isDir: false, exists: true, wasReindexed: false };
      }
    }

    // 2b. Directory
    if (stat.isDirectory()) {
      let canonicalDir = this.resolveFilePath(absPath, normCwd);
      if (!canonicalDir.endsWith("/")) canonicalDir += "/";

      const maxBytes = this.getMaxAutoIndexBytes(sessionId);
      const exemptExts = this.getExemptExtensions(sessionId);
      const files = [];

      const scan = (dir, depth = 0) => {
        if (depth > 5) return;
        let entries = [];
        try {
          entries = fs.readdirSync(dir, { withFileTypes: true });
        } catch {
          return;
        }
        for (const ent of entries) {
          if (ent.name.startsWith(".") || ent.name === "node_modules") continue;
          const full = path.join(dir, ent.name);
          if (ent.isDirectory()) {
            scan(full, depth + 1);
          } else if (ent.isFile()) {
            const ext = path.extname(ent.name).toLowerCase();
            if (exemptExts.includes(ext)) continue;
            files.push(full);
          }
        }
      };

      scan(absPath);

      for (const f of files) {
        try {
          const fStat = fs.statSync(f);
          if (maxBytes > 0 && fStat.size > maxBytes) continue;
          const fCanonical = this.resolveFilePath(f, normCwd);
          const fMtime = Math.round(fStat.mtimeMs);
          const exist = this.db.prepare("SELECT path, mtime, bytes_total FROM sources WHERE path = ?").get(fCanonical);
          if (!exist || exist.mtime !== fMtime || exist.bytes_total !== fStat.size) {
            const content = fs.readFileSync(f, "utf8");
            this.indexContent({
              sessionId,
              worktree: normCwd,
              agent,
              kind: "file",
              label: fCanonical,
              path: fCanonical,
              content,
              mtime: fMtime
            });
          }
        } catch {}
      }

      return { path: canonicalDir, isDir: true, exists: true };
    }

    return { path: targetPath, isDir: false, exists: false };
  }

  /** Search indexed chunks using BM25 + Trigram Reciprocal Rank Fusion */
  search({ queries, path: searchPath, sourceId, sessionId, worktree, scope = "session", limit = 5, offset = 0, reverse = false, maxSnippetBytes }) {
    this.open();
    const queryList = Array.isArray(queries) ? queries : [queries];
    const maxHits = Math.min(Math.max(1, limit || 5), 20);
    const skipHits = Math.max(0, offset || 0);

    const target = searchPath || sourceId || null;
    let pathFilter = null;
    let isDir = false;

    if (target) {
      const indexed = this.ensurePathIndexed(target, { cwd: worktree, sessionId });
      if (indexed) {
        pathFilter = indexed.path;
        isDir = Boolean(indexed.isDir);
      } else {
        pathFilter = target;
        isDir = target.endsWith("/");
      }
    }

    const scores = new Map();

    for (const q of queryList) {
      if (!q || typeof q !== "string") continue;
      const porterQ = sanitizeFtsQuery(q);
      const trigramQ = sanitizeTrigramQuery(q);

      // 1. Porter FTS5 with BM25 ranking
      if (porterQ) {
        let sql = `
          SELECT rowid, path, ord, start_line, end_line, context, body, title, session_id, worktree, bm25(chunks) AS rank
          FROM chunks
          WHERE chunks MATCH ?
        `;
        const params = [porterQ];

        if (pathFilter) {
          if (isDir) {
            sql += " AND path LIKE ?";
            params.push(`${pathFilter}%`);
          } else {
            sql += " AND path = ?";
            params.push(pathFilter);
          }
        } else if (scope === "session" && sessionId) {
          sql += " AND session_id = ?";
          params.push(sessionId);
        } else if (scope === "worktree" && worktree) {
          sql += " AND worktree = ?";
          params.push(worktree);
        }

        sql += " ORDER BY rank LIMIT 30";

        try {
          const rows = this.db.prepare(sql).all(...params);
          rows.forEach((row, rankIdx) => {
            const key = `${row.path}:${row.ord}`;
            const rrf = 1.0 / (RRF_K + rankIdx + 1);
            const existing = scores.get(key);
            if (existing) {
              existing.rrfScore += rrf;
              existing.matches.push({ query: q, type: "porter", rank: rankIdx + 1 });
            } else {
              scores.set(key, {
                rowid: row.rowid,
                path: row.path,
                ord: row.ord,
                startLine: row.start_line,
                endLine: row.end_line,
                context: row.context,
                body: row.body,
                title: row.title,
                sessionId: row.session_id,
                worktree: row.worktree,
                rrfScore: rrf,
                matches: [{ query: q, type: "porter", rank: rankIdx + 1 }]
              });
            }
          });
        } catch {
          // Porter match error ignored
        }
      }

      // 2. Trigram FTS5
      if (trigramQ) {
        let sqlTri = `
          SELECT rowid, path, ord, start_line, end_line, context, body, session_id
          FROM chunks_tri
          WHERE chunks_tri MATCH ?
        `;
        const paramsTri = [trigramQ];

        if (pathFilter) {
          if (isDir) {
            sqlTri += " AND path LIKE ?";
            paramsTri.push(`${pathFilter}%`);
          } else {
            sqlTri += " AND path = ?";
            paramsTri.push(pathFilter);
          }
        } else if (scope === "session" && sessionId) {
          sqlTri += " AND session_id = ?";
          paramsTri.push(sessionId);
        }

        sqlTri += " LIMIT 30";

        try {
          const rowsTri = this.db.prepare(sqlTri).all(...paramsTri);
          rowsTri.forEach((row, rankIdx) => {
            const key = `${row.path}:${row.ord}`;
            const rrf = 1.0 / (RRF_K + rankIdx + 1);
            const existing = scores.get(key);
            if (existing) {
              existing.rrfScore += rrf;
              existing.matches.push({ query: q, type: "trigram", rank: rankIdx + 1 });
            } else {
              scores.set(key, {
                rowid: row.rowid,
                path: row.path,
                ord: row.ord,
                startLine: row.start_line,
                endLine: row.end_line,
                context: row.context,
                body: row.body,
                title: "",
                sessionId: row.session_id,
                worktree: "",
                rrfScore: rrf,
                matches: [{ query: q, type: "trigram", rank: rankIdx + 1 }]
              });
            }
          });
        } catch {
          // Trigram match error ignored
        }
      }
    }

    const allHits = Array.from(scores.values());
    if (reverse) {
      // Prioritize tail chunks (newest lines / latest events in logs) first
      allHits.sort((a, b) => {
        if (b.ord !== a.ord) {
          return b.ord - a.ord; // Highest ord (tail of file) first
        }
        return b.rrfScore - a.rrfScore;
      });
    } else {
      allHits.sort((a, b) => b.rrfScore - a.rrfScore);
    }

    const paginated = allHits.slice(skipHits, skipHits + maxHits);

    const formatted = paginated.map((hit) => {
      const snippet = generateContextSnippet({
        body: hit.body,
        queryList,
        context: hit.context,
        startLine: hit.startLine,
        endLine: hit.endLine,
        maxBytes: maxSnippetBytes || SNIPPET_MAX_BYTES
      });
      return {
        path: hit.path,
        ord: hit.ord,
        startLine: hit.startLine || 1,
        endLine: hit.endLine || 1,
        context: hit.context || hit.title || hit.path,
        title: hit.title || hit.path,
        rrfScore: parseFloat(hit.rrfScore.toFixed(5)),
        matches: hit.matches.length,
        snippet
      };
    });

    return {
      queries: queryList,
      path: pathFilter || null,
      reverse: Boolean(reverse),
      hits: formatted,
      totalMatches: allHits.length,
      returned: formatted.length,
      offset: skipHits,
      limit: maxHits
    };
  }

  /** Record an event in the session journal */
  recordEvent({
    sessionId,
    worktree,
    agent,
    kind,
    tool,
    key,
    path: filePath,
    ok = 1,
    exitCode,
    bytes = 0,
    body = "",
    turn = 0,
    sandboxBytes = 0,
    returnedBytes = 0
  }) {
    this.open();
    const now = new Date().toISOString();
    const insert = this.db.prepare(`
      INSERT INTO events (
        session_id, worktree, agent, kind, tool, key, path, ok, exit_code, bytes, body, turn, sandbox_bytes, returned_bytes, created_at, accessed_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);

    const result = insert.run(
      sessionId || "default",
      worktree || process.cwd(),
      agent || "antigravity",
      kind || "tool",
      tool || null,
      key || null,
      filePath || null,
      ok ? 1 : 0,
      exitCode ?? (ok ? 0 : 1),
      bytes,
      body || null,
      turn,
      sandboxBytes,
      returnedBytes,
      now,
      now
    );

    return { seq: result.lastInsertRowid };
  }

  /** Retrieve compaction / state resume block */
  getResumeState(sessionId, limit = 5, options = {}) {
    this.open();
    const sid = sessionId || "default";
    const sinceTs = options.sinceTs || null;
    const sinceIso = sinceTs ? new Date(sinceTs).toISOString() : null;

    let sources;
    let events;

    if (sid === "all" || sid === "lifetime") {
      sources = this.db
        .prepare(`
          SELECT id, label, kind, bytes_total, chunk_count, created_at
          FROM sources
          ORDER BY created_ts DESC
          LIMIT ?
        `)
        .all(limit);
      events = this.db
        .prepare(`
          SELECT seq, tool, kind, key, path, ok, sandbox_bytes, returned_bytes, agent, created_at
          FROM events
          ORDER BY seq DESC
          LIMIT ?
        `)
        .all(limit);
    } else if (sid === "app" && sinceIso) {
      sources = this.db
        .prepare(`
          SELECT path, label, kind, bytes_total, chunk_count, created_at
          FROM sources
          WHERE created_at >= ? OR created_ts >= ?
          ORDER BY created_ts DESC
          LIMIT ?
        `)
        .all(sinceIso, sinceTs, limit);
      events = this.db
        .prepare(`
          SELECT seq, tool, kind, key, path, ok, sandbox_bytes, returned_bytes, agent, created_at
          FROM events
          WHERE created_at >= ?
          ORDER BY seq DESC
          LIMIT ?
        `)
        .all(sinceIso, limit);
    } else {
      sources = this.db
        .prepare(`
          SELECT path, label, kind, bytes_total, chunk_count, created_at
          FROM sources
          WHERE session_id = ?
          ORDER BY created_ts DESC
          LIMIT ?
        `)
        .all(sid, limit);
      events = this.db
        .prepare(`
          SELECT seq, tool, kind, key, path, ok, sandbox_bytes, returned_bytes, agent, created_at
          FROM events
          WHERE session_id = ?
          ORDER BY seq DESC
          LIMIT ?
        `)
        .all(sid, limit);
    }

    return {
      sessionId: sid,
      recentSources: sources,
      recentEvents: events
    };
  }

  /** Retrieve full event body by sequence number */
  getEventBySeq(seq) {
    this.open();
    const row = this.db.prepare("SELECT * FROM events WHERE seq = ?").get(seq);
    return row || null;
  }

  /** Retrieve all chunks for a specific path */
  getSourceChunks(targetPath) {
    this.open();
    if (!targetPath) return [];
    return this.db
      .prepare("SELECT ord, body, title, start_line, end_line, context FROM chunks WHERE path = ? ORDER BY ord ASC")
      .all(targetPath);
  }

  /** Calculate token savings & statistics */
  getStats(sessionId, options = {}) {
    this.open();
    const sid = sessionId || "default";
    const sinceTs = options.sinceTs || null;
    const sinceIso = sinceTs ? new Date(sinceTs).toISOString() : null;

    let eventStats;
    let sourceStats;
    let unrecordedSources;

    if (sid === "all" || sid === "lifetime") {
      eventStats = this.db.prepare(`
        SELECT
          COUNT(*) AS total_calls,
          SUM(MAX(sandbox_bytes, bytes, returned_bytes)) AS total_raw_bytes,
          SUM(returned_bytes) AS total_returned_bytes,
          SUM(MAX(0, MAX(sandbox_bytes, bytes, returned_bytes) - returned_bytes)) AS total_kept_out_bytes
        FROM events
      `).get();

      sourceStats = this.db.prepare(`
        SELECT
          COUNT(*) AS total_sources,
          SUM(bytes_total) AS total_source_bytes,
          SUM(chunk_count) AS total_chunks
        FROM sources
      `).get();

      unrecordedSources = this.db.prepare(`
        SELECT COALESCE(SUM(bytes_total), 0) AS unrecorded_source_bytes
        FROM sources s
        WHERE s.kind NOT IN ('execution', 'batch', 'tool_output', 'exec')
          AND NOT EXISTS (
            SELECT 1 FROM events e
            WHERE (
              (e.tool = 'ctx_index' AND e.sandbox_bytes = s.bytes_total)
              OR e.path = s.path
              OR e.path = s.label
            )
          )
      `).get();
    } else if (sid === "app" && sinceIso) {
      eventStats = this.db.prepare(`
        SELECT
          COUNT(*) AS total_calls,
          SUM(MAX(sandbox_bytes, bytes, returned_bytes)) AS total_raw_bytes,
          SUM(returned_bytes) AS total_returned_bytes,
          SUM(MAX(0, MAX(sandbox_bytes, bytes, returned_bytes) - returned_bytes)) AS total_kept_out_bytes
        FROM events
        WHERE created_at >= ?
      `).get(sinceIso);

      sourceStats = this.db.prepare(`
        SELECT
          COUNT(*) AS total_sources,
          SUM(bytes_total) AS total_source_bytes,
          SUM(chunk_count) AS total_chunks
        FROM sources
        WHERE created_at >= ? OR created_ts >= ?
      `).get(sinceIso, sinceTs);

      unrecordedSources = this.db.prepare(`
        SELECT COALESCE(SUM(bytes_total), 0) AS unrecorded_source_bytes
        FROM sources s
        WHERE (created_at >= ? OR created_ts >= ?)
          AND s.kind NOT IN ('execution', 'batch', 'tool_output', 'exec')
          AND NOT EXISTS (
            SELECT 1 FROM events e
            WHERE (
              (e.tool = 'ctx_index' AND e.sandbox_bytes = s.bytes_total)
              OR e.path = s.path
              OR e.path = s.label
            )
          )
      `).get(sinceIso, sinceTs);
    } else {
      eventStats = this.db.prepare(`
        SELECT
          COUNT(*) AS total_calls,
          SUM(MAX(sandbox_bytes, bytes, returned_bytes)) AS total_raw_bytes,
          SUM(returned_bytes) AS total_returned_bytes,
          SUM(MAX(0, MAX(sandbox_bytes, bytes, returned_bytes) - returned_bytes)) AS total_kept_out_bytes
        FROM events
        WHERE session_id = ?
      `).get(sid);

      sourceStats = this.db.prepare(`
        SELECT
          COUNT(*) AS total_sources,
          SUM(bytes_total) AS total_source_bytes,
          SUM(chunk_count) AS total_chunks
        FROM sources
        WHERE session_id = ?
      `).get(sid);

      unrecordedSources = this.db.prepare(`
        SELECT COALESCE(SUM(bytes_total), 0) AS unrecorded_source_bytes
        FROM sources s
        WHERE s.session_id = ?
          AND s.kind NOT IN ('execution', 'batch', 'tool_output', 'exec')
          AND NOT EXISTS (
            SELECT 1 FROM events e
            WHERE e.session_id = s.session_id
              AND (
                (e.tool = 'ctx_index' AND e.sandbox_bytes = s.bytes_total)
                OR e.path = s.path
                OR e.path = s.label
              )
          )
      `).get(sid);
    }

    const unrecordedBytes = unrecordedSources?.unrecorded_source_bytes || 0;
    const totalRawBytes = (eventStats?.total_raw_bytes || 0) + unrecordedBytes;
    const totalReturnedBytes = eventStats?.total_returned_bytes || 0;
    const keptOutBytes = (eventStats?.total_kept_out_bytes || 0) + unrecordedBytes;
    const savingsPercent = totalRawBytes > 0 ? Number(((keptOutBytes / totalRawBytes) * 100).toFixed(1)) : 0;
    const tokensKeptOut = Math.round(keptOutBytes / 4);

    return {
      sessionId: sid,
      calls: eventStats?.total_calls || 0,
      totalRawBytes,
      totalReturnedBytes,
      keptOutBytes,
      savingsPercent,
      tokensKeptOut,
      sourcesCount: sourceStats?.total_sources || 0,
      chunksCount: sourceStats?.total_chunks || 0
    };
  }

  /** Purge stored data */
  purge({ scope = "session", sessionId, worktree, sinceTs }) {
    this.open();
    if (scope === "all" || scope === "lifetime") {
      this.db.exec(`
        DELETE FROM chunks;
        DELETE FROM chunks_tri;
        DELETE FROM sources;
        DELETE FROM events;
        VACUUM;
      `);
      return { ok: true, purged: "all" };
    }

    if (scope === "app" && sinceTs) {
      const sinceIso = new Date(sinceTs).toISOString();
      const paths = this.db.prepare("SELECT path FROM sources WHERE created_at >= ? OR created_ts >= ?").all(sinceIso, sinceTs).map((r) => r.path);
      for (const p of paths) {
        this.db.prepare("DELETE FROM chunks WHERE path = ?").run(p);
        this.db.prepare("DELETE FROM chunks_tri WHERE path = ?").run(p);
      }
      this.db.prepare("DELETE FROM sources WHERE created_at >= ? OR created_ts >= ?").run(sinceIso, sinceTs);
      this.db.prepare("DELETE FROM events WHERE created_at >= ?").run(sinceIso);
      return { ok: true, purged: "app", count: paths.length };
    }

    if (scope === "worktree" && worktree) {
      this.db.prepare("DELETE FROM chunks WHERE worktree = ?").run(worktree);
      this.db.prepare("DELETE FROM sources WHERE worktree = ?").run(worktree);
      this.db.prepare("DELETE FROM events WHERE worktree = ?").run(worktree);
      return { ok: true, purged: "worktree", worktree };
    }

    const sid = sessionId || "default";
    this.db.prepare("DELETE FROM chunks WHERE session_id = ?").run(sid);
    this.db.prepare("DELETE FROM chunks_tri WHERE session_id = ?").run(sid);
    this.db.prepare("DELETE FROM sources WHERE session_id = ?").run(sid);
    this.db.prepare("DELETE FROM events WHERE session_id = ?").run(sid);
    return { ok: true, purged: "session", sessionId: sid };
  }

  /** Run diagnostic checks */
  doctor() {
    try {
      this.open();
      const versionRow = this.db.prepare("SELECT value FROM meta WHERE key = 'schema_version'").get();
      const ftsCheck = this.db.prepare("SELECT count(*) as cnt FROM chunks").get();
      const stat = fs.existsSync(this.dbPath) ? fs.statSync(this.dbPath) : null;

      return {
        status: "HEALTHY",
        allOk: true,
        dbPath: this.dbPath,
        dbSizeBytes: stat ? stat.size : 0,
        schemaVersion: versionRow ? versionRow.value : "unknown",
        totalChunksIndexed: ftsCheck ? ftsCheck.cnt : 0,
        sqliteVersion: process.versions.node
      };
    } catch (err) {
      return {
        status: "ERROR",
        allOk: false,
        error: String(err && err.message ? err.message : err)
      };
    }
  }

  /** Read a key from meta table */
  getMeta(key, defaultValue = null) {
    this.open();
    const row = this.db.prepare("SELECT value FROM meta WHERE key = ?").get(key);
    return row ? row.value : defaultValue;
  }

  /** Write a key-value pair to meta table */
  setMeta(key, value) {
    this.open();
    this.db
      .prepare(
        "INSERT INTO meta (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value"
      )
      .run(key, String(value));
  }

  /** Delete a key from meta table */
  deleteMeta(key) {
    this.open();
    this.db.prepare("DELETE FROM meta WHERE key = ?").run(key);
  }

  /** Clear all session-level overrides so new sessions start with lifetime defaults */
  clearSessionOverrides() {
    this.open();
    this.db.prepare("DELETE FROM meta WHERE key LIKE '%:session:app' OR key LIKE '%:session:default'").run();
  }

  /** Check if a session or conversation has an explicit cutoff override */
  hasCutoffOverride(sessionId) {
    if (!sessionId || sessionId === "all" || sessionId === "lifetime") return false;
    this.open();
    return this.getMeta(`cutoff:session:${sessionId}`) !== null;
  }

  /** Get active cutoff in bytes (0 = none/disabled) */
  getCutoff(sessionId = null) {
    this.open();
    // 1. Session/Conversation override
    if (sessionId && sessionId !== "all" && sessionId !== "lifetime") {
      const sessionCutoff = this.getMeta(`cutoff:session:${sessionId}`);
      if (sessionCutoff !== null) {
        return parseInt(sessionCutoff, 10);
      }
      // If checking a conversation, fall back to the app session cutoff if set
      if (sessionId !== "app") {
        const appCutoff = this.getMeta("cutoff:session:app");
        if (appCutoff !== null) {
          return parseInt(appCutoff, 10);
        }
      }
    }

    // 2. Environment variable if explicitly set
    const envVal = process.env.CONTEXT_MODE_MAX_READ_BYTES;
    if (envVal) {
      const parsed = parseInt(envVal, 10);
      if (!Number.isNaN(parsed)) return parsed;
    }

    // 3. Saved global setting in SQLite
    const globalCutoff = this.getMeta("cutoff:global");
    if (globalCutoff !== null) {
      return parseInt(globalCutoff, 10);
    }

    // 4. Default 8 KB
    return 8192;
  }

  /** Set cutoff in bytes (0 = none/disabled) */
  setCutoff(bytes, sessionId = null) {
    this.open();
    const val = parseInt(bytes, 10);
    const numVal = Number.isNaN(val) ? 8192 : Math.max(0, val);
    if (sessionId && sessionId !== "all" && sessionId !== "lifetime") {
      this.setMeta(`cutoff:session:${sessionId}`, numVal);
    } else {
      this.setMeta("cutoff:global", numVal);
      // When changing cutoff in lifetime, clear session-level and default overrides
      // so new sessions and new conversations inherit this global setting as their default.
      this.deleteMeta("cutoff:session:app");
      this.deleteMeta("cutoff:session:default");
    }
    return numVal;
  }

  /** Get active auto-indexing max file size in bytes (default 50 MB, 0 = disabled) */
  getMaxAutoIndexBytes(sessionId = null) {
    this.open();
    // 1. Session/Conversation override
    if (sessionId && sessionId !== "all" && sessionId !== "lifetime") {
      const sessionVal = this.getMeta(`max_auto_index:session:${sessionId}`);
      if (sessionVal !== null) {
        return parseInt(sessionVal, 10);
      }
      if (sessionId !== "app") {
        const appVal = this.getMeta("max_auto_index:session:app");
        if (appVal !== null) {
          return parseInt(appVal, 10);
        }
      }
    }

    // 2. Environment variable if explicitly set
    const envVal = process.env.CONTEXT_MODE_MAX_AUTO_INDEX_BYTES;
    if (envVal) {
      const parsed = parseInt(envVal, 10);
      if (!Number.isNaN(parsed)) return parsed;
    }

    // 3. Saved global setting in SQLite
    const globalVal = this.getMeta("max_auto_index:global");
    if (globalVal !== null) {
      return parseInt(globalVal, 10);
    }

    // 4. Default 50 MB
    return DEFAULT_MAX_AUTO_INDEX_BYTES;
  }

  /** Set auto-indexing max file size in bytes (0 = disabled) */
  setMaxAutoIndexBytes(bytes, sessionId = null) {
    this.open();
    const val = parseInt(bytes, 10);
    const numVal = Number.isNaN(val) ? DEFAULT_MAX_AUTO_INDEX_BYTES : Math.max(0, val);
    if (sessionId && sessionId !== "all" && sessionId !== "lifetime") {
      this.setMeta(`max_auto_index:session:${sessionId}`, numVal);
    } else {
      this.setMeta("max_auto_index:global", numVal);
      this.deleteMeta("max_auto_index:session:app");
      this.deleteMeta("max_auto_index:session:default");
    }
    return numVal;
  }

  /** Resolve intuitive file path: workspace-relative when inside project, normalized absolute when outside */
  resolveFilePath(filePath, cwd = process.cwd()) {
    return resolveFilePath(filePath, cwd);
  }

  resolveFileSourceId(filePath, cwd = process.cwd()) {
    return this.resolveFilePath(filePath, cwd);
  }

  /** Internal helper to recompute and save the combined exempt_ext list */
  _recomputeExemptExtensions(sessionId = null) {
    const activeCats = this.getExemptCategories(sessionId);
    const customs = this.getCustomExemptExtensions(sessionId);
    const combined = new Set(customs);
    for (const catId of activeCats) {
      const cat = FILE_CATEGORIES[catId];
      if (cat && Array.isArray(cat.extensions)) {
        for (const ext of cat.extensions) {
          combined.add(ext);
        }
      }
    }
    const cleanList = Array.from(combined);
    const serialized = JSON.stringify(cleanList);
    if (sessionId && sessionId !== "all") {
      this.setMeta(`exempt_ext:session:${sessionId}`, serialized);
    } else {
      this.setMeta("exempt_ext:global", serialized);
    }
    return cleanList;
  }

  /** Get list of active category IDs */
  getExemptCategories(sessionId = null) {
    this.open();
    if (sessionId && sessionId !== "all") {
      const raw = this.getMeta(`exempt_categories:session:${sessionId}`);
      if (raw) {
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) return parsed;
        } catch {}
      }
      if (sessionId !== "app") {
        const appRaw = this.getMeta("exempt_categories:session:app");
        if (appRaw) {
          try {
            const parsed = JSON.parse(appRaw);
            if (Array.isArray(parsed)) return parsed;
          } catch {}
        }
      }
    }
    const globalRaw = this.getMeta("exempt_categories:global");
    if (globalRaw) {
      try {
        const parsed = JSON.parse(globalRaw);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return [...DEFAULT_ACTIVE_CATEGORIES];
  }

  /** Set list of active category IDs */
  setExemptCategories(categories, sessionId = null) {
    this.open();
    const cleanList = Array.isArray(categories)
      ? Array.from(new Set(categories.map(c => String(c).trim().toLowerCase()).filter(c => FILE_CATEGORIES[c])))
      : [...DEFAULT_ACTIVE_CATEGORIES];
    const serialized = JSON.stringify(cleanList);
    if (sessionId && sessionId !== "all") {
      this.setMeta(`exempt_categories:session:${sessionId}`, serialized);
    } else {
      this.setMeta("exempt_categories:global", serialized);
    }
    this._recomputeExemptExtensions(sessionId);
    return cleanList;
  }

  /** Toggle an exempt category on or off */
  toggleExemptCategory(categoryId, enabled = null, sessionId = null) {
    const active = this.getExemptCategories(sessionId);
    const cat = String(categoryId || "").trim().toLowerCase();
    if (!FILE_CATEGORIES[cat]) return active;

    const isCurrentlyActive = active.includes(cat);
    const shouldEnable = enabled !== null ? Boolean(enabled) : !isCurrentlyActive;

    let updated;
    if (shouldEnable && !isCurrentlyActive) {
      updated = [...active, cat];
    } else if (!shouldEnable && isCurrentlyActive) {
      updated = active.filter(c => c !== cat);
    } else {
      return active;
    }
    return this.setExemptCategories(updated, sessionId);
  }

  /** Get custom file extensions (explicitly added by user) */
  getCustomExemptExtensions(sessionId = null) {
    this.open();
    if (sessionId && sessionId !== "all") {
      const raw = this.getMeta(`custom_exempt_ext:session:${sessionId}`);
      if (raw) {
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) return parsed;
        } catch {}
      }
      if (sessionId !== "app") {
        const appRaw = this.getMeta("custom_exempt_ext:session:app");
        if (appRaw) {
          try {
            const parsed = JSON.parse(appRaw);
            if (Array.isArray(parsed)) return parsed;
          } catch {}
        }
      }
    }
    const globalRaw = this.getMeta("custom_exempt_ext:global");
    if (globalRaw) {
      try {
        const parsed = JSON.parse(globalRaw);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return [];
  }

  /** Set custom file extensions */
  setCustomExemptExtensions(extensions, sessionId = null) {
    this.open();
    const cleanList = Array.from(
      new Set(
        (Array.isArray(extensions) ? extensions : [])
          .map((ext) => String(ext || "").trim().toLowerCase())
          .filter(Boolean)
          .map((ext) => (ext.startsWith(".") ? ext : `.${ext}`))
      )
    );
    const serialized = JSON.stringify(cleanList);
    if (sessionId && sessionId !== "all") {
      this.setMeta(`custom_exempt_ext:session:${sessionId}`, serialized);
    } else {
      this.setMeta("custom_exempt_ext:global", serialized);
    }
    this._recomputeExemptExtensions(sessionId);
    return cleanList;
  }

  /** Add custom file extension */
  addCustomExemptExtension(ext, sessionId = null) {
    const list = this.getCustomExemptExtensions(sessionId);
    const cleanExt = String(ext || "").trim().toLowerCase();
    if (!cleanExt) return list;
    const formatted = cleanExt.startsWith(".") ? cleanExt : `.${cleanExt}`;
    if (!list.includes(formatted)) {
      list.push(formatted);
      this.setCustomExemptExtensions(list, sessionId);
    }
    return list;
  }

  /** Remove custom file extension */
  removeCustomExemptExtension(ext, sessionId = null) {
    const list = this.getCustomExemptExtensions(sessionId);
    const cleanExt = String(ext || "").trim().toLowerCase();
    const formatted = cleanExt.startsWith(".") ? cleanExt : `.${cleanExt}`;
    const filtered = list.filter((e) => e !== formatted);
    this.setCustomExemptExtensions(filtered, sessionId);
    return filtered;
  }

  /** Get list of file extensions exempt from the size cutoff */
  getExemptExtensions(sessionId = null) {
    this.open();
    // 1. Session/Conversation override
    if (sessionId && sessionId !== "all") {
      const raw = this.getMeta(`exempt_ext:session:${sessionId}`);
      if (raw) {
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) return parsed;
        } catch {}
      }
      // If checking a conversation, fall back to the app session exemptions if set
      if (sessionId !== "app") {
        const appRaw = this.getMeta("exempt_ext:session:app");
        if (appRaw) {
          try {
            const parsed = JSON.parse(appRaw);
            if (Array.isArray(parsed)) return parsed;
          } catch {}
        }
      }
    }
    const globalRaw = this.getMeta("exempt_ext:global");
    if (globalRaw) {
      try {
        const parsed = JSON.parse(globalRaw);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return [...DEFAULT_EXEMPT_EXTENSIONS];
  }

  /** Get live human-readable summary of active exempt categories and custom extensions */
  getExemptSummary(sessionId = null) {
    this.open();
    const activeCats = this.getExemptCategories(sessionId);
    const customs = this.getCustomExemptExtensions(sessionId);
    return formatExemptSummary({ activeCats, customs });
  }

  /** Set list of file extensions exempt from the size cutoff */
  setExemptExtensions(extensions, sessionId = null) {
    this.open();
    if (extensions === null) {
      this.setExemptCategories(null, sessionId);
      this.setCustomExemptExtensions([], sessionId);
      return this.getExemptExtensions(sessionId);
    }
    const cleanList = Array.from(
      new Set(
        (Array.isArray(extensions) ? extensions : [])
          .map((ext) => String(ext || "").trim().toLowerCase())
          .filter(Boolean)
          .map((ext) => (ext.startsWith(".") ? ext : `.${ext}`))
      )
    );
    const activeCats = [];
    for (const [catId, catObj] of Object.entries(FILE_CATEGORIES)) {
      if (catObj.extensions.some((e) => cleanList.includes(e))) {
        activeCats.push(catId);
      }
    }
    const allCatExts = new Set(activeCats.flatMap((c) => FILE_CATEGORIES[c]?.extensions || []));
    const customs = cleanList.filter((e) => !allCatExts.has(e));

    this.setExemptCategories(activeCats, sessionId);
    this.setCustomExemptExtensions(customs, sessionId);
    return this.getExemptExtensions(sessionId);
  }

  /** Add an exempt file extension */
  addExemptExtension(ext, sessionId = null) {
    this.addCustomExemptExtension(ext, sessionId);
    return this.getExemptExtensions(sessionId);
  }

  /** Remove an exempt file extension */
  removeExemptExtension(ext, sessionId = null) {
    this.removeCustomExemptExtension(ext, sessionId);
    return this.getExemptExtensions(sessionId);
  }

  /** Check if protection is paused (true = paused, false = active) */
  isPaused(sessionId = null) {
    this.open();
    // 1. Session/Conversation override
    if (sessionId && sessionId !== "all") {
      const sessionPaused = this.getMeta(`paused:session:${sessionId}`);
      if (sessionPaused !== null) {
        return sessionPaused === "true" || sessionPaused === "1";
      }
      // If checking a conversation, fall back to the app session paused state if set
      if (sessionId !== "app") {
        const appPaused = this.getMeta("paused:session:app");
        if (appPaused !== null) {
          return appPaused === "true" || appPaused === "1";
        }
      }
    }
    // 2. Global paused setting
    const globalPaused = this.getMeta("paused:global");
    if (globalPaused !== null) {
      return globalPaused === "true" || globalPaused === "1";
    }
    return false;
  }

  /** Set protection paused state (true = paused, false = active) */
  setPaused(paused, sessionId = null) {
    this.open();
    const boolVal = Boolean(paused && paused !== "false" && paused !== "0");
    const strVal = boolVal ? "true" : "false";
    if (sessionId && sessionId !== "all") {
      this.setMeta(`paused:session:${sessionId}`, strVal);
    } else {
      this.setMeta("paused:global", strVal);
    }
    return boolVal;
  }

  /**
   * Automatic LRU pruning: limits the number of indexed sources and chunks
   * to avoid unbounded database expansion.
   */
  pruneStaleSources({ maxSourcesPerSession = 50, maxTotalSources = 200 } = {}) {
    this.open();
    try {
      // 1. Prune sources exceeding per-session cap
      const excessSessionSources = this.db
        .prepare(`
          SELECT path FROM sources
          WHERE path NOT IN (
            SELECT path FROM sources s2
            WHERE s2.session_id = sources.session_id
            ORDER BY s2.created_ts DESC
            LIMIT ?
          )
        `)
        .all(maxSourcesPerSession);

      if (excessSessionSources.length > 0) {
        const paths = excessSessionSources.map((r) => r.path);
        const placeholders = paths.map(() => "?").join(",");
        this.db.prepare(`DELETE FROM chunks WHERE path IN (${placeholders})`).run(...paths);
        this.db.prepare(`DELETE FROM chunks_tri WHERE path IN (${placeholders})`).run(...paths);
        this.db.prepare(`DELETE FROM sources WHERE path IN (${placeholders})`).run(...paths);
      }

      // 2. Prune global total sources if over cap
      const totalCountRow = this.db.prepare("SELECT count(*) as count FROM sources").get();
      if (totalCountRow && totalCountRow.count > maxTotalSources) {
        const deleteCount = totalCountRow.count - maxTotalSources;
        const oldest = this.db
          .prepare("SELECT path FROM sources ORDER BY created_ts ASC LIMIT ?")
          .all(deleteCount);

        if (oldest.length > 0) {
          const paths = oldest.map((r) => r.path);
          const placeholders = paths.map(() => "?").join(",");
          this.db.prepare(`DELETE FROM chunks WHERE path IN (${placeholders})`).run(...paths);
          this.db.prepare(`DELETE FROM chunks_tri WHERE path IN (${placeholders})`).run(...paths);
          this.db.prepare(`DELETE FROM sources WHERE path IN (${placeholders})`).run(...paths);
        }
      }
    } catch {
      // Best-effort auto-prune
    }
  }
}
