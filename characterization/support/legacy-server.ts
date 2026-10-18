import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import { copyFileSync, existsSync, mkdtempSync, mkdirSync, rmSync } from 'node:fs';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
export const REPO_ROOT = resolve(HERE, '../..');
export const LEGACY_DIR = join(REPO_ROOT, 'legacy');
const PHP_DIR = join(HERE, 'php');
const SCHEMA = join(LEGACY_DIR, 'TVShowScraperDB.sql');
const SEED = join(REPO_ROOT, 'characterization/fixtures/seed.sql');
const PHP = process.env.PHP_BIN ?? 'php';

export type RpcParams = Record<string, string | number>;

/** Response of legacy api.php: parsed JSON when possible, raw text otherwise (e.g. PHP warnings in output). */
export type RpcResponse = { httpStatus: number; body: unknown };

export interface LegacyServer {
  rpc(action: string, params?: RpcParams): Promise<RpcResponse>;
  /** Restores the seeded DB. Call before each test. */
  reset(): void;
  /** All tables of the current DB as JSON. */
  dump(): Record<string, Record<string, unknown>[]>;
  stop(): Promise<void>;
}

function freePort(): Promise<number> {
  return new Promise((ok, ko) => {
    const srv = createServer();
    srv.unref();
    srv.on('error', ko);
    srv.listen(0, '127.0.0.1', () => {
      const { port } = srv.address() as { port: number };
      srv.close(() => ok(port));
    });
  });
}

async function waitUntilUp(url: string, proc: ChildProcess): Promise<void> {
  for (let i = 0; i < 100; i++) {
    if (proc.exitCode !== null) throw new Error(`php -S exited with code ${proc.exitCode}`);
    try {
      await fetch(url);
      return;
    } catch {
      await new Promise((r) => setTimeout(r, 50));
    }
  }
  throw new Error(`Legacy server did not start at ${url}`);
}

export async function startLegacyServer(): Promise<LegacyServer> {
  // config.php is read after env vars and would override them, possibly pointing tests at a real DB.
  if (existsSync(join(LEGACY_DIR, 'config.php'))) {
    throw new Error('legacy/config.php exists: move it away before running characterization tests');
  }

  const workDir = mkdtempSync(join(tmpdir(), 'tvscraper-legacy-'));
  const pristineDb = join(workDir, 'seed.db');
  const dbFile = join(workDir, 'test.db');
  const logDir = join(workDir, 'log');
  mkdirSync(logDir);

  execFileSync(PHP, [join(PHP_DIR, 'build-db.php'), SCHEMA, SEED, pristineDb]);
  copyFileSync(pristineDb, dbFile);

  const port = await freePort();
  const baseUrl = `http://127.0.0.1:${port}`;
  const proc = spawn(PHP, ['-S', `127.0.0.1:${port}`, '-t', LEGACY_DIR], {
    cwd: LEGACY_DIR,
    env: {
      ...process.env,
      TVSCRAPER_SQLITE_FILE: dbFile,
      TVSCRAPER_LOG_DIR: logDir,
      TVSCRAPER_BROWSER_CACHE_DIR: workDir,
      TVSCRAPER_BROWSER_COOKIES_DIR: workDir,
    },
    stdio: 'ignore',
  });
  await waitUntilUp(baseUrl, proc);

  return {
    async rpc(action, params = {}) {
      const form = new URLSearchParams({ action });
      for (const [k, v] of Object.entries(params)) form.append(k, String(v));
      const res = await fetch(`${baseUrl}/api.php`, { method: 'POST', body: form });
      const text = await res.text();
      let body: unknown;
      try {
        body = JSON.parse(text);
      } catch {
        body = { raw: text };
      }
      return { httpStatus: res.status, body };
    },
    reset() {
      copyFileSync(pristineDb, dbFile);
    },
    dump() {
      return JSON.parse(execFileSync(PHP, [join(PHP_DIR, 'dump-db.php'), dbFile], { encoding: 'utf8' }));
    },
    async stop() {
      proc.kill();
      rmSync(workDir, { recursive: true, force: true });
    },
  };
}
